<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idle-timeout for the 'web' (customer) guard, independent of Laravel's
 * global SESSION_LIFETIME (config/session.php) — that config is a single
 * value shared with the admin guard, so a merchant-configurable customer
 * inactivity window (auth.customer_session_lifetime) needs its own check.
 */
class EnforceCustomerSessionLifetime
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('web')->check()) {
            return $next($request);
        }

        $lifetimeMinutes = (int) settings('auth.customer_session_lifetime', 120);
        $lastActivity = $request->session()->get('customer_last_activity');

        if ($lastActivity && now()->diffInMinutes($lastActivity, true) > $lifetimeMinutes) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('auth.session_expired'),
                ], 401);
            }

            return redirect()->route('frontend.home', ['lang' => app()->getLocale()])
                ->with('error', __('auth.session_expired'))
                ->with('show_auth_modal', true);
        }

        $request->session()->put('customer_last_activity', now());

        return $next($request);
    }
}
