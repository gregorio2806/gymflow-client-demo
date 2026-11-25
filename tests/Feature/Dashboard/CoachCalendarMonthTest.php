<?php

namespace Tests\Feature\Dashboard;

use App\Enums\GymRole;
use App\Models\CoachSchedule;
use App\Models\Gym;
use App\Models\User;
use App\Support\CoachCalendarMonth;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CoachCalendarMonthTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private User $owner;

    private User $coach;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gym = Gym::factory()->create();
        $this->owner = User::factory()->memberOf($this->gym, GymRole::Owner)->create();
        $this->coach = User::factory()->memberOf($this->gym, GymRole::Coach)->create();
        app(CurrentGym::class)->set($this->gym);
        $this->actingAs($this->owner);
    }

    private function entry(string $from, string $to, bool $cancelled = false, ?User $coach = null): void
    {
        (new CoachSchedule)->forceFill(['coach_id' => ($coach ?? $this->coach)->id, 'branch_id' => $this->gym->branches()->first()->id, 'title' => 'Private session title', 'notes' => 'Private note', 'starts_at' => CarbonImmutable::parse($from, $this->gym->timezone), 'ends_at' => CarbonImmutable::parse($to, $this->gym->timezone), 'is_cancelled' => $cancelled])->save();
    }

    public function test_defaults_to_gym_local_month_and_today_across_utc_boundary(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-30 16:15:00', 'UTC'));
        $this->get(route('coach-calendar.index'))->assertInertia(fn (Assert $p) => $p->where('month.value', '2026-10')->where('today', '2026-10-01')->where('selectedDay', '2026-10-01')->where('days.0.today', true)->where('days.0.selected', true)->has('days', 31));
    }

    public function test_counts_overnight_month_edges_and_excludes_cancelled_and_other_coaches(): void
    {
        $this->entry('2026-09-30 23:00', '2026-10-01 01:00');
        $this->entry('2026-10-01 23:00', '2026-10-02 00:00');
        $this->entry('2026-10-31 23:30', '2026-11-01 00:30');
        $this->entry('2026-10-01 10:00', '2026-10-01 11:00', true);
        $other = User::factory()->memberOf($this->gym, GymRole::Coach)->create();
        $this->entry('2026-10-01 10:00', '2026-10-01 11:00', false, $other);
        $this->get(route('coach-calendar.index', ['month' => '2026-10', 'coach_id' => $this->coach->id, 'day' => '2026-10-31']))->assertInertia(fn (Assert $p) => $p->where('days.0.count', 2)->where('days.1.count', 0)->where('days.30.count', 1)->where('days.30.selected', true)->where('month.offset', 4)->where('month.previous', '2026-09')->where('month.next', '2026-11')->missing('days.0.notes')->missing('days.0.title'));
    }

    public function test_leap_month_and_december_navigation(): void
    {
        $this->get(route('coach-calendar.index', ['month' => '2028-02']))->assertInertia(fn (Assert $p) => $p->has('days', 29)->where('selectedDay', '2028-02-01'));
        $this->get(route('coach-calendar.index', ['month' => '2026-12']))->assertInertia(fn (Assert $p) => $p->where('month.next', '2027-01'));
    }

    public function test_rejects_invalid_month_and_day_outside_month(): void
    {
        foreach (['2026-13', '2026-2', '0000-01', '9999-01'] as $month) {
            $this->get(route('coach-calendar.index', ['month' => $month]))->assertSessionHasErrors('month');
        }
        $this->get(route('coach-calendar.index', ['month' => '2026-10', 'day' => '2026-11-01']))->assertSessionHasErrors('day');
        $this->get(route('coach-calendar.index', ['month' => '2026-02', 'day' => '2026-02-30']))->assertSessionHasErrors('day');
    }

    public function test_foreign_inactive_and_other_coach_selections_are_not_found(): void
    {
        $other = Gym::factory()->create();
        $foreign = User::factory()->memberOf($other, GymRole::Coach)->create();
        $this->get(route('coach-calendar.index', ['coach_id' => $foreign->id]))->assertNotFound();
        $this->gym->users()->updateExistingPivot($this->coach->id, ['is_active' => false]);
        $this->get(route('coach-calendar.index', ['coach_id' => $this->coach->id]))->assertNotFound();
        $this->gym->users()->updateExistingPivot($this->coach->id, ['is_active' => true]);
        $second = User::factory()->memberOf($this->gym, GymRole::Coach)->create();
        $this->actingAs($this->coach)->get(route('coach-calendar.index', ['coach_id' => $second->id]))->assertNotFound();
        $this->get(route('coach-calendar.index'))->assertInertia(fn (Assert $p) => $p->where('selectedCoach.id', $this->coach->id)->where('canSelectCoach', false));
    }

    public function test_counts_use_one_bounded_aggregate_query(): void
    {
        $this->entry('2026-10-01 00:00', '2026-11-01 00:00');
        DB::enableQueryLog();
        DB::flushQueryLog();
        $days = (new CoachCalendarMonth)->build($this->coach->id, CarbonImmutable::parse('2026-10-01', $this->gym->timezone), '2026-10-01', '2026-10-01');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('sum(case', strtolower($queries[0]['query']));
        $bindings = array_map(fn ($value) => $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value, $queries[0]['bindings']);
        $this->assertContains('2026-09-30 16:00:00', $bindings);
        $this->assertContains('2026-10-31 16:00:00', $bindings);
        $this->assertCount(31, $days);
        $this->assertSame(31, array_sum(array_column($days, 'count')));
    }

    public function test_selected_coach_survives_roster_pagination_and_empty_state_is_explicit(): void
    {
        User::factory()->count(26)->memberOf($this->gym, GymRole::Coach)->create();
        $this->get(route('coach-calendar.index', ['month' => '2026-10', 'day' => '2026-10-20', 'coach_id' => $this->coach->id, 'page' => 2]))->assertInertia(fn (Assert $p) => $p->where('selectedCoach.id', $this->coach->id)->where('selectedDay', '2026-10-20')->where('coaches.prev_page_url', fn ($url) => str_contains($url, 'coach_id='.$this->coach->id) && str_contains($url, 'month=2026-10')));
        $empty = Gym::factory()->create();
        $owner = User::factory()->memberOf($empty, GymRole::Owner)->create();
        $this->actingAs($owner)->withSession(['current_gym_id' => $empty->id])->get(route('coach-calendar.index'))->assertInertia(fn (Assert $p) => $p->where('selectedCoach', null)->has('days', 0));
    }
}
