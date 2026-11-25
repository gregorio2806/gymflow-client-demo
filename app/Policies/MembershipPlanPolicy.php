<?php

namespace App\Policies;

use App\Enums\GymRole;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Tenancy\CurrentGym;

class MembershipPlanPolicy
{
    public function viewAny(User $user): bool
    {
        $gym = app(CurrentGym::class)->get();

        return $gym && in_array($user->roleIn($gym), [GymRole::Owner, GymRole::Staff, GymRole::BranchManager], true);
    }

    public function create(User $user): bool
    {
        $gym = app(CurrentGym::class)->get();

        return $gym && $user->roleIn($gym) === GymRole::Owner;
    }

    public function update(User $user, MembershipPlan $plan): bool
    {
        return $plan->gym_id === app(CurrentGym::class)->id() && $this->create($user);
    }
}
