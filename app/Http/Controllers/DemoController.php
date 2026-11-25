<?php

namespace App\Http\Controllers;

use App\Actions\StartDemo;
use App\Http\Middleware\SetCurrentGym;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DemoController extends Controller
{
    /**
     * "Try demo" button: create a private demo gym and log the visitor in as its owner.
     */
    public function store(Request $request, StartDemo $startDemo): RedirectResponse
    {
        if (StartDemo::atCapacity()) {
            return to_route('home')->with('status', __('The demo is busy right now. Please try again in a few minutes.'));
        }

        $owner = $startDemo->handle();

        Auth::login($owner);
        $request->session()->regenerate();
        $request->session()->put(SetCurrentGym::SESSION_KEY, $owner->gyms()->value('gyms.id'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Welcome to the demo! Look around — nothing here is real.')]);

        return to_route('dashboard');
    }

    /**
     * Leave the demo. Sign-up is invite-only, so point them to requesting access.
     */
    public function destroy(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_demo, 404);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(route('home').'#pricing')->with('status', __('Thanks for trying the demo! To get your own gym, email :email to request access.', [
            'email' => config('platform.access_email'),
        ]));
    }
}
