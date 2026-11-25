<?php

namespace App\Actions;

use App\Enums\GymRole;
use App\Enums\Plan;
use App\Models\Gym;
use App\Models\User;
use App\Support\Audit;
use App\Support\PlanLimits;
use App\Support\TeamBranches;
use App\Tenancy\CurrentGym;
use Illuminate\Support\Facades\DB;

class ReconcilePlanLimits
{
    public function handle(Gym $gym): void
    {
        app(CurrentGym::class)->runAs($gym, fn () => DB::transaction(function () use ($gym) {
            $gym = Gym::whereKey($gym->id)->lockForUpdate()->firstOrFail();
            $subscription = $gym->subscription()->lockForUpdate()->firstOrFail();
            $branches = $gym->branches()->where('is_active', true)->orderBy('created_at')->orderBy('id')->lockForUpdate()->get();
            foreach ($branches->slice($subscription->branchLimit()) as $branch) {
                $branch->forceFill(['is_active' => false])->save();
                Audit::log('branch.deactivated', $branch, ['before' => ['is_active' => true], 'after' => ['is_active' => false], 'reason' => 'plan_limit']);
                $gym->needs_branch_selection = true;
            }
            $staff = $gym->users()->wherePivot('is_active', true)->wherePivotIn('role', [GymRole::Staff->value, GymRole::Coach->value])
                ->orderBy('gym_user.created_at')->orderBy('gym_user.id')->get();
            $used = [];
            $limit = app(PlanLimits::class)->staffLimit($gym);
            foreach ($staff as $user) {
                $ids = $subscription->plan === Plan::MultiBranch ? TeamBranches::ids($user->pivot) : [0];
                if (collect($ids)->contains(fn ($id) => ($used[$id] ?? 0) >= $limit)) {
                    $this->deactivate($gym, $user);
                    $gym->needs_staff_selection = true;
                } else {
                    foreach ($ids as $id) {
                        $used[$id] = ($used[$id] ?? 0) + 1;
                    }
                }
            }
            if ($subscription->plan !== Plan::MultiBranch) {
                foreach ($gym->users()->wherePivot('is_active', true)->wherePivot('role', GymRole::BranchManager->value)->get() as $manager) {
                    $this->deactivate($gym, $manager);
                    $gym->needs_staff_selection = true;
                }
            }
            $gym->save();
        }));
    }

    private function deactivate(Gym $gym, User $user): void
    {
        $gym->users()->updateExistingPivot($user->id, ['is_active' => false]);
        Audit::log('staff.deactivated', $user, ['before' => ['is_active' => true], 'after' => ['is_active' => false], 'reason' => 'plan_limit']);
    }
}
