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
        // Skip for admin routes and API
        if ($request->is('admin/*') || $request->is('api/*')) {
            return $next($request);
        }

        $path = ltrim($request->path(), '/');

        // Check cache first
        $redirect = Cache::remember("redirect.{$path}", now()->addHours(24), function () use ($path) {
            return RedirectModel::where('from_url', $path)
                ->where('is_active', true)
                ->first();
        });

        if ($redirect) {
            // Increment hit count
            $redirect->increment('hit_count');

            // Perform redirect
            return redirect($redirect->to_url, $redirect->type);
        }

        return $next($request);
    }
}
