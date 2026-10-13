<?php

namespace App\Services\Migration\Export;

use App\Models\BackupRun;
use App\Services\Backup\BackupLock;
use App\Services\Migration\KeyEscrow;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\Package\WriterLedger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Hygiene for exports, which the backup janitor must not touch (an export is not an abandoned backup).
 *
 *  - a run that nobody has advanced for the one horizon (30 minutes: not by a poll, the command line, a
 *    queue job or a watchdog nudge) is dead: it is failed, and its staging, its package under construction,
 *    its keys and, if it still owns it, the shared lock are removed in one pass;
 *  - a run that was started but whose driver never made its first step within `worker_pickup_seconds` never
 *    froze anything (a freeze engages in the first step), and is failed as `no_worker`;
 *  - a package ledger whose package-in-progress is gone is removed;
 *  - finished packages older than the retention are deleted: a package is a full copy of customer data.
 *
 * A run whose freeze is still live is never touched: the freeze's own deadline logic owns it.
 */
final class ExportJanitor
{
    public function __construct(
        private readonly BackupLock $lock,
        private readonly PackageOutbox $outbox,
        private readonly KeyEscrow $escrow,
    ) {}

    /**
     * @return array{dead: int, unpicked: int, orphans: int, expired: int, escrow: int}
     */
    public function run(): array
    {
        return [
            'dead' => $this->failDeadRuns(),
            'unpicked' => $this->failUnpickedRuns(),
            'orphans' => $this->removeOrphans(),
            'expired' => $this->pruneExpired(),
            'escrow' => $this->escrow->prune(),
        ];
    }

    /** Runs silent for longer than the horizon. */
    public function failDeadRuns(): int
    {
        $horizon = max(1, (int) config('migration.export.heartbeat_stale_seconds', 1800));
        $count = 0;

        $runs = BackupRun::query()->exports()
            ->whereIn('status', [BackupRun::STATUS_PENDING, BackupRun::STATUS_RUNNING])
            ->where('updated_at', '<', now()->subSeconds($horizon))
            ->get();

        foreach ($runs as $run) {
            if ($this->hasLiveFreeze($run)) {
                continue;
            }

            $this->end($run, 'run_stale', 'The export stopped moving for more than '.intdiv($horizon, 60).' minutes and was closed.');
            $count++;
        }

        return $count;
    }

    /** Started, but the driver never took its first step: nothing was frozen, nothing was copied. */
    public function failUnpickedRuns(): int
    {
        $wait = max(1, (int) config('migration.export.worker_pickup_seconds', 120));
        $count = 0;

        $runs = BackupRun::query()->exports()
            ->where('status', BackupRun::STATUS_PENDING)
            ->where('updated_at', '<', now()->subSeconds($wait))
            ->get();

        foreach ($runs as $run) {
            $checkpoint = $run->checkpoint();

            if ($checkpoint['stage_index'] !== 0 || $checkpoint['stage_state'] !== [] || $this->hasLiveFreeze($run)) {
                continue;
            }

            $this->end($run, MigrationErrorCode::NoWorker->value, 'No queue worker picked this export up within '.$wait.' seconds, so it never started.');
            $count++;
        }

        return $count;
    }

    /** A `.part.ledger` whose `.part` is gone belongs to nothing. */
    public function removeOrphans(): int
    {
        $removed = 0;

        foreach (glob($this->outbox->directory().DIRECTORY_SEPARATOR.'*.part.ledger') ?: [] as $ledgerDir) {
            if (! is_dir($ledgerDir) || is_file(substr($ledgerDir, 0, -strlen('.ledger')))) {
                continue;
            }

            $ledger = new WriterLedger($ledgerDir);
            $ledger->destroy();

            if (! $ledger->exists()) {
                $removed++;
            }
        }

        return $removed;
    }

    public function pruneExpired(): int
    {
        return $this->outbox->pruneOlderThan((int) config('migration.retention.export_days', 7));
    }

    // ------------------------------------------------------------------------------------------------

    private function hasLiveFreeze(BackupRun $run): bool
    {
        $freeze = FreezeState::read();

        return $freeze !== null && $freeze->belongsToRun((int) $run->getKey()) && $freeze->isLive();
    }

    /** Fail the run and clear away everything it left. */
    private function end(BackupRun $run, string $code, string $why): void
    {
        $meta = (array) $run->meta;
        $meta['error_code'] = $code;
        $run->meta = $meta;

        // fail() lifts a freeze that belongs to the run, deletes its keys, writes the audit line and releases the lock if it is still ours.
        app(ExportManager::class)->fail($run, $why);

        $this->purge($run);

        // A lock still named after this dead run is released even if fail() could not (it was not "owned" by the run's own flag).
        $this->lock->releaseIfOwnedBy($run->lockOwner());

        Log::channel('migration')->warning("Export {$run->getKey()} was closed by the janitor: {$why}");
    }

    private function purge(BackupRun $run): void
    {
        $directory = 'backups/'.$run->getKey();

        foreach (array_unique([(string) $run->disk, (string) config('backup.staging_disk', 'local')]) as $disk) {
            if ($disk !== '' && Storage::disk($disk)->exists($directory)) {
                Storage::disk($disk)->deleteDirectory($directory);
            }
        }

        $final = (string) ($run->meta['final_path'] ?? '');

        if ($final !== '') {
            @unlink($final.'.part');
            (new WriterLedger($final.'.part.ledger'))->destroy();
        }
    }
}
