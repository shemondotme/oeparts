<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A page lives at ONE address. Behind nginx's "try_files $uri $uri/ /index.php?$query_string" (and Apache's
 * PATH_INFO handling) a request for /index.php/fr/parts/A3094210211 reaches the front controller too, and Laravel
 * strips the script name and answers the page at /fr/parts/A3094210211 with a 200, so every page also exists under an
 * /index.php/ prefix. Search engines find such links (Search Console listed one with clicks) and index the copy.
 *
 * Found in the 2026-11-21 SEO audit. Only GET and HEAD are redirected (a form that posts to such an address keeps
 * working); the query string is kept; /index.php on its own goes to the home page. The redirect is permanent, so
 * what was indexed under the prefix moves to the clean address.
 */
class RedirectIndexPhpPaths
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        // The address as the client asked for it: getPathInfo() has already lost the script name.
        $path = (string) parse_url((string) $request->server->get('REQUEST_URI', ''), PHP_URL_PATH);

        if (preg_match('#^/index\.php(/.*)?$#i', $path, $m) !== 1) {
            return $next($request);
        }

        $clean = ($m[1] ?? '') !== '' ? $m[1] : '/';
        $query = (string) $request->getQueryString();

        return redirect($clean.($query !== '' ? '?'.$query : ''), 301);
    }
}
