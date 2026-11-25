<?php

namespace Tests\Feature\Dashboard;

use App\Enums\GymRole;
use App\Models\AuditLog;
use App\Models\Gym;
use App\Models\GymInvitation;
use App\Models\User;
use App\Notifications\GymInvitationNotification;
use App\Support\LegalDocuments;
use App\Tenancy\CurrentGym;
use Database\Factories\GymInvitationFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StaffInvitationTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->gym = Gym::factory()->create(['name' => 'DM Fitness']);
        $this->owner = User::factory()->memberOf($this->gym, GymRole::Owner)->create();
    }

    /**
     * Create a pending invitation for the gym and return its plain token.
     */
    private function invite(string $email = 'coach@example.com', ?Gym $gym = null, bool $expired = false): string
    {
        $factory = GymInvitation::factory()->state(['email' => $email, 'role' => GymRole::Coach, 'gym_id' => ($gym ?? $this->gym)->id]);
        ($expired ? $factory->expired() : $factory)->create();

        return GymInvitationFactory::$lastToken;
    }

    // --- Owner side ---------------------------------------------------------

    public function test_owner_can_invite_staff_and_an_email_is_sent()
    {
        $this->actingAs($this->owner)
            ->post(route('staff.invitations.store'), ['email' => 'Juan@Example.com', 'role' => 'staff'])
            ->assertRedirect(route('staff.index'));

        app(CurrentGym::class)->set($this->gym);
        $invitation = GymInvitation::sole();
        $this->assertSame('juan@example.com', $invitation->email);
        $this->assertSame(GymRole::Staff, $invitation->role);
        $this->assertSame('staff.invited', AuditLog::sole()->action);

        Notification::assertSentTo(new AnonymousNotifiable, GymInvitationNotification::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'juan@example.com');
    }

    public function test_only_a_hash_of_the_token_is_stored()
    {
        $this->actingAs($this->owner)
            ->post(route('staff.invitations.store'), ['email' => 'juan@example.com', 'role' => 'staff']);

        Notification::assertSentTo(new AnonymousNotifiable, GymInvitationNotification::class, function ($notification) {
            $this->assertDatabaseMissing('gym_invitations', ['token_hash' => $notification->token]);
            $this->assertDatabaseHas('gym_invitations', ['token_hash' => hash('sha256', $notification->token)]);

            return true;
        });
    }

    public function test_owner_role_cannot_be_invited()
    {
        $this->actingAs($this->owner)
            ->post(route('staff.invitations.store'), ['email' => 'juan@example.com', 'role' => 'owner'])
            ->assertSessionHasErrors('role');
    }

    public function test_existing_team_members_cannot_be_invited_again()
    {
        $this->actingAs($this->owner)
            ->post(route('staff.invitations.store'), ['email' => $this->owner->email, 'role' => 'staff'])
            ->assertSessionHasErrors('email');
    }

    public function test_reinviting_replaces_the_previous_invitation()
    {
        $this->actingAs($this->owner)->post(route('staff.invitations.store'), ['email' => 'juan@example.com', 'role' => 'staff']);
        $this->actingAs($this->owner)->post(route('staff.invitations.store'), ['email' => 'juan@example.com', 'role' => 'coach']);

        $this->assertDatabaseCount('gym_invitations', 1);
        $this->assertDatabaseHas('gym_invitations', ['email' => 'juan@example.com', 'role' => 'coach']);
    }

    public function test_staff_page_lists_team_and_pending_invitations()
    {
        $this->invite('pending@example.com');

        $this->actingAs($this->owner)
            ->get(route('staff.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/gym/Staff')
                ->has('members', 1)
                ->where('members.0.role', 'owner')
                ->where('members.0.isYou', true)
                ->has('invitations', 1)
                ->where('invitations.0.email', 'pending@example.com')
                ->missing('invitations.0.token_hash'));
    }

    public function test_staff_and_coaches_cannot_manage_staff()
    {
        $staff = User::factory()->memberOf($this->gym, GymRole::Staff)->create();

        $this->actingAs($staff)->get(route('staff.index'))->assertForbidden();
        $this->actingAs($staff)
            ->post(route('staff.invitations.store'), ['email' => 'x@example.com', 'role' => 'staff'])
            ->assertForbidden();

        Notification::assertNothingSent();
    }

    public function test_owner_can_cancel_an_invitation()
    {
        $this->invite();
        $invitation = GymInvitation::withoutGlobalScope('gym')->sole();

        $this->actingAs($this->owner)
            ->delete(route('staff.invitations.destroy', $invitation->id))
            ->assertRedirect(route('staff.index'));

        $this->assertDatabaseCount('gym_invitations', 0);
    }

    public function test_owner_cannot_cancel_another_gyms_invitation()
    {
        $otherGym = Gym::factory()->create();
        $this->invite('theirs@example.com', $otherGym);
        $invitation = GymInvitation::withoutGlobalScope('gym')->sole();

        $this->actingAs($this->owner)
            ->delete(route('staff.invitations.destroy', $invitation->id))
            ->assertNotFound();

        $this->assertDatabaseCount('gym_invitations', 1);
    }

    // --- Invitee side -------------------------------------------------------

    public function test_guest_sees_the_invitation_and_is_returned_to_it_after_login()
    {
        $token = $this->invite();

        $this->get(route('invitations.show', $token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('invitations/Show')
                ->where('status', 'guest')
                ->where('invitation.gymName', 'DM Fitness'))
            ->assertSessionHas('url.intended', route('invitations.show', $token));
    }

    public function test_invited_user_can_accept_and_joins_the_gym()
    {
        $token = $this->invite('coach@example.com');
        $coach = User::factory()->unverified()->create(['email' => 'Coach@example.com']);

        $this->actingAs($coach)
            ->get(route('invitations.show', $token))
            ->assertInertia(fn (Assert $page) => $page->where('status', 'ready'));

        $this->actingAs($coach)
            ->post(route('invitations.accept', $token), ['terms_accepted' => true, 'policy_version' => LegalDocuments::bundle()['version']])
            ->assertRedirect(route('dashboard'));

        $this->assertSame(GymRole::Coach, $coach->roleIn($this->gym));
        $this->assertTrue($coach->refresh()->hasVerifiedEmail(), 'Opening the emailed link proves the email address.');
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_an_invitation_can_only_be_used_once()
    {
        $token = $this->invite('coach@example.com');
        $coach = User::factory()->create(['email' => 'coach@example.com']);

        $this->actingAs($coach)->post(route('invitations.accept', $token), ['terms_accepted' => true, 'policy_version' => LegalDocuments::bundle()['version']]);

        $this->actingAs($coach)->post(route('invitations.accept', $token), ['terms_accepted' => true, 'policy_version' => LegalDocuments::bundle()['version']])->assertNotFound();
        $this->get(route('invitations.show', $token))->assertNotFound();
    }

    public function test_someone_with_a_different_email_cannot_accept()
    {
        $token = $this->invite('coach@example.com');
        $intruder = User::factory()->create(['email' => 'intruder@example.com']);

        $this->actingAs($intruder)
            ->get(route('invitations.show', $token))
            ->assertInertia(fn (Assert $page) => $page->where('status', 'wrong-account'));

        $this->actingAs($intruder)->post(route('invitations.accept', $token), ['terms_accepted' => true, 'policy_version' => LegalDocuments::bundle()['version']])->assertForbidden();

        $this->assertNull($intruder->roleIn($this->gym));
    }

    public function test_expired_or_made_up_links_do_not_work()
    {
        $token = $this->invite('coach@example.com', expired: true);
        $coach = User::factory()->create(['email' => 'coach@example.com']);

        $this->get(route('invitations.show', $token))->assertNotFound();
        $this->get(route('invitations.show', 'made-up-token'))->assertNotFound();
        $this->actingAs($coach)->post(route('invitations.accept', $token), ['terms_accepted' => true, 'policy_version' => LegalDocuments::bundle()['version']])->assertNotFound();
    }

    public function test_new_staff_create_their_account_from_the_invitation()
    {
        $token = $this->invite('newcoach@example.com');

        $this->get(route('invitations.show', $token))
            ->assertInertia(fn (Assert $page) => $page->where('status', 'guest')->where('hasAccount', false));

        $this->post(route('invitations.register', $token), ['terms_accepted' => true, 'policy_version' => LegalDocuments::bundle()['version'],
            'name' => 'New Coach', 'password' => 'password', 'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard'));

        $coach = User::where('email', 'newcoach@example.com')->sole();
        $this->assertAuthenticatedAs($coach);
        $this->assertTrue($coach->hasVerifiedEmail());
        $this->assertSame(GymRole::Coach, $coach->roleIn($this->gym));
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_invitees_with_an_existing_account_are_asked_to_log_in()
    {
        User::factory()->create(['email' => 'coach@example.com']);
        $token = $this->invite('coach@example.com');

        $this->get(route('invitations.show', $token))
            ->assertInertia(fn (Assert $page) => $page->where('hasAccount', true));

        $this->post(route('invitations.register', $token), ['terms_accepted' => true, 'policy_version' => LegalDocuments::bundle()['version'],
            'name' => 'Imposter', 'password' => 'password', 'password_confirmation' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertNull(User::where('email', 'coach@example.com')->sole()->roleIn($this->gym));
    }

    public function test_used_invitations_cannot_create_more_accounts()
    {
        $token = $this->invite('newcoach@example.com');
        $this->post(route('invitations.register', $token), ['terms_accepted' => true, 'policy_version' => LegalDocuments::bundle()['version'],
            'name' => 'New Coach', 'password' => 'password', 'password_confirmation' => 'password',
        ]);
        $this->post(route('logout'));

        $this->post(route('invitations.register', $token), ['terms_accepted' => true, 'policy_version' => LegalDocuments::bundle()['version'],
            'name' => 'Again', 'password' => 'password', 'password_confirmation' => 'password',
        ])->assertNotFound();
    }

    public function test_guests_must_log_in_to_accept()
    {
        $token = $this->invite();

        $this->post(route('invitations.accept', $token), ['terms_accepted' => true, 'policy_version' => LegalDocuments::bundle()['version']])->assertRedirect(route('login'));
    }
}
