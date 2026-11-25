<?php

namespace Tests\Feature\Tenancy;

use App\Enums\GymRole;
use App\Http\Middleware\SetCurrentGym;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CurrentGymTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_first_gym_becomes_current_and_is_shared_with_the_ui()
    {
        $gym = Gym::factory()->create(['name' => 'Iron House']);
        $user = User::factory()->memberOf($gym, GymRole::Owner)->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertSessionHas(SetCurrentGym::SESSION_KEY, $gym->id)
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentGym', [
                    'id' => $gym->id,
                    'name' => 'Iron House',
                    'slug' => 'iron-house',
                    'role' => 'owner',
                    'plan' => 'starter',
                    'hasExtraBranches' => false,
                ]));
    }

    public function test_gym_saved_in_session_is_used()
    {
        $gymA = Gym::factory()->create();
        $gymB = Gym::factory()->create();
        $user = User::factory()->memberOf($gymA)->memberOf($gymB, GymRole::Coach)->create();

        $this->actingAs($user)
            ->withSession([SetCurrentGym::SESSION_KEY => $gymB->id])
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentGym.id', $gymB->id)
                ->where('currentGym.role', 'coach'));
    }

    public function test_session_pointing_to_a_gym_the_user_left_falls_back_to_their_own_gym()
    {
        $ownGym = Gym::factory()->create();
        $otherGym = Gym::factory()->create();
        $user = User::factory()->memberOf($ownGym)->create();

        $this->actingAs($user)
            ->withSession([SetCurrentGym::SESSION_KEY => $otherGym->id])
            ->get(route('dashboard'))
            ->assertSessionHas(SetCurrentGym::SESSION_KEY, $ownGym->id)
            ->assertInertia(fn (Assert $page) => $page->where('currentGym.id', $ownGym->id));
    }

    public function test_user_without_a_gym_has_no_current_gym()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('gyms.create'))
            ->assertSessionMissing(SetCurrentGym::SESSION_KEY)
            ->assertInertia(fn (Assert $page) => $page->where('currentGym', null));
    }

    public function test_user_can_switch_to_another_of_their_gyms()
    {
        $gymA = Gym::factory()->create();
        $gymB = Gym::factory()->create();
        $user = User::factory()->memberOf($gymA)->memberOf($gymB)->create();

        $this->actingAs($user)
            ->put(route('current-gym.update'), ['gym_id' => $gymB->id])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas(SetCurrentGym::SESSION_KEY, $gymB->id);
    }

    public function test_user_cannot_switch_to_a_gym_they_do_not_belong_to()
    {
        $ownGym = Gym::factory()->create();
        $otherGym = Gym::factory()->create();
        $user = User::factory()->memberOf($ownGym)->create();

        $this->actingAs($user)
            ->put(route('current-gym.update'), ['gym_id' => $otherGym->id])
            ->assertNotFound();
    }

    public function test_guests_cannot_switch_gyms()
    {
        $gym = Gym::factory()->create();

        $this->put(route('current-gym.update'), ['gym_id' => $gym->id])
            ->assertRedirect(route('login'));
    }
}
