<?php

namespace App\Actions;

use App\Enums\GymRole;
use App\Models\Branch;
use App\Models\Gym;
use App\Support\Audit;
use App\Support\PlanLimits;
use App\Support\TeamBranches;
use App\Tenancy\CurrentGym;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetPlanActivation
{
    public function staff(Gym $gym, int $userId, bool $active): void
    {
        app(CurrentGym::class)->runAs($gym, fn () => DB::transaction(function () use ($gym, $userId, $active) {
            $this->lock($gym);
            $user = $gym->users()->whereKey($userId)->firstOrFail();
            $membership = $user->pivot;
            if ($membership->role === GymRole::Owner) {
                throw ValidationException::withMessages(['is_active' => __('The owner must stay active.')]);
            }
            if ($membership->is_active === $active) {
                return;
            }
            if ($active) {
                foreach (TeamBranches::ids($membership) as $id) {
                    $membership->role === GymRole::BranchManager
                        ? app(PlanLimits::class)->ensureBranchManagerAllowed($gym)
                        : app(PlanLimits::class)->ensureStaffAvailable($gym, null, $id, 'is_active');
                }
            }
            $gym->users()->updateExistingPivot($userId, ['is_active' => $active]);
            Audit::log($active ? 'staff.activated' : 'staff.deactivated', $user, ['before' => ['is_active' => ! $active], 'after' => ['is_active' => $active]]);
        }));
    }

    public function branch(Gym $gym, Branch $branch, bool $active): void
    {
        app(CurrentGym::class)->runAs($gym, fn () => DB::transaction(function () use ($gym, $branch, $active) {
            $this->lock($gym);
            $branch = $gym->branches()->lockForUpdate()->findOrFail($branch->id);
            if ($branch->is_active === $active) {
                return;
            }
            if ($active) {
                app(PlanLimits::class)->ensureBranchAvailable($gym);
            } elseif ($gym->branches()->where('is_active', true)->count() <= 1) {
                throw ValidationException::withMessages(['is_active' => __('Keep at least one branch active.')]);
            }
            $branch->forceFill(['is_active' => $active])->save();
            Audit::log($active ? 'branch.activated' : 'branch.deactivated', $branch, ['before' => ['is_active' => ! $active], 'after' => ['is_active' => $active]]);
        }));
    }

    private function lock(Gym $gym): void
    {
        Gym::whereKey($gym->id)->lockForUpdate()->firstOrFail();
        $gym->subscription()->lockForUpdate()->firstOrFail();
    }

    public function selectBranches(Gym $gym, array $ids): void
    {
        app(CurrentGym::class)->runAs($gym, fn () => DB::transaction(function () use ($gym, $ids) {
            $this->lock($gym);
            $ids = array_map('intval', $ids);
            $branches = $gym->branches()->lockForUpdate()->get();
            if (count($ids) < 1 || count($ids) > $gym->subscription()->firstOrFail()->branchLimit() || count(array_unique($ids)) !== count($ids)
                || array_diff($ids, $branches->pluck('id')->all())) {
                throw ValidationException::withMessages(['branch_ids' => __('Choose branches from this gym within your plan allowance.')]);
            }
            foreach ($branches as $branch) {
                $active = in_array($branch->id, $ids, true);
                if ($branch->is_active !== $active) {
                    $branch->forceFill(['is_active' => $active])->save();
                    Audit::log($active ? 'branch.activated' : 'branch.deactivated', $branch, ['before' => ['is_active' => ! $active], 'after' => ['is_active' => $active]]);
                }
            }
            $gym->forceFill(['needs_branch_selection' => false])->save();
            Audit::log('plan.selection_confirmed', $gym, ['after' => ['selection' => 'branches', 'branch_ids' => $ids]]);
        }));
    }
}
