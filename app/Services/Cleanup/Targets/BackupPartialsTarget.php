<?php

namespace App\Services\Cleanup\Targets;

use App\Services\Backup\BackupJanitor;
use App\Services\Cleanup\CleanupContext;
use App\Services\Cleanup\Contracts\CleanupTarget;

/**
 * Failed and abandoned backup runs that still hold files on disk (and a lock-holding "running" row that
 * stopped moving). The backup janitor already reclaims these every hour; this target makes what is waiting
 * VISIBLE on the Cleanup page and lets an admin run the same reclaim on demand, with before/after numbers.
 *
 * Delegates to {@see BackupJanitor}: partialsQuery() to count, cleanupPartials() to reclaim.
 */
final class BackupPartialsTarget implements CleanupTarget
{
    public function key(): string
    {
        return 'backup_partials';
    }

    public function label(): string
    {
        return 'Failed & abandoned backups';
    }

    public function description(): string
    {
        return 'Backups that failed or stopped halfway still hold their files. Removes those files; a backup that is still making progress is never touched.';
    }

    public function group(): string
    {
        return 'backups';
    }

    public function kind(): string
    {
        return 'callable';
    }

    public function defaultDays(): int
    {
        return 0;
    }

    public function floorDays(): int
    {
        return 0;
    }

    public function truncatable(): bool
    {
        return false;
    }

    public function reportOnly(): bool
    {
        return false;
    }

    public function autoByDefault(): bool
    {
        return false; // the janitor already does this every hour
    }

    public function needsBackupLock(): bool
    {
        return true;
    }

    public function totalRows(): ?int
    {
        return null;
    }

    public function begin(CleanupContext $ctx): array
    {
        return [];
    }

    public function plan(CleanupContext $ctx, array &$cp, float $deadline): bool
    {
        $runs = app(BackupJanitor::class)->partialsQuery()->get(['id', 'total_bytes']);

        $cp['targeted'] = $runs->count();
        $cp['bytes_apparent'] = (int) $runs->sum('total_bytes');

        return true;
    }

    public function delete(CleanupContext $ctx, array &$cp, float $deadline): bool
    {
        $cp['deleted'] = app(BackupJanitor::class)->cleanupPartials();
        $cp['failed'] = max(0, (int) $cp['targeted'] - (int) $cp['deleted']);

        return true;
    }

    public function verify(CleanupContext $ctx, array &$cp, float $deadline): bool
    {
        $cp['remaining'] = app(BackupJanitor::class)->partialsQuery()->count();
        $cp['late_rows'] = 0;

        return true;
    }
}
