<?php

namespace App\Actions;

use App\Enums\MemberSubscriptionStatus;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\MemberSubscription;
use App\Support\Audit;
use App\Support\PaymentDiscount;
use App\Support\PlanLimits;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ManageMemberSubscription
{
    public function assign(Member $member, array $data): MemberSubscription
    {
        if (! array_key_exists('_pricing_snapshot', $data) && app(CurrentGym::class)->get()->pricing_settings !== null
            && ! MemberSubscription::withoutGlobalScope('branch')->where('request_key', $data['request_key'])->exists()) {
            $cart = [...Arr::only($data, ['membership_plan_id', 'operation', 'request_key']),
                'starts_on' => $data['starts_on'] ?? now(app(CurrentGym::class)->get()->timezone)->toDateString(),
                'pricing_discount_id' => $member->pricing_discount_id,
                'eligibility_verified' => $member->eligibility_verified_at !== null,
                'eligibility_from' => $member->eligibility_from,
                'eligibility_through' => $member->eligibility_through];
            $checkout = app(PurchaseMemberOfferings::class)->handle($member, $cart);

            return MemberSubscription::findOrFail($checkout->summary['lines'][0]['purchase_id']);
        }
        $mode = $data['discount_mode'] ?? 'percentage';
        $points = $mode === 'percentage' ? PaymentDiscount::percentage((string) ($data['discount_percent'] ?? '0')) : 0;
        $fixed = $mode === 'peso' ? PaymentDiscount::pesos((string) ($data['discount_php'] ?? '')) : 0;
        $type = $data['discount_type'] ?? 'regular';
        $other = $type === 'others' ? trim($data['discount_other'] ?? '') : null;

        return DB::transaction(function () use ($member, $data, $points, $fixed, $mode, $type, $other) {
            [$gym, $member] = $this->lockMember($member);
            $existing = MemberSubscription::withoutGlobalScope('branch')->where('gym_id', $gym->id)->where('request_key', $data['request_key'])->first();
            if ($existing) {
                if ($existing->member_id !== $member->id || $existing->membership_plan_id !== (int) $data['membership_plan_id'] || $existing->operation !== $data['operation']
                    || ($data['operation'] === 'assign' && $existing->starts_at->setTimezone($gym->timezone)->toDateString() !== $data['starts_on'])
                    || $existing->discount_basis_points !== $points || $existing->discount_type !== $type || $existing->discount_other !== $other
                    || $existing->discount_mode !== $mode || ($mode === 'peso' && $existing->original_price_centavos - $existing->price_centavos !== $fixed)) {
                    throw ValidationException::withMessages(['request_key' => __('This submission has already been used. Refresh and try again.')]);
                }

                return $existing;
            }
            if (! $member->is_active) {
                throw ValidationException::withMessages(['membership_plan_id' => __('Activate the member profile before assigning a membership.')]);
            }
            $plan = MembershipPlan::lockForUpdate()->findOrFail($data['membership_plan_id']);
            if (! $plan->is_active) {
                throw ValidationException::withMessages(['membership_plan_id' => __('This plan is archived. Choose an available plan.')]);
            }
            $savings = $mode === 'peso' ? $fixed : PaymentDiscount::savings($plan->price_centavos, $points);
            if ($plan->branch_ids && ! in_array($member->home_branch_id, $plan->branch_ids)) {
                throw ValidationException::withMessages(['membership_plan_id' => __('This subscription is not available at the member’s registration branch.')]);
            }
            if ($savings > $plan->price_centavos) {
                throw ValidationException::withMessages(['discount_php' => __('The discount cannot exceed the membership price.')]);
            }
            $periods = $member->subscriptions()->where('status', '!=', MemberSubscriptionStatus::Cancelled->value)->lockForUpdate()->get();
            if ($data['operation'] === 'renew') {
                if ($periods->isEmpty()) {
                    throw ValidationException::withMessages(['operation' => __('Assign an initial membership before renewing.')]);
                }
                if ($periods->contains(fn ($period) => $period->status === MemberSubscriptionStatus::Suspended && $period->ends_at->isFuture())) {
                    throw ValidationException::withMessages(['operation' => __('Resume or cancel the suspended membership before renewing.')]);
                }
                $latest = $periods->sortByDesc('ends_at')->first()->ends_at;
                $today = CarbonImmutable::now($gym->timezone)->startOfDay()->utc();
                $start = $latest->greaterThan($today) ? $latest : $today;
            } else {
                $start = CarbonImmutable::createFromFormat('!Y-m-d', $data['starts_on'], $gym->timezone)->utc();
            }
            $localStart = $start->setTimezone($gym->timezone);
            $end = ($plan->duration_unit === 'months' ? $localStart->addMonthsNoOverflow($plan->duration_amount) : $localStart->addDays($plan->duration_amount))->utc();
            if ($periods->contains(fn ($period) => $period->starts_at->lessThan($end) && $period->ends_at->greaterThan($start))) {
                throw ValidationException::withMessages(['starts_on' => __('This membership overlaps an existing period. Choose another start date or renew instead.')]);
            }
            $subscription = new MemberSubscription;
            $subscription->forceFill([
                'pricing_snapshot' => $data['_pricing_snapshot'] ?? ['branch_ids' => $plan->branch_ids ?? []],
                'member_id' => $member->id, 'membership_plan_id' => $plan->id,
                'plan_name' => $plan->name, 'duration_amount' => $plan->duration_amount, 'duration_unit' => $plan->duration_unit,
                'price_centavos' => $plan->price_centavos - $savings, 'starts_at' => $start, 'ends_at' => $end,
                'original_price_centavos' => $plan->price_centavos, 'discount_basis_points' => $points,
                'discount_type' => $type, 'discount_other' => $other, 'discount_mode' => $mode,
                'status' => MemberSubscriptionStatus::Active, 'operation' => $data['operation'], 'request_key' => $data['request_key'],
            ])->save();
            Audit::log($data['operation'] === 'renew' ? 'membership.renewed' : 'membership.assigned', $subscription, ['after' => $subscription->only(['member_id', 'membership_plan_id', 'plan_name', 'price_centavos', 'original_price_centavos', 'discount_basis_points', 'discount_type', 'discount_other', 'discount_mode', 'starts_at', 'ends_at'])]);

            return $subscription;
        });
    }

    public function change(Member $member, MemberSubscription $subscription, string $action, ?string $reason = null): void
    {
        DB::transaction(function () use ($member, $subscription, $action, $reason) {
            [, $member] = $this->lockMember($member);
            $subscription = $member->subscriptions()->lockForUpdate()->findOrFail($subscription->id);
            $target = match ($action) {
                'suspend' => MemberSubscriptionStatus::Suspended,
                'resume' => MemberSubscriptionStatus::Active,
                'cancel' => MemberSubscriptionStatus::Cancelled,
            };
            if ($subscription->status === $target) {
                return;
            }
            if ($subscription->status === MemberSubscriptionStatus::Cancelled || ($target !== MemberSubscriptionStatus::Cancelled && ($subscription->status === MemberSubscriptionStatus::Expired || ! $subscription->ends_at->isFuture()))) {
                throw ValidationException::withMessages(['action' => __('This membership can no longer be changed that way.')]);
            }
            $before = $subscription->status->value;
            $subscription->status = $target;
            $subscription->save();
            Audit::log('membership.'.match ($action) {
                'suspend' => 'suspended', 'resume' => 'resumed', 'cancel' => 'cancelled'
            }, $subscription, ['before' => ['status' => $before], 'after' => ['status' => $target->value], 'reason' => $reason]);
        });
    }

    private function lockMember(Member $member): array
    {
        $gym = Gym::whereKey(app(CurrentGym::class)->id())->lockForUpdate()->firstOrFail();
        $member = Member::lockForUpdate()->findOrFail($member->id);
        Gate::authorize('update', $member);
        app(PlanLimits::class)->ensureBranchWritable($gym, $member->home_branch_id, 'membership_plan_id');

        return [$gym, $member];
    }
}
