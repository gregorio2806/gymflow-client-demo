<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetCurrentGym;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CurrentGymController extends Controller
{
    /**
     * Switch the gym the user is working in.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate(['gym_id' => ['required', 'integer']]);

        // 404 when the user doesn't belong to that gym — don't confirm it exists.
        $gym = $request->user()->gyms()->wherePivot('is_active', true)->whereKey($validated['gym_id'])->firstOrFail();

        $request->session()->put(SetCurrentGym::SESSION_KEY, $gym->id);

        return to_route('dashboard');
    }
}
