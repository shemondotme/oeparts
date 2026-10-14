<?php

namespace App\Services\Backup;

use App\Models\BackupRun;
use App\Models\CleanupRun;
use App\Services\Backup\Exceptions\BackupLockException;
use App\Services\Migration\Export\FreezeState;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportStateStore;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * BackupLock (Module 14/21, Chunk 2.1) — the single mutual-exclusion lock shared
 * by the Backup Engine and the Update Engine. Only one backup OR update may run
 * at a time on an instance (they touch the same files/DB), so both coordinate
 * through one lock file at config('updates.state_path')/lock.
 *
 * Pure filesystem, no queue/Redis dependency (shared-hosting-safe).
 * Acquisition is atomic via O_EXCL (`fopen(..., 'x')`): the create fails if the
 * file already exists, so two concurrent requests can never both win.
 */
class BackupLock
{
    public function path(): string
    {
        $dir = (string) config('updates.state_path', storage_path('app/updates'));

        return rtrim($dir, '/\\').DIRECTORY_SEPARATOR.'lock';
    }

    /**
     * Acquire the lock for $owner (e.g. "backup:12" or "update:1.1.0").
     *
     * @throws BackupLockException if already held
     */
    public function acquire(string $owner): void
    {
        $path = $this->path();
        $dir = dirname($path);

        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $handle = @fopen($path, 'x'); // O_EXCL — fails if the lock already exists

        if ($handle === false) {
            $held = $this->owner();

            throw new BackupLockException(
                'A backup or update is already in progress'
                .($held['owner'] ?? null ? ' (held by '.$held['owner'].')' : '').'.'
            );
        }

        fwrite($handle, (string) json_encode([
            'owner' => $owner,
            'acquired_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT));
        fclose($handle);
    }

    public function isLocked(): bool
    {
        clearstatcache(true, $this->path()); // another process takes and releases this lock; a long-lived worker must see the file as it is now

        return is_file($this->path());
    }

    /** @return array{owner?:string,acquired_at?:string} */
    public function owner(): array
    {
        if (! is_file($this->path())) {
            return [];
        }

        $data = json_decode((string) @file_get_contents($this->path()), true);

        return is_array($data) ? $data : [];
    }

    public function release(): void
    {
        if (is_file($this->path())) {
            @unlink($this->path());
        }
    }

    /**
     * Release the lock only if `$owner` is still the one holding it. A run that lost its lock (a janitor
     * reclaimed it and a backup took it) must not delete the new holder's file when it fails or finishes.
     *
     * @return bool true when the lock was ours and is now released
     */
    public function releaseIfOwnedBy(string $owner): bool
    {
        if (($this->owner()['owner'] ?? null) !== $owner) {
            return false;
        }

        $this->release();

        return true;
    }

    /**
     * True when the lock is held by a Cleanup Engine run that is still making progress (its row's heartbeat is
     * fresh). The janitors must not release the lock out from under a cleanup that merely takes longer than the
     * stale threshold; a cleanup that stopped moving IS reclaimable, like any other abandoned holder.
     */
    public function heldByLiveCleanup(int $freshSeconds): bool
    {
        $owner = (string) ($this->owner()['owner'] ?? '');

        if (! str_starts_with($owner, 'cleanup:')) {
            return false;
        }

        return CleanupRun::query()
            ->whereKey((int) substr($owner, strlen('cleanup:')))
            ->active()
            ->where('updated_at', '>=', now()->subSeconds($freshSeconds))
            ->exists();
    }

    /**
     * Is the current holder still working, by the rule of ITS kind of owner? ONE entry point for every janitor
     * and watchdog, so they cannot disagree. A cleanup is live while its row keeps moving; an export is live by
     * its own horizon (or while its freeze lasts); an import by its heartbeat. An owner of any other kind
     * (a backup, an update, a restore, an unknown prefix) is never "live" here, so the old rule applies to it:
     * the lock is reclaimable once it is older than the stale threshold and no backup row is still moving.
     */
    public function heldByLiveOwner(int $freshSeconds): bool
    {
        $owner = (string) ($this->owner()['owner'] ?? '');

        return match (true) {
            str_starts_with($owner, 'cleanup:') => $this->heldByLiveCleanup($freshSeconds),
            str_starts_with($owner, 'export:') => $this->heldByLiveExport(),
            str_starts_with($owner, 'import:') => $this->heldByLiveImport(),
            default => false,
        };
    }

    /**
     * An export holds the lock for hours on a big site, far longer than the generic stale thresholds, so it
     * is judged by its own horizon (migration.export.heartbeat_stale_seconds) and ignores the caller's. It is
     * live when its run is pending or running and was advanced within that horizon, OR when the freeze it
     * started is still within its deadline: a frozen shop whose driver and watchdog both died keeps the lock
     * until the freeze itself ends, and the release path then fails the run.
     */
    public function heldByLiveExport(): bool
    {
        $owner = (string) ($this->owner()['owner'] ?? '');

        if (! str_starts_with($owner, 'export:')) {
            return false;
        }

        $runId = (int) substr($owner, strlen('export:'));
        if ($runId <= 0) {
            return false;
        }

        $horizon = max(1, (int) config('migration.export.heartbeat_stale_seconds', 1800));

        $moving = BackupRun::query()
            ->exports()
            ->whereKey($runId)
            ->whereIn('status', [BackupRun::STATUS_PENDING, BackupRun::STATUS_RUNNING])
            ->where('updated_at', '>=', now()->subSeconds($horizon))
            ->exists();

        if ($moving) {
            return true;
        }

        $freeze = FreezeState::read();

        return $freeze !== null && $freeze->belongsToRun($runId) && $freeze->isLive();
    }

    /**
     * An import holds the lock while its marker is present and its state file keeps moving. "Moving" is the run's
     * heartbeat within migration.import.heartbeat_stale_seconds, or an announced long statement (one ALTER that
     * takes minutes writes `long_running_until` before it starts) that has not yet run out of time. Without the
     * marker the import is not holding the site (a rolled back or finished run), so the lock is reclaimable.
     */
    public function heldByLiveImport(): bool
    {
        $owner = (string) ($this->owner()['owner'] ?? '');

        if (! str_starts_with($owner, 'import:')) {
            return false;
        }

        $runId = substr($owner, strlen('import:'));
        $marker = new ImportMarker;

        if (! $marker->isActive() || ! ImportStateStore::validId($runId)) {
            return false;
        }

        try {
            $state = (new ImportStateStore)->find($runId);
        } catch (\RuntimeException) {
            return false; // an unreadable state file cannot vouch for the run
        }

        return $state !== null && $state->isAlive(max(1, (int) config('migration.import.heartbeat_stale_seconds', 1800)));
    }

    /**
     * An owner that wants to carry on working asks whether the lock is still its own. Still ours: carry on.
     * Free (a janitor legitimately released it while the run sat idle): take it again, atomically, and carry
     * on. Held by anyone else: it is lost; the caller must stop and write nothing more.
     */
    public function ensureHeld(string $owner): LockCustody
    {
        $current = $this->owner()['owner'] ?? null;

        if ($current === $owner) {
            return LockCustody::Held;
        }

        if ($current === null && ! $this->isLocked()) {
            try {
                $this->acquire($owner);

                return LockCustody::Reacquired;
            } catch (BackupLockException) {
                return LockCustody::Lost; // another process took it between the check and the create
            }
        }

        return LockCustody::Lost;
    }

    /** True when the lock is older than $maxAgeSeconds (a crashed, abandoned run). */
    public function isStale(int $maxAgeSeconds): bool
    {
        $owner = $this->owner();

        if (empty($owner['acquired_at'])) {
            // Locked but unreadable/undated — treat any such file as stale.
            return $this->isLocked();
        }

        try {
            return Carbon::parse($owner['acquired_at'])->addSeconds($maxAgeSeconds)->isPast();
        } catch (\Throwable $e) {
            Log::channel(config('updates.log_channel', 'stack'))
                ->warning('Backup lock timestamp unparseable: '.$e->getMessage());

            return true;
        }
    }
}
