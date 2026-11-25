<?php

namespace Tests\Feature\Tenancy;

use App\Enums\GymRole;
use App\Http\Middleware\SetCurrentGym;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_a_gym_is_sent_to_create_one()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertRedirect(route('gyms.create'));
    }

    public function test_create_gym_page_renders_for_an_invited_owner()
    {
        $user = User::factory()->create();
        $this->grantGymAccess($user);

        $this->actingAs($user)
            ->get(route('gyms.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('onboarding/CreateGym'));
    }

    public function test_user_can_create_a_gym_and_becomes_its_owner()
    {
        $user = User::factory()->create();
        $this->grantGymAccess($user);

        $this->actingAs($user)
            ->post(route('gyms.store'), [
                'name' => 'DM Fitness',
                'phone' => '09171234567',
                'address' => 'Quezon City',
            ])
            ->assertRedirect(route('dashboard'));

        $gym = Gym::where('name', 'DM Fitness')->firstOrFail();
        $this->assertSame('dm-fitness', $gym->slug);
        $this->assertSame(GymRole::Owner, $user->roleIn($gym));
        $this->assertSame($gym->id, session(SetCurrentGym::SESSION_KEY));

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_users_without_granted_access_cannot_create_a_gym()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('gyms.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('onboarding/NoAccess')
                ->where('accessEmail', config('platform.access_email')));

        $this->actingAs($user)->post(route('gyms.store'), ['name' => 'Sneaky Gym'])->assertForbidden();
        $this->assertDatabaseCount('gyms', 0);
    }

    public function test_one_invitation_creates_only_one_gym()
    {
        $user = User::factory()->create();
        $invitation = $this->grantGymAccess($user);

        $this->actingAs($user)->post(route('gyms.store'), ['name' => 'First']);
        $this->assertSame(Gym::where('name', 'First')->sole()->id, $invitation->refresh()->gym_id);

        $this->actingAs($user)->post(route('gyms.store'), ['name' => 'Second'])->assertForbidden();
        $this->assertSame(1, Gym::count());
    }

    public function test_gym_name_is_required()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('gyms.store'), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('gyms', 0);
    }

    public function test_status_and_slug_cannot_be_chosen_by_the_owner()
    {
        $user = User::factory()->create();
        $this->grantGymAccess($user);

        $this->actingAs($user)
            ->post(route('gyms.store'), ['name' => 'Flex Zone', 'status' => 'suspended', 'slug' => 'admin']);

        $gym = Gym::firstOrFail();
        $this->assertSame('flex-zone', $gym->slug);
        $this->assertSame('active', $gym->status->value);
    }

    public function test_unverified_users_cannot_create_a_gym()
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->post(route('gyms.store'), ['name' => 'DM Fitness'])
            ->assertRedirect(route('verification.notice'));

        $this->assertDatabaseCount('gyms', 0);
    }

    public function test_platform_admin_without_a_gym_uses_platform_gyms_instead_of_onboarding()
    {
        $this->actingAs(User::factory()->platformAdmin()->create())
            ->get(route('dashboard'))
            ->assertRedirect(route('platform.gyms.index'));
    }
}
