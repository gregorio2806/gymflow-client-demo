<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('feature:qr-attendance'). 404 when the current gym has the feature off.
 */
class EnsureGymFeature
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless(Feature::active($feature), 404);

        return $next($request);
    }
}
