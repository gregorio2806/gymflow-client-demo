<?php

namespace App\Http\Middleware;

use App\Tenancy\CurrentGym;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('gym-site.*', 'portal.*')) {
            return $next($request);
        }
        $hosts = [config('tenancy.platform_domain')];
        if (app()->environment(['local', 'testing'])) {
            $hosts = [...$hosts, 'localhost', '127.0.0.1'];
        }
        if (! in_array($request->getHost(), $hosts, true)) {
            app(CurrentGym::class)->forget();
            abort(404);
        }

        return $next($request);
    }
}
