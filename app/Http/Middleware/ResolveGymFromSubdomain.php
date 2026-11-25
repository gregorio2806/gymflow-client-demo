<?php

namespace App\Http\Middleware;

use App\Enums\GymStatus;
use App\Models\Gym;
use App\Tenancy\CurrentGym;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveGymFromSubdomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(CurrentGym::class);
        $context->forget();
        $suffix = '.'.config('tenancy.platform_domain');
        $host = $request->getHost();
        abort_unless(str_ends_with($host, $suffix), 404);
        $slug = substr($host, 0, -strlen($suffix));
        abort_unless(preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $slug)
            && ! in_array($slug, Gym::RESERVED_SLUGS, true), 404);
        $gym = Gym::where('slug', $slug)->where('status', GymStatus::Active)->whereNull('demo_expires_at')->firstOrFail();

        // Public host context never inherits dashboard roles or branch restrictions.
        return $context->runAs($gym, fn () => $next($request));
    }
}
