<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class IpBlocklist
{
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();

        // Cache blocklist check for 5 minutes per IP to avoid repeated DB hits
        $blocked = Cache::remember("ip_blocked.{$ip}", now()->addMinutes(5), function () use ($ip) {
            try {
                return \App\Models\IpBlocklist::where('ip_address', $ip)
                    ->where('is_active', true)
                    ->exists();
            } catch (\Exception) {
                return false;
            }
        });

        if ($blocked) {
            abort(403, 'Access denied.');
        }

        return $next($request);
    }
}
