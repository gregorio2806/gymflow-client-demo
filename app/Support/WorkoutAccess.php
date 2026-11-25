<?php

namespace App\Support;

use App\Enums\GymRole;
use App\Tenancy\CurrentGym;

class WorkoutAccess
{
    public static function coachBranchId(): ?int
    {
        return self::coachBranchIds()[0] ?? null;
    }

    public static function coachBranchIds(): array
    {
        $gym = app(CurrentGym::class)->get();
        $membership = $gym?->users()->whereKey(auth()->id())->wherePivot('is_active', true)->first()?->pivot;

        return $membership ? $gym->branches()->whereIn('id', TeamBranches::ids($membership))->pluck('id')->map(fn ($id) => (int) $id)->all() : [];
    }

    public static function roles(): array
    {
        return [GymRole::Owner, GymRole::Staff, GymRole::BranchManager, GymRole::Coach];
    }
}
