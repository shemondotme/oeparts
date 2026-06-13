<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InstallerMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // If already installed (via file or database), block installer access
        try {
            if (settings('system.installed', false)) {
                return redirect('/');
            }
        } catch (\Exception $e) {
            // Settings table may not exist yet during initial install
        }

        if (file_exists(storage_path('installed.lock'))) {
            return redirect('/');
        }

        return $next($request);
    }
}
