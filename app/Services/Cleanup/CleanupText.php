<?php

namespace App\Services\Cleanup;

use App\Models\CleanupRun;
use App\Models\CleanupRunItem;

/**
 * Plain-language wording for what the engine records as machine codes, so the admin page, the notifications and
 * the CLI all say the same thing. Reason codes are stable identifiers stored with every run; this is the only
 * place that turns them into sentences.
 */
final class CleanupText
{
    /** @var array<string, string> */
    private const REASONS = [
        'dry_run' => 'Preview only — nothing was deleted.',
        'remaining_rows' => 'Some items qualified but were still there when the run finished.',
        'items_failed' => 'Some items could not be removed (see the log for the exact error).',
        'external_deleted' => 'Some items were removed by something else while the cleanup ran; they are not counted as ours.',
        'late_rows' => 'New items that qualify appeared while it ran; the next run will take them.',
        'over_delete' => 'The numbers contradict each other (more removed than found). Nothing more was done; this is a bug worth reporting.',
        'archive_mismatch' => 'The export file holds fewer rows than were deleted.',
        'cancelled_by_admin' => 'Stopped by an admin. What was already removed stays removed.',
        'over_delete_pct' => 'Skipped: it would have deleted more of this table than the safety limit allows for an automatic run. Preview it and run it by hand if that is intended.',
        'below_retention_floor' => 'Skipped: the retention is shorter than the minimum allowed for this data.',
        'too_many_rows_to_archive' => 'Skipped: too many rows to export first. Turn off "export before delete" or clean in smaller steps.',
        'archive_would_fill_disk' => 'Skipped: the export file would use too much of the free disk space.',
        'heartbeat_lost' => 'The run stopped making progress and was closed automatically. It can be resumed.',
        'exception' => 'An error stopped this item.',
        'fs_lag' => 'The disk reports less free space than expected right after deleting; filesystems update that figure with a delay.',
        'busy' => 'Waiting for a backup, update or restore to finish.',
    ];

    public static function reason(string $code): string
    {
        if (str_starts_with($code, 'error: ')) {
            return 'Error: '.substr($code, 7);
        }

        return self::REASONS[$code] ?? ucfirst(str_replace('_', ' ', $code));
    }

    /** Short label for a run or item status. */
    public static function status(string $status, bool $dryRun = false): string
    {
        if ($dryRun && $status === CleanupRun::STATUS_SUCCESS) {
            return 'Preview ready';
        }

        return match ($status) {
            CleanupRun::STATUS_SUCCESS => 'Completed & verified',
            CleanupRun::STATUS_PARTIAL => 'Partly completed',
            CleanupRun::STATUS_FAILED => 'Failed',
            CleanupRun::STATUS_CANCELLED => 'Cancelled',
            CleanupRun::STATUS_PAUSED => 'Waiting',
            CleanupRun::STATUS_RUNNING => 'Running',
            CleanupRun::STATUS_PENDING => 'Starting',
            CleanupRunItem::STATUS_SKIPPED_GUARD => 'Skipped (safety limit)',
            CleanupRunItem::STATUS_SKIPPED_FLOOR => 'Skipped (retention floor)',
            CleanupRunItem::STATUS_BLOCKED_BUSY => 'Skipped (system busy)',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    /** The CSS tone (pill class suffix) that matches a status. */
    public static function tone(string $status, bool $dryRun = false): string
    {
        if ($dryRun && $status === CleanupRun::STATUS_SUCCESS) {
            return 'info';
        }

        return match ($status) {
            CleanupRun::STATUS_SUCCESS => 'ok',
            CleanupRun::STATUS_PARTIAL, CleanupRun::STATUS_PAUSED, CleanupRunItem::STATUS_SKIPPED_GUARD,
            CleanupRunItem::STATUS_SKIPPED_FLOOR, CleanupRunItem::STATUS_BLOCKED_BUSY => 'warn',
            CleanupRun::STATUS_FAILED => 'down',
            default => 'muted',
        };
    }

    /** The Filament colour name (badge, stat, callout) for a {@see tone()}. */
    public static function color(string $status, bool $dryRun = false): string
    {
        return match (self::tone($status, $dryRun)) {
            'ok' => 'success',
            'warn' => 'warning',
            'down' => 'danger',
            'info' => 'info',
            default => 'gray',
        };
    }

    public static function bytes(?int $bytes, int $precision = 1): string
    {
        if ($bytes === null) {
            return '—';
        }
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return round($bytes / (1024 ** $i), $i === 0 ? 0 : $precision).' '.$units[$i];
    }

    public static function number(?int $n): string
    {
        return $n === null ? '—' : number_format($n);
    }

    public static function duration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.'s';
        }
        if ($seconds < 3600) {
            return intdiv($seconds, 60).'m '.($seconds % 60).'s';
        }

        return intdiv($seconds, 3600).'h '.intdiv($seconds % 3600, 60).'m';
    }
}
