<?php

namespace App\Http\Middleware;

use App\Services\OemNormalizerService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeOemUrl
{
    public function __construct(private OemNormalizerService $normalizer) {}

    public function handle(Request $request, Closure $next): Response
    {
        $oem = $request->route('oem');

        if (! $oem) {
            return $next($request);
        }

        $normalized = $this->normalizer->normalize($oem);

        if ($oem !== $normalized) {
            // Redirect to the SAME route the request matched, not always
            // frontend.search.results — this used to hardcode the hub
            // route, so a wrong-case hit on the per-product detail route
            // (/parts/{oem}/{idSlug}) silently dropped idSlug and
            // downgraded the visitor to the generic hub page instead of
            // the specific product they requested.
            $parameters = array_merge($request->route()->parameters(), ['oem' => $normalized]);

            $url = route($request->route()->getName(), $parameters);

            // route()/URL::route() only ever encodes named route
            // parameters — a filtered link like
            // "/parts/abc123?manufacturer=5&condition=new" lost every
            // query-string filter on this 301 otherwise.
            if ($queryString = $request->getQueryString()) {
                $url .= '?'.$queryString;
            }

            // 301 permanent redirect so Google updates its index
            return redirect($url, 301);
        }

        return $next($request);
    }
}
