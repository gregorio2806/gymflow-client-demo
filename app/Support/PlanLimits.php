<?php

namespace App\Support;

use App\Enums\GymRole;
use App\Enums\Plan;
use App\Models\Gym;
use App\Models\GymInvitation;
use Illuminate\Validation\ValidationException;

class PlanLimits
{
    public function staffLimit(Gym $gym): int
    {
        $limits = $gym->subscription()->firstOrFail()->plan->limits();

        return $limits['staff_accounts'] ?? $limits['staff_accounts_per_branch'];
    }

    public function staffUsed(Gym $gym, ?string $exceptEmail = null, ?int $branchId = null): int
    {
        $pending = GymInvitation::withoutGlobalScope('gym')->where('gym_id', $gym->id)->whereIn('role', [GymRole::Staff->value, GymRole::Coach->value])->pending();
        if ($exceptEmail !== null) {
            $pending->where('email', '!=', $exceptEmail);
        }

        $staff = $gym->users()->wherePivot('is_active', true)->wherePivotIn('role', [GymRole::Staff->value, GymRole::Coach->value]);
        if ($gym->plan() === Plan::MultiBranch) {
            $branchId ??= $gym->mainBranch()->id;
            TeamBranches::whereAssigned($staff, $branchId, 'gym_user.');
            TeamBranches::whereAssigned($pending, $branchId);
        }

        return $staff->count() + $pending->count();
    }

    public function ensureStaffAvailable(Gym $gym, ?string $exceptEmail = null, ?int $branchId = null, string $field = 'email'): void
    {
        if ($this->staffUsed($gym, $exceptEmail, $branchId) >= $this->staffLimit($gym)) {
            throw ValidationException::withMessages([$field => __('This gym or branch has reached its allowance of :limit staff and coaches. Cancel a pending invitation or upgrade to add more.', ['limit' => $this->staffLimit($gym)])]);
        }
    }

    public function ensureBranchAvailable(Gym $gym): void
    {
        if ($gym->branches()->where('is_active', true)->count() >= $gym->subscription()->firstOrFail()->branchLimit()) {
            throw ValidationException::withMessages(['name' => __('Your plan has reached its branch limit. Upgrade or arrange an extra branch with the platform admin to add more.')]);
        }
    }

    public function ensureBranchManagerAllowed(Gym $gym): void
    {
        if ($gym->subscription()->firstOrFail()->plan !== Plan::MultiBranch) {
            throw ValidationException::withMessages(['role' => __('Upgrade to Multi-Branch to add a branch manager.')]);
        }
    }

    public function ensureBranchWritable(Gym $gym, int $branchId, string $field = 'branch_id'): void
    {
        if (! $gym->branches()->whereKey($branchId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages([$field => __('This branch is read-only. Activate it or choose an active branch.')]);
        }
    }

    // Phase 2 calls this with the gym-scoped active-member count inside its create transaction.
    public function ensureMemberAvailable(Gym $gym, int $activeMembers): void
    {
        $limit = $gym->subscription()->firstOrFail()->plan->limits()['active_members'];
        if ($limit !== null && $activeMembers >= $limit) {
            throw ValidationException::withMessages(['name' => __('Your plan includes :limit active members. Upgrade to add more.', ['limit' => $limit])]);
        }
    }
}
