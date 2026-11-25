<?php

namespace Tests\Feature\Dashboard;

use App\Actions\StartDemo;
use App\Enums\GymRole;
use App\Enums\MemberSubscriptionStatus;
use App\Enums\Plan;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\MemberSubscription;
use App\Models\User;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Database\Seeders\IronHouseMemberSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature;
use LogicException;
use Tests\TestCase;

class MemberSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private Member $member;

    private MembershipPlan $plan;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-05 04:00:00', 'UTC'));
        $this->gym = Gym::factory()->create();
        $this->owner = User::factory()->memberOf($this->gym, GymRole::Owner)->create();
        app(CurrentGym::class)->set($this->gym);
        $this->member = Member::factory()->create();
        $this->plan = MembershipPlan::factory()->create(['duration_amount' => 30, 'duration_unit' => 'days']);
        $this->actingAs($this->owner);
    }

    private function data(array $overrides = []): array
    {
        return array_replace(['membership_plan_id' => $this->plan->id, 'operation' => 'assign', 'starts_on' => '2026-10-05', 'request_key' => (string) Str::uuid()], $overrides);
    }

    private function assign(array $overrides = []): MemberSubscription
    {
        $this->post(route('member-subscriptions.store', $this->member), $this->data($overrides))->assertSessionHasNoErrors();

        return $this->member->subscriptions()->latest('id')->firstOrFail();
    }

    public function test_freeze_records_preserve_expiry_and_record_actor_reason_and_resume(): void
    {
        $period = $this->assign();
        $end = $period->ends_at;
        $route = route('member-subscriptions.update', [$this->member, $period]);
        $this->patch($route, ['action' => 'suspend', 'reason' => 'Fictional travel'])->assertSessionHasNoErrors();
        $this->patch($route, ['action' => 'suspend', 'reason' => 'Retry'])->assertSessionHasNoErrors();
        $this->assertSame(1, AuditLog::where('action', 'membership.suspended')->count());
        $this->assertTrue($period->fresh()->ends_at->equalTo($end));
        $this->get(route('freeze-records.index'))->assertInertia(fn (Assert $p) => $p->has('records.data', 1)->where('records.data.0.action', 'Frozen')->where('records.data.0.createdBy', $this->owner->name)->where('records.data.0.reason', 'Fictional travel')->missing('records.data.0.ip_address'));
        $this->patch($route, ['action' => 'resume', 'reason' => 'Returned'])->assertSessionHasNoErrors();
        $this->get(route('freeze-records.index'))->assertInertia(fn (Assert $p) => $p->has('records.data', 2)->where('records.data.0.action', 'Resumed'));
        $this->assertTrue($period->fresh()->ends_at->equalTo($end));
        $this->get(route('freeze-records.index', ['member_id' => $this->member->id]))->assertInertia(fn (Assert $p) => $p->where('member.id', $this->member->id)->has('records.data', 2));
        $other = Member::factory()->create();
        $this->get(route('freeze-records.index', ['member_id' => $other->id]))->assertInertia(fn (Assert $p) => $p->where('member.id', $other->id)->has('records.data', 0));
        $this->get(route('freeze-records.index', ['search' => 'No match']))->assertInertia(fn (Assert $p) => $p->has('records.data', 0));
        $coach = User::factory()->memberOf($this->gym, GymRole::Coach)->create();
        $this->actingAs($coach)->get(route('freeze-records.index'))->assertForbidden();
    }

    public function test_assignment_snapshots_terms_and_converts_manila_midnight_to_utc(): void
    {
        $period = $this->assign();
        $this->assertSame('2026-10-04 16:00:00', $period->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-03 16:00:00', $period->ends_at->format('Y-m-d H:i:s'));
        $this->plan->forceFill(['name' => 'Changed', 'price_centavos' => 999999, 'duration_amount' => 5])->save();
        $this->assertSame(150000, $period->refresh()->price_centavos);
        $this->assertSame(30, $period->duration_amount);
        $this->assertSame('membership.assigned', AuditLog::sole()->action);
        $this->get(route('member-subscriptions.index', $this->member))->assertInertia(fn (Assert $page) => $page->where('subscriptions.data.0.startsOn', '2026-10-05')->where('subscriptions.data.0.validThrough', '2026-11-03')->missing('subscriptions.data.0.request_key')->where('member.mobile', $this->member->mobile)->missing('member.portal_password')->missing('member.privacy_recorded_by'));
    }

    public function test_overlap_is_rejected_but_adjacent_periods_are_allowed(): void
    {
        $this->assign();
        foreach (['2026-10-01', '2026-10-05', '2026-10-20'] as $date) {
            $this->post(route('member-subscriptions.store', $this->member), $this->data(['starts_on' => $date]))->assertSessionHasErrors('starts_on');
        }
        $this->assign(['starts_on' => '2026-11-04']);
        $this->assertSame(2, $this->member->subscriptions()->count());
    }

    public function test_renewal_starts_after_latest_period_and_is_idempotent(): void
    {
        $first = $this->assign();
        $data = $this->data(['operation' => 'renew', 'starts_on' => null]);
        $this->post(route('member-subscriptions.store', $this->member), $data)->assertSessionHasNoErrors();
        $renewal = $this->member->subscriptions()->latest('id')->first();
        $this->assertTrue($renewal->starts_at->equalTo($first->ends_at));
        $this->post(route('member-subscriptions.store', $this->member), $data)->assertSessionHasNoErrors();
        $this->assertSame(2, $this->member->subscriptions()->count());
        $this->assertSame(1, AuditLog::where('action', 'membership.renewed')->count());
    }

    public function test_repeated_assignment_and_changed_replay_are_handled(): void
    {
        $data = $this->data();
        $this->post(route('member-subscriptions.store', $this->member), $data)->assertSessionHasNoErrors();
        $this->post(route('member-subscriptions.store', $this->member), $data)->assertSessionHasNoErrors();
        $data['starts_on'] = '2026-11-04';
        $this->post(route('member-subscriptions.store', $this->member), $data)->assertSessionHasErrors('request_key');
        $this->assertSame(1, $this->member->subscriptions()->count());
    }

    public function test_expired_renewal_starts_today_and_initial_renewal_is_rejected(): void
    {
        $this->post(route('member-subscriptions.store', $this->member), $this->data(['operation' => 'renew']))->assertSessionHasErrors('operation');
        $old = $this->assign(['starts_on' => '2026-07-01']);
        $this->assertSame('Expired', $old->displayStatus());
        $renewal = $this->assign(['operation' => 'renew']);
        $this->assertSame('2026-10-05', $renewal->starts_at->setTimezone($this->gym->timezone)->toDateString());
    }

    public function test_suspend_reserves_dates_and_resume_preserves_expiry(): void
    {
        $period = $this->assign();
        $end = $period->ends_at;
        $route = route('member-subscriptions.update', [$this->member, $period]);
        $this->patch($route, ['action' => 'suspend'])->assertSessionHasNoErrors();
        $this->assertSame(MemberSubscriptionStatus::Suspended, $period->refresh()->status);
        $this->post(route('member-subscriptions.store', $this->member), $this->data())->assertSessionHasErrors('starts_on');
        $this->post(route('member-subscriptions.store', $this->member), $this->data(['operation' => 'renew']))->assertSessionHasErrors('operation');
        $this->patch($route, ['action' => 'resume'])->assertSessionHasNoErrors();
        $this->assertTrue($period->refresh()->ends_at->equalTo($end));
        $this->assertSame(MemberSubscriptionStatus::Active, $period->status);
    }

    public function test_cancellation_preserves_history_and_frees_period_but_cannot_resume(): void
    {
        $period = $this->assign();
        $route = route('member-subscriptions.update', [$this->member, $period]);
        $this->patch($route, ['action' => 'cancel'])->assertSessionHasNoErrors();
        $this->assertSame(MemberSubscriptionStatus::Cancelled, $period->refresh()->status);
        $this->patch($route, ['action' => 'resume'])->assertSessionHasErrors('action');
        $this->assign();
        $this->assertSame(2, $this->member->subscriptions()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'membership.cancelled', 'subject_id' => $period->id]);
    }

    public function test_archived_foreign_plans_inactive_members_and_bad_dates_are_rejected(): void
    {
        $other = Gym::factory()->create();
        $foreign = app(CurrentGym::class)->runAs($other, fn () => MembershipPlan::factory()->create());
        $this->post(route('member-subscriptions.store', $this->member), $this->data(['membership_plan_id' => $foreign->id]))->assertSessionHasErrors('membership_plan_id');
        $this->post(route('member-subscriptions.store', $this->member), $this->data(['starts_on' => '2026-02-30']))->assertSessionHasErrors('starts_on');
        $this->plan->forceFill(['is_active' => false])->save();
        $this->post(route('member-subscriptions.store', $this->member), $this->data())->assertSessionHasErrors('membership_plan_id');
        $this->plan->forceFill(['is_active' => true])->save();
        $this->member->is_active = false;
        $this->member->save();
        $this->post(route('member-subscriptions.store', $this->member), $this->data())->assertSessionHasErrors('membership_plan_id');
    }

    public function test_subscription_cannot_be_changed_through_another_member_url(): void
    {
        $period = $this->assign();
        $other = Member::factory()->create();
        $this->patch(route('member-subscriptions.update', [$other, $period]), ['action' => 'cancel'])->assertNotFound();
        $this->assertSame(MemberSubscriptionStatus::Active, $period->refresh()->status);
    }

    public function test_manager_cannot_access_other_home_branch_memberships(): void
    {
        $period = $this->assign();
        $this->gym->subscription->update(['plan' => Plan::MultiBranch]);
        $branch = Branch::factory()->create();
        $manager = User::factory()->memberOf($this->gym, GymRole::BranchManager)->create();
        $this->gym->users()->updateExistingPivot($manager->id, ['branch_id' => $branch->id]);
        $own = Member::factory()->create(['home_branch_id' => $branch->id]);
        $this->actingAs($manager)->get(route('member-subscriptions.index', $this->member))->assertNotFound();
        $this->post(route('member-subscriptions.store', $this->member), $this->data())->assertNotFound();
        $this->patch(route('member-subscriptions.update', [$own, $period]), ['action' => 'cancel'])->assertNotFound();
        $this->post(route('member-subscriptions.store', $own), $this->data())->assertSessionHasNoErrors();
    }

    public function test_read_only_branch_allows_history_but_blocks_writes(): void
    {
        $period = $this->assign();
        $this->gym->mainBranch()->forceFill(['is_active' => false])->save();
        $this->get(route('member-subscriptions.index', $this->member))->assertOk();
        $this->post(route('member-subscriptions.store', $this->member), $this->data(['operation' => 'renew']))->assertSessionHasErrors('membership_plan_id');
        $this->patch(route('member-subscriptions.update', [$this->member, $period]), ['action' => 'cancel'])->assertSessionHasErrors('membership_plan_id');
    }

    public function test_disabled_feature_and_coach_permissions_block_membership_routes(): void
    {
        $period = $this->assign();
        $coach = User::factory()->memberOf($this->gym, GymRole::Coach)->create();
        $this->actingAs($coach)->get(route('member-subscriptions.index', $this->member))->assertForbidden();
        $this->post(route('member-subscriptions.store', $this->member), $this->data())->assertForbidden();
        $this->patch(route('member-subscriptions.update', [$this->member, $period]), ['action' => 'cancel'])->assertForbidden();
        $this->actingAs($this->owner);
        Feature::for($this->gym)->deactivate('members');
        $this->get(route('member-subscriptions.index', $this->member))->assertNotFound();
        $this->post(route('member-subscriptions.store', $this->member), $this->data())->assertNotFound();
        $this->patch(route('member-subscriptions.update', [$this->member, $period]), ['action' => 'cancel'])->assertNotFound();
    }

    public function test_agreed_terms_are_immutable(): void
    {
        $period = $this->assign();
        $this->expectException(LogicException::class);
        $period->forceFill(['price_centavos' => 1])->save();
    }

    public function test_calendar_month_period_clamps_at_month_end(): void
    {
        $this->plan->update(['duration_unit' => 'months', 'duration_amount' => 1]);
        $period = $this->assign(['starts_on' => '2026-01-31']);
        $this->assertSame('2026-02-28 00:00:00', $period->ends_at->setTimezone($this->gym->timezone)->format('Y-m-d H:i:s'));
    }

    public function test_staff_can_assign_and_expired_periods_cannot_be_suspended(): void
    {
        $staff = User::factory()->memberOf($this->gym, GymRole::Staff)->create();
        $this->actingAs($staff);
        $period = $this->assign(['starts_on' => '2026-01-01']);
        $this->patch(route('member-subscriptions.update', [$this->member, $period]), ['action' => 'suspend'])->assertSessionHasErrors('action');
        $this->assign();
    }

    public function test_sample_seeder_is_blocked_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->expectException(\RuntimeException::class);
        app(IronHouseMemberSeeder::class)->run();
    }

    public function test_demo_subscriptions_include_active_expired_and_suspended_examples(): void
    {
        $gym = app(StartDemo::class)->handle()->gyms()->sole();
        app(CurrentGym::class)->runAs($gym, function () {
            $this->assertEqualsCanonicalizing(['Active', 'Expired', 'Suspended'], MemberSubscription::get()->map->displayStatus()->all());
        });
    }

    public function test_iron_house_samples_have_requested_mix_and_reruns_preserve_edits(): void
    {
        $this->gym->update(['slug' => 'iron-house-fitness']);
        $this->seed(IronHouseMemberSeeder::class);
        $samples = Member::where('email', 'like', 'sample-member-%@ironhouse.example.invalid')->get();
        $this->assertCount(10, $samples);
        $this->assertSame(3, $samples->where('is_active', false)->count());
        $this->assertSame(3, MemberSubscription::where('ends_at', '<', now())->count());
        $sample = $samples->first();
        $sample->update(['name' => 'Preserved']);
        $this->seed(IronHouseMemberSeeder::class);
        $this->assertSame('Preserved', $sample->refresh()->name);
        $this->assertSame(10, MemberSubscription::count());
        $this->assertTrue($samples->every(fn ($member) => $member->privacy_consented_at === null));
    }
}
