<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InstallerMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // If already installed, block installer access
        if (file_exists(storage_path('installed.lock'))) {
            return redirect('/');
        }

        return $next($request);
    }
}
