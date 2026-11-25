<?php

namespace Tests\Feature\Dashboard;

use App\Actions\ManageMemberSubscription;
use App\Enums\GymRole;
use App\Enums\PaymentStatus;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use App\Tenancy\CurrentGym;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class PaymentFoundationTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private array $details;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gym = Gym::factory()->create();
        app(CurrentGym::class)->set($this->gym);
        $owner = User::factory()->memberOf($this->gym, GymRole::Owner)->create();
        $this->actingAs($owner);
        $member = Member::factory()->create();
        $plan = MembershipPlan::factory()->create();
        $period = app(ManageMemberSubscription::class)->assign($member, ['membership_plan_id' => $plan->id, 'operation' => 'assign', 'starts_on' => now()->toDateString(), 'request_key' => (string) Str::uuid()]);
        $this->details = ['member_id' => $member->id, 'member_subscription_id' => $period->id, 'branch_id' => $member->home_branch_id, 'recorded_by' => $owner->id, 'amount_centavos' => 123456, 'method' => 'cash', 'paid_at' => now()->utc(), 'request_key' => (string) Str::uuid()];
    }

    private function payment(array $overrides = []): Payment
    {
        $payment = new Payment;
        $payment->forceFill(array_replace($this->details, $overrides))->save();

        return $payment;
    }

    public function test_payment_keeps_exact_peso_centavos_and_fails_closed_across_tenants(): void
    {
        $payment = $this->payment();
        $this->assertSame(123456, $payment->fresh()->amount_centavos);
        $this->assertSame('PHP', $payment->currency);
        $this->assertSame(PaymentStatus::Recorded, $payment->status);
        $this->assertSame($this->details['member_id'], $payment->member->id);
        app(CurrentGym::class)->set(Gym::factory()->create());
        $this->assertNull(Payment::find($payment->id));
        app(CurrentGym::class)->forget();
        $this->assertSame(0, Payment::count());
    }

    public function test_foreign_branch_and_subscription_are_rejected(): void
    {
        $foreign = Gym::factory()->create();
        $branch = app(CurrentGym::class)->runAs($foreign, fn () => Branch::factory()->create());
        $this->expectException(LogicException::class);
        $this->payment(['branch_id' => $branch->id]);
    }

    public function test_subscription_must_match_member(): void
    {
        $this->expectException(LogicException::class);
        $this->payment(['member_id' => Member::factory()->create()->id]);
    }

    public function test_foreign_recorder_is_rejected(): void
    {
        $foreign = Gym::factory()->create();
        $recorder = User::factory()->memberOf($foreign)->create();
        $this->expectException(LogicException::class);
        $this->payment(['recorded_by' => $recorder->id]);
    }

    public function test_manager_reads_only_recorded_branch_even_after_member_transfer(): void
    {
        $payment = $this->payment();
        $branch = Branch::factory()->create();
        $payment->member->update(['home_branch_id' => $branch->id]);
        app(CurrentGym::class)->set($this->gym, GymRole::BranchManager, $this->details['branch_id']);
        $this->assertSame($payment->id, Payment::sole()->id);
        $this->assertNotNull(Payment::sole()->member);
        app(CurrentGym::class)->set($this->gym, GymRole::BranchManager, $branch->id);
        $this->assertSame(0, Payment::count());
    }

    public function test_non_peso_currency_and_non_integer_amounts_are_rejected(): void
    {
        foreach ([['currency' => 'USD'], ['amount_centavos' => 12.34], ['amount_centavos' => 0], ['amount_centavos' => -1]] as $data) {
            try {
                $this->payment($data);
                $this->fail('Invalid payment accepted.');
            } catch (LogicException) {
                $this->assertSame(0, Payment::count());
            }
        }
    }

    public function test_agreed_details_cannot_be_edited_or_deleted(): void
    {
        $payment = $this->payment();
        try {
            $payment->forceFill(['amount_centavos' => 1])->save();
            $this->fail('Payment details changed.');
        } catch (LogicException) {
            $this->assertSame(123456, $payment->fresh()->amount_centavos);
        }
        $this->expectException(LogicException::class);
        $payment->delete();
    }

    public function test_voiding_requires_metadata_and_cannot_be_reversed(): void
    {
        $payment = $this->payment();
        try {
            $payment->forceFill(['status' => 'voided'])->save();
            $this->fail('Void without reason accepted.');
        } catch (LogicException) {
            $this->assertSame(PaymentStatus::Recorded, $payment->fresh()->status);
        }
        $payment->forceFill(['void_reason' => 'Recorded twice', 'voided_at' => now(), 'voided_by' => $this->details['recorded_by']])->save();
        $this->expectException(LogicException::class);
        $payment->forceFill(['status' => 'recorded'])->save();
    }

    public function test_submission_key_is_unique_per_gym(): void
    {
        $this->payment();
        $this->expectException(QueryException::class);
        $this->payment();
    }

    public function test_gym_erasure_cleans_payments_before_restricted_relations(): void
    {
        $this->payment();
        $this->gym->delete();
        $this->assertDatabaseCount('payments', 0);
    }
}
