<?php

namespace App\Services\Cleanup;

use App\Models\BackupRun;
use App\Models\ProductImportRun;
use App\Models\UpdateHistory;
use App\Services\Backup\BackupLock;
use App\Services\Updates\RecoveryWindowFlag;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The single "is the system in the middle of something delicate?" predicate
 * every cleanup action checks before it deletes anything.
 *
 * A backup, an update or a production restore all read and swap the very
 * files and tables a cleanup would be deleting, so cleanup must never run
 * alongside one. The lock file is the first (cheapest) signal; the DB rows
 * are a second opinion for the cases where the lock file is gone but the work
 * isn't finished.
 *
 * Deliberately NOT based on the `maintenance.enabled` setting: its stored
 * format is inconsistent ('0'/'1' vs 'true'/'false') and an operator can turn
 * maintenance mode on by hand for reasons unrelated to a running job.
 *
 * Fails SAFE: if a DB check itself throws, the system is reported as busy
 * (and the failure logged) rather than assumed idle.
 */
class SystemBusyGuard
{
    public function __construct(
        private readonly BackupLock $lock,
        private readonly RecoveryWindowFlag $recovery,
    ) {}

    /**
     * @return string|null a human-readable reason, or null when the system is idle
     */
    public function reason(): ?string
    {
        if ($this->lock->isLocked()) {
            $owner = $this->lock->owner()['owner'] ?? 'unknown';

            return "A backup, update or restore is in progress ({$owner}).";
        }

        if ($this->recovery->isArmed()) {
            return 'An update/restore recovery window is open.';
        }

        if (is_file(storage_path('framework/down'))) {
            return 'The application is in framework maintenance mode.';
        }

        try {
            if (UpdateHistory::query()
                ->whereNotIn('status', [
                    UpdateHistory::STATUS_SUCCESS,
                    UpdateHistory::STATUS_FAILED,
                    UpdateHistory::STATUS_ROLLED_BACK,
                ])->exists()) {
                return 'An update or restore has not finished yet.';
            }

            if (BackupRun::query()
                ->whereIn('status', [BackupRun::STATUS_PENDING, BackupRun::STATUS_RUNNING])
                ->exists()) {
                return 'A backup is still running.';
            }

            if (ProductImportRun::query()->where('status', ProductImportRun::STATUS_RUNNING)->exists()) {
                return 'A product import is still running.';
            }
        } catch (Throwable $e) {
            Log::warning('SystemBusyGuard could not check the database; treating the system as busy.', [
                'error' => $e->getMessage(),
            ]);

            return 'The system state could not be checked.';
        }

        return null;
    }

    public function isBusy(): bool
    {
        return $this->reason() !== null;
    }
}
