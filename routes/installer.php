<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InstallerController;

/*
|--------------------------------------------------------------------------
| Installer Routes (Sprint 18)
|--------------------------------------------------------------------------
|
| These routes are used by the web installer. Most are protected by the
| InstallerMiddleware, which blocks access once the site is installed
| (checked two ways: storage/installed.lock, and — even if that file is
| missing — a populated `admins` table, so migrate:fresh can never run
| again over a live database just because the lock file was deleted).
| `throttle:60,1` bounds both the form submissions and the AJAX progress
| polling that drives step 6 (roughly one request every 0.6s while an
| install runs — well under the limit even with normal step retries).
|
| installer.complete is intentionally OUTSIDE the `installer` middleware
| group: by the time a user reaches it, InstallManager's last step has
| already written storage/installed.lock, so gating this route the same
| way would immediately bounce them to '/' and they'd never see it.
|
*/

Route::middleware(['installer', 'throttle:60,1'])->group(function () {
    // Step 1: Requirements check
    Route::get('/install', [InstallerController::class, 'index'])
        ->name('installer.index');

    // Step 2: Database configuration
    Route::get('/install/database', [InstallerController::class, 'database'])
        ->name('installer.database');
    Route::post('/install/database', [InstallerController::class, 'processDatabase'])
        ->name('installer.process-database');

    // Step 3: Site settings
    Route::get('/install/site-settings', [InstallerController::class, 'siteSettings'])
        ->name('installer.site-settings');
    Route::post('/install/site-settings', [InstallerController::class, 'processSiteSettings'])
        ->name('installer.process-site-settings');

    // Step 4: Admin account
    Route::get('/install/admin-account', [InstallerController::class, 'adminAccount'])
        ->name('installer.admin-account');
    Route::post('/install/admin-account', [InstallerController::class, 'processAdminAccount'])
        ->name('installer.process-admin-account');

    // Step 5: Email setup
    Route::get('/install/email-setup', [InstallerController::class, 'emailSetup'])
        ->name('installer.email-setup');
    Route::post('/install/email-setup', [InstallerController::class, 'processEmailSetup'])
        ->name('installer.process-email-setup');
    Route::post('/install/email-setup/test-mail', [InstallerController::class, 'testMail'])
        ->name('installer.test-mail');

    // Step 6: chunked, AJAX-polled installation run (see InstallManager)
    Route::get('/install/run', [InstallerController::class, 'run'])
        ->name('installer.install');
    Route::post('/install/run/advance', [InstallerController::class, 'advance'])
        ->name('installer.advance');
    Route::post('/install/retry', [InstallerController::class, 'retry'])
        ->name('installer.retry');
});

Route::get('/install/complete', [InstallerController::class, 'complete'])
    ->name('installer.complete');
