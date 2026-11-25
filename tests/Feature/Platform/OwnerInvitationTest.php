<?php

namespace Tests\Feature\Platform;

use App\Enums\GymRole;
use App\Enums\Plan;
use App\Models\Gym;
use App\Models\OwnerInvitation;
use App\Models\User;
use App\Notifications\OwnerInvitationNotification;
use App\Support\LegalDocuments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OwnerInvitationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->admin = User::factory()->platformAdmin()->create();
    }

    /**
     * Platform admin sends access; returns the emailed token.
     */
    private function sendAccess(string $email = 'owner@dmfitness.test', string $plan = 'pro', ?string $gymName = 'DM Fitness'): string
    {
        $this->actingAs($this->admin)
            ->post(route('platform.owner-invitations.store'), ['email' => $email, 'plan' => $plan, 'gym_name' => $gymName])
            ->assertRedirect(route('platform.owner-invitations.index'));

        $token = null;
        Notification::assertSentTo(new AnonymousNotifiable, OwnerInvitationNotification::class,
            function ($notification, $channels, $notifiable) use ($email, &$token) {
                $token = $notification->token;

                return $notifiable->routes['mail'] === mb_strtolower($email);
            });

        $this->post(route('logout'));

        return $token;
    }

    // --- Platform admin ----------------------------------------------------

    public function test_platform_admin_sends_access_and_only_a_token_hash_is_stored()
    {
        $token = $this->sendAccess('Owner@DMFitness.test');

        $invitation = OwnerInvitation::sole();
        $this->assertSame('owner@dmfitness.test', $invitation->email);
        $this->assertSame(Plan::Pro, $invitation->plan);
        $this->assertSame('DM Fitness', $invitation->gym_name);
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->assertDatabaseMissing('owner_invitations', ['token_hash' => $token]);
    }

    public function test_sending_again_replaces_the_pending_link()
    {
        $old = $this->sendAccess();
        $new = $this->sendAccess();

        $this->assertSame(1, OwnerInvitation::count());
        $this->get(route('owner-invitations.show', $old))->assertNotFound();
        $this->get(route('owner-invitations.show', $new))->assertOk();
    }

    public function test_platform_admin_sees_invitations_and_can_cancel_a_pending_one()
    {
        $this->sendAccess();
        $invitation = OwnerInvitation::sole();

        $this->actingAs($this->admin)->get(route('platform.owner-invitations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/OwnerInvitations')
                ->where('invitations.data.0.email', 'owner@dmfitness.test')
                ->where('invitations.data.0.status', 'pending')
                ->missing('invitations.data.0.token_hash'));

        $this->actingAs($this->admin)->delete(route('platform.owner-invitations.destroy', $invitation))
            ->assertRedirect(route('platform.owner-invitations.index'));
        $this->assertSame(0, OwnerInvitation::count());
    }

    public function test_gym_owners_and_guests_cannot_send_access()
    {
        $owner = User::factory()->memberOf(Gym::factory()->create(), GymRole::Owner)->create();

        $this->actingAs($owner)->get(route('platform.owner-invitations.index'))->assertForbidden();
        $this->actingAs($owner)
            ->post(route('platform.owner-invitations.store'), ['email' => 'friend@example.com', 'plan' => 'multi_branch'])
            ->assertForbidden();
        $this->post(route('logout'));
        $this->post(route('platform.owner-invitations.store'), ['email' => 'x@example.com', 'plan' => 'pro'])
            ->assertRedirect(route('login'));

        Notification::assertNothingSent();
    }

    // --- Invited owner ---------------------------------------------------

    public function test_new_owner_sets_a_password_then_creates_their_gym_on_the_invited_plan()
    {
        $token = $this->sendAccess();

        $this->get(route('owner-invitations.show', $token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('owner-invitations/Show')
                ->where('status', 'guest')
                ->where('hasAccount', false)
                ->where('invitation.plan', 'Pro'));

        $this->post(route('owner-invitations.register', $token), ['terms_accepted' => true, 'policy_version' => LegalDocuments::bundle()['version'],
            'name' => 'Dina Owner', 'password' => 'password', 'password_confirmation' => 'password',
        ])->assertRedirect(route('gyms.create'));

        $owner = User::where('email', 'owner@dmfitness.test')->sole();
        $this->assertAuthenticatedAs($owner);
        $this->assertTrue($owner->hasVerifiedEmail(), 'The emailed link proves the address.');

        $this->get(route('gyms.create'))
            ->assertInertia(fn (Assert $page) => $page->where('gymName', 'DM Fitness')->where('plan.label', 'Pro'));

        $this->post(route('gyms.store'), ['name' => 'DM Fitness'])->assertRedirect(route('dashboard'));

        $gym = Gym::where('name', 'DM Fitness')->sole();
        $this->assertSame(Plan::Pro, $gym->plan());
        $this->assertSame(GymRole::Owner, $owner->roleIn($gym));
        $this->assertSame($gym->id, OwnerInvitation::sole()->gym_id);
    }

    public function test_person_with_an_existing_account_logs_in_and_continues()
    {
        $existing = User::factory()->create(['email' => 'owner@dmfitness.test']);
        $token = $this->sendAccess();

        $this->get(route('owner-invitations.show', $token))
            ->assertInertia(fn (Assert $page) => $page->where('status', 'guest')->where('hasAccount', true))
            ->assertSessionHas('url.intended', route('owner-invitations.show', $token));

        $this->actingAs($existing)
            ->post(route('owner-invitations.accept', $token), ['terms_accepted' => true, 'policy_version' => LegalDocuments::bundle()['version']])
            ->assertRedirect(route('gyms.create'));

        $this->get(route('gyms.create'))->assertInertia(fn (Assert $page) => $page->component('onboarding/CreateGym'));
    }

    public function test_signing_up_with_an_email_that_already_has_an_account_is_refused()
    {
        User::factory()->create(['email' => 'owner@dmfitness.test', 'name' => 'Original']);
        $token = $this->sendAccess();

        $this->post(route('owner-invitations.register', $token), ['terms_accepted' => true, 'policy_version' => LegalDocuments::bundle()['version'],
            'name' => 'Imposter', 'password' => 'password', 'password_confirmation' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertSame('Original', User::where('email', 'owner@dmfitness.test')->sole()->name);
        $this->assertGuest();
    }

    public function test_someone_logged_in_with_another_email_cannot_use_the_link()
    {
        $token = $this->sendAccess();
        $intruder = User::factory()->create(['email' => 'intruder@example.com']);

        $this->actingAs($intruder)
            ->get(route('owner-invitations.show', $token))
            ->assertInertia(fn (Assert $page) => $page->where('status', 'wrong-account'));
        $this->actingAs($intruder)->post(route('owner-invitations.accept', $token), ['terms_accepted' => true, 'policy_version' => LegalDocuments::bundle()['version']])->assertForbidden();

        $this->actingAs($intruder)->get(route('gyms.create'))
            ->assertInertia(fn (Assert $page) => $page->component('onboarding/NoAccess'));
    }

    public function test_links_work_once_and_expire()
    {
        $token = $this->sendAccess();
        $this->post(route('owner-invitations.register', $token), ['terms_accepted' => true, 'policy_version' => LegalDocuments::bundle()['version'],
            'name' => 'Dina', 'password' => 'password', 'password_confirmation' => 'password',
        ]);
        $this->post(route('logout'));

        $this->get(route('owner-invitations.show', $token))->assertNotFound();
        $this->get(route('owner-invitations.show', 'made-up-token'))->assertNotFound();

        $expired = $this->sendAccess('late@example.com');
        OwnerInvitation::where('email', 'late@example.com')->update(['expires_at' => now()->subMinute()]);
        $this->get(route('owner-invitations.show', $expired))->assertNotFound();
    }

    public function test_weak_passwords_and_missing_names_are_rejected()
    {
        $token = $this->sendAccess();

        $this->post(route('owner-invitations.register', $token), ['terms_accepted' => true, 'policy_version' => LegalDocuments::bundle()['version'],
            'name' => '', 'password' => 'a', 'password_confirmation' => 'b',
        ])->assertSessionHasErrors(['name', 'password']);

        $this->assertDatabaseMissing('users', ['email' => 'owner@dmfitness.test']);
    }
}
