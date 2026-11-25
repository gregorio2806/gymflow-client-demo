<?php

namespace App\Models;

use App\Enums\GymRole;
use App\Models\Concerns\BelongsToGym;
use App\Support\WorkoutAccess;
use App\Tenancy\CurrentGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class WorkoutRoutine extends Model
{
    use BelongsToGym;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::addGlobalScope('workout-access', function (Builder $q) {
            app(CurrentGym::class)->restrictToBranch($q);
            if (app(CurrentGym::class)->role() === GymRole::Coach) {
                $q->whereIn('branch_id', WorkoutAccess::coachBranchIds())->where('created_by', auth()->id());
            }
        });
        static::saving(function (WorkoutRoutine $routine) {
            $gymId = $routine->exists ? $routine->gym_id : (app(CurrentGym::class)->id() ?? $routine->gym_id);
            if (! Branch::withoutGlobalScope('gym')->where('gym_id', $gymId)->whereKey($routine->branch_id)->exists()) {
                throw new LogicException('Routine branch must belong to its gym.');
            }
            if ($routine->exists && $routine->isDirty(['branch_id', 'created_by', 'request_key'])) {
                throw new LogicException('Routine ownership is immutable.');
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    protected function casts(): array
    {
        return ['days' => 'array', 'revision' => 'integer'];
    }
}
