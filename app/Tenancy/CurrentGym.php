<?php

namespace App\Tenancy;

use App\Enums\GymRole;
use App\Models\Gym;
use Illuminate\Database\Eloquent\Builder;

/**
 * The gym the current request (or job) is working in.
 *
 * Registered as a scoped singleton, so it resets between requests and queued jobs.
 * Jobs and commands must call set() themselves — there is no logged-in user there.
 */
class CurrentGym
{
    private ?Gym $gym = null;

    private ?GymRole $role = null;

    private ?int $branchId = null;

    private ?int $viewBranchId = null;

    public function set(Gym $gym, ?GymRole $role = null, ?int $branchId = null): void
    {
        $this->gym = $gym;
        $this->role = $role;
        $this->branchId = $role === GymRole::BranchManager ? $branchId : null;
        $this->viewBranchId = null;
    }

    public function setViewBranch(?int $branchId): void
    {
        $this->viewBranchId = $branchId;
    }

    /** A display filter, separate from the manager's authorization restriction. */
    public function viewBranchId(): ?int
    {
        return $this->branchId ?? $this->viewBranchId;
    }

    public function filterView(Builder $query, string $column = 'branch_id'): Builder
    {
        return $query->when($this->viewBranchId(), fn (Builder $query, int $id) => $query->where($query->qualifyColumn($column), $id));
    }

    public function branchId(): ?int
    {
        return $this->branchId;
    }

    public function restrictToBranch(Builder $query, string $column = 'branch_id'): void
    {
        if ($this->role === GymRole::BranchManager) {
            $this->branchId !== null
                ? $query->where($query->qualifyColumn($column), $this->branchId)
                : $query->whereRaw('1 = 0');
        }
    }

    public function get(): ?Gym
    {
        return $this->gym;
    }

    public function id(): ?int
    {
        return $this->gym?->id;
    }

    /**
     * The logged-in user's role in the current gym.
     */
    public function role(): ?GymRole
    {
        return $this->role;
    }

    public function check(): bool
    {
        return $this->gym !== null;
    }

    public function forget(): void
    {
        $this->gym = null;
        $this->role = null;
        $this->branchId = null;
        $this->viewBranchId = null;
    }

    /**
     * Run code as if the given gym were current, then restore the previous gym.
     * For work on a gym other than the request's own (creating a new gym's defaults,
     * platform admin actions).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function runAs(Gym $gym, callable $callback): mixed
    {
        [$previousGym, $previousRole, $previousBranch, $previousViewBranch] = [$this->gym, $this->role, $this->branchId, $this->viewBranchId];

        $this->set($gym);

        try {
            return $callback();
        } finally {
            $previousGym ? $this->set($previousGym, $previousRole, $previousBranch) : $this->forget();
            $this->viewBranchId = $previousViewBranch;
        }
    }
}
