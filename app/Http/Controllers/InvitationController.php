<?php

namespace App\Http\Controllers;

use App\Actions\AcceptInvitation;
use App\Actions\CreateInvitedUser;
use App\Http\Middleware\SetCurrentGym;
use App\Models\Gym;
use App\Models\GymInvitation;
use App\Models\User;
use App\Support\LegalDocuments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The invitee's side: open the emailed link, then accept.
 */
class InvitationController extends Controller
{
    public function show(Request $request, string $token): SymfonyResponse
    {
        $invitation = GymInvitation::findPendingByToken($token);

        if (! $invitation) {
            return Inertia::render('invitations/Show', ['status' => 'invalid'])->toResponse($request)->setStatusCode(404);
        }

        $user = $request->user();

        if (! $user) {
            // Come back here after logging in or creating an account.
            $request->session()->put('url.intended', $request->url());
        }

        return Inertia::render('invitations/Show', [
            'status' => match (true) {
                ! $user => 'guest',
                $this->emailMatches($user->email, $invitation) => 'ready',
                default => 'wrong-account',
            },
            'invitation' => [
                'gymName' => Gym::findOrFail($invitation->gym_id)->name,
                'role' => $invitation->role->value,
                'email' => $invitation->email,
            ],
            // Guests: new people set a name + password here (public sign-up is closed); existing accounts log in.
            'hasAccount' => ! $user && User::where('email', $invitation->email)->exists(),
            'token' => $token,
            'legal' => LegalDocuments::bundle(),
        ])->toResponse($request);
    }

    /**
     * A new person creates their account from the invitation and joins the gym in one step.
     */
    public function register(Request $request, string $token, CreateInvitedUser $createInvitedUser, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        $invitation = GymInvitation::findPendingByToken($token);

        abort_unless($invitation, 404);

        [$user, $gym] = DB::transaction(function () use ($invitation, $request, $createInvitedUser, $acceptInvitation) {
            $user = $createInvitedUser->handle($invitation->email, $request->only(['name', 'password', 'password_confirmation', 'terms_accepted', 'policy_version']));

            return [$user, $acceptInvitation->handle($invitation, $user)];
        });

        Auth::login($user);
        $request->session()->regenerate();

        $request->session()->put(SetCurrentGym::SESSION_KEY, $gym->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Welcome to :gym!', ['gym' => $gym->name])]);

        return to_route('dashboard');
    }

    public function accept(Request $request, string $token, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        $invitation = GymInvitation::findPendingByToken($token);

        abort_unless($invitation, 404);
        abort_unless($this->emailMatches($request->user()->email, $invitation), 403);

        $gym = DB::transaction(function () use ($request, $invitation, $acceptInvitation) {
            LegalDocuments::validate($request->only(['terms_accepted', 'policy_version']));
            $gym = $acceptInvitation->handle($invitation, $request->user());
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            LegalDocuments::acceptUser($user, $request->only(['terms_accepted', 'policy_version']));

            return $gym;
        });

        $request->session()->put(SetCurrentGym::SESSION_KEY, $gym->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Welcome to :gym!', ['gym' => $gym->name])]);

        return to_route('dashboard');
    }

    private function emailMatches(string $email, GymInvitation $invitation): bool
    {
        return mb_strtolower($email) === $invitation->email;
    }
}
