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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class IronHousePortalMemberSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Portal test credentials are local/testing only.');
        }
        $gym = Gym::where('slug', 'iron-house-fitness')->firstOrFail();
        app(CurrentGym::class)->runAs($gym, fn () => DB::transaction(function () use ($gym) {
            Gym::whereKey($gym->id)->lockForUpdate()->firstOrFail();
            $gym->subscription()->lockForUpdate()->firstOrFail();
            if (Member::where('email', 'member@ironhouse.test')->exists()) {
                return;
            }
            app(PlanLimits::class)->ensureMemberAvailable($gym, Member::where('is_active', true)->count());
            $branch = $gym->mainBranch();
            $plan = MembershipPlan::firstOrCreate(['name' => 'Sample monthly access'], ['duration_amount' => 30, 'duration_unit' => 'days', 'price_centavos' => 120000]);
            $member = new Member(['name' => 'Iron House Test Member', 'mobile' => '09000000999', 'email' => 'member@ironhouse.test', 'home_branch_id' => $branch->id]);
            $member->forceFill(['is_active' => true, 'portal_login' => 'member@ironhouse.test', 'portal_password' => Hash::make('password')])->save();
            $start = CarbonImmutable::now($gym->timezone)->startOfDay()->utc();
            (new MemberSubscription)->forceFill([
                'member_id' => $member->id, 'membership_plan_id' => $plan->id, 'plan_name' => $plan->name,
                'duration_amount' => 30, 'duration_unit' => 'days', 'price_centavos' => $plan->price_centavos,
                'starts_at' => $start, 'ends_at' => $start->addDays(30), 'status' => MemberSubscriptionStatus::Active,
                'operation' => 'assign', 'request_key' => (string) Str::uuid(),
            ])->save();
        }));
    }
}
