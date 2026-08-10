<?php

namespace App\Http\Middleware;

use App\Models\Redirect as RedirectModel;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class HandleRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('admin/*') || $request->is('api/*')) {
            return $next($request);
        }

        $path = ltrim($request->path(), '/');

        // Cache::remember() only short-circuits on a non-null cached value.
        // "No redirect for this path" — true for the overwhelming majority
        // of requests — is itself null, so it was never actually cached:
        // this middleware ran a DB query on every single storefront page
        // view regardless of the TTL below. Cache a `false` sentinel for
        // the miss case so it's distinguishable from "not yet cached".
        $cached = Cache::remember("redirect.{$path}", now()->addSeconds(60), function () use ($path) {
            return RedirectModel::where('from_url', $path)
                ->where('is_active', true)
                ->first() ?: false;
        });

        if ($cached) {
            try {
                $cached->increment('hit_count');
            } catch (\Exception) {
                // Continue even if increment fails
            }

            // ->type is cast to the RedirectType enum, not a scalar —
            // Symfony's RedirectResponse constructor requires an int status
            // and does not coerce enum instances, so passing the enum
            // itself threw a TypeError on every single matched redirect:
            // visitors got a 500 instead of ever being redirected.
            return redirect($cached->to_url, (int) $cached->type->value);
        }

        return $next($request);
    }
}
