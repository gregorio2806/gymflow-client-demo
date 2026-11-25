<?php

namespace App\Support;

use App\Models\CoachSchedule;
use App\Models\MemberPurchase;
use App\Models\MemberSubscription;
use App\Models\Payment;
use App\Models\SessionRedemption;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class TrainingPackage
{
    public static function eligible(int $id, int $memberId, int $branchId, int $coachId, CarbonImmutable $start, CarbonImmutable $end): MemberPurchase
    {
        // Destination appointment authorization runs before this gym-wide lookup.
        $purchase = MemberPurchase::withoutGlobalScope('branch')->where('member_id', $memberId)->where('kind', 'package')->lockForUpdate()->findOrFail($id);
        $config = $purchase->snapshot['configuration'];
        if ($purchase->status !== 'active' || $purchase->remaining() < 1 || $purchase->starts_at > $start || ($purchase->ends_at && $purchase->ends_at < $end)
            || (! empty($purchase->snapshot['branch_ids']) && ! in_array($branchId, $purchase->snapshot['branch_ids']))
            || (! empty($config['coach_ids']) && ! in_array($coachId, $config['coach_ids']))) {
            throw ValidationException::withMessages(['member_purchase_id' => __('Choose a valid package with remaining sessions for this member, branch and coach.')]);
        }
        if (($purchase->snapshot['packages_require_access'] ?? false) && ! MemberSubscription::withoutGlobalScope('branch')->where('member_id', $memberId)->where('status', 'active')->where('starts_at', '<=', $start)->where('ends_at', '>=', $end)->get()->contains(fn ($s) => empty($s->pricing_snapshot['branch_ids']) || in_array($branchId, $s->pricing_snapshot['branch_ids']))) {
            throw ValidationException::withMessages(['member_purchase_id' => __('This package requires valid gym access at the session branch.')]);
        }

        return $purchase;
    }

    public static function redeem(CoachSchedule $schedule): void
    {
        if (! $schedule->member_purchase_id || SessionRedemption::where('coach_schedule_id', $schedule->id)->exists()) {
            return;
        }
        $purchase = self::eligible($schedule->member_purchase_id, $schedule->member_id, $schedule->branch_id, $schedule->coach_id, $schedule->starts_at, $schedule->ends_at);
        $paid = Payment::withoutGlobalScope('branch')->where('member_purchase_id', $purchase->id)->where('status', 'recorded')->selectRaw('COALESCE(SUM(amount_centavos + discount_centavos), 0) AS settled')->first()->settled;
        if ((int) $paid < $purchase->price_centavos) {
            throw ValidationException::withMessages(['member_purchase_id' => __('Pay the remaining package balance before completing this session.')]);
        }
        $purchase->used_sessions++;
        $purchase->save();
        $redemption = new SessionRedemption;
        $redemption->forceFill(['member_purchase_id' => $purchase->id, 'coach_schedule_id' => $schedule->id, 'branch_id' => $schedule->branch_id, 'recorded_by' => auth()->id()])->save();
        Audit::log('session.redeemed', $redemption, ['after' => ['member_purchase_id' => $purchase->id, 'coach_schedule_id' => $schedule->id, 'remaining' => $purchase->remaining()]]);
    }
}
