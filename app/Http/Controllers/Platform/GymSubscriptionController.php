<?php

namespace App\Http\Controllers\Platform;

use App\Enums\BillingCycle;
use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Platform admin sets a gym's plan by hand (e.g. after a GCash payment) until online billing exists.
 */
class GymSubscriptionController extends Controller
{
    public function update(Request $request, Gym $gym): RedirectResponse
    {
        $validated = $request->validate([
            'plan' => ['required', Rule::enum(Plan::class)],
            'billing_cycle' => ['required', Rule::enum(BillingCycle::class)],
            'status' => ['required', Rule::enum(SubscriptionStatus::class)],
            'extra_branches' => ['required', 'integer', 'min:0', 'max:50'],
            'current_period_ends_at' => ['nullable', 'date'],
        ]);

        // Extra branches only exist on Multi-Branch.
        if ($validated['plan'] !== Plan::MultiBranch->value) {
            $validated['extra_branches'] = 0;
        }

        DB::transaction(function () use ($gym, $validated) {
            Gym::whereKey($gym->id)->lockForUpdate()->firstOrFail();
            $subscription = $gym->subscription()->lockForUpdate()->firstOrFail();
            $subscription->update($validated);

            if ($subscription->wasChanged()) {
                Audit::logForGym($gym, 'subscription.updated', $subscription);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plan saved for :gym.', ['gym' => $gym->name])]);

        return to_route('platform.gyms.features.edit', $gym);
    }
}
