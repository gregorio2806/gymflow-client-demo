<?php

namespace App\Models;

use App\Enums\AppointmentService;
use App\Enums\GymRole;
use App\Models\Concerns\BelongsToGym;
use App\Support\CoachCalendarAccess;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CoachSchedule extends Model
{
    use BelongsToGym;

    protected $guarded = ['*'];

    protected $attributes = ['is_cancelled' => false, 'revision' => 1, 'kind' => 'block', 'status' => 'scheduled'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function setStartsAtAttribute(mixed $value): void
    {
        $this->attributes['starts_at'] = $value === null ? null : CarbonImmutable::parse($value)->utc()->format('Y-m-d H:i:s');
    }

    public function setEndsAtAttribute(mixed $value): void
    {
        $this->attributes['ends_at'] = $value === null ? null : CarbonImmutable::parse($value)->utc()->format('Y-m-d H:i:s');
    }

    protected static function booted(): void
    {
        static::addGlobalScope('calendar-access', fn (Builder $query) => CoachCalendarAccess::scopeSchedules($query));
        static::saving(function (CoachSchedule $schedule) {
            if ($schedule->exists && ($schedule->isDirty(['kind', 'request_key', 'creation_hash', 'created_by']) || ($schedule->kind === 'block' && $schedule->isDirty('coach_id')))) {
                throw new LogicException('Schedule creation identity is immutable.');
            }
            if ($schedule->kind === 'appointment' && $schedule->exists && $schedule->getRawOriginal('status') !== 'scheduled' && $schedule->isDirty()) {
                throw new LogicException('Finished appointments cannot be changed.');
            }
            if ($schedule->exists && $schedule->getRawOriginal('is_cancelled') && $schedule->isDirty(['title', 'branch_id', 'starts_at', 'ends_at', 'notes', 'is_cancelled', 'cancelled_at', 'cancelled_by'])) {
                throw new LogicException('Cancelled schedules cannot be changed.');
            }
            $gymId = $schedule->exists ? $schedule->gym_id : (app(CurrentGym::class)->id() ?? $schedule->gym_id);
            if ($schedule->member_purchase_id && ($schedule->kind !== 'appointment' || ! MemberPurchase::withoutGlobalScopes()->where('gym_id', $gymId)->where('kind', 'package')->where('member_id', $schedule->member_id)->whereKey($schedule->member_purchase_id)->exists())) {
                throw new LogicException('A package must belong to the appointment member and gym.');
            }
            if (! in_array($schedule->kind, ['block', 'appointment'], true) || ! in_array($schedule->status, ['scheduled', 'completed', 'cancelled', 'no-show'], true)) {
                throw new LogicException('A supported schedule kind and status are required.');
            }
            if ($schedule->kind === 'appointment' && (! AppointmentService::tryFrom($schedule->service ?? '')
                || ($schedule->member_id && ! Member::withoutGlobalScopes()->where('gym_id', $gymId)->whereKey($schedule->member_id)->exists())
                || (! $schedule->member_id && ! trim($schedule->customer_name ?? ''))
                || mb_strlen($schedule->customer_name ?? '') > 255 || mb_strlen($schedule->location ?? '') > 100)) {
                throw new LogicException('Appointments require a same-gym member or customer and supported service.');
            }
            if ($schedule->is_cancelled) {
                $schedule->status = 'cancelled';
            }
            if (! Branch::withoutGlobalScopes()->where('gym_id', $gymId)->whereKey($schedule->branch_id)->exists()) {
                throw new LogicException('Schedule branch must belong to its gym.');
            }
            // Historical entries remain usable after a coach leaves; new assignments require an active coach.
            if (! $schedule->exists || $schedule->isDirty('coach_id')) {
                if (! Gym::find($gymId)?->users()->where('users.id', $schedule->coach_id)->wherePivot('role', GymRole::Coach->value)->wherePivot('is_active', true)->exists()) {
                    throw new LogicException('Schedule coach must be active in its gym.');
                }
            }
            $schedule->title = trim($schedule->title ?? '');
            if ($schedule->title === '' || mb_strlen($schedule->title) > 100 || mb_strlen($schedule->notes ?? '') > 1000) {
                throw new LogicException('Schedule requires a title of at most 100 characters and notes of at most 1000 characters.');
            }
            if (! $schedule->starts_at || ! $schedule->ends_at || $schedule->ends_at->lessThanOrEqualTo($schedule->starts_at)) {
                throw new LogicException('Schedule end must be after start.');
            }
            $schedule->starts_at = $schedule->starts_at->utc();
            $schedule->ends_at = $schedule->ends_at->utc();
        });
    }

    /** Half-open interval: adjacent entries do not overlap. Inputs must represent UTC instants. */
    public function scopeOverlapping(Builder $query, \DateTimeInterface $start, \DateTimeInterface $end): void
    {
        $query->where('starts_at', '<', $end)->where('ends_at', '>', $start);
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withoutGlobalScope('branch');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(MemberPurchase::class, 'member_purchase_id')->withoutGlobalScope('branch');
    }

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'is_cancelled' => 'boolean', 'revision' => 'integer', 'cancelled_at' => 'immutable_datetime', 'status_changed_at' => 'immutable_datetime'];
    }
}
