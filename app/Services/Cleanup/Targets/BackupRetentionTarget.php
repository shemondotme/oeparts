<?php

namespace App\Services\Cleanup\Targets;

use App\Models\BackupRun;
use App\Services\Backup\BackupRetentionService;
use App\Services\Cleanup\CleanupContext;
use App\Services\Cleanup\Contracts\CleanupTarget;

/**
 * Backup retention (the GFS policy: newest per day/week/month) put under the engine, so the page can PREVIEW
 * which backups would be deleted and how much disk that frees, then run it with the lock, the verification and
 * the history like every other cleanup.
 *
 * It delegates both the decision and the deletion to {@see BackupRetentionService} — the same code the scheduled
 * backup runs after every backup — so the preview can never disagree with what really happens, and every safety
 * guard added there (newest of each profile, backups an update may roll back to, incremental chains) applies here.
 *
 * What counts as "deleted" is a backup whose FILES were removed and that was stamped pruned; its history row stays
 * (the separate "Old backup records" target deals with those later).
 */
final class BackupRetentionTarget implements CleanupTarget
{
    public function key(): string
    {
        return 'backup_retention';
    }

    public function label(): string
    {
        return 'Backups beyond the retention policy';
    }

    public function description(): string
    {
        return 'Deletes the files of backups older than the daily / weekly / monthly retention in Backup settings. The newest backup of each kind and any backup an update could still roll back to are always kept.';
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
        return 0; // governed by the backup retention settings, not by an age
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
        return true; // idempotent, and the scheduled backup runs the very same retention after each backup
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
        $prune = app(BackupRetentionService::class)->plan()['prune'];

        $cp['targeted'] = $prune->count();
        $cp['bytes_planned'] = (int) $prune->sum(fn (BackupRun $r) => (int) $r->total_bytes);
        $cp['bytes_apparent'] = $cp['bytes_planned']; // for a preview: what a real run would free

        return true;
    }

    public function delete(CleanupContext $ctx, array &$cp, float $deadline): bool
    {
        $result = app(BackupRetentionService::class)->prune();

        $cp['deleted'] = (int) $result['pruned'];
        $cp['failed'] = max(0, (int) $cp['targeted'] - (int) $result['pruned']);

        return true;
    }

    public function verify(CleanupContext $ctx, array &$cp, float $deadline): bool
    {
        $cp['remaining'] = app(BackupRetentionService::class)->plan()['prune']->count();
        $cp['late_rows'] = 0;

        $targeted = max(1, (int) $cp['targeted']);
        $cp['bytes_apparent'] = (int) round(((int) ($cp['bytes_planned'] ?? 0)) * min(1, ((int) ($cp['deleted'] ?? 0)) / $targeted));

        return true;
    }
}
