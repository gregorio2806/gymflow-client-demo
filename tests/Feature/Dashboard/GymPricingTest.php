<?php

namespace Tests\Feature\Dashboard;

use App\Enums\GymRole;
use App\Enums\Plan;
use App\Models\Branch;
use App\Models\CoachSchedule;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberPurchase;
use App\Models\MembershipPlan;
use App\Models\MemberSubscription;
use App\Models\Payment;
use App\Models\PricingDiscount;
use App\Models\SessionRedemption;
use App\Models\User;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GymPricingTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-08 12:00', 'Asia/Manila'));
        $this->gym = Gym::factory()->create();
        $this->gym->subscription->update(['plan' => Plan::MultiBranch]);
        $this->actingAs(User::factory()->memberOf($this->gym, GymRole::Owner)->create());
        app(CurrentGym::class)->set($this->gym);
    }

    private function offering(string $kind, string $price, array $configuration = []): MembershipPlan
    {
        $this->post(route('membership-plans.store'), ['kind' => $kind, 'name' => 'Fictional '.$kind, 'price_php' => $price, 'duration_amount' => $kind === 'benefit' ? 12 : 30, 'duration_unit' => $kind === 'benefit' ? 'months' : 'days', 'configuration' => $configuration])->assertSessionHasNoErrors();

        return MembershipPlan::withoutGlobalScope('access-products')->latest('id')->firstOrFail();
    }

    private function cart(array $extra = []): array
    {
        return array_replace(['pricing_checkout' => true, 'starts_on' => '2026-10-08', 'request_key' => (string) Str::uuid()], $extra);
    }

    public function test_registration_prices_mixed_cart_at_1499_and_preserves_snapshots_and_retries(): void
    {
        $access = $this->offering('subscription', '1000');
        $benefit = $this->offering('benefit', '599', ['discount_unit' => 'percentage', 'discount_input' => '10']);
        $cart = $this->cart(['membership_plan_id' => $access->id, 'benefit_plan_id' => $benefit->id]);
        $this->postJson(route('pricing.quote'), $cart)->assertOk()->assertJsonPath('total_centavos', 149900)->assertJsonPath('lines.1.total_centavos', 59900);
        $data = [...$cart, 'name' => 'Fictional checkout member', 'paid_php' => '500', 'method' => 'cash'];
        $this->post(route('members.store'), $data)->assertSessionHasNoErrors();
        $this->post(route('members.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('members', 1);
        $this->assertDatabaseCount('member_subscriptions', 1);
        $this->assertDatabaseCount('member_purchases', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(90000, MemberSubscription::sole()->price_centavos);
        $this->assertSame('', Member::sole()->mobile);
        $this->assertNull(Member::sole()->email);
        $this->assertNull(Member::sole()->privacy_consented_at);
        $this->assertNull(Member::sole()->privacy_recorded_by);
        $this->post(route('members.store'), [...$data, 'name' => 'Different name'])->assertSessionHasErrors('request_key');
        $this->patch(route('membership-plans.update', $access), ['kind' => 'subscription', 'name' => 'Updated price', 'price_php' => '2000', 'duration_amount' => 7, 'duration_unit' => 'days'])->assertSessionHasNoErrors();
        $this->assertSame(100000, MemberSubscription::sole()->original_price_centavos);
        $this->assertSame('Fictional subscription', MemberSubscription::sole()->plan_name);
        $this->get(route('purchases.index', Member::sole()))->assertInertia(fn (Assert $p) => $p->where('purchases.data.0.balance', 59900)->where('member.reservedPeriods.0.kind', 'subscription')->where('member.reservedPeriods.1.kind', 'benefit'));
        $purchase = MemberPurchase::sole();
        $member = Member::sole();
        $this->get(route('payments.create', $member))->assertInertia(fn (Assert $p) => $p->where('subscriptions.data.0.balanceCentavos', 40000)->where('purchases.data.0.balanceCentavos', 59900)->where('purchases.data.0.kind', 'benefit'));
        $this->get(route('payments.overview'))->assertInertia(fn (Assert $p) => $p->where('summary.outstandingCentavos', 99900)->has('balances.data', 2));
        $this->post(route('payments.store', $member), ['member_purchase_id' => $purchase->id, 'amount_php' => '200', 'method' => 'cash', 'return_to_profile' => true, 'request_key' => (string) Str::uuid()])->assertRedirect(route('members.show', ['member' => $member->id, 'tab' => 'payment']));
        $this->get(route('members.show', $member))->assertInertia(fn (Assert $p) => $p->where('paymentForm.purchases.data.0.balanceCentavos', 39900)->where('paymentHistory.payments.data.0.planName', $purchase->plan_name));
    }

    public function test_discount_stacking_fixed_caps_eligibility_and_statutory_vat_are_server_owned(): void
    {
        $access = $this->offering('subscription', '1000');
        $benefit = $this->offering('benefit', '599', ['discount_unit' => 'percentage', 'discount_input' => '10']);
        $this->post(route('pricing.discounts.store'), ['name' => 'Student', 'category' => 'student', 'unit' => 'percentage', 'input' => '5', 'requires_verification' => true, 'is_active' => true, 'product_kinds' => ['subscription']])->assertSessionHasNoErrors();
        $student = PricingDiscount::sole();
        $cart = $this->cart(['membership_plan_id' => $access->id, 'benefit_plan_id' => $benefit->id, 'pricing_discount_id' => $student->id, 'eligibility_verified' => true]);
        $this->postJson(route('pricing.quote'), [...$cart, 'eligibility_verified' => false])->assertUnprocessable()->assertJsonValidationErrors('eligibility_verified');
        $this->postJson(route('pricing.quote'), $cart)->assertOk()->assertJsonPath('total_centavos', 149900);
        $this->patch(route('pricing.settings'), ['require_purchase' => true, 'packages_require_access' => false, 'promotion_stacking' => 'additive', 'tax_profile' => 'unconfirmed'])->assertSessionHasNoErrors();
        $this->postJson(route('pricing.quote'), $cart)->assertOk()->assertJsonPath('total_centavos', 144900);
        $this->patch(route('pricing.discounts.update', $student), ['name' => 'Student', 'category' => 'student', 'unit' => 'peso', 'input' => '9999', 'requires_verification' => true, 'is_active' => true, 'product_kinds' => ['subscription']])->assertSessionHasNoErrors();
        $this->postJson(route('pricing.quote'), $cart)->assertOk()->assertJsonPath('total_centavos', 59900);
        $this->post(route('pricing.discounts.store'), ['name' => 'PWD', 'category' => 'pwd', 'unit' => 'peso', 'input' => '1', 'requires_verification' => false, 'is_active' => true, 'product_kinds' => ['benefit']])->assertSessionHasNoErrors();
        $pwd = PricingDiscount::where('category', 'pwd')->firstOrFail();
        $this->assertSame(2000, $pwd->value);
        $this->assertTrue($pwd->requires_verification);
        $this->postJson(route('pricing.quote'), [...$cart, 'pricing_discount_id' => $pwd->id])->assertUnprocessable()->assertJsonValidationErrors('pricing_discount_id');
        $this->patch(route('pricing.settings'), ['require_purchase' => true, 'packages_require_access' => false, 'promotion_stacking' => 'additive', 'tax_profile' => 'vat-inclusive'])->assertSessionHasNoErrors();
        $vatAccess = $this->offering('subscription', '1120');
        $this->postJson(route('pricing.quote'), [...$cart, 'membership_plan_id' => $vatAccess->id, 'pricing_discount_id' => $pwd->id])->assertOk()->assertJsonPath('lines.0.total_centavos', 80000)->assertJsonPath('lines.0.discounts.0.vat_exemption_centavos', 12000)->assertJsonPath('lines.1.total_centavos', 59900);
    }

    public function test_optional_purchase_policy_and_non_access_offerings_do_not_grant_access(): void
    {
        $this->post(route('members.store'), [...$this->cart(), 'name' => 'No purchase', 'privacy_consent' => true])->assertSessionHasErrors('membership_plan_id');
        $this->assertDatabaseCount('members', 0);
        $this->patch(route('pricing.settings'), ['require_purchase' => false, 'packages_require_access' => false, 'promotion_stacking' => 'none', 'tax_profile' => 'non-vat'])->assertSessionHasNoErrors();
        $this->post(route('members.store'), [...$this->cart(), 'name' => 'No purchase', 'privacy_consent' => true])->assertSessionHasNoErrors();
        $benefit = $this->offering('benefit', '599', ['discount_unit' => 'peso', 'discount_input' => '50']);
        $member = Member::sole();
        $this->post(route('purchases.store', $member), $this->cart(['benefit_plan_id' => $benefit->id]))->assertSessionHasNoErrors();
        $this->assertSame(0, MembershipPlan::count());
        $this->assertDatabaseCount('member_subscriptions', 0);
        $this->postJson(route('check-in.store'), ['member_id' => $member->id, 'branch_id' => $this->gym->mainBranch()->id])->assertUnprocessable();
        $this->post(route('purchases.store', $member), $this->cart(['benefit_plan_id' => $benefit->id]))->assertSessionHasErrors('benefit_starts_on');
    }

    public function test_training_package_uses_one_name_and_supports_day_or_session_validity(): void
    {
        $sessionPackage = $this->offering('package', '600', ['duration_mode' => 'sessions', 'sessions' => 3]);
        $this->assertSame('sessions', $sessionPackage->configuration['duration_mode']);
        $this->assertArrayNotHasKey('service', $sessionPackage->configuration);
        $this->postJson(route('pricing.quote'), $this->cart(['package_ids' => [$sessionPackage->id]]))->assertOk()->assertJsonPath('lines.0.ends_at', null);

        $dayPackage = $this->offering('package', '600', ['duration_mode' => 'days', 'sessions' => 3]);
        $this->assertSame('days', $dayPackage->configuration['duration_mode']);
        $this->postJson(route('pricing.quote'), $this->cart(['package_ids' => [$dayPackage->id]]))->assertOk()->assertJsonPath('lines.0.ends_at', '2026-11-06T16:00:00+00:00');
    }

    public function test_active_benefits_price_renewals_and_eligible_packages_without_changing_old_terms(): void
    {
        $access = $this->offering('subscription', '1000');
        $package = $this->offering('package', '600', ['service' => 'Workout', 'sessions' => 3]);
        $benefit = $this->offering('benefit', '599', ['discount_unit' => 'percentage', 'discount_input' => '10', 'eligible_plan_ids' => [$access->id], 'applies_to_packages' => true]);
        $member = Member::factory()->create();
        $this->post(route('purchases.store', $member), $this->cart(['membership_plan_id' => $access->id, 'benefit_plan_id' => $benefit->id]))->assertSessionHasNoErrors();
        $this->postJson(route('pricing.quote'), [...$this->cart(['membership_plan_id' => $access->id, 'starts_on' => '2026-10-20']), 'member_id' => $member->id])->assertUnprocessable()->assertJsonValidationErrors('starts_on');
        $this->postJson(route('pricing.quote'), [...$this->cart(['package_ids' => [$package->id]]), 'member_id' => $member->id])->assertOk()->assertJsonPath('total_centavos', 54000);
        $renewal = ['membership_plan_id' => $access->id, 'operation' => 'renew', 'request_key' => (string) Str::uuid()];
        $this->post(route('member-subscriptions.store', $member), $renewal)->assertSessionHasNoErrors();
        $this->post(route('member-subscriptions.store', $member), $renewal)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('member_subscriptions', 2);
        $this->assertSame(90000, MemberSubscription::latest('id')->firstOrFail()->price_centavos);
        $this->assertSame(100000, MemberSubscription::latest('id')->firstOrFail()->original_price_centavos);
        $this->postJson(route('pricing.quote'), [...$this->cart(['membership_plan_id' => $access->id, 'starts_on' => '2027-10-08']), 'member_id' => $member->id])->assertOk()->assertJsonPath('total_centavos', 100000);
    }

    public function test_existing_member_can_renew_as_unpaid_or_paid(): void
    {
        $access = $this->offering('subscription', '1000');
        $member = Member::factory()->create();
        $this->post(route('purchases.store', $member), $this->cart(['membership_plan_id' => $access->id]))->assertSessionHasNoErrors();
        $this->get(route('purchases.index', $member))->assertInertia(fn (Assert $page) => $page->where('canSave', true)->where('renewalAvailable', true));

        $this->post(route('purchases.store', $member), $this->cart(['membership_plan_id' => $access->id, 'operation' => 'renew', 'paid_php' => '', 'method' => 'cash']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('member_subscriptions', 2);
        $this->assertDatabaseCount('payments', 0);
        $this->get(route('payments.create', $member))->assertInertia(fn (Assert $page) => $page->where('subscriptions.data.0.balanceCentavos', 100000));
        $renewal = [
            ...$this->cart(['membership_plan_id' => $access->id, 'operation' => 'renew', 'paid_php' => '1000', 'method' => 'cash']),
            'name' => '', 'mobile' => '', 'email' => '', 'pricing_checkout' => true,
            'benefit_plan_id' => '', 'package_ids' => [], 'benefit_starts_on' => '2026-10-08',
            'pricing_discount_id' => '', 'eligibility_verified' => false,
            'eligibility_from' => '', 'eligibility_through' => '', 'reference' => '',
        ];
        $this->postJson(route('pricing.quote'), [...$renewal, 'member_id' => $member->id])->assertOk()->assertJsonPath('total_centavos', 100000);
        $this->post(route('purchases.store', $member), $renewal)->assertSessionHasNoErrors();
        $this->post(route('purchases.store', $member), $renewal)->assertSessionHasNoErrors();

        $this->assertDatabaseCount('member_subscriptions', 3);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('renew', MemberSubscription::latest('id')->firstOrFail()->operation);
        $this->assertSame(100000, Payment::sole()->amount_centavos);
        $this->assertSame(MemberSubscription::latest('id')->firstOrFail()->id, Payment::sole()->member_subscription_id);
    }

    public function test_packages_redeem_once_on_completion_not_cancellation_and_require_full_payment(): void
    {
        $package = $this->offering('package', '600', ['service' => 'Workout', 'sessions' => 3]);
        $member = Member::factory()->create();
        $this->post(route('purchases.store', $member), $this->cart(['package_ids' => [$package->id], 'paid_php' => '300', 'method' => 'cash']))->assertSessionHasNoErrors();
        $purchase = MemberPurchase::sole();
        $coach = User::factory()->memberOf($this->gym, GymRole::Coach)->create();
        $this->gym->users()->updateExistingPivot($coach->id, ['availability' => array_fill(0, 7, ['enabled' => true, 'start' => '09:00', 'end' => '18:00'])]);
        $data = ['coach_id' => $coach->id, 'branch_id' => $this->gym->mainBranch()->id, 'member_id' => $member->id, 'member_purchase_id' => $purchase->id, 'service' => 'personal-training', 'starts_at' => '2026-10-08T09:00', 'ends_at' => '2026-10-08T10:00', 'request_key' => (string) Str::uuid()];
        $this->post(route('appointments.store'), $data)->assertSessionHasNoErrors();
        $schedule = CoachSchedule::sole();
        $this->patch(route('appointments.status', $schedule), ['revision' => 1, 'status' => 'completed'])->assertSessionHasErrors('member_purchase_id');
        $this->assertSame(0, $purchase->fresh()->used_sessions);
        $this->post(route('payments.store', $member), ['member_purchase_id' => $purchase->id, 'amount_php' => '300.01', 'method' => 'cash', 'request_key' => (string) Str::uuid()])->assertSessionHasErrors('amount_php');
        $this->post(route('payments.store', $member), ['member_purchase_id' => $purchase->id, 'amount_php' => '300', 'method' => 'cash', 'request_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->patch(route('appointments.status', $schedule), ['revision' => 1, 'status' => 'completed'])->assertSessionHasNoErrors();
        $this->patch(route('appointments.status', $schedule), ['revision' => 1, 'status' => 'completed'])->assertSessionHasNoErrors();
        $this->assertSame(1, $purchase->fresh()->used_sessions);
        $this->assertDatabaseCount('session_redemptions', 1);
        $this->get(route('appointments.index', ['date' => '2026-10-08']))->assertInertia(fn (Assert $p) => $p->where('appointments.data.0.packageName', $package->name));
        $this->post(route('appointments.store'), [...$data, 'starts_at' => '2026-10-08T10:00', 'ends_at' => '2026-10-08T11:00', 'request_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $cancelled = CoachSchedule::latest('id')->firstOrFail();
        $this->patch(route('appointments.status', $cancelled), ['revision' => 1, 'status' => 'cancelled'])->assertSessionHasNoErrors();
        $this->assertSame(2, $purchase->fresh()->remaining());
        $this->assertSame($purchase->id, SessionRedemption::sole()->member_purchase_id);
        $this->post(route('appointments.store'), [...$data, 'starts_at' => '2026-10-08T11:00', 'ends_at' => '2026-10-08T12:00', 'request_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $noShow = CoachSchedule::latest('id')->firstOrFail();
        $this->patch(route('appointments.status', $noShow), ['revision' => 1, 'status' => 'no-show'])->assertSessionHasNoErrors();
        $this->assertSame(2, $purchase->fresh()->remaining());
        $this->travel(31)->days();
        $this->post(route('appointments.store'), [...$data, 'starts_at' => '2026-11-08T09:00', 'ends_at' => '2026-11-08T10:00', 'request_key' => (string) Str::uuid()])->assertSessionHasErrors('member_purchase_id');
        $adjustment = ['action' => 'adjust', 'adjustment' => 1, 'expected_adjustment' => 0, 'expected_used' => 1, 'reason' => 'Fictional owner correction'];
        $this->patch(route('purchases.change', [$member, $purchase]), $adjustment)->assertSessionHasNoErrors();
        $this->patch(route('purchases.change', [$member, $purchase]), $adjustment)->assertSessionHasErrors('adjustment');
        $this->assertSame(3, $purchase->fresh()->remaining());
        $this->patch(route('purchases.change', [$member, $purchase]), ['action' => 'cancel', 'reason' => 'Fictional cancellation'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $purchase->fresh()->status);
        $this->assertSame(60000, (int) Payment::where('status', 'recorded')->sum('amount_centavos'));
        $this->get(route('purchases.index', $member))->assertInertia(fn (Assert $p) => $p->has('redemptions', 1)->where('purchases.data.0.updatedBy', auth()->user()->name));
    }

    public function test_archived_offerings_branch_restrictions_and_foreign_ids_are_rejected(): void
    {
        $access = $this->offering('subscription', '1000');
        $otherBranch = Branch::factory()->create();
        $access->forceFill(['branch_ids' => [$otherBranch->id]])->save();
        $this->postJson(route('pricing.quote'), $this->cart(['membership_plan_id' => $access->id]))->assertUnprocessable()->assertJsonValidationErrors('branch_id');
        $this->patch(route('membership-plans.activation', $access), ['is_active' => false])->assertSessionHasNoErrors();
        $this->postJson(route('pricing.quote'), $this->cart(['membership_plan_id' => $access->id]))->assertUnprocessable()->assertJsonValidationErrors('membership_plan_id');
        $foreign = Gym::factory()->create();
        $foreignPlan = app(CurrentGym::class)->runAs($foreign, fn () => MembershipPlan::factory()->create());
        $this->postJson(route('pricing.quote'), $this->cart(['membership_plan_id' => $foreignPlan->id]))->assertUnprocessable();
        $staff = User::factory()->memberOf($this->gym, GymRole::Staff)->create();
        $this->actingAs($staff)->patch(route('pricing.settings'), [])->assertForbidden();
        $this->post(route('pricing.discounts.store'), [])->assertForbidden();
    }
}
