<?php

namespace Tests\Feature\Dashboard;

use App\Actions\ManageMemberSubscription;
use App\Enums\GymRole;
use App\Enums\Plan;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\MemberSubscription;
use App\Models\Payment;
use App\Models\User;
use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MemberManagementTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private User $owner;

    private MembershipPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gym = Gym::factory()->create();
        $this->owner = User::factory()->memberOf($this->gym, GymRole::Owner)->create();
        app(CurrentGym::class)->set($this->gym);
        $this->plan = MembershipPlan::factory()->create(['name' => 'Fictional monthly plan', 'duration_amount' => 30, 'duration_unit' => 'days', 'price_centavos' => 120000]);
        $this->actingAs($this->owner);
    }

    private function data(array $overrides = []): array
    {
        $mainBranchId = app(CurrentGym::class)->runAs($this->gym, fn () => $this->gym->mainBranch()->id);

        return array_replace(['name' => 'Alex Santos', 'mobile' => '09000000001', 'email' => null, 'home_branch_id' => $mainBranchId, 'privacy_consent' => true, 'membership_plan_id' => $this->plan->id, 'starts_on' => now()->setTimezone($this->gym->timezone)->toDateString(), 'request_key' => (string) Str::uuid()], $overrides);
    }

    public function test_member_list_eager_loads_branches_instead_of_querying_per_row(): void
    {
        Member::factory()->count(25)->create();
        DB::enableQueryLog();
        $this->get(route('members.index'))->assertOk();
        $queries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains(strtolower($query['query']), 'from "branches"'));
        DB::disableQueryLog();
        // Includes one fixed query for the navbar branch options.
        $this->assertLessThanOrEqual(4, $queries->count(), 'Branch queries must stay bounded for a full page of members.');
    }

    public function test_create_records_consent_and_audit_without_trusting_activation_input(): void
    {
        $this->post(route('members.store'), $this->data(['is_active' => false, 'privacy_recorded_by' => 999]))->assertRedirect(route('members.index'));
        $member = Member::sole();
        $this->assertTrue($member->is_active);
        $this->assertNotNull($member->privacy_consented_at);
        $this->assertSame($this->owner->id, $member->privacy_recorded_by);
        $this->assertSame($member->id, AuditLog::where('action', 'member.created')->sole()->subject_id);
        $period = MemberSubscription::sole();
        $this->assertSame($member->id, $period->member_id);
        $this->assertSame($this->plan->id, $period->membership_plan_id);
        $this->assertSame($this->plan->price_centavos, $period->price_centavos);
        $this->assertSame('assign', $period->operation);
        $this->assertSame(now()->setTimezone($this->gym->timezone)->startOfDay()->utc()->toDateTimeString(), $period->starts_at->toDateTimeString());
        $this->assertTrue($period->ends_at->equalTo($period->starts_at->addDays(30)));
        $this->assertSame($period->id, AuditLog::where('action', 'membership.assigned')->sole()->subject_id);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_new_member_requires_an_active_plan_from_this_gym_and_valid_start_date(): void
    {
        $data = $this->data();
        unset($data['membership_plan_id'], $data['starts_on'], $data['request_key']);
        $this->post(route('members.store'), $data)->assertSessionHasErrors(['membership_plan_id', 'starts_on', 'request_key']);
        $foreign = Gym::factory()->create();
        $foreignPlan = app(CurrentGym::class)->runAs($foreign, fn () => MembershipPlan::factory()->create());
        $archived = MembershipPlan::factory()->create(['is_active' => false]);
        foreach ([$foreignPlan->id, $archived->id] as $id) {
            $this->post(route('members.store'), $this->data(['membership_plan_id' => $id]))->assertSessionHasErrors('membership_plan_id');
        }
        foreach (['2026-02-30', '1899-12-31', '2101-01-01'] as $date) {
            $this->post(route('members.store'), $this->data(['starts_on' => $date]))->assertSessionHasErrors('starts_on');
        }
        $this->assertDatabaseCount('members', 0);
        $this->assertDatabaseCount('member_subscriptions', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_registration_branch_is_automatic_without_a_branch_input(): void
    {
        $data = $this->data();
        unset($data['home_branch_id']);
        $this->post(route('members.store'), $data)->assertSessionHasNoErrors();
        $this->assertSame($this->gym->mainBranch()->id, Member::sole()->home_branch_id);
        $this->assertDatabaseCount('member_subscriptions', 1);
        $this->assertSame($this->gym->mainBranch()->id, AuditLog::where('action', 'member.created')->sole()->changes['after']['home_branch_id']);
    }

    public function test_registration_branch_follows_the_navbar_and_read_only_selections_are_blocked(): void
    {
        $this->gym->subscription->update(['plan' => Plan::MultiBranch]);
        $branch = Branch::factory()->create();
        $this->withSession(['view_branch.'.$this->gym->id => $branch->id]);
        $this->get(route('members.create'))->assertInertia(fn (Assert $page) => $page->where('canSave', true));
        // A submitted main-branch ID cannot override the server's current branch.
        $this->post(route('members.store'), $this->data())->assertSessionHasNoErrors();
        $this->assertSame($branch->id, Member::sole()->home_branch_id);
        $this->withSession(['view_branch.'.$this->gym->id => $this->gym->mainBranch()->id]);
        $this->get(route('members.index'))->assertInertia(fn (Assert $page) => $page->has('members.data', 1));
        $branch->forceFill(['is_active' => false])->save();
        $this->withSession(['view_branch.'.$this->gym->id => $branch->id]);
        $this->get(route('members.create'))->assertInertia(fn (Assert $page) => $page->where('canSave', false));
        $this->post(route('members.store'), $this->data())->assertSessionHasErrors('home_branch_id');
        $this->assertDatabaseCount('members', 1);
        $this->assertDatabaseCount('member_subscriptions', 1);
    }

    public function test_all_branches_registration_uses_an_active_branch_when_main_is_inactive(): void
    {
        $this->gym->subscription->update(['plan' => Plan::MultiBranch]);
        $branch = Branch::factory()->create();
        $this->gym->mainBranch()->forceFill(['is_active' => false])->save();
        $this->get(route('members.create'))->assertInertia(fn (Assert $page) => $page->where('canSave', true));
        $this->post(route('members.store'), $this->data())->assertSessionHasNoErrors();
        $this->assertSame($branch->id, Member::sole()->home_branch_id);
        $branch->forceFill(['is_active' => false])->save();
        $this->get(route('members.create'))->assertInertia(fn (Assert $page) => $page->where('canSave', false));
        $this->post(route('members.store'), $this->data())->assertSessionHasErrors('home_branch_id');
        $this->assertDatabaseCount('members', 1);
    }

    public function test_registration_discounts_are_owner_defined_snapshotted_and_used_for_payment_balances(): void
    {
        foreach (['regular', 'student', 'pwd', 'senior', 'others'] as $type) {
            $this->post(route('members.store'), $this->data([
                'discount_percent' => '15', 'discount_type' => $type, 'discount_other' => 'Family promotion',
                'price_centavos' => 1, 'original_price_centavos' => 1, 'discount_basis_points' => 9999,
            ]))->assertSessionHasNoErrors();
            $period = MemberSubscription::latest('id')->first();
            $this->assertSame(120000, $period->original_price_centavos);
            $this->assertSame(102000, $period->price_centavos);
            $this->assertSame(1500, $period->discount_basis_points);
            $this->assertSame($type, $period->discount_type);
            $this->assertSame($type === 'others' ? 'Family promotion' : null, $period->discount_other);
        }
        $member = $period->member;
        $this->get(route('members.show', $member))->assertInertia(fn (Assert $page) => $page
            ->where('member.planAmountCentavos', 102000)->where('subscriptions.data.0.discountType', 'Family promotion')
            ->where('subscriptions.data.0.discountPercent', 15)->where('subscriptions.data.0.originalPriceCentavos', 120000)
            ->where('paymentForm.subscriptions.data.0.balanceCentavos', 102000));
        $audit = AuditLog::where('action', 'membership.assigned')->latest('id')->first();
        $this->assertSame('Family promotion', $audit->changes['after']['discount_other']);
        $this->assertSame($this->owner->id, $audit->user_id);
        $this->assertDatabaseCount('payments', 0);
        $this->post(route('payments.store', $member), [
            'member_subscription_id' => $period->id, 'amount_php' => '1020.00', 'discount_percent' => '0',
            'method' => 'cash', 'request_key' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();
        $this->assertSame(102000, Payment::sole()->amount_centavos);
        $this->get(route('members.show', $member))->assertInertia(fn (Assert $page) => $page->where('paymentForm.subscriptions.data.0.balanceCentavos', 0));
        $this->plan->forceFill(['price_centavos' => 200000])->save();
        $this->patch(route('members.update', $member), $this->data(['discount_percent' => '99', 'discount_type' => 'student']))->assertSessionHasNoErrors();
        $this->assertSame(102000, $period->refresh()->price_centavos);
        $this->assertSame(120000, $period->original_price_centavos);
    }

    public function test_invalid_registration_discounts_and_foreign_plans_leave_no_member_or_history(): void
    {
        foreach ([
            ['discount_percent' => '-1'], ['discount_percent' => '100.01'], ['discount_percent' => '5.555'],
            ['discount_percent' => 'abc'], ['discount_type' => 'unknown'], ['discount_type' => 'others'],
            ['discount_type' => 'others', 'discount_other' => '   '],
            ['discount_type' => 'others', 'discount_other' => str_repeat('x', 101)],
        ] as $input) {
            $this->post(route('members.store'), $this->data($input))->assertSessionHasErrors();
        }
        $foreign = Gym::factory()->create();
        $plan = app(CurrentGym::class)->runAs($foreign, fn () => MembershipPlan::factory()->create());
        $this->post(route('members.store'), $this->data(['membership_plan_id' => $plan->id, 'discount_type' => 'student', 'discount_percent' => '15']))->assertSessionHasErrors('membership_plan_id');
        $this->assertDatabaseCount('members', 0);
        $this->assertDatabaseCount('member_subscriptions', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_registration_discount_rounding_and_zero_and_full_discounts(): void
    {
        $this->plan->forceFill(['price_centavos' => 10005])->save();
        foreach ([['0', 10005], ['12.5', 8754], ['100', 0]] as [$percent, $expected]) {
            $this->post(route('members.store'), $this->data(['discount_percent' => $percent, 'discount_type' => 'student']))->assertSessionHasNoErrors();
            $period = MemberSubscription::latest('id')->first();
            $this->assertSame($expected, $period->price_centavos);
            $this->assertSame(10005, $period->original_price_centavos);
        }
    }

    public function test_peso_registration_discounts_save_exact_centavos_and_show_the_selected_unit(): void
    {
        foreach ([['0', 120000], ['200.25', 99975], ['1200.00', 0]] as [$discount, $expected]) {
            $this->post(route('members.store'), $this->data([
                'discount_mode' => 'peso', 'discount_php' => $discount, 'discount_percent' => '999', 'discount_type' => 'student',
            ]))->assertSessionHasNoErrors();
            $period = MemberSubscription::latest('id')->first();
            $this->assertSame('peso', $period->discount_mode);
            $this->assertSame(0, $period->discount_basis_points);
            $this->assertSame($expected, $period->price_centavos);
            $this->assertSame(120000, $period->original_price_centavos);
            $this->get(route('members.show', $period->member))->assertInertia(fn (Assert $page) => $page
                ->where('subscriptions.data.0.discountMode', 'peso')->where('subscriptions.data.0.discountCentavos', 120000 - $expected)
                ->where('paymentForm.subscriptions.data.0.balanceCentavos', $expected));
        }
        $this->assertSame('peso', AuditLog::where('action', 'membership.assigned')->latest('id')->first()->changes['after']['discount_mode']);
        $this->assertDatabaseCount('payments', 0);
        // The inactive unit must not affect percentage calculations either.
        $this->post(route('members.store'), $this->data(['discount_mode' => 'percentage', 'discount_percent' => '10', 'discount_php' => '99999']))->assertSessionHasNoErrors();
        $this->assertSame(108000, MemberSubscription::latest('id')->first()->price_centavos);
    }

    public function test_invalid_peso_registration_discounts_roll_back_the_member_and_history(): void
    {
        foreach ([null, '-1', '1200.01', '1.234', '1e2', 'abc', '10000000'] as $value) {
            $this->post(route('members.store'), $this->data(['discount_mode' => 'peso', 'discount_php' => $value]))->assertSessionHasErrors('discount_php');
        }
        $this->post(route('members.store'), $this->data(['discount_mode' => 'unknown']))->assertSessionHasErrors('discount_mode');
        $this->assertDatabaseCount('members', 0);
        $this->assertDatabaseCount('member_subscriptions', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_peso_discount_retries_use_the_saved_terms_and_reject_changed_details(): void
    {
        $data = $this->data(['discount_mode' => 'peso', 'discount_php' => '200.25', 'operation' => 'assign']);
        $this->post(route('members.store'), $data)->assertSessionHasNoErrors();
        $period = MemberSubscription::sole();
        $member = $period->member;
        $action = app(ManageMemberSubscription::class);
        $this->plan->forceFill(['price_centavos' => 240000])->save();
        $this->assertSame($period->id, $action->assign($member, $data)->id);
        foreach ([['discount_php' => '200.26'], ['discount_mode' => 'percentage', 'discount_percent' => '0']] as $changes) {
            try {
                $action->assign($member, array_replace($data, $changes));
                $this->fail('Changed discount retries must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('request_key', $exception->errors());
            }
        }
        try {
            $period->forceFill(['discount_mode' => 'percentage'])->save();
            $this->fail('Saved discount units must be immutable.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }
        $this->assertDatabaseCount('member_subscriptions', 1);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_membership_assignment_failure_rolls_back_the_new_member_and_its_audit(): void
    {
        $data = $this->data();
        $this->post(route('members.store'), $data)->assertSessionHasNoErrors();
        $this->post(route('members.store'), $data)->assertSessionHasErrors('request_key');
        $this->assertDatabaseCount('members', 1);
        $this->assertDatabaseCount('member_subscriptions', 1);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_add_form_lists_only_available_gym_plans_and_edit_does_not_assign_a_plan(): void
    {
        MembershipPlan::factory()->create(['is_active' => false]);
        $foreign = Gym::factory()->create();
        app(CurrentGym::class)->runAs($foreign, fn () => MembershipPlan::factory()->create());
        $this->get(route('members.create'))->assertInertia(fn (Assert $page) => $page->has('plans', 1)->where('plans.0.id', $this->plan->id)->where('today', now()->setTimezone($this->gym->timezone)->toDateString()));
        $this->post(route('members.store'), $this->data())->assertSessionHasNoErrors();
        $member = Member::sole();
        $this->patch(route('members.update', $member), ['name' => 'Edited member', 'home_branch_id' => $member->home_branch_id])->assertSessionHasNoErrors();
        $this->get(route('members.edit', $member))->assertInertia(fn (Assert $page) => $page->where('plans', []));
        $this->assertDatabaseCount('member_subscriptions', 1);
        $this->plan->forceFill(['is_active' => false])->save();
        $this->get(route('members.create'))->assertInertia(fn (Assert $page) => $page->where('plans', []));
    }

    public function test_view_and_edit_share_explicit_profile_props_with_read_only_mode(): void
    {
        $member = Member::factory()->create();
        $this->get(route('members.show', $member))->assertInertia(fn (Assert $page) => $page->component('dashboard/members/Subscriptions')
            ->where('readOnly', true)->where('member.name', $member->name)->where('member.membershipStatus', 'No membership')->missing('member.privacy_recorded_by'));
        $this->get(route('members.edit', $member))->assertInertia(fn (Assert $page) => $page->component('dashboard/members/Edit')->where('readOnly', false)->where('member.name', $member->name));
        $this->assertDatabaseCount('audit_logs', 0);
        $this->gym->mainBranch()->forceFill(['is_active' => false])->save();
        $this->get(route('members.show', $member))->assertInertia(fn (Assert $page) => $page->where('readOnly', true)->where('canSave', false)->has('branches', 1)->where('branches.0.id', $member->home_branch_id));
    }

    public function test_view_respects_coach_feature_and_manager_branch_restrictions(): void
    {
        $member = Member::factory()->create();
        $this->actingAs(User::factory()->memberOf($this->gym, GymRole::Coach)->create())->get(route('members.show', $member))->assertForbidden();
        $this->gym->subscription->update(['plan' => Plan::MultiBranch]);
        $manager = User::factory()->memberOf($this->gym, GymRole::BranchManager)->create();
        $other = Member::factory()->create(['home_branch_id' => Branch::factory()->create()->id]);
        $this->actingAs($manager)->get(route('members.show', $member))->assertOk();
        $this->get(route('members.show', $other))->assertNotFound();
        Feature::for($this->gym)->deactivate('members');
        $this->get(route('members.show', $member))->assertNotFound();
    }

    public function test_member_payment_picker_is_gated_and_gym_wide_for_owner(): void
    {
        $member = Member::factory()->create();
        $other = Member::factory()->create(['home_branch_id' => Branch::factory()->create()->id]);
        $url = route('members.index', ['section' => 'member-payment']);
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('paymentSelection', true)->has('members.data', 2));
        $this->withSession(['view_branch.'.$this->gym->id => $member->home_branch_id])->get($url)->assertInertia(fn (Assert $page) => $page->has('members.data', 2));
        Feature::for($this->gym)->deactivate('payments');
        $this->get($url)->assertNotFound();
        $this->get(route('members.index'))->assertOk();
    }

    public function test_profile_combines_information_and_membership_data_without_private_fields(): void
    {
        $member = Member::factory()->create();
        $this->get(route('members.show', $member))->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/members/Subscriptions')->where('member.mobile', $member->mobile)
            ->where('member.email', $member->email)->where('member.branchName', $this->gym->mainBranch()->name)
            ->has('plans')->has('subscriptions.data', 0)->missing('member.portal_password')->missing('member.gym_id')
            ->missing('member.privacy_recorded_by')->missing('member.privacy_consented_at'));
    }

    public function test_invalid_contact_is_rejected(): void
    {
        $this->post(route('members.store'), $this->data(['name' => '', 'mobile' => 'abc', 'email' => 'not-email', 'privacy_consent' => false]))->assertSessionHasErrors(['name', 'mobile', 'email']);
        $this->assertDatabaseCount('members', 0);
    }

    public function test_registration_and_edit_without_consent_do_not_invent_consent_records(): void
    {
        $data = $this->data();
        unset($data['privacy_consent']);
        $this->post(route('members.store'), $data)->assertSessionHasNoErrors();
        $member = Member::sole();
        $this->assertNull($member->privacy_consented_at);
        $this->assertNull($member->privacy_recorded_by);
        $this->patch(route('members.update', $member), [...$data, 'name' => 'Updated member'])->assertSessionHasNoErrors();
        $this->assertNull($member->fresh()->privacy_consented_at);
        $this->assertNull($member->fresh()->privacy_recorded_by);
    }

    public function test_members_can_be_created_without_mobile_and_existing_numbers_can_be_cleared(): void
    {
        foreach ([[], ['mobile' => null], ['mobile' => ''], ['mobile' => '   ']] as $input) {
            $data = $this->data($input);
            if ($input === []) {
                unset($data['mobile']);
            }
            $this->post(route('members.store'), $data)->assertSessionHasNoErrors()->assertRedirect(route('members.index'));
            $member = Member::latest('id')->first();
            $this->assertSame('', $member->mobile);
        }

        $this->patch(route('members.update', $member), $this->data())->assertSessionHasNoErrors();
        $data = $this->data();
        unset($data['mobile']);
        $this->patch(route('members.update', $member), $data)->assertSessionHasNoErrors();
        $this->assertSame('09000000001', $member->refresh()->mobile);
        $this->patch(route('members.update', $member), $this->data(['mobile' => '']))->assertSessionHasNoErrors();
        $this->assertSame('', $member->refresh()->mobile);
        $this->get(route('members.edit', $member))->assertInertia(fn (Assert $page) => $page->where('member.mobile', ''));
        $this->assertSame('', AuditLog::where('action', 'member.updated')->latest('id')->first()->changes['after']['mobile']);
    }

    public function test_edit_preserves_original_consent_and_can_transfer_home_branch(): void
    {
        $this->gym->subscription->update(['plan' => Plan::MultiBranch]);
        $branch = Branch::factory()->create();
        $this->post(route('members.store'), $this->data());
        $member = Member::sole();
        $consent = $member->privacy_consented_at;
        $this->patch(route('members.update', $member), $this->data(['name' => 'Updated', 'home_branch_id' => $branch->id]))->assertSessionHasNoErrors();
        $this->assertSame('Updated', $member->refresh()->name);
        $this->assertSame($branch->id, $member->home_branch_id);
        $this->assertTrue($member->privacy_consented_at->equalTo($consent));
        $this->assertDatabaseHas('audit_logs', ['action' => 'member.updated', 'subject_id' => $member->id]);
    }

    public function test_deactivation_and_reactivation_do_not_require_consent(): void
    {
        $member = Member::factory()->create();
        $this->patch(route('members.activation', $member), ['is_active' => false])->assertSessionHasNoErrors();
        $this->assertFalse($member->refresh()->is_active);
        $this->patch(route('members.activation', $member), ['is_active' => true])->assertSessionHasNoErrors();
        $this->assertTrue($member->refresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'member.activated', 'subject_id' => $member->id]);
    }

    public static function cappedPlans(): array
    {
        return [[Plan::Starter], [Plan::Pro]];
    }

    #[DataProvider('cappedPlans')]
    public function test_create_and_reactivate_enforce_plan_boundary(Plan $plan): void
    {
        $this->gym->subscription->update(['plan' => $plan]);
        $limit = $plan->limits()['active_members'];
        Member::factory()->count($limit - 1)->create();
        $this->post(route('members.store'), $this->data())->assertSessionHasNoErrors();
        $this->post(route('members.store'), $this->data(['name' => 'Over limit']))->assertSessionHasErrors('name');
        $inactive = Member::factory()->inactive()->create(['privacy_consented_at' => now()]);
        $this->patch(route('members.activation', $inactive), ['is_active' => true])->assertSessionHasErrors('name');
        $active = Member::where('is_active', true)->first();
        $this->patch(route('members.activation', $active), ['is_active' => false])->assertSessionHasNoErrors();
        $this->patch(route('members.activation', $inactive), ['is_active' => true])->assertSessionHasNoErrors();
        $this->assertTrue($inactive->refresh()->is_active);
    }

    public function test_downgrade_never_deactivates_existing_members_and_edit_still_works(): void
    {
        $this->gym->subscription->update(['plan' => Plan::MultiBranch]);
        Member::factory()->count(151)->create();
        $this->gym->subscription->update(['plan' => Plan::Starter]);
        $this->assertSame(151, Member::where('is_active', true)->count());
        $this->get(route('members.index'))->assertOk();
        $member = Member::first();
        $this->patch(route('members.update', $member), $this->data())->assertSessionHasNoErrors();
        $this->post(route('members.store'), $this->data())->assertSessionHasErrors('name');
    }

    public function test_unlimited_plan_counts_only_current_gym_and_inactive_members_do_not_count(): void
    {
        $this->gym->subscription->update(['plan' => Plan::MultiBranch]);
        Member::factory()->count(151)->create();
        $this->post(route('members.store'), $this->data())->assertSessionHasNoErrors();
        $this->gym->subscription->update(['plan' => Plan::Starter]);
        Member::query()->update(['is_active' => false]);
        $other = Gym::factory()->create();
        app(CurrentGym::class)->runAs($other, fn () => Member::factory()->count(150)->create());
        $this->post(route('members.store'), $this->data())->assertSessionHasNoErrors();
    }

    public function test_list_paginates_filters_searches_and_passes_only_needed_fields(): void
    {
        Member::factory()->count(26)->create();
        $this->get(route('members.index'))->assertInertia(fn (Assert $page) => $page->has('members.data', 25)->where('members.total', 26)->missing('members.data.0.gym_id')->missing('members.data.0.privacy_recorded_by'));
        $member = Member::factory()->inactive()->create(['name' => 'Unique Search', 'email' => 'unique@demo.invalid', 'mobile' => '09099999999']);
        foreach (['Unique Search', 'unique@demo.invalid', '09099999999'] as $search) {
            $this->get(route('members.index', ['search' => $search, 'status' => 'inactive']))->assertInertia(fn (Assert $page) => $page->has('members.data', 1)->where('members.data.0.id', $member->id));
        }
        $this->get(route('members.index', ['search' => '%']))->assertInertia(fn (Assert $page) => $page->has('members.data', 0));
        $this->get(route('members.index', ['status' => 'bad']))->assertSessionHasErrors('status');
    }

    public function test_staff_can_manage_members_but_coaches_cannot(): void
    {
        $staff = User::factory()->memberOf($this->gym, GymRole::Staff)->create();
        $coach = User::factory()->memberOf($this->gym, GymRole::Coach)->create();
        $this->actingAs($staff)->post(route('members.store'), $this->data())->assertSessionHasNoErrors();
        $member = Member::sole();
        $this->actingAs($coach)->get(route('members.index'))->assertForbidden();
        $this->get(route('members.create'))->assertForbidden();
        $this->post(route('members.store'), $this->data())->assertForbidden();
        $this->get(route('members.edit', $member))->assertForbidden();
        $this->patch(route('members.update', $member), $this->data())->assertForbidden();
        $this->patch(route('members.activation', $member), ['is_active' => false])->assertForbidden();
    }

    public function test_manager_access_is_limited_to_assigned_home_branch(): void
    {
        $this->gym->subscription->update(['plan' => Plan::MultiBranch]);
        $branch = Branch::factory()->create();
        $foreign = Member::factory()->create();
        $own = Member::factory()->create(['home_branch_id' => $branch->id]);
        $manager = User::factory()->memberOf($this->gym, GymRole::BranchManager)->create();
        $this->gym->users()->updateExistingPivot($manager->id, ['branch_id' => $branch->id]);
        $this->actingAs($manager)->get(route('members.index'))->assertInertia(fn (Assert $page) => $page->has('members.data', 1)->where('members.data.0.id', $own->id));
        $this->get(route('members.edit', $foreign))->assertNotFound();
        $this->patch(route('members.update', $foreign), $this->data())->assertNotFound();
        $this->patch(route('members.activation', $foreign), ['is_active' => false])->assertNotFound();
        // Registration is server-assigned to the manager's branch, regardless of submitted IDs.
        $this->post(route('members.store'), $this->data())->assertSessionHasNoErrors();
        $this->assertSame($branch->id, Member::where('name', 'Alex Santos')->sole()->home_branch_id);
        $this->patch(route('members.update', $own), $this->data(['home_branch_id' => $branch->id]))->assertSessionHasNoErrors();
    }

    public function test_read_only_branch_members_can_be_read_but_not_changed(): void
    {
        $branch = $this->gym->mainBranch();
        $member = Member::factory()->create();
        $branch->forceFill(['is_active' => false])->save();
        $this->get(route('members.index'))->assertInertia(fn (Assert $page) => $page->where('members.data.0.canEdit', false));
        $this->patch(route('members.update', $member), $this->data())->assertSessionHasErrors('home_branch_id');
        $this->post(route('members.store'), $this->data())->assertSessionHasErrors('home_branch_id');
        $this->patch(route('members.activation', $member), ['is_active' => false])->assertSessionHasErrors('is_active');
    }

    public function test_disabled_members_feature_blocks_all_member_endpoints(): void
    {
        $member = Member::factory()->create();
        Feature::for($this->gym)->deactivate('members');
        foreach (['members.index', 'members.create'] as $route) {
            $this->get(route($route))->assertNotFound();
        }
        $this->get(route('members.edit', $member))->assertNotFound();
        $this->post(route('members.store'), $this->data())->assertNotFound();
        $this->patch(route('members.update', $member), $this->data())->assertNotFound();
        $this->patch(route('members.activation', $member), ['is_active' => false])->assertNotFound();
    }
}
