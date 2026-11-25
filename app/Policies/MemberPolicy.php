<?php

namespace App\Policies;

use App\Enums\GymRole;
use App\Models\Member;
use App\Models\User;
use App\Tenancy\CurrentGym;

class MemberPolicy
{
    public function viewAny(User $user): bool
    {
        $gym = app(CurrentGym::class)->get();

        return $gym && in_array($user->roleIn($gym), [GymRole::Owner, GymRole::Staff, GymRole::BranchManager], true);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Member $member): bool
    {
        $context = app(CurrentGym::class);

        return $this->viewAny($user) && $member->gym_id === $context->id()
            && ($context->role() !== GymRole::BranchManager || $member->home_branch_id === $context->branchId());
    }
}
