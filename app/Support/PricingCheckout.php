<?php

namespace App\Support;

use App\Enums\MemberSubscriptionStatus;
use App\Models\Branch;
use App\Models\Member;
use App\Models\MemberPurchase;
use App\Models\MembershipPlan;
use App\Models\PricingDiscount;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PricingCheckout
{
    public static function rules(): array
    {
        $gymId = app(CurrentGym::class)->id();
        $offering = fn ($kind) => ['nullable', 'integer', Rule::exists('membership_plans', 'id')->where('gym_id', $gymId)->where('kind', $kind)->where('is_active', true)];

        return [
            'pricing_checkout' => ['sometimes', 'boolean'],
            'membership_plan_id' => $offering('subscription'),
            'benefit_plan_id' => $offering('benefit'),
            'package_ids' => ['nullable', 'array', 'max:10'],
            'package_ids.*' => ['integer', 'distinct', Rule::exists('membership_plans', 'id')->where('gym_id', $gymId)->where('kind', 'package')->where('is_active', true)],
            'starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:2100-12-31'],
            'benefit_starts_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:2100-12-31'],
            'pricing_discount_id' => ['nullable', 'integer', Rule::exists('pricing_discounts', 'id')->where('gym_id', $gymId)->where('is_active', true)],
            'eligibility_verified' => ['sometimes', 'boolean'],
            'eligibility_from' => ['nullable', 'date_format:Y-m-d'],
            'eligibility_through' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:eligibility_from'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('gym_id', $gymId)],
            'operation' => ['sometimes', Rule::in(['assign', 'renew'])],
            'request_key' => ['required', 'uuid'],
            'paid_php' => ['nullable', 'string', 'regex:/^\d{1,7}(?:\.\d{1,2})?$/'],
            'method' => ['required_with:paid_php', Rule::in(['cash', 'gcash', 'maya', 'bank-transfer'])],
            'reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function quote(array $data, ?Member $member = null): array
    {
        $gym = app(CurrentGym::class)->get();
        $settings = PricingCatalog::settings($gym);
        $branchId = (int) ($data['branch_id'] ?? $member?->home_branch_id ?? app(CurrentGym::class)->viewBranchId() ?? Branch::where('is_active', true)->orderByDesc('is_main')->orderBy('id')->value('id'));
        if (! Branch::whereKey($branchId)->exists()) {
            $this->fail('branch_id', 'Choose an available branch.');
        }
        $start = CarbonImmutable::parse($data['starts_on'], $gym->timezone)->startOfDay()->utc();
        if (($data['operation'] ?? 'assign') === 'renew' && ! empty($data['membership_plan_id'])) {
            if (! $member) {
                $this->fail('operation', 'Assign an initial subscription before renewing.');
            }
            $latest = $member->subscriptions()->where('status', '!=', 'cancelled')->max('ends_at');
            if (! $latest) {
                $this->fail('operation', 'Assign an initial subscription before renewing.');
            }
            $start = max(CarbonImmutable::parse($latest, 'UTC'), CarbonImmutable::now($gym->timezone)->startOfDay()->utc());
        }
        $ids = array_values(array_filter([$data['membership_plan_id'] ?? null, $data['benefit_plan_id'] ?? null, ...($data['package_ids'] ?? [])]));
        if (! $ids && $settings['require_purchase']) {
            $this->fail('membership_plan_id', 'Choose a subscription, membership benefit or training package.');
        }
        $plans = MembershipPlan::withoutGlobalScope('access-products')->whereIn('id', $ids)->get()->keyBy('id');
        foreach (['membership_plan_id' => 'subscription', 'benefit_plan_id' => 'benefit'] as $field => $kind) {
            if (! empty($data[$field]) && $plans->get($data[$field])?->kind !== $kind) {
                $this->fail($field, 'Choose the correct offering type.');
            }
        }
        foreach ($data['package_ids'] ?? [] as $id) {
            if ($plans->get($id)?->kind !== 'package') {
                $this->fail('package_ids', 'Choose training packages only.');
            }
        }
        $subscriptions = $member ? $member->subscriptions()->where('status', '!=', MemberSubscriptionStatus::Cancelled->value)->get() : collect();
        $benefits = $member ? MemberPurchase::withoutGlobalScope('branch')->where('member_id', $member->id)->where('kind', 'benefit')->where('status', 'active')->get() : collect();
        $benefit = ! empty($data['benefit_plan_id']) ? $plans->get($data['benefit_plan_id']) : null;
        if ($benefit) {
            $benefitStart = CarbonImmutable::parse($data['benefit_starts_on'] ?? $data['starts_on'], $gym->timezone)->startOfDay()->utc();
            if (! ($benefit->configuration['deferred_start_allowed'] ?? true) && $benefitStart->toDateString() !== CarbonImmutable::now($gym->timezone)->startOfDay()->utc()->toDateString()) {
                $this->fail('benefit_starts_on', 'This benefit must start today.');
            }
            $benefitEnd = $this->end($benefit, $benefitStart);
            if ($benefits->contains(fn ($b) => $b->starts_at < $benefitEnd && $b->ends_at > $benefitStart)) {
                $this->fail('benefit_starts_on', 'This benefit overlaps an existing membership benefit. Choose a later start.');
            }
            if ($benefits->contains(fn ($b) => $b->membership_plan_id === $benefit->id) && ! ($benefit->configuration['allow_renewal'] ?? true)) {
                $this->fail('benefit_plan_id', 'This benefit does not allow renewal.');
            }
            $benefits->push((object) ['starts_at' => $benefitStart, 'ends_at' => $benefitEnd, 'snapshot' => ['configuration' => $benefit->configuration, 'branch_ids' => $benefit->branch_ids ?? []], 'plan_name' => $benefit->name]);
        }
        $discount = ! empty($data['pricing_discount_id']) ? PricingDiscount::findOrFail($data['pricing_discount_id']) : null;
        if ($discount && ! $discount->is_active) {
            $this->fail('pricing_discount_id', 'This discount is archived.');
        }
        if ($discount?->requires_verification && ! ($data['eligibility_verified'] ?? false)) {
            $this->fail('eligibility_verified', 'Confirm that you verified this member’s eligibility. Keep supporting documents offline.');
        }
        $lines = [];
        foreach ($ids as $id) {
            $plan = $plans->get($id);
            if (! $plan || ! $plan->is_active) {
                $this->fail('membership_plan_id', 'Choose an available offering from this gym.');
            }
            if ($plan->branch_ids && ! in_array($branchId, $plan->branch_ids)) {
                $this->fail('branch_id', 'An offering is not available at this branch.');
            }
            $lineStart = $plan->kind === 'benefit' ? $benefitStart : $start;
            $packageSessionValidity = $plan->kind === 'package' && (($plan->configuration['duration_mode'] ?? null) === 'sessions' || ($plan->configuration['unlimited_validity'] ?? false));
            $end = $packageSessionValidity ? null : $this->end($plan, $lineStart);
            if ($plan->kind === 'subscription' && $subscriptions->contains(fn ($subscription) => $subscription->starts_at < $end && $subscription->ends_at > $lineStart)) {
                $this->fail('starts_on', 'This subscription overlaps an existing membership period. Choose a later start date or renew instead.');
            }
            $base = $plan->price_centavos;
            $promotions = [];
            if ($plan->kind !== 'benefit') {
                foreach ($benefits as $b) {
                    $c = $b->snapshot['configuration'];
                    if (! empty($b->snapshot['branch_ids']) && ! in_array($branchId, $b->snapshot['branch_ids'])) {
                        continue;
                    }
                    if ($b->starts_at > $lineStart || $b->ends_at <= $lineStart || ($plan->kind === 'package' && ! ($c['applies_to_packages'] ?? false)) || (($data['operation'] ?? 'assign') === 'renew' && ! ($c['applies_to_renewals'] ?? true)) || ($plan->kind === 'subscription' && ! empty($c['eligible_plan_ids']) && ! in_array($id, $c['eligible_plan_ids']))) {
                        continue;
                    }
                    $promotions[] = ['reason' => $b->plan_name, 'basis_centavos' => $base, 'savings_centavos' => $this->savings($base, $c['discount_unit'], (int) $c['discount_value'])];
                }
            }
            $statutory = null;
            $date = $lineStart->setTimezone($gym->timezone)->toDateString();
            if ($discount && in_array($plan->kind, $discount->product_kinds) && (! $discount->plan_ids || in_array($id, $discount->plan_ids))
                && (! $discount->effective_from || $date >= $discount->effective_from) && (! $discount->effective_through || $date <= $discount->effective_through)
                && (empty($data['eligibility_from']) || $date >= $data['eligibility_from']) && (empty($data['eligibility_through']) || $date <= $data['eligibility_through'])) {
                if ($discount->rule_type === 'statutory') {
                    if ($settings['tax_profile'] === 'unconfirmed') {
                        $this->fail('pricing_discount_id', 'The owner must confirm VAT status in Plans & Pricing before applying a statutory discount.');
                    }
                    $taxFree = $settings['tax_profile'] === 'vat-inclusive' ? intdiv($base * 100 + 56, 112) : $base;
                    $statutory = ['reason' => $discount->name.' (statutory)', 'basis_centavos' => $taxFree, 'savings_centavos' => PaymentDiscount::savings($taxFree, 2000), 'vat_exemption_centavos' => $base - $taxFree];
                } else {
                    $promotions[] = ['reason' => $discount->name, 'basis_centavos' => $base, 'savings_centavos' => $this->savings($base, $discount->unit, $discount->value)];
                }
            }
            if ($settings['promotion_stacking'] === 'none') {
                $promotions = array_slice(collect($promotions)->sortByDesc('savings_centavos')->values()->all(), 0, 1);
            }
            $saving = min($base, array_sum(array_column($promotions, 'savings_centavos')));
            if ($statutory && $saving <= $statutory['savings_centavos'] + $statutory['vat_exemption_centavos']) {
                $promotions = [$statutory];
                $saving = $statutory['savings_centavos'] + $statutory['vat_exemption_centavos'];
            }
            $promotions = array_values(array_filter($promotions, fn ($p) => $p['savings_centavos'] > 0 || ($p['vat_exemption_centavos'] ?? 0) > 0));
            $left = $base;
            foreach ($promotions as &$promotion) {
                $promotion['savings_centavos'] = min($left, $promotion['savings_centavos']);
                $left -= $promotion['savings_centavos'] + ($promotion['vat_exemption_centavos'] ?? 0);
            }
            unset($promotion);
            if ($plan->kind === 'package' && $settings['packages_require_access'] && empty($data['membership_plan_id']) && (! $member || ! $member->subscriptions()->where('status', MemberSubscriptionStatus::Active)->where('starts_at', '<=', $lineStart)->where('ends_at', '>', $lineStart)->exists())) {
                $this->fail('package_ids', 'This gym requires active gym access to buy a training package.');
            }
            $lines[] = ['plan_id' => $plan->id, 'kind' => $plan->kind, 'name' => $plan->name, 'original_centavos' => $base, 'total_centavos' => $base - $saving, 'discounts' => $promotions,
                'starts_at' => $lineStart->toIso8601String(), 'ends_at' => $end?->toIso8601String(), 'starts_on' => $lineStart->setTimezone($gym->timezone)->toDateString(),
                'branch_ids' => $plan->branch_ids ?? [], 'configuration' => $plan->configuration ?? [], 'timezone' => $gym->timezone, 'packages_require_access' => $settings['packages_require_access']];
        }

        return ['branch_id' => $branchId, 'lines' => $lines, 'total_centavos' => array_sum(array_column($lines, 'total_centavos'))];
    }

    private function savings(int $base, string $unit, int $value): int
    {
        return min($base, $unit === 'peso' ? $value : PaymentDiscount::savings($base, $value));
    }

    private function end(MembershipPlan $plan, CarbonImmutable $start): CarbonImmutable
    {
        $local = $start->setTimezone(app(CurrentGym::class)->get()->timezone);

        return ($plan->duration_unit === 'months' ? $local->addMonthsNoOverflow($plan->duration_amount) : $local->addDays($plan->duration_amount))->utc();
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => __($message)]);
    }
}
