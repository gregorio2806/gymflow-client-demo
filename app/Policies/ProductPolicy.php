<?php

namespace App\Policies;

use App\Enums\GymRole;
use App\Models\Branch;
use App\Models\Product;
use App\Models\User;
use App\Support\TeamBranches;
use App\Tenancy\CurrentGym;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        $gym = app(CurrentGym::class)->get();

        return $gym && in_array($user->roleIn($gym), [GymRole::Owner, GymRole::Staff, GymRole::BranchManager], true);
    }

    public function create(User $user): bool
    {
        $gym = app(CurrentGym::class)->get();

        return $gym && in_array($user->roleIn($gym), [GymRole::Owner, GymRole::Staff], true);
    }

    public function update(User $user, Product $product): bool
    {
        return $product->gym_id === app(CurrentGym::class)->id() && $this->create($user);
    }

    public function archive(User $user, Product $product): bool
    {
        return $this->update($user, $product) && $user->roleIn(app(CurrentGym::class)->get()) === GymRole::Owner;
    }

    public function adjustStock(User $user, Product $product, int $branchId): bool
    {
        $current = app(CurrentGym::class);
        if (! $this->viewAny($user) || $product->gym_id !== $current->id() || ! $product->is_active) {
            return false;
        }

        $branch = Branch::whereKey($branchId)->where('is_active', true)->first();
        if (! $branch) {
            return false;
        }

        if ($current->role() === GymRole::BranchManager) {
            return $branch->id === $current->branchId();
        }
        if ($current->role() === GymRole::Staff) {
            $membership = $user->gyms()->whereKey($current->id())->first()?->pivot;

            return in_array($branch->id, TeamBranches::ids($membership), true);
        }

        return true;
    }
}
