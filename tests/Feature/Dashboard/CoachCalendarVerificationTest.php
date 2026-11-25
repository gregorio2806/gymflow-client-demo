<?php

namespace Tests\Feature\Dashboard;

use App\Enums\GymRole;
use App\Enums\Plan;
use App\Http\Middleware\HandleInertiaRequests;
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
use Tests\TestCase;

class CoachCalendarVerificationTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private User $owner;

    private User $coach;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gym = Gym::factory()->create();
        $this->gym->subscription->update(['plan' => Plan::MultiBranch]);
        $this->owner = User::factory()->memberOf($this->gym, GymRole::Owner)->create();
        $this->coach = User::factory()->memberOf($this->gym, GymRole::Coach)->create();
        app(CurrentGym::class)->set($this->gym);
        $this->actingAs($this->owner);
    }

    private function schedule(Gym $gym, string $from = '2026-10-06 09:00', string $through = '2026-10-06 10:00'): CoachSchedule
    {
        return app(CurrentGym::class)->runAs($gym, function () use ($gym, $from, $through) {
            $schedule = (new CoachSchedule)->forceFill(['coach_id' => $this->coach->id, 'branch_id' => $gym->mainBranch()->id, 'title' => 'Fictional verification session', 'starts_at' => CarbonImmutable::parse($from, $gym->timezone), 'ends_at' => CarbonImmutable::parse($through, $gym->timezone)]);
            $schedule->save();

            return $schedule;
        });
    }

    private function filters(): array
    {
        return ['coach_id' => $this->coach->id, 'month' => '2026-10', 'day' => '2026-10-06'];
    }

    private function data(CoachSchedule $schedule): array
    {
        return [...$this->filters(), 'branch_id' => $schedule->branch_id, 'title' => 'Updated verification session', 'starts_at' => '2026-10-06T09:00', 'ends_at' => '2026-10-06T10:00', 'revision' => $schedule->revision];
    }

    public function test_same_coach_in_two_gyms_does_not_mix_month_counts_or_day_entries(): void
    {
        $other = Gym::factory()->create();
        $other->users()->attach($this->coach, ['role' => GymRole::Coach]);
        $foreign = $this->schedule($other);
        $local = $this->schedule($this->gym);
        $this->get(route('coach-calendar.index', $this->filters()))->assertInertia(fn (Assert $p) => $p->has('schedules.data', 1)->where('schedules.data.0.id', $local->id)->where('days.5.count', 1));
        $this->patch(route('coach-calendar.update', $foreign), $this->data($foreign))->assertNotFound();
        $this->post(route('coach-calendar.cancel', $foreign), $this->filters() + ['revision' => 1])->assertNotFound();
        $this->actingAs($this->coach)->withSession(['current_gym_id' => $other->id])->get(route('coach-calendar.index', $this->filters()))->assertInertia(fn (Assert $p) => $p->has('schedules.data', 1)->where('schedules.data.0.id', $foreign->id)->where('days.5.count', 1));
    }

    public function test_month_and_day_queries_remain_bounded_with_many_outside_entries(): void
    {
        $this->schedule($this->gym);
        for ($i = 0; $i < 110; $i++) {
            $this->schedule($this->gym, '2026-09-01 09:00', '2026-09-01 10:00');
        }
        $version = app(HandleInertiaRequests::class)->version(request());
        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version])->get(route('coach-calendar.index', $this->filters()))->assertOk();
        $queries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains(strtolower($query['query']), 'from "coach_schedules"'))->values();
        DB::disableQueryLog();
        $this->assertCount(3, $queries); // day count + paginated rows + one month aggregate
        $this->assertSame(1, $queries->filter(fn ($query) => str_contains(strtolower($query['query']), 'sum(case'))->count());
        $this->assertCount(31, $response->json('props.days'));
        $this->assertCount(1, $response->json('props.schedules.data'));
        $this->assertLessThan(200 * 1024, strlen($response->getContent()));
    }

    public function test_manager_reassignment_after_opening_calendar_blocks_old_schedule_writes(): void
    {
        $schedule = $this->schedule($this->gym);
        $branch = Branch::factory()->create();
        $manager = User::factory()->memberOf($this->gym, GymRole::BranchManager)->create();
        $this->actingAs($manager)->get(route('coach-calendar.index', $this->filters()))->assertInertia(fn (Assert $p) => $p->where('schedules.data.0.canEdit', true));
        $this->gym->users()->updateExistingPivot($manager->id, ['branch_id' => $branch->id]);
        $this->patch(route('coach-calendar.update', $schedule), $this->data($schedule))->assertNotFound();
        $this->post(route('coach-calendar.cancel', $schedule), $this->filters() + ['revision' => 1])->assertNotFound();
        $this->assertDatabaseHas('coach_schedules', ['id' => $schedule->id, 'revision' => 1, 'is_cancelled' => false]);
    }

    public function test_read_only_target_branch_rejects_a_move_from_writable_branch(): void
    {
        $schedule = $this->schedule($this->gym);
        $branch = Branch::factory()->create();
        $branch->forceFill(['is_active' => false])->save();
        $this->patch(route('coach-calendar.update', $schedule), [...$this->data($schedule), 'branch_id' => $branch->id])->assertForbidden();
        $this->assertDatabaseHas('coach_schedules', ['id' => $schedule->id, 'branch_id' => $schedule->branch_id, 'revision' => 1]);
        $this->assertSame(0, AuditLog::where('action', 'coach-schedule.updated')->count());
    }

    public function test_client_cannot_change_creation_or_cancellation_metadata_during_edit(): void
    {
        $schedule = app(CurrentGym::class)->runAs($this->gym, function () {
            $row = (new CoachSchedule)->forceFill(['coach_id' => $this->coach->id, 'branch_id' => $this->gym->mainBranch()->id, 'title' => 'Metadata fixture', 'starts_at' => CarbonImmutable::parse('2026-10-07 09:00', $this->gym->timezone), 'ends_at' => CarbonImmutable::parse('2026-10-07 10:00', $this->gym->timezone), 'request_key' => (string) Str::uuid(), 'creation_hash' => str_repeat('a', 64), 'created_by' => $this->owner->id]);
            $row->save();

            return $row;
        });
        $original = $schedule->only(['gym_id', 'coach_id', 'created_by', 'request_key', 'creation_hash']);
        $this->patch(route('coach-calendar.update', $schedule), [...$this->data($schedule), 'starts_at' => '2026-10-07T09:00', 'ends_at' => '2026-10-07T10:00', 'gym_id' => 999, 'created_by' => 999, 'request_key' => (string) Str::uuid(), 'creation_hash' => str_repeat('b', 64), 'is_cancelled' => true, 'cancelled_at' => '2000-01-01', 'cancelled_by' => 999])->assertSessionHasNoErrors();
        $this->assertEquals($original, $schedule->refresh()->only(array_keys($original)));
        $this->assertFalse($schedule->is_cancelled);
        $this->assertNull($schedule->cancelled_at);
        $this->assertNull($schedule->cancelled_by);
    }
}
