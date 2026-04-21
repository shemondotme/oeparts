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
            // 301 permanent redirect so Google updates its index
            return redirect()->route('frontend.search.results', [
                'lang' => $request->route('lang'),
                'oem'  => $normalized,
            ], 301);
        }

        return $next($request);
    }
}
