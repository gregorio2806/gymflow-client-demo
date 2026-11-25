<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $private = preg_match('~^/(?:\.env(?:[./]|$)|\.git(?:/|$)|storage/(?:logs|app)(?:/|$)|vendor(?:/|$))~i', rawurldecode($request->getPathInfo()));
        $response = $private ? response('Not Found', 404) : $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $embeddedPreview = $request->routeIs('website.preview') && $request->boolean('embedded') && $response->isSuccessful();
        $response->headers->set('X-Frame-Options', $embeddedPreview ? 'SAMEORIGIN' : 'DENY');
        if ($embeddedPreview) {
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'self'");
        }
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');
        if (! $response->headers->has('Referrer-Policy')) {
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
        $ancestors = $embeddedPreview ? "'self'" : "'none'";
        $response->headers->set('Content-Security-Policy-Report-Only', "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors {$ancestors}; form-action 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'");
        if (app()->isProduction() && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
