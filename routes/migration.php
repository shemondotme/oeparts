<?php

use App\Http\Controllers\MigrationRunController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| The runner of a site import
|--------------------------------------------------------------------------
|
| Loaded from routes/web.php inside Route::withoutMiddleware(['web', VerifyCsrfToken::class]): no session, no CSRF,
| no installed-site redirect, nothing that reads the database. The database may be empty or half restored while an
| import runs, and these routes must keep answering. Authorisation is the per-run token (see MigrationRunController).
|
| Controller actions only, never closures: `route:cache` (the update finalizer runs it) cannot cache a closure.
*/

Route::prefix('migrate/run')->name('migrate.run.')->middleware('throttle:240,1')->group(function (): void {
    Route::get('{id}', [MigrationRunController::class, 'page'])->where('id', '[a-z0-9]{12,40}')->name('page');
    Route::get('{id}/status', [MigrationRunController::class, 'status'])->where('id', '[a-z0-9]{12,40}')->name('status');
    Route::post('{id}/advance', [MigrationRunController::class, 'advance'])->where('id', '[a-z0-9]{12,40}')->name('advance');
});
