<?php

namespace App\Models\Concerns;

use App\Models\Gym;
use App\Tenancy\CurrentGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Tenant isolation for gym-owned models.
 *
 * - Queries only see rows of the current gym. With no current gym they see NOTHING (fail closed).
 * - New rows get the current gym's ID; a gym_id passed in is ignored while a gym is current.
 * - A row can never be moved to another gym.
 *
 * Code that runs without a logged-in user (jobs, commands, seeders) must call
 * app(CurrentGym::class)->set($gym) first, or pass gym_id explicitly when creating.
 * Cross-gym queries (platform admin only) use Model::withoutGlobalScope('gym').
 *
 * Never add gym_id to a model's $fillable.
 *
 * @mixin Model
 */
trait BelongsToGym
{
    protected static function bootBelongsToGym(): void
    {
        static::addGlobalScope('gym', function (Builder $query) {
            $gymId = app(CurrentGym::class)->id();

            $gymId
                ? $query->where($query->qualifyColumn('gym_id'), $gymId)
                : $query->whereRaw('1 = 0');
        });

        static::creating(function (Model $model) {
            $gymId = app(CurrentGym::class)->id();

            if ($gymId) {
                $model->setAttribute('gym_id', $gymId);
            } elseif (! $model->getAttribute('gym_id')) {
                throw new LogicException('Cannot create '.static::class.' without a current gym.');
            }
        });

        static::updating(function (Model $model) {
            if ($model->isDirty('gym_id')) {
                throw new LogicException('Cannot move '.static::class.' to another gym.');
            }
        });
    }

    /**
     * @return BelongsTo<Gym, $this>
     */
    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }
}
