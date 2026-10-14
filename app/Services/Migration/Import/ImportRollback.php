<?php

namespace App\Services\Migration\Import;

use App\Security\PackageAudit;
use App\Services\Backup\BackupLock;
use App\Services\Instance\InstanceCacheReset;
use App\Services\Instance\InstanceEnv;
use App\Services\Migration\Import\Contracts\QueueGuard;
use App\Services\Migration\Import\Contracts\RollbackHook;
use App\Services\Migration\Import\Recovery\RecoveryPoint;
use App\Services\Migration\KeyEscrow;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\SettingsService;
use App\Support\AtomicJsonFile;
use App\Support\LocaleRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Puts the site back exactly as the recovery point says it was. Resumable at every step: a kill in the middle of any
 * part simply does that part again, and each finished part is a flag in the run's state that is never repeated.
 *
 *   1. the hooks of later parts of the engine (the files journal);
 *   2. every table of the site: emptied, then reloaded from the recovery point, table by table and segment by segment,
 *      with a row-count check before each segment that notices a half-finished load and starts that table again;
 *      then the hooks that need the final rows (the search index that was dropped for speed is built again);
 *   3. the .env keys the import changed, back to the image taken before (a key that was not in the file is removed);
 *   4. the caches that held anything of the import;
 *   5. the site is opened: the queue guard lets go, the marker is removed, the lock released.
 *
 * It ends `rolled_back` when all of it worked. If any part fails, it ends `rollback_failed` and does NOT open the site:
 * the marker and the lock stay, so nobody and nothing writes to a half-restored database while a person decides what to do.
 */
class ImportRollback
{
    /**
     * @param  iterable<int, RollbackHook>  $hooks  run before the tables are reloaded
     * @param  iterable<int, RollbackHook>  $lateHooks  run once the tables are back (the search index is built over the final rows)
     */
    public function __construct(
        private readonly ImportStateStore $store,
        private readonly BackupLock $lock,
        private readonly ImportMarker $marker,
        private readonly QueueGuard $guard,
        private readonly RowLoader $loader,
        private readonly InstanceEnv $env,
        private readonly InstanceCacheReset $caches,
        private readonly PackageAudit $audit,
        private readonly KeyEscrow $escrow,
        private readonly iterable $hooks = [],
        private readonly iterable $lateHooks = [],
    ) {}

    /**
     * One budgeted step. True when the rollback has ended (the state then says how); false when there is more to do.
     */
    public function step(ImportState $state, ImportContext $context): bool
    {
        try {
            return $this->run($state, $context);
        } catch (\Throwable $e) {
            Log::channel('migration')->critical("Rollback of import {$state->id} failed in {$this->where($state)}: ".$e->getMessage());

            $state->status = ImportStatus::RollbackFailed;
            $state->error = ['code' => MigrationErrorCode::RollbackFailed->value, 'params' => ['step' => $this->where($state)], 'stage' => $state->stage];

            return true;
        }
    }

    private function run(ImportState $state, ImportContext $context): bool
    {
        $this->loader->prepareSession();

        foreach ($this->hooks as $hook) {
            $flag = 'rollback.'.$hook->name();

            if (! $state->flag($flag)) {
                if (! $hook->run($state, $context)) {
                    return false;
                }

                $state->setFlag($flag);
            }
        }

        if (! $state->flag('rollback.db')) {
            if (! $this->reloadTables($state, $context)) {
                return false;
            }

            $state->setFlag('rollback.db');
        }

        foreach ($this->lateHooks as $hook) {
            $flag = 'rollback.'.$hook->name();

            if (! $state->flag($flag)) {
                if (! $hook->run($state, $context)) {
                    return false;
                }

                $state->setFlag($flag);
            }
        }

        if (! $state->flag('rollback.env')) {
            $this->restoreEnv($state);
            $state->setFlag('rollback.env');
        }

        if (! $state->flag('rollback.caches')) {
            $this->clearCaches();
            $state->setFlag('rollback.caches');
        }

        if (! $state->flag('rollback.release')) {
            $this->guard->resume();
            $this->marker->remove();
            RecoveryNote::remove();
            $this->lock->releaseIfOwnedBy('import:'.$state->id);
            $state->setFlag('rollback.release');
        }

        $state->status = ImportStatus::RolledBack;
        $state->finishedAt ??= now()->toIso8601String();
        $this->escrow->delete(ImportContext::escrowId($state->id));
        $this->audit->record('import.rolled_back', $state->id, ['stage' => $state->stage, 'error' => $state->error['code'] ?? null]);

        return true;
    }

    // ---- the database ------------------------------------------------------------------------------------------

    /** @return bool true when every table is back */
    private function reloadTables(ImportState $state, ImportContext $context): bool
    {
        $point = RecoveryPoint::of($state->id, $this->store);

        if (! $point->isComplete()) {
            throw new MigrationException(MigrationErrorCode::RollbackFailed, ['why' => 'there is no complete recovery point']);
        }

        $manifest = (array) $point->read();
        $tables = array_keys((array) $manifest['tables']);
        $cursor = (array) ($state->stageState['rb'] ?? ['ti' => 0, 'pi' => 0, 'expected' => 0]);

        while ($cursor['ti'] < count($tables)) {
            $table = $tables[$cursor['ti']];
            $parts = $point->partsOf($table);

            // Every segment starts from a known state: the table is empty (first segment) or holds exactly the rows loaded so far.
            // Anything else is a load that was cut off in the middle: that table starts again.
            if ($cursor['pi'] === 0 || (int) DB::table($table)->count() !== (int) $cursor['expected']) {
                $this->loader->truncate($table);
                $cursor['pi'] = 0;
                $cursor['expected'] = 0;
            }

            while ($cursor['pi'] < count($parts)) {
                $part = $parts[$cursor['pi']];
                $decoder = $point->decoderOf($part);
                $this->loader->assertColumns($decoder);
                $this->loader->load($decoder, $point->lines($part));

                $cursor['pi']++;
                $cursor['expected'] += (int) $part['rows'];
                $state->stageState['rb'] = $cursor;

                if ($cursor['pi'] < count($parts) && $context->expired()) {
                    return false;
                }
            }

            $expected = (int) $manifest['tables'][$table];

            if ((int) DB::table($table)->count() !== $expected) {
                throw new MigrationException(MigrationErrorCode::RollbackFailed, ['why' => "{$table} did not come back with {$expected} rows"]);
            }

            $cursor = ['ti' => $cursor['ti'] + 1, 'pi' => 0, 'expected' => 0];
            $state->stageState['rb'] = $cursor;

            if ($cursor['ti'] < count($tables) && $context->expired()) {
                return false;
            }
        }

        return true;
    }

    // ---- the environment ----------------------------------------------------------------------------------------

    private function restoreEnv(ImportState $state): void
    {
        $file = new AtomicJsonFile($this->store->directory($state->id).DIRECTORY_SEPARATOR.'journal'.DIRECTORY_SEPARATOR.'env-before.json');
        $image = $file->read();

        if ($image === null) {
            return; // the run never got as far as taking the image, so it never changed .env
        }

        $this->env->restore($image, 'import-rollback:'.$state->id);
    }

    /** Targeted: forget what the import put in the caches. NEVER a flush of a shared store. */
    private function clearCaches(): void
    {
        $settings = app(SettingsService::class);

        foreach (DB::table('settings')->distinct()->pluck('group') as $group) {
            $settings->forget((string) $group);
        }

        LocaleRegistry::forget();
        $this->caches->reset();
    }

    private function where(ImportState $state): string
    {
        foreach (['rollback.release', 'rollback.caches', 'rollback.env', 'rollback.db'] as $flag) {
            if ($state->flag($flag)) {
                return 'after '.$flag;
            }
        }

        return 'before the database';
    }
}
