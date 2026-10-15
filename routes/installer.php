<?php

use App\Http\Controllers\InstallerController;
use App\Http\Controllers\InstallerImportChoiceController;
use App\Http\Controllers\InstallerImportController;
use Illuminate\Support\Facades\Route;

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

    // While a browser holds the import branch these belong to it (ImportBranchGuardsLegacyInstaller); otherwise unchanged.
    Route::middleware('import.legacy')->group(function () {
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

    // The import branch ("Import an existing site"). Starting it is open to any browser that reaches /install (it creates
    // only a wizard owned by that browser); every other page belongs to the browser that started it (ImportWizardOwner).
    Route::get('/install/import', [InstallerImportController::class, 'mode'])
        ->name('installer.import.mode');
    Route::post('/install/import', [InstallerImportController::class, 'start'])
        ->name('installer.import.start');

    Route::middleware('import.wizard')->group(function () {
        Route::post('/install/import/cancel', [InstallerImportController::class, 'cancel'])
            ->name('installer.import.cancel');
        Route::get('/install/import/package', [InstallerImportController::class, 'package'])
            ->name('installer.import.package');
        Route::post('/install/import/package', [InstallerImportController::class, 'open'])
            ->name('installer.import.package.open');
        Route::post('/install/import/package/forget', [InstallerImportController::class, 'forget'])
            ->name('installer.import.package.forget');

        Route::get('/install/import/choose', [InstallerImportChoiceController::class, 'show'])
            ->name('installer.import.choose');
        Route::post('/install/import/choose', [InstallerImportChoiceController::class, 'save'])
            ->name('installer.import.choose.save');

        // Step 5. A GET only shows what was chosen and creates nothing; the run is created by the POST (CSRF token and the
        // browser's wizard cookie), which hands the browser over to the runner page (/migrate/run/{id}).
        Route::get('/install/import/check', [InstallerImportController::class, 'check'])
            ->name('installer.import.check');
        Route::post('/install/import/check', [InstallerImportController::class, 'begin'])
            ->name('installer.import.check.run');
    });
});

Route::get('/install/complete', [InstallerController::class, 'complete'])
    ->name('installer.complete');

// The report of an import. Outside the `installer` group for the same reason as installer.complete: by the time anyone reads it
// the site is installed. Reached only with the signed address the runner page hands out (two hours); it shows counts and names.
Route::get('/install/import/report/{id}', [InstallerImportController::class, 'report'])
    ->where('id', '[a-z0-9]{12,40}')
    ->middleware(['signed', 'throttle:30,1'])
    ->name('installer.import.report');
