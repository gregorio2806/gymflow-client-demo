<?php

namespace App\Actions;

use App\Enums\MemberSubscriptionStatus;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberPurchase;
use App\Models\MemberSubscription;
use App\Models\Payment;
use App\Support\Audit;
use App\Support\PaymentDiscount;
use App\Support\PlanLimits;
use App\Tenancy\CurrentGym;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RecordPayment
{
    public function handle(Member $member, array $data): Payment
    {
        $field = empty($data['member_purchase_id']) ? 'member_subscription_id' : 'member_purchase_id';
        if (empty($data[$field]) || (! empty($data['member_purchase_id']) && ! empty($data['member_subscription_id']))) {
            throw ValidationException::withMessages([$field => __('Choose exactly one purchase or subscription.')]);
        }
        [$pesos, $centavos] = array_pad(explode('.', $data['amount_php'], 2), 2, '');
        $amount = (int) $pesos * 100 + (int) str_pad($centavos, 2, '0');
        $points = PaymentDiscount::percentage((string) ($data['discount_percent'] ?? '0'));
        $scope = $points > 0 ? 'payment' : null;
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount_php' => __('Enter an amount greater than zero pesos.')]);
        }

        return DB::transaction(function () use ($member, $data, $amount, $points, $scope, $field) {
            $gym = Gym::whereKey(app(CurrentGym::class)->id())->lockForUpdate()->firstOrFail();
            $member = Member::lockForUpdate()->findOrFail($member->id);
            Gate::authorize('update', $member);
            $reference = $data['reference'] ?? null;
            $existing = Payment::withoutGlobalScope('branch')->where('gym_id', $gym->id)->where('request_key', $data['request_key'])->first();
            if ($existing) {
                if ($existing->member_id !== $member->id || $existing->{$field} !== (int) $data[$field]
                    || ($scope === 'payment' ? $existing->amount_centavos + $existing->discount_centavos : $existing->amount_centavos) !== $amount
                    || $existing->discount_basis_points !== $points || $existing->discount_scope !== $scope
                    || $existing->method->value !== $data['method'] || $existing->reference !== $reference) {
                    throw ValidationException::withMessages(['request_key' => __('This submission was already used for different payment details. Refresh and try again.')]);
                }

                return $existing;
            }
            $gym->subscription()->lockForUpdate()->firstOrFail();
            $model = $field === 'member_subscription_id' ? MemberSubscription::class : MemberPurchase::class;
            $subscription = $model::where('member_id', $member->id)->lockForUpdate()->findOrFail($data[$field]);
            $branchId = $field === 'member_purchase_id' ? $subscription->branch_id : $member->home_branch_id;
            app(PlanLimits::class)->ensureBranchWritable($gym, $branchId);
            if (in_array($subscription->status, [MemberSubscriptionStatus::Cancelled, 'cancelled'], true)) {
                throw ValidationException::withMessages([$field => __('Cannot record a payment for a cancelled purchase.')]);
            }
            $recorded = Payment::withoutGlobalScope('branch')->where('gym_id', $gym->id)->where($field, $subscription->id)->where('status', 'recorded');
            $base = $amount;
            $discount = PaymentDiscount::savings($base, $points);
            $received = $amount - $discount;
            $paid = (clone $recorded)->selectRaw('COALESCE(SUM(amount_centavos + discount_centavos), 0) AS settled')->first()->settled;
            if ($received + $discount <= 0) {
                throw ValidationException::withMessages(['amount_php' => __('Enter a payment or a discount greater than zero.')]);
            }
            if ($received + $discount > $subscription->price_centavos - (int) $paid) {
                throw ValidationException::withMessages(['amount_php' => __('The amount exceeds the remaining membership balance.')]);
            }
            $payment = new Payment;
            $payment->forceFill([
                'member_id' => $member->id, $field => $subscription->id, 'branch_id' => $branchId,
                'amount_centavos' => $received, 'discount_basis_points' => $points, 'discount_centavos' => $discount,
                'discount_scope' => $scope, 'discount_base_centavos' => $scope ? $base : null,
                'method' => $data['method'], 'reference' => $reference,
                'recorded_by' => auth()->id(), 'paid_at' => now()->utc(), 'request_key' => $data['request_key'],
            ])->save();
            Audit::log('payment.recorded', $payment, ['after' => $payment->only(['member_id', 'member_subscription_id', 'member_purchase_id', 'branch_id', 'amount_centavos', 'discount_basis_points', 'discount_centavos', 'discount_scope', 'discount_base_centavos', 'currency', 'method', 'paid_at', 'recorded_by', 'status'])]);

            return $payment;
        });
    }
}
