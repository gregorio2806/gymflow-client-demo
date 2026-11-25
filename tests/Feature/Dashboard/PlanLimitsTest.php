<?php

namespace Tests\Feature\Dashboard;

use App\Actions\AcceptInvitation;
use App\Enums\GymRole;
use App\Enums\Plan;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\GymInvitation;
use App\Models\User;
use App\Support\LegalDocuments;
use App\Support\PlanLimits;
use App\Tenancy\CurrentGym;
use Database\Factories\GymInvitationFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlanLimitsTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->gym = Gym::factory()->create();
        $this->owner = User::factory()->memberOf($this->gym, GymRole::Owner)->create();
        app(CurrentGym::class)->set($this->gym);
    }

    public static function plans(): array
    {
        return [['starter', 2], ['pro', 5], ['multi_branch', 5]];
    }

    #[DataProvider('plans')]
    public function test_staff_boundary_and_pending_reservations(string $plan, int $limit): void
    {
        $this->gym->subscription()->update(['plan' => $plan]);
        User::factory()->count($limit - 1)->memberOf($this->gym, GymRole::Coach)->create();
        $this->actingAs($this->owner)->post(route('staff.invitations.store'), ['email' => 'last@example.test', 'role' => 'staff'])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post(route('staff.invitations.store'), ['email' => 'over@example.test', 'role' => 'coach'])->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('gym_invitations', ['email' => 'over@example.test']);
        // Refreshing a reserved invitation must not consume a second place.
        $this->actingAs($this->owner)->post(route('staff.invitations.store'), ['email' => 'last@example.test', 'role' => 'coach'])->assertSessionHasNoErrors();
    }

    public function test_expired_and_other_gym_invitations_do_not_use_places(): void
    {
        GymInvitation::factory()->expired()->create(['gym_id' => $this->gym->id]);
        $other = Gym::factory()->create();
        app(CurrentGym::class)->runAs($other, fn () => GymInvitation::factory()->count(5)->create());
        $this->actingAs($this->owner)->post(route('staff.invitations.store'), ['email' => 'new@example.test', 'role' => 'staff'])->assertSessionHasNoErrors();
    }

    public function test_acceptance_rechecks_capacity_after_plan_change(): void
    {
        $this->gym->subscription()->update(['plan' => 'pro']);
        User::factory()->count(2)->memberOf($this->gym, GymRole::Staff)->create();
        GymInvitation::factory()->create(['email' => 'new@example.test']);
        $token = GymInvitationFactory::$lastToken;
        $this->gym->subscription()->update(['plan' => 'starter']);
        $invitee = User::factory()->create(['email' => 'new@example.test']);
        $this->actingAs($invitee)->post(route('invitations.accept', $token), ['terms_accepted' => true, 'policy_version' => LegalDocuments::bundle()['version']])->assertSessionHasErrors('email');
        $this->assertNull($invitee->roleIn($this->gym));
        $this->assertNull(GymInvitation::withoutGlobalScope('gym')->sole()->accepted_at);
    }

    public function test_registration_at_capacity_rolls_back_the_account(): void
    {
        User::factory()->count(2)->memberOf($this->gym, GymRole::Staff)->create();
        GymInvitation::factory()->create(['email' => 'new@example.test']);
        $token = GymInvitationFactory::$lastToken;
        $this->post(route('invitations.register', $token), ['terms_accepted' => true, 'policy_version' => LegalDocuments::bundle()['version'], 'name' => 'New Staff', 'password' => 'password', 'password_confirmation' => 'password'])->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'new@example.test']);
        $this->assertGuest();
    }

    public function test_last_reserved_place_can_be_accepted(): void
    {
        User::factory()->memberOf($this->gym, GymRole::Staff)->create();
        GymInvitation::factory()->create(['email' => 'last@example.test']);
        $token = GymInvitationFactory::$lastToken;
        $invitee = User::factory()->create(['email' => 'last@example.test']);
        $this->actingAs($invitee)->post(route('invitations.accept', $token), ['terms_accepted' => true, 'policy_version' => LegalDocuments::bundle()['version']])->assertRedirect(route('dashboard'));
        $this->assertNotNull($invitee->roleIn($this->gym));
    }

    public function test_successful_registration_assigns_branch_and_records_the_invitee_as_actor(): void
    {
        GymInvitation::factory()->create(['email' => 'joined@example.test']);
        $token = GymInvitationFactory::$lastToken;
        $this->post(route('invitations.register', $token), ['terms_accepted' => true, 'policy_version' => LegalDocuments::bundle()['version'], 'name' => 'Joined Staff', 'password' => 'password', 'password_confirmation' => 'password'])->assertRedirect(route('dashboard'));
        $user = User::where('email', 'joined@example.test')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame($this->gym->mainBranch()->id, $this->gym->users()->whereKey($user->id)->sole()->pivot->branch_id);
        $this->assertSame($user->id, AuditLog::sole()->user_id);
    }

    public function test_branch_create_limit_extra_capacity_and_audit(): void
    {
        $this->gym->subscription()->update(['plan' => 'multi_branch']);
        $this->actingAs($this->owner)->post(route('branches.store'), ['name' => 'Second'])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post(route('branches.store'), ['name' => 'Third'])->assertSessionHasErrors('name');
        $this->assertSame(2, $this->gym->branches()->count());
        $this->gym->subscription()->update(['extra_branches' => 1]);
        $this->actingAs($this->owner)->post(route('branches.store'), ['name' => 'Third'])->assertSessionHasNoErrors();
        $this->assertSame(3, $this->gym->branches()->count());
        $this->assertSame(2, AuditLog::where('action', 'branch.created')->count());
        $this->assertSame(5, app(PlanLimits::class)->staffLimit($this->gym));
    }

    public function test_multi_branch_invitations_enforce_each_branch_and_assign_on_acceptance(): void
    {
        $this->gym->subscription()->update(['plan' => 'multi_branch']);
        $second = Branch::create(['name' => 'Second']);
        User::factory()->count(5)->memberOf($this->gym, GymRole::Staff)->create();
        $this->actingAs($this->owner)->post(route('staff.invitations.store'), ['email' => 'full@example.test', 'role' => 'staff', 'branch_id' => $this->gym->mainBranch()->id])->assertSessionHasErrors('email');
        $this->actingAs($this->owner)->post(route('staff.invitations.store'), ['email' => 'room@example.test', 'role' => 'coach', 'branch_id' => $second->id])->assertSessionHasNoErrors();
        $invitation = GymInvitation::sole();
        $this->assertSame($second->id, $invitation->branch_id);
        $user = User::factory()->create(['email' => 'room@example.test']);
        app(AcceptInvitation::class)->handle($invitation, $user);
        $this->assertSame($second->id, $this->gym->users()->whereKey($user->id)->sole()->pivot->branch_id);
    }

    public function test_staff_can_move_to_a_branch_with_capacity_but_not_a_full_branch(): void
    {
        $this->gym->subscription()->update(['plan' => 'multi_branch']);
        $second = Branch::create(['name' => 'Second']);
        $staff = User::factory()->memberOf($this->gym, GymRole::Staff)->create();
        $this->actingAs($this->owner)->patch(route('staff.update', $staff), ['name' => $staff->name, 'role' => 'staff', 'branch_id' => $second->id])->assertSessionHasNoErrors();
        $this->assertSame($second->id, $this->gym->users()->whereKey($staff->id)->sole()->pivot->branch_id);
        $this->assertSame($second->id, AuditLog::sole()->changes['after']['branch_id']);
        User::factory()->count(5)->memberOf($this->gym, GymRole::Staff)->create();
        $this->actingAs($this->owner)->patch(route('staff.update', $staff), ['name' => 'Should roll back', 'role' => 'coach', 'branch_id' => $this->gym->mainBranch()->id])->assertSessionHasErrors('branch_id');
        $this->assertNotSame('Should roll back', $staff->refresh()->name);
        $this->assertSame($second->id, $this->gym->users()->whereKey($staff->id)->sole()->pivot->branch_id);
    }

    public function test_foreign_branch_ids_are_rejected_for_invites_and_team_edits(): void
    {
        $foreign = Gym::factory()->create()->mainBranch();
        $staff = User::factory()->memberOf($this->gym, GymRole::Staff)->create();
        $this->actingAs($this->owner)->post(route('staff.invitations.store'), ['email' => 'new@example.test', 'role' => 'staff', 'branch_id' => $foreign->id])->assertSessionHasErrors('branch_id');
        $this->actingAs($this->owner)->patch(route('staff.update', $staff), ['name' => $staff->name, 'role' => 'staff', 'branch_id' => $foreign->id])->assertSessionHasErrors('branch_id');
        $this->assertDatabaseCount('gym_invitations', 0);
        $this->assertSame($this->gym->mainBranch()->id, $this->gym->users()->whereKey($staff->id)->sole()->pivot->branch_id);
    }

    public function test_branch_edit_preserves_main_status_and_ignores_tenant_input(): void
    {
        $this->gym->subscription()->update(['plan' => 'multi_branch']);
        $main = $this->gym->mainBranch();
        $this->actingAs($this->owner)->patch(route('branches.update', $main), ['name' => 'Updated', 'address' => 'QC', 'phone' => '123', 'is_main' => false, 'gym_id' => 999])->assertSessionHasNoErrors();
        $this->assertSame('Updated', $main->refresh()->name);
        $this->assertTrue($main->is_main);
        $this->assertSame($this->gym->id, $main->gym_id);
        $this->assertSame('branch.updated', AuditLog::sole()->action);
    }

    public function test_branch_page_is_paginated_and_does_not_expose_models(): void
    {
        $this->gym->subscription()->update(['plan' => 'multi_branch', 'extra_branches' => 30]);
        Branch::factory()->count(25)->create();
        $this->actingAs($this->owner)->get(route('branches.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/branches/Index')->has('branches.data', 25)->where('branches.total', 26)
            ->missing('branches.data.0.gym_id')->missing('branches.data.0.created_at'));
    }

    public function test_branches_require_owner_and_multi_branch_plan(): void
    {
        foreach ([Plan::Starter, Plan::Pro] as $plan) {
            $this->gym->subscription()->update(['plan' => $plan]);
            $this->actingAs($this->owner)->get(route('branches.index'))->assertForbidden();
            $this->actingAs($this->owner)->post(route('branches.store'), ['name' => 'Denied'])->assertForbidden();
        }
        $this->gym->subscription()->update(['plan' => 'multi_branch']);
        $staff = User::factory()->memberOf($this->gym, GymRole::Staff)->create();
        $this->actingAs($staff)->get(route('branches.index'))->assertForbidden();
        $this->actingAs($staff)->patch(route('branches.update', $this->gym->mainBranch()), ['name' => 'Denied'])->assertForbidden();
    }

    public static function memberLimits(): array
    {
        return [['starter', 149, true], ['starter', 150, false], ['pro', 499, true], ['pro', 500, false], ['multi_branch', 1000000, true]];
    }

    #[DataProvider('memberLimits')]
    public function test_active_member_capacity_guard(string $plan, int $used, bool $allowed): void
    {
        $this->gym->subscription()->update(['plan' => $plan]);
        if (! $allowed) {
            $this->expectException(ValidationException::class);
        }
        app(PlanLimits::class)->ensureMemberAvailable($this->gym, $used);
        if ($allowed) {
            $this->assertTrue(true);
        }
    }
}
