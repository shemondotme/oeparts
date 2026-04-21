<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackUtm
{
    protected array $utmParams = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content'];

    public function handle(Request $request, Closure $next): Response
    {
        foreach ($this->utmParams as $param) {
            if ($request->has($param)) {
                session([$param => $request->get($param)]);
            }
        }

        return $next($request);
    }
}
