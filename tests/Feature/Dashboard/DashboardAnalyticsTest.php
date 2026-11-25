<?php

namespace Tests\Feature\Dashboard;

use App\Actions\VoidPayment;
use App\Enums\GymRole;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\MemberSubscription;
use App\Models\Payment;
use App\Models\User;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class DashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-07 12:00:00', 'Asia/Manila'));
        $this->gym = Gym::factory()->create();
        $this->gym->subscription->update(['plan' => 'multi_branch']);
        app(CurrentGym::class)->set($this->gym);
        $this->owner = User::factory()->memberOf($this->gym, GymRole::Owner)->create();
        $this->actingAs($this->owner);
    }

    private function member(?Branch $branch = null): MemberSubscription
    {
        $member = Member::factory()->create(['home_branch_id' => ($branch ?? app(CurrentGym::class)->get()->mainBranch())->id]);
        $plan = MembershipPlan::factory()->create();

        return tap(new MemberSubscription, fn ($period) => $period->forceFill(['member_id' => $member->id, 'membership_plan_id' => $plan->id, 'plan_name' => 'Annual plan', 'price_centavos' => 1200000, 'duration_amount' => 12, 'duration_unit' => 'months', 'starts_at' => now()->subMonths(3), 'ends_at' => now()->addMonths(9), 'status' => 'active', 'operation' => 'assign', 'request_key' => (string) Str::uuid()])->save());
    }

    private function payment(MemberSubscription $period, string $date, int $amount, string $method = 'cash'): Payment
    {
        return tap(new Payment, fn ($payment) => $payment->forceFill(['member_id' => $period->member_id, 'member_subscription_id' => $period->id, 'branch_id' => $period->member->home_branch_id, 'recorded_by' => auth()->id(), 'paid_at' => CarbonImmutable::parse($date, 'Asia/Manila')->utc(), 'amount_centavos' => $amount, 'method' => $method, 'request_key' => (string) Str::uuid()])->save());
    }

    private function checkIn(MemberSubscription $period, string $date): void
    {
        (new AttendanceRecord)->forceFill(['member_id' => $period->member_id, 'branch_id' => $period->member->home_branch_id, 'checked_in_at' => CarbonImmutable::parse($date, 'Asia/Manila')->utc(), 'source' => 'manual'])->save();
    }

    public function test_real_series_follow_manila_boundaries_and_exclude_voids_and_future_records(): void
    {
        $period = $this->member();
        $this->payment($period, '2026-09-30 23:59:59', 20000);
        $this->payment($period, '2026-10-01 00:00:00', 10000);
        $this->payment($period, '2026-10-07 11:00:00', 5000, 'gcash');
        $voided = $this->payment($period, '2026-10-07 10:00:00', 9000);
        app(VoidPayment::class)->handle($voided, 'Fictional correction');
        $this->payment($period, '2026-10-08 00:00:00', 7000);
        $this->checkIn($period, '2026-10-06 23:59:59');
        $this->checkIn($period, '2026-10-07 00:00:00');
        $this->checkIn($period, '2026-10-07 11:00:00');
        $this->checkIn($period, '2026-10-08 00:00:00');
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('analytics.period.from', '2026-10-01')->where('analytics.revenue.totalCentavos', 15000)
            ->where('analytics.revenue.months.10.value', 20000)->where('analytics.revenue.months.11.value', 15000)
            ->has('analytics.revenue.methods', 2)->where('analytics.attendance.today', 2)
            ->where('analytics.attendance.changePercent', 100)->where('analytics.attendance.periodTotal', 3)
            ->where('analytics.attendance.days.28.value', 1)->where('analytics.attendance.days.29.value', 2)
            ->where('analytics.attendance.busiestDay', '2026-10-07')->where('analytics.membership.total', 1)
            ->missing('analytics.membership.members'));
    }

    public function test_branch_manager_saved_view_and_foreign_gym_cannot_leak_analytics(): void
    {
        $main = $this->member();
        $otherBranch = Branch::factory()->create();
        $other = $this->member($otherBranch);
        $this->payment($main, '2026-10-07 09:00:00', 10000);
        $this->payment($other, '2026-10-07 09:00:00', 50000);
        $this->checkIn($main, '2026-10-07 09:00:00');
        $this->checkIn($other, '2026-10-07 09:00:00');
        $foreignGym = Gym::factory()->create();
        app(CurrentGym::class)->runAs($foreignGym, function () use ($foreignGym) {
            $this->actingAs(User::factory()->memberOf($foreignGym, GymRole::Owner)->create());
            $foreignPeriod = $this->member();
            $this->payment($foreignPeriod, '2026-10-07 09:00:00', 90000);
            $this->checkIn($foreignPeriod, '2026-10-07 09:00:00');
        });
        $this->actingAs($this->owner);
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('analytics.revenue.totalCentavos', 60000)->where('analytics.membership.total', 2)->where('analytics.attendance.today', 2));
        $this->withSession(['view_branch.'.$this->gym->id => $otherBranch->id])->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('analytics.revenue.totalCentavos', 50000)->where('analytics.attendance.today', 1)->where('analytics.membership.total', 1));
        $manager = User::factory()->memberOf($this->gym, GymRole::BranchManager)->create();
        $this->gym->users()->updateExistingPivot($manager->id, ['branch_id' => $this->gym->mainBranch()->id]);
        $this->actingAs($manager)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('analytics.revenue.totalCentavos', 10000)->where('analytics.attendance.today', 1)->where('analytics.membership.total', 1));
    }

    public function test_period_changes_zero_baselines_feature_flags_and_coach_permissions(): void
    {
        $period = $this->member();
        $this->payment($period, '2026-10-01 09:00:00', 10000);
        $this->get(route('dashboard', ['period' => 'week']))->assertInertia(fn (Assert $page) => $page
            ->where('analytics.period.from', '2026-10-05')->where('analytics.revenue.totalCentavos', 0));
        $this->get(route('dashboard', ['period' => 'year']))->assertInertia(fn (Assert $page) => $page
            ->where('analytics.period.from', '2026-01-01')->where('analytics.revenue.totalCentavos', 10000)
            ->where('analytics.revenue.changePercent', null)->where('analytics.attendance.changePercent', null));
        $this->get(route('dashboard', ['period' => 'invalid']))->assertSessionHasErrors('period');
        foreach (['members', 'payments', 'qr-attendance'] as $feature) {
            Feature::for($this->gym)->deactivate($feature);
        }
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('analytics.revenue', null)->where('analytics.membership', null)->where('analytics.attendance', null)->where('analytics.memberStats', null));
        foreach (['members', 'payments', 'qr-attendance'] as $feature) {
            Feature::for($this->gym)->activate($feature);
        }
        $this->actingAs(User::factory()->memberOf($this->gym, GymRole::Coach)->create())->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('analytics.revenue', null)->where('analytics.membership', null)->where('analytics.attendance', null));
    }
}
