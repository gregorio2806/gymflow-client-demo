<?php

namespace App\Policies;

use App\Enums\GymRole;
use App\Models\User;
use App\Models\WorkoutRoutine;
use App\Support\WorkoutAccess;
use App\Tenancy\CurrentGym;

class WorkoutRoutinePolicy
{
    public function viewAny(User $user): bool
    {
        $gym = app(CurrentGym::class)->get();

        return $gym && in_array($user->roleIn($gym), WorkoutAccess::roles(), true);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user) && (app(CurrentGym::class)->role() !== GymRole::Coach || WorkoutAccess::coachBranchId() !== null);
    }

    public function update(User $user, WorkoutRoutine $routine): bool
    {
        $c = app(CurrentGym::class);

        return $this->viewAny($user) && $routine->gym_id === $c->id()
            && ($c->role() !== GymRole::BranchManager || $routine->branch_id === $c->branchId())
            && ($c->role() !== GymRole::Coach || ($routine->created_by === $user->id && in_array($routine->branch_id, WorkoutAccess::coachBranchIds(), true)));
    }
}
