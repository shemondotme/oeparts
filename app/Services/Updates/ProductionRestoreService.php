<?php

namespace App\Services\Updates;

use App\Jobs\NotifyAdminsOfBackupFailure;
use App\Jobs\NotifyAdminsOfUpdateResult;
use App\Models\BackupRun;
use App\Models\UpdateHistory;
use App\Services\Backup\BackupLock;
use App\Services\Backup\BackupManager;
use App\Services\Backup\RestoreManager;
use App\Services\Backup\RestoreOptions;
use App\Services\SettingsService;
use App\Services\Updates\Exceptions\UpdateException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ProductionRestoreService — self-service, admin-initiated "roll production
 * all the way back to an older backup" (files AND database), distinct from
 * BackupDashboard's existing files-only restore (which only extracts to a
 * side directory, never touches the live install) and from
 * UpdateApplier::rollback() (which only reverses the SAME update attempt,
 * automatically, not an intentional downgrade days later).
 *
 * Reuses the same primitives UpdateApplier already trusts for exactly this
 * kind of destructive operation: BackupLock (mutual exclusion with any
 * backup/update), maintenance mode, RestoreManager (DB + file reassembly),
 * UpdateSwapper (atomic directory-rename swap — the restored files land in
 * a side directory via RestoreManager, then THIS swaps them into the live
 * root the same way a normal update's extracted release is swapped in),
 * and RecoveryWindowFlag (so a restore gone wrong is exactly as recoverable
 * as a failed update). A safety backup of the CURRENT state is taken before
 * touching anything, so a restore-of-a-restore is itself reversible.
 *
 * vendor/ is excluded from most backups (config('backup.files.include_vendor')),
 * so UpdateSwapper::swap() — which only swaps core_paths entries that exist
 * in the staging dir — will silently skip vendor/ if it's absent from this
 * backup; composer.lock IS restored, so the admin may need to run
 * `composer install` afterward. This is surfaced in the confirmation modal
 * (BackupDashboard::restoreFullAction()), not hidden here.
 */
class ProductionRestoreService
{
    public function restore(BackupRun $run, ?int $initiatedBy = null): UpdateHistory
    {
        if (! $run->isRestorable()) {
            throw new UpdateException('This backup is not restorable (not successful, or pruned).');
        }

        // isRestorable() alone doesn't guarantee the backup actually HAS both
        // database and file parts — only a PROFILE_FULL run does. Restoring
        // a files_only backup here would silently skip the database entirely
        // (RestoreManager::restoreDatabase() treats "no DB parts" as a mere
        // warning, not an error, and this method only ever checked
        // $report->errors) while still reporting a plain SUCCESS — an admin
        // doing an emergency full restore would believe the database was
        // rolled back too when it never was. A database_only backup fails
        // more loudly (UpdateSwapper::swap() throws on the missing staging
        // dir, triggering the automatic rollback) but still wastes a real
        // destructive DB write-then-rollback cycle for an action that can
        // never succeed. Failing fast here, before maintenance mode or the
        // safety backup, closes both.
        if ($run->profile !== BackupRun::PROFILE_FULL) {
            throw new UpdateException('Only a full (files + database) backup can be restored into production.');
        }

        app(BackupLock::class)->acquire('restore:'.$run->getKey());

        $history = null;

        try {
            $this->enterMaintenance();

            $history = UpdateHistory::create([
                'from_version' => app(UpdateChecker::class)->currentVersion(),
                'to_version' => 'restore-'.$run->getKey(),
                'type' => UpdateHistory::TYPE_RESTORE,
                'status' => UpdateHistory::STATUS_RESTORING,
                'step' => 'restore',
                'initiated_by' => $initiatedBy,
                'restore_of_backup_run_id' => $run->getKey(),
                'started_at' => now(),
                'meta' => ['restore_of_backup_run_id' => $run->getKey()],
            ]);

            app(RecoveryWindowFlag::class)->arm([
                'history_id' => $history->getKey(),
                'from_version' => $history->from_version,
                'to_version' => $history->to_version,
                'deployment_type' => 'restore',
            ]);

            Log::channel(config('updates.log_channel', 'stack'))->notice('restore.start', [
                'history' => $history->getKey(), 'backup_run' => $run->getKey(), 'admin' => $initiatedBy,
            ]);
        } catch (\Throwable $e) {
            // Same stuck-lock-avoidance shape as UpdateApplier::start()'s
            // catch block — a cleanup failure here must never mask $e nor
            // skip releasing the lock.
            try {
                $this->exitMaintenance();
            } catch (\Throwable $cleanupError) {
                Log::channel(config('updates.log_channel', 'stack'))
                    ->error('Failed to exit maintenance mode while cleaning up a failed restore start: '.$cleanupError->getMessage());
            } finally {
                app(BackupLock::class)->release();
            }

            throw $e;
        }

        // Safety backup of the CURRENT (pre-restore) state — if everything
        // below goes wrong, this is what rollback() restores from, so the
        // restore-of-a-restore is itself reversible.
        $safetyRun = null;
        // True only once RestoreManager::restore() is about to run — its DB
        // restore isn't transactional (same reasoning as UpdateApplier's
        // needsRollback() gate), so a failure BEFORE this point (e.g. the
        // safety backup itself failing) never touched anything and must be
        // a plain FAILED, not a ROLLED_BACK that implies something was
        // reversed.
        $destructivePhaseStarted = false;

        try {
            $safetyRun = app(BackupManager::class)->start(
                BackupRun::PROFILE_UPDATE_SAFETY, BackupRun::TRIGGER_PRE_UPDATE, [], acquireLock: false
            );
            $safetyRun = app(BackupManager::class)->run($safetyRun);

            if ($safetyRun->status !== BackupRun::STATUS_SUCCESS) {
                dispatch(new NotifyAdminsOfBackupFailure(
                    $safetyRun->profile, (string) $safetyRun->error, (int) $safetyRun->getKey(), optional($safetyRun->finished_at)->toDateTimeString()
                ));

                throw new UpdateException('Pre-restore safety backup failed: '.$safetyRun->error);
            }

            $history->putMeta('safety_backup_run_id', $safetyRun->getKey());
            $history->save();

            $destructivePhaseStarted = true;

            $stagingDir = storage_path('app/restore/run-'.$run->getKey());
            $report = app(RestoreManager::class)->restore($run, new RestoreOptions(
                database: true, files: true, targetRoot: $stagingDir,
            ));

            if ($report->errors !== []) {
                throw new UpdateException('Restore reassembly failed: '.implode('; ', $report->errors));
            }

            // Files restored into the side directory above still need to be
            // swapped into the live root — restoreFiles() alone never does
            // that (it's shared with the deliberately-non-destructive
            // files-only restore action).
            app(UpdateSwapper::class)->swap($stagingDir, $history->to_version);

            return $this->complete($history);
        } catch (\Throwable $e) {
            return $this->fail($history, $e->getMessage(), $destructivePhaseStarted, $safetyRun);
        }
    }

    private function complete(UpdateHistory $history): UpdateHistory
    {
        $history->status = UpdateHistory::STATUS_SUCCESS;
        $history->finished_at = now();
        $history->save();

        try {
            $this->exitMaintenance();
            app(RecoveryWindowFlag::class)->disarm();
        } finally {
            app(BackupLock::class)->release();
        }

        Log::channel(config('updates.log_channel', 'stack'))->notice('restore.success', ['history' => $history->getKey()]);

        $this->notifyResult($history, success: true, rolledBack: false);

        return $history;
    }

    private function fail(UpdateHistory $history, string $error, bool $destructivePhaseStarted, ?BackupRun $safetyRun): UpdateHistory
    {
        Log::channel(config('updates.log_channel', 'stack'))->error('restore.failed: '.$error, ['history' => $history->getKey()]);

        // Nothing destructive was attempted yet (e.g. the safety backup
        // itself failed) — there is nothing to reverse, so this is a plain
        // failure, not a rollback. Calling rollback() anyway would report
        // "true" (both its no-op checks vacuously succeed) and misleadingly
        // mark the row ROLLED_BACK.
        $rolledBack = $destructivePhaseStarted && $this->rollback($safetyRun);

        $history->status = $rolledBack ? UpdateHistory::STATUS_ROLLED_BACK : UpdateHistory::STATUS_FAILED;
        $history->error = Str::limit($error, 2000, '');
        $history->finished_at = now();
        $history->save();

        try {
            $this->exitMaintenance();
            if ($rolledBack) {
                app(RecoveryWindowFlag::class)->disarm();
            }
        } finally {
            app(BackupLock::class)->release();
        }

        $this->notifyResult($history, success: false, rolledBack: $rolledBack);

        return $history;
    }

    /**
     * Reverse whatever the failed attempt above got through: undo the file
     * swap (if it happened) and restore the DB from the pre-restore safety
     * backup. Same two-part, independently-tracked shape as
     * UpdateApplier::rollback() — true only if BOTH succeed.
     */
    private function rollback(?BackupRun $safetyRun): bool
    {
        $filesOk = true;

        try {
            app(UpdateSwapper::class)->rollback();
        } catch (\Throwable $e) {
            Log::channel(config('updates.log_channel', 'stack'))->error('Restore file rollback failed: '.$e->getMessage());
            $filesOk = false;
        }

        $dbOk = true;

        if ($safetyRun && $safetyRun->status === BackupRun::STATUS_SUCCESS) {
            try {
                app(RestoreManager::class)->restore($safetyRun, RestoreOptions::databaseOnly());
            } catch (\Throwable $e) {
                Log::channel(config('updates.log_channel', 'stack'))->error('Restore DB rollback failed: '.$e->getMessage());
                $dbOk = false;
            }
        }

        return $filesOk && $dbOk;
    }

    private function notifyResult(UpdateHistory $history, bool $success, bool $rolledBack): void
    {
        dispatch(new NotifyAdminsOfUpdateResult([
            'from_version' => $history->from_version,
            'to_version' => $history->to_version,
            'success' => $success,
            'rolled_back' => $rolledBack,
            'error' => $history->error,
            'started_at' => optional($history->started_at)->toIso8601String(),
            'trigger' => 'restore',
        ]));
    }

    private function enterMaintenance(): void
    {
        app(SettingsService::class)->set('maintenance.enabled', true);
    }

    private function exitMaintenance(): void
    {
        app(SettingsService::class)->set('maintenance.enabled', false);
    }
}
