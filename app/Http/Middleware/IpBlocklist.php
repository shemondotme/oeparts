<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class IpBlocklist
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth('admin')->check()) {
            return $next($request);
        }

        $ip = $request->ip();

        // Cache blocklist check for 10 seconds per IP — short TTL ensures
        // newly blocked/unblocked IPs take effect almost immediately.
        $blocked = Cache::remember("ip_blocked.{$ip}", now()->addSeconds(10), function () use ($ip) {
            try {
                return \App\Models\IpBlocklist::where('ip_address', $ip)
                    ->where('is_active', true)
                    ->where(function ($query) {
                        $query->whereNull('expires_at')
                              ->orWhere('expires_at', '>', now());
                    })
                    ->exists();
            } catch (\Exception) {
                return false;
            }
        });

        if ($blocked) {
            Log::warning('Blocked request from IP: ' . $request->ip(), ['path' => $request->path()]);
            abort(403, 'Access denied.');
        }

        return $next($request);
    }
}
