<?php

namespace App\Support;

use App\Enums\GymRole;
use App\Enums\Plan;
use App\Models\Branch;
use App\Models\User;
use App\Tenancy\CurrentGym;
use Illuminate\Database\Eloquent\Builder;

class CoachCalendarAccess
{
    public static function actor(User $user): ?User
    {
        $context = app(CurrentGym::class);
        $actor = $context->get()?->users()->whereKey($user->id)->wherePivot('is_active', true)->first();
        if (! $actor || ! in_array($actor->pivot->role, [GymRole::Owner, GymRole::Staff, GymRole::Coach, GymRole::BranchManager], true)) {
            return null;
        }
        if ($actor->pivot->role === GymRole::BranchManager && ($context->get()->plan() !== Plan::MultiBranch || ! $actor->pivot->branch_id || $actor->pivot->branch_id !== $context->branchId()
            || ! Branch::withoutGlobalScopes()->where('gym_id', $context->id())->whereKey($actor->pivot->branch_id)->exists())) {
            return null;
        }

        return $actor;
    }

    public static function coaches(User $user): Builder
    {
        $context = app(CurrentGym::class);
        $query = User::query()->whereHas('gyms', fn (Builder $gyms) => $gyms->where('gyms.id', $context->id() ?? 0)->where('gym_user.role', GymRole::Coach->value)->where('gym_user.is_active', true));
        $actor = self::actor($user);
        if (! $actor) {
            return $query->whereRaw('1 = 0');
        }
        if ($actor->pivot->role === GymRole::Coach) {
            $query->whereKey($user->id);
        } elseif ($actor->pivot->role === GymRole::BranchManager) {
            $query->whereHas('gyms', function (Builder $gyms) use ($context, $actor) {
                $gyms->where('gyms.id', $context->id());
                TeamBranches::whereAssigned($gyms, $actor->pivot->branch_id, 'gym_user.');
            });
        }

        return $query;
    }

    public static function scopeSchedules(Builder $query): void
    {
        $context = app(CurrentGym::class);
        // Explicit runAs contexts support internal jobs; dashboard contexts must recheck the actor.
        if ($context->role() === null) {
            return;
        }
        $actor = auth()->user() ? self::actor(auth()->user()) : null;
        if (! $actor) {
            $query->whereRaw('1 = 0');

            return;
        }
        if ($actor->pivot->role === GymRole::Coach) {
            $query->where($query->qualifyColumn('coach_id'), $actor->id);
        } elseif ($actor->pivot->role === GymRole::BranchManager) {
            $query->where($query->qualifyColumn('branch_id'), $actor->pivot->branch_id)
                ->whereIn($query->qualifyColumn('coach_id'), self::coaches(auth()->user())->select('users.id'));
        }
    }
}
