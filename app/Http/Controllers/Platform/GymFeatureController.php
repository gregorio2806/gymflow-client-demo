<?php

namespace App\Http\Controllers\Platform;

use App\Enums\GymFeature;
use App\Enums\Plan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UpdateGymFeaturesRequest;
use App\Models\Gym;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Pennant\Feature;

/**
 * Platform admin ("super admin"): switch modules on/off per gym.
 */
class GymFeatureController extends Controller
{
    /**
     * All gyms, newest first.
     */
    public function index(): Response
    {
        // Real gyms only — temporary "Try demo" gyms would bury them.
        $gyms = Gym::query()->whereNull('demo_expires_at')->with('subscription')->latest()->paginate(25)->through(fn (Gym $gym) => [
            'id' => $gym->id,
            'name' => $gym->name,
            'slug' => $gym->slug,
            'status' => $gym->status->value,
            'plan' => $gym->plan()->label(),
            'subscriptionStatus' => $gym->subscription->status->value,
        ]);

        return Inertia::render('platform/Gyms', ['gyms' => $gyms]);
    }

    /**
     * Feature switches for one gym.
     */
    public function edit(Gym $gym): Response
    {
        $active = $this->activeFeatures($gym);

        return Inertia::render('platform/GymFeatures', [
            'gym' => ['id' => $gym->id, 'name' => $gym->name, 'slug' => $gym->slug],
            'subscription' => [
                'plan' => $gym->subscription->plan->value,
                'billingCycle' => $gym->subscription->billing_cycle->value,
                'status' => $gym->subscription->status->value,
                'extraBranches' => $gym->subscription->extra_branches,
                'currentPeriodEndsAt' => $gym->subscription->current_period_ends_at?->toDateString(),
            ],
            'plans' => collect(Plan::cases())->map(fn (Plan $plan) => ['value' => $plan->value, 'label' => $plan->label()]),
            'features' => collect(GymFeature::cases())->map(fn (GymFeature $feature) => [
                'key' => $feature->value,
                'label' => $feature->label(),
                'active' => in_array($feature->value, $active, true),
                'default' => $feature->enabledByDefault($gym->plan()),
                'mode' => array_key_exists($feature->value, $gym->feature_overrides ?? [])
                    ? ($gym->feature_overrides[$feature->value] ? 'on' : 'off')
                    : (in_array($feature->value, $active, true) === $feature->enabledByDefault($gym->plan()) ? 'default' : (in_array($feature->value, $active, true) ? 'on' : 'off')),
            ]),
        ]);
    }

    public function update(UpdateGymFeaturesRequest $request, Gym $gym): RedirectResponse
    {
        DB::transaction(function () use ($request, $gym) {
            $gym = Gym::whereKey($gym->id)->lockForUpdate()->firstOrFail();
            $validated = $request->validated();
            $before = $this->activeFeatures($gym);
            $beforeOverrides = $gym->feature_overrides ?? [];
            $overrides = $beforeOverrides;

            if (array_key_exists('overrides', $validated)) {
                foreach ($validated['overrides'] as $key => $mode) {
                    if ($mode === 'default') {
                        unset($overrides[$key]);
                    } else {
                        $overrides[$key] = $mode === 'on';
                    }
                    Feature::for($gym)->forget($key);
                }
            } else {
                // Preserve the original checkbox submission contract.
                foreach (GymFeature::cases() as $feature) {
                    $enabled = in_array($feature->value, $validated['features'] ?? [], true);
                    if ($enabled !== in_array($feature->value, $before, true)) {
                        $overrides[$feature->value] = $enabled;
                    }
                }
            }
            $gym->forceFill(['feature_overrides' => $overrides ?: null])->save();

            foreach ($overrides as $key => $enabled) {
                $enabled ? Feature::for($gym)->activate($key) : Feature::for($gym)->deactivate($key);
            }
            $after = $this->activeFeatures($gym);

            if (array_diff($before, $after) || array_diff($after, $before) || $beforeOverrides !== $overrides) {
                sort($before);
                sort($after);
                Audit::logForGym($gym, 'features.updated', $gym, ['before' => ['features' => $before, 'overrides' => $beforeOverrides], 'after' => ['features' => $after, 'overrides' => $overrides]]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Features saved for :gym.', ['gym' => $gym->name])]);

        return to_route('platform.gyms.features.edit', $gym);
    }

    /**
     * @return list<string>
     */
    private function activeFeatures(Gym $gym): array
    {
        $values = Feature::for($gym)->values(array_map(fn (GymFeature $f) => $f->value, GymFeature::cases()));

        return array_keys(array_filter($values));
    }
}
