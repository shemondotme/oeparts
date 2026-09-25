<?php

namespace App\Http\Middleware;

use App\Services\Updates\InterruptedUpdateResumer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global middleware: the first request that reaches freshly swapped-in code
 * finishes the update whose browser tab can no longer drive it. See
 * InterruptedUpdateResumer for why that tab is doomed. The cost when no update
 * is in flight is a single is_file() call.
 */
class ResumeInterruptedUpdate
{
    public function handle(Request $request, Closure $next): Response
    {
        app(InterruptedUpdateResumer::class)->resume();

        return $next($request);
    }
}
