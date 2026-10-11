<?php

namespace App\Services\Cleanup\Contracts;

use App\Services\Cleanup\CleanupContext;
use App\Services\Cleanup\TargetRegistry;

/**
 * One thing the Cleanup Engine knows how to clean (a log table, a directory of
 * leftovers, a backup-retention pass, ...).
 *
 * Targets are an ALLOW-LIST: the engine can only ever act on a target registered in
 * {@see TargetRegistry}. Nothing from a request (a table name,
 * a path) ever reaches a query or the filesystem except through one of these.
 *
 * Work is expressed as three resumable phases the manager drives one bounded slice at
 * a time. Each phase method receives the item's checkpoint array BY REFERENCE, does as
 * much work as fits before `$deadline` (microtime(true) value), keeps everything it needs
 * to continue inside the checkpoint (it is persisted after every call), and returns true
 * once the phase is complete.
 *
 * Checkpoint keys the MANAGER reads (a target must keep them accurate):
 *   targeted      int   rows/files that qualified when planning finished
 *   deleted       int   rows/files actually removed (as reported by the database/filesystem)
 *   failed        int   rows/files that could not be removed
 *   remaining     int   still qualifying after the delete phase (verify phase)
 *   late_rows     int   qualifying rows added after the run started (informational)
 *   bytes_apparent / bytes_allocated   int|null  bytes freed (files only)
 *   archive_rows  int   rows written to the archive (when archiving)
 * Everything else in the checkpoint is private to the target.
 */
interface CleanupTarget
{
    /** Stable identifier, also the suffix of the target's settings keys. Lowercase snake_case. */
    public function key(): string;

    public function label(): string;

    /** One sentence for the admin: what accumulates here and why deleting it is safe. */
    public function description(): string;

    /** 'logs' | 'history' | 'system' | 'files' | 'backups' — the section it is listed under. */
    public function group(): string;

    /** 'db' | 'file' | 'callable' */
    public function kind(): string;

    /** Default retention in days (0 = not age-based, e.g. "already expired"). */
    public function defaultDays(): int;

    /** The shortest retention an operator can configure; enforced in the UI, the registry AND at plan time. */
    public function floorDays(): int;

    /** May the admin wipe the whole thing at once ("Truncate all")? Only debug/telemetry data. */
    public function truncatable(): bool;

    /** Report-only targets are listed and previewed but can never delete. */
    public function reportOnly(): bool;

    /** Whether the scheduled run includes this target until an admin turns it off (still subject to the dry-run-first rule). */
    public function autoByDefault(): bool;

    /**
     * File-family targets (backups, update artifacts, restore staging) also take the
     * shared BackupLock so a backup/update cannot start underneath them.
     */
    public function needsBackupLock(): bool;

    /**
     * Estimated total size of the target in rows (db) — used by the over-delete guard.
     * null = unknown (the guard then cannot judge and lets the run proceed).
     */
    public function totalRows(): ?int;

    /** Fresh checkpoint for a new item. Freeze anything that must not change mid-run (max id, ...). */
    public function begin(CleanupContext $ctx): array;

    /** Phase 1 — count what qualifies. Sets `targeted`. Return true when complete. */
    public function plan(CleanupContext $ctx, array &$cp, float $deadline): bool;

    /** Phase 2 — delete one bounded slice. Never called for dry runs / report-only targets. Return true when complete. */
    public function delete(CleanupContext $ctx, array &$cp, float $deadline): bool;

    /** Phase 3 — recount what still qualifies. Sets `remaining` (and `late_rows`). Return true when complete. */
    public function verify(CleanupContext $ctx, array &$cp, float $deadline): bool;
}
