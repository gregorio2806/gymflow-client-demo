<?php

namespace Database\Seeders;

use App\Enums\GymRole;
use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Local demo data: two gyms (for checking tenant isolation) and one account per role.
 * Iron House is on Multi-Branch, Flex Zone on Starter.
 * All passwords are "password". Listed in AGENTS.md → "Dev accounts". Safe to re-run.
 */
class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DevelopmentSeeder must never run in production (known passwords).');
        }

        $this->user('Platform Admin', 'admin@example.test')->forceFill(['is_platform_admin' => true])->save();

        $ironHouse = $this->gym('Iron House Fitness', 'iron-house-fitness', Plan::MultiBranch);
        $this->member($ironHouse, $this->user('Iris Owner', 'owner@ironhouse.test'), GymRole::Owner);
        $this->member($ironHouse, $this->user('Sam Staff', 'staff@ironhouse.test'), GymRole::Staff);
        $this->member($ironHouse, $this->user('Cora Coach', 'coach@ironhouse.test'), GymRole::Coach);
        $this->call(IronHouseBranchManagerSeeder::class);
        $this->call(IronHouseMemberSeeder::class);
        $this->call(IronHousePortalMemberSeeder::class);
        $this->call(IronHouseWebsiteSeeder::class);

        $flexZone = $this->gym('Flex Zone Gym', 'flex-zone-gym', Plan::Starter);
        $this->member($flexZone, $this->user('Felix Owner', 'owner@flexzone.test'), GymRole::Owner);
    }

    private function user(string $name, string $email): User
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->forceFill(['name' => $name, 'password' => 'password', 'email_verified_at' => now()])->save();

        return $user;
    }

    private function gym(string $name, string $slug, Plan $plan): Gym
    {
        $gym = Gym::firstOrCreate(['slug' => $slug], ['name' => $name, 'phone' => '09171234567', 'address' => 'Quezon City']);
        $gym->subscription->update(['plan' => $plan, 'status' => SubscriptionStatus::Active]);

        return $gym;
    }

    private function member(Gym $gym, User $user, GymRole $role): void
    {
        $gym->users()->syncWithoutDetaching([$user->id => ['role' => $role]]);
    }
}
