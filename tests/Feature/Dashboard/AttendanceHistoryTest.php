<?php

namespace Tests\Feature\Dashboard;

use App\Enums\GymRole;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\Member;
use App\Models\User;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature;
use LogicException;
use Tests\TestCase;

class AttendanceHistoryTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-05 16:00:00', 'UTC'));
        $this->gym = Gym::factory()->create();
        app(CurrentGym::class)->set($this->gym);
        $this->member = Member::factory()->create(['name' => 'Alex History']);
        $this->actingAs(User::factory()->memberOf($this->gym, GymRole::Owner)->create());
    }

    private function record(string $localTime, ?Member $member = null, ?Branch $branch = null): AttendanceRecord
    {
        $record = new AttendanceRecord;
        $record->forceFill(['member_id' => ($member ?? $this->member)->id, 'branch_id' => ($branch ?? $this->gym->mainBranch())->id, 'checked_in_at' => CarbonImmutable::parse($localTime, $this->gym->timezone)->utc(), 'source' => 'manual'])->save();

        return $record;
    }

    public function test_default_daily_view_uses_manila_midnight_and_exclusive_end(): void
    {
        $this->record('2026-10-05 23:59:59');
        $start = $this->record('2026-10-06 00:00:00');
        $this->record('2026-10-06 23:59:59');
        $this->record('2026-10-07 00:00:00');
        $this->get(route('attendance.index'))->assertInertia(fn (Assert $page) => $page
            ->where('filters.date', '2026-10-06')->where('filters.view', 'day')
            ->where('summary', ['checkIns' => 2, 'uniqueMembers' => 1])
            ->has('attendance.data', 2)->where('attendance.data.1.id', $start->id)
            ->where('attendance.data.1.checkedInAt', 'Oct 6, 2026 12:00 AM')
            ->missing('attendance.data.0.member_id')->missing('attendance.data.0.recorded_by')->missing('attendance.data.0.member'));
    }

    public function test_history_exposes_creation_metadata_without_account_details(): void
    {
        $record = new AttendanceRecord;
        $record->forceFill(['member_id' => $this->member->id, 'branch_id' => $this->gym->mainBranch()->id,
            'checked_in_at' => now()->utc(), 'source' => 'manual', 'recorded_by' => auth()->id()])->save();
        $this->get(route('attendance.index'))->assertInertia(fn (Assert $page) => $page
            ->where('attendance.data.0.createdBy', auth()->user()->name)
            ->where('attendance.data.0.createdAt', 'Oct 6, 2026 12:00 AM')
            ->missing('attendance.data.0.recorder')->missing('attendance.data.0.recorded_by'));
    }

    public function test_weekly_view_uses_monday_through_sunday_across_years(): void
    {
        $this->record('2026-12-27 23:59:59');
        $this->record('2026-12-28 00:00:00');
        $this->record('2027-01-03 23:59:59', Member::factory()->create());
        $this->record('2027-01-04 00:00:00');
        $this->get(route('attendance.index', ['view' => 'week', 'date' => '2027-01-01']))->assertInertia(fn (Assert $page) => $page
            ->where('period', ['from' => 'Dec 28, 2026', 'through' => 'Jan 3, 2027'])
            ->where('summary', ['checkIns' => 2, 'uniqueMembers' => 2]));
    }

    public function test_monthly_view_handles_leap_day_and_excludes_next_month(): void
    {
        $this->record('2028-01-31 23:59:59');
        $this->record('2028-02-01 00:00:00');
        $this->record('2028-02-29 23:59:59');
        $this->record('2028-03-01 00:00:00');
        $this->get(route('attendance.index', ['view' => 'month', 'date' => '2028-02-29']))->assertInertia(fn (Assert $page) => $page
            ->where('period', ['from' => 'Feb 1, 2028', 'through' => 'Feb 29, 2028'])
            ->where('summary', ['checkIns' => 2, 'uniqueMembers' => 1]));
    }

    public function test_history_is_paginated_and_uses_bounded_aggregate_and_eager_queries(): void
    {
        foreach (range(1, 26) as $number) {
            $this->record('2026-10-06 12:00:00');
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('attendance.index', ['view' => 'month', 'date' => '2026-10-06', 'per_page' => 25]))->assertInertia(fn (Assert $page) => $page
            ->has('attendance.data', 25)->where('attendance.total', 26)->where('summary.checkIns', 26)
            ->where('attendance.next_page_url', fn ($url) => str_contains($url, 'view=month') && str_contains($url, 'date=2026-10-06')));
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertCount(4, $queries->filter(fn ($query) => str_contains($query['query'], 'attendance_records')));
        $this->assertCount(1, $queries->filter(fn ($query) => str_contains($query['query'], 'from "members"')));
        $this->get(route('attendance.index', ['view' => 'month', 'date' => '2026-10-06', 'per_page' => 25, 'page' => 2]))->assertInertia(fn (Assert $page) => $page->has('attendance.data', 1));
    }

    public function test_search_source_and_summary_use_the_same_filtered_records(): void
    {
        $this->member->update(['mobile' => '09123456789']);
        $this->record('2026-10-06 17:10:00');
        $this->record('2026-10-06 17:40:00');
        $this->record('2026-10-06 10:00:00', Member::factory()->create(['name' => 'Other Person']));
        $this->get(route('attendance.index', ['search' => '091234', 'source' => 'manual']))->assertInertia(fn (Assert $page) => $page
            ->where('summary.checkIns', 2)->where('insights.dailyAverage', 2)->where('insights.peakTime', '5 PM – 6 PM')->has('attendance.data', 2));
        $this->get(route('attendance.index', ['search' => 'Alex', 'source' => 'qr']))->assertInertia(fn (Assert $page) => $page
            ->where('summary.checkIns', 0)->where('insights.peakTime', null)->has('attendance.data', 0));
        $this->getJson(route('attendance.index', ['per_page' => 1000]))->assertUnprocessable();
    }

    public function test_csv_export_is_feature_gated_filtered_tenant_scoped_and_formula_safe(): void
    {
        Feature::for($this->gym)->activate('full-reports');
        $this->member->update(['name' => '=Dangerous', 'mobile' => '09123456789']);
        $this->record('2026-10-06 12:00:00');
        $this->record('2026-10-06 13:00:00', Member::factory()->create(['name' => 'Unmatched']));
        $foreign = Gym::factory()->create();
        app(CurrentGym::class)->runAs($foreign, function () {
            $this->record('2026-10-06 12:00:00', Member::factory()->create(['name' => 'Foreign Person']), app(CurrentGym::class)->get()->mainBranch());
        });
        $response = $this->get(route('attendance.index', ['export' => 'csv', 'search' => 'Dangerous']));
        $response->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=Dangerous", $csv);
        $this->assertStringNotContainsString('Unmatched', $csv);
        $this->assertStringNotContainsString('Foreign Person', $csv);
        Feature::for($this->gym)->deactivate('full-reports');
        $this->get(route('attendance.index', ['export' => 'csv']))->assertNotFound();
    }

    public function test_branch_filter_uses_check_in_branch_and_managers_keep_history_after_member_transfer(): void
    {
        $this->gym->subscription->update(['plan' => 'multi_branch']);
        $main = $this->gym->mainBranch();
        $other = Branch::factory()->create(['name' => 'Other branch']);
        $this->record('2026-10-06 08:00:00');
        $this->record('2026-10-06 09:00:00', null, $other);
        $this->member->update(['home_branch_id' => $other->id]);
        $this->get(route('attendance.index', ['branch_id' => $main->id]))->assertInertia(fn (Assert $page) => $page->where('summary.checkIns', 1)->where('attendance.data.0.memberName', 'Alex History'));
        $manager = User::factory()->memberOf($this->gym, GymRole::BranchManager)->create();
        $this->gym->users()->updateExistingPivot($manager->id, ['branch_id' => $main->id]);
        $this->actingAs($manager)->get(route('attendance.index'))->assertInertia(fn (Assert $page) => $page->where('filters.branch_id', $main->id)->where('summary.checkIns', 1)->has('branches', 1)->where('attendance.data.0.memberName', 'Alex History'));
        $this->get(route('attendance.index', ['branch_id' => $other->id]))->assertNotFound();
    }

    public function test_staff_can_read_history_but_coach_and_disabled_module_cannot(): void
    {
        $staff = User::factory()->memberOf($this->gym)->create();
        $this->actingAs($staff)->get(route('attendance.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('summary', ['checkIns' => 0, 'uniqueMembers' => 0]));
        $coach = User::factory()->memberOf($this->gym, GymRole::Coach)->create();
        $this->actingAs($coach)->get(route('attendance.index'))->assertForbidden();
        Feature::for($this->gym)->deactivate('qr-attendance');
        $this->actingAs($staff)->get(route('attendance.index'))->assertNotFound();
    }

    public function test_invalid_dates_views_and_foreign_branches_are_rejected(): void
    {
        foreach ([['date' => '2026-02-30'], ['view' => 'year'], ['date' => '1900-01-01']] as $filters) {
            $this->getJson(route('attendance.index', $filters))->assertUnprocessable();
        }
        $foreign = Gym::factory()->create();
        $this->get(route('attendance.index', ['branch_id' => $foreign->mainBranch()->id]))->assertNotFound();
    }

    public function test_attendance_model_fails_closed_and_rejects_foreign_relations_and_edits(): void
    {
        $record = $this->record('2026-10-06 12:00:00');
        $foreign = Gym::factory()->create();
        app(CurrentGym::class)->set($foreign);
        $this->assertNull(AttendanceRecord::find($record->id));
        $this->assertSame(0, AttendanceRecord::whereKey($record->id)->update(['source' => 'qr']));
        try {
            $this->record('2026-10-06 12:00:00');
            $this->fail('Foreign member and branch must be rejected.');
        } catch (LogicException) {
            $this->assertDatabaseCount('attendance_records', 1);
        }
        app(CurrentGym::class)->forget();
        $this->assertSame(0, AttendanceRecord::count());
        app(CurrentGym::class)->set($this->gym);
        $this->expectException(LogicException::class);
        $record->forceFill(['source' => 'qr'])->save();
    }

    public function test_gym_deletion_removes_attendance_before_branch_cascades_and_preserves_other_gyms(): void
    {
        $record = $this->record('2026-10-06 12:00:00');
        $foreign = Gym::factory()->create();
        app(CurrentGym::class)->set($foreign);
        $other = $this->record('2026-10-06 12:00:00', Member::factory()->create(), $foreign->mainBranch());
        $this->gym->delete();
        $this->assertDatabaseMissing('attendance_records', ['id' => $record->id]);
        $this->assertModelExists($other);
        $this->assertSame($foreign->id, app(CurrentGym::class)->id());
    }
}
