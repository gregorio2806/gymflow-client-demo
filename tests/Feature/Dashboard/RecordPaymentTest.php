<?php

namespace Tests\Feature\Dashboard;

use App\Actions\ManageMemberSubscription;
use App\Enums\GymRole;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature;
use RuntimeException;
use Tests\TestCase;

class RecordPaymentTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private Member $member;

    private array $data;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gym = Gym::factory()->create();
        app(CurrentGym::class)->set($this->gym);
        $this->actingAs(User::factory()->memberOf($this->gym, GymRole::Staff)->create());
        $this->member = Member::factory()->create();
        $period = app(ManageMemberSubscription::class)->assign($this->member, ['membership_plan_id' => MembershipPlan::factory()->create(['price_centavos' => 100000])->id, 'operation' => 'assign', 'starts_on' => now()->toDateString(), 'request_key' => (string) Str::uuid()]);
        $this->data = ['member_subscription_id' => $period->id, 'amount_php' => '250.05', 'method' => 'cash', 'request_key' => (string) Str::uuid()];
    }

    private function record(array $data = [])
    {
        return $this->post(route('payments.store', $this->member), array_replace($this->data, $data));
    }

    public function test_histories_show_original_creator_and_creation_time_separately_from_void_actor(): void
    {
        $creator = auth()->user();
        $this->record()->assertRedirect();
        $payment = Payment::sole();
        $timestamp = $payment->created_at->setTimezone($this->gym->timezone)->format('M j, Y g:i A');
        $otherStaff = User::factory()->memberOf($this->gym, GymRole::Staff)->create();
        $this->actingAs($otherStaff);
        app(ManageMemberSubscription::class)->change($this->member, $payment->subscription, 'suspend');
        $this->patch(route('payments.void', $payment), ['void_reason' => 'Correct entry'])->assertRedirect();

        $this->get(route('members.show', $this->member))->assertInertia(fn (Assert $page) => $page
            ->where('subscriptions.data.0.createdBy', $creator->name)
            ->where('paymentHistory.payments.data.0.createdBy', $creator->name)
            ->where('paymentHistory.payments.data.0.createdAt', $timestamp)
            ->where('paymentHistory.payments.data.0.voidedBy', $otherStaff->name)
            ->missing('subscriptions.data.0.creationAudit')
            ->missing('paymentHistory.payments.data.0.recorder'));
        $this->get(route('payments.show', $payment))->assertInertia(fn (Assert $page) => $page
            ->where('record.createdBy', $creator->name)->where('record.createdAt', $timestamp)
            ->where('record.voidedBy', $otherStaff->name)->missing('record.recorder'));
        $this->get(route('payments.overview'))->assertInertia(fn (Assert $page) => $page
            ->where('payments.data.0.createdBy', $creator->name)
            ->where('payments.data.0.voidedBy', $otherStaff->name)
            ->where('payments.data.0.voidedAt', $payment->fresh()->voided_at->setTimezone($this->gym->timezone)->format('M j, Y g:i A'))
            ->missing('payments.data.0.voider'));

        $foreignGym = Gym::factory()->create();
        $this->actingAs(User::factory()->memberOf($foreignGym, GymRole::Owner)->create());
        $this->get(route('members.show', $this->member))->assertNotFound();
        $this->get(route('payments.show', $payment))->assertNotFound();
    }

    public function test_membership_without_creation_audit_does_not_invent_a_creator(): void
    {
        // Simulate legacy fixture data which has no assignment audit.
        AuditLog::where('action', 'membership.assigned')->delete();
        $this->get(route('members.show', $this->member))->assertInertia(fn (Assert $page) => $page
            ->where('subscriptions.data.0.createdBy', 'Not recorded')
            ->where('subscriptions.data.0.createdAt', fn ($value) => is_string($value) && strlen($value) > 0));
    }

    public function test_payment_discount_settles_gross_amount_but_only_cash_is_revenue(): void
    {
        $data = ['discount_percent' => '12.50', 'discount_scope' => 'payment', 'discount_centavos' => 99999];
        $this->record($data)->assertRedirect();
        $this->record($data)->assertRedirect();
        $payment = Payment::sole();
        $this->assertSame(21879, $payment->amount_centavos);
        $this->assertSame(3126, $payment->discount_centavos);
        $this->assertSame(25005, $payment->discount_base_centavos);
        $this->assertSame(1, AuditLog::where('action', 'payment.recorded')->count());
        $this->get(route('payments.create', $this->member))->assertInertia(fn (Assert $page) => $page->where('subscriptions.data.0.balanceCentavos', 74995));
        $this->get(route('payments.overview'))->assertInertia(fn (Assert $page) => $page->where('summary.revenueCentavos', 21879)->where('summary.outstandingCentavos', 74995));
        $this->get(route('payments.show', $payment))->assertInertia(fn (Assert $page) => $page->where('record.discountPercent', 12.5)->where('record.discountCentavos', 3126));
        $this->record(['discount_percent' => '12.51', 'discount_scope' => 'payment'])->assertSessionHasErrors('request_key');
        $this->patch(route('payments.void', $payment), ['void_reason' => 'Discount correction'])->assertRedirect();
        $this->get(route('payments.overview'))->assertInertia(fn (Assert $page) => $page->where('summary.revenueCentavos', 0)->where('summary.outstandingCentavos', 100000));
    }

    public function test_each_payment_has_its_own_discount_even_if_client_requests_membership_scope(): void
    {
        $discount = ['discount_percent' => '10', 'discount_scope' => 'membership', 'amount_php' => '250.00'];
        $this->record($discount)->assertRedirect();
        $this->record($discount)->assertRedirect();
        $payment = Payment::sole();
        $this->assertSame('payment', $payment->discount_scope);
        $this->assertSame(2500, $payment->discount_centavos);
        $this->assertSame(22500, $payment->amount_centavos);
        $this->assertSame(100000, $payment->subscription->price_centavos);
        $this->get(route('payments.create', $this->member))->assertInertia(fn (Assert $page) => $page->where('subscriptions.data.0.balanceCentavos', 75000));
        $this->record(['amount_php' => '750.01', 'request_key' => (string) Str::uuid()])->assertSessionHasErrors('amount_php');
        $this->record(['amount_php' => '750.00', 'discount_percent' => '10', 'request_key' => (string) Str::uuid()])->assertRedirect();
        $this->get(route('payments.overview'))->assertInertia(fn (Assert $page) => $page->where('summary.revenueCentavos', 90000)->where('summary.outstandingCentavos', 0));
        $this->patch(route('payments.void', $payment), ['void_reason' => 'Correct sale'])->assertRedirect();
        $this->get(route('payments.create', $this->member))->assertInertia(fn (Assert $page) => $page->where('subscriptions.data.0.balanceCentavos', 25000));
    }

    public function test_legacy_membership_discount_history_remains_readable_and_voidable(): void
    {
        $this->record()->assertRedirect();
        $record = Payment::sole();
        // Historical whole-membership records remain unchanged by the new recording flow.
        $legacy = new Payment;
        $legacy->forceFill([
            'member_id' => $this->member->id, 'member_subscription_id' => $this->data['member_subscription_id'],
            'branch_id' => $record->branch_id, 'recorded_by' => auth()->id(), 'amount_centavos' => 10000,
            'method' => 'cash', 'paid_at' => now()->utc(), 'request_key' => (string) Str::uuid(),
            'discount_scope' => 'membership', 'discount_base_centavos' => 100000,
            'discount_basis_points' => 1000, 'discount_centavos' => 10000,
        ])->save();
        $this->get(route('payments.show', $legacy))->assertInertia(fn (Assert $page) => $page->where('record.discountScope', 'membership')->where('record.discountCentavos', 10000));
        $this->get(route('payments.create', $this->member))->assertInertia(fn (Assert $page) => $page->where('subscriptions.data.0.balanceCentavos', 54995));
        $this->patch(route('payments.void', $legacy), ['void_reason' => 'Legacy correction'])->assertRedirect();
        $this->get(route('payments.create', $this->member))->assertInertia(fn (Assert $page) => $page->where('subscriptions.data.0.balanceCentavos', 74995));
    }

    public function test_full_discount_and_invalid_percentages_are_handled_without_fake_revenue(): void
    {
        foreach (['-1', '100.01', '101', '1.234', 'invalid'] as $percent) {
            $this->record(['discount_percent' => $percent])->assertSessionHasErrors('discount_percent');
        }
        $this->record(['discount_percent' => '100', 'amount_php' => '0'])->assertSessionHasErrors('amount_php');
        $this->record(['discount_percent' => '100', 'amount_php' => '1000'])->assertRedirect();
        $payment = Payment::sole();
        $this->assertSame(0, $payment->amount_centavos);
        $this->assertSame(100000, $payment->discount_centavos);
        $this->get(route('payments.overview'))->assertInertia(fn (Assert $page) => $page->where('summary.revenueCentavos', 0)->where('summary.outstandingCentavos', 0));
        $this->patch(route('payments.void', $payment), ['void_reason' => 'Reverse waiver'])->assertRedirect();
        $this->record(['discount_percent' => '100', 'discount_scope' => 'payment', 'amount_php' => '1000', 'request_key' => (string) Str::uuid()])->assertRedirect();
        $this->get(route('payments.create', $this->member))->assertInertia(fn (Assert $page) => $page->where('subscriptions.data.0.balanceCentavos', 0));
    }

    public function test_discounts_preserve_tenant_isolation_and_immutable_history(): void
    {
        $this->record(['discount_percent' => '10', 'discount_scope' => 'payment'])->assertRedirect();
        $payment = Payment::sole();
        foreach (['discount_centavos' => 1, 'discount_basis_points' => 2000, 'discount_scope' => 'membership', 'discount_base_centavos' => 999] as $field => $value) {
            try {
                $payment->fresh()->forceFill([$field => $value])->save();
                $this->fail('Discount history must be immutable.');
            } catch (\LogicException $exception) {
                $this->assertStringContainsString('immutable', $exception->getMessage());
            }
        }
        $other = Gym::factory()->create();
        $this->actingAs(User::factory()->memberOf($other, GymRole::Owner)->create());
        $this->record(['discount_percent' => '10'])->assertNotFound();
        $this->get(route('payments.show', $payment))->assertNotFound();
    }

    public function test_inline_profile_payment_uses_existing_balance_and_feature_restrictions(): void
    {
        $this->record(['return_to_profile' => true])->assertRedirect(route('members.show', ['member' => $this->member->id, 'tab' => 'payment']));
        $this->get(route('members.show', $this->member))->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/members/Subscriptions')
            ->where('paymentForm.subscriptions.data.0.balanceCentavos', 74995)
            ->where('paymentHistory.payments.data.0.amountCentavos', 25005)
            ->where('paymentForm.subscriptions.current_page', 1)
            ->where('paymentHistory.payments.current_page', 1)
            ->missing('member.portal_password'));
        $this->get(route('members.show', ['member' => $this->member->id, 'payments_page' => 2]))->assertInertia(fn (Assert $page) => $page
            ->where('paymentHistory.payments.current_page', 2)
            ->where('paymentForm.subscriptions.current_page', 1)->where('subscriptions.current_page', 1));
        Feature::for($this->gym)->deactivate('payments');
        $this->get(route('members.show', $this->member))->assertInertia(fn (Assert $page) => $page->where('paymentForm', null)->where('paymentHistory', null));
        $this->record(['return_to_profile' => true])->assertNotFound();
    }

    public function test_partial_payment_is_exact_server_owned_audited_and_idempotent(): void
    {
        $this->record(['gym_id' => 999, 'recorded_by' => 999, 'currency' => 'USD', 'paid_at' => '2000-01-01'])->assertRedirect(route('payments.create', $this->member));
        $this->record()->assertRedirect();
        $payment = Payment::sole();
        $this->assertSame(25005, $payment->amount_centavos);
        $this->assertSame($this->gym->id, $payment->gym_id);
        $this->assertSame(auth()->id(), $payment->recorded_by);
        $this->assertSame('PHP', $payment->currency);
        $this->assertSame(1, AuditLog::where('action', 'payment.recorded')->count());
        $this->get(route('payments.create', $this->member))->assertInertia(fn (Assert $page) => $page->component('dashboard/payments/Create')->where('subscriptions.data.0.balanceCentavos', 74995)->missing('payments'));
        $this->record(['amount_php' => '250.06'])->assertSessionHasErrors('request_key');
    }

    public function test_void_requires_reason_preserves_details_restores_balance_and_is_idempotent(): void
    {
        $this->record()->assertRedirect();
        $payment = Payment::sole();
        $url = route('payments.void', $payment);
        $this->patch($url, ['void_reason' => '   '])->assertSessionHasErrors('void_reason');
        $this->patch($url, ['void_reason' => str_repeat('x', 501)])->assertSessionHasErrors('void_reason');
        $this->patch($url, ['void_reason' => 'Entered twice', 'voided_by' => 999])->assertRedirect();
        $this->patch($url, ['void_reason' => 'Entered twice'])->assertRedirect();
        $payment->refresh();
        $this->assertSame('voided', $payment->status->value);
        $this->assertSame(25005, $payment->amount_centavos);
        $this->assertSame(auth()->id(), $payment->voided_by);
        $this->assertNotNull($payment->voided_at);
        $this->assertSame(1, AuditLog::where('action', 'payment.voided')->count());
        $this->patch($url, ['void_reason' => 'Changed reason'])->assertSessionHasErrors('void_reason');
        $this->get(route('payments.create', $this->member))->assertInertia(fn (Assert $page) => $page->where('subscriptions.data.0.balanceCentavos', 100000));
        $this->get(route('payments.index', $this->member))->assertInertia(fn (Assert $page) => $page->where('payments.data.0.status', 'voided')->where('payments.data.0.canVoid', false));
    }

    public function test_overview_excludes_voided_revenue_and_restores_unpaid_and_overdue_balances(): void
    {
        $this->record();
        $this->get(route('payments.overview'))->assertInertia(fn (Assert $page) => $page->component('dashboard/payments/Overview')
            ->where('summary.revenueCentavos', 25005)->where('summary.outstandingCentavos', 74995)->where('balances.data.0.overdue', true));
        $this->patch(route('payments.void', Payment::sole()), ['void_reason' => 'Test correction']);
        $this->get(route('payments.overview', ['balance' => 'overdue']))->assertInertia(fn (Assert $page) => $page
            ->where('summary.revenueCentavos', 0)->where('summary.outstandingCentavos', 100000)->where('payments.data.0.status', 'voided'));
    }

    public function test_printable_record_has_explicit_peso_props_and_void_disclosure(): void
    {
        $this->record(['reference' => 'TEST-REFERENCE']);
        $payment = Payment::sole();
        $this->get(route('payments.show', $payment))->assertInertia(fn (Assert $page) => $page->component('dashboard/payments/Record')
            ->where('record.amountCentavos', 25005)->where('record.reference', 'TEST-REFERENCE')
            ->where('record.status', 'recorded')->missing('record.recorded_by')->missing('record.request_key')->missing('record.email')->missing('record.mobile'));
        $this->patch(route('payments.void', $payment), ['void_reason' => 'Test correction']);
        $this->get(route('payments.show', $payment))->assertInertia(fn (Assert $page) => $page->where('record.status', 'voided')->where('record.voidReason', 'Test correction'));
        Feature::for($this->gym)->deactivate('payments');
        $this->get(route('payments.show', $payment))->assertNotFound();
    }

    public function test_record_is_blocked_for_coach_and_manager_in_another_branch(): void
    {
        $this->record();
        $payment = Payment::sole();
        $this->actingAs(User::factory()->memberOf($this->gym, GymRole::Coach)->create())->get(route('payments.show', $payment))->assertForbidden();
        $this->gym->subscription->update(['plan' => 'multi_branch']);
        $manager = User::factory()->memberOf($this->gym, GymRole::BranchManager)->create();
        $branch = Branch::factory()->create();
        $this->gym->users()->updateExistingPivot($manager->id, ['branch_id' => $branch->id]);
        $this->actingAs($manager)->get(route('payments.show', $payment))->assertNotFound();
    }

    public function test_revenue_uses_manila_midnight_and_leap_month_boundaries(): void
    {
        $this->travelTo('2028-02-29 04:00:00');
        foreach (['2028-02-28 15:59:59', '2028-02-28 16:00:00', '2028-02-29 15:59:59', '2028-02-29 16:00:00'] as $at) {
            $payment = new Payment;
            $payment->forceFill(['member_id' => $this->member->id, 'member_subscription_id' => $this->data['member_subscription_id'], 'branch_id' => $this->member->home_branch_id, 'amount_centavos' => 100, 'method' => 'cash', 'recorded_by' => auth()->id(), 'paid_at' => $at, 'request_key' => (string) Str::uuid()])->save();
        }
        $this->get(route('payments.overview', ['date' => '2028-02-29', 'view' => 'day']))->assertInertia(fn (Assert $page) => $page->where('summary.revenueCentavos', 200)->has('payments.data', 2));
        $this->get(route('payments.overview', ['date' => '2028-02-29', 'view' => 'month']))->assertInertia(fn (Assert $page) => $page->where('summary.revenueCentavos', 300)->has('payments.data', 3));
    }

    public function test_scheduled_unpaid_is_not_overdue_cancelled_is_excluded_and_free_plans_have_no_balance(): void
    {
        $member = Member::factory()->create();
        $plan = MembershipPlan::factory()->create(['price_centavos' => 12300]);
        app(ManageMemberSubscription::class)->assign($member, ['membership_plan_id' => $plan->id, 'operation' => 'assign', 'starts_on' => now()->addDays(10)->toDateString(), 'request_key' => (string) Str::uuid()]);
        $this->member->subscriptions()->sole()->forceFill(['status' => 'cancelled'])->save();
        $this->get(route('payments.overview'))->assertInertia(fn (Assert $page) => $page->where('summary.outstandingCentavos', 12300)->has('balances.data', 1)->where('balances.data.0.overdue', false));
        $this->get(route('payments.overview', ['balance' => 'overdue']))->assertInertia(fn (Assert $page) => $page->where('summary.outstandingCentavos', 0)->has('balances.data', 0));
        $free = Member::factory()->create();
        app(ManageMemberSubscription::class)->assign($free, ['membership_plan_id' => MembershipPlan::factory()->create(['price_centavos' => 0])->id, 'operation' => 'assign', 'starts_on' => now()->toDateString(), 'request_key' => (string) Str::uuid()]);
        $this->get(route('payments.overview', ['balance' => 'overdue']))->assertInertia(fn (Assert $page) => $page->has('balances.data', 0));
    }

    public function test_overview_paginates_and_rejects_bad_filters_or_unauthorized_roles(): void
    {
        for ($index = 0; $index < 26; $index++) {
            $this->record(['amount_php' => '0.01', 'request_key' => (string) Str::uuid()]);
        }
        $this->get(route('payments.overview'))->assertInertia(fn (Assert $page) => $page->has('payments.data', 25)->where('payments.total', 26)->where('summary.revenueCentavos', 26));
        $this->get(route('payments.overview', ['payment_page' => 2]))->assertInertia(fn (Assert $page) => $page->has('payments.data', 1));
        $this->get(route('payments.overview', ['date' => '2028-02-30']))->assertSessionHasErrors('date');
        $this->get(route('payments.overview', ['view' => 'year']))->assertSessionHasErrors('view');
        $this->actingAs(User::factory()->memberOf($this->gym, GymRole::Coach)->create())->get(route('payments.overview'))->assertForbidden();
    }

    public function test_manager_revenue_follows_recorded_branch_but_balances_follow_current_home_branch(): void
    {
        $this->record();
        $branch = Branch::factory()->create();
        $this->member->update(['home_branch_id' => $branch->id]);
        $this->gym->subscription->update(['plan' => 'multi_branch']);
        $manager = User::factory()->memberOf($this->gym, GymRole::BranchManager)->create();
        $this->actingAs($manager)->get(route('payments.overview'))->assertInertia(fn (Assert $page) => $page->where('summary.revenueCentavos', 25005)->has('balances.data', 0));
        $this->get(route('payments.overview', ['branch_id' => $branch->id]))->assertNotFound();
    }

    public function test_void_is_blocked_for_coach_foreign_branch_and_read_only_branch(): void
    {
        $this->record();
        $payment = Payment::sole();
        $staff = auth()->user();
        $this->actingAs(User::factory()->memberOf($this->gym, GymRole::Coach)->create());
        $this->patch(route('payments.void', $payment), ['void_reason' => 'No access'])->assertForbidden();
        $this->actingAs($staff);
        $this->member->homeBranch->forceFill(['is_active' => false])->save();
        $this->patch(route('payments.void', $payment), ['void_reason' => 'Read only'])->assertSessionHasErrors('branch_id');
        $this->gym->subscription->update(['plan' => 'multi_branch']);
        $manager = User::factory()->memberOf($this->gym, GymRole::BranchManager)->create();
        $other = Branch::factory()->create();
        $this->gym->users()->updateExistingPivot($manager->id, ['branch_id' => $other->id]);
        $this->actingAs($manager)->patch(route('payments.void', $payment), ['void_reason' => 'Wrong branch'])->assertNotFound();
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'recorded']);
    }

    public function test_void_rolls_back_on_audit_failure(): void
    {
        $this->record();
        $payment = Payment::sole();
        AuditLog::creating(fn () => throw new RuntimeException('Audit unavailable'));
        try {
            $this->withoutExceptionHandling()->patch(route('payments.void', $payment), ['void_reason' => 'Mistake']);
            $this->fail('Void without audit accepted.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
            $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'recorded', 'void_reason' => null]);
        }
    }

    public function test_overpayment_zero_and_invalid_decimal_forms_are_rejected(): void
    {
        foreach (['0', '1000.01', '-1', '1.001', '1e3', '1,000', '99999999'] as $amount) {
            $this->record(['amount_php' => $amount])->assertSessionHasErrors('amount_php');
        }
        $this->assertDatabaseCount('payments', 0);
        $this->record(['amount_php' => '1000'])->assertRedirect();
        $this->record(['request_key' => (string) Str::uuid(), 'amount_php' => '0.01'])->assertSessionHasErrors('amount_php');
    }

    public function test_foreign_and_other_members_subscriptions_are_rejected(): void
    {
        $other = Member::factory()->create();
        $this->post(route('payments.store', $other), $this->data)->assertNotFound();
        $foreign = Gym::factory()->create();
        $user = User::factory()->memberOf($foreign)->create();
        $this->actingAs($user)->get(route('payments.create', $this->member))->assertNotFound();
        $this->record()->assertNotFound();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_cancelled_membership_and_read_only_branch_reject_new_payments(): void
    {
        $period = $this->member->subscriptions()->sole();
        $period->forceFill(['status' => 'cancelled'])->save();
        $this->record()->assertSessionHasErrors('member_subscription_id');
        $this->member->homeBranch->forceFill(['is_active' => false])->save();
        $this->record()->assertSessionHasErrors('branch_id');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_coaches_feature_off_and_managers_foreign_branch_are_blocked(): void
    {
        $this->actingAs(User::factory()->memberOf($this->gym, GymRole::Coach)->create());
        $this->get(route('payments.create', $this->member))->assertForbidden();
        $this->record()->assertForbidden();
        $this->gym->subscription->update(['plan' => 'multi_branch']);
        $manager = User::factory()->memberOf($this->gym, GymRole::BranchManager)->create();
        $this->actingAs($manager);
        $this->record()->assertRedirect();
        app(CurrentGym::class)->runAs($this->gym, function () {
            $branch = Branch::factory()->create();
            $this->member->update(['home_branch_id' => $branch->id]);
        });
        $this->record()->assertNotFound();
        Feature::for($this->gym)->deactivate('payments');
        $this->get(route('payments.create', $this->member))->assertNotFound();
    }

    public function test_transaction_rolls_back_when_audit_fails(): void
    {
        AuditLog::creating(fn () => throw new RuntimeException('Audit unavailable'));
        try {
            $this->withoutExceptionHandling()->record();
            $this->fail('Missing audit accepted.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
            $this->assertDatabaseCount('payments', 0);
        }
    }

    public function test_same_key_cannot_be_used_for_a_different_member_and_retries_survive_branch_downgrades(): void
    {
        $this->record()->assertRedirect();
        $other = Member::factory()->create();
        $this->post(route('payments.store', $other), $this->data)->assertSessionHasErrors('request_key');
        $this->member->homeBranch->forceFill(['is_active' => false])->save();
        $this->record()->assertRedirect();
        $this->assertDatabaseCount('payments', 1);
    }
}
