<?php

namespace Tests\Feature\Tenancy;

use App\Enums\GymRole;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\CoachSchedule;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\MemberSubscription;
use App\Models\User;
use App\Models\WorkoutRoutine;
use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BranchViewTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private Branch $other;

    private User $owner;

    private Member $mainMember;

    private Member $otherMember;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gym = Gym::factory()->create();
        $this->gym->subscription->update(['plan' => 'multi_branch']);
        app(CurrentGym::class)->set($this->gym);
        $this->other = Branch::create(['name' => 'Other branch']);
        $this->owner = User::factory()->memberOf($this->gym, GymRole::Owner)->create();
        $this->mainMember = Member::factory()->create();
        $this->otherMember = Member::factory()->create(['home_branch_id' => $this->other->id]);
        $plan = MembershipPlan::factory()->create();
        foreach ([$this->mainMember, $this->otherMember] as $member) {
            (new MemberSubscription)->forceFill(['member_id' => $member->id, 'membership_plan_id' => $plan->id, 'plan_name' => 'Test plan', 'duration_amount' => 30, 'duration_unit' => 'days', 'price_centavos' => 10000, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(20), 'status' => 'active', 'operation' => 'assign', 'request_key' => (string) Str::uuid()])->save();
            (new AttendanceRecord)->forceFill(['member_id' => $member->id, 'branch_id' => $member->home_branch_id, 'checked_in_at' => now(), 'source' => 'manual'])->save();
        }
        $this->actingAs($this->owner);
    }

    private function select(?int $id, string $target = '/dashboard'): void
    {
        $this->put(route('branch-view.update'), ['branch_id' => $id, 'return_to' => $target])->assertStatus(303)->assertRedirect($target);
    }

    public function test_selection_stays_on_page_filters_reporting_and_keeps_owner_members_gym_wide(): void
    {
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('membershipCounts.active', 2));
        $this->select($this->other->id, '/members?search=');
        $this->get(route('members.index'))->assertInertia(fn (Assert $page) => $page->where('selectedBranchId', $this->other->id)->has('members.data', 2)->where('statistics.total', 2)->where('statistics.active', 2));
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('membershipCounts.active', 1));
        $this->select(null);
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('selectedBranchId', null)->where('membershipCounts.active', 2));
        $this->get(route('members.index'))->assertInertia(fn (Assert $page) => $page->has('members.data', 2));
    }

    public function test_selection_filters_branch_records_but_check_in_finds_visiting_members(): void
    {
        $this->select($this->other->id);
        $this->get(route('attendance.index'))->assertInertia(fn (Assert $page) => $page->where('summary.checkIns', 1)->where('filters.branch_id', $this->other->id));
        $this->get(route('payments.overview'))->assertInertia(fn (Assert $page) => $page->where('filters.branch_id', $this->other->id)->has('balances.data', 1));
        $this->get(route('reports.index'))->assertInertia(fn (Assert $page) => $page->where('filters.branch_id', $this->other->id)->where('summary.checkIns', 1));
        // The local member already checked in today; the main-branch member can still visit here.
        $this->getJson(route('check-in.search'))->assertJsonCount(1, 'members.data')->assertJsonPath('members.data.0.id', $this->mainMember->id);
    }

    public function test_page_branch_filters_update_saved_selection(): void
    {
        $this->get(route('attendance.index', ['branch_id' => $this->other->id]))->assertOk();
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('selectedBranchId', $this->other->id));
        $this->get(route('attendance.index', ['branch_id' => '']))->assertOk();
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('selectedBranchId', null));
    }

    public function test_selection_filters_workouts_calendar_days_and_team(): void
    {
        $coach = User::factory()->memberOf($this->gym, GymRole::Coach)->create();
        $staff = User::factory()->memberOf($this->gym, GymRole::Staff)->create();
        $this->gym->users()->updateExistingPivot($staff->id, ['branch_id' => $this->other->id]);
        foreach ([$this->gym->mainBranch(), $this->other] as $branch) {
            (new WorkoutRoutine)->forceFill(['branch_id' => $branch->id, 'name' => $branch->name, 'days' => [], 'created_by' => $this->owner->id, 'request_key' => (string) Str::uuid(), 'revision' => 1])->save();
            (new CoachSchedule)->forceFill(['coach_id' => $coach->id, 'branch_id' => $branch->id, 'title' => $branch->name, 'starts_at' => now()->startOfDay()->addHours(2), 'ends_at' => now()->startOfDay()->addHours(3)])->save();
        }
        $this->select($this->other->id);
        $this->get(route('workout-routines.index'))->assertInertia(fn (Assert $page) => $page->has('routines.data', 1)->where('routines.data.0.branch', $this->other->name));
        $this->get(route('coach-calendar.index', ['coach_id' => $coach->id]))->assertInertia(fn (Assert $page) => $page->has('schedules.data', 1)->where('schedules.data.0.branch', $this->other->name)->where('days', fn ($days) => collect($days)->sum('count') === 1));
        $this->get(route('staff.index'))->assertInertia(fn (Assert $page) => $page->has('members', 1)->where('members.0.id', $staff->id));
    }

    public function test_selection_does_not_change_member_edit_permissions_or_gym_catalogs(): void
    {
        $this->select($this->other->id);
        $this->get(route('members.edit', $this->mainMember))->assertOk();
        $this->get(route('membership-plans.index'))->assertInertia(fn (Assert $page) => $page->has('plans.data', 1));
    }

    public function test_managers_cannot_change_branch_or_select_all(): void
    {
        $manager = User::factory()->memberOf($this->gym, GymRole::BranchManager)->create();
        $this->gym->users()->updateExistingPivot($manager->id, ['branch_id' => $this->other->id]);
        $this->actingAs($manager);
        foreach ([null, $this->other->id, $this->gym->mainBranch()->id] as $id) {
            $this->put(route('branch-view.update'), ['branch_id' => $id, 'return_to' => '/dashboard'])->assertForbidden();
        }
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('selectedBranchId', $this->other->id)->where('membershipCounts.active', 1));
    }

    public function test_foreign_or_stale_session_selection_is_discarded(): void
    {
        $foreign = Gym::factory()->create()->mainBranch();
        $this->put(route('branch-view.update'), ['branch_id' => $foreign->id, 'return_to' => '/dashboard'])->assertNotFound();
        $this->withSession(['view_branch.'.$this->gym->id => $foreign->id])->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('selectedBranchId', null)->where('membershipCounts.active', 2));
    }

    public function test_selection_is_separate_per_gym_and_run_as_restores_it(): void
    {
        $this->select($this->other->id);
        $second = Gym::factory()->create();
        $this->owner->gyms()->attach($second, ['role' => GymRole::Owner, 'is_active' => true]);
        $this->put(route('current-gym.update'), ['gym_id' => $second->id]);
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('selectedBranchId', null));
        $this->put(route('current-gym.update'), ['gym_id' => $this->gym->id]);
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('selectedBranchId', $this->other->id));
        $current = app(CurrentGym::class);
        $current->runAs($second, fn () => $this->assertNull($current->viewBranchId()));
        $this->assertSame($this->other->id, $current->viewBranchId());
    }

    public function test_return_target_cannot_redirect_to_an_external_site(): void
    {
        foreach (['https://example.com', '//example.com', '/\\example.com', "/dashboard\r\nLocation: https://example.com"] as $target) {
            $this->put(route('branch-view.update'), ['branch_id' => null, 'return_to' => $target])->assertStatus(422);
        }
    }
}
