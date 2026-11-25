<?php

use App\Http\Middleware\BlockDemoAccounts;
use App\Http\Middleware\EnsureGymFeature;
use App\Http\Middleware\EnsurePlatformHost;
use App\Http\Middleware\EnsurePortalMember;
use App\Http\Middleware\EnsureUserHasGym;
use App\Http\Middleware\FullPageVisit;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PortalPrivacy;
use App\Http\Middleware\ResolveGymFromSubdomain;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetCurrentGym;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias([
            'has-gym' => EnsureUserHasGym::class,
            'blade-page' => FullPageVisit::class,
            'feature' => EnsureGymFeature::class,
            'not-demo' => BlockDemoAccounts::class,
            'gym-subdomain' => ResolveGymFromSubdomain::class,
            'portal-privacy' => PortalPrivacy::class,
            'portal-member' => EnsurePortalMember::class,
        ]);

        // Resolve the gym before tenant-aware rate limits and scoped route binding.
        $middleware->prependToPriorityList(before: ThrottleRequests::class, prepend: SetCurrentGym::class);
        $middleware->prependToPriorityList(before: ThrottleRequests::class, prepend: ResolveGymFromSubdomain::class);
        $middleware->prependToPriorityList(before: AuthenticatesRequests::class, prepend: EnsurePlatformHost::class);

        $middleware->web(append: [
            HandleAppearance::class,
            EnsurePlatformHost::class,
            SetCurrentGym::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
