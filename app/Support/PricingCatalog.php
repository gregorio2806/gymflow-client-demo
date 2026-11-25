<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Gym;
use App\Models\MembershipPlan;
use App\Models\PricingDiscount;
use App\Tenancy\CurrentGym;

class PricingCatalog
{
    public static function settings(Gym $gym): array
    {
        return array_replace(['require_purchase' => true, 'packages_require_access' => false, 'promotion_stacking' => 'none', 'tax_profile' => 'unconfirmed'], $gym->pricing_settings ?? []);
    }

    public static function data(): array
    {
        $gym = app(CurrentGym::class)->get();

        return [
            'offerings' => MembershipPlan::withoutGlobalScope('access-products')->where('is_active', true)->orderBy('kind')->orderBy('name')->get()->map(fn ($p) => $p->only(['id', 'name', 'kind', 'price_centavos', 'duration_amount', 'duration_unit', 'branch_ids', 'configuration'])),
            'discounts' => PricingDiscount::where('is_active', true)->orderBy('id')->get()->map(fn ($d) => $d->only(['id', 'name', 'category', 'rule_type', 'unit', 'value', 'requires_verification'])),
            'pricingSettings' => self::settings($gym),
            'today' => now($gym->timezone)->toDateString(),
            'purchaseBranches' => Branch::where('is_active', true)->orderByDesc('is_main')->orderBy('name')->get(['id', 'name'])->map(fn ($b) => $b->only(['id', 'name'])),
        ];
    }
}
