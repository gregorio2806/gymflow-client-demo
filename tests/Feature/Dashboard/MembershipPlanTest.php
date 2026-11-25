<?php

namespace Tests\Feature\Dashboard;

use App\Actions\StartDemo;
use App\Enums\GymRole;
use App\Enums\Plan;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MembershipPlanTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gym = Gym::factory()->create();
        $this->owner = User::factory()->memberOf($this->gym, GymRole::Owner)->create();
        app(CurrentGym::class)->set($this->gym);
        $this->actingAs($this->owner);
    }

    private function data(array $overrides = []): array
    {
        return array_replace(['name' => 'Monthly gym access', 'duration_amount' => 1, 'duration_unit' => 'months', 'price_php' => '1500.50', 'inclusions' => 'Equipment and gym floor'], $overrides);
    }

    public function test_catalog_is_compact_and_details_show_only_that_plans_history(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-07 01:00:00', 'UTC'));
        $this->post(route('membership-plans.store'), $this->data())->assertRedirect();
        $plan = MembershipPlan::sole();
        $this->post(route('membership-plans.store'), $this->data(['name' => 'Another plan']))->assertRedirect();
        $this->get(route('membership-plans.index', ['tab' => 'overview']))->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'subscription')->missing('plans.data.0.createdBy')->missing('plans.data.0.createdAt'));

        $updater = User::factory()->memberOf($this->gym, GymRole::Owner)->create();
        $this->actingAs($updater);
        $this->travel(1)->hours();
        $this->patch(route('membership-plans.update', $plan), $this->data(['price_php' => '1200']))->assertRedirect(route('membership-plans.edit', $plan));
        $this->travel(1)->hours();
        $this->patch(route('membership-plans.activation', $plan), ['is_active' => false])->assertRedirect();
        $this->patch(route('membership-plans.activation', $plan), ['is_active' => true])->assertRedirect();
        $this->get(route('membership-plans.edit', $plan))->assertInertia(fn (Assert $page) => $page
            ->where('readOnly', false)->has('history.data', 4)
            ->where('history.data.0.action', 'Activated')->where('history.data.1.action', 'Archived')
            ->where('history.data.2.action', 'Updated')->where('history.data.2.by', $updater->name)
            ->where('history.data.2.at', 'October 7, 2026 10:00 AM')
            ->where('history.data.2.details.0', 'Price: ₱1,500.50 → ₱1,200.00')
            ->where('history.data.3.action', 'Created')->where('history.data.3.by', $this->owner->name)
            ->missing('history.data.0.ip_address')->missing('history.data.0.changes')->missing('history.data.0.user'));

        $staff = User::factory()->memberOf($this->gym, GymRole::Staff)->create();
        $this->actingAs($staff)->get(route('membership-plans.show', $plan))->assertInertia(fn (Assert $page) => $page
            ->where('readOnly', true)->where('canManage', false)->has('history.data', 4));
        $this->get(route('membership-plans.edit', $plan))->assertForbidden();
        $foreignGym = Gym::factory()->create();
        $this->actingAs(User::factory()->memberOf($foreignGym, GymRole::Owner)->create());
        $this->get(route('membership-plans.show', $plan))->assertNotFound();
        $this->get(route('membership-plans.edit', $plan))->assertNotFound();
    }

    public function test_legacy_plan_has_no_invented_history(): void
    {
        $plan = MembershipPlan::factory()->create();
        $this->get(route('membership-plans.show', $plan))->assertInertia(fn (Assert $page) => $page
            ->where('plan.createdBy', 'Not recorded')->has('history.data', 0));
    }

    public static function prices(): array
    {
        return [['0', 0], ['0.01', 1], ['0.29', 29], ['1500.5', 150050], ['9999999.99', 999999999]];
    }

    #[DataProvider('prices')]
    public function test_creation_stores_exact_centavos_and_audits(string $price, int $centavos): void
    {
        $this->post(route('membership-plans.store'), $this->data(['price_php' => $price, 'is_active' => false, 'price_centavos' => 9]))->assertRedirect(route('membership-plans.index', ['tab' => 'subscription']));
        $plan = MembershipPlan::sole();
        $this->assertSame($centavos, $plan->price_centavos);
        $this->assertTrue($plan->is_active);
        $this->assertSame('membership_plan.created', AuditLog::sole()->action);
    }

    public static function invalidPrices(): array
    {
        return [['-1'], ['1.001'], ['1e3'], ['1,500'], ['10000000'], ['abc']];
    }

    #[DataProvider('invalidPrices')]
    public function test_price_validation_rejects_invalid_or_excess_precision_values(string $price): void
    {
        $this->post(route('membership-plans.store'), $this->data(['price_php' => $price]))->assertSessionHasErrors('price_php');
        $this->assertDatabaseCount('membership_plans', 0);
    }

    public function test_duration_and_inclusions_validation(): void
    {
        $this->post(route('membership-plans.store'), $this->data(['name' => '', 'duration_amount' => 0, 'duration_unit' => 'years', 'inclusions' => str_repeat('x', 2001)]))->assertSessionHasErrors(['name', 'duration_amount', 'duration_unit', 'inclusions']);
        $this->post(route('membership-plans.store'), $this->data(['duration_amount' => 121]))->assertSessionHasErrors('duration_amount');
        $this->post(route('membership-plans.store'), $this->data(['duration_unit' => 'days', 'duration_amount' => 3651]))->assertSessionHasErrors('duration_amount');
        $this->post(route('membership-plans.store'), $this->data(['duration_unit' => 'days', 'duration_amount' => 7]))->assertSessionHasNoErrors();
    }

    public function test_owner_edits_archives_and_reactivates_without_deletion(): void
    {
        $plan = MembershipPlan::factory()->create();
        $this->get(route('membership-plans.edit', $plan))->assertInertia(fn (Assert $page) => $page->where('plan.price_php', '1500.00')->missing('plan.gym_id'));
        $this->patch(route('membership-plans.update', $plan), $this->data(['price_php' => '2000.01', 'duration_amount' => 3]))->assertSessionHasNoErrors();
        $this->assertSame(200001, $plan->refresh()->price_centavos);
        $this->patch(route('membership-plans.activation', $plan), ['is_active' => false])->assertSessionHasNoErrors();
        $this->assertFalse($plan->refresh()->is_active);
        $this->assertModelExists($plan);
        $this->patch(route('membership-plans.activation', $plan), ['is_active' => true])->assertSessionHasNoErrors();
        $this->assertTrue($plan->refresh()->is_active);
        $this->assertEqualsCanonicalizing(['membership_plan.updated', 'membership_plan.archived', 'membership_plan.activated'], AuditLog::pluck('action')->all());
    }

    public function test_list_paginates_and_filters_without_private_props(): void
    {
        MembershipPlan::factory()->count(26)->create();
        $archived = MembershipPlan::factory()->inactive()->create();
        $this->get(route('membership-plans.index'))->assertInertia(fn (Assert $page) => $page->has('plans.data', 25)->where('plans.total', 27)->missing('plans.data.0.gym_id')->missing('plans.data.0.created_at'));
        $this->get(route('membership-plans.index', ['status' => 'inactive']))->assertInertia(fn (Assert $page) => $page->has('plans.data', 1)->where('plans.data.0.id', $archived->id));
        $this->get(route('membership-plans.index', ['status' => 'invalid']))->assertSessionHasErrors('status');
    }

    public function test_staff_and_managers_can_view_gym_wide_catalog_but_not_change_it(): void
    {
        $this->gym->subscription->update(['plan' => Plan::MultiBranch]);
        $plan = MembershipPlan::factory()->create();
        $branch = Branch::factory()->create();
        foreach ([GymRole::Staff, GymRole::BranchManager] as $role) {
            $user = User::factory()->memberOf($this->gym, $role)->create();
            if ($role === GymRole::BranchManager) {
                $this->gym->users()->updateExistingPivot($user->id, ['branch_id' => $branch->id]);
            }
            $this->actingAs($user)->get(route('membership-plans.index'))->assertInertia(fn (Assert $page) => $page->has('plans.data', 1)->where('canManage', false));
            $this->get(route('membership-plans.create'))->assertForbidden();
            $this->get(route('membership-plans.edit', $plan))->assertForbidden();
            $this->post(route('membership-plans.store'), $this->data())->assertForbidden();
            $this->patch(route('membership-plans.update', $plan), $this->data())->assertForbidden();
            $this->patch(route('membership-plans.activation', $plan), ['is_active' => false])->assertForbidden();
        }
    }

    public function test_coaches_cannot_access_catalog(): void
    {
        $coach = User::factory()->memberOf($this->gym, GymRole::Coach)->create();
        $this->actingAs($coach)->get(route('membership-plans.index'))->assertForbidden();
        $this->get(route('membership-plans.show', MembershipPlan::factory()->create()))->assertForbidden();
    }

    public function test_disabled_feature_blocks_all_plan_endpoints(): void
    {
        $plan = MembershipPlan::factory()->create();
        Feature::for($this->gym)->deactivate('members');
        $this->get(route('membership-plans.index'))->assertNotFound();
        $this->get(route('membership-plans.create'))->assertNotFound();
        $this->get(route('membership-plans.edit', $plan))->assertNotFound();
        $this->get(route('membership-plans.show', $plan))->assertNotFound();
        $this->post(route('membership-plans.store'), $this->data())->assertNotFound();
        $this->patch(route('membership-plans.update', $plan), $this->data())->assertNotFound();
        $this->patch(route('membership-plans.activation', $plan), ['is_active' => false])->assertNotFound();
    }

    public function test_model_fails_closed_without_context_and_cannot_move_gyms(): void
    {
        $plan = MembershipPlan::factory()->create();
        app(CurrentGym::class)->forget();
        $this->assertSame(0, MembershipPlan::count());
        $other = Gym::factory()->create();
        $plan->gym_id = $other->id;
        $this->expectException(LogicException::class);
        $plan->save();
    }

    public function test_demo_plans_are_private_and_deleted_with_the_gym(): void
    {
        $gym = app(StartDemo::class)->handle()->gyms()->sole();
        app(CurrentGym::class)->runAs($gym, function () use ($gym) {
            $this->assertSame(3, MembershipPlan::count());
            $this->assertSame([15000, 150000, 400000], MembershipPlan::orderBy('id')->pluck('price_centavos')->all());
            $gym->delete();
        });
        $this->assertSame(0, MembershipPlan::withoutGlobalScope('gym')->where('gym_id', $gym->id)->count());
    }
}
