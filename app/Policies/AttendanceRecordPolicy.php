<?php

namespace App\Policies;

use App\Enums\GymRole;
use App\Models\User;
use App\Tenancy\CurrentGym;

class AttendanceRecordPolicy
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
}
