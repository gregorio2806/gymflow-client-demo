<?php

namespace Tests\Feature\Dashboard;

use App\Actions\AcceptInvitation;
use App\Actions\ReconcilePlanLimits;
use App\Enums\GymRole;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\GymInvitation;
use App\Models\User;
use App\Models\WorkoutRoutine;
use App\Support\CoachCalendarAccess;
use App\Support\PlanLimits;
use App\Support\TeamBranches;
use App\Support\WorkoutAccess;
use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamBranchesTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private User $owner;

    private User $staff;

    private Branch $second;

    private int $mainId;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->gym = Gym::factory()->create();
        $this->gym->subscription->update(['plan' => 'multi_branch']);
        app(CurrentGym::class)->set($this->gym);
        $this->mainId = $this->gym->mainBranch()->id;
        $this->second = Branch::factory()->create(['gym_id' => $this->gym->id]);
        $this->owner = User::factory()->memberOf($this->gym, GymRole::Owner)->create();
        $this->staff = User::factory()->memberOf($this->gym, GymRole::Staff)->create();
        $this->actingAs($this->owner);
    }

    private function ids(): array
    {
        return [$this->mainId, $this->second->id];
    }

    private function update(array $overrides = [])
    {
        return $this->patch(route('staff.update', $this->staff), array_replace(['name' => $this->staff->name, 'role' => 'staff', 'branch_ids' => $this->ids()], $overrides));
    }

    public function test_assignments_persist_and_each_branch_manager_can_see_the_person(): void
    {
        $this->update()->assertSessionHasNoErrors();
        $this->assertSame($this->ids(), TeamBranches::ids($this->gym->users()->find($this->staff->id)->pivot));
        foreach ($this->ids() as $id) {
            app(CurrentGym::class)->set($this->gym);
            $manager = User::factory()->memberOf($this->gym, GymRole::BranchManager)->create();
            $this->gym->users()->updateExistingPivot($manager->id, ['branch_id' => $id]);
            $this->actingAs($manager)->get(route('staff.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('members', fn ($members) => collect($members)->contains(fn ($member) => $member['id'] === $this->staff->id && $member['branchIds'] === $this->ids())));
        }
    }

    public function test_invitation_acceptance_and_pending_capacity_use_all_assignments(): void
    {
        $this->post(route('staff.invitations.store'), ['name' => 'Invited User', 'email' => 'multi@example.invalid', 'role' => 'coach', 'branch_ids' => $this->ids()])->assertSessionHasNoErrors();
        $invite = GymInvitation::sole();
        $this->assertSame($this->ids(), TeamBranches::ids($invite));
        $limits = app(PlanLimits::class);
        $this->assertSame(2, $limits->staffUsed($this->gym, null, $this->ids()[0]));
        $this->assertSame(1, $limits->staffUsed($this->gym, null, $this->second->id));
        $user = User::factory()->create(['email' => $invite->email]);
        app(AcceptInvitation::class)->handle($invite, $user);
        $this->assertSame($this->ids(), TeamBranches::ids($this->gym->users()->find($user->id)->pivot));
        $this->assertSame(1, $limits->staffUsed($this->gym, null, $this->second->id));
    }

    public function test_invalid_assignments_are_atomic_and_managers_remain_single_branch(): void
    {
        $foreign = Gym::factory()->create()->mainBranch()->id;
        $this->update(['branch_ids' => [$this->ids()[0], $foreign]])->assertSessionHasErrors('branch_ids.1');
        $this->update(['branch_ids' => []])->assertSessionHasErrors('branch_ids');
        $this->update(['role' => 'branch-manager'])->assertSessionHasErrors('branch_ids');
        $this->second->forceFill(['is_active' => false])->save();
        $this->update()->assertSessionHasErrors('branch_ids');
        $this->assertSame([$this->ids()[0]], TeamBranches::ids($this->gym->users()->find($this->staff->id)->pivot));
        $this->assertSame(GymRole::Staff, $this->staff->roleIn($this->gym));
        $this->post(route('staff.invitations.store'), ['name' => 'Invited User', 'email' => 'foreign@example.invalid', 'role' => 'coach', 'branch_ids' => [$foreign]])->assertSessionHasErrors('branch_ids.0');
    }

    public function test_full_secondary_branch_blocks_assignment_and_reactivation(): void
    {
        for ($i = 0; $i < app(PlanLimits::class)->staffLimit($this->gym); $i++) {
            $user = User::factory()->memberOf($this->gym, GymRole::Staff)->create();
            $this->gym->users()->updateExistingPivot($user->id, ['branch_id' => $this->second->id]);
        }
        $this->update()->assertSessionHasErrors('branch_ids');
        $this->gym->users()->updateExistingPivot($this->staff->id, ['is_active' => false, 'branch_ids' => $this->ids()]);
        $this->patch(route('staff.activation', $this->staff), ['is_active' => true])->assertSessionHasErrors('is_active');
        $this->assertFalse($this->gym->users()->find($this->staff->id)->pivot->is_active);
    }

    public function test_coach_workouts_and_manager_calendar_lookup_use_secondary_assignment(): void
    {
        $this->update(['role' => 'coach'])->assertSessionHasNoErrors();
        $this->actingAs($this->staff);
        app(CurrentGym::class)->set($this->gym, GymRole::Coach);
        $this->assertEqualsCanonicalizing($this->ids(), WorkoutAccess::coachBranchIds());
        $manager = User::factory()->memberOf($this->gym, GymRole::BranchManager)->create();
        $this->gym->users()->updateExistingPivot($manager->id, ['branch_id' => $this->second->id]);
        app(CurrentGym::class)->set($this->gym, GymRole::BranchManager, $this->second->id);
        $this->assertTrue(CoachCalendarAccess::coaches($manager)->whereKey($this->staff->id)->exists());
    }

    public function test_secondary_branch_workout_access_ends_when_assignment_is_removed(): void
    {
        $this->update(['role' => 'coach'])->assertSessionHasNoErrors();
        $this->actingAs($this->staff)->post(route('workout-routines.store'), [
            'branch_id' => $this->second->id, 'request_key' => (string) Str::uuid(), 'name' => 'Secondary branch routine',
            'days' => [['name' => 'Monday', 'exercises' => [['name' => 'Squat', 'sets' => 3, 'reps' => '8']]]],
        ])->assertSessionHasNoErrors();
        $routine = WorkoutRoutine::sole();
        $this->get(route('workout-routines.edit', $routine))->assertOk();
        $this->actingAs($this->owner);
        $this->update(['role' => 'coach', 'branch_ids' => [$this->mainId]])->assertSessionHasNoErrors();
        $this->actingAs($this->staff)->get(route('workout-routines.edit', $routine))->assertNotFound();
    }

    public function test_plan_reconciliation_counts_secondary_assignments_and_preserves_records(): void
    {
        $this->update()->assertSessionHasNoErrors();
        $others = [];
        for ($i = 0; $i < 5; $i++) {
            $user = User::factory()->memberOf($this->gym, GymRole::Coach)->create();
            $this->gym->users()->updateExistingPivot($user->id, ['branch_id' => $this->second->id]);
            $others[] = $user;
        }
        app(ReconcilePlanLimits::class)->handle($this->gym);
        $this->assertSame(5, app(PlanLimits::class)->staffUsed($this->gym, null, $this->second->id));
        $this->assertFalse($this->gym->users()->find($others[4]->id)->pivot->is_active);
        $this->assertSame(7, $this->gym->users()->count());
        $this->assertSame($this->ids(), TeamBranches::ids($this->gym->users()->find($this->staff->id)->pivot));
    }
}
