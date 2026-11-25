<?php

namespace Tests\Feature\Dashboard;

use App\Enums\GymRole;
use App\Enums\Plan;
use App\Jobs\GenerateReportExport;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberPurchase;
use App\Models\MembershipPlan;
use App\Models\MemberSubscription;
use App\Models\Payment;
use App\Models\ReportExport;
use App\Models\User;
use App\Support\GymReport;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gym = Gym::factory()->create();
        $this->gym->subscription->update(['plan' => Plan::MultiBranch]);
        $this->owner = User::factory()->memberOf($this->gym, GymRole::Owner)->create();
        app(CurrentGym::class)->set($this->gym);
        $this->actingAs($this->owner);
        Queue::fake();
    }

    private function filters(): array
    {
        return ['from' => '2026-10-06', 'through' => '2026-10-07'];
    }

    private function records(?Branch $branch = null): void
    {
        $member = Member::factory()->create(['home_branch_id' => $branch?->id ?? $this->gym->mainBranch()->id]);
        $plan = MembershipPlan::factory()->create();
        $period = (new MemberSubscription)->forceFill(['member_id' => $member->id, 'membership_plan_id' => $plan->id, 'plan_name' => 'Fictional access', 'duration_amount' => 30, 'duration_unit' => 'days', 'price_centavos' => 120000, 'starts_at' => '2026-10-06 00:00:00', 'ends_at' => '2026-11-06 00:00:00', 'status' => 'active', 'operation' => 'renew', 'request_key' => (string) Str::uuid(), 'created_at' => '2026-10-05 16:00:00']);
        $period->save();
        foreach (['2026-10-05 15:59:59', '2026-10-05 16:00:00', '2026-10-06 15:59:59', '2026-10-07 16:00:00'] as $time) {
            (new AttendanceRecord)->forceFill(['member_id' => $member->id, 'branch_id' => $member->home_branch_id, 'source' => 'manual', 'checked_in_at' => $time])->save();
        }
        $payment = (new Payment)->forceFill(['member_id' => $member->id, 'member_subscription_id' => $period->id, 'branch_id' => $member->home_branch_id, 'amount_centavos' => 10001, 'method' => 'cash', 'recorded_by' => $this->owner->id, 'paid_at' => '2026-10-05 16:00:00', 'request_key' => (string) Str::uuid()]);
        $payment->save();
    }

    private function export(): ReportExport
    {
        $this->post(route('reports.export'), [...$this->filters(), 'request_key' => (string) Str::uuid()])->assertSessionHasNoErrors()->assertRedirect();

        return ReportExport::latest('id')->firstOrFail();
    }

    public function test_daily_reports_use_manila_boundaries_and_exclude_voided_revenue(): void
    {
        $this->records();
        $this->get(route('reports.index', $this->filters()))->assertInertia(fn (Assert $p) => $p->component('dashboard/reports/Index')->where('summary.checkIns', 2)->where('summary.renewals', 1)->where('summary.revenueCentavos', 10001)->where('filters.days_sort', 'desc')->where('days.data.0.uniqueMembers', 0)->where('days.data.1.checkIns', 2));
        Payment::sole()->forceFill(['status' => 'voided', 'voided_by' => $this->owner->id, 'void_reason' => 'Fictional test', 'voided_at' => now()])->save();
        $this->get(route('reports.index', $this->filters()))->assertInertia(fn (Assert $p) => $p->where('summary.revenueCentavos', 0));
    }

    public function test_detailed_tabs_and_csv_respect_filters_scope_and_formula_safety(): void
    {
        $this->records();
        Member::sole()->update(['name' => '=Fictional spreadsheet']);
        $this->get(route('reports.index', $this->filters()))->assertInertia(fn (Assert $p) => $p
            ->where('filters.report', 'revenue')->where('detail.metrics.0.value', 10001)->has('detail.rows.data', 1));
        $foreign = Gym::factory()->create();
        app(CurrentGym::class)->runAs($foreign, function () use ($foreign) {
            $member = Member::factory()->create(['name' => 'Foreign report member']);
            (new AttendanceRecord)->forceFill(['member_id' => $member->id, 'branch_id' => $foreign->mainBranch()->id, 'source' => 'manual', 'checked_in_at' => '2026-10-05 16:00:00'])->save();
        });
        $params = [...$this->filters(), 'report' => 'attendance', 'search' => 'Fictional', 'source' => 'manual'];
        $this->get(route('reports.index', $params))->assertInertia(fn (Assert $p) => $p
            ->where('detail.metrics.0.value', 2)->where('detail.metrics.1.value', 1)->has('detail.rows.data', 2)
            ->where('detail.columns.0.label', 'Check-in'));
        $csv = $this->get(route('reports.index', [...$params, 'export' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString("'=Fictional spreadsheet", $csv);
        $this->assertStringNotContainsString('Foreign report member', $csv);
        $this->assertStringNotContainsString('Revenue', $csv);
        $this->assertCount(3, array_filter(explode("\n", trim($csv))));
        $this->get(route('reports.index', [...$this->filters(), 'report' => 'membership']))->assertInertia(fn (Assert $p) => $p
            ->where('detail.metrics.0.value', 1)->where('detail.rows.data.0.plan', 'Fictional access')->where('detail.rows.data.0.paid', 10001)->where('detail.rows.data.0.balance', 109999));
        Feature::for($this->gym)->deactivate('payments');
        $this->get(route('reports.index', [...$this->filters(), 'report' => 'revenue']))->assertNotFound();
        $this->get(route('reports.index', [...$this->filters(), 'report' => 'revenue', 'export' => 'csv']))->assertNotFound();
    }

    public function test_membership_and_payments_report_combines_saved_offerings_and_settlement(): void
    {
        $this->records();
        $member = Member::sole();
        $rows = [];
        foreach (['benefit' => [59900, 59900], 'package' => [60000, 54000]] as $kind => [$original, $agreed]) {
            $plan = MembershipPlan::factory()->create(['kind' => $kind]);
            $purchase = (new MemberPurchase)->forceFill(['member_id' => $member->id, 'membership_plan_id' => $plan->id, 'branch_id' => $member->home_branch_id, 'kind' => $kind, 'plan_name' => 'Fictional '.$kind, 'original_price_centavos' => $original, 'price_centavos' => $agreed, 'starts_at' => '2026-10-05 16:00:00', 'ends_at' => '2026-11-05 16:00:00', 'sessions' => $kind === 'package' ? 3 : 0, 'created_by' => $this->owner->id, 'request_key' => (string) Str::uuid(), 'created_at' => '2026-10-05 16:00:00', 'snapshot' => ['discounts' => $kind === 'package' ? [['reason' => 'Student', 'savings_centavos' => 6000]] : []]]);
            $purchase->save();
            $rows[$kind] = $purchase;
        }
        (new Payment)->forceFill(['member_id' => $member->id, 'member_purchase_id' => $rows['package']->id, 'branch_id' => $member->home_branch_id, 'amount_centavos' => 20000, 'discount_centavos' => 1000, 'discount_basis_points' => 476, 'discount_base_centavos' => 21000, 'discount_scope' => 'payment', 'method' => 'cash', 'recorded_by' => $this->owner->id, 'paid_at' => '2026-10-06 00:00:00', 'request_key' => (string) Str::uuid()])->save();
        $void = (new Payment)->forceFill(['member_id' => $member->id, 'member_purchase_id' => $rows['benefit']->id, 'branch_id' => $member->home_branch_id, 'amount_centavos' => 10000, 'method' => 'cash', 'recorded_by' => $this->owner->id, 'paid_at' => '2026-10-06 00:00:00', 'request_key' => (string) Str::uuid()]);
        $void->save();
        $void->forceFill(['status' => 'voided', 'voided_by' => $this->owner->id, 'void_reason' => 'Fictional correction', 'voided_at' => now()])->save();
        $foreign = Gym::factory()->create();
        app(CurrentGym::class)->runAs($foreign, function () {
            $member = Member::factory()->create(['name' => 'Foreign purchase member']);
            $plan = MembershipPlan::factory()->create(['kind' => 'benefit']);
            (new MemberPurchase)->forceFill(['member_id' => $member->id, 'membership_plan_id' => $plan->id, 'branch_id' => $member->home_branch_id, 'kind' => 'benefit', 'plan_name' => 'Foreign purchase', 'original_price_centavos' => 10000, 'price_centavos' => 10000, 'starts_at' => '2026-10-05 16:00:00', 'ends_at' => null, 'request_key' => (string) Str::uuid(), 'created_at' => '2026-10-05 16:00:00', 'snapshot' => []])->save();
        });
        $params = [...$this->filters(), 'report' => 'membership'];
        $this->get(route('reports.index', $params))->assertInertia(fn (Assert $p) => $p
            ->where('filters.report', 'membership')->where('availableReports', ['revenue', 'membership', 'attendance', 'appointments'])
            ->has('detail.rows.data', 3)->where('detail.rows.total', 3)->where('detail.metrics.0.value', 3)
            ->where('detail.metrics.1.value', 239900)->where('detail.metrics.2.value', 7000)->where('detail.metrics.3.value', 232900)
            ->where('detail.metrics.4.value', 30001)->where('detail.metrics.5.value', 202899));
        $this->get(route('reports.index', [...$params, 'kind' => 'package']))->assertInertia(fn (Assert $p) => $p
            ->has('detail.rows.data', 1)->where('detail.rows.data.0.kindLabel', 'Training package')
            ->where('detail.rows.data.0.amount', 53000)->where('detail.rows.data.0.discount', 7000)
            ->where('detail.rows.data.0.paid', 20000)->where('detail.rows.data.0.balance', 33000)
            ->where('detail.rows.data.0.sessions', '3 of 3 remaining'));
        $this->get(route('reports.index', [...$params, 'payment_state' => 'unpaid']))->assertInertia(fn (Assert $p) => $p->has('detail.rows.data', 1)->where('detail.rows.data.0.kindLabel', 'Membership plan'));
        $this->get(route('reports.index', [...$params, 'search' => $member->name, 'method' => 'cash']))->assertInertia(fn (Assert $p) => $p->has('detail.rows.data', 2));
        $this->get(route('reports.index', [...$params, 'report' => 'payments']))->assertInertia(fn (Assert $p) => $p->where('filters.report', 'membership')->has('detail.rows.data', 3));
        $csv = $this->get(route('reports.index', [...$params, 'export' => 'csv', 'page' => 2]))->assertOk()->streamedContent();
        $this->assertCount(4, array_filter(explode("\n", trim($csv))));
        $this->assertStringContainsString('Membership plan', $csv);
        $this->assertStringContainsString('530.00', $csv);
        $this->assertStringContainsString('Student:', $csv);
        $this->assertStringContainsString('Payment discount:', $csv);
        $this->assertStringNotContainsString('Foreign purchase', $csv);
        $rows['package']->forceFill(['status' => 'cancelled'])->save();
        $this->get(route('reports.index', [...$params, 'kind' => 'package']))->assertInertia(fn (Assert $p) => $p->where('detail.rows.data.0.paid', 20000)->where('detail.rows.data.0.balance', 0)->where('detail.rows.data.0.status', 'Cancelled'));
        Feature::for($this->gym)->deactivate('payments');
        $this->get(route('reports.index', $params))->assertInertia(fn (Assert $p) => $p->has('detail.rows.data', 3)->where('detail.rows.data.0.paid', 0)->has('detail.metrics', 4));
    }

    public function test_detail_exports_require_full_reports_and_ignore_page_size(): void
    {
        foreach (range(1, 8) as $i) {
            $this->records();
        }
        $this->get(route('reports.index', $this->filters()))->assertInertia(fn (Assert $p) => $p->has('detail.rows.data', 8)->where('detail.rows.total', 8));
        $csv = $this->get(route('reports.index', [...$this->filters(), 'export' => 'csv', 'page' => 2]))->assertOk()->streamedContent();
        $this->assertCount(9, array_filter(explode("\n", trim($csv))));
        $this->gym->subscription->update(['plan' => Plan::Starter]);
        $this->get(route('reports.index', [...$this->filters(), 'export' => 'csv']))->assertNotFound();
    }

    public function test_queued_export_is_private_encrypted_idempotent_and_downloadable(): void
    {
        $this->records();
        $data = [...$this->filters(), 'request_key' => (string) Str::uuid()];
        $this->post(route('reports.export'), $data)->assertRedirect();
        $this->post(route('reports.export'), $data)->assertRedirect();
        Queue::assertPushed(GenerateReportExport::class, 1);
        $export = ReportExport::sole();
        (new GenerateReportExport($this->gym->id, $export->id))->handle(app(GymReport::class));
        $export->refresh();
        $this->assertSame('ready', $export->status);
        $this->assertStringNotContainsString('Date (Manila)', $export->getRawOriginal('csv'));
        $this->get(route('reports.download', $export))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertSee('100.01')->assertDontSee('Fictional access');
        (new GenerateReportExport($this->gym->id, $export->id))->handle(app(GymReport::class));
        $this->assertSame(1, ReportExport::count());
        $this->actingAs(User::factory()->memberOf($this->gym, GymRole::Staff)->create())->get(route('reports.download', $export))->assertForbidden();
    }

    public function test_job_rechecks_actor_access_and_restores_tenant_context(): void
    {
        $staff = User::factory()->memberOf($this->gym, GymRole::Staff)->create();
        $this->actingAs($staff);
        $export = $this->export();
        $this->gym->users()->updateExistingPivot($staff->id, ['is_active' => false]);
        $other = Gym::factory()->create();
        app(CurrentGym::class)->set($other);
        (new GenerateReportExport($this->gym->id, $export->id))->handle(app(GymReport::class));
        $this->assertSame($other->id, app(CurrentGym::class)->id());
        $this->assertDatabaseHas('report_exports', ['id' => $export->id, 'status' => 'failed', 'csv' => null]);
    }

    public function test_manager_reports_and_exports_restrict_to_their_branch(): void
    {
        $this->records();
        $branch = Branch::factory()->create(['gym_id' => $this->gym->id]);
        $this->records($branch);
        $manager = User::factory()->memberOf($this->gym, GymRole::BranchManager)->create();
        $this->actingAs($manager);
        $this->get(route('reports.index', $this->filters()))->assertInertia(fn (Assert $p) => $p->where('summary.checkIns', 2)->where('summary.revenueCentavos', 10001));
        $this->get(route('reports.index', [...$this->filters(), 'branch_id' => $branch->id]))->assertNotFound();
        $this->get(route('reports.index', [...$this->filters(), 'report' => 'membership']))->assertInertia(fn (Assert $p) => $p->has('detail.rows.data', 1)->where('detail.rows.data.0.balance', 109999));
        $export = $this->export();
        (new GenerateReportExport($this->gym->id, $export->id))->handle(app(GymReport::class));
        $this->get(route('reports.download', $export))->assertOk()->assertDontSee('200.02');
        $this->gym->users()->updateExistingPivot($manager->id, ['branch_id' => $branch->id]);
        $this->get(route('reports.download', $export))->assertForbidden();
    }

    public function test_starter_reports_are_bounded_and_export_is_feature_gated(): void
    {
        $this->gym->subscription->update(['plan' => Plan::Starter]);
        $this->get(route('reports.index', $this->filters()))->assertInertia(fn (Assert $p) => $p->where('fullReports', false)->where('exports', null));
        $this->get(route('reports.index', ['from' => '2026-01-01', 'through' => '2026-02-01']))->assertSessionHasErrors('through');
        $this->post(route('reports.export'), [...$this->filters(), 'request_key' => (string) Str::uuid()])->assertNotFound();
        Feature::for($this->gym)->deactivate('reports');
        $this->get(route('reports.index'))->assertNotFound();
    }

    public function test_coaches_cannot_access_reports_and_expired_exports_are_not_downloadable(): void
    {
        $export = $this->export();
        $this->travel(25)->hours();
        $this->get(route('reports.download', $export))->assertForbidden();
        $this->travelBack();
        $this->actingAs(User::factory()->memberOf($this->gym, GymRole::Coach)->create())->get(route('reports.index'))->assertForbidden();
    }

    public function test_ranges_are_validated_and_daily_rows_are_paginated(): void
    {
        $this->get(route('reports.index', ['from' => '2026-10-07', 'through' => '2026-10-06']))->assertSessionHasErrors('through');
        $this->get(route('reports.index', ['from' => '2025-01-01', 'through' => '2026-12-31']))->assertSessionHasErrors('through');
        $this->get(route('reports.index', ['from' => '2026-01-01', 'through' => '2026-01-31']))->assertInertia(fn (Assert $p) => $p->has('days.data', 10)->where('days.total', 31)->where('days.data.0.date', '2026-01-31'));
        $this->get(route('reports.index', ['from' => '2026-01-01', 'through' => '2026-01-31', 'days_sort' => 'asc']))->assertInertia(fn (Assert $p) => $p->where('filters.days_sort', 'asc')->where('days.data.0.date', '2026-01-01'));
    }

    public function test_date_presets_resolve_gym_local_dates_and_custom_ranges(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-06 16:30:00', 'UTC'));
        foreach ([
            [['period' => 'this-year'], '2026-01-01', '2026-10-07'],
            [['period' => 'this-month'], '2026-10-01', '2026-10-07'],
            [['period' => 'last-month'], '2026-09-01', '2026-09-30'],
            [['period' => 'month', 'month' => '2024-02'], '2024-02-01', '2024-02-29'],
            [['period' => 'custom', 'from' => '2026-10-02', 'through' => '2026-10-05'], '2026-10-02', '2026-10-05'],
        ] as [$params, $from, $through]) {
            $this->get(route('reports.index', $params))->assertInertia(fn (Assert $p) => $p->where('filters.period', $params['period'])->where('filters.from', $from)->where('filters.through', $through));
        }
        $this->travelTo(CarbonImmutable::parse('2026-12-31 16:30:00', 'UTC'));
        $this->get(route('reports.index', ['period' => 'last-month']))->assertInertia(fn (Assert $p) => $p->where('filters.from', '2026-12-01')->where('filters.through', '2026-12-31'));
        $this->get(route('reports.index', ['period' => 'this-year']))->assertInertia(fn (Assert $p) => $p->where('filters.from', '2027-01-01')->where('filters.through', '2027-01-01'));
    }

    public function test_date_presets_are_validated_and_retain_plan_range_limits(): void
    {
        $this->get(route('reports.index', ['period' => 'month']))->assertSessionHasErrors('month');
        $this->get(route('reports.index', ['period' => 'month', 'month' => '2026-13']))->assertSessionHasErrors('month');
        $this->get(route('reports.index', ['period' => 'unbounded']))->assertSessionHasErrors('period');
        $this->travelTo(CarbonImmutable::parse('2026-10-07 00:00:00', 'Asia/Manila'));
        $this->gym->subscription->update(['plan' => Plan::Starter]);
        $this->get(route('reports.index', ['period' => 'this-year']))->assertSessionHasErrors('through');
        $this->get(route('reports.index', ['period' => 'month', 'month' => '2024-02']))->assertInertia(fn (Assert $p) => $p->where('days.total', 29));
    }

    public function test_detail_and_queued_exports_use_the_selected_preset_dates(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-07 00:00:00', 'Asia/Manila'));
        $this->records();
        $this->get(route('reports.index', ['period' => 'this-month', 'report' => 'exports']))->assertInertia(fn (Assert $p) => $p->where('filters.report', 'revenue'));
        $csv = $this->get(route('reports.index', ['period' => 'this-month', 'export' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString('100.01', $csv);
        $csv = $this->get(route('reports.index', [...$this->filters(), 'period' => 'last-month', 'export' => 'csv']))->assertOk()->streamedContent();
        $this->assertCount(1, array_filter(explode("\n", trim($csv))));
        $params = ['period' => 'month', 'month' => '2024-02', 'request_key' => (string) Str::uuid()];
        $this->post(route('reports.export'), $params)->assertSessionHasNoErrors()->assertRedirect(route('reports.index', ['from' => '2024-02-01', 'through' => '2024-02-29', 'period' => 'month', 'month' => '2024-02', 'report' => 'revenue']));
        $export = ReportExport::sole();
        $this->assertSame('2024-02-01', $export->from);
        $this->assertSame('2024-02-29', $export->through);
        (new GenerateReportExport($this->gym->id, $export->id))->handle(app(GymReport::class));
        $this->get(route('reports.download', $export))->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="gym-report-2024-02-01-2024-02-29.csv"');
    }

    public function test_export_retries_with_changed_filters_are_rejected_and_feature_changes_block_jobs(): void
    {
        $key = (string) Str::uuid();
        $this->post(route('reports.export'), [...$this->filters(), 'request_key' => $key])->assertRedirect();
        $this->post(route('reports.export'), [...$this->filters(), 'through' => '2026-10-08', 'request_key' => $key])->assertSessionHasErrors('request_key');
        $export = ReportExport::sole();
        Feature::for($this->gym)->deactivate('full-reports');
        (new GenerateReportExport($this->gym->id, $export->id))->handle(app(GymReport::class));
        $this->assertSame('failed', $export->refresh()->status);
        $this->assertNull($export->csv);
        $this->get(route('reports.download', $export))->assertNotFound();
    }

    public function test_expired_export_pruning_cleans_all_tenants(): void
    {
        $old = $this->export();
        $this->travel(25)->hours();
        $fresh = $this->export();
        app(CurrentGym::class)->forget();
        $this->artisan('model:prune', ['--model' => [ReportExport::class]])->assertSuccessful();
        $this->assertDatabaseMissing('report_exports', ['id' => $old->id]);
        $this->assertDatabaseHas('report_exports', ['id' => $fresh->id]);
    }

    public function test_report_generation_failure_rolls_back_and_can_retry(): void
    {
        $export = $this->export();
        $broken = \Mockery::mock(GymReport::class);
        $broken->shouldReceive('build')->once()->andThrow(new \RuntimeException('Fictional failure'));
        try {
            (new GenerateReportExport($this->gym->id, $export->id))->handle($broken);
            $this->fail('Expected report error');
        } catch (\RuntimeException $error) {
            $this->assertSame('Fictional failure', $error->getMessage());
        }
        $this->assertSame('queued', $export->refresh()->status);
        $this->assertNull($export->csv);
        (new GenerateReportExport($this->gym->id, $export->id))->handle(app(GymReport::class));
        $this->assertSame('ready', $export->refresh()->status);
    }
}
