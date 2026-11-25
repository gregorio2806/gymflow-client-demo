<?php

namespace App\Actions;

use App\Enums\GymRole;
use App\Models\Gym;
use App\Models\GymInvitation;
use App\Models\User;
use App\Support\Audit;
use App\Support\PlanLimits;
use App\Support\TeamBranches;
use App\Tenancy\CurrentGym;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AcceptInvitation
{
    public function __construct(private CurrentGym $currentGym) {}

    /**
     * Add the user to the invitation's gym. The caller must check the email matches.
     */
    public function handle(GymInvitation $invitation, User $user): Gym
    {
        return DB::transaction(function () use ($invitation, $user) {
            $gym = Gym::whereKey($invitation->gym_id)->lockForUpdate()->firstOrFail();
            $gym->subscription()->lockForUpdate()->firstOrFail();
            // Re-check under a lock so the same link can't be used twice at once.
            $invitation = GymInvitation::withoutGlobalScopes(['gym', 'branch'])
                ->pending()
                ->lockForUpdate()
                ->find($invitation->id) ?? throw new NotFoundHttpException;

            if (! $gym->users()->whereKey($user->id)->exists()) {
                $this->currentGym->runAs($gym, function () use ($gym, $invitation, $user) {
                    $ids = TeamBranches::ids($invitation);
                    TeamBranches::validateRole($invitation->role, $ids);
                    foreach ($ids as $id) {
                        $gym->branches()->findOrFail($id);
                        $invitation->role === GymRole::BranchManager
                            ? app(PlanLimits::class)->ensureBranchManagerAllowed($gym)
                            : app(PlanLimits::class)->ensureStaffAvailable($gym, $invitation->email, $id);
                        app(PlanLimits::class)->ensureBranchWritable($gym, $id);
                    }
                    $gym->users()->attach($user->id, ['role' => $invitation->role, 'branch_id' => $ids[0], 'branch_ids' => $ids]);
                });
            }

            $invitation->accepted_at = now();
            $invitation->save();

            // The invitation link was emailed to this address, so opening it proves ownership.
            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            $membership = $gym->users()->whereKey($user->id)->sole()->pivot;
            $this->currentGym->set($gym, $membership->role, $membership->branch_id);
            Audit::log('staff.joined', $invitation, ['after' => ['user_id' => $user->id, 'role' => $invitation->role->value, 'branch_id' => $invitation->branch_id, 'branch_ids' => TeamBranches::ids($invitation)]], $user->id);

            return $gym;
        });
    }
}
