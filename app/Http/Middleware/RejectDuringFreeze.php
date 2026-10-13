<?php

namespace App\Http\Middleware;

use App\Services\Migration\Export\FreezeState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Payment and bank-transfer webhooks are not behind maintenance mode, and a webhook that lands during a
 * freeze export would change an order that has already been copied. While a freeze is live they get a
 * 503 with Retry-After, which the gateways treat as "try again later". Outside a freeze this costs one
 * missing file check.
 */
class RejectDuringFreeze
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! is_file(FreezeState::file()->path())) {
            return $next($request);
        }

        $state = FreezeState::read();

        if ($state === null || ! $state->isLive()) {
            return $next($request);
        }

        return response()->json(['message' => 'The service is temporarily unavailable. Please retry shortly.'], 503)
            ->header('Retry-After', '300');
    }
}
