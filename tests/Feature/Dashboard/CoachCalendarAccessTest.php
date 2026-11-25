<?php

namespace Tests\Feature\Dashboard;

use App\Enums\GymRole;
use App\Enums\Plan;
use App\Models\Branch;
use App\Models\CoachSchedule;
use App\Models\Gym;
use App\Models\User;
use App\Policies\CoachSchedulePolicy;
use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class CoachCalendarAccessTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private User $owner;

    private User $coach;

    private User $secondCoach;

    private CoachSchedule $schedule;

    private CoachSchedule $secondSchedule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gym = Gym::factory()->create();
        $this->gym->subscription->update(['plan' => Plan::MultiBranch]);
        $this->owner = User::factory()->memberOf($this->gym, GymRole::Owner)->create();
        $this->coach = User::factory()->memberOf($this->gym, GymRole::Coach)->create(['name' => 'Alice Coach']);
        $this->secondCoach = User::factory()->memberOf($this->gym, GymRole::Coach)->create(['name' => 'Beth Coach']);
        app(CurrentGym::class)->set($this->gym);
        $this->schedule = $this->entry($this->coach);
        $this->secondSchedule = $this->entry($this->secondCoach);
    }

    private function entry(User $coach, ?int $branchId = null): CoachSchedule
    {
        $entry = new CoachSchedule;
        $entry->forceFill(['coach_id' => $coach->id, 'branch_id' => $branchId ?? $this->gym->branches()->first()->id, 'title' => 'Training', 'starts_at' => now(), 'ends_at' => now()->addHour()])->save();

        return $entry;
    }

    private function context(User $user, GymRole $role, ?int $branch = null): CoachSchedulePolicy
    {
        $this->actingAs($user);
        app(CurrentGym::class)->set($this->gym, $role, $branch);

        return new CoachSchedulePolicy;
    }

    public function test_owner_and_staff_can_view_gym_coaches_and_manage_entries(): void
    {
        foreach ([$this->owner, User::factory()->memberOf($this->gym)->create()] as $actor) {
            $policy = $this->context($actor, $actor->roleIn($this->gym));
            $this->assertTrue($policy->view($actor, $this->schedule));
            $this->assertTrue($policy->update($actor, $this->secondSchedule));
            $this->assertTrue($policy->cancel($actor, $this->schedule));
            $this->get(route('coach-calendar.roster'))->assertOk()->assertInertia(fn (Assert $p) => $p->component('dashboard/coach-calendar/Coaches')->has('coaches.data', 2)->where('coaches.data.0.name', 'Alice Coach'));
            $this->get(route('coach-calendar.index'))->assertOk()->assertInertia(fn (Assert $p) => $p->component('dashboard/coach-calendar/Index')->has('coaches.data', 2)->where('coaches.data.0.name', 'Alice Coach'));
        }
    }

    public function test_coach_only_sees_and_manages_own_calendar(): void
    {
        $policy = $this->context($this->coach, GymRole::Coach);
        $this->assertTrue($policy->update($this->coach, $this->schedule));
        $this->assertFalse($policy->view($this->coach, $this->secondSchedule));
        $this->assertFalse($policy->create($this->coach, $this->secondCoach->id, $this->schedule->branch_id));
        $this->assertSame([$this->schedule->id], CoachSchedule::pluck('id')->all());
        $this->get(route('coach-calendar.index'))->assertInertia(fn (Assert $p) => $p->has('coaches.data', 1)->where('coaches.data.0.id', $this->coach->id));
    }

    public function test_manager_is_restricted_to_assigned_coaches_and_branch(): void
    {
        $branch = Branch::factory()->create();
        $this->gym->users()->updateExistingPivot($this->secondCoach->id, ['branch_id' => $branch->id]);
        $otherBranchEntry = $this->entry($this->secondCoach, $branch->id);
        $manager = User::factory()->memberOf($this->gym, GymRole::BranchManager)->create();
        $policy = $this->context($manager, GymRole::BranchManager, $this->schedule->branch_id);
        $this->assertTrue($policy->update($manager, $this->schedule));
        $this->assertFalse($policy->view($manager, $this->secondSchedule));
        $this->assertFalse($policy->view($manager, $otherBranchEntry));
        $this->assertFalse($policy->create($manager, $this->coach->id, $branch->id));
        $this->assertSame([$this->schedule->id], CoachSchedule::pluck('id')->all());
        $this->get(route('coach-calendar.index'))->assertInertia(fn (Assert $p) => $p->has('coaches.data', 1)->where('coaches.data.0.id', $this->coach->id));
    }

    public function test_read_only_branch_preserves_view_but_blocks_writes(): void
    {
        $this->gym->branches()->whereKey($this->schedule->branch_id)->update(['is_active' => false]);
        $policy = $this->context($this->owner, GymRole::Owner);
        $this->assertTrue($policy->view($this->owner, $this->schedule));
        $this->assertFalse($policy->update($this->owner, $this->schedule));
        $this->assertFalse($policy->cancel($this->owner, $this->schedule));
    }

    public function test_disabled_feature_returns_404_and_is_not_shared_for_navigation(): void
    {
        $this->context($this->owner, GymRole::Owner);
        Feature::deactivate('coach-calendar');
        $this->get(route('coach-calendar.index'))->assertNotFound();
        $this->get(route('dashboard'))->assertInertia(fn (Assert $p) => $p->where('features', fn ($features) => ! in_array('coach-calendar', $features->all(), true)));
        $this->assertFalse((new CoachSchedulePolicy)->viewAny($this->owner));
    }

    public function test_inactive_actor_or_coach_is_rechecked_without_trusting_context_role(): void
    {
        $policy = $this->context($this->owner, GymRole::Owner);
        $this->gym->users()->updateExistingPivot($this->coach->id, ['is_active' => false]);
        $this->assertTrue($policy->view($this->owner, $this->schedule));
        $this->assertFalse($policy->update($this->owner, $this->schedule));
        $this->context($this->coach, GymRole::Owner);
        $this->assertFalse($policy->viewAny($this->coach));
        $this->assertSame(0, CoachSchedule::count());
    }

    public function test_foreign_actors_branches_and_entries_are_denied(): void
    {
        $other = Gym::factory()->create();
        $foreign = User::factory()->memberOf($other, GymRole::Owner)->create();
        $policy = $this->context($this->owner, GymRole::Owner);
        $foreignBranch = app(CurrentGym::class)->runAs($other, fn () => $other->branches()->first()->id);
        $this->assertFalse($policy->create($this->owner, $this->coach->id, $foreignBranch));
        $this->assertFalse($policy->viewAny($foreign));
        app(CurrentGym::class)->set($other, GymRole::Owner);
        $this->actingAs($foreign);
        $this->assertFalse($policy->view($foreign, $this->schedule));
        $this->assertSame(0, CoachSchedule::count());
    }

    public function test_roster_is_paginated_and_hides_inactive_coaches_and_contact_data(): void
    {
        User::factory()->count(25)->memberOf($this->gym, GymRole::Coach)->create();
        $this->gym->users()->updateExistingPivot($this->secondCoach->id, ['is_active' => false]);
        $this->actingAs($this->owner)->get(route('coach-calendar.index'))->assertInertia(fn (Assert $p) => $p->has('coaches.data', 25)->where('coaches.total', 26)->missing('coaches.data.0.email'));
    }

    public function test_manager_reassignment_and_downgrade_revoke_old_scope(): void
    {
        $manager = User::factory()->memberOf($this->gym, GymRole::BranchManager)->create();
        $policy = $this->context($manager, GymRole::BranchManager, $this->schedule->branch_id);
        $this->assertTrue($policy->viewAny($manager));
        $this->gym->users()->updateExistingPivot($manager->id, ['branch_id' => null]);
        $this->assertFalse($policy->viewAny($manager));
        $this->assertSame(0, CoachSchedule::count());
        $this->gym->users()->updateExistingPivot($manager->id, ['branch_id' => $this->schedule->branch_id]);
        $this->gym->subscription->update(['plan' => Plan::Starter]);
        $this->assertFalse($policy->viewAny($manager));
    }
}
