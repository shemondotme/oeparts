<?php

use App\Services\Backup\Stages\DatabaseBackupStage;
use App\Services\Backup\Stages\EncryptTransportStage;
use App\Services\Backup\Stages\FileBackupStage;

/*
|--------------------------------------------------------------------------
| Backup Engine (Module 14 / 21)
|--------------------------------------------------------------------------
| Chunked, resumable, encrypted backups — shared by the updater (update-safety
| profile) and standalone disaster recovery (full profile). Source of truth:
| docs/UPDATE_SYSTEM_MASTER_WORKFLOW.md.
*/

return [

    'enabled' => env('OE_BACKUP_ENABLED', true),

    // MANDATORY encryption — backups hold customer PII (GDPR, CLAUDE.md rule #45).
    // OE_BACKUP_KEY is a DEDICATED key (not APP_KEY). Losing it = losing every
    // encrypted backup — back it up somewhere safe.
    'encryption' => [
        'enabled' => true,
        'key' => env('OE_BACKUP_KEY'),
        'cipher' => 'aes-256-gcm',
        // Soft wall-clock budget per EncryptTransportStage step: keep securing
        // whole parts (never a partial part) until this many seconds have
        // elapsed this step, then yield. Most parts (table schema, small data
        // chunks) are a few KB-MB, so encrypting several per poll instead of
        // exactly one cuts the fixed per-poll overhead for the common case,
        // while a single genuinely large volume still gets its own step.
        'batch_seconds' => (float) env('OE_BACKUP_ENCRYPT_BATCH_SECONDS', 5),
    ],

    // Final destination disk. Off-site (S3 EU region / SFTP) strongly recommended;
    // 'local' warns in pre-flight. S3 must be an EU region for GDPR residency.
    'disk' => env('OE_BACKUP_DISK', 'local'),

    // Local working disk where parts are staged + encrypted before transport. MUST
    // be a local-driver disk (native paths / append). Off-site parts are streamed
    // here first, then uploaded and deleted (never stage a whole backup off-site).
    'staging_disk' => env('OE_BACKUP_STAGING_DISK', 'local'),

    // Volume split size for large file backups (keeps memory flat, enables resume).
    'volume_bytes' => (int) env('OE_BACKUP_VOLUME_BYTES', 512 * 1024 * 1024), // 512 MB

    // gzip (universal) | zstd (faster, only if the extension is present).
    'compression' => env('OE_BACKUP_COMPRESSION', 'gzip'),

    'db' => [
        'chunk_rows' => (int) env('OE_BACKUP_DB_CHUNK', 5000), // keyset-cursor page size
        // Soft wall-clock budget per FSM step: keep dumping table schema/data
        // units (never partial — always whole schema-writes or whole keyset
        // pages) until this many seconds have elapsed this step, then yield.
        // Lets a site with many small tables clear several per poll instead of
        // one table-unit per 2s tick, without ever risking a shared-hosting
        // execution-time limit (bounded by TIME, never by a fixed count).
        'batch_seconds' => (float) env('OE_BACKUP_DB_BATCH_SECONDS', 5),
        // Consistent-snapshot intent (LOCKED DECISION #5). A true held-transaction
        // snapshot is impossible across the resumable, cross-request FSM, so this is
        // realised via the maintenance gate (writes blocked) and recorded in each
        // data part's meta for audit/restore; default is eventual-consistent chunked.
        'consistent' => (bool) env('OE_BACKUP_DB_CONSISTENT', false),
        // Tables backed up structure-only by default (bloat / regenerable / session state).
        'exclude_table_data' => [
            'activity_log', 'activity_logs', 'email_logs', 'login_logs',
            'search_logs', 'failed_search_logs', 'sessions', 'cache', 'cache_locks',
            'jobs', 'job_batches', 'failed_jobs',
        ],
        // Never backed up (schema OR data) — the backup/update engine's OWN live
        // operational bookkeeping, not application data. A database restore
        // replays a schema part as `DROP TABLE IF EXISTS x; CREATE TABLE x`, so
        // including these here would let a restore triggered by one update
        // attempt's rollback wipe a DIFFERENT, concurrently- or subsequently-
        // running backup/update attempt's own live rows out from under it —
        // confirmed live (2026-08-11): a rollback's restore dropped `backup_runs`
        // while a fresh retry's backup was actively inserting `backup_parts` rows
        // referencing it, failing every retry with a foreign-key violation.
        // `migrations` is deliberately NOT here — Laravel's migration-tracking
        // must stay consistent with whatever state the rest of a restore leaves
        // the schema in, even though that has its own known limitation (a table
        // created by a migration that ran AFTER the backup was taken survives a
        // restore untouched, since the restore only replays tables present in
        // the snapshot) — mitigated by keeping every migration in this app
        // idempotent (existence-checked) rather than by excluding this table.
        'exclude_tables_entirely' => [
            'backup_runs', 'backup_parts', 'update_histories',
        ],
    ],

    'files' => [
        // Root of the file backup (the whole app). Overridable so tests can point
        // at a fixture tree instead of the real project.
        'root' => base_path(),
        // Never back up caches, logs, our own backup/update dirs, or build tooling.
        // `.env*` is skipped here and handled as a dedicated encrypted 'env' part
        // (Chunk 2.4).
        //
        // vendor/ is EXCLUDED BY DEFAULT. It was originally included so a full-profile
        // restore is self-contained on shared hosting with no composer available, but
        // backing up + encrypting the entire vendor/ tree (tens of thousands of files)
        // is exactly what segfaulted PHP on Windows during a real full backup (see
        // CLAUDE.md rule #49) — a backup that crashes protects nothing. composer.json/
        // composer.lock ARE still backed up, so `composer install --no-dev` reproduces
        // vendor/ on restore. Set OE_BACKUP_INCLUDE_VENDOR=true to opt back into the
        // fully self-contained (but crash-prone on large trees) behaviour.
        'exclude' => array_values(array_filter([
            'storage/framework/cache',
            'storage/framework/sessions',
            'storage/framework/views',
            // PHPStan's result cache (phpstan.neon: tmpDir) — thousands of
            // small, purely-rebuildable analysis-cache files at any real
            // project size. Found live: backing these up at 100k-record
            // scale grew the backup's file-scan checkpoint (backup_runs.meta,
            // a JSON column) large enough to trip a MySQL "Invalid JSON
            // text" write failure, crashing the whole backup run.
            'storage/framework/phpstan',
            'storage/logs',
            'storage/app/backups',
            'storage/app/updates',
            'node_modules',
            '.git',
            '.env',
            // ReleaseBuilder's own build output (gitignored, purely regenerable
            // via `php artisan oeparts:release:build`) — `dist/export` re-explodes
            // a full copy of vendor/ as a release payload, which hit the exact
            // same checkpoint-JSON-bloat crash as the phpstan cache above at
            // 100k-scale. Backing up a build artifact that itself contains a
            // copy of already-excluded vendor/ protects nothing.
            'dist',
            env('OE_BACKUP_INCLUDE_VENDOR', false) ? null : 'vendor',
        ])),
        'throttle_ms' => (int) env('OE_BACKUP_THROTTLE_MS', 0), // pause between files on shared hosting
        // Soft wall-clock budget per FSM step: keep archiving files until this many
        // raw bytes have been processed this step, then yield (the volume stays open
        // and continues next poll). A volume filling up also ends a step.
        'batch_bytes' => (int) env('OE_BACKUP_FILE_BATCH_BYTES', 64 * 1024 * 1024), // 64 MB
        // Incremental (hash-manifest diff vs the previous successful full backup).
        // Per-run override via BackupRun.meta['incremental'].
        'incremental' => (bool) env('OE_BACKUP_FILE_INCREMENTAL', false),
    ],

    // GFS retention — auto-prune (LOCKED DECISION #5).
    'retention' => [
        'daily' => (int) env('OE_BACKUP_KEEP_DAILY', 7),
        'weekly' => (int) env('OE_BACKUP_KEEP_WEEKLY', 4),
        'monthly' => (int) env('OE_BACKUP_KEEP_MONTHLY', 6),
    ],

    'schedule' => [
        'enabled' => env('OE_BACKUP_SCHEDULE', true),
        'time' => env('OE_BACKUP_TIME', '01:00'), // supersedes the old db:backup command
    ],

    // A run still 'running' this many seconds after it started is presumed crashed;
    // the BackupJanitor reclaims its files and releases the shared lock.
    'stale_after_seconds' => (int) env('OE_BACKUP_STALE_AFTER', 3600),

    // Refuse to even START a backup below this much free space on the backup
    // path's volume — cheap, fails fast before wasting time/DB load on a run
    // that would fail mid-way anyway. Unlike the Update Engine's equivalent
    // check (PreflightService::checkDiskSpace()), a backup's eventual size
    // can't be predicted from a manifest, so this is a floor, not a
    // multiplier-of-expected-size estimate. Mid-run disk exhaustion despite
    // this (usage can still change between the check and the write) is
    // already handled safely regardless — BackupManager::advance()'s stage
    // try/catch routes ANY \Throwable (including a disk-full write failure)
    // to fail(), which marks the run FAILED and releases the lock cleanly.
    'min_free_bytes' => (int) env('OE_BACKUP_MIN_FREE_BYTES', 200 * 1024 * 1024),

    // Ordered pipeline of BackupStage classes per profile (Chunk 2.1 seam).
    // The engine runs them in listed order, one chunk per poll. EncryptTransport
    // MUST be last — it encrypts + ships every part the earlier stages staged.
    'stages' => [
        'update_safety' => [
            DatabaseBackupStage::class,
            EncryptTransportStage::class,
        ],
        'full' => [
            DatabaseBackupStage::class,
            FileBackupStage::class,
            EncryptTransportStage::class,
        ],
        'database_only' => [
            DatabaseBackupStage::class,
            EncryptTransportStage::class,
        ],
        'files_only' => [
            FileBackupStage::class,
            EncryptTransportStage::class,
        ],
    ],

    'path' => storage_path('app/backups'),
];
