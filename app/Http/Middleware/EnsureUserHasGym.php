<?php

namespace App\Http\Middleware;

use App\Tenancy\CurrentGym;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends logged-in users who don't belong to any gym to the "Create your gym" page.
 */
class EnsureUserHasGym
{
    public function __construct(private CurrentGym $currentGym) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->currentGym->check() && ! $request->user()?->is_platform_admin) {
            return to_route('gyms.create');
        }

        return $next($request);
    }
}
