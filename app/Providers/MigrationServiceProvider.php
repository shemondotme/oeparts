<?php

namespace App\Providers;

use App\Services\Instance\Contracts\InstanceBegin;
use App\Services\Instance\InstanceBeginner;
use App\Services\Instance\Readiness\ReadinessRegistry;
use App\Services\Instance\Readiness\ReadinessRunner;
use App\Services\Instance\SystemGate;
use App\Services\Migration\Export\ExportDisks;
use App\Services\Migration\Export\ExportFreeze;
use App\Services\Migration\Export\FreezeBlockSource;
use App\Services\Migration\Export\PackageOutbox;
use App\Services\Migration\Export\Stages\ExportFilesStage;
use App\Services\Migration\Import\Contracts\QueueGuard;
use App\Services\Migration\Import\Contracts\RunDriver;
use App\Services\Migration\Import\Files\FilesRollbackHook;
use App\Services\Migration\Import\FulltextIndex;
use App\Services\Migration\Import\FulltextRollbackHook;
use App\Services\Migration\Import\ImportInbox;
use App\Services\Migration\Import\ImportManager;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportQueueGuard;
use App\Services\Migration\Import\ImportRollback;
use App\Services\Migration\Import\ImportStageRegistry;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\RowLoader;
use App\Services\Migration\Import\Stages\FilesStage;
use App\Services\Migration\KeyEscrow;
use App\Services\Migration\Ux\PackageUnlocker;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Event;
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
        $this->app->singleton(ReadinessRegistry::class);
        $this->app->singleton(ReadinessRunner::class);
        $this->app->singleton(FulltextIndex::class);
        $this->app->singleton(ImportRollback::class);
        $this->app->when(ImportRollback::class)->needs('$hooks')->give(fn (): array => [$this->app->make(FilesRollbackHook::class)]);
        $this->app->when(ImportRollback::class)->needs('$lateHooks')->give(fn (): array => [$this->app->make(FulltextRollbackHook::class)]);

        // Holds the customer queue workers while an import replaces the data they write to.
        $this->app->singleton(ImportQueueGuard::class);
        $this->app->bind(QueueGuard::class, ImportQueueGuard::class);

        // Puts the installation in the import's mode while it runs: the new address, quarantine, an identity and cache prefixes of its own.
        $this->app->bind(InstanceBegin::class, InstanceBeginner::class);

        // The runner routes, the command line and the watchdog all drive a run through this.
        $this->app->bind(RunDriver::class, ImportManager::class);

        // The folders an import writes its files into (the stage, its rollback and the readiness check that looks for them all agree).
        $this->app->bind('migration.import_disks', static fn (): array => ExportDisks::application());
        $this->app->when(FilesStage::class)->needs('$disks')->give(fn (): array => $this->app->make('migration.import_disks'));
        $this->app->when(FilesRollbackHook::class)->needs('$disks')->give(fn (): array => $this->app->make('migration.import_disks'));
        $this->app->when(ExportFilesStage::class)->needs('$disks')->give(static fn (): array => ExportDisks::application());
    }

    public function boot(): void
    {
        $this->app->make(SystemGate::class)->register(new FreezeBlockSource);
        $this->app->make(SystemGate::class)->register($this->app->make(ImportMarker::class));

        // Every worker asks before each loop whether it may take a job; while an import holds the site the answer is no.
        Event::listen(Looping::class, fn (Looping $event): ?bool => $this->app->make(ImportQueueGuard::class)->mayWork($event));
    }
}
