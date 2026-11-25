<?php

namespace Tests\Feature\Tenancy;

use App\Enums\GymRole;
use App\Enums\Plan;
use App\Models\Gym;
use App\Models\Member;
use App\Models\User;
use App\Tenancy\CurrentGym;
use Database\Seeders\DevelopmentSeeder;
use Database\Seeders\IronHouseBranchManagerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class DevelopmentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_dev_accounts_listed_in_agents_md()
    {
        $this->seed();

        $ironHouse = Gym::where('slug', 'iron-house-fitness')->sole();
        $flexZone = Gym::where('slug', 'flex-zone-gym')->sole();

        $this->assertTrue(User::where('email', 'admin@example.test')->sole()->is_platform_admin);
        $this->assertSame(GymRole::Owner, User::where('email', 'owner@ironhouse.test')->sole()->roleIn($ironHouse));
        $this->assertSame(GymRole::Staff, User::where('email', 'staff@ironhouse.test')->sole()->roleIn($ironHouse));
        $this->assertSame(GymRole::Coach, User::where('email', 'coach@ironhouse.test')->sole()->roleIn($ironHouse));
        $this->assertSame(GymRole::Owner, User::where('email', 'owner@flexzone.test')->sole()->roleIn($flexZone));
        $this->assertNull(User::where('email', 'owner@flexzone.test')->sole()->roleIn($ironHouse));

        $owner = User::where('email', 'owner@ironhouse.test')->sole();
        $this->assertTrue(Hash::check('password', $owner->password));
        $this->assertTrue($owner->hasVerifiedEmail());
        $member = app(CurrentGym::class)->runAs($ironHouse, fn () => Member::where('portal_login', 'member@ironhouse.test')->sole());
        $this->assertTrue(Hash::check('password', $member->portal_password));
        $this->assertFalse(User::where('email', 'member@ironhouse.test')->exists());
    }

    public function test_seeded_owner_can_log_in_and_reach_the_dashboard()
    {
        $this->seed();

        $this->post(route('login.store'), ['email' => 'owner@ironhouse.test', 'password' => 'password']);

        $this->assertAuthenticated();
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_it_can_run_twice_without_duplicates()
    {
        $this->seed();
        $this->seed();

        $this->assertSame(2, Gym::count());
        $this->assertSame(6, User::count());
        $ironHouse = Gym::where('slug', 'iron-house-fitness')->sole();
        $manager = User::where('email', 'manager@ironhouse.test')->sole();
        $this->assertSame(4, $ironHouse->users()->count());
        $this->assertSame($ironHouse->mainBranch()->id, $ironHouse->users()->whereKey($manager->id)->sole()->pivot->branch_id);
    }

    public function test_seeded_branch_manager_can_log_in_and_access_assigned_branch(): void
    {
        $this->seed();
        $gym = Gym::where('slug', 'iron-house-fitness')->sole();
        $manager = User::where('email', 'manager@ironhouse.test')->sole();
        $this->assertSame(Plan::MultiBranch, $gym->plan());
        $this->assertSame(GymRole::BranchManager, $manager->roleIn($gym));
        $this->post(route('login.store'), ['email' => 'manager@ironhouse.test', 'password' => 'password']);
        $this->assertAuthenticatedAs($manager);
        $this->get(route('branches.index'))->assertOk();
        $this->get(route('gym.settings.edit'))->assertForbidden();
    }

    public function test_branch_manager_seeder_refuses_production(): void
    {
        $this->app['env'] = 'production';
        $this->expectException(RuntimeException::class);
        $this->app->make(IronHouseBranchManagerSeeder::class)->run();
    }

    public function test_it_refuses_to_run_in_production()
    {
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);

        // Call the seeder directly (db:seed would stop at its own production prompt first).
        $this->app->make(DevelopmentSeeder::class)->run();
    }
}
