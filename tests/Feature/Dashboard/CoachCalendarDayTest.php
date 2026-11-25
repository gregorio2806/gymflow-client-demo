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
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class CoachCalendarDayTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private User $owner;

    private User $coach;

    private int $branchId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gym = Gym::factory()->create();
        $this->gym->subscription->update(['plan' => Plan::MultiBranch]);
        $this->owner = User::factory()->memberOf($this->gym, GymRole::Owner)->create();
        $this->coach = User::factory()->memberOf($this->gym, GymRole::Coach)->create();
        app(CurrentGym::class)->set($this->gym);
        $this->branchId = $this->gym->mainBranch()->id;
        $this->actingAs($this->owner);
    }

    private function data(array $overrides = []): array
    {
        return array_replace(['coach_id' => $this->coach->id, 'branch_id' => $this->branchId, 'title' => 'Fictional training session', 'starts_at' => '2026-10-06T09:00', 'ends_at' => '2026-10-06T10:00', 'notes' => 'Bring a towel.', 'request_key' => (string) Str::uuid(), 'month' => '2026-10', 'day' => '2026-10-06'], $overrides);
    }

    private function entry(string $from, string $to, array $overrides = []): CoachSchedule
    {
        return app(CurrentGym::class)->runAs($this->gym, function () use ($from, $to, $overrides) {
            $schedule = (new CoachSchedule)->forceFill(array_replace(['coach_id' => $this->coach->id, 'branch_id' => $this->branchId, 'title' => 'Sample session', 'starts_at' => CarbonImmutable::parse($from, $this->gym->timezone), 'ends_at' => CarbonImmutable::parse($to, $this->gym->timezone)], $overrides));
            $schedule->save();

            return $schedule;
        });
    }

    private function day(array $filters = []): string
    {
        return route('coach-calendar.index', array_replace(['coach_id' => $this->coach->id, 'month' => '2026-10', 'day' => '2026-10-06'], $filters));
    }

    public function test_day_lists_overnight_and_cancelled_entries_in_start_order_with_local_times(): void
    {
        $later = $this->entry('2026-10-06 20:00', '2026-10-07 01:00', ['is_cancelled' => true]);
        $earlier = $this->entry('2026-10-05 23:30', '2026-10-06 00:30');
        $this->entry('2026-10-05 23:00', '2026-10-06 00:00');
        $this->entry('2026-10-07 00:00', '2026-10-07 01:00');
        $this->get($this->day())->assertInertia(fn (Assert $p) => $p->has('schedules.data', 2)->where('schedules.data.0.id', $earlier->id)->where('schedules.data.0.startsAt', 'Oct 5, 2026 11:30 PM')->where('schedules.data.0.endsAt', 'Oct 6, 2026 12:30 AM')->where('schedules.data.0.startTime', '11:30 PM')->where('schedules.data.0.endTime', '12:30 AM')->where('schedules.data.0.dateRange', 'October 5, 2026 - October 6, 2026')->where('schedules.data.0.branch', $this->gym->mainBranch()->name)->where('schedules.data.1.id', $later->id)->where('schedules.data.1.is_cancelled', true)->where('days.5.count', 1)->missing('schedules.data.0.notes')->missing('schedules.data.0.request_key')->where('timezone', 'Asia/Manila'));
        $this->get($this->day(['day' => '2026-10-07']))->assertInertia(fn (Assert $p) => $p->has('schedules.data', 2));
    }

    public function test_day_pagination_keeps_coach_month_day_and_has_deterministic_ties(): void
    {
        $ids = [];
        for ($i = 0; $i < 11; $i++) {
            $ids[] = $this->entry('2026-10-06 09:00', '2026-10-06 10:00')->id;
        }
        $this->get($this->day())->assertInertia(fn (Assert $p) => $p->has('schedules.data', 10)->where('schedules.total', 11)->where('schedules.data.0.id', $ids[0])->where('schedules.next_page_url', fn ($url) => str_contains($url, 'coach_id='.$this->coach->id) && str_contains($url, 'month=2026-10') && str_contains($url, 'day=2026-10-06') && str_contains($url, 'schedules_page=2')));
        $this->get($this->day(['schedules_page' => 2]))->assertInertia(fn (Assert $p) => $p->has('schedules.data', 1)->where('schedules.data.0.id', $ids[10]));
    }

    public function test_empty_day_and_read_only_branch_keep_view_without_add_action(): void
    {
        $this->get($this->day())->assertInertia(fn (Assert $p) => $p->has('schedules.data', 0)->where('canAdd', true));
        $this->entry('2026-10-06 09:00', '2026-10-06 10:00');
        $this->gym->branches()->update(['is_active' => false]);
        $this->get($this->day())->assertInertia(fn (Assert $p) => $p->has('schedules.data', 1)->where('canAdd', false));
    }

    public function test_creation_is_audited_utc_normalized_double_click_safe_and_preserves_filters(): void
    {
        $data = $this->data(['gym_id' => 999, 'created_by' => 999, 'is_cancelled' => true]);
        for ($i = 0; $i < 2; $i++) {
            $this->post(route('coach-calendar.store'), [...$data, 'request_key' => $i === 1 ? strtoupper($data['request_key']) : $data['request_key']])->assertSessionHasNoErrors()->assertRedirect($this->day());
        }
        $schedule = CoachSchedule::sole();
        $this->assertSame('2026-10-06 01:00:00', $schedule->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame($this->gym->id, $schedule->gym_id);
        $this->assertSame($this->owner->id, $schedule->created_by);
        $this->assertFalse($schedule->is_cancelled);
        $this->assertSame(1, AuditLog::where('action', 'coach-schedule.created')->count());
        $this->assertStringNotContainsString('Bring a towel', json_encode(AuditLog::where('action', 'coach-schedule.created')->sole()->changes));
        $this->post(route('coach-calendar.store'), [...$data, 'title' => 'Changed'])->assertSessionHasErrors('request_key');
        $this->assertDatabaseCount('coach_schedules', 1);
    }

    public function test_overlap_is_blocked_across_branches_but_adjacency_and_cancelled_periods_are_allowed(): void
    {
        $otherBranch = Branch::factory()->create();
        $this->entry('2026-10-06 09:00', '2026-10-06 10:00', ['branch_id' => $otherBranch->id]);
        $manager = User::factory()->memberOf($this->gym, GymRole::BranchManager)->create();
        $this->actingAs($manager)->post(route('coach-calendar.store'), $this->data())->assertSessionHasErrors('starts_at');
        $this->post(route('coach-calendar.store'), $this->data(['starts_at' => '2026-10-06T10:00', 'ends_at' => '2026-10-06T11:00']))->assertSessionHasNoErrors();
        $this->entry('2026-10-06 11:00', '2026-10-06 12:00', ['is_cancelled' => true]);
        $this->post(route('coach-calendar.store'), $this->data(['starts_at' => '2026-10-06T11:00', 'ends_at' => '2026-10-06T12:00']))->assertSessionHasNoErrors();
    }

    public function test_coach_creates_own_entries_but_cannot_choose_another_coach(): void
    {
        $other = User::factory()->memberOf($this->gym, GymRole::Coach)->create();
        $this->actingAs($this->coach)->post(route('coach-calendar.store'), $this->data(['coach_id' => $other->id]))->assertNotFound();
        $this->post(route('coach-calendar.store'), $this->data())->assertSessionHasNoErrors();
    }

    public function test_manager_day_list_excludes_other_branch_and_reassigned_coaches(): void
    {
        $otherBranch = Branch::factory()->create();
        $local = $this->entry('2026-10-06 09:00', '2026-10-06 10:00');
        $this->entry('2026-10-06 10:00', '2026-10-06 11:00', ['branch_id' => $otherBranch->id]);
        $manager = User::factory()->memberOf($this->gym, GymRole::BranchManager)->create();
        $this->actingAs($manager)->get($this->day())->assertInertia(fn (Assert $p) => $p->has('schedules.data', 1)->where('schedules.data.0.id', $local->id));
        $this->post(route('coach-calendar.store'), $this->data(['branch_id' => $otherBranch->id]))->assertNotFound();
        $this->gym->users()->updateExistingPivot($this->coach->id, ['branch_id' => $otherBranch->id]);
        $this->get($this->day())->assertNotFound();
    }

    public function test_inactive_coach_read_only_branch_and_disabled_feature_block_creation(): void
    {
        $this->gym->users()->updateExistingPivot($this->coach->id, ['is_active' => false]);
        $this->post(route('coach-calendar.store'), $this->data())->assertNotFound();
        $this->gym->users()->updateExistingPivot($this->coach->id, ['is_active' => true]);
        $this->gym->branches()->update(['is_active' => false]);
        $this->post(route('coach-calendar.store'), $this->data())->assertForbidden();
        Feature::deactivate('coach-calendar');
        $this->post(route('coach-calendar.store'), $this->data())->assertNotFound();
        $this->assertDatabaseCount('coach_schedules', 0);
    }

    public function test_rejects_invalid_fields_and_foreign_branch_without_writing(): void
    {
        $other = Gym::factory()->create();
        $branchId = app(CurrentGym::class)->runAs($other, fn () => $other->mainBranch()->id);
        $this->post(route('coach-calendar.store'), $this->data(['branch_id' => $branchId]))->assertNotFound();
        $this->post(route('coach-calendar.store'), $this->data(['ends_at' => '2026-10-06T09:00', 'title' => '', 'notes' => str_repeat('a', 1001), 'day' => '2026-11-06']))->assertSessionHasErrors(['ends_at', 'title', 'notes', 'day']);
        $this->post(route('coach-calendar.store'), $this->data(['day' => ['2026-10-06']]))->assertSessionHasErrors('day');
        $this->assertDatabaseCount('coach_schedules', 0);
    }

    public function test_audit_failure_rolls_back_creation(): void
    {
        $dispatcher = AuditLog::getEventDispatcher();
        AuditLog::setEventDispatcher(clone $dispatcher);
        AuditLog::creating(fn () => throw new \RuntimeException('Fixture audit failure'));
        try {
            $this->withoutExceptionHandling()->post(route('coach-calendar.store'), $this->data());
            $this->fail('Expected audit failure.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Fixture audit failure', $error->getMessage());
        } finally {
            AuditLog::setEventDispatcher($dispatcher);
        }
        $this->assertDatabaseCount('coach_schedules', 0);
    }
}
