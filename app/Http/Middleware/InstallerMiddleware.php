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

        $installer = app(InstallManager::class);

        // An install run already in progress (or one that failed partway and
        // is waiting to be retried) legitimately has a populated admins table
        // by step 8 (create_admin) — three steps before the run actually
        // finishes (persist_settings, mail_env, lock). Without this check,
        // the "already installed" heuristic below fires on the very next AJAX
        // poll after create_admin, redirecting /install/run/advance away
        // before InstallManager::advance() ever runs — silently stalling the
        // progress bar forever and self-writing installed.lock over a run
        // that never reached its own lock step.
        if ($installer->isRunning() || $installer->hasFailedRun()) {
            return $next($request);
        }

        // Second, independent gate: even if the lock file is missing (deleted
        // by mistake, or per step6-complete's own "delete this file to
        // reinstall" instructions on a host that already has real data), a
        // completed install leaves an unmistakable signature — a populated
        // admins table. Detecting that here, BEFORE the wizard ever loads,
        // is what stops InstallManager::stepMigrate()'s migrate:fresh from
        // ever running against a live production database. Self-healing:
        // recreate the lock file so this DB query only has to run once.
        if ($installer->looksAlreadyInstalled()) {
            @File::put(storage_path('installed.lock'), 'Detected at '.now()->toDateTimeString());

            return redirect('/');
        }

        return $next($request);
    }
}
