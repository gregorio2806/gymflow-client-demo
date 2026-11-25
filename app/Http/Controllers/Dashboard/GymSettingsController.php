<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateGymSettingsRequest;
use App\Support\Audit;
use App\Tenancy\CurrentGym;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GymSettingsController extends Controller
{
    public function __construct(private CurrentGym $currentGym) {}

    /**
     * Show the current gym's settings form (owners only).
     */
    public function edit(): Response
    {
        $gym = $this->currentGym->get();

        Gate::authorize('update', $gym);

        return Inertia::render('dashboard/gym/Settings', [
            'gym' => $gym->only(['name', 'email', 'phone', 'address', 'timezone']),
        ]);
    }

    /**
     * Save the current gym's settings.
     */
    public function update(UpdateGymSettingsRequest $request): RedirectResponse
    {
        $gym = $this->currentGym->get();

        $gym->update($request->validated());

        if ($gym->wasChanged()) {
            Audit::log('gym.updated', $gym);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Gym settings saved.')]);

        return to_route('gym.settings.edit');
    }
}
