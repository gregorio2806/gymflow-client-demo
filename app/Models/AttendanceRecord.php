<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use App\Tenancy\CurrentGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AttendanceRecord extends Model
{
    use BelongsToGym;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::addGlobalScope('branch', fn (Builder $query) => app(CurrentGym::class)->restrictToBranch($query));
        static::creating(function (AttendanceRecord $record) {
            if (! Member::withoutGlobalScope('gym')->where('gym_id', $record->gym_id)->whereKey($record->member_id)->exists()
                || ! Branch::withoutGlobalScope('gym')->where('gym_id', $record->gym_id)->whereKey($record->branch_id)->exists()
                || ! in_array($record->source, ['qr', 'manual'], true)) {
                throw new LogicException('Attendance requires a member and branch in its gym and a supported source.');
            }
        });
        static::updating(fn () => throw new LogicException('Attendance history cannot be changed.'));
    }

    public function member(): BelongsTo
    {
        // History belongs to the recorded branch even after the member transfers home branch.
        return $this->belongsTo(Member::class)->withoutGlobalScope('branch');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    protected function casts(): array
    {
        return ['checked_in_at' => 'immutable_datetime'];
    }
}
