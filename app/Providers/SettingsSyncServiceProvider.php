<?php

namespace App\Providers;

use App\Services\RuntimeSettingsSyncService;
use App\Services\SettingsService;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class SettingsSyncServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    // The actual sync logic lives in RuntimeSettingsSyncService — boot()
    // only runs once per process, which is correct under classic PHP-FPM
    // (fresh process + fresh boot() per request) but stale for the rest
    // of any long-running process's lifetime otherwise. Also wired to run
    // before every queued job (the existing supervisor-managed
    // queue:work worker already lives long enough for this to matter
    // today, independent of any Octane adoption) and, via
    // SyncRuntimeSettingsIntoConfig, before every HTTP request. boot()
    // itself still runs it once too, covering one-off artisan commands
    // that are neither.
    public function boot(): void
    {
        app(RuntimeSettingsSyncService::class)->sync(app(SettingsService::class));

        Queue::before(function (JobProcessing $event) {
            app(RuntimeSettingsSyncService::class)->sync(app(SettingsService::class));
        });
    }
}
