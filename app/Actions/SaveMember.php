<?php

namespace App\Actions;

use App\Models\Branch;
use App\Models\Gym;
use App\Models\Member;
use App\Models\PricingCheckout;
use App\Support\Audit;
use App\Support\PlanLimits;
use App\Tenancy\CurrentGym;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveMember
{
    public function handle(array $data, ?Member $member = null): Member
    {
        return DB::transaction(function () use ($data, $member) {
            $gym = $this->lockGym();
            $creating = $member === null;
            if ($creating && ($data['pricing_checkout'] ?? false)) {
                $existing = PricingCheckout::where('request_key', $data['request_key'])->first();
                if ($existing) {
                    if ($existing->fingerprint !== PurchaseMemberOfferings::fingerprint($data)) {
                        throw ValidationException::withMessages(['request_key' => __('This submission was already used. Refresh before changing registration details.')]);
                    }
                    $saved = Member::findOrFail($existing->member_id);
                    Gate::authorize('update', $saved);

                    return $saved;
                }
            }
            $member = $creating ? new Member : Member::lockForUpdate()->findOrFail($member->id);
            Gate::authorize($creating ? 'create' : 'update', $creating ? Member::class : $member);
            if ($creating) {
                $data['home_branch_id'] = app(CurrentGym::class)->viewBranchId() ?? Branch::where('is_active', true)->orderByDesc('is_main')->orderBy('id')->value('id');
                if (! $data['home_branch_id']) {
                    throw ValidationException::withMessages(['home_branch_id' => __('Activate a branch before adding a member.')]);
                }
            }
            app(PlanLimits::class)->ensureBranchWritable($gym, (int) $data['home_branch_id'], 'home_branch_id');
            if (! $creating) {
                app(PlanLimits::class)->ensureBranchWritable($gym, $member->home_branch_id, 'home_branch_id');
            } else {
                $this->ensureCapacity($gym);
            }
            $before = $member->only(['name', 'mobile', 'email', 'home_branch_id']);
            $member->fill(Arr::only($data, ['name', 'mobile', 'email', 'home_branch_id']));
            $member->mobile ??= '';
            if (! $member->privacy_consented_at && in_array($data['privacy_consent'] ?? null, [true, 1, '1'], true)) {
                $member->privacy_consented_at = now();
                $member->privacy_recorded_by = auth()->id();
            }
            $member->save();
            Audit::log($creating ? 'member.created' : 'member.updated', $member, ['before' => $creating ? [] : $before, 'after' => $member->only(['name', 'mobile', 'email', 'home_branch_id', 'privacy_consented_at', 'privacy_recorded_by'])]);
            if ($creating) {
                if ($data['pricing_checkout'] ?? false) {
                    app(PurchaseMemberOfferings::class)->handle($member, $data);
                } else {
                    app(ManageMemberSubscription::class)->assign($member, [...Arr::only($data, ['membership_plan_id', 'starts_on', 'request_key', 'discount_percent', 'discount_mode', 'discount_php', 'discount_type', 'discount_other']), 'operation' => 'assign']);
                }
            }

            return $member;
        });
    }

    public function activation(Member $member, bool $active): void
    {
        DB::transaction(function () use ($member, $active) {
            $gym = $this->lockGym();
            $member = Member::lockForUpdate()->findOrFail($member->id);
            Gate::authorize('update', $member);
            app(PlanLimits::class)->ensureBranchWritable($gym, $member->home_branch_id, 'is_active');
            if ($member->is_active === $active) {
                return;
            }
            if ($active) {
                $this->ensureCapacity($gym);
            }
            $member->is_active = $active;
            $member->save();
            Audit::log($active ? 'member.activated' : 'member.deactivated', $member);
        });
    }

    private function lockGym(): Gym
    {
        $gym = Gym::whereKey(app(CurrentGym::class)->id())->lockForUpdate()->firstOrFail();
        $gym->subscription()->lockForUpdate()->firstOrFail();

        return $gym;
    }

    private function ensureCapacity(Gym $gym): void
    {
        app(PlanLimits::class)->ensureMemberAvailable($gym, Member::withoutGlobalScope('branch')->where('gym_id', $gym->id)->where('is_active', true)->count());
    }
}
