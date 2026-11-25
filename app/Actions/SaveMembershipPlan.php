<?php

namespace App\Actions;

use App\Models\Gym;
use App\Models\MembershipPlan;
use App\Support\Audit;
use App\Support\PaymentDiscount;
use App\Support\PricingCatalog;
use App\Tenancy\CurrentGym;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SaveMembershipPlan
{
    public function handle(array $data, ?MembershipPlan $plan = null): MembershipPlan
    {
        return DB::transaction(function () use ($data, $plan) {
            $gym = Gym::whereKey(app(CurrentGym::class)->id())->lockForUpdate()->firstOrFail();
            $creating = $plan === null;
            $plan = $creating ? new MembershipPlan : MembershipPlan::withoutGlobalScope('access-products')->lockForUpdate()->findOrFail($plan->id);
            Gate::authorize($creating ? 'create' : 'update', $creating ? MembershipPlan::class : $plan);
            $before = $plan->only(['name', 'kind', 'branch_ids', 'configuration', 'duration_amount', 'duration_unit', 'price_centavos', 'inclusions']);
            $plan->fill(Arr::only($data, ['name', 'duration_amount', 'duration_unit', 'inclusions']));
            $plan->forceFill(Arr::only($data, ['branch_ids', 'configuration']));
            if ($creating) {
                $plan->kind = $data['kind'] ?? 'subscription';
            }
            if ($plan->kind === 'benefit') {
                $config = $plan->configuration ?? [];
                $config['discount_value'] = ($config['discount_unit'] ?? 'percentage') === 'percentage'
                    ? PaymentDiscount::percentage((string) ($config['discount_input'] ?? '0'))
                    : PaymentDiscount::pesos((string) ($config['discount_input'] ?? '0'));
                $plan->configuration = $config;
            }
            if ($plan->kind === 'package') {
                $config = $plan->configuration ?? [];
                $config['duration_mode'] = $config['duration_mode'] ?? (! empty($config['unlimited_validity']) ? 'sessions' : 'days');
                unset($config['service'], $config['unlimited_validity']);
                $plan->configuration = $config;
            }
            // The request validates decimal text; do not round money through a float.
            [$pesos, $fraction] = array_pad(explode('.', $data['price_php'], 2), 2, '');
            $plan->price_centavos = (int) $pesos * 100 + (int) str_pad($fraction, 2, '0');
            $plan->save();
            if ($plan->kind !== 'subscription' && $gym->pricing_settings === null) {
                $gym->forceFill(['pricing_settings' => PricingCatalog::settings($gym)])->save();
            }
            Audit::log($creating ? 'membership_plan.created' : 'membership_plan.updated', $plan, ['before' => $creating ? [] : $before, 'after' => $plan->only(['name', 'kind', 'branch_ids', 'configuration', 'duration_amount', 'duration_unit', 'price_centavos', 'inclusions'])]);

            return $plan;
        });
    }

    public function availability(MembershipPlan $plan, bool $active): void
    {
        DB::transaction(function () use ($plan, $active) {
            Gym::whereKey(app(CurrentGym::class)->id())->lockForUpdate()->firstOrFail();
            $plan = MembershipPlan::withoutGlobalScope('access-products')->lockForUpdate()->findOrFail($plan->id);
            Gate::authorize('update', $plan);
            if ($plan->is_active === $active) {
                return;
            }
            $plan->is_active = $active;
            $plan->save();
            Audit::log($active ? 'membership_plan.activated' : 'membership_plan.archived', $plan);
        });
    }
}
