<?php

namespace Tests\Feature\Dashboard;

use App\Enums\GymRole;
use App\Enums\Plan;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\CoachSchedule;
use App\Models\Gym;
use App\Models\User;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class CoachScheduleManagementTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private User $owner;

    private User $coach;

    private CoachSchedule $schedule;

    private array $creation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gym = Gym::factory()->create();
        $this->gym->subscription->update(['plan' => Plan::MultiBranch]);
        $this->owner = User::factory()->memberOf($this->gym, GymRole::Owner)->create();
        $this->coach = User::factory()->memberOf($this->gym, GymRole::Coach)->create();
        app(CurrentGym::class)->set($this->gym);
        $this->creation = ['coach_id' => $this->coach->id, 'branch_id' => $this->gym->mainBranch()->id, 'title' => 'Original session', 'starts_at' => '2026-10-06T09:00', 'ends_at' => '2026-10-06T10:00', 'notes' => 'Original note', 'request_key' => (string) Str::uuid(), 'month' => '2026-10', 'day' => '2026-10-06'];
        $this->actingAs($this->owner)->post(route('coach-calendar.store'), $this->creation)->assertSessionHasNoErrors();
        $this->schedule = CoachSchedule::sole();
    }

    private function data(array $overrides = []): array
    {
        return array_replace($this->creation, ['revision' => 1], $overrides);
    }

    private function context(array $overrides = []): array
    {
        return array_replace(['revision' => 1, 'month' => '2026-10', 'day' => '2026-10-06'], $overrides);
    }

    private function entry(string $from, string $to, array $overrides = []): CoachSchedule
    {
        return app(CurrentGym::class)->runAs($this->gym, function () use ($from, $to, $overrides) {
            $schedule = (new CoachSchedule)->forceFill(array_replace(['coach_id' => $this->coach->id, 'branch_id' => $this->schedule->branch_id, 'title' => 'Other session', 'starts_at' => CarbonImmutable::parse($from, $this->gym->timezone), 'ends_at' => CarbonImmutable::parse($to, $this->gym->timezone)], $overrides));
            $schedule->save();

            return $schedule;
        });
    }

    public function test_edit_reschedule_and_branch_change_are_audited_with_retry_and_stale_protection(): void
    {
        $branch = Branch::factory()->create();
        $data = $this->data(['branch_id' => $branch->id, 'title' => 'Updated session', 'notes' => 'Private changed note', 'starts_at' => '2026-10-07T09:30', 'ends_at' => '2026-10-07T10:30']);
        for ($i = 0; $i < 2; $i++) {
            $this->patch(route('coach-calendar.update', $this->schedule), $data)->assertSessionHasNoErrors()->assertRedirect(route('coach-calendar.index', ['coach_id' => $this->coach->id, 'month' => '2026-10', 'day' => '2026-10-06']));
        }
        $schedule = $this->schedule->refresh();
        $this->assertSame(2, $schedule->revision);
        $this->assertSame($branch->id, $schedule->branch_id);
        $this->assertSame('2026-10-07 01:30:00', $schedule->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame($this->creation['request_key'], $schedule->request_key);
        $audit = AuditLog::where('action', 'coach-schedule.updated')->sole();
        $this->assertSame($this->owner->id, $audit->user_id);
        $this->assertContains('notes', $audit->changes['after']['changed_fields']);
        $this->assertStringNotContainsString('Private changed note', json_encode($audit->changes));
        $this->patch(route('coach-calendar.update', $schedule), [...$data, 'title' => 'Stale edit'])->assertSessionHasErrors('revision');
        $this->assertSame('Updated session', $schedule->refresh()->title);
        $this->patch(route('coach-calendar.update', $schedule), [...$data, 'revision' => 2])->assertSessionHasNoErrors();
        $this->assertSame(2, $schedule->refresh()->revision);
        $this->assertSame(1, AuditLog::where('action', 'coach-schedule.updated')->count());
        $this->post(route('coach-calendar.store'), $this->creation)->assertSessionHasNoErrors();
        $this->assertSame('Updated session', $schedule->refresh()->title);
    }

    public function test_rescheduling_rejects_cross_branch_conflict_and_allows_adjacent_times(): void
    {
        $branch = Branch::factory()->create();
        $this->entry('2026-10-06 10:00', '2026-10-06 11:00', ['branch_id' => $branch->id]);
        $manager = User::factory()->memberOf($this->gym, GymRole::BranchManager)->create();
        $this->actingAs($manager)->patch(route('coach-calendar.update', $this->schedule), $this->data(['starts_at' => '2026-10-06T09:30', 'ends_at' => '2026-10-06T10:30']))->assertSessionHasErrors('starts_at');
        $this->assertSame(1, $this->schedule->refresh()->revision);
        $this->patch(route('coach-calendar.update', $this->schedule), $this->data(['starts_at' => '2026-10-06T11:00', 'ends_at' => '2026-10-06T12:00']))->assertSessionHasNoErrors();
        $this->assertSame('2026-10-06 03:00:00', $this->schedule->refresh()->starts_at->format('Y-m-d H:i:s'));
    }

    public function test_cancellation_is_terminal_double_click_safe_and_frees_time_without_deleting_history(): void
    {
        $before = $this->schedule->only(['title', 'starts_at', 'ends_at']);
        for ($i = 0; $i < 2; $i++) {
            $this->post(route('coach-calendar.cancel', $this->schedule), $this->context())->assertSessionHasNoErrors();
        }
        $schedule = $this->schedule->refresh();
        $this->assertTrue($schedule->is_cancelled);
        $this->assertSame(2, $schedule->revision);
        $this->assertSame($this->owner->id, $schedule->cancelled_by);
        $this->assertNotNull($schedule->cancelled_at);
        $this->assertEquals($before, $schedule->only(['title', 'starts_at', 'ends_at']));
        $this->assertSame(1, AuditLog::where('action', 'coach-schedule.cancelled')->count());
        $this->patch(route('coach-calendar.update', $schedule), $this->data(['revision' => 2, 'is_cancelled' => false]))->assertForbidden();
        $this->post(route('coach-calendar.store'), [...$this->creation, 'request_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('coach_schedules', 2);
        $this->get(route('coach-calendar.index', $this->context(['coach_id' => $this->coach->id])))->assertInertia(fn (Assert $p) => $p->has('schedules.data', 2)->where('schedules.data.0.is_cancelled', true)->where('schedules.data.0.canEdit', false)->where('schedules.data.0.edit', null)->where('days.5.count', 1));
    }

    public function test_stale_cancellation_does_not_cancel_a_newer_edit(): void
    {
        $this->patch(route('coach-calendar.update', $this->schedule), $this->data(['title' => 'Newer edit']))->assertSessionHasNoErrors();
        $this->post(route('coach-calendar.cancel', $this->schedule), $this->context())->assertSessionHasErrors('revision');
        $this->assertFalse($this->schedule->refresh()->is_cancelled);
        $this->post(route('coach-calendar.cancel', $this->schedule), $this->context(['revision' => 2]))->assertSessionHasNoErrors();
    }

    public function test_coach_and_staff_can_manage_permitted_calendar(): void
    {
        $staff = User::factory()->memberOf($this->gym)->create();
        $this->actingAs($this->coach)->patch(route('coach-calendar.update', $this->schedule), $this->data(['title' => 'Coach edit']))->assertSessionHasNoErrors();
        $this->actingAs($staff)->patch(route('coach-calendar.update', $this->schedule), $this->data(['title' => 'Staff edit', 'revision' => 2]))->assertSessionHasNoErrors();
        $this->actingAs($this->coach)->post(route('coach-calendar.cancel', $this->schedule), $this->context(['revision' => 3]))->assertSessionHasNoErrors();
        $this->assertSame($this->coach->id, $this->schedule->refresh()->cancelled_by);
    }

    public function test_other_coach_and_manager_foreign_branch_are_denied(): void
    {
        $other = User::factory()->memberOf($this->gym, GymRole::Coach)->create();
        $this->actingAs($other)->patch(route('coach-calendar.update', $this->schedule), $this->data())->assertNotFound();
        $this->post(route('coach-calendar.cancel', $this->schedule), $this->context())->assertNotFound();
        $branch = app(CurrentGym::class)->runAs($this->gym, fn () => Branch::factory()->create());
        $manager = User::factory()->memberOf($this->gym, GymRole::BranchManager)->create();
        $this->actingAs($manager)->patch(route('coach-calendar.update', $this->schedule), $this->data(['branch_id' => $branch->id]))->assertNotFound();
        $this->gym->users()->updateExistingPivot($this->coach->id, ['branch_id' => $branch->id]);
        $this->post(route('coach-calendar.cancel', $this->schedule), $this->context())->assertNotFound();
    }

    public function test_foreign_target_branch_and_coach_identity_spoofing_are_blocked(): void
    {
        $otherGym = Gym::factory()->create();
        $branch = app(CurrentGym::class)->runAs($otherGym, fn () => $otherGym->mainBranch());
        $this->patch(route('coach-calendar.update', $this->schedule), $this->data(['branch_id' => $branch->id]))->assertNotFound();
        $other = User::factory()->memberOf($this->gym, GymRole::Coach)->create();
        $this->patch(route('coach-calendar.update', $this->schedule), $this->data(['coach_id' => $other->id]))->assertSessionHasErrors('coach_id');
        $this->assertSame($this->coach->id, $this->schedule->refresh()->coach_id);
    }

    public function test_read_only_inactive_coach_and_feature_disabled_block_writes(): void
    {
        $this->gym->branches()->whereKey($this->schedule->branch_id)->update(['is_active' => false]);
        $this->patch(route('coach-calendar.update', $this->schedule), $this->data())->assertForbidden();
        $this->post(route('coach-calendar.cancel', $this->schedule), $this->context())->assertForbidden();
        $this->get(route('coach-calendar.index', ['coach_id' => $this->coach->id, 'month' => '2026-10', 'day' => '2026-10-06']))->assertInertia(fn (Assert $p) => $p->where('schedules.data.0.canEdit', false)->where('schedules.data.0.canCancel', false)->where('schedules.data.0.edit', null));
        $this->gym->branches()->whereKey($this->schedule->branch_id)->update(['is_active' => true]);
        $this->gym->users()->updateExistingPivot($this->coach->id, ['is_active' => false]);
        $this->patch(route('coach-calendar.update', $this->schedule), $this->data())->assertForbidden();
        $this->post(route('coach-calendar.cancel', $this->schedule), $this->context())->assertForbidden();
        Feature::deactivate('coach-calendar');
        $this->patch(route('coach-calendar.update', $this->schedule), $this->data())->assertNotFound();
        $this->post(route('coach-calendar.cancel', $this->schedule), $this->context())->assertNotFound();
    }

    public function test_invalid_fields_cannot_modify_schedule(): void
    {
        $this->patch(route('coach-calendar.update', $this->schedule), $this->data(['ends_at' => '2026-10-06T09:00', 'title' => '', 'day' => ['2026-10-06'], 'revision' => 0]))->assertSessionHasErrors(['title', 'ends_at', 'day', 'revision']);
        $this->post(route('coach-calendar.cancel', $this->schedule), $this->context(['day' => '2026-11-01']))->assertSessionHasErrors('day');
        $this->assertSame(1, $this->schedule->refresh()->revision);
    }

    public function test_update_and_cancellation_roll_back_if_audit_fails(): void
    {
        $dispatcher = AuditLog::getEventDispatcher();
        AuditLog::setEventDispatcher(clone $dispatcher);
        AuditLog::creating(fn () => throw new \RuntimeException('Fixture audit failure'));
        try {
            foreach (['update', 'cancel'] as $operation) {
                try {
                    $this->withoutExceptionHandling();
                    $operation === 'update' ? $this->patch(route('coach-calendar.update', $this->schedule), $this->data(['title' => 'Changed'])) : $this->post(route('coach-calendar.cancel', $this->schedule), $this->context());
                    $this->fail('Expected audit failure.');
                } catch (\RuntimeException $error) {
                    $this->assertSame('Fixture audit failure', $error->getMessage());
                    $this->assertSame(1, $this->schedule->refresh()->revision);
                    $this->assertSame('Original session', $this->schedule->title);
                    $this->assertFalse($this->schedule->is_cancelled);
                }
            }
        } finally {
            AuditLog::setEventDispatcher($dispatcher);
        }
    }

    public function test_edit_props_are_explicit_and_branch_reads_do_not_grow_with_rows(): void
    {
        $branchQueries = [];
        DB::listen(function ($query) use (&$branchQueries) {
            if (str_contains(strtolower($query->sql), 'from "branches"')) {
                $branchQueries[] = $query->sql;
            }
        });
        $url = route('coach-calendar.index', ['coach_id' => $this->coach->id, 'month' => '2026-10', 'day' => '2026-10-06']);
        $this->get($url)->assertInertia(fn (Assert $p) => $p->where('schedules.data.0.edit.starts_at', '2026-10-06T09:00')->where('schedules.data.0.edit.notes', 'Original note')->missing('schedules.data.0.creation_hash')->missing('schedules.data.0.created_by'));
        $one = count($branchQueries);
        for ($i = 0; $i < 9; $i++) {
            $this->entry('2026-10-06 12:00', '2026-10-06 13:00');
        }
        $branchQueries = [];
        $this->get($url)->assertInertia(fn (Assert $p) => $p->has('schedules.data', 10));
        $this->assertSame($one, count($branchQueries));
    }
}
