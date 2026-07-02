<?php

/*
|--------------------------------------------------------------------------
| Backup Engine (Module 14 / 21)
|--------------------------------------------------------------------------
| Chunked, resumable, encrypted backups — shared by the updater (update-safety
| profile) and standalone disaster recovery (full profile). Source of truth:
| UPDATE_SYSTEM_MASTER_WORKFLOW.md.
*/

return [

    'enabled' => env('OE_BACKUP_ENABLED', true),

    // MANDATORY encryption — backups hold customer PII (GDPR, CLAUDE.md rule #45).
    // OE_BACKUP_KEY is a DEDICATED key (not APP_KEY). Losing it = losing every
    // encrypted backup — back it up somewhere safe.
    'encryption' => [
        'enabled' => true,
        'key'     => env('OE_BACKUP_KEY'),
        'cipher'  => 'aes-256-gcm',
    ],

    // Laravel filesystem disk. Off-site (S3 EU region / SFTP) strongly recommended;
    // 'local' warns in pre-flight. S3 must be an EU region for GDPR residency.
    'disk' => env('OE_BACKUP_DISK', 'local'),

    // Volume split size for large file backups (keeps memory flat, enables resume).
    'volume_bytes' => (int) env('OE_BACKUP_VOLUME_BYTES', 512 * 1024 * 1024), // 512 MB

    // gzip (universal) | zstd (faster, only if the extension is present).
    'compression' => env('OE_BACKUP_COMPRESSION', 'gzip'),

    'db' => [
        'chunk_rows' => (int) env('OE_BACKUP_DB_CHUNK', 5000), // keyset-cursor page size
        // Tables backed up structure-only by default (bloat / regenerable / session state).
        'exclude_table_data' => [
            'activity_log', 'activity_logs', 'email_logs', 'login_logs',
            'search_logs', 'failed_search_logs', 'sessions', 'cache', 'cache_locks',
            'jobs', 'job_batches', 'failed_jobs',
        ],
    ],

    'files' => [
        // Never back up caches, logs, our own backup/update dirs, or build tooling.
        // NOTE: vendor/ is intentionally NOT excluded — full-profile restores must be
        // self-contained on shared hosting (no composer available).
        'exclude' => [
            'storage/framework/cache',
            'storage/framework/sessions',
            'storage/framework/views',
            'storage/logs',
            'storage/app/backups',
            'storage/app/updates',
            'node_modules',
            '.git',
        ],
        'throttle_ms' => (int) env('OE_BACKUP_THROTTLE_MS', 0), // pause between chunks on shared hosting
    ],

    // GFS retention — auto-prune (LOCKED DECISION #5).
    'retention' => [
        'daily'   => (int) env('OE_BACKUP_KEEP_DAILY', 7),
        'weekly'  => (int) env('OE_BACKUP_KEEP_WEEKLY', 4),
        'monthly' => (int) env('OE_BACKUP_KEEP_MONTHLY', 6),
    ],

    'schedule' => [
        'enabled' => env('OE_BACKUP_SCHEDULE', true),
        'time'    => env('OE_BACKUP_TIME', '01:00'), // supersedes the old db:backup command
    ],

    // A run still 'running' this many seconds after it started is presumed crashed;
    // the BackupJanitor reclaims its files and releases the shared lock.
    'stale_after_seconds' => (int) env('OE_BACKUP_STALE_AFTER', 3600),

    // Ordered pipeline of BackupStage classes per profile (Chunk 2.1 seam).
    // The DB (2.2), file (2.3) and env/encryption (2.4) stages register here as
    // they land; the engine runs them in listed order, one chunk per poll.
    'stages' => [
        'update_safety' => [],
        'full'          => [],
    ],

    'path' => storage_path('app/backups'),
];
