<?php

namespace App\Actions;

use App\Enums\GymRole;
use App\Enums\MemberSubscriptionStatus;
use App\Enums\Plan;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\MemberSubscription;
use App\Models\User;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Try demo": a private, temporary gym owned by a throwaway account.
 * Deleted by `php artisan demo:purge` once it expires.
 */
class StartDemo
{
    public const LIFETIME_HOURS = 24;

    /**
     * Protects the database from being flooded with demo gyms.
     */
    public const MAX_ACTIVE = 200;

    public static function atCapacity(): bool
    {
        return Gym::where('demo_expires_at', '>', now())->count() >= self::MAX_ACTIVE;
    }

    public function handle(): User
    {
        return DB::transaction(function () {
            $gym = new Gym([
                'name' => 'Demo Fitness',
                'slug' => 'demo-'.Str::lower(Str::random(12)), // random: avoids slug lookups across many demos
                'phone' => '09171234567',
                'address' => 'Quezon City',
            ]);
            $gym->demo_expires_at = now()->addHours(self::LIFETIME_HOURS);
            $gym->save();
            $gym->subscription->update(['plan' => Plan::MultiBranch]); // show off every feature

            $owner = new User;
            $owner->forceFill([
                'name' => 'Demo Owner',
                'email' => 'demo-'.Str::uuid().'@demo.invalid', // .invalid can never receive mail
                'password' => Str::random(40),
                'email_verified_at' => now(),
                'is_demo' => true,
            ])->save();

            $gym->users()->attach($owner, ['role' => GymRole::Owner]);

            app(CurrentGym::class)->runAs($gym, function () {
                foreach ([['Day pass', 1, 'days', 15000], ['Monthly access', 1, 'months', 150000], ['Quarterly access', 3, 'months', 400000]] as [$name, $amount, $unit, $price]) {
                    $plan = new MembershipPlan(['name' => $name, 'duration_amount' => $amount, 'duration_unit' => $unit, 'inclusions' => 'Gym floor and equipment access']);
                    $plan->price_centavos = $price;
                    $plan->save();
                }
                foreach (['Alex Santos', 'Jamie Cruz', 'Sam Reyes', 'Taylor Garcia', 'Casey Ramos'] as $index => $name) {
                    // Fictional contacts only; demo records never claim real consent.
                    $member = new Member([
                        'name' => $name,
                        'mobile' => '0900000000'.($index + 1),
                        'email' => $index % 2 === 0 ? 'member-'.($index + 1).'@demo.invalid' : null,
                    ]);
                    $member->is_active = $index !== 4;
                    $member->save();
                    if ($index < 3) {
                        $plan = MembershipPlan::where('name', 'Monthly access')->firstOrFail();
                        $start = CarbonImmutable::now($member->gym->timezone)->startOfDay()->subDays($index === 1 ? 60 : 5);
                        (new MemberSubscription)->forceFill([
                            'member_id' => $member->id, 'membership_plan_id' => $plan->id,
                            'plan_name' => $plan->name, 'duration_amount' => $plan->duration_amount, 'duration_unit' => $plan->duration_unit,
                            'price_centavos' => $plan->price_centavos, 'starts_at' => $start->utc(), 'ends_at' => $start->addMonthsNoOverflow(1)->utc(),
                            'status' => $index === 2 ? MemberSubscriptionStatus::Suspended : MemberSubscriptionStatus::Active,
                            'operation' => 'assign', 'request_key' => (string) Str::uuid(),
                        ])->save();
                    }
                }
            });

            // Later tasks add sample payments.

            return $owner;
        });
    }
}
