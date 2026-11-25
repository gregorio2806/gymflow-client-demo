<?php

namespace Tests\Feature\Dashboard;

use App\Enums\GymRole;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\User;
use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlanDowngradeTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->gym = Gym::factory()->create();
        $this->gym->subscription->update(['plan' => 'pro']);
        $this->owner = User::factory()->memberOf($this->gym, GymRole::Owner)->create();
        app(CurrentGym::class)->set($this->gym);
    }

    private function staff(int $count): array
    {
        $users = [];
        for ($index = 0; $index < $count; $index++) {
            $user = User::factory()->memberOf($this->gym, GymRole::Staff)->create();
            $this->gym->users()->updateExistingPivot($user->id, ['created_at' => now()->subDays($count - $index)]);
            $users[] = $user;
        }

        return $users;
    }

    public function test_downgrade_keeps_longest_serving_staff_and_preserves_all_records(): void
    {
        $staff = $this->staff(5);
        $this->gym->subscription->update(['plan' => 'starter']);
        $this->assertNotNull($staff[0]->roleIn($this->gym));
        $this->assertNotNull($staff[1]->roleIn($this->gym));
        $this->assertNull($staff[2]->roleIn($this->gym));
        $this->assertSame(6, $this->gym->users()->count());
        $this->assertSame(GymRole::Owner, $this->owner->roleIn($this->gym));
        $this->assertTrue($this->gym->refresh()->needs_staff_selection);
        $this->assertSame(3, AuditLog::where('action', 'staff.deactivated')->count());
        $this->actingAs($this->owner)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('planReview.staff', true)->where('planReview.staffLimit', 2));
    }

    public function test_inactive_staff_loses_only_this_gyms_access_immediately(): void
    {
        $staff = $this->staff(3)[2];
        $other = Gym::factory()->create();
        $other->users()->attach($staff, ['role' => GymRole::Staff]);
        $this->actingAs($this->owner)->patch(route('staff.activation', $staff), ['is_active' => false])->assertSessionHasNoErrors();
        $this->actingAs($staff)->put(route('current-gym.update'), ['gym_id' => $this->gym->id])->assertNotFound();
        $this->actingAs($staff)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('currentGym.id', $other->id));
        $this->assertNotNull($staff->roleIn($other));
    }

    public function test_owner_swaps_staff_within_limit_and_confirms_selection(): void
    {
        $staff = $this->staff(3);
        $this->gym->subscription->update(['plan' => 'starter']);
        $this->actingAs($this->owner)->patch(route('staff.activation', $staff[2]), ['is_active' => true])->assertSessionHasErrors('is_active');
        $this->actingAs($this->owner)->patch(route('staff.activation', $staff[1]), ['is_active' => false])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->patch(route('staff.activation', $staff[2]), ['is_active' => true])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->put(route('plan-selection.confirm'), ['selection' => 'staff'])->assertSessionHasNoErrors();
        $this->assertFalse($this->gym->refresh()->needs_staff_selection);
        $this->assertNotNull($staff[2]->roleIn($this->gym));
    }

    public function test_owner_can_never_be_deactivated(): void
    {
        $this->actingAs($this->owner)->patch(route('staff.activation', $this->owner), ['is_active' => false])->assertSessionHasErrors('is_active');
        $this->assertSame(GymRole::Owner, $this->owner->roleIn($this->gym));
    }

    public function test_upgrade_allows_one_click_reactivation_without_restoring_everyone(): void
    {
        $staff = $this->staff(4);
        $this->gym->subscription->update(['plan' => 'starter']);
        $this->gym->subscription->update(['plan' => 'pro']);
        $this->assertNull($staff[2]->roleIn($this->gym));
        $this->actingAs($this->owner)->patch(route('staff.activation', $staff[2]), ['is_active' => true])->assertSessionHasNoErrors();
        $this->assertNotNull($staff[2]->roleIn($this->gym));
        $this->assertNull($staff[3]->roleIn($this->gym));
    }

    public function test_branches_become_read_only_and_owner_can_swap_the_active_branch(): void
    {
        $this->gym->subscription->update(['plan' => 'multi_branch', 'extra_branches' => 1]);
        $second = Branch::create(['name' => 'Second']);
        $third = Branch::create(['name' => 'Third']);
        $mainId = $this->gym->mainBranch()->id;
        $this->gym->subscription->update(['plan' => 'starter', 'extra_branches' => 0]);
        $this->assertTrue($this->gym->mainBranch()->is_active);
        $this->assertFalse($second->refresh()->is_active);
        $this->assertFalse($third->refresh()->is_active);
        $this->assertSame(3, $this->gym->branches()->count());
        $this->actingAs($this->owner)->get(route('branches.index'))->assertOk();
        $this->actingAs($this->owner)->patch(route('branches.update', $second), ['name' => 'Hacked'])->assertForbidden();
        $this->actingAs($this->owner)->put(route('branches.selection'), ['branch_ids' => [$second->id]])->assertSessionHasNoErrors();
        $this->assertTrue($second->refresh()->is_active);
        $this->assertFalse($this->gym->branches()->findOrFail($mainId)->is_active);
        $this->assertFalse($this->gym->refresh()->needs_branch_selection);
        $this->actingAs($this->owner)->post(route('staff.invitations.store'), ['name' => 'Invited User', 'email' => 'new@example.test', 'role' => 'staff', 'branch_id' => $mainId])->assertSessionHasErrors('branch_id');
    }

    public function test_reducing_extra_branches_and_branch_reactivation_obey_capacity(): void
    {
        $this->gym->subscription->update(['plan' => 'multi_branch', 'extra_branches' => 1]);
        Branch::create(['name' => 'Second']);
        $third = Branch::create(['name' => 'Third']);
        $this->gym->subscription->update(['extra_branches' => 0]);
        $this->assertFalse($third->refresh()->is_active);
        $this->actingAs($this->owner)->patch(route('branches.activation', $third), ['is_active' => true])->assertSessionHasErrors('name');
        $this->gym->subscription->update(['extra_branches' => 1]);
        $this->actingAs($this->owner)->patch(route('branches.activation', $third), ['is_active' => true])->assertSessionHasNoErrors();
        $this->assertTrue($third->refresh()->is_active);
    }

    public function test_branch_selection_cannot_exceed_limit_or_select_none(): void
    {
        $this->gym->subscription->update(['plan' => 'multi_branch']);
        $second = Branch::create(['name' => 'Second']);
        $this->gym->subscription->update(['plan' => 'pro']);
        $this->actingAs($this->owner)->put(route('branches.selection'), ['branch_ids' => [$this->gym->mainBranch()->id, $second->id]])->assertSessionHasErrors('branch_ids');
        $this->actingAs($this->owner)->put(route('branches.selection'), ['branch_ids' => []])->assertSessionHasErrors('branch_ids');
    }

    public function test_non_owners_cannot_change_activation_or_confirm_choices(): void
    {
        $staff = $this->staff(1)[0];
        $this->actingAs($staff)->patch(route('staff.activation', $staff), ['is_active' => false])->assertForbidden();
        $this->actingAs($staff)->put(route('branches.selection'), ['branch_ids' => [$this->gym->mainBranch()->id]])->assertForbidden();
        $this->actingAs($staff)->put(route('plan-selection.confirm'), ['selection' => 'staff'])->assertForbidden();
    }

    public function test_manager_of_a_read_only_branch_can_view_but_not_edit(): void
    {
        $this->gym->subscription->update(['plan' => 'multi_branch', 'extra_branches' => 1]);
        Branch::create(['name' => 'Second']);
        $third = Branch::create(['name' => 'Third']);
        $manager = User::factory()->memberOf($this->gym, GymRole::BranchManager)->create();
        $this->gym->users()->updateExistingPivot($manager->id, ['branch_id' => $third->id]);
        $this->gym->subscription->update(['extra_branches' => 0]);
        $this->actingAs($manager)->get(route('branches.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('branches.data.0.is_active', false)->where('branches.data.0.canEdit', false));
        $this->actingAs($manager)->patch(route('branches.update', $third), ['name' => 'Hacked'])->assertForbidden();
    }
}
