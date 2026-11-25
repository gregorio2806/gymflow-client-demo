<?php

namespace App\Http\Middleware;

use App\Enums\GymFeature;
use App\Enums\GymRole;
use App\Enums\Plan;
use App\Models\Branch;
use App\Support\PlanLimits;
use App\Tenancy\CurrentGym;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Laravel\Pennant\Feature;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        if ($request->routeIs('portal.*')) {
            // Staff cookies may be shared across subdomains; never expose staff or tenant internals here.
            return [...parent::share($request), 'name' => config('app.name'), 'auth' => ['user' => null], 'currentGym' => null, 'currentBranch' => null, 'features' => [], 'planReview' => null, 'demo' => null, 'isPlatformAdmin' => false, 'sidebarOpen' => false];
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user() ? [
                    ...$request->user()->only(['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at']),
                    'two_factor_enabled' => $request->user()->two_factor_confirmed_at !== null,
                ] : null,
            ],
            // Only the fields the UI needs — Inertia props are visible in the browser.
            'currentGym' => function () {
                $currentGym = app(CurrentGym::class);

                return $currentGym->check() ? [
                    'id' => $currentGym->id(),
                    'name' => $currentGym->get()->name,
                    'slug' => $currentGym->get()->slug,
                    'role' => $currentGym->role()?->value,
                    'plan' => $currentGym->get()->plan()->value,
                    'hasExtraBranches' => $currentGym->get()->branches()->count() > 1,
                ] : null;
            },
            // Active feature keys for the current gym (hide nav for switched-off modules).
            'currentBranch' => function () {
                $current = app(CurrentGym::class);

                return $current->branchId() !== null ? Branch::findOrFail($current->branchId())->only(['id', 'name']) : null;
            },
            'navigationBranches' => fn () => app(CurrentGym::class)->check()
                ? Branch::orderByDesc('is_main')->orderBy('name')->get(['id', 'name'])->map(fn ($branch) => $branch->only(['id', 'name']))
                : [],
            'selectedBranchId' => fn () => app(CurrentGym::class)->viewBranchId(),
            'planReview' => function () {
                $current = app(CurrentGym::class);
                $gym = $current->get();
                if (! $gym || $current->role() !== GymRole::Owner || (! $gym->needs_staff_selection && ! $gym->needs_branch_selection)) {
                    return null;
                }

                return [
                    'staff' => $gym->needs_staff_selection,
                    'branches' => $gym->needs_branch_selection,
                    'staffLimit' => app(PlanLimits::class)->staffLimit($gym),
                    'branchLimit' => $gym->subscription->branchLimit(),
                    'perBranch' => $gym->plan() === Plan::MultiBranch,
                ];
            },
            'features' => function () {
                $gym = app(CurrentGym::class)->get();

                if (! $gym) {
                    return [];
                }

                $values = Feature::for($gym)->values(array_map(fn (GymFeature $f) => $f->value, GymFeature::cases()));

                return array_keys(array_filter($values));
            },
            // Set while exploring a "Try demo" gym (shows the demo banner).
            'demo' => function () {
                $gym = app(CurrentGym::class)->get();

                return $gym?->isDemo() ? ['expiresAt' => $gym->demo_expires_at->toIso8601String()] : null;
            },
            'isPlatformAdmin' => fn () => (bool) $request->user()?->is_platform_admin,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
