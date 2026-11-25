<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class MemberSummaryFilter
{
    /** Match the same current/future/latest period that the list displays. */
    public static function apply(Builder $members, ?int $planId, ?string $state, string $timezone): void
    {
        if (! $planId && ! $state) {
            return;
        }
        if ($state === 'none') {
            if ($planId) {
                $members->whereRaw('1 = 0');

                return;
            }
            $members->whereDoesntHave('currentMembership')->whereDoesntHave('nextMembership')->whereDoesntHave('latestMembership');

            return;
        }
        $at = CarbonImmutable::now();
        $cutoff = $at->setTimezone($timezone)->startOfDay()->addDays(max(0, (int) config('memberships.expiring_soon_days', 7)) + 1)->utc();
        $match = function (Builder $period) use ($planId, $state, $at, $cutoff) {
            $period->when($planId, fn ($q) => $q->where('membership_plan_id', $planId));
            match ($state) {
                'cancelled' => $period->where('status', 'cancelled'),
                'expired' => $period->where('status', '!=', 'cancelled')->where(fn ($q) => $q->where('status', 'expired')->orWhere('ends_at', '<=', $at)),
                'suspended' => $period->where('status', 'suspended')->where('ends_at', '>', $at),
                'scheduled' => $period->where('status', 'active')->where('starts_at', '>', $at)->where('ends_at', '>', $at),
                'expiring-soon' => $period->where('status', 'active')->where('starts_at', '<=', $at)->where('ends_at', '>', $at)->where('ends_at', '<=', $cutoff),
                'active' => $period->where('status', 'active')->where('starts_at', '<=', $at)->where('ends_at', '>', $cutoff),
                default => null,
            };
        };
        $members->where(fn ($q) => $q->whereHas('currentMembership', $match)
            ->orWhere(fn ($q) => $q->whereDoesntHave('currentMembership')->whereHas('nextMembership', $match))
            ->orWhere(fn ($q) => $q->whereDoesntHave('currentMembership')->whereDoesntHave('nextMembership')->whereHas('latestMembership', $match)));
    }
}
