<?php

namespace App\Http\Controllers;

use App\Actions\CreateInvitedUser;
use App\Models\OwnerInvitation;
use App\Models\User;
use App\Support\LegalDocuments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The invited gym owner's side: open the emailed link → create account (or log in) → create the gym.
 */
class OwnerInvitationController extends Controller
{
    public function show(Request $request, string $token): SymfonyResponse
    {
        $invitation = OwnerInvitation::findPendingByToken($token);

        if (! $invitation) {
            return Inertia::render('owner-invitations/Show', ['status' => 'invalid'])->toResponse($request)->setStatusCode(404);
        }

        $user = $request->user();

        if (! $user) {
            $request->session()->put('url.intended', $request->url()); // back here after logging in
        }

        return Inertia::render('owner-invitations/Show', [
            'status' => match (true) {
                ! $user => 'guest',
                $this->emailMatches($user, $invitation) => 'ready',
                default => 'wrong-account',
            },
            'invitation' => ['email' => $invitation->email, 'plan' => $invitation->plan->label()],
            'hasAccount' => ! $user && User::where('email', $invitation->email)->exists(),
            'token' => $token,
            'legal' => LegalDocuments::bundle(),
        ])->toResponse($request);
    }

    /**
     * Logged in with the invited email: claim the invitation, then create the gym.
     */
    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = OwnerInvitation::findPendingByToken($token);

        abort_unless($invitation, 404);
        abort_unless($this->emailMatches($request->user(), $invitation), 403);

        DB::transaction(function () use ($invitation, $request) {
            $invitation = OwnerInvitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            abort_if($invitation->accepted_at || $invitation->expires_at->isPast(), 404);
            LegalDocuments::acceptUser(User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail(), $request->only(['terms_accepted', 'policy_version']));
            $this->claim($invitation, $request->user());
        });

        return to_route('gyms.create');
    }

    /**
     * New person: create the account from the invitation, then create the gym.
     */
    public function register(Request $request, string $token, CreateInvitedUser $createInvitedUser): RedirectResponse
    {
        $invitation = OwnerInvitation::findPendingByToken($token);

        abort_unless($invitation, 404);

        $user = DB::transaction(function () use ($invitation, $request, $createInvitedUser) {
            $invitation = OwnerInvitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            abort_if($invitation->accepted_at || $invitation->expires_at->isPast(), 404);
            $user = $createInvitedUser->handle($invitation->email, $request->only(['name', 'password', 'password_confirmation', 'terms_accepted', 'policy_version']));
            $this->claim($invitation, $user);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return to_route('gyms.create');
    }

    private function claim(OwnerInvitation $invitation, User $user): void
    {
        $invitation->forceFill(['user_id' => $user->id, 'accepted_at' => now()])->save();

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified(); // the link was emailed to this address
        }
    }

    private function emailMatches(User $user, OwnerInvitation $invitation): bool
    {
        return mb_strtolower($user->email) === $invitation->email;
    }
}
