<?php

namespace App\Policies;

use App\Enums\GymRole;
use App\Enums\Plan;
use App\Models\Gym;
use App\Models\User;

/**
 * Who can do what with a gym. Platform admins pass every check (see AppServiceProvider).
 */
class GymPolicy
{
    public function manageWebsite(User $user, Gym $gym): bool
    {
        return $user->roleIn($gym) === GymRole::Owner;
    }

    public function viewBranches(User $user, Gym $gym): bool
    {
        return ($user->roleIn($gym) === GymRole::Owner && ($gym->plan() === Plan::MultiBranch || $gym->branches()->count() > 1))
            || ($gym->plan() === Plan::MultiBranch && $user->roleIn($gym) === GymRole::BranchManager);
    }

    public function viewStaff(User $user, Gym $gym): bool
    {
        return $user->roleIn($gym) === GymRole::Owner || ($gym->plan() === Plan::MultiBranch && $user->roleIn($gym) === GymRole::BranchManager);
    }

    public function manageBranches(User $user, Gym $gym): bool
    {
        return $user->roleIn($gym) === GymRole::Owner && $gym->plan() === Plan::MultiBranch;
    }

    /**
     * Any owner, staff member, or coach of the gym.
     */
    public function view(User $user, Gym $gym): bool
    {
        return $user->roleIn($gym) !== null;
    }

    /**
     * Change gym settings (name, contact details).
     */
    public function update(User $user, Gym $gym): bool
    {
        return $user->roleIn($gym) === GymRole::Owner;
    }

    /**
     * Invite, remove, or change the role of staff and coaches.
     */
    public function manageStaff(User $user, Gym $gym): bool
    {
        return $user->roleIn($gym) === GymRole::Owner;
    }
}
