<?php

namespace App\Actions;

use App\Enums\GymRole;
use App\Models\Gym;
use App\Models\GymInvitation;
use App\Notifications\GymInvitationNotification;
use App\Support\Audit;
use App\Support\PlanLimits;
use App\Support\TeamBranches;
use App\Tenancy\CurrentGym;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class InviteStaff
{
    /**
     * Invite someone to the gym by email. Re-inviting the same email replaces the old invitation.
     */
    public function handle(Gym $gym, string $name, string $email, GymRole $role, int|array|null $branchId = null): GymInvitation
    {
        return app(CurrentGym::class)->runAs($gym, fn () => DB::transaction(fn () => $this->createInvitation($gym, $name, $email, $role, $branchId)));
    }

    private function createInvitation(Gym $gym, string $name, string $email, GymRole $role, int|array|null $branchId): GymInvitation
    {
        Gym::whereKey($gym->id)->lockForUpdate()->firstOrFail();
        $gym->subscription()->lockForUpdate()->firstOrFail();
        $email = mb_strtolower(trim($email));

        if ($gym->users()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => __('This person is already part of your gym.')]);
        }

        $ids = array_values(array_unique(array_map('intval', is_array($branchId) ? $branchId : [$branchId ?? $gym->mainBranch()->id])));
        TeamBranches::validateRole($role, $ids);
        foreach ($ids as $id) {
            $gym->branches()->findOrFail($id);
            $role === GymRole::BranchManager
                ? app(PlanLimits::class)->ensureBranchManagerAllowed($gym)
                : app(PlanLimits::class)->ensureStaffAvailable($gym, $email, $id);
            app(PlanLimits::class)->ensureBranchWritable($gym, $id, is_array($branchId) ? 'branch_ids' : 'branch_id');
        }
        GymInvitation::where('email', $email)->whereNull('accepted_at')->delete();

        $invitation = new GymInvitation(['name' => trim($name), 'email' => $email, 'role' => $role]);
        $invitation->branch_id = $ids[0];
        $invitation->branch_ids = $ids;
        $invitation->invited_by = auth()->id();
        $token = $invitation->issueToken();
        $invitation->save();

        DB::afterCommit(fn () => Notification::route('mail', $email)
            ->notify(new GymInvitationNotification($gym, $role->value, $token)));

        Audit::log('staff.invited', $invitation, ['after' => ['name' => $invitation->name, 'email' => $email, 'role' => $role->value, 'branch_id' => $ids[0], 'branch_ids' => $ids]]);

        return $invitation;
    }
}
