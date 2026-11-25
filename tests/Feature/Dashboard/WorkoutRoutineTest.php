<?php

namespace Tests\Feature\Dashboard;

use App\Enums\GymRole;
use App\Enums\Plan;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberRoutineAssignment;
use App\Models\User;
use App\Models\WorkoutRoutine;
use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class WorkoutRoutineTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gym = Gym::factory()->create();
        $this->owner = User::factory()->memberOf($this->gym, GymRole::Owner)->create();
        app(CurrentGym::class)->set($this->gym);
        $this->actingAs($this->owner);
    }

    private function data(array $overrides = []): array
    {
        return array_replace(['branch_id' => $this->gym->branches()->first()->id, 'request_key' => (string) Str::uuid(), 'name' => 'Beginner strength', 'notes' => 'Use controlled movements', 'days' => [['name' => 'Monday', 'exercises' => [['name' => 'Squat', 'sets' => '3', 'reps' => '8-12', 'notes' => 'Slow tempo']]]]], $overrides);
    }

    private function routine(): WorkoutRoutine
    {
        $this->post(route('workout-routines.store'), $this->data())->assertSessionHasNoErrors();

        return WorkoutRoutine::latest('id')->firstOrFail();
    }

    public function test_creation_is_double_click_safe_and_ignores_server_owned_input(): void
    {
        $data = $this->data(['gym_id' => 999, 'created_by' => 999]);
        $this->post(route('workout-routines.store'), $data)->assertRedirect();
        $this->post(route('workout-routines.store'), $data)->assertRedirect();
        $routine = WorkoutRoutine::sole();
        $this->assertSame($this->gym->id, $routine->gym_id);
        $this->assertSame($this->owner->id, $routine->created_by);
        $this->assertSame(3, $routine->days[0]['exercises'][0]['sets']);
        $this->assertSame(1, AuditLog::where('action', 'workout-routine.saved')->count());
        $this->post(route('workout-routines.store'), [...$data, 'name' => 'Different'])->assertSessionHasErrors('request_key');
    }

    public function test_assignments_preserve_snapshots_reject_stale_writes_and_keep_history(): void
    {
        $routine = $this->routine();
        $member = Member::factory()->create();
        $url = route('workout-routines.assign', $routine);
        $data = ['member_id' => $member->id, 'revision' => 1];
        $this->post($url, $data)->assertSessionHasNoErrors();
        $this->post($url, $data)->assertSessionHasNoErrors();
        $this->assertSame(1, MemberRoutineAssignment::count());
        $this->assertSame(1, AuditLog::where('action', 'workout-routine.assigned')->count());
        $update = $this->data(['revision' => 1, 'name' => 'Updated strength']);
        $this->patch(route('workout-routines.update', $routine), $update)->assertSessionHasNoErrors();
        $this->assertSame('Beginner strength', MemberRoutineAssignment::sole()->snapshot['name']);
        $this->patch(route('workout-routines.update', $routine), $update)->assertSessionHasErrors('revision');
        $this->post($url, $data)->assertSessionHasErrors('revision');
        $this->post($url, [...$data, 'revision' => 2])->assertSessionHasNoErrors();
        $this->assertSame(2, MemberRoutineAssignment::count());
        $this->assertSame(1, MemberRoutineAssignment::whereNull('ended_at')->count());
        $this->assertSame('Updated strength', MemberRoutineAssignment::whereNull('ended_at')->sole()->snapshot['name']);
    }

    public function test_cross_gym_and_cross_branch_member_assignments_are_blocked(): void
    {
        $routine = $this->routine();
        $foreign = Gym::factory()->create();
        $member = app(CurrentGym::class)->runAs($foreign, fn () => Member::factory()->create());
        $this->post(route('workout-routines.assign', $routine), ['member_id' => $member->id, 'revision' => 1])->assertNotFound();
        $branch = Branch::factory()->create(['gym_id' => $this->gym->id]);
        $member = Member::factory()->create(['home_branch_id' => $branch->id]);
        $this->post(route('workout-routines.assign', $routine), ['member_id' => $member->id, 'revision' => 1])->assertNotFound();
        $this->assertSame(0, MemberRoutineAssignment::count());
    }

    public function test_read_only_branch_and_inactive_member_reject_writes(): void
    {
        $routine = $this->routine();
        $member = Member::factory()->inactive()->create();
        $this->post(route('workout-routines.assign', $routine), ['member_id' => $member->id, 'revision' => 1])->assertSessionHasErrors('member_id');
        $member->forceFill(['is_active' => true])->save();
        $routine->branch->forceFill(['is_active' => false])->save();
        $this->patch(route('workout-routines.update', $routine), $this->data(['revision' => 1]))->assertSessionHasErrors('branch_id');
        $this->post(route('workout-routines.assign', $routine), ['member_id' => $member->id, 'revision' => 1])->assertSessionHasErrors('branch_id');
        $this->get(route('workout-routines.edit', $routine))->assertInertia(fn (Assert $page) => $page->where('writable', false));
    }

    public function test_coaches_only_manage_their_own_routines_in_their_branch(): void
    {
        $ownersRoutine = $this->routine();
        $coach = User::factory()->memberOf($this->gym, GymRole::Coach)->create();
        $this->actingAs($coach)->get(route('workout-routines.edit', $ownersRoutine))->assertNotFound();
        $this->get(route('workout-routines.index'))->assertInertia(fn (Assert $page) => $page->has('routines.data', 0));
        $this->post(route('workout-routines.store'), $this->data())->assertSessionHasNoErrors();
        $own = WorkoutRoutine::sole();
        $this->assertSame($coach->id, $own->created_by);
        $this->get(route('workout-routines.edit', $own))->assertOk();
        $member = Member::factory()->create();
        $this->post(route('workout-routines.assign', $own), ['member_id' => $member->id, 'revision' => 1])->assertSessionHasNoErrors();
        $branch = Branch::factory()->create(['gym_id' => $this->gym->id]);
        $this->post(route('workout-routines.store'), $this->data(['branch_id' => $branch->id]))->assertForbidden();
        $this->gym->users()->updateExistingPivot($coach->id, ['is_active' => false]);
        $this->get(route('workout-routines.index'))->assertRedirect(route('gyms.create'));
    }

    public function test_manager_cannot_read_or_write_other_branch_routines(): void
    {
        $this->gym->subscription->update(['plan' => Plan::MultiBranch]);
        $main = $this->routine();
        $branch = Branch::factory()->create(['gym_id' => $this->gym->id]);
        $this->post(route('workout-routines.store'), $this->data(['branch_id' => $branch->id]))->assertSessionHasNoErrors();
        $other = WorkoutRoutine::latest('id')->firstOrFail();
        $manager = User::factory()->memberOf($this->gym, GymRole::BranchManager)->create();
        $this->actingAs($manager)->get(route('workout-routines.edit', $other))->assertNotFound();
        $this->patch(route('workout-routines.update', $other), $this->data(['revision' => 1]))->assertNotFound();
        $this->get(route('workout-routines.edit', $main))->assertOk();
        $this->get(route('workout-routines.create'))->assertInertia(fn (Assert $page) => $page->has('branches.data', 1));
    }

    public function test_disabled_feature_hides_pages_and_blocks_writes(): void
    {
        Feature::for($this->gym)->deactivate('workouts');
        $this->get(route('workout-routines.index'))->assertNotFound();
        $this->post(route('workout-routines.store'), $this->data())->assertNotFound();
    }

    public function test_bounded_structure_and_creation_key_are_required(): void
    {
        $data = $this->data(['request_key' => null, 'revision' => 1]);
        $this->post(route('workout-routines.store'), $data)->assertSessionHasErrors('request_key');
        $data = $this->data();
        $data['days'][0]['exercises'][0]['sets'] = 0;
        $this->post(route('workout-routines.store'), $data)->assertSessionHasErrors('days.0.exercises.0.sets');
        $data = $this->data();
        $data['days'] = array_fill(0, 8, $data['days'][0]);
        $this->post(route('workout-routines.store'), $data)->assertSessionHasErrors('days');
        $data = $this->data();
        $data['days'][0]['exercises'] = array_fill(0, 21, $data['days'][0]['exercises'][0]);
        $this->post(route('workout-routines.store'), $data)->assertSessionHasErrors('days.0.exercises');
        $this->assertSame(0, WorkoutRoutine::count());
    }

    public function test_member_selector_is_paginated_and_excludes_contacts_and_transferred_members(): void
    {
        $routine = $this->routine();
        Member::factory()->count(26)->create(['email' => 'private@example.invalid']);
        $this->get(route('workout-routines.edit', $routine))->assertInertia(fn (Assert $page) => $page->has('members.data', 25)->missing('members.data.0.email')->missing('members.data.0.mobile')->where('members.total', 26));
        $member = Member::firstOrFail();
        $this->post(route('workout-routines.assign', $routine), ['member_id' => $member->id, 'revision' => 1]);
        $branch = Branch::factory()->create(['gym_id' => $this->gym->id]);
        $member->forceFill(['home_branch_id' => $branch->id])->save();
        $this->get(route('workout-routines.edit', $routine))->assertInertia(fn (Assert $page) => $page->has('assignments.data', 0));
    }

    public function test_assignment_replacement_and_snapshot_immutability(): void
    {
        $first = $this->routine();
        $member = Member::factory()->create();
        $this->post(route('workout-routines.assign', $first), ['member_id' => $member->id, 'revision' => 1])->assertSessionHasNoErrors();
        $second = $this->routine();
        $this->post(route('workout-routines.assign', $second), ['member_id' => $member->id, 'revision' => 1])->assertSessionHasNoErrors();
        $this->assertSame(2, MemberRoutineAssignment::count());
        $this->assertSame($second->id, MemberRoutineAssignment::whereNull('ended_at')->sole()->workout_routine_id);
        $this->expectException(\LogicException::class);
        MemberRoutineAssignment::firstOrFail()->forceFill(['snapshot' => ['name' => 'Tampered']])->save();
    }

    public function test_audit_failure_rolls_back_assignment_and_replacement(): void
    {
        $routine = $this->routine();
        $member = Member::factory()->create();
        $this->post(route('workout-routines.assign', $routine), ['member_id' => $member->id, 'revision' => 1])->assertSessionHasNoErrors();
        $second = $this->routine();
        AuditLog::creating(function ($log) {
            if ($log->action === 'workout-routine.assigned') {
                throw new \RuntimeException('Audit unavailable');
            }
        });
        try {
            $this->post(route('workout-routines.assign', $second), ['member_id' => $member->id, 'revision' => 1])->assertStatus(500);
            $this->assertSame(1, MemberRoutineAssignment::count());
            $this->assertNull(MemberRoutineAssignment::sole()->ended_at);
        } finally {
            AuditLog::flushEventListeners();
        }
    }

    public function test_staff_can_manage_routines_but_cannot_move_their_branch(): void
    {
        $staff = User::factory()->memberOf($this->gym, GymRole::Staff)->create();
        $this->actingAs($staff);
        $routine = $this->routine();
        $branch = Branch::factory()->create(['gym_id' => $this->gym->id]);
        $this->patch(route('workout-routines.update', $routine), $this->data(['revision' => 1, 'branch_id' => $branch->id]))->assertSessionHasErrors('branch_id');
        $this->assertSame(1, $routine->refresh()->revision);
    }

    public function test_coach_cannot_reuse_another_coachs_creation_key(): void
    {
        $routine = $this->routine();
        $coach = User::factory()->memberOf($this->gym, GymRole::Coach)->create();
        $this->actingAs($coach)->post(route('workout-routines.store'), $this->data(['request_key' => $routine->request_key]))->assertForbidden();
        $this->assertDatabaseCount('workout_routines', 1);
    }
}
