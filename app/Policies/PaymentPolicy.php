<?php

namespace App\Policies;

use App\Enums\GymRole;
use App\Models\Payment;
use App\Models\User;
use App\Tenancy\CurrentGym;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        $gym = app(CurrentGym::class)->get();

        return $gym && in_array($user->roleIn($gym), [GymRole::Owner, GymRole::Staff, GymRole::BranchManager], true);
    }

    public function view(User $user, Payment $payment): bool
    {
        $context = app(CurrentGym::class);

        return $this->viewAny($user) && $payment->gym_id === $context->id()
            && ($context->role() !== GymRole::BranchManager || $payment->branch_id === $context->branchId());
    }

    public function void(User $user, Payment $payment): bool
    {
        return $this->view($user, $payment);
    }
}
