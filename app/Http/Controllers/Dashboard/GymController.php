<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\CreateGym;
use App\Enums\Plan;
use App\Http\Controllers\Controller;
use App\Http\Middleware\SetCurrentGym;
use App\Http\Requests\Dashboard\StoreGymRequest;
use App\Models\OwnerInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Create your gym". Sign-up is invite-only: only owners with an accepted, unused owner
 * invitation (or the platform admin) can create a gym. The plan comes from the invitation.
 */
class GymController extends Controller
{
    public function create(Request $request): Response
    {
        $user = $request->user();
        $invitation = OwnerInvitation::unusedFor($user);

        if (! $invitation && ! $user->is_platform_admin) {
            return Inertia::render('onboarding/NoAccess', [
                'accessEmail' => config('platform.access_email'),
            ]);
        }

        $plan = $invitation?->plan ?? Plan::Starter;

        return Inertia::render('onboarding/CreateGym', [
            'plan' => ['label' => $plan->label(), 'monthlyPrice' => $plan->monthlyPrice()],
            'gymName' => $invitation?->gym_name,
        ]);
    }

    public function store(StoreGymRequest $request, CreateGym $createGym): RedirectResponse
    {
        $user = $request->user();
        $invitation = OwnerInvitation::unusedFor($user);

        abort_if(! $invitation && ! $user->is_platform_admin, 403);

        $gym = DB::transaction(function () use ($request, $user, $invitation, $createGym) {
            $gym = $createGym->handle($user, $request->validated(), $invitation?->plan ?? Plan::Starter);
            $invitation?->forceFill(['gym_id' => $gym->id])->save(); // one invitation = one gym

            return $gym;
        });

        $request->session()->put(SetCurrentGym::SESSION_KEY, $gym->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Welcome to :gym!', ['gym' => $gym->name])]);

        return to_route('dashboard');
    }
}
