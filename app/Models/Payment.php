<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToGym;
use App\Support\PaymentDiscount;
use App\Tenancy\CurrentGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class Payment extends Model
{
    use BelongsToGym;

    protected $guarded = ['*'];

    protected $attributes = ['currency' => 'PHP', 'status' => 'recorded', 'discount_basis_points' => 0, 'discount_centavos' => 0];

    protected static function booted(): void
    {
        static::addGlobalScope('branch', fn (Builder $query) => app(CurrentGym::class)->restrictToBranch($query));
        static::creating(function (Payment $payment) {
            $model = $payment->member_purchase_id ? MemberPurchase::class : MemberSubscription::class;
            $subscription = $model::withoutGlobalScopes()->where('gym_id', $payment->gym_id)->find($payment->member_purchase_id ?: $payment->member_subscription_id);
            if ((bool) $payment->member_purchase_id === (bool) $payment->member_subscription_id || ! $subscription || $subscription->member_id !== (int) $payment->member_id
                || ! Branch::withoutGlobalScopes()->where('gym_id', $payment->gym_id)->whereKey($payment->branch_id)->exists()
                || ! User::whereHas('gyms', fn ($query) => $query->where('gyms.id', $payment->gym_id)->where('gym_user.is_active', true))->whereKey($payment->recorded_by)->exists()) {
                throw new LogicException('Payments require a matching gym subscription, branch, and active recorder.');
            }
            if ($payment->discount_basis_points < 0 || $payment->discount_basis_points > 10000 || $payment->discount_centavos < 0
                || ($payment->discount_basis_points > 0 && (! in_array($payment->discount_scope, ['payment', 'membership'], true)
                    || $payment->discount_base_centavos < 1 || $payment->discount_centavos !== PaymentDiscount::savings($payment->discount_base_centavos, $payment->discount_basis_points)
                    || ($payment->discount_scope === 'payment' && $payment->discount_base_centavos !== $payment->amount_centavos + $payment->discount_centavos)
                    || ($payment->discount_scope === 'membership' && $payment->discount_base_centavos !== $subscription->price_centavos)))
                || ($payment->discount_basis_points === 0 && ($payment->discount_centavos !== 0 || $payment->discount_scope !== null || $payment->discount_base_centavos !== null))) {
                throw new LogicException('Payment discounts must have a valid percentage, base and exact savings.');
            }
            if ($payment->currency !== 'PHP' || ! is_int($payment->getAttributes()['amount_centavos'] ?? null) || $payment->amount_centavos < 0
                || ($payment->amount_centavos === 0 && $payment->discount_centavos === 0)
                || $payment->status !== PaymentStatus::Recorded || $payment->void_reason !== null || $payment->voided_at !== null || $payment->voided_by !== null) {
                throw new LogicException('New payments require integer PHP centavos, positive settled value and recorded status.');
            }
            // Resolve the enum to reject unsupported methods before persistence.
            if (! $payment->method instanceof PaymentMethod) {
                throw new LogicException('A supported offline payment method is required.');
            }
        });
        static::updating(function (Payment $payment) {
            foreach (['member_id', 'member_subscription_id', 'member_purchase_id', 'branch_id', 'amount_centavos', 'discount_basis_points', 'discount_centavos', 'discount_base_centavos', 'discount_scope', 'currency', 'method', 'reference', 'recorded_by', 'paid_at', 'request_key'] as $field) {
                if ($payment->isDirty($field)) {
                    throw new LogicException('Recorded payment details are immutable.');
                }
            }
            if ($payment->getRawOriginal('status') === PaymentStatus::Voided->value
                || $payment->status !== PaymentStatus::Voided || ! trim($payment->void_reason ?? '') || ! $payment->voided_at
                || ! User::whereHas('gyms', fn ($query) => $query->where('gyms.id', $payment->gym_id)->where('gym_user.is_active', true))->whereKey($payment->voided_by)->exists()) {
                throw new LogicException('Voiding requires a reason, timestamp, and active gym actor; it is terminal.');
            }
        });
        static::deleting(fn () => throw new LogicException('Void payments instead of deleting them.'));
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withoutGlobalScope('branch');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(MemberSubscription::class, 'member_subscription_id')->withoutGlobalScope('branch');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(MemberPurchase::class, 'member_purchase_id')->withoutGlobalScope('branch');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    protected function casts(): array
    {
        return ['amount_centavos' => 'integer', 'discount_basis_points' => 'integer', 'discount_centavos' => 'integer', 'discount_base_centavos' => 'integer', 'method' => PaymentMethod::class, 'status' => PaymentStatus::class, 'paid_at' => 'immutable_datetime', 'voided_at' => 'immutable_datetime'];
    }
}
