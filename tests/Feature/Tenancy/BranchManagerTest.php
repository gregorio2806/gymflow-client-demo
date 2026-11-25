<?php

namespace Tests\Feature\Tenancy;

use App\Enums\GymRole;
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
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BranchManagerTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private Branch $assigned;

    private User $owner;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->gym = Gym::factory()->create();
        $this->gym->subscription->update(['plan' => 'multi_branch']);
        app(CurrentGym::class)->set($this->gym);
        $this->assigned = Branch::create(['name' => 'Assigned branch']);
        $this->owner = User::factory()->memberOf($this->gym, GymRole::Owner)->create();
        $this->manager = User::factory()->memberOf($this->gym, GymRole::BranchManager)->create();
        $this->gym->users()->updateExistingPivot($this->manager->id, ['branch_id' => $this->assigned->id]);
    }

    public function test_manager_sees_only_assigned_branch_and_shared_branch_context(): void
    {
        $this->actingAs($this->manager)->get(route('branches.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('navigationBranches', 1)->where('navigationBranches.0.id', $this->assigned->id)
            ->has('branches.data', 1)->where('branches.data.0.id', $this->assigned->id)
            ->where('currentBranch.id', $this->assigned->id)->where('currentBranch.name', 'Assigned branch')->where('canAdd', false));
    }

    public function test_navigation_branches_exclude_other_gyms_and_private_fields(): void
    {
        Gym::factory()->create();
        $this->actingAs($this->owner)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('navigationBranches', 2)
            ->where('navigationBranches', fn ($branches) => collect($branches)->every(fn ($branch) => in_array($branch['id'], [$this->gym->mainBranch()->id, $this->assigned->id], true) && array_keys($branch) === ['id', 'name'])));
    }

    public function test_manager_can_edit_own_branch_and_change_is_audited(): void
    {
        $this->actingAs($this->manager)->patch(route('branches.update', $this->assigned), ['name' => 'New name', 'address' => 'QC', 'phone' => '123'])->assertSessionHasNoErrors();
        $this->assertSame('New name', $this->assigned->refresh()->name);
        $log = AuditLog::sole();
        $this->assertSame('branch.updated', $log->action);
        $this->assertSame($this->manager->id, $log->user_id);
    }

    public function test_manager_cannot_edit_another_branch_or_create_branches(): void
    {
        $main = $this->gym->mainBranch();
        $foreign = Gym::factory()->create()->mainBranch();
        $this->actingAs($this->manager)->patch(route('branches.update', $main), ['name' => 'Hacked'])->assertNotFound();
        $this->actingAs($this->manager)->patch(route('branches.update', $foreign), ['name' => 'Hacked'])->assertNotFound();
        $this->actingAs($this->manager)->post(route('branches.store'), ['name' => 'New'])->assertForbidden();
        $this->assertSame('Main branch', $main->refresh()->name);
    }

    public function test_team_roster_excludes_other_branches_and_pending_invitations(): void
    {
        $ownStaff = User::factory()->memberOf($this->gym, GymRole::Staff)->create();
        $this->gym->users()->updateExistingPivot($ownStaff->id, ['branch_id' => $this->assigned->id]);
        User::factory()->memberOf($this->gym, GymRole::Staff)->create();
        GymInvitation::factory()->create(['branch_id' => $this->assigned->id]);
        $this->actingAs($this->manager)->get(route('staff.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('members', 2)->where('members', fn ($members) => collect($members)->every(fn ($member) => $member['branchId'] === $this->assigned->id))
            ->has('branches', 1)->where('branches.0.id', $this->assigned->id)->has('invitations', 0)->where('canManage', false));
    }

    public function test_manager_cannot_manage_staff_settings_or_platform(): void
    {
        $staff = User::factory()->memberOf($this->gym, GymRole::Staff)->create();
        $this->gym->users()->updateExistingPivot($staff->id, ['branch_id' => $this->assigned->id]);
        $invite = GymInvitation::factory()->create(['branch_id' => $this->assigned->id]);
        $this->actingAs($this->manager)->post(route('staff.invitations.store'), ['name' => 'Invited User', 'email' => 'new@example.test', 'role' => 'staff', 'branch_id' => $this->assigned->id])->assertForbidden();
        $this->actingAs($this->manager)->patch(route('staff.update', $staff), ['name' => 'Hacked', 'role' => 'branch-manager'])->assertForbidden();
        $this->actingAs($this->manager)->delete(route('staff.invitations.destroy', $invite))->assertForbidden();
        $this->actingAs($this->manager)->get(route('gym.settings.edit'))->assertForbidden();
        $this->actingAs($this->manager)->get(route('platform.gyms.index'))->assertForbidden();
    }

    public function test_owner_can_invite_manager_even_when_staff_places_are_full(): void
    {
        User::factory()->count(5)->memberOf($this->gym, GymRole::Staff)->create();
        $this->actingAs($this->owner)->post(route('staff.invitations.store'), ['name' => 'Invited User', 'email' => 'manager@example.test', 'role' => 'branch-manager', 'branch_id' => $this->gym->mainBranch()->id])->assertSessionHasNoErrors();
        $invite = GymInvitation::sole();
        $this->assertSame(GymRole::BranchManager, $invite->role);
        $this->assertSame(5, app(PlanLimits::class)->staffUsed($this->gym));
    }

    public function test_owner_can_promote_and_move_manager_but_demotion_requires_staff_capacity(): void
    {
        $staff = User::factory()->memberOf($this->gym, GymRole::Staff)->create();
        $mainId = $this->gym->mainBranch()->id;
        $this->actingAs($this->owner)->patch(route('staff.update', $staff), ['name' => $staff->name, 'role' => 'branch-manager', 'branch_id' => $this->assigned->id])->assertSessionHasNoErrors();
        $this->assertSame(GymRole::BranchManager, $staff->roleIn($this->gym));
        $this->actingAs($this->owner)->patch(route('staff.update', $staff), ['name' => $staff->name, 'role' => 'branch-manager', 'branch_id' => $mainId])->assertSessionHasNoErrors();
        User::factory()->count(5)->memberOf($this->gym, GymRole::Staff)->create();
        $this->actingAs($this->owner)->patch(route('staff.update', $staff), ['name' => $staff->name, 'role' => 'staff', 'branch_id' => $mainId])->assertSessionHasErrors('role');
        $this->assertSame(GymRole::BranchManager, $staff->roleIn($this->gym));
        $this->actingAs($this->owner)->patch(route('staff.update', $staff), ['name' => $staff->name, 'role' => 'coach', 'branch_id' => $this->assigned->id])->assertSessionHasNoErrors();
        $this->assertSame(GymRole::Coach, $staff->roleIn($this->gym));
    }

    public function test_manager_role_requires_multi_branch_and_an_explicit_valid_branch(): void
    {
        $foreign = Gym::factory()->create()->mainBranch();
        $this->actingAs($this->owner)->post(route('staff.invitations.store'), ['name' => 'Invited User', 'email' => 'missing@example.test', 'role' => 'branch-manager'])->assertSessionHasErrors('branch_id');
        $this->actingAs($this->owner)->post(route('staff.invitations.store'), ['name' => 'Invited User', 'email' => 'foreign@example.test', 'role' => 'branch-manager', 'branch_id' => $foreign->id])->assertSessionHasErrors('branch_id');
        $this->gym->subscription->update(['plan' => 'pro']);
        $this->actingAs($this->owner)->post(route('staff.invitations.store'), ['name' => 'Invited User', 'email' => 'pro@example.test', 'role' => 'branch-manager', 'branch_id' => $this->assigned->id])->assertSessionHasErrors('role');
    }

    public function test_manager_invitation_acceptance_preserves_assignment(): void
    {
        GymInvitation::factory()->create(['email' => 'joined@example.test', 'role' => GymRole::BranchManager, 'branch_id' => $this->assigned->id]);
        $token = GymInvitationFactory::$lastToken;
        $user = User::factory()->create(['email' => 'joined@example.test']);
        $this->actingAs($user)->post(route('invitations.accept', $token), ['terms_accepted' => true, 'policy_version' => LegalDocuments::bundle()['version']])->assertRedirect(route('dashboard'));
        $this->assertSame(GymRole::BranchManager, $user->roleIn($this->gym));
        $this->actingAs($user)->get(route('branches.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('currentBranch.id', $this->assigned->id));
    }

    public function test_manager_invite_cannot_be_accepted_after_downgrade(): void
    {
        GymInvitation::factory()->create(['email' => 'joined@example.test', 'role' => GymRole::BranchManager, 'branch_id' => $this->assigned->id]);
        $token = GymInvitationFactory::$lastToken;
        $this->gym->subscription->update(['plan' => 'pro']);
        $user = User::factory()->create(['email' => 'joined@example.test']);
        $this->actingAs($user)->post(route('invitations.accept', $token), ['terms_accepted' => true, 'policy_version' => LegalDocuments::bundle()['version']])->assertSessionHasErrors('role');
        $this->assertNull($user->roleIn($this->gym));
    }

    public function test_invalid_or_missing_manager_assignment_fails_closed(): void
    {
        $this->gym->users()->updateExistingPivot($this->manager->id, ['branch_id' => null]);
        $this->actingAs($this->manager)->get(route('dashboard'))->assertForbidden();
        $foreign = Gym::factory()->create()->mainBranch();
        $this->gym->users()->updateExistingPivot($this->manager->id, ['branch_id' => $foreign->id]);
        $this->actingAs($this->manager)->get(route('branches.index'))->assertForbidden();
        app(CurrentGym::class)->set($this->gym, GymRole::BranchManager);
        $this->assertSame(0, Branch::count());
    }

    public function test_branch_reassignment_and_role_changes_take_effect_on_next_request(): void
    {
        $mainId = $this->gym->mainBranch()->id;
        $this->actingAs($this->manager)->get(route('branches.index'))->assertOk();
        $this->gym->users()->updateExistingPivot($this->manager->id, ['branch_id' => $mainId]);
        $this->actingAs($this->manager)->get(route('branches.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('branches.data.0.id', $mainId));
        $this->gym->users()->updateExistingPivot($this->manager->id, ['role' => GymRole::Staff]);
        $this->actingAs($this->manager)->get(route('staff.index'))->assertForbidden();
    }

    public function test_system_context_restores_manager_branch_after_run_as(): void
    {
        $current = app(CurrentGym::class);
        $current->set($this->gym, GymRole::BranchManager, $this->assigned->id);
        $this->assertSame(1, Branch::count());
        $current->runAs($this->gym, fn () => $this->assertSame(2, Branch::count()));
        $this->assertSame($this->assigned->id, $current->branchId());
        $this->assertSame(1, Branch::count());
    }

    public function test_owners_keep_all_branch_access_and_team_is_paginated(): void
    {
        User::factory()->count(26)->memberOf($this->gym, GymRole::Staff)->create();
        $this->actingAs($this->owner)->get(route('branches.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('branches.data', 2)->where('currentBranch', null));
        $this->actingAs($this->owner)->get(route('staff.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('members', 25)->where('teamPagination.total', 28)->where('canManage', true));
    }
}
