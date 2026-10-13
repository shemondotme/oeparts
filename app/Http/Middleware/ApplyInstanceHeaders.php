<?php

namespace App\Http\Middleware;

use App\Services\Instance\SafeMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * On a staging or quarantine copy every response says "do not index" in its headers as well as in the page, so
 * images, files, JSON and error pages are covered too, and names the mode so it can be seen with a plain curl.
 * On the real shop it adds nothing and costs one config read.
 */
class ApplyInstanceHeaders
{
    public function __construct(private readonly SafeMode $safeMode) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->safeMode->isCopy()) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
            $response->headers->set('X-OE-Instance-Mode', $this->safeMode->mode()->value);
        }

        return $response;
    }
}
