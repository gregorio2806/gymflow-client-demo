<?php

namespace Tests\Feature\Platform;

use App\Enums\BillingCycle;
use App\Enums\GymRole;
use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use App\Models\AuditLog;
use App\Models\Gym;
use App\Models\User;
use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GymSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_gym_starts_on_starter_trialing()
    {
        $subscription = Gym::factory()->create()->subscription;

        $this->assertSame(Plan::Starter, $subscription->plan);
        $this->assertSame(SubscriptionStatus::Trialing, $subscription->status);
        $this->assertSame(BillingCycle::Monthly, $subscription->billing_cycle);
    }

    public function test_create_your_gym_shows_the_invited_plan_and_gym_name()
    {
        $user = User::factory()->create();
        $this->grantGymAccess($user, Plan::Pro, 'DM Fitness');

        $this->actingAs($user)
            ->get(route('gyms.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('onboarding/CreateGym')
                ->where('plan.label', 'Pro')
                ->where('plan.monthlyPrice', 1999)
                ->where('gymName', 'DM Fitness'));
    }

    public function test_owner_creates_their_gym_on_the_invited_plan()
    {
        $user = User::factory()->create();
        $this->grantGymAccess($user, Plan::Pro);

        $this->actingAs($user)->post(route('gyms.store'), ['name' => 'DM Fitness']);

        $gym = Gym::where('name', 'DM Fitness')->sole();
        $this->assertSame(Plan::Pro, $gym->plan());
        $this->assertSame(SubscriptionStatus::Trialing, $gym->subscription->status);
    }

    public function test_owner_cannot_pick_a_different_plan_than_invited()
    {
        $user = User::factory()->create();
        $this->grantGymAccess($user, Plan::Starter);

        $this->actingAs($user)->post(route('gyms.store'), ['name' => 'DM Fitness', 'plan' => 'multi_branch']);

        $this->assertSame(Plan::Starter, Gym::sole()->plan());
    }

    public function test_the_plan_is_shared_with_the_ui()
    {
        $gym = Gym::factory()->create();
        $gym->subscription->update(['plan' => Plan::MultiBranch]);
        $owner = User::factory()->memberOf($gym, GymRole::Owner)->create();

        $this->actingAs($owner)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('currentGym.plan', 'multi_branch'));
    }

    public function test_demo_gyms_are_on_multi_branch()
    {
        $this->post(route('demo.store'));

        $this->assertSame(Plan::MultiBranch, Gym::sole()->plan());
    }

    public function test_branch_limit_counts_extra_branches_only_on_multi_branch()
    {
        $subscription = Gym::factory()->create()->subscription;

        $this->assertSame(1, $subscription->branchLimit());

        $subscription->update(['plan' => Plan::MultiBranch, 'extra_branches' => 3]);
        $this->assertSame(5, $subscription->branchLimit());

        $subscription->update(['plan' => Plan::Pro]);
        $this->assertSame(1, $subscription->branchLimit());
    }

    public function test_platform_admin_sets_a_gyms_plan_and_it_is_audit_logged()
    {
        $gym = Gym::factory()->create();
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->put(route('platform.gyms.subscription.update', $gym), [
                'plan' => 'multi_branch',
                'billing_cycle' => 'yearly',
                'status' => 'active',
                'extra_branches' => 2,
                'current_period_ends_at' => '2027-10-05',
            ])
            ->assertRedirect(route('platform.gyms.features.edit', $gym));

        $subscription = $gym->subscription()->first();
        $this->assertSame(Plan::MultiBranch, $subscription->plan);
        $this->assertSame(BillingCycle::Yearly, $subscription->billing_cycle);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame(2, $subscription->extra_branches);
        $this->assertSame('2027-10-05', $subscription->current_period_ends_at->toDateString());

        app(CurrentGym::class)->set($gym);
        $log = AuditLog::sole();
        $this->assertSame('subscription.updated', $log->action);
        $this->assertSame('starter', $log->changes['before']['plan']);
        $this->assertSame('multi_branch', $log->changes['after']['plan']);
    }

    public function test_extra_branches_are_cleared_when_not_on_multi_branch()
    {
        $gym = Gym::factory()->create();

        $this->actingAs(User::factory()->platformAdmin()->create())
            ->put(route('platform.gyms.subscription.update', $gym), [
                'plan' => 'pro', 'billing_cycle' => 'monthly', 'status' => 'active', 'extra_branches' => 4,
            ]);

        $this->assertSame(0, $gym->subscription()->first()->extra_branches);
    }

    public function test_gym_owners_cannot_change_their_own_plan()
    {
        $gym = Gym::factory()->create();
        $owner = User::factory()->memberOf($gym, GymRole::Owner)->create();

        $this->actingAs($owner)
            ->put(route('platform.gyms.subscription.update', $gym), [
                'plan' => 'multi_branch', 'billing_cycle' => 'monthly', 'status' => 'active', 'extra_branches' => 10,
            ])
            ->assertForbidden();

        $this->assertSame(Plan::Starter, $gym->subscription()->first()->plan);
    }

    public function test_admin_pages_show_the_plan()
    {
        $gym = Gym::factory()->create(['name' => 'Iron House']);
        $gym->subscription->update(['plan' => Plan::Pro, 'status' => SubscriptionStatus::Active]);
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)->get(route('platform.gyms.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('gyms.data.0.plan', 'Pro')
                ->where('gyms.data.0.subscriptionStatus', 'active'));

        $this->actingAs($admin)->get(route('platform.gyms.features.edit', $gym))
            ->assertInertia(fn (Assert $page) => $page
                ->where('subscription.plan', 'pro')
                ->where('subscription.status', 'active')
                ->has('plans', 3));
    }

    public function test_seeded_gyms_have_plans()
    {
        $this->seed();

        $this->assertSame(Plan::MultiBranch, Gym::where('slug', 'iron-house-fitness')->sole()->plan());
        $this->assertSame(Plan::Starter, Gym::where('slug', 'flex-zone-gym')->sole()->plan());
    }
}
