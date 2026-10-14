<?php

namespace App\Http\Controllers;

use App\Services\Migration\Transport\ShareLinkService;
use App\Services\Migration\Transport\ShareSlots;
use App\Services\Migration\Transport\ShareStreamer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /_pkg/{token}: hands an exported package to the one downloader that holds a share link. Registered in routes/migration.php
 * (no session, no cookies, no CSRF, nothing that reads settings before the token is judged).
 *
 * Every way of being refused answers the same plain 404; the reason is in the audit history only, so the endpoint cannot be used
 * to find out which tokens exist. Too many refusals from one address are answered with 429. HEAD is refused (405): only the real
 * download may claim a link.
 */
class PackageShareController extends Controller
{
    public function show(Request $request, string $token, ShareLinkService $links, ShareStreamer $streamer, ShareSlots $slots): Response
    {
        if ($request->isMethod('HEAD')) {
            return new Response('', 405, ['Allow' => 'GET', 'Cache-Control' => 'no-store']);
        }

        $ip = (string) $request->ip();
        $denyKey = 'package-share-deny:'.$ip;
        $limit = max(1, (int) config('migration.share.deny_limit_per_hour', 20));

        if (RateLimiter::tooManyAttempts($denyKey, $limit)) {
            return new Response('Too many requests', 429, ['Retry-After' => (string) RateLimiter::availableIn($denyKey), 'Cache-Control' => 'no-store']);
        }

        // The slot is taken BEFORE the link is claimed: a "busy" answer after a successful first claim would leave a claimed
        // link whose claim secret the downloader never received. A refusal gives the slot straight back (it takes microseconds).
        $slot = $slots->acquire();

        if ($slot === null) {
            return new Response('Busy, try again shortly', 503, ['Retry-After' => '30', 'Cache-Control' => 'no-store']);
        }

        $claim = $links->claim($token, $request->header('X-OeParts-Claim'), $ip);
        $path = $claim['ok'] ? $streamer->fileFor($claim['link']) : null;

        if (! $claim['ok'] || $path === null) {
            $slots->release($slot);
            RateLimiter::hit($denyKey, 3600);

            return $this->notFound();
        }

        return $streamer->respond($claim['link'], $path, $request->header('Range'), $claim['claim'], $slot, $slots);
    }

    private function notFound(): Response
    {
        return new Response('Not found', 404, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
