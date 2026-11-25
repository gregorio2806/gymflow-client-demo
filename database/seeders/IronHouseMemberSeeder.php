<?php

namespace Database\Seeders;

use App\Enums\MemberSubscriptionStatus;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\MemberSubscription;
use App\Support\PlanLimits;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class IronHouseMemberSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('IronHouseMemberSeeder is development-only.');
        }
        $gym = Gym::where('slug', 'iron-house-fitness')->firstOrFail();
        app(CurrentGym::class)->runAs($gym, fn () => DB::transaction(function () use ($gym) {
            Gym::whereKey($gym->id)->lockForUpdate()->firstOrFail();
            $gym->subscription()->lockForUpdate()->firstOrFail();
            $branch = $gym->branches()->where('is_active', true)->orderByDesc('is_main')->firstOrFail();
            $plan = MembershipPlan::firstOrNew(['name' => 'Sample monthly access']);
            if (! $plan->exists) {
                $plan->forceFill(['duration_amount' => 30, 'duration_unit' => 'days', 'price_centavos' => 120000, 'inclusions' => 'Fictional development plan'])->save();
            }
            $states = collect(['active', 'active', 'active', 'active', 'inactive', 'inactive', 'inactive', 'expired', 'expired', 'expired'])->shuffle()->values();
            foreach ($states as $index => $state) {
                $email = 'sample-member-'.($index + 1).'@ironhouse.example.invalid';
                if (Member::where('email', $email)->exists()) {
                    continue;
                }
                $active = $state !== 'inactive';
                if ($active) {
                    app(PlanLimits::class)->ensureMemberAvailable($gym, Member::where('is_active', true)->count());
                }
                $member = new Member(['name' => fake()->name(), 'mobile' => '09000000'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT), 'email' => $email, 'home_branch_id' => $branch->id]);
                $member->is_active = $active;
                $member->save();
                $start = CarbonImmutable::now($gym->timezone)->startOfDay()->subDays($state === 'expired' ? random_int(40, 70) : random_int(1, 10))->utc();
                (new MemberSubscription)->forceFill([
                    'member_id' => $member->id, 'membership_plan_id' => $plan->id,
                    'plan_name' => $plan->name, 'duration_amount' => 30, 'duration_unit' => 'days', 'price_centavos' => $plan->price_centavos,
                    'starts_at' => $start, 'ends_at' => $start->addDays(30), 'status' => MemberSubscriptionStatus::Active,
                    'operation' => 'assign', 'request_key' => (string) Str::uuid(),
                ])->save();
            }
        }));
    }
}
