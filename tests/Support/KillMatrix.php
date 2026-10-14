<?php

namespace Tests\Support;

/**
 * The kill matrix of plan section 11.4 as data: every stage of the export and the import, where a kill may land, what
 * resuming must do and what is asserted. A guard test (KillMatrixCoverageTest) compares it with the REAL stage lists:
 * a stage that exists without a row here fails the build, so a new stage cannot be added without deciding how it
 * survives being killed; and a row whose stage has not landed yet must be named in `allowedMissing()`, a list that
 * shrinks as stages arrive and MUST be empty from step 7.5 on.
 *
 * Rows are keyed by (side, stage) because `verify` and `files` exist on both sides.
 */
final class KillMatrix
{
    public const EXPORT = 'export';

    public const IMPORT = 'import';

    /** Rows that are not stages of a list (rollback, undo, the release path, the post-import phase). */
    public const PSEUDO = 'pseudo';

    /**
     * @return array<int, array{side: string, stage: string, kill: string, resume: string, assert: string}>
     */
    public static function rows(): array
    {
        $r = static fn (string $side, string $stage, string $kill, string $resume, string $assert): array => compact('side', 'stage', 'kill', 'resume', 'assert');

        return [
            // ---- export
            $r(self::EXPORT, 'plan', 'K1; after export-freeze.json is written but before the maintenance setting; after the setting but before the run is saved', 'engage the freeze idempotently, never remembering an already-set maintenance value as "previous"', 'previous maintenance value restored on release; a driver that never ran leaves no freeze'),
            $r(self::EXPORT, 'db', 'K1/Km/Kl of the first, a middle and the last table; between segment seal and checkpoint; between ftruncate and the next write', 'truncate the segment to the recorded size', 'byte-identical segment; zero duplicate rows'),
            $r(self::EXPORT, 'files', 'K1/Km/Kl of a volume; between index shard and checkpoint', 'truncate volume and index to recorded sizes', 'every sha256 equals the index'),
            $r(self::EXPORT, 'verify', 'between fence "after" probe and the report write; between report and freeze release', 're-run probes; release once', 'drift figure present; freeze released exactly once'),
            $r(self::EXPORT, 'pack', 'mid-entry; mid-seal; between seal and the atomic rename', 'resume at entry granularity', 'final package opens with PackageReader::verifyAll'),
            $r(self::PSEUDO, 'export.finalize', 'before the history line; before lock release', 'idempotent', 'exactly one history line; lock released'),
            $r(self::PSEUDO, 'freeze.release', 'during ExportFreeze::release; between maintenance restore and file delete; watchdog killed mid-run', 'release is idempotent and restores the REMEMBERED value', 'never leaves maintenance on; one release only'),
            // ---- import
            $r(self::IMPORT, 'locate', 'before the key check; after the key check before the state write', 're-peek, same pinned identity', 'one escrow entry; package_identity unchanged'),
            $r(self::IMPORT, 'verify', 'in entry N (K1/Km/Kl); inside the whole-file sha256 pass; in the LAST entry', 'redo at most one entry; resume the hash from its context', 'zero write queries in the whole stage'),
            $r(self::IMPORT, 'ensure_schema', 'installer path: K1/Km/Kl of migrate --force; admin/CLI: any', 'migrate --force again (pending only)', 'schema equals a fresh install'),
            $r(self::IMPORT, 'preflight', 'any; after options frozen before the state save', 're-run; re-confirm', 'privilege probe table gone'),
            $r(self::IMPORT, 'recovery_point', 'mid-package (writer resume); after seal before verify; after the .env before-image', 'resume the writer; re-verify', 'recovery.oeparts verifies; confirm() cannot continue otherwise'),
            $r(self::IMPORT, 'arm', 'after EACH of the seven sub-steps: lock, identity, audit, marker, pause, begin(), re-pause', 'redo unfinished sub-steps from the arm.* flags; or ArmUndo', 'a worker restarted under the new prefix between marker and re-pause is still held; exactly one import.started line per run id; ArmUndo ends failed, not rolled_back'),
            $r(self::IMPORT, 'db_clear', 'between TRUNCATEs: first/mid/last table', 're-run (safe)', 'all non-system tables empty'),
            $r(self::IMPORT, 'db_index_drop', 'after indexes_dropped is saved but before the DDL; after the DDL before the checkpoint', 'check information_schema.STATISTICS, treat a dropped index as done', 'index dropped exactly once (MySQL suite)'),
            $r(self::IMPORT, 'db_data', 'between INSERT and checkpoint, first/middle/last batch of first/middle/last table; mid-table', 'COUNT(*) rule: redo, advance, or TRUNCATE-restart that table', 'zero duplicate rows; equal SQL-side checksums'),
            $r(self::IMPORT, 'db_index_restore', 'while the ALTER runs; after completion before the checkpoint', 'an existing index counts as done', 'index present after resume and after a rollback (MySQL suite)'),
            $r(self::IMPORT, 'db_audit', 'mid FK scan cursor', 'continue the cursor', 'orphan list identical'),
            $r(self::IMPORT, 'db_seed_defaults', 'mid-seeder of each seed-backed group; after truncate before seeding', 're-truncate the group first, then seed again', 'row counts equal the FreshInstallBaselineTest baseline'),
            $r(self::IMPORT, 'files', 'mid-file (tmp present); after rename before the cursor save; between journal append and rename; last batch', 'sweep tmp, skip verified files', 'file tree hash equal; journal complete'),
            $r(self::IMPORT, 'fin_secrets', 'between two secret writes', 'rewrite idempotently', 'each value decrypts under the TARGET key'),
            $r(self::IMPORT, 'fin_check_migrations', 'any', 'pure check', 'nothing pending'),
            $r(self::IMPORT, 'fin_rewrite', 'K1/Km/Kl of a table; between a chunk commit and the cursor save', 'optimistic guard makes the redo a no-op', 'resumed run equals uninterrupted run'),
            $r(self::IMPORT, 'fin_normalize', 'between two setting writes', 'rewrite explicit values', 'the "we adjusted" list is complete and not duplicated'),
            $r(self::IMPORT, 'fin_origin_reset', 'after the truncate of origin-bound tables before the setting flips; between two flips', 'idempotent', 'every origin_reset key is OFF and recorded once in the ledger'),
            $r(self::IMPORT, 'fin_sequences', 'between two sequence rows', 'recompute the floor', 'a counter is never lowered'),
            $r(self::IMPORT, 'fin_caches', 'after forgetDriver() before the cache rebuild; any', 'idempotent', 'the pre-warmed-empty-cache test still passes'),
            $r(self::IMPORT, 'fin_admins', 'mid-step', 'idempotent', 'at least one active super_admin'),
            $r(self::IMPORT, 'fin_sessions', 'mid-step', 'idempotent', 'sessions purged'),
            $r(self::IMPORT, 'fin_links', 'any', 'idempotent, best effort', 'WARN only'),
            $r(self::IMPORT, 'fin_sitemap', 'any', 'idempotent, best effort', 'WARN only'),
            $r(self::IMPORT, 'fin_queue', 'after queue:restart before the Queue::resume', 'resume the queues', 'no queue left paused'),
            $r(self::IMPORT, 'fin_backup_key', 'after the key is generated before the .env write; after the write before the state save', 'the second advance must NOT replace an existing key', 'OE_BACKUP_KEY non-empty, differs from any package value, written once (installer path)'),
            $r(self::IMPORT, 'fin_readiness', 'mid-checks', 're-run', 'same report'),
            $r(self::IMPORT, 'complete', 'after the scrub; after the escrow delete; after the marker removal before the lock release; after the lock release before the history line; between the history line and the ActivityLog row; before installed.lock; after installed.lock', 'idempotent from the complete.* flags; installed.lock strictly last', 'marker gone, queue released, one history line, one ActivityLog row, escrow deleted'),
            $r(self::PSEUDO, 'post.phase_b', 'during the HTTP checks; between the switch_mode env write and the queue:restart', 're-run checks; re-apply', 'mode agrees with the worker probe'),
            $r(self::PSEUDO, 'ArmUndo', 'between each reverse step', 'continue the undo', '.env equals the before-image, marker gone, queues resumed'),
            $r(self::PSEUDO, 'ImportRollback', 'in the files journal replay; mid-table reload of recovery.oeparts; between the FULLTEXT re-add and the .env restore; in the cache clear; between marker removal and lock release', 'resume the rollback', 'ends rolled_back; tables, rows, files and .env keys equal the pre-import state'),
        ];
    }

    /**
     * Rows whose stage has not been implemented yet. Shrinks as stages arrive; MUST be empty from step 7.5 on.
     *
     * @return array<int, string> "side:stage"
     */
    public static function allowedMissing(): array
    {
        return [
            'import:files',
            'import:fin_secrets', 'import:fin_check_migrations', 'import:fin_rewrite', 'import:fin_normalize', 'import:fin_origin_reset',
            'import:fin_sequences', 'import:fin_caches', 'import:fin_admins', 'import:fin_sessions', 'import:fin_links', 'import:fin_sitemap',
            'import:fin_queue', 'import:fin_backup_key', 'import:fin_readiness', 'import:complete',
            'pseudo:post.phase_b',
        ];
    }

    /** @return array<int, string> "side:stage" of every row */
    public static function keys(): array
    {
        return array_map(static fn (array $row): string => $row['side'].':'.$row['stage'], self::rows());
    }
}
