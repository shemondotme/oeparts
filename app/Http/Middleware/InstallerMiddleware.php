<?php

namespace App\Http\Middleware;

use App\Services\Install\InstallManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

class InstallerMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (file_exists(storage_path('installed.lock'))) {
            return redirect('/');
        }

        // Second, independent gate: even if the lock file is missing (deleted
        // by mistake, or per step6-complete's own "delete this file to
        // reinstall" instructions on a host that already has real data), a
        // completed install leaves an unmistakable signature — a populated
        // admins table. Detecting that here, BEFORE the wizard ever loads,
        // is what stops InstallManager::stepMigrate()'s migrate:fresh from
        // ever running against a live production database. Self-healing:
        // recreate the lock file so this DB query only has to run once.
        if (app(InstallManager::class)->looksAlreadyInstalled()) {
            @File::put(storage_path('installed.lock'), 'Detected at '.now()->toDateTimeString());

            return redirect('/');
        }

        return $next($request);
    }
}
