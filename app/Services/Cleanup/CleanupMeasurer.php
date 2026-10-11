<?php

namespace App\Services\Cleanup;

use App\Models\CleanupDiskSample;
use App\Models\CleanupRun;
use App\Models\CleanupRunItem;
use App\Models\CleanupTargetStat;
use App\Services\Cleanup\Contracts\CleanupTarget;
use App\Services\Cleanup\Targets\DbPruneTarget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Fills the cached numbers the Cleanup page shows (row counts, sizes, oldest record, "eligible now") and
 * records disk samples for the growth trend.
 *
 * Measuring is the expensive part (counting 1M rows, walking a directory of millions of files), so it never
 * happens while the page renders: a daily command and a per-row "Refresh" fill `cleanup_target_stats`, and the
 * page only reads that table. Every figure is an ESTIMATE and the UI says so.
 *
 * "Eligible now" is computed by running the target's own PLAN phase as a throw-away dry run, so the number on the
 * page is, by construction, what a preview would report.
 */
final class CleanupMeasurer
{
    public function __construct(
        private readonly TargetRegistry $registry,
        private readonly DiskUsageService $disk,
        private readonly DirectorySizer $sizer,
    ) {}

    public function measureTarget(CleanupTarget $target, float $budgetSeconds = 2.0): CleanupTargetStat
    {
        $stat = ['rows_est' => null, 'bytes_est' => null, 'oldest_at' => null, 'eligible_rows' => null, 'eligible_bytes_est' => null];

        if ($target instanceof DbPruneTarget) {
            $info = $target->sizeInfo();
            $stat['rows_est'] = $info['rows'];
            $stat['bytes_est'] = $info['bytes'];
            $stat['oldest_at'] = $info['oldest'];
        }

        try {
            $cp = $target->begin($this->context($target));
            $ctx = $this->context($target);

            if ($target->plan($ctx, $cp, microtime(true) + max(0.2, $budgetSeconds))) {
                $stat['eligible_rows'] = (int) ($cp['targeted'] ?? 0);
                $stat['eligible_bytes_est'] = isset($cp['bytes_apparent']) ? (int) $cp['bytes_apparent'] : $this->estimateBytes($target, $stat);
            }
            // Not finished inside the budget: leave "eligible" unknown rather than show a misleading partial count.
        } catch (Throwable $e) {
            Log::channel('cleanup')->warning("Could not measure cleanup target {$target->key()}: ".$e->getMessage());
        }

        return CleanupTargetStat::updateOrCreate(['target' => $target->key()], $stat + ['measured_at' => now()]);
    }

    /**
     * @return int number of targets measured
     */
    public function measureAll(float $perTargetBudget = 5.0): int
    {
        $count = 0;

        foreach ($this->registry->all() as $target) {
            $this->measureTarget($target, $perTargetBudget);
            $count++;
        }

        return $count;
    }

    /** Record the current disk numbers plus a per-category breakdown (each walk bounded by $budgetPerCategory). */
    public function sampleDisk(float $budgetPerCategory = 5.0): CleanupDiskSample
    {
        $snapshot = $this->disk->snapshot();

        $breakdown = [];
        foreach ($this->categoryDirectories() as $label => $dir) {
            $measured = $this->sizer->size($dir, $budgetPerCategory);
            $breakdown[$label] = ['bytes' => $measured['bytes'], 'complete' => $measured['complete']];
        }

        $database = $this->databaseBytes();
        if ($database !== null) {
            $breakdown['database'] = ['bytes' => $database, 'complete' => true];
        }

        return CleanupDiskSample::create([
            'measured_at' => now(),
            'total_bytes' => (int) ($snapshot['total'] ?? 0),
            'free_bytes' => (int) ($snapshot['free'] ?? 0),
            'used_bytes' => (int) ($snapshot['used'] ?? 0),
            'breakdown' => $breakdown,
        ]);
    }

    /** @return array<string, string> label => absolute directory (existing local directories only) */
    private function categoryDirectories(): array
    {
        $dirs = [
            'logs' => (string) config('cleanup.log_dir', storage_path('logs')),
            'backups' => $this->diskPath((string) config('backup.staging_disk', 'local'), 'backups'),
            'updates' => rtrim((string) config('updates.state_path', storage_path('app/updates')), '/\\'),
            'uploads' => $this->diskPath('public', ''),
            'cache' => storage_path('framework/cache'),
        ];

        return array_filter($dirs, fn (?string $dir) => $dir !== null && $dir !== '' && is_dir($dir));
    }

    private function diskPath(string $disk, string $directory): ?string
    {
        if (config("filesystems.disks.{$disk}.driver") !== 'local') {
            return null;
        }

        return rtrim(Storage::disk($disk)->path($directory), '/\\');
    }

    /** Total size of the application's database (MySQL only), in bytes. */
    private function databaseBytes(): ?int
    {
        try {
            if (DB::getDriverName() !== 'mysql') {
                return null;
            }

            $row = DB::selectOne('SELECT SUM(DATA_LENGTH + INDEX_LENGTH) AS b FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()');

            return $row?->b !== null ? (int) $row->b : null;
        } catch (Throwable $e) {
            Log::channel('cleanup')->notice('Could not read the database size: '.$e->getMessage());

            return null;
        }
    }

    /** Eligible rows x the table's average row size, when both are known. */
    private function estimateBytes(CleanupTarget $target, array $stat): ?int
    {
        if (! isset($stat['rows_est'], $stat['bytes_est']) || (int) $stat['rows_est'] === 0 || $stat['eligible_rows'] === null) {
            return null;
        }

        return (int) round(($stat['bytes_est'] / $stat['rows_est']) * $stat['eligible_rows']);
    }

    /** An unsaved dry-run context: enough for a target's plan phase, never written to the ledger. */
    private function context(CleanupTarget $target): CleanupContext
    {
        $days = $this->registry->retentionDays($target);

        $run = new CleanupRun(['mode' => CleanupRun::MODE_DRY_RUN, 'trigger' => CleanupRun::TRIGGER_MANUAL]);
        $item = new CleanupRunItem(['target' => $target->key(), 'cutoff' => now()->subDays($days), 'retention_days' => $days]);

        return new CleanupContext($run, $item, now()->subDays($days), $days, true, false, 2000, 0, null, fn () => false);
    }
}
