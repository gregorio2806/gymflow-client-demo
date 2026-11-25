<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * For plain Blade pages (landing page, gym websites).
 *
 * When the Vue app navigates to one of these pages (a <Link>, or the redirect after logout),
 * Inertia expects an Inertia response; getting plain HTML, it shows the page inside a modal.
 * This tells Inertia to do a normal full page load instead.
 */
class FullPageVisit
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->header('X-Inertia')) {
            // Keep flash messages (e.g. "Thanks for trying the demo") for the real page load that follows.
            if ($request->hasSession()) {
                $request->session()->reflash();
            }

            return Inertia::location($request->fullUrl());
        }

        return $next($request);
    }
}
