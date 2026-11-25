<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\InviteStaff;
use App\Enums\GymRole;
use App\Enums\Plan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\InviteStaffRequest;
use App\Http\Requests\Dashboard\UpdateTeamMemberRequest;
use App\Models\Gym;
use App\Models\GymInvitation;
use App\Support\Audit;
use App\Support\PlanLimits;
use App\Support\TeamBranches;
use App\Tenancy\CurrentGym;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function __construct(private CurrentGym $currentGym) {}

    /**
     * Team members and pending invitations (owners only).
     */
    public function index(Request $request): Response
    {
        $gym = $this->currentGym->get();

        Gate::authorize('viewStaff', $gym);
        $canManage = Gate::allows('manageStaff', $gym);
        $members = $gym->users()->withCount('gyms')->orderBy('name');
        if (! $canManage) {
            TeamBranches::whereAssigned($members, $this->currentGym->branchId(), 'gym_user.');
        }
        if ($viewBranchId = $this->currentGym->viewBranchId()) {
            TeamBranches::whereAssigned($members, $viewBranchId, 'gym_user.');
        }
        $teamPage = $members->orderBy('users.id')->paginate(25, ['users.*'], 'team_page')->withQueryString();
        $invitations = GymInvitation::pending();
        if ($viewBranchId = $this->currentGym->viewBranchId()) {
            TeamBranches::whereAssigned($invitations, $viewBranchId);
        }
        $invitePage = $canManage ? $invitations->latest()->paginate(25, ['*'], 'invitations_page')->withQueryString() : null;

        return Inertia::render('dashboard/gym/Staff', [
            'canManage' => $canManage,
            'needsSelection' => $gym->needs_staff_selection,
            'teamPagination' => ['total' => $teamPage->total(), 'prev' => $teamPage->previousPageUrl(), 'next' => $teamPage->nextPageUrl()],
            'invitePagination' => $invitePage ? ['total' => $invitePage->total(), 'prev' => $invitePage->previousPageUrl(), 'next' => $invitePage->nextPageUrl()] : null,
            'staffCapacity' => ['used' => app(PlanLimits::class)->staffUsed($gym, null, $this->currentGym->branchId()), 'limit' => app(PlanLimits::class)->staffLimit($gym), 'perBranch' => $gym->plan() === Plan::MultiBranch],
            'branches' => $gym->branches()->orderByDesc('is_main')->orderBy('name')->limit(100)->get()->map(fn ($branch) => $branch->only(['id', 'name'])),
            'members' => $teamPage->getCollection()->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->pivot->role->value,
                'branchId' => $user->pivot->branch_id,
                'branchIds' => TeamBranches::ids($user->pivot),
                'isActive' => $user->pivot->is_active,
                'isYou' => $user->is($request->user()),
                // Only people who work at this gym alone can be renamed by its owner.
                'canEditName' => $canManage && $user->gyms_count === 1,
            ]),
            'invitations' => $invitePage ? $invitePage->getCollection()->map(fn (GymInvitation $invitation) => [
                'id' => $invitation->id,
                'name' => $invitation->name,
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'branchId' => $invitation->branch_id,
                'branchIds' => TeamBranches::ids($invitation),
                'expiresAt' => $invitation->expires_at->toIso8601String(),
            ]) : [],
        ]);
    }

    /**
     * Email an invitation to join the gym.
     */
    public function invite(InviteStaffRequest $request, InviteStaff $inviteStaff): RedirectResponse
    {
        $invitation = $inviteStaff->handle(
            $this->currentGym->get(),
            $request->validated('name'),
            $request->validated('email'),
            GymRole::from($request->validated('role')),
            $request->validated('branch_ids') ?? $request->validated('branch_id'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent to :email.', ['email' => $invitation->email])]);

        return to_route('staff.index');
    }

    /**
     * Fix a team member's name or switch them between staff and coach.
     * The member is looked up through the current gym, so other gyms' people 404.
     */
    public function update(UpdateTeamMemberRequest $request, int $member): RedirectResponse
    {
        return DB::transaction(fn () => $this->updateMember($request, $member));
    }

    private function updateMember(UpdateTeamMemberRequest $request, int $member): RedirectResponse
    {
        $gym = $this->currentGym->get();
        Gym::whereKey($gym->id)->lockForUpdate()->firstOrFail();
        $gym->subscription()->lockForUpdate()->firstOrFail();
        $user = $gym->users()->whereKey($member)->firstOrFail();
        $currentRole = $user->pivot->role;
        $before = ['name' => $user->name, 'role' => $currentRole->value];

        $name = $request->validated('name');
        $role = $request->validated('role');
        $oldIds = TeamBranches::ids($user->pivot);
        $ids = array_map('intval', $request->validated('branch_ids') ?? ($request->validated('branch_id') !== null ? [$request->validated('branch_id')] : $oldIds));
        sort($ids);
        $sortedOld = $oldIds;
        sort($sortedOld);
        $newRole = GymRole::from($role ?? $currentRole->value);
        if ($currentRole !== GymRole::Owner || $request->has('branch_ids') || $request->validated('branch_id') !== null) {
            TeamBranches::validateRole($newRole, $ids);
        }
        if ($newRole === GymRole::BranchManager) {
            app(PlanLimits::class)->ensureBranchManagerAllowed($gym);
        }
        foreach ($ids as $id) {
            if (! in_array($id, $oldIds, true)) {
                app(PlanLimits::class)->ensureBranchWritable($gym, $id, $request->has('branch_ids') ? 'branch_ids' : 'branch_id');
            }
            if ($user->pivot->is_active && $newRole->usesStaffPlace() && (! $currentRole->usesStaffPlace() || ! in_array($id, $oldIds, true))) {
                // Global plans count the existing account once even when another branch is added.
                if (! $currentRole->usesStaffPlace() || $gym->plan() === Plan::MultiBranch) {
                    app(PlanLimits::class)->ensureStaffAvailable($gym, null, $id, $ids !== $sortedOld ? ($request->has('branch_ids') ? 'branch_ids' : 'branch_id') : 'role');
                }
            }
        }
        if ($ids !== $sortedOld) {
            $before['branch_id'] = $user->pivot->branch_id;
            $before['branch_ids'] = $oldIds;
            $gym->users()->updateExistingPivot($user->id, ['branch_id' => $ids[0], 'branch_ids' => $ids]);
        }

        if ($name !== $user->name) {
            // Accounts can belong to several gyms; one gym's owner must not rename someone everywhere.
            if ($user->gyms()->count() > 1) {
                throw ValidationException::withMessages([
                    'name' => __('This person also works at another gym, so only they can change their name (Settings → Profile).'),
                ]);
            }

            $user->forceFill(['name' => $name])->save();
        }

        if ($role !== null && $role !== $currentRole->value) {
            if ($currentRole === GymRole::Owner) {
                throw ValidationException::withMessages(['role' => __('The owner\'s role can\'t be changed.')]);
            }

            $gym->users()->updateExistingPivot($user->id, ['role' => $role]);
        }

        $after = ['name' => $user->name, 'role' => $role ?? $currentRole->value];
        if (array_key_exists('branch_id', $before)) {
            $after['branch_id'] = $ids[0];
            $after['branch_ids'] = $ids;
        }

        if ($after !== $before) {
            Audit::log('staff.updated', $user, ['before' => $before, 'after' => $after]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name updated.', ['name' => $user->name])]);

        return to_route('staff.index');
    }

    /**
     * Cancel a pending invitation. Route binding is gym-scoped, so other gyms' invitations 404.
     */
    public function revoke(GymInvitation $invitation): RedirectResponse
    {
        Gate::authorize('manageStaff', $this->currentGym->get());

        $invitation->delete();
        Audit::log('staff.invitation_revoked', null, ['before' => ['email' => $invitation->email, 'role' => $invitation->role->value]]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation cancelled.')]);

        return to_route('staff.index');
    }
}
