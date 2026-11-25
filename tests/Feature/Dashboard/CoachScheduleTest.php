<?php

namespace Tests\Feature\Dashboard;

use App\Enums\GymRole;
use App\Models\CoachSchedule;
use App\Models\Gym;
use App\Models\User;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class CoachScheduleTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private User $coach;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gym = Gym::factory()->create();
        $this->coach = User::factory()->memberOf($this->gym, GymRole::Coach)->create();
        app(CurrentGym::class)->set($this->gym);
    }

    private function schedule(array $overrides = []): CoachSchedule
    {
        $schedule = new CoachSchedule;
        $schedule->forceFill(array_replace([
            'coach_id' => $this->coach->id,
            'branch_id' => $this->gym->branches()->first()->id,
            'title' => ' Evening session ',
            'starts_at' => CarbonImmutable::parse('2026-10-06 23:30', $this->gym->timezone),
            'ends_at' => CarbonImmutable::parse('2026-10-07 00:30', $this->gym->timezone),
            'notes' => 'Bring a towel.',
        ], $overrides))->save();

        return $schedule->refresh();
    }

    public function test_stores_utc_and_finds_overnight_entries_on_both_local_days(): void
    {
        $schedule = $this->schedule();
        $this->assertSame('2026-10-06 15:30:00', $schedule->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-06 16:30:00', $schedule->ends_at->format('Y-m-d H:i:s'));
        $this->assertSame('Evening session', $schedule->title);
        $this->assertSame($this->coach->id, $schedule->coach->id);
        foreach (['2026-10-06', '2026-10-07'] as $day) {
            $start = CarbonImmutable::parse($day, $this->gym->timezone)->utc();
            $this->assertSame(1, CoachSchedule::overlapping($start, $start->addDay())->count());
        }
        $this->assertSame(0, CoachSchedule::overlapping($schedule->ends_at, $schedule->ends_at->addHour())->count());
        $schedule->forceFill(['is_cancelled' => true])->save();
        $this->assertTrue($schedule->refresh()->is_cancelled);
    }

    public function test_tenant_scope_fails_closed_and_gym_cannot_be_changed(): void
    {
        $other = Gym::factory()->create();
        $schedule = $this->schedule(['gym_id' => $other->id]);
        $this->assertSame($this->gym->id, $schedule->gym_id);
        app(CurrentGym::class)->set($other);
        $this->assertSame(0, CoachSchedule::count());
        app(CurrentGym::class)->forget();
        $this->assertSame(0, CoachSchedule::count());
        $this->expectException(LogicException::class);
        $schedule->forceFill(['gym_id' => $other->id])->save();
    }

    public function test_rejects_foreign_branch(): void
    {
        $other = Gym::factory()->create();
        $branchId = app(CurrentGym::class)->runAs($other, fn () => $other->branches()->first()->id);
        $this->expectException(LogicException::class);
        $this->schedule(['branch_id' => $branchId]);
    }

    public function test_rejects_foreign_coach(): void
    {
        $other = Gym::factory()->create();
        $coach = User::factory()->memberOf($other, GymRole::Coach)->create();
        $this->expectException(LogicException::class);
        $this->schedule(['coach_id' => $coach->id]);
    }

    public function test_rejects_staff_as_coach(): void
    {
        $staff = User::factory()->memberOf($this->gym)->create();
        $this->expectException(LogicException::class);
        $this->schedule(['coach_id' => $staff->id]);
    }

    public function test_inactive_coach_cannot_receive_new_entries_but_history_is_preserved(): void
    {
        $schedule = $this->schedule();
        $this->gym->users()->updateExistingPivot($this->coach->id, ['is_active' => false]);
        $schedule->forceFill(['is_cancelled' => true])->save();
        $this->assertTrue($schedule->refresh()->is_cancelled);
        $this->expectException(LogicException::class);
        $this->schedule();
    }

    public function test_rejects_zero_length_period(): void
    {
        $this->expectException(LogicException::class);
        $this->schedule(['ends_at' => CarbonImmutable::parse('2026-10-06 23:30', $this->gym->timezone)]);
    }

    public function test_rejects_blank_title(): void
    {
        $this->expectException(LogicException::class);
        $this->schedule(['title' => '   ']);
    }

    public function test_rejects_unbounded_notes(): void
    {
        $this->expectException(LogicException::class);
        $this->schedule(['notes' => str_repeat('a', 1001)]);
    }
}
