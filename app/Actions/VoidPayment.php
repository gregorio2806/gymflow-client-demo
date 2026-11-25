<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Models\Gym;
use App\Models\Payment;
use App\Support\Audit;
use App\Support\PlanLimits;
use App\Tenancy\CurrentGym;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class VoidPayment
{
    public function handle(Payment $payment, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $reason) {
            $gym = Gym::whereKey(app(CurrentGym::class)->id())->lockForUpdate()->firstOrFail();
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);
            Gate::authorize('void', $payment);
            $reason = trim($reason);
            if ($reason === '' || mb_strlen($reason) > 500) {
                throw ValidationException::withMessages(['void_reason' => __('Enter a reason of up to 500 characters.')]);
            }
            if ($payment->status === PaymentStatus::Voided) {
                if ($payment->void_reason !== $reason) {
                    throw ValidationException::withMessages(['void_reason' => __('This payment is already voided. Its reason cannot be changed.')]);
                }

                return $payment;
            }
            $gym->subscription()->lockForUpdate()->firstOrFail();
            app(PlanLimits::class)->ensureBranchWritable($gym, $payment->branch_id);
            $payment->forceFill(['status' => PaymentStatus::Voided, 'void_reason' => $reason, 'voided_at' => now()->utc(), 'voided_by' => auth()->id()])->save();
            Audit::log('payment.voided', $payment, ['before' => ['status' => 'recorded'], 'after' => $payment->only(['status', 'void_reason', 'voided_at', 'voided_by'])]);

            return $payment;
        });
    }
}
