<?php

namespace App\Policies;

use App\Enums\GymRole;
use App\Enums\Plan;
use App\Models\Branch;
use App\Models\User;
use App\Tenancy\CurrentGym;

class BranchPolicy
{
    public function update(User $user, Branch $branch): bool
    {
        $current = app(CurrentGym::class);
        $gym = $current->get();
        if (! $gym || $branch->gym_id !== $gym->id || ! $branch->is_active) {
            return false;
        }
        $role = $user->roleIn($gym);

        return ($role === GymRole::Owner && ($gym->plan() === Plan::MultiBranch || $gym->branches()->count() > 1))
            || ($gym->plan() === Plan::MultiBranch && $role === GymRole::BranchManager && $branch->id === $current->branchId());
    }
}
