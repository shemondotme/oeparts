<?php

namespace App\Providers;

use App\Services\Instance\SystemGate;
use App\Services\Migration\Export\ExportDisks;
use App\Services\Migration\Export\ExportFreeze;
use App\Services\Migration\Export\FreezeBlockSource;
use App\Services\Migration\Export\PackageOutbox;
use App\Services\Migration\Export\Stages\ExportFilesStage;
use App\Services\Migration\Import\Contracts\RunDriver;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\StatusOnlyRunDriver;
use App\Services\Migration\KeyEscrow;
use Illuminate\Support\ServiceProvider;

/**
 * Wiring for the site-migration feature. Nothing here runs work: it tells the container how to build the
 * few classes that need more than other classes, and registers the export freeze with the SystemGate (as a
 * file check, so a site that never exports pays nothing for it).
 */
class MigrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(KeyEscrow::class, static fn (): KeyEscrow => KeyEscrow::forApplication());
        $this->app->singleton(PackageOutbox::class);
        $this->app->singleton(ExportFreeze::class);
        $this->app->singleton(ImportStateStore::class);
        $this->app->singleton(ImportMarker::class);

        // The runner routes ask this; the import manager replaces the status-only driver when it exists.
        $this->app->bind(RunDriver::class, StatusOnlyRunDriver::class);

        $this->app->when(ExportFilesStage::class)->needs('$disks')->give(static fn (): array => ExportDisks::application());
    }

    public function boot(): void
    {
        $this->app->make(SystemGate::class)->register(new FreezeBlockSource);
        $this->app->make(SystemGate::class)->register($this->app->make(ImportMarker::class));
    }
}
