<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('not-demo'). For actions a throwaway demo account must not take:
 * anything that sends email to real people, or changes login details.
 */
class BlockDemoAccounts
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->user()?->is_demo, 403, __('This isn\'t available in the demo. Create your own gym to use it.'));

        return $next($request);
    }
}
