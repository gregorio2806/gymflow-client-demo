<?php

namespace App\Actions;

use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberPurchase;
use App\Models\Payment;
use App\Models\PricingCheckout;
use App\Support\Audit;
use App\Support\PaymentDiscount;
use App\Support\PlanLimits;
use App\Tenancy\CurrentGym;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Pennant\Feature;

class PurchaseMemberOfferings
{
    public static function fingerprint(array $data): string
    {
        $allowed = [...array_keys(\App\Support\PricingCheckout::rules()), 'name', 'mobile', 'email', 'privacy_consent'];
        $values = Arr::only($data, array_filter($allowed, fn ($key) => ! str_contains($key, '*')));
        ksort($values);

        return hash('sha256', json_encode([$values, auth()->id()], JSON_THROW_ON_ERROR));
    }

    public function handle(Member $member, array $data): PricingCheckout
    {
        return DB::transaction(function () use ($member, $data) {
            $gym = Gym::whereKey(app(CurrentGym::class)->id())->lockForUpdate()->firstOrFail();
            $member = Member::lockForUpdate()->findOrFail($member->id);
            Gate::authorize('update', $member);
            $fingerprint = self::fingerprint($data);
            $existing = PricingCheckout::where('request_key', $data['request_key'])->first();
            if ($existing) {
                if ($existing->member_id !== $member->id || $existing->fingerprint !== $fingerprint) {
                    throw ValidationException::withMessages(['request_key' => __('This submission was already used. Refresh before changing purchase details.')]);
                }

                return $existing;
            }
            if (! $member->is_active) {
                throw ValidationException::withMessages(['membership_plan_id' => __('Activate this member before adding purchases.')]);
            }
            $summary = app(\App\Support\PricingCheckout::class)->quote($data, $member);
            app(PlanLimits::class)->ensureBranchWritable($gym, $summary['branch_id'], 'branch_id');
            $paid = empty($data['paid_php']) ? 0 : PaymentDiscount::pesos($data['paid_php']);
            if ($paid > $summary['total_centavos']) {
                throw ValidationException::withMessages(['paid_php' => __('The payment exceeds the checkout total.')]);
            }
            if ($paid > 0) {
                abort_unless(Feature::active('payments'), 404);
                Gate::authorize('viewAny', Payment::class);
            }
            if (array_key_exists('pricing_discount_id', $data)) {
                $verified = ! empty($data['pricing_discount_id']) && ($data['eligibility_verified'] ?? false);
                $member->forceFill(['pricing_discount_id' => $data['pricing_discount_id'] ?: null, 'eligibility_verified_at' => $verified ? now()->utc() : null, 'eligibility_verified_by' => $verified ? auth()->id() : null, 'eligibility_from' => $data['eligibility_from'] ?? null, 'eligibility_through' => $data['eligibility_through'] ?? null])->save();
                Audit::log('member.discount_eligibility', $member, ['after' => $member->only(['pricing_discount_id', 'eligibility_verified_at', 'eligibility_verified_by', 'eligibility_from', 'eligibility_through'])]);
            }
            foreach ($summary['lines'] as &$line) {
                if ($line['kind'] === 'subscription') {
                    $purchase = app(ManageMemberSubscription::class)->assign($member, ['membership_plan_id' => $line['plan_id'], 'starts_on' => $line['starts_on'], 'operation' => $data['operation'] ?? 'assign', 'request_key' => (string) Str::uuid(), 'discount_mode' => 'peso', 'discount_php' => self::pesos($line['original_centavos'] - $line['total_centavos']), 'discount_type' => 'regular', '_pricing_snapshot' => $line]);
                    $field = 'member_subscription_id';
                } else {
                    $purchase = new MemberPurchase;
                    $purchase->forceFill(['member_id' => $member->id, 'membership_plan_id' => $line['plan_id'], 'branch_id' => $summary['branch_id'], 'kind' => $line['kind'], 'plan_name' => $line['name'], 'price_centavos' => $line['total_centavos'], 'original_price_centavos' => $line['original_centavos'], 'starts_at' => $line['starts_at'], 'ends_at' => $line['ends_at'], 'snapshot' => $line, 'sessions' => $line['kind'] === 'package' ? $line['configuration']['sessions'] : 0, 'used_sessions' => 0, 'session_adjustment' => 0, 'created_by' => auth()->id(), 'request_key' => (string) Str::uuid(), 'status' => 'active'])->save();
                    Audit::log('purchase.created', $purchase, ['after' => $purchase->only(['member_id', 'kind', 'plan_name', 'price_centavos', 'original_price_centavos', 'starts_at', 'ends_at', 'sessions', 'snapshot', 'created_by'])]);
                    $field = 'member_purchase_id';
                }
                $line['purchase_id'] = $purchase->id;
                $line['payment_field'] = $field;
                $amount = min($paid, $line['total_centavos']);
                if ($amount > 0) {
                    app(RecordPayment::class)->handle($member, [$field => $purchase->id, 'amount_php' => self::pesos($amount), 'method' => $data['method'], 'reference' => $data['reference'] ?? null, 'request_key' => (string) Str::uuid()]);
                }
                $line['paid_centavos'] = $amount;
                $paid -= $amount;
            }
            unset($line);
            $checkout = new PricingCheckout;
            $checkout->forceFill(['member_id' => $member->id, 'request_key' => $data['request_key'], 'fingerprint' => $fingerprint, 'summary' => $summary])->save();
            Audit::log('checkout.created', $checkout, ['after' => ['member_id' => $member->id, 'total_centavos' => $summary['total_centavos']]]);

            return $checkout;
        });
    }

    public static function pesos(int $centavos): string
    {
        return intdiv($centavos, 100).'.'.str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
    }
}
