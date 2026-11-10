<?php

namespace App\Http\Middleware;

use App\Models\Redirect as RedirectModel;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class HandleRedirects
{
    /** Longest chain collapsed into one hop; beyond this the remaining hops are left to the browser. */
    private const MAX_HOPS = 5;

    public function handle(Request $request, Closure $next): Response
    {
        return self::resolve($request) ?? $next($request);
    }

    /**
     * Admin-defined redirect for this request, or null. Public + static so
     * the 404 handler (bootstrap/app.php) can use the exact same lookup for
     * legacy URLs that never match a route — in particular locale-less ones
     * like "/old-page", which this middleware (wired only onto the
     * "{lang}" route group) never saw.
     */
    public static function resolve(Request $request): ?Response
    {
        if ($request->is('admin/*') || $request->is('api/*')) {
            return null;
        }

        // Unlike EnforceCanonicalHost (which explicitly gates its own
        // slash-strip to GET/HEAD), this used to run for every method. It's
        // wired onto the entire {lang} route group, including POST routes
        // like login/checkout/contact-submit — an admin-created redirect
        // whose from_url happens to collide with one of those paths would
        // silently 301/302 a real form submission instead of letting it
        // process.
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return null;
        }

        $redirect = self::lookup(strtolower(ltrim($request->path(), '/')));

        if (! $redirect) {
            return null;
        }

        try {
            $redirect->increment('hit_count');
        } catch (\Exception $e) {
            // Must never block the redirect itself — but a swallowed
            // failure here previously left no trace at all.
            Log::warning('Redirect hit_count increment failed', [
                'redirect_id' => $redirect->id,
                'error' => $e->getMessage(),
            ]);
        }

        [$target, $status] = self::collapseChain($redirect);

        // The visitor's query string (utm_*, gclid, ?page=…) must survive a
        // redirect, otherwise campaign attribution and paginated deep links
        // are silently dropped. A destination that carries its own query
        // string wins.
        $query = $request->getQueryString();
        if ($query && ! str_contains($target, '?')) {
            $target .= '?'.$query;
        }

        // ->type is cast to the RedirectType enum, not a scalar — Symfony's
        // RedirectResponse requires an int status and does not coerce enum
        // instances.
        return redirect($target, $status);
    }

    /**
     * Cache::remember() only short-circuits on a non-null cached value.
     * "No redirect for this path" — true for the overwhelming majority of
     * requests — is itself null, so a `false` sentinel is cached for the
     * miss case to stay distinguishable from "not yet cached".
     */
    private static function lookup(string $path): ?RedirectModel
    {
        $cached = Cache::remember("redirect.{$path}", now()->addSeconds(60), function () use ($path) {
            return RedirectModel::where('from_url', $path)
                ->where('is_active', true)
                ->first() ?: false;
        });

        return $cached ?: null;
    }

    /**
     * Follows A → B → C to the final destination so crawlers get ONE hop
     * (Google follows at most ~10 and wastes crawl budget on each), keeping
     * the first redirect's status. Stops on a loop or after MAX_HOPS.
     *
     * @return array{0: string, 1: int}
     */
    private static function collapseChain(RedirectModel $first): array
    {
        $status = (int) $first->type->value;
        $target = (string) $first->to_url;
        $seen = [$first->from_url => true];

        for ($hop = 0; $hop < self::MAX_HOPS; $hop++) {
            // Only same-site targets can chain; an absolute URL to another
            // host ends it.
            if (preg_match('#^https?://#i', $target) === 1) {
                $host = strtolower((string) parse_url($target, PHP_URL_HOST));
                if ($host !== strtolower((string) request()->getHost())) {
                    break;
                }
                $path = (string) parse_url($target, PHP_URL_PATH);
            } else {
                $path = (string) parse_url($target, PHP_URL_PATH);
            }

            $key = strtolower(trim($path, '/'));
            if ($key === '' || isset($seen[$key])) {
                break;
            }

            $next = self::lookup($key);
            if (! $next) {
                break;
            }

            $seen[$key] = true;
            $target = (string) $next->to_url;
        }

        return [$target, $status];
    }
}
