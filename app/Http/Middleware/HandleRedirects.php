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
        $redirect = Cache::remember("redirect.{$path}", now()->addSeconds(60), function () use ($path) {
            return RedirectModel::where('from_url', $path)
                ->where('is_active', true)
                ->first();
        });

        if ($redirect) {
            try {
                $redirect->increment('hit_count');
            } catch (\Exception) {
                // Continue even if increment fails
            }

            return redirect($redirect->to_url, $redirect->type);
        }

        return $next($request);
    }
}
