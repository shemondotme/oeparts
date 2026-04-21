<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InstallerController;

/*
|--------------------------------------------------------------------------
| Installer Routes (Sprint 18)
|--------------------------------------------------------------------------
|
| These routes are used by the web installer. They are protected by the
| InstallerMiddleware which blocks access once the site is installed.
|
*/

Route::middleware(['installer'])->group(function () {
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

    // Step 6: Installation & complete
    Route::post('/install/run', [InstallerController::class, 'install'])
        ->name('installer.install');
    Route::get('/install/complete', [InstallerController::class, 'complete'])
        ->name('installer.complete');
});