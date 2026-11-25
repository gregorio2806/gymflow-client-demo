<?php

namespace App\Http\Controllers\Platform;

use App\Actions\InviteGymOwner;
use App\Enums\Plan;
use App\Http\Controllers\Controller;
use App\Models\AccessRequest;
use App\Models\OwnerInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform admin: grant access to gym owners who emailed to request it.
 */
class OwnerInvitationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('platform/OwnerInvitations', [
            'invitations' => OwnerInvitation::query()->with('gym:id,name')->latest()->paginate(25)
                ->through(fn (OwnerInvitation $invitation) => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'plan' => $invitation->plan->label(),
                    'gymName' => $invitation->gym?->name ?? $invitation->gym_name,
                    'status' => match (true) {
                        $invitation->gym_id !== null => 'gym created',
                        $invitation->accepted_at !== null => 'account created',
                        $invitation->expires_at->isPast() => 'expired',
                        default => 'pending',
                    },
                    'sentAt' => $invitation->created_at->toIso8601String(),
                ]),
            'accessRequests' => AccessRequest::where('status', 'new')->latest()->limit(50)->get()
                ->map(fn (AccessRequest $accessRequest) => [
                    'id' => $accessRequest->id,
                    'name' => $accessRequest->name,
                    'email' => $accessRequest->email,
                    'phone' => $accessRequest->phone,
                    'gymName' => $accessRequest->gym_name,
                    'location' => $accessRequest->location,
                    'plan' => $accessRequest->plan->value,
                    'planLabel' => $accessRequest->plan->label(),
                    'message' => $accessRequest->message,
                    'sentAt' => $accessRequest->created_at->toIso8601String(),
                ]),
            'plans' => collect(Plan::cases())->map(fn (Plan $plan) => ['value' => $plan->value, 'label' => $plan->label()]),
        ]);
    }

    public function store(Request $request, InviteGymOwner $inviteGymOwner): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'plan' => ['required', Rule::enum(Plan::class)],
            'gym_name' => ['nullable', 'string', 'max:100'],
            'access_request_id' => ['nullable', 'integer', 'exists:access_requests,id'],
        ]);

        $invitation = $inviteGymOwner->handle($validated['email'], Plan::from($validated['plan']), $validated['gym_name'] ?? null);

        if (isset($validated['access_request_id'])) {
            AccessRequest::whereKey($validated['access_request_id'])->update(['status' => 'invited']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Access sent to :email.', ['email' => $invitation->email])]);

        return to_route('platform.owner-invitations.index');
    }

    /**
     * Dismiss an access request. Deleted rather than kept: no reason to hold their personal data.
     */
    public function dismissRequest(AccessRequest $accessRequest): RedirectResponse
    {
        $accessRequest->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Request dismissed.')]);

        return to_route('platform.owner-invitations.index');
    }

    /**
     * Cancel an invitation that hasn't been used yet.
     */
    public function destroy(OwnerInvitation $invitation): RedirectResponse
    {
        abort_if($invitation->accepted_at !== null, 404);

        $invitation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation cancelled.')]);

        return to_route('platform.owner-invitations.index');
    }
}
