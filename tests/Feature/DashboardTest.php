<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        // Dashboard requires a gym (users without one are sent to onboarding — see OnboardingTest).
        $user = User::factory()->memberOf(Gym::factory()->create())->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->where('membershipCounts', ['active' => 0, 'expiringThisWeek' => 0, 'expired' => 0]));
    }

    public function test_platform_admin_without_a_gym_is_redirected_to_platform_gyms(): void
    {
        // Unrelated tenants must not become a fallback current gym for the admin.
        Gym::factory()->create();
        $admin = User::factory()->platformAdmin()->create();
        $this->actingAs($admin)->get(route('dashboard'))->assertRedirect(route('platform.gyms.index'));
        $this->get(route('platform.gyms.index'))->assertOk();
    }

    public function test_platform_admin_with_a_gym_can_still_view_its_dashboard(): void
    {
        $gym = Gym::factory()->create();
        $admin = User::factory()->platformAdmin()->memberOf($gym)->create();
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('currentGym.id', $gym->id));
    }
}
