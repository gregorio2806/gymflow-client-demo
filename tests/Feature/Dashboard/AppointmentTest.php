<?php

namespace Tests\Feature\Dashboard;

use App\Enums\GymRole;
use App\Enums\Plan;
use App\Models\Branch;
use App\Models\CoachSchedule;
use App\Models\Gym;
use App\Models\Member;
use App\Models\User;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private User $owner;

    private User $coach;

    private Member $member;

    private array $data;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-08 12:00', 'Asia/Manila'));
        $this->gym = Gym::factory()->create();
        $this->gym->subscription->update(['plan' => Plan::MultiBranch]);
        $this->owner = User::factory()->memberOf($this->gym, GymRole::Owner)->create();
        $this->coach = User::factory()->memberOf($this->gym, GymRole::Coach)->create();
        app(CurrentGym::class)->set($this->gym);
        $this->gym->users()->updateExistingPivot($this->coach->id, ['availability' => array_fill(0, 7, ['enabled' => true, 'start' => '09:00', 'end' => '18:00'])]);
        $this->member = Member::factory()->create(['name' => 'Fictional booking member']);
        $this->data = ['coach_id' => $this->coach->id, 'branch_id' => $this->gym->mainBranch()->id, 'member_id' => $this->member->id, 'service' => 'personal-training', 'starts_at' => '2026-10-08T09:00', 'ends_at' => '2026-10-08T10:00', 'request_key' => (string) Str::uuid(), 'location' => 'Training area', 'notes' => 'Fictional booking'];
        $this->actingAs($this->owner);
    }

    public function test_booking_appears_in_calendar_and_reports_and_status_is_terminal(): void
    {
        $this->post(route('appointments.store'), $this->data)->assertSessionHasNoErrors();
        $this->post(route('appointments.store'), $this->data)->assertSessionHasNoErrors();
        $row = CoachSchedule::sole();
        $this->assertSame('appointment', $row->kind);
        $this->assertSame('2026-10-08 01:00:00', $row->starts_at->format('Y-m-d H:i:s'));
        $this->get(route('appointments.index', ['date' => '2026-10-08']))->assertInertia(fn (Assert $p) => $p->has('appointments.data', 1)->where('appointments.data.0.customer', $this->member->name));
        $this->member->update(['name' => 'Renamed fictional booking member']);
        $this->get(route('appointments.index', ['date' => '2026-10-08', 'search' => 'Renamed fictional']))->assertInertia(fn (Assert $p) => $p->has('appointments.data', 1)->where('appointments.data.0.customer', 'Renamed fictional booking member'));
        $this->get(route('coach-calendar.index', ['coach_id' => $this->coach->id, 'month' => '2026-10', 'day' => '2026-10-08']))->assertInertia(fn (Assert $p) => $p->where('days.7.services.0.count', 1)->where('schedules.data.0.kind', 'appointment')->where('schedules.data.0.canEdit', false));
        $this->patch(route('appointments.status', $row), ['status' => 'completed', 'revision' => 1])->assertSessionHasNoErrors();
        $this->patch(route('appointments.status', $row), ['status' => 'completed', 'revision' => 1])->assertSessionHasNoErrors();
        $this->patch(route('appointments.status', $row), ['status' => 'cancelled', 'revision' => 2])->assertSessionHasErrors('revision');
        $this->patch(route('appointments.update', $row), [...$this->data, 'revision' => 2])->assertForbidden();
        $this->get(route('reports.index', ['report' => 'appointments', 'from' => '2026-10-08', 'through' => '2026-10-08']))->assertInertia(fn (Assert $p) => $p->where('detail.metrics.0.value', 1)->where('detail.metrics.1.value', 1)->where('detail.rows.data.0.status', 'Completed')->where('detail.analysis.0.rows.0.completed', 1));
    }

    public function test_slots_and_server_conflicts_cover_blocks_hours_and_branch_assignments(): void
    {
        $block = ['coach_id' => $this->coach->id, 'branch_id' => $this->data['branch_id'], 'title' => 'Break', 'starts_at' => '2026-10-08T10:00', 'ends_at' => '2026-10-08T11:00', 'request_key' => (string) Str::uuid(), 'month' => '2026-10', 'day' => '2026-10-08'];
        $this->post(route('coach-calendar.store'), $block)->assertSessionHasNoErrors();
        $query = ['coach_id' => $this->coach->id, 'branch_id' => $this->data['branch_id'], 'date' => '2026-10-08', 'duration' => 60];
        $slots = $this->getJson(route('appointments.slots', $query))->assertOk()->json('slots');
        $this->assertContains('2026-10-08T09:00', array_column($slots, 'starts_at'));
        $this->assertNotContains('2026-10-08T09:30', array_column($slots, 'starts_at'));
        $this->post(route('appointments.store'), [...$this->data, 'starts_at' => '2026-10-08T10:00', 'ends_at' => '2026-10-08T11:00'])->assertSessionHasErrors('starts_at');
        $this->post(route('appointments.store'), [...$this->data, 'starts_at' => '2026-10-08T08:00', 'ends_at' => '2026-10-08T09:00'])->assertSessionHasErrors('starts_at');
        $other = Branch::factory()->create();
        $this->post(route('appointments.store'), [...$this->data, 'branch_id' => $other->id])->assertSessionHasErrors('branch_id');
        $this->getJson(route('appointments.slots', [...$query, 'branch_id' => $other->id]))->assertOk()->assertJson(['slots' => []]);
        $this->gym->users()->updateExistingPivot($this->coach->id, ['branch_ids' => [$this->data['branch_id'], $other->id]]);
        $this->post(route('appointments.store'), [...$this->data, 'branch_id' => $other->id])->assertSessionHasNoErrors();
        $this->post(route('appointments.store'), [...$this->data, 'request_key' => (string) Str::uuid()])->assertSessionHasErrors('starts_at');
        $this->get(route('reports.index', ['report' => 'appointments', 'from' => '2026-10-08', 'through' => '2026-10-08']))->assertInertia(fn (Assert $p) => $p->where('detail.metrics.0.value', 1));
    }

    public function test_reschedule_coach_customer_and_cancel_release_slots_with_stale_protection(): void
    {
        $this->post(route('appointments.store'), $this->data)->assertSessionHasNoErrors();
        $row = CoachSchedule::sole();
        $coach = User::factory()->memberOf($this->gym, GymRole::Coach)->create();
        $this->gym->users()->updateExistingPivot($coach->id, ['availability' => array_fill(0, 7, ['enabled' => true, 'start' => '09:00', 'end' => '18:00'])]);
        $update = [...$this->data, 'member_id' => null, 'customer_name' => 'Fictional guest', 'coach_id' => $coach->id, 'revision' => 1];
        $this->patch(route('appointments.update', $row), $update)->assertSessionHasNoErrors();
        $this->patch(route('appointments.update', $row), $update)->assertSessionHasNoErrors();
        $this->patch(route('appointments.update', $row), [...$update, 'notes' => 'Stale'])->assertSessionHasErrors('revision');
        $this->assertSame($coach->id, $row->refresh()->coach_id);
        $this->patch(route('appointments.status', $row), ['status' => 'cancelled', 'revision' => 2])->assertSessionHasNoErrors();
        $slots = $this->getJson(route('appointments.slots', ['coach_id' => $coach->id, 'branch_id' => $row->branch_id, 'date' => '2026-10-08', 'duration' => 60]))->assertOk()->json('slots');
        $this->assertContains('2026-10-08T09:00', array_column($slots, 'starts_at'));
        $this->assertSame('cancelled', $row->refresh()->status);
    }

    public function test_availability_and_roles_preserve_scopes_and_existing_bookings(): void
    {
        $hours = array_fill(0, 7, ['enabled' => true, 'start' => '10:00', 'end' => '18:00']);
        $this->put(route('coach-calendar.availability', $this->coach->id), ['revision' => 1, 'hours' => $hours])->assertSessionHasNoErrors();
        $this->put(route('coach-calendar.availability', $this->coach->id), ['revision' => 1, 'hours' => $hours])->assertSessionHasErrors('revision');
        $this->put(route('coach-calendar.availability', $this->coach->id), ['revision' => 2, 'hours' => array_fill(0, 7, ['enabled' => true, 'start' => '18:00', 'end' => '09:00'])])->assertSessionHasErrors('hours.0.end');
        $this->actingAs($this->coach)->post(route('appointments.store'), $this->data)->assertForbidden();
        $this->getJson(route('appointments.members', ['coach_id' => $this->coach->id, 'branch_id' => $this->data['branch_id']]))->assertForbidden();
        $this->put(route('coach-calendar.availability', $this->coach->id), ['revision' => 2, 'hours' => $hours])->assertSessionHasNoErrors();
        $other = User::factory()->memberOf($this->gym, GymRole::Coach)->create();
        $this->put(route('coach-calendar.availability', $other->id), ['revision' => 1, 'hours' => $hours])->assertNotFound();
        $this->get(route('coach-calendar.index', ['view' => 'week', 'month' => '2026-10', 'day' => '2026-10-08']))->assertInertia(fn (Assert $p) => $p->has('days', 7)->where('view', 'week'));
    }

    public function test_manager_books_visiting_members_only_at_assigned_branch_and_foreign_members_are_rejected(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->memberOf($this->gym, GymRole::BranchManager)->create();
        $this->gym->users()->updateExistingPivot($manager->id, ['branch_id' => $branch->id]);
        $this->gym->users()->updateExistingPivot($this->coach->id, ['branch_ids' => [$this->data['branch_id'], $branch->id]]);
        $this->actingAs($manager)->getJson(route('appointments.members', ['coach_id' => $this->coach->id, 'branch_id' => $branch->id]))->assertOk()->assertJsonPath('members.0.id', $this->member->id);
        $this->post(route('appointments.store'), [...$this->data, 'branch_id' => $branch->id])->assertSessionHasNoErrors();
        $this->post(route('appointments.store'), [...$this->data, 'request_key' => (string) Str::uuid()])->assertNotFound();
        $this->put(route('coach-calendar.availability', $this->coach->id), [])->assertForbidden();
        $foreignGym = Gym::factory()->create();
        $foreignMember = app(CurrentGym::class)->runAs($foreignGym, fn () => Member::factory()->create());
        $this->actingAs($this->owner)->post(route('appointments.store'), [...$this->data, 'member_id' => $foreignMember->id, 'starts_at' => '2026-10-08T11:00', 'ends_at' => '2026-10-08T12:00', 'request_key' => (string) Str::uuid()])->assertNotFound();
        $this->assertDatabaseCount('coach_schedules', 1);
    }

    public function test_future_completion_is_rejected_and_no_show_is_reported_after_the_session(): void
    {
        $this->post(route('appointments.store'), [...$this->data, 'starts_at' => '2026-10-08T14:00', 'ends_at' => '2026-10-08T15:00'])->assertSessionHasNoErrors();
        $row = CoachSchedule::sole();
        $this->patch(route('appointments.status', $row), ['revision' => 1, 'status' => 'completed'])->assertSessionHasErrors('status');
        $this->travelTo(CarbonImmutable::parse('2026-10-08 16:00', 'Asia/Manila'));
        $this->patch(route('appointments.status', $row), ['revision' => 1, 'status' => 'no-show'])->assertSessionHasNoErrors();
        $this->get(route('reports.index', ['report' => 'appointments', 'status' => 'no-show', 'from' => '2026-10-08', 'through' => '2026-10-08']))->assertInertia(fn (Assert $p) => $p->where('detail.metrics.3.value', 1)->where('detail.rows.data.0.status', 'No-show'));
    }
}
