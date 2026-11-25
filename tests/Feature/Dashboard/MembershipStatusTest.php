<?php

namespace Tests\Feature\Dashboard;

use App\Actions\ManageMemberSubscription;
use App\Enums\GymRole;
use App\Enums\MemberSubscriptionStatus;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\MemberSubscription;
use App\Models\User;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MembershipStatusTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private MembershipPlan $plan;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-05 04:00:00', 'UTC'));
        $this->gym = Gym::factory()->create();
        $this->owner = User::factory()->memberOf($this->gym, GymRole::Owner)->create();
        app(CurrentGym::class)->set($this->gym);
        $this->plan = MembershipPlan::factory()->create();
        $this->actingAs($this->owner);
    }

    private function period(Member $member, string $start, string $end, string $status = 'active'): MemberSubscription
    {
        return tap(new MemberSubscription, fn ($subscription) => $subscription->forceFill([
            'member_id' => $member->id, 'membership_plan_id' => $this->plan->id,
            'plan_name' => $this->plan->name, 'price_centavos' => $this->plan->price_centavos,
            'duration_amount' => 1, 'duration_unit' => 'months',
            'starts_at' => CarbonImmutable::parse($start, $this->gym->timezone)->utc(),
            'ends_at' => CarbonImmutable::parse($end, $this->gym->timezone)->utc(),
            'status' => $status, 'operation' => 'assign', 'request_key' => (string) Str::uuid(),
        ])->save());
    }

    public function test_member_filters_follow_displayed_period_and_summary_counts_ignore_search(): void
    {
        $active = Member::factory()->create(['name' => 'Renewed member']);
        $this->period($active, '2026-09-01', '2026-10-01');
        $this->period($active, '2026-10-01', '2026-11-01');
        $expiring = Member::factory()->inactive()->create();
        $this->period($expiring, '2026-10-01', '2026-10-08');
        $scheduled = Member::factory()->create();
        $this->period($scheduled, '2026-09-01', '2026-10-01');
        $this->period($scheduled, '2026-11-01', '2026-12-01');
        $none = Member::factory()->create();
        foreach (['active' => $active, 'expiring-soon' => $expiring, 'scheduled' => $scheduled, 'none' => $none] as $state => $member) {
            $this->get(route('members.index', ['membership_state' => $state]))->assertInertia(fn (Assert $page) => $page
                ->has('members.data', 1)->where('members.data.0.id', $member->id)
                ->where('statistics', ['total' => 4, 'active' => 2, 'expiring' => 1, 'inactive' => 1]));
        }
        $this->get(route('members.index', ['search' => 'Renewed', 'plan_id' => $this->plan->id, 'per_page' => 8]))->assertInertia(fn (Assert $page) => $page
            ->has('members.data', 1)->where('members.per_page', 8)->where('statistics.total', 4));
        $this->get(route('members.index', ['membership_state' => 'none', 'plan_id' => $this->plan->id]))->assertInertia(fn (Assert $page) => $page->has('members.data', 0));
        $foreign = app(CurrentGym::class)->runAs(Gym::factory()->create(), fn () => MembershipPlan::factory()->create());
        $this->get(route('members.index', ['plan_id' => $foreign->id]))->assertNotFound();
    }

    public function test_dashboard_counts_members_once_using_current_and_latest_states(): void
    {
        foreach ([
            ['2026-10-01', '2026-11-01', 'active'],
            ['2026-10-01', '2026-10-06', 'active'],
            ['2026-10-01', '2026-10-13', 'active'],
            ['2026-10-01', '2026-10-13 00:00:01', 'active'],
            ['2026-09-01', '2026-10-05 12:00:00', 'active'],
            ['2026-09-01', '2026-10-01', 'suspended'],
            ['2026-09-01', '2026-10-01', 'expired'],
            ['2026-10-01', '2026-11-01', 'suspended'],
            ['2026-09-01', '2026-10-01', 'cancelled'],
            ['2026-11-01', '2026-12-01', 'active'],
        ] as [$start, $end, $status]) {
            $this->period(Member::factory()->inactive()->create(), $start, $end, $status);
        }
        Member::factory()->create();
        $renewed = Member::factory()->create();
        $this->period($renewed, '2026-09-01', '2026-10-01');
        $this->period($renewed, '2026-10-01', '2026-11-01');
        $this->period($renewed, '2026-11-01', '2026-12-01');
        $scheduled = Member::factory()->create();
        $this->period($scheduled, '2026-09-01', '2026-10-01');
        $this->period($scheduled, '2026-11-01', '2026-12-01');
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('membershipCounts', ['active' => 5, 'expiringThisWeek' => 2, 'expired' => 3]));
        $this->travelTo(CarbonImmutable::parse('2026-10-06 00:00:00', 'Asia/Manila'));
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('membershipCounts', ['active' => 4, 'expiringThisWeek' => 2, 'expired' => 4]));
    }

    public function test_dashboard_hides_counts_when_members_disabled_or_role_is_coach(): void
    {
        Feature::for($this->gym)->deactivate('members');
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('membershipCounts', null));
        Feature::for($this->gym)->activate('members');
        $coach = User::factory()->memberOf($this->gym, GymRole::Coach)->create();
        $this->actingAs($coach)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('membershipCounts', null));
    }

    public function test_dashboard_counts_one_hundred_members_without_loading_histories(): void
    {
        foreach (range(1, 100) as $number) {
            $this->period(Member::factory()->create(), '2026-10-01', '2026-11-01');
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('membershipCounts', ['active' => 100, 'expiringThisWeek' => 0, 'expired' => 0]));
        $queries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'member_subscriptions'));
        DB::disableQueryLog();
        // Three summary counts, four extra chart states and one renewal aggregate.
        $this->assertCount(8, $queries);
        foreach ($queries as $query) {
            $this->assertStringContainsString('count(*)', $query['query']);
        }
    }

    public function test_dashboard_counts_exclude_other_gyms_and_manager_other_branches(): void
    {
        $this->gym->subscription->update(['plan' => 'multi_branch']);
        $main = $this->gym->branches()->firstOrFail();
        $otherBranch = Branch::factory()->create();
        $this->period(Member::factory()->create(['home_branch_id' => $main->id]), '2026-10-01', '2026-11-01');
        $this->period(Member::factory()->create(['home_branch_id' => $otherBranch->id]), '2026-10-01', '2026-10-06');
        $foreignGym = Gym::factory()->create();
        app(CurrentGym::class)->runAs($foreignGym, function () {
            $this->plan = MembershipPlan::factory()->create();
            $this->period(Member::factory()->create(), '2026-10-01', '2026-11-01');
        });
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('membershipCounts', ['active' => 2, 'expiringThisWeek' => 1, 'expired' => 0]));
        $manager = User::factory()->memberOf($this->gym, GymRole::BranchManager)->create();
        $this->gym->users()->updateExistingPivot($manager->id, ['branch_id' => $main->id]);
        $this->actingAs($manager)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('membershipCounts', ['active' => 1, 'expiringThisWeek' => 0, 'expired' => 0]));
    }

    public function test_list_shows_each_membership_state_independently_of_profile_activation(): void
    {
        foreach (['Active' => ['2026-10-01', '2026-11-01', 'active'], 'Expiring soon' => ['2026-10-01', '2026-10-10', 'active'], 'Expired' => ['2026-09-01', '2026-10-01', 'active'], 'Suspended' => ['2026-10-01', '2026-11-01', 'suspended'], 'Cancelled' => ['2026-10-01', '2026-11-01', 'cancelled'], 'Scheduled' => ['2026-11-01', '2026-12-01', 'active']] as $label => [$start, $end, $status]) {
            $member = Member::factory()->create(['name' => $label]);
            $this->period($member, $start, $end, $status);
        }
        Member::factory()->inactive()->create(['name' => 'No membership']);
        $this->get(route('members.index'))->assertInertia(function (Assert $page) {
            $page->has('members.data', 7);
            foreach ($page->toArray()['props']['members']['data'] as $member) {
                $this->assertSame($member['name'], $member['membershipStatus']);
            }
        });
    }

    public function test_current_period_takes_priority_over_future_renewal_and_cancelled_period(): void
    {
        $member = Member::factory()->create();
        $this->period($member, '2026-10-01', '2026-11-01');
        $this->period($member, '2026-11-01', '2026-12-01');
        $this->period($member, '2026-10-01', '2026-11-01', 'cancelled');
        $this->get(route('members.index'))->assertInertia(fn (Assert $page) => $page->where('members.data.0.membershipStatus', 'Active'));
        $this->travelTo(CarbonImmutable::parse('2026-11-01 00:00:00', 'Asia/Manila'));
        $this->get(route('members.index'))->assertInertia(fn (Assert $page) => $page->where('members.data.0.membershipStatus', 'Active'));
    }

    public function test_earliest_future_period_is_selected_when_there_is_a_gap(): void
    {
        $member = Member::factory()->create();
        $this->period($member, '2026-08-01', '2026-09-01');
        $first = $this->period($member, '2026-11-01', '2026-12-01');
        $this->period($member, '2026-12-01', '2027-01-01');
        $this->assertSame($first->id, $member->nextMembership->id);
        $this->get(route('members.index'))->assertInertia(fn (Assert $page) => $page->where('members.data.0.membershipStatus', 'Scheduled'));
    }

    public function test_suspended_current_membership_is_not_hidden_by_a_future_renewal(): void
    {
        $member = Member::factory()->inactive()->create();
        $this->period($member, '2026-10-01', '2026-11-01', 'suspended');
        $this->period($member, '2026-11-01', '2026-12-01');
        $this->get(route('members.index'))->assertInertia(fn (Assert $page) => $page->where('members.data.0.membershipStatus', 'Suspended')->where('members.data.0.is_active', false));
    }

    public function test_list_and_history_share_warning_and_expiration_boundaries(): void
    {
        $member = Member::factory()->create();
        $period = $this->period($member, '2026-10-01', '2026-10-14');
        foreach ([['2026-10-05 23:59:59', 'Active'], ['2026-10-06 00:00:00', 'Expiring soon'], ['2026-10-14 00:00:00', 'Expired']] as [$at, $state]) {
            $this->travelTo(CarbonImmutable::parse($at, $this->gym->timezone));
            $this->get(route('members.index'))->assertInertia(fn (Assert $page) => $page->where('members.data.0.membershipStatus', $state));
            $this->get(route('member-subscriptions.index', $member))->assertInertia(fn (Assert $page) => $page->where('subscriptions.data.0.status', $state));
        }
        $this->assertSame(MemberSubscriptionStatus::Active, $period->refresh()->status);
    }

    public function test_status_queries_remain_bounded_for_a_full_page(): void
    {
        Member::factory()->count(25)->create()->each(fn ($member) => $this->period($member, '2026-10-01', '2026-11-01'));
        DB::enableQueryLog();
        $this->get(route('members.index'))->assertOk();
        $queries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'member_subscriptions'));
        DB::disableQueryLog();
        // Three eager-loaded summaries plus two aggregate membership card queries.
        $this->assertLessThanOrEqual(5, $queries->count());
    }

    public function test_status_summaries_preserve_tenant_and_manager_isolation(): void
    {
        $other = Gym::factory()->create();
        app(CurrentGym::class)->runAs($other, fn () => Member::factory()->create());
        $this->gym->subscription->update(['plan' => 'multi_branch']);
        $branch = Branch::factory()->create();
        $foreignBranchMember = Member::factory()->create();
        $this->period($foreignBranchMember, '2026-10-01', '2026-11-01');
        $own = Member::factory()->create(['home_branch_id' => $branch->id]);
        $this->period($own, '2026-09-01', '2026-10-01');
        $manager = User::factory()->memberOf($this->gym, GymRole::BranchManager)->create();
        $this->gym->users()->updateExistingPivot($manager->id, ['branch_id' => $branch->id]);
        $this->actingAs($manager)->get(route('members.index'))->assertInertia(fn (Assert $page) => $page->has('members.data', 1)->where('members.data.0.id', $own->id)->where('members.data.0.membershipStatus', 'Expired'));
    }

    public static function calendarPeriods(): array
    {
        return [['2024-01-31', 1, 'months', '2024-02-29'], ['2025-01-31', 1, 'months', '2025-02-28'], ['2024-02-29', 12, 'months', '2025-02-28'], ['2026-12-31', 1, 'days', '2027-01-01']];
    }

    #[DataProvider('calendarPeriods')]
    public function test_assigned_calendar_period_expiration(string $start, int $amount, string $unit, string $end): void
    {
        $member = Member::factory()->create();
        $this->plan->update(['duration_amount' => $amount, 'duration_unit' => $unit]);
        $period = app(ManageMemberSubscription::class)->assign($member, ['membership_plan_id' => $this->plan->id, 'operation' => 'assign', 'starts_on' => $start, 'request_key' => (string) Str::uuid()]);
        $boundary = CarbonImmutable::parse($end, $this->gym->timezone);
        $this->assertTrue($period->ends_at->equalTo($boundary));
        $this->assertSame('Expiring soon', $period->displayStatus($boundary->subSecond(), $this->gym->timezone));
        $this->assertSame('Expired', $period->displayStatus($boundary, $this->gym->timezone));
    }
}
