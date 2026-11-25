<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use App\Tenancy\CurrentGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class MemberRoutineAssignment extends Model
{
    use BelongsToGym;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::addGlobalScope('branch', fn (Builder $q) => app(CurrentGym::class)->restrictToBranch($q));
        static::creating(function (MemberRoutineAssignment $a) {
            $member = Member::withoutGlobalScope('gym')->withoutGlobalScope('branch')->where('gym_id', $a->gym_id)->find($a->member_id);
            $routine = WorkoutRoutine::withoutGlobalScope('gym')->withoutGlobalScope('workout-access')->where('gym_id', $a->gym_id)->find($a->workout_routine_id);
            if (! $member || ! $routine || $member->home_branch_id !== $a->branch_id || $routine->branch_id !== $a->branch_id) {
                throw new LogicException('Assignment member, routine, and branch must share a gym/branch.');
            }
        });
        static::updating(function (MemberRoutineAssignment $a) {
            if ($a->isDirty(['member_id', 'workout_routine_id', 'branch_id', 'snapshot', 'routine_revision', 'assigned_at', 'assigned_by'])) {
                throw new LogicException('Assigned routine snapshots are immutable.');
            }
            if ($a->getRawOriginal('ended_at') !== null && $a->isDirty('ended_at')) {
                throw new LogicException('Ended assignments cannot be reopened.');
            }
        });
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    protected function casts(): array
    {
        return ['routine_revision' => 'integer', 'snapshot' => 'array', 'assigned_at' => 'immutable_datetime', 'ended_at' => 'immutable_datetime'];
    }
}
