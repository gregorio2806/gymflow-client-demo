<?php

namespace App\Models;

use App\Enums\GymRole;
use App\Enums\MembershipState;
use App\Enums\MemberSubscriptionStatus;
use App\Models\Concerns\BelongsToGym;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use LogicException;

class MemberSubscription extends Model
{
    use BelongsToGym;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::addGlobalScope('branch', function (Builder $query) {
            if (app(CurrentGym::class)->role() === GymRole::BranchManager) {
                $query->whereHas('member');
            }
        });
        static::creating(function (MemberSubscription $subscription) {
            if (! Member::withoutGlobalScope('gym')->where('gym_id', $subscription->gym_id)->whereKey($subscription->member_id)->exists()
                || ! MembershipPlan::withoutGlobalScope('gym')->where('gym_id', $subscription->gym_id)->whereKey($subscription->membership_plan_id)->exists()) {
                throw new LogicException('A subscription member and plan must belong to its gym.');
            }
        });
        static::updating(function (MemberSubscription $subscription) {
            foreach (['pricing_snapshot', 'member_id', 'membership_plan_id', 'plan_name', 'duration_amount', 'duration_unit', 'price_centavos', 'original_price_centavos', 'discount_basis_points', 'discount_type', 'discount_other', 'discount_mode', 'starts_at', 'ends_at', 'request_key', 'operation'] as $field) {
                if ($subscription->isDirty($field)) {
                    throw new LogicException('Membership periods and agreed plan terms are immutable.');
                }
            }
        });
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function membershipPlan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class);
    }

    public function creationAudit(): MorphOne
    {
        return $this->morphOne(AuditLog::class, 'subject')->ofMany(['id' => 'min'], fn (Builder $query) => $query->whereIn('action', ['membership.assigned', 'membership.renewed']));
    }

    public function membershipState(?CarbonImmutable $at = null, ?string $timezone = null): MembershipState
    {
        $at ??= CarbonImmutable::now();
        if ($this->status === MemberSubscriptionStatus::Cancelled) {
            return MembershipState::Cancelled;
        }
        if ($this->status === MemberSubscriptionStatus::Expired || $this->ends_at->lessThanOrEqualTo($at)) {
            return MembershipState::Expired;
        }
        if ($this->status === MemberSubscriptionStatus::Suspended) {
            return MembershipState::Suspended;
        }
        if ($this->starts_at->greaterThan($at)) {
            return MembershipState::Scheduled;
        }
        $timezone ??= $this->gym->timezone;
        $days = max(0, (int) config('memberships.expiring_soon_days', 7));
        $cutoff = $at->setTimezone($timezone)->startOfDay()->addDays($days + 1)->utc();
        if ($this->ends_at->lessThanOrEqualTo($cutoff)) {
            return MembershipState::ExpiringSoon;
        }

        return MembershipState::Active;
    }

    public function displayStatus(?CarbonImmutable $at = null, ?string $timezone = null): string
    {
        return $this->membershipState($at, $timezone)->label();
    }

    protected function casts(): array
    {
        return ['pricing_snapshot' => 'array', 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'status' => MemberSubscriptionStatus::class, 'price_centavos' => 'integer', 'original_price_centavos' => 'integer', 'discount_basis_points' => 'integer', 'duration_amount' => 'integer'];
    }
}
