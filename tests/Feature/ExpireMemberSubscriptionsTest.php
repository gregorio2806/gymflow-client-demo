<?php

namespace Tests\Feature;

use App\Actions\ManageMemberSubscription;
use App\Enums\GymRole;
use App\Enums\MemberSubscriptionStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\MemberSubscription;
use App\Models\User;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExpireMemberSubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-05 00:00:00', 'Asia/Manila'));
    }

    private function period(Gym $gym, string $end, string $status = 'active', ?int $branchId = null): MemberSubscription
    {
        return app(CurrentGym::class)->runAs($gym, function () use ($end, $status, $branchId) {
            $member = Member::factory()->create($branchId ? ['home_branch_id' => $branchId] : []);
            $plan = MembershipPlan::factory()->create();

            return tap(new MemberSubscription, fn ($period) => $period->forceFill([
                'member_id' => $member->id, 'membership_plan_id' => $plan->id,
                'plan_name' => $plan->name, 'price_centavos' => $plan->price_centavos,
                'duration_amount' => 1, 'duration_unit' => 'months',
                'starts_at' => now()->subMonth(), 'ends_at' => CarbonImmutable::parse($end, 'Asia/Manila')->utc(),
                'status' => $status, 'operation' => 'assign', 'request_key' => (string) Str::uuid(),
            ])->save());
        });
    }

    public function test_end_boundary_expires_active_and_suspended_only_and_preserves_profiles_and_terms(): void
    {
        $gym = Gym::factory()->create();
        $active = $this->period($gym, '2026-10-05');
        $suspended = $this->period($gym, '2026-10-04', 'suspended');
        $cancelled = $this->period($gym, '2026-10-04', 'cancelled');
        $future = $this->period($gym, '2026-10-05 00:00:01');
        $already = $this->period($gym, '2026-10-04', 'expired');
        $before = $active->getAttributes();
        app(CurrentGym::class)->forget();

        $this->artisan('memberships:expire')->expectsOutput('Expired 2 member subscription(s).')->assertSuccessful();
        app(CurrentGym::class)->runAs($gym, function () use ($active, $suspended, $cancelled, $future, $already, $before) {
            $this->assertSame(MemberSubscriptionStatus::Expired, $active->refresh()->status);
            $this->assertSame(MemberSubscriptionStatus::Expired, $suspended->refresh()->status);
            $this->assertSame(MemberSubscriptionStatus::Cancelled, $cancelled->refresh()->status);
            $this->assertSame(MemberSubscriptionStatus::Active, $future->refresh()->status);
            $this->assertSame(MemberSubscriptionStatus::Expired, $already->refresh()->status);
            foreach (['starts_at', 'ends_at', 'plan_name', 'price_centavos', 'request_key', 'member_id'] as $field) {
                $this->assertEquals($before[$field], $active->getAttributes()[$field]);
            }
            $this->assertSame(5, Member::where('is_active', true)->count());
            $logs = AuditLog::where('action', 'membership.expired')->get();
            $this->assertCount(2, $logs);
            $this->assertEqualsCanonicalizing([$active->id, $suspended->id], $logs->pluck('subject_id')->all());
            foreach ($logs as $log) {
                $this->assertNull($log->user_id);
                $this->assertSame('expired', $log->changes['after']['status']);
            }
        });
        $this->assertFalse(app(CurrentGym::class)->check());
        $this->artisan('memberships:expire')->expectsOutput('Expired 0 member subscription(s).')->assertSuccessful();
        app(CurrentGym::class)->runAs($gym, fn () => $this->assertSame(2, AuditLog::where('action', 'membership.expired')->count()));
    }

    public function test_processing_crosses_chunk_boundaries_and_restores_manager_context(): void
    {
        $gym = Gym::factory()->create();
        $other = Gym::factory()->create();
        app(CurrentGym::class)->set($gym);
        $branch = Branch::factory()->create();
        for ($i = 0; $i < 105; $i++) {
            $this->period($gym, '2026-10-04', $i % 2 ? 'suspended' : 'active', $branch->id);
        }
        $this->period($other, '2026-10-04');
        $this->period($other, '2026-11-01');
        $mainBranchId = $gym->mainBranch()->id;
        app(CurrentGym::class)->set($gym, GymRole::BranchManager, $mainBranchId);
        $this->artisan('memberships:expire')->expectsOutput('Expired 106 member subscription(s).')->assertSuccessful();
        $this->assertSame($gym->id, app(CurrentGym::class)->id());
        $this->assertSame(GymRole::BranchManager, app(CurrentGym::class)->role());
        $this->assertSame($mainBranchId, app(CurrentGym::class)->branchId());
        app(CurrentGym::class)->runAs($gym, function () {
            $this->assertSame(105, MemberSubscription::where('status', 'expired')->count());
            $this->assertSame(105, AuditLog::where('action', 'membership.expired')->count());
        });
        app(CurrentGym::class)->runAs($other, function () {
            $this->assertSame(1, MemberSubscription::where('status', 'expired')->count());
            $this->assertSame(1, MemberSubscription::where('status', 'active')->count());
            $this->assertSame(1, AuditLog::where('action', 'membership.expired')->count());
        });
    }

    public function test_audit_failure_rolls_back_expiration_and_restores_context(): void
    {
        $gym = Gym::factory()->create();
        $period = $this->period($gym, '2026-10-04');
        app(CurrentGym::class)->forget();
        AuditLog::creating(fn () => throw new \RuntimeException('Audit unavailable'));
        try {
            $this->artisan('memberships:expire')->run();
            $this->fail('Expected audit failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }
        $this->assertFalse(app(CurrentGym::class)->check());
        app(CurrentGym::class)->runAs($gym, fn () => $this->assertSame(MemberSubscriptionStatus::Active, $period->refresh()->status));
    }

    public function test_cancelled_after_selection_is_rechecked_before_expiration(): void
    {
        $gym = Gym::factory()->create();
        $period = $this->period($gym, '2026-10-04');
        $cancelled = false;
        MemberSubscription::retrieved(function ($candidate) use ($period, &$cancelled) {
            if (! $cancelled && $candidate->id === $period->id && ! array_key_exists('status', $candidate->getAttributes())) {
                $cancelled = true;
                $current = MemberSubscription::findOrFail($period->id);
                $current->status = MemberSubscriptionStatus::Cancelled;
                $current->save();
            }
        });
        $this->artisan('memberships:expire')->expectsOutput('Expired 0 member subscription(s).')->assertSuccessful();
        app(CurrentGym::class)->runAs($gym, function () use ($period) {
            $this->assertSame(MemberSubscriptionStatus::Cancelled, $period->refresh()->status);
            $this->assertSame(0, AuditLog::where('action', 'membership.expired')->count());
        });
    }

    public function test_gym_chunking_and_cutoff_remain_stable_when_the_clock_advances(): void
    {
        $gyms = Gym::factory()->count(101)->create();
        $gym = $gyms->last();
        $ended = $this->period($gym, '2026-10-05');
        $future = $this->period($gym, '2026-10-05 00:00:01');
        $advanced = false;
        MemberSubscription::retrieved(function () use (&$advanced) {
            if (! $advanced) {
                $advanced = true;
                $this->travelTo(CarbonImmutable::parse('2026-10-06', 'Asia/Manila'));
            }
        });
        $this->artisan('memberships:expire')->expectsOutput('Expired 1 member subscription(s).')->assertSuccessful();
        app(CurrentGym::class)->runAs($gym, function () use ($ended, $future) {
            $this->assertSame(MemberSubscriptionStatus::Expired, $ended->refresh()->status);
            $this->assertSame(MemberSubscriptionStatus::Active, $future->refresh()->status);
        });
    }

    public function test_expired_period_can_be_renewed_but_not_resumed(): void
    {
        $gym = Gym::factory()->create();
        $period = $this->period($gym, '2026-10-04');
        $this->artisan('memberships:expire')->assertSuccessful();
        app(CurrentGym::class)->set($gym);
        $owner = User::factory()->memberOf($gym, GymRole::Owner)->create();
        $this->actingAs($owner);
        $member = $period->member;
        $renewal = app(ManageMemberSubscription::class)->assign($member, [
            'membership_plan_id' => $period->membership_plan_id, 'operation' => 'renew', 'request_key' => (string) Str::uuid(),
        ]);
        $this->assertTrue($renewal->starts_at->equalTo(now()));
        $this->assertSame('Expired', $period->refresh()->displayStatus());
        $this->expectException(ValidationException::class);
        app(ManageMemberSubscription::class)->change($member, $period, 'resume');
    }

    public function test_command_is_scheduled_daily_at_manila_midnight_with_overlap_protection(): void
    {
        $event = collect(Schedule::events())->first(fn ($event) => str_contains($event->command ?? '', 'memberships:expire'));
        $this->assertNotNull($event);
        $this->assertSame('0 0 * * *', $event->expression);
        $this->assertSame('Asia/Manila', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
    }
}
