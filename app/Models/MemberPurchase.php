<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use App\Tenancy\CurrentGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use LogicException;

class MemberPurchase extends Model
{
    use BelongsToGym;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::addGlobalScope('branch', fn ($q) => app(CurrentGym::class)->restrictToBranch($q));
        static::creating(function (self $row) {
            foreach ([Member::class => $row->member_id, MembershipPlan::class => $row->membership_plan_id, Branch::class => $row->branch_id] as $model => $id) {
                if (! $model::withoutGlobalScopes()->where('gym_id', $row->gym_id)->whereKey($id)->exists()) {
                    throw new LogicException('Purchases require same-gym relations.');
                }
            }
        });
        static::updating(function (self $row) {
            if ($row->isDirty(['member_id', 'membership_plan_id', 'branch_id', 'kind', 'plan_name', 'price_centavos', 'original_price_centavos', 'starts_at', 'ends_at', 'snapshot', 'sessions', 'created_by', 'request_key'])) {
                throw new LogicException('Purchased terms are immutable.');
            }
            if ($row->used_sessions < 0 || $row->sessions + $row->session_adjustment < $row->used_sessions) {
                throw new LogicException('Session balances cannot become negative.');
            }
        });
        static::deleting(fn () => throw new LogicException('Cancel purchases instead of deleting their history.'));
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withoutGlobalScope('branch');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function remaining(): int
    {
        return max(0, $this->sessions + $this->session_adjustment - $this->used_sessions);
    }

    public function updateAudit(): MorphOne
    {
        return $this->morphOne(AuditLog::class, 'subject')->ofMany(['id' => 'max'], fn ($q) => $q->whereIn('action', ['purchase.cancel', 'purchase.adjust']));
    }

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'snapshot' => 'array', 'price_centavos' => 'integer', 'original_price_centavos' => 'integer', 'sessions' => 'integer', 'used_sessions' => 'integer', 'session_adjustment' => 'integer'];
    }
}
