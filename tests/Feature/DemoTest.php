<?php

namespace Tests\Feature;

use App\Actions\StartDemo;
use App\Enums\GymRole;
use App\Http\Middleware\SetCurrentGym;
use App\Models\Gym;
use App\Models\Member;
use App\Models\User;
use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DemoTest extends TestCase
{
    use RefreshDatabase;

    private function startDemo(): User
    {
        $this->post(route('demo.store'))->assertRedirect(route('dashboard'));

        return Auth::user();
    }

    public function test_demo_members_are_fictional_and_private_to_each_demo(): void
    {
        $first = app(StartDemo::class)->handle()->gyms()->sole();
        $second = app(StartDemo::class)->handle()->gyms()->sole();
        app(CurrentGym::class)->runAs($first, function () use ($first, $second) {
            $members = $first->members()->get();
            $this->assertCount(5, $members);
            $this->assertSame(4, $members->where('is_active', true)->count());
            $this->assertTrue($members->every(fn ($member) => $member->home_branch_id === $first->mainBranch()->id));
            $this->assertTrue($members->every(fn ($member) => $member->email === null || str_ends_with($member->email, '@demo.invalid')));
            $this->assertTrue($members->every(fn ($member) => $member->privacy_consented_at === null));
            $this->assertSame(0, $second->members()->count());
        });
    }

    public function test_landing_page_offers_try_demo_in_the_navbar()
    {
        $this->get(route('home'))
            ->assertSee('Try demo')
            ->assertSee('action="'.route('demo.store').'"', false);
    }

    public function test_try_demo_creates_a_private_demo_gym_and_logs_in_as_its_owner()
    {
        $owner = $this->startDemo();

        $gym = Gym::sole();
        $this->assertTrue($gym->isDemo());
        $this->assertTrue($gym->demo_expires_at->between(now()->addHours(23), now()->addHours(25)));
        $this->assertTrue($owner->is_demo);
        $this->assertStringEndsWith('@demo.invalid', $owner->email);
        $this->assertSame(GymRole::Owner, $owner->roleIn($gym));
        $this->assertSame($gym->id, session(SetCurrentGym::SESSION_KEY));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('demo.expiresAt', $gym->demo_expires_at->toIso8601String()));
    }

    public function test_each_visitor_gets_their_own_demo_gym()
    {
        $first = $this->startDemo();
        $this->post(route('logout'));
        $second = $this->startDemo();

        $this->assertSame(2, Gym::count());
        $this->assertNotEquals($first->gyms()->value('gyms.id'), $second->gyms()->value('gyms.id'));
    }

    public function test_real_gyms_have_no_demo_banner()
    {
        $owner = User::factory()->memberOf(Gym::factory()->create(), GymRole::Owner)->create();

        $this->actingAs($owner)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('demo', null));
    }

    public function test_demo_accounts_cannot_send_staff_invitations()
    {
        Notification::fake();
        $this->startDemo();

        $this->post(route('staff.invitations.store'), ['name' => 'Invited User', 'email' => 'victim@example.com', 'role' => 'staff'])
            ->assertForbidden();

        Notification::assertNothingSent();
    }

    public function test_demo_accounts_cannot_change_email_or_password()
    {
        $owner = $this->startDemo();

        $this->patch(route('profile.update'), ['name' => 'X', 'email' => 'real@example.com'])->assertForbidden();
        $this->put(route('user-password.update'), [
            'current_password' => 'whatever', 'password' => 'new-password', 'password_confirmation' => 'new-password',
        ])->assertForbidden();

        $this->assertStringEndsWith('@demo.invalid', $owner->refresh()->email);
    }

    public function test_demo_accounts_can_still_explore_owner_features()
    {
        $this->startDemo();

        $this->get(route('staff.index'))->assertOk();
        $this->patch(route('gym.settings.update'), ['name' => 'My Test Gym', 'timezone' => 'Asia/Manila'])
            ->assertRedirect(route('gym.settings.edit'));
    }

    public function test_leaving_the_demo_logs_out_and_points_to_requesting_access()
    {
        $this->startDemo();

        $this->delete(route('demo.destroy'))
            ->assertRedirect(route('home').'#pricing')
            ->assertSessionHas('status', fn ($status) => str_contains($status, config('platform.access_email')));

        $this->assertGuest();
    }

    public function test_the_thanks_message_survives_the_full_page_load_to_the_landing_page()
    {
        $this->startDemo();
        $this->delete(route('demo.destroy'), [], ['X-Inertia' => 'true']);

        $this->get(route('home'), ['X-Inertia' => 'true'])->assertStatus(409); // Inertia hop
        $this->get(route('home'))->assertSee('Thanks for trying the demo!');
    }

    public function test_real_accounts_cannot_use_the_leave_demo_endpoint()
    {
        $owner = User::factory()->memberOf(Gym::factory()->create(), GymRole::Owner)->create();

        $this->actingAs($owner)->delete(route('demo.destroy'))->assertNotFound();
        $this->assertAuthenticatedAs($owner);
    }

    public function test_logged_in_users_cannot_start_a_demo()
    {
        $owner = User::factory()->memberOf(Gym::factory()->create(), GymRole::Owner)->create();

        $this->actingAs($owner)->post(route('demo.store'))->assertRedirect();

        $this->assertSame(1, Gym::count());
    }

    public function test_demos_are_rate_limited_per_visitor()
    {
        for ($i = 0; $i < 5; $i++) {
            $this->startDemo();
            $this->post(route('logout'));
        }

        $this->post(route('demo.store'))->assertTooManyRequests();
        $this->assertSame(5, Gym::count());
    }

    public function test_demo_is_refused_when_too_many_are_active()
    {
        Gym::factory()->count(StartDemo::MAX_ACTIVE)->create()->each(
            fn (Gym $gym) => $gym->forceFill(['demo_expires_at' => now()->addHour()])->save()
        );

        $this->post(route('demo.store'))
            ->assertRedirect(route('home'))
            ->assertSessionHas('status');

        $this->assertGuest();
    }

    public function test_demo_gyms_are_hidden_from_the_platform_admin_list()
    {
        $this->startDemo();
        $this->post(route('logout'));
        Gym::factory()->create(['name' => 'Real Gym']);

        $this->actingAs(User::factory()->platformAdmin()->create())
            ->get(route('platform.gyms.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('gyms.data', 1)
                ->where('gyms.data.0.name', 'Real Gym'));
    }

    public function test_purge_deletes_expired_demos_and_keeps_everything_else()
    {
        $expiredOwner = $this->startDemo();
        $this->post(route('logout'));
        $expiredGym = $expiredOwner->gyms()->first();
        $expiredGym->forceFill(['demo_expires_at' => now()->subMinute()])->save();

        $freshOwner = $this->startDemo();
        $this->post(route('logout'));

        $realGym = Gym::factory()->create();
        $realOwner = User::factory()->memberOf($realGym, GymRole::Owner)->create();

        $this->artisan('demo:purge')->assertSuccessful();

        $this->assertModelMissing($expiredGym);
        $this->assertModelMissing($expiredOwner);
        $this->assertSame(0, Member::withoutGlobalScopes()->where('gym_id', $expiredGym->id)->count());
        $this->assertSame(5, Member::withoutGlobalScopes()->where('gym_id', $freshOwner->gyms()->sole()->id)->count());
        $this->assertModelExists($freshOwner);
        $this->assertModelExists($realGym);
        $this->assertModelExists($realOwner);
        $this->assertDatabaseCount('gym_user', 2);
    }

    public function test_purge_runs_every_hour()
    {
        $this->artisan('schedule:list')->expectsOutputToContain('demo:purge');
    }
}
