<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tells the browser how many bytes an admin page really has, in a `Server-Timing: oelen;desc="N"` header.
 *
 * The page guard (resources/views/filament/hooks/navigation-guard.blade.php) compares that number with the number of
 * bytes the browser says it received for the same request (Resource Timing's decodedBodySize). When they differ, the page
 * was damaged on the way (a proxy, the web server or the network dropped or cut a part) — not by the application — and
 * the guard reports it to /admin/diagnostics/page-integrity together with the protocol the page came over (HTTP/1.1, h2,
 * h3). That is the evidence that says WHERE a "blank page until reload" comes from on a live server.
 *
 * Costs one strlen() of a string that already exists. Only HTML pages of the admin panel.
 */
class AnnouncePageSize
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! ($request->is('admin') || $request->is('admin/*'))
            || $response->getStatusCode() !== 200
            || $response instanceof StreamedResponse
            || $response instanceof BinaryFileResponse
            || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }

        $length = strlen((string) $response->getContent());
        $existing = (string) $response->headers->get('Server-Timing', '');
        $response->headers->set('Server-Timing', ($existing !== '' ? $existing.', ' : '').'oelen;desc="'.$length.'"');

        return $response;
    }
}
