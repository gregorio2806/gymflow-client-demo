<?php

namespace Tests\Feature\Dashboard;

use App\Enums\GymRole;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberQrToken;
use App\Models\MembershipPlan;
use App\Models\MemberSubscription;
use App\Models\User;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature;
use RuntimeException;
use Tests\TestCase;

class CheckInTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private Member $member;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-06 04:00:00', 'UTC'));
        $this->gym = Gym::factory()->create();
        app(CurrentGym::class)->set($this->gym);
        $this->staff = User::factory()->memberOf($this->gym, GymRole::Staff)->create();
        $this->member = Member::factory()->create(['name' => 'Alex Sample']);
        $this->period($this->member);
        $this->actingAs($this->staff);
    }

    private function period(Member $member, string $status = 'active', ?string $start = null, ?string $end = null): MemberSubscription
    {
        $plan = MembershipPlan::factory()->create();
        $period = new MemberSubscription;
        $period->forceFill(['member_id' => $member->id, 'membership_plan_id' => $plan->id, 'plan_name' => $plan->name, 'duration_amount' => 30, 'duration_unit' => 'days', 'price_centavos' => 100, 'starts_at' => $start ?? now()->subDay(), 'ends_at' => $end ?? now()->addDays(29), 'status' => $status, 'operation' => 'assign', 'request_key' => (string) Str::uuid()])->save();

        return $period;
    }

    private function checkIn(array $data = [])
    {
        return $this->postJson(route('check-in.store'), array_replace(['member_id' => $this->member->id, 'branch_id' => $this->gym->mainBranch()->id], $data));
    }

    private function qr(): string
    {
        return $this->postJson(route('member-qr.store', $this->member), ['operation' => 'generate'])->assertOk()->json('payload');
    }

    public function test_selection_hides_today_check_ins_at_selected_branch_and_returns_them_next_day(): void
    {
        $main = $this->gym->mainBranch();
        $other = Branch::factory()->create(['is_main' => false]);
        $this->travelTo(CarbonImmutable::parse('2026-10-05 16:00:00', 'UTC'));
        $this->checkIn()->assertOk();
        foreach (['', 'Alex'] as $search) {
            $this->getJson(route('check-in.search', ['search' => $search, 'branch_id' => $main->id]))
                ->assertOk()->assertJsonPath('members.total', 0);
        }
        $this->getJson(route('check-in.search', ['branch_id' => $other->id]))
            ->assertOk()->assertJsonPath('members.data.0.id', $this->member->id);
        $foreignGym = Gym::factory()->create();
        $this->getJson(route('check-in.search', ['branch_id' => $foreignGym->mainBranch()->id]))->assertNotFound();
        $this->travelTo(CarbonImmutable::parse('2026-10-06 16:00:00', 'UTC'));
        $this->getJson(route('check-in.search', ['branch_id' => $main->id]))
            ->assertOk()->assertJsonPath('members.data.0.id', $this->member->id);
    }

    public function test_manual_check_in_stores_server_utc_time_branch_actor_and_audit(): void
    {
        $this->checkIn(['gym_id' => 999, 'recorded_by' => 999, 'checked_in_at' => '2000-01-01'])->assertOk()->assertJson(['memberName' => 'Alex Sample', 'checkedInAt' => 'Oct 6, 2026 12:00 PM']);
        $record = AttendanceRecord::sole();
        $this->assertSame($this->gym->id, $record->gym_id);
        $this->assertSame($this->staff->id, $record->recorded_by);
        $this->assertSame($this->gym->mainBranch()->id, $record->branch_id);
        $this->assertSame('2026-10-06 04:00:00', $record->checked_in_at->format('Y-m-d H:i:s'));
        $this->assertSame('manual', $record->source);
        $this->assertSame('attendance.checked-in', AuditLog::sole()->action);
    }

    public function test_member_search_and_check_in_work_without_a_mobile_number(): void
    {
        $this->member->update(['mobile' => '']);
        foreach (['', 'Alex'] as $search) {
            $this->getJson(route('check-in.search', ['search' => $search]))->assertOk()
                ->assertJsonPath('members.data.0.id', $this->member->id)
                ->assertJsonPath('members.data.0.mobileHint', '');
        }
        $this->checkIn()->assertOk()->assertJsonPath('memberName', $this->member->name);
        $this->assertDatabaseCount('attendance_records', 1);
    }

    public function test_qr_check_in_accepts_current_card_and_rejects_replaced_revoked_expired_and_portal_tokens(): void
    {
        $payload = $this->qr();
        $this->checkIn(['member_id' => null, 'token' => $payload])->assertOk();
        $this->assertSame('qr', AttendanceRecord::sole()->source);
        $replacement = $this->postJson(route('member-qr.store', $this->member), ['operation' => 'reissue'])->json('payload');
        $this->checkIn(['member_id' => null, 'token' => $payload])->assertUnprocessable()->assertJsonValidationErrors('token');
        $token = MemberQrToken::whereNull('revoked_at')->sole();
        $token->forceFill(['expires_at' => now()])->save();
        $this->checkIn(['member_id' => null, 'token' => $replacement])->assertUnprocessable()->assertJsonValidationErrors('token');
        $portalRaw = Str::random(64);
        $portal = new MemberQrToken;
        $portal->forceFill(['member_id' => $this->member->id, 'purpose' => 'portal', 'token_hash' => hash('sha256', $portalRaw)])->save();
        $this->checkIn(['member_id' => null, 'token' => 'gym-attendance:'.$portalRaw])->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->assertSame(1, AttendanceRecord::count());
    }

    public function test_foreign_qr_is_rejected_even_when_request_uses_local_branch(): void
    {
        $payload = $this->qr();
        $foreign = Gym::factory()->create();
        $foreignStaff = User::factory()->memberOf($foreign)->create();
        $this->actingAs($foreignStaff)->postJson(route('check-in.store'), ['token' => $payload, 'branch_id' => $foreign->mainBranch()->id])->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_inactive_and_non_current_memberships_are_rejected_but_expiring_is_valid(): void
    {
        $this->member->forceFill(['is_active' => false])->save();
        $this->checkIn()->assertUnprocessable()->assertJsonValidationErrors('member_id');
        $this->member->forceFill(['is_active' => true])->save();
        $this->member->subscriptions()->delete();
        foreach (['suspended', 'cancelled', 'expired'] as $status) {
            $period = $this->period($this->member, $status);
            $this->checkIn()->assertUnprocessable()->assertJsonValidationErrors('member_id');
            $period->delete();
        }
        $period = $this->period($this->member, 'active', '2026-10-07 00:00:00', '2026-11-07 00:00:00');
        $this->checkIn()->assertUnprocessable();
        $period->delete();
        $this->period($this->member, 'active', '2026-10-06 04:00:00', '2026-10-06 16:00:00');
        $this->checkIn()->assertOk();
        $this->travelTo(CarbonImmutable::parse('2026-10-06 16:00:00', 'UTC'));
        $this->checkIn()->assertUnprocessable();
    }

    public function test_check_in_survives_member_capacity_and_read_only_branch_downgrades(): void
    {
        config(['plans.plans.starter.limits.active_members' => 0]);
        $this->gym->mainBranch()->forceFill(['is_active' => false])->save();
        $this->checkIn()->assertOk();
    }

    public function test_manager_checks_gym_wide_members_by_search_or_qr_only_into_assigned_branch(): void
    {
        $this->gym->subscription->update(['plan' => 'multi_branch']);
        $manager = User::factory()->memberOf($this->gym, GymRole::BranchManager)->create();
        $this->gym->users()->updateExistingPivot($manager->id, ['branch_id' => $this->gym->mainBranch()->id]);
        $otherBranch = Branch::factory()->create();
        $otherMember = Member::factory()->create(['name' => 'Visiting Sample', 'home_branch_id' => $otherBranch->id]);
        $this->period($otherMember);
        $payload = $this->postJson(route('member-qr.store', $otherMember), ['operation' => 'generate'])->assertOk()->json('payload');
        $this->actingAs($manager)->get(route('check-in.index'))->assertInertia(fn (Assert $page) => $page->has('branches', 1));
        $this->getJson(route('check-in.search', ['search' => 'Visiting']))
            ->assertOk()->assertJsonPath('members.data.0.id', $otherMember->id)->assertJsonPath('members.data.0.membershipStatus', 'Active');
        $this->getJson(route('check-in.search'))->assertOk()->assertJsonCount(2, 'members.data');
        $this->checkIn()->assertOk();
        $this->checkIn(['member_id' => $otherMember->id])->assertOk();
        $this->travel(6)->minutes();
        $this->checkIn(['member_id' => null, 'token' => $payload])->assertOk();
        $record = AttendanceRecord::where('source', 'qr')->sole();
        $this->assertSame($otherMember->id, $record->member_id);
        $this->assertSame($this->gym->mainBranch()->id, $record->branch_id);
        $this->assertSame($manager->id, $record->recorded_by);
        $this->assertSame($otherBranch->id, $record->member->home_branch_id);
        $this->get(route('members.show', $otherMember))->assertNotFound();
        $this->checkIn(['branch_id' => $otherBranch->id])->assertNotFound();
        $this->getJson(route('check-in.search', ['branch_id' => $otherBranch->id]))->assertNotFound();
        $this->assertSame(GymRole::BranchManager, app(CurrentGym::class)->role());
    }

    public function test_manager_visiting_member_access_preserves_tenant_and_membership_checks(): void
    {
        $this->gym->subscription->update(['plan' => 'multi_branch']);
        $other = Branch::factory()->create();
        $this->member->update(['home_branch_id' => $other->id]);
        $period = $this->member->subscriptions()->sole();
        $payload = $this->qr();
        $foreign = Gym::factory()->create();
        $foreignBranchId = $foreign->mainBranch()->id;
        $foreignMember = app(CurrentGym::class)->runAs($foreign, fn () => Member::factory()->create(['name' => 'Foreign Sample']));
        $foreignRaw = Str::random(64);
        app(CurrentGym::class)->runAs($foreign, fn () => (new MemberQrToken)->forceFill(['member_id' => $foreignMember->id, 'purpose' => 'attendance', 'token_hash' => hash('sha256', $foreignRaw)])->save());
        $this->actingAs(User::factory()->memberOf($this->gym, GymRole::BranchManager)->create());

        $this->getJson(route('check-in.search', ['search' => 'Foreign']))->assertOk()->assertJsonCount(0, 'members.data');
        $this->checkIn(['member_id' => $foreignMember->id])->assertNotFound();
        $this->checkIn(['member_id' => null, 'token' => 'gym-attendance:'.$foreignRaw])->assertUnprocessable();
        $this->checkIn(['branch_id' => $foreignBranchId])->assertNotFound();
        $period->forceFill(['status' => 'suspended'])->save();
        $this->checkIn()->assertUnprocessable()->assertJsonValidationErrors('member_id');
        $this->checkIn(['member_id' => null, 'token' => $payload])->assertUnprocessable()->assertJsonValidationErrors('member_id');
        $this->assertDatabaseCount('attendance_records', 0);
        $this->assertSame(GymRole::BranchManager, app(CurrentGym::class)->role());
    }

    public function test_owner_and_staff_can_check_members_into_another_gym_branch(): void
    {
        $branch = Branch::factory()->create();
        $this->checkIn(['branch_id' => $branch->id])->assertOk();
        $this->assertSame($branch->id, AttendanceRecord::sole()->branch_id);
    }

    public function test_coach_and_disabled_feature_cannot_access_check_in_or_search(): void
    {
        $coach = User::factory()->memberOf($this->gym, GymRole::Coach)->create();
        $this->actingAs($coach)->get(route('check-in.index'))->assertForbidden();
        $this->getJson(route('check-in.search', ['search' => 'Alex']))->assertForbidden();
        $this->checkIn()->assertForbidden();
        Feature::for($this->gym)->deactivate('qr-attendance');
        $this->actingAs($this->staff)->get(route('check-in.index'))->assertNotFound();
        $this->getJson(route('check-in.search', ['search' => 'Alex']))->assertNotFound();
        $this->checkIn()->assertNotFound();
    }

    public function test_empty_search_lists_only_active_members_alphabetically_with_pagination_and_tenant_isolation(): void
    {
        $this->member->update(['name' => 'zulu Sample']);
        Member::factory()->create(['name' => 'Alpha Sample']);
        Member::factory()->inactive()->create(['name' => 'Aardvark Inactive']);
        Member::factory()->count(24)->create(['name' => 'Middle Sample']);
        $foreign = Gym::factory()->create();
        app(CurrentGym::class)->runAs($foreign, fn () => Member::factory()->create(['name' => 'Aardvark Foreign']));

        $response = $this->getJson(route('check-in.search'))->assertOk()
            ->assertJsonCount(25, 'members.data')
            ->assertJsonPath('members.total', 26)
            ->assertJsonPath('members.data.0.name', 'Alpha Sample')
            ->assertJsonMissingPath('members.data.0.mobile');
        $this->assertTrue(collect($response->json('members.data'))->every(fn ($member) => $member['is_active']));
        $this->getJson(route('check-in.search', ['page' => 2, 'search' => '']))->assertOk()
            ->assertJsonCount(1, 'members.data')->assertJsonPath('members.data.0.name', 'zulu Sample');
        $this->getJson(route('check-in.search', ['search' => 'A']))->assertUnprocessable();
    }

    public function test_search_is_paginated_literal_and_contains_only_minimal_props(): void
    {
        Member::factory()->count(26)->create(['name' => 'Sample Search']);
        $this->getJson(route('check-in.search', ['search' => 'Sample']))->assertJsonCount(25, 'members.data')->assertJsonPath('members.total', 27)->assertJsonMissingPath('members.data.0.mobile')->assertJsonMissingPath('members.data.0.email')->assertJsonMissingPath('members.data.0.subscriptions');
        $this->getJson(route('check-in.search', ['search' => 'Sample', 'page' => 2]))->assertJsonCount(2, 'members.data');
        $this->getJson(route('check-in.search', ['search' => '%%']))->assertJsonPath('members.total', 0);
        $this->getJson(route('check-in.search', ['search' => 'A']))->assertUnprocessable();
    }

    public function test_check_in_and_attendance_history_are_separate_entry_points(): void
    {
        $this->get(route('check-in.index'))->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/attendance/CheckIn')->has('branches')
            ->where('defaultBranchId', $this->gym->mainBranch()->id));
        $this->get(route('attendance.index'))->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/attendance/History')->has('summary')->has('insights')->has('attendance'));
        $member = Member::factory()->create(['id' => 98765, 'mobile' => '09000000000']);
        $this->getJson(route('check-in.search', ['search' => '98765']))->assertOk()->assertJsonPath('members.total', 1)->assertJsonPath('members.data.0.id', $member->id);
    }

    public function test_token_and_member_are_mutually_exclusive_and_branch_is_required(): void
    {
        $this->checkIn(['token' => $this->qr()])->assertUnprocessable();
        $this->checkIn(['branch_id' => null])->assertUnprocessable()->assertJsonValidationErrors('branch_id');
        $this->checkIn(['member_id' => null, 'token' => 'bad'])->assertUnprocessable();
    }

    public function test_failed_audit_rolls_back_check_in(): void
    {
        AuditLog::creating(fn () => throw new RuntimeException('Audit unavailable'));
        $this->withoutExceptionHandling();
        try {
            $this->checkIn();
            $this->fail('Expected failed audit.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
            $this->assertSame(0, AttendanceRecord::count());
        } finally {
            AuditLog::flushEventListeners();
        }
    }

    public function test_duplicates_across_manual_and_qr_sources_return_original_time_without_extra_audit(): void
    {
        $payload = $this->qr();
        $this->checkIn()->assertJsonPath('duplicate', false);
        $this->travel(4)->minutes();
        $this->checkIn(['member_id' => null, 'token' => $payload])->assertOk()->assertJsonPath('duplicate', true)->assertJsonPath('checkedInAt', 'Oct 6, 2026 12:00 PM');
        $this->assertSame(1, AttendanceRecord::count());
        $this->assertSame(1, AuditLog::where('action', 'attendance.checked-in')->count());
        $this->travel(1)->minutes();
        $this->checkIn()->assertOk()->assertJsonPath('duplicate', false);
        $this->assertSame(2, AttendanceRecord::count());
    }

    public function test_duplicate_window_is_configurable_and_scoped_to_member_and_actual_branch(): void
    {
        config(['attendance.duplicate_window_minutes' => 10]);
        $this->checkIn()->assertJsonPath('duplicate', false);
        $branch = Branch::factory()->create();
        $this->checkIn(['branch_id' => $branch->id])->assertJsonPath('duplicate', false);
        $other = Member::factory()->create();
        $this->period($other);
        $this->checkIn(['member_id' => $other->id])->assertJsonPath('duplicate', false);
        $this->travel(6)->minutes();
        $this->checkIn()->assertJsonPath('duplicate', true);
        config(['attendance.duplicate_window_minutes' => 0]);
        $this->checkIn()->assertJsonPath('duplicate', false);
        $this->assertSame(4, AttendanceRecord::count());
    }

    public function test_request_limits_are_per_gym_user_and_search_is_limited(): void
    {
        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->checkIn()->assertOk();
        }
        $this->checkIn()->assertTooManyRequests();
        $this->actingAs(User::factory()->memberOf($this->gym)->create());
        $this->checkIn()->assertOk();
        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->getJson(route('check-in.search', ['search' => 'Alex']))->assertOk();
        }
        $this->getJson(route('check-in.search', ['search' => 'Alex']))->assertTooManyRequests();
    }

    public function test_failed_qr_lookups_are_limited_without_blocking_manual_fallback(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->checkIn(['member_id' => null, 'token' => 'gym-attendance:'.Str::random(64)])->assertUnprocessable();
        }
        $this->checkIn(['member_id' => null, 'token' => 'gym-attendance:'.Str::random(64)])->assertTooManyRequests();
        $this->checkIn()->assertOk();
        $this->travel(61)->seconds();
        $this->checkIn(['member_id' => null, 'token' => 'gym-attendance:'.Str::random(64)])->assertUnprocessable();
    }

    public function test_failed_qr_counter_persists_with_production_database_cache(): void
    {
        $limiter = new RateLimiter(Cache::store('database'));
        foreach (['check-in', 'attendance-search'] as $name) {
            $limiter->for($name, \Illuminate\Support\Facades\RateLimiter::limiter($name));
        }
        \Illuminate\Support\Facades\RateLimiter::swap($limiter);
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->checkIn(['member_id' => null, 'token' => 'gym-attendance:'.Str::random(64)])->assertUnprocessable();
        }
        $this->checkIn(['member_id' => null, 'token' => 'gym-attendance:'.Str::random(64)])->assertTooManyRequests();
    }

    public function test_tenant_context_is_resolved_before_the_check_in_rate_limiter(): void
    {
        $resolvedGym = null;
        \Illuminate\Support\Facades\RateLimiter::for('check-in', function () use (&$resolvedGym) {
            $resolvedGym = app(CurrentGym::class)->id();

            return Limit::none();
        });
        app(CurrentGym::class)->forget();
        $this->checkIn()->assertOk();
        $this->assertSame($this->gym->id, $resolvedGym);
    }
}
