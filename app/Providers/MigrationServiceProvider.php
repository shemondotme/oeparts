<?php

namespace App\Providers;

use App\Services\Instance\Contracts\InstanceBegin;
use App\Services\Instance\NullInstanceBegin;
use App\Services\Instance\SystemGate;
use App\Services\Migration\Export\ExportDisks;
use App\Services\Migration\Export\ExportFreeze;
use App\Services\Migration\Export\FreezeBlockSource;
use App\Services\Migration\Export\PackageOutbox;
use App\Services\Migration\Export\Stages\ExportFilesStage;
use App\Services\Migration\Import\Contracts\QueueGuard;
use App\Services\Migration\Import\Contracts\RunDriver;
use App\Services\Migration\Import\ImportInbox;
use App\Services\Migration\Import\ImportManager;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportRollback;
use App\Services\Migration\Import\ImportStageRegistry;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\NullQueueGuard;
use App\Services\Migration\Import\RowLoader;
use App\Services\Migration\KeyEscrow;
use App\Services\Migration\Ux\PackageUnlocker;
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

        $this->app->singleton(ImportInbox::class);
        $this->app->singleton(PackageUnlocker::class, static fn (): PackageUnlocker => PackageUnlocker::forApplication());
        $this->app->singleton(ImportStageRegistry::class, static fn (): ImportStageRegistry => ImportStageRegistry::forApplication());
        $this->app->singleton(ImportManager::class);
        $this->app->singleton(RowLoader::class);
        $this->app->singleton(ImportRollback::class);

        // The real queue guard replaces this when it lands; until then no worker is held.
        $this->app->bind(QueueGuard::class, NullQueueGuard::class);

        // Puts the installation in the import's mode while it runs. The real one lands with the import's finalisation.
        $this->app->bind(InstanceBegin::class, NullInstanceBegin::class);

        // The runner routes, the command line and the watchdog all drive a run through this.
        $this->app->bind(RunDriver::class, ImportManager::class);

        $this->app->when(ExportFilesStage::class)->needs('$disks')->give(static fn (): array => ExportDisks::application());
    }

    public function boot(): void
    {
        $this->app->make(SystemGate::class)->register(new FreezeBlockSource);
        $this->app->make(SystemGate::class)->register($this->app->make(ImportMarker::class));
    }
}
