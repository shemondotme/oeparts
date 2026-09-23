<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MaintenanceMode
{
    /**
     * Handle an incoming request during maintenance mode.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Allow admin routes and health endpoints through regardless
        if ($request->is('admin/*') || $request->is('health') || $request->is('api/ping') || $request->is('install/*')) {
            return $next($request);
        }

        $enabled = (bool) settings('maintenance.enabled', false);

        if (! $enabled) {
            return $next($request);
        }

        // Check if IP is whitelisted (admin bypass)
        $allowedIps = settings('maintenance.allowed_ips', '');
        if (! empty($allowedIps)) {
            $allowedIpArray = array_map('trim', explode(',', $allowedIps));
            $clientIp = $request->ip();

            if (in_array($clientIp, $allowedIpArray)) {
                return $next($request);
            }
        }

        $message = settings('maintenance.message', ['en' => "We'll be back soon."]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => trans_field(normalize_multilang_setting($message)),
                'errors' => null,
            ], 503)
                ->header('Retry-After', '3600')
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        return response()->view('errors.maintenance', [
            'message' => $message,
            'estimatedBackAt' => settings('maintenance.estimated_back_at', ''),
            'showEstimatedTime' => (bool) settings('maintenance.show_estimated_time', false),
            'contactEmail' => settings('maintenance.contact_email', ''),
        ], 503)
            ->header('Retry-After', '3600')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
