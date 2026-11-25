<?php

namespace App\Policies;

use App\Enums\GymRole;
use App\Models\ReportExport;
use App\Models\User;
use App\Tenancy\CurrentGym;

class ReportExportPolicy
{
    public function viewAny(User $user): bool
    {
        $gym = app(CurrentGym::class)->get();

        return $gym && in_array($user->roleIn($gym), [GymRole::Owner, GymRole::Staff, GymRole::BranchManager], true);
    }

    public function view(User $user, ReportExport $export): bool
    {
        $c = app(CurrentGym::class);

        return $this->viewAny($user) && $export->gym_id === $c->id() && $export->user_id === $user->id && $export->requested_role === $c->role()?->value && ($c->role() !== GymRole::BranchManager || $export->branch_id === $c->branchId()) && $export->expires_at->isFuture();
    }
}
