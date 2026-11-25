<?php

namespace App\Http\Middleware;

use App\Enums\GymRole;
use App\Enums\Plan;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\User;
use App\Tenancy\CurrentGym;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the logged-in user's current gym from the session.
 *
 * The session only stores a gym ID; membership is re-checked on every request,
 * so removing a user from a gym takes effect immediately.
 */
class SetCurrentGym
{
    public const SESSION_KEY = 'current_gym_id';

    public function __construct(private CurrentGym $currentGym) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->currentGym->forget();
        $user = $request->user();

        if ($user instanceof User && ($gym = $this->resolve($request, $user))) {
            $role = $gym->pivot->role;
            $branchId = $gym->pivot->branch_id;
            if ($role === GymRole::BranchManager) {
                abort_unless($gym->plan() === Plan::MultiBranch && $branchId !== null && $gym->branches()->whereKey($branchId)->exists(), 403);
            }
            $this->currentGym->set($gym, $role, $branchId);

            $selectionKey = 'view_branch.'.$gym->id;
            $selected = $request->session()->get($selectionKey);
            if ($selected !== null) {
                if (Branch::whereKey($selected)->exists()) {
                    $this->currentGym->setViewBranch((int) $selected);
                } else {
                    $request->session()->forget($selectionKey);
                }
            }
            // Keep existing page-level branch filters synchronized with the navbar.
            if ($request->isMethod('GET') && $request->routeIs('attendance.index', 'payments.overview', 'reports.index') && $request->query->has('branch_id')) {
                $data = $request->validate(['branch_id' => ['nullable', 'integer']]);
                $branch = isset($data['branch_id']) ? Branch::findOrFail($data['branch_id']) : null;
                if ($role !== GymRole::BranchManager) {
                    $request->session()->put($selectionKey, $branch?->id);
                    $this->currentGym->setViewBranch($branch?->id);
                }
            }

            if ($request->session()->get(self::SESSION_KEY) !== $gym->id) {
                $request->session()->put(self::SESSION_KEY, $gym->id);
            }
        }

        return $next($request);
    }

    /**
     * The gym saved in the session if the user still belongs to it,
     * otherwise the first gym they joined.
     */
    private function resolve(Request $request, User $user): ?Gym
    {
        $gymId = $request->session()->get(self::SESSION_KEY);

        $gym = $gymId ? $user->gyms()->wherePivot('is_active', true)->whereKey($gymId)->first() : null;

        return $gym ?? $user->gyms()->wherePivot('is_active', true)->orderBy('gym_user.id')->first();
    }
}
