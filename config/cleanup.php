<?php

use App\Services\Cleanup\Targets\BackupPartialsTarget;
use App\Services\Cleanup\Targets\BackupRetentionTarget;
use App\Services\Cleanup\Targets\DevFilesTarget;
use App\Services\Cleanup\Targets\NotificationsTarget;

/*
|--------------------------------------------------------------------------
| Cleanup Engine (System -> Cleanup)
|--------------------------------------------------------------------------
|
| Fallback defaults. Every value here is overridable at runtime from the admin
| Cleanup page (settings group "cleanup"); read them with
|   settings('cleanup.<key>', config('cleanup.<key>'))
| so an install that predates the seeded rows keeps working unchanged.
|
| Per-target retention (days) and the per-target auto-clean toggle live on the
| target classes in App\Services\Cleanup (default + floor), not here, so the
| defaults and the safety floors can never drift apart.
*/

return [
    // Master switch for the scheduled (automatic) cleanup. Manual cleanup always works.
    'auto_enabled' => (bool) env('OE_CLEANUP_AUTO', false),

    // Automatic runs only start inside this window (store local time, HH:MM). Outside
    // it a running batch pauses and resumes the next night, so a cleanup never fights
    // peak traffic or the nightly backup.
    'window_start' => env('OE_CLEANUP_WINDOW_START', '02:00'),
    'window_end' => env('OE_CLEANUP_WINDOW_END', '06:00'),

    // Rows deleted per statement. The engine adapts it per chunk (halves when a chunk
    // takes > 1s, grows when < 100ms) but never above this ceiling nor below 200.
    'chunk_rows' => (int) env('OE_CLEANUP_CHUNK_ROWS', 2000),

    // Pause between chunks (ms) so replicas and other writers can breathe.
    'sleep_ms' => (int) env('OE_CLEANUP_SLEEP_MS', 50),

    // Work budget per advance() tick (seconds). Small for the browser-polled UI so a
    // shared host's max_execution_time is never at risk; larger for the CLI.
    'tick_seconds' => (float) env('OE_CLEANUP_TICK_SECONDS', 3),
    'cli_tick_seconds' => (float) env('OE_CLEANUP_CLI_TICK_SECONDS', 50),

    // An AUTOMATIC run aborts a target that would delete more than this share of its
    // table (a retention typo must not be able to wipe a whole history). Manual runs
    // are confirmed by the admin instead, but never bypass a target's floor.
    'max_delete_pct' => (int) env('OE_CLEANUP_MAX_DELETE_PCT', 50),

    // A running run whose heartbeat (updated_at) is older than this is "stuck".
    'stale_after_seconds' => (int) env('OE_CLEANUP_STALE_AFTER', 900),

    // A backup / update / product-import row that says "running" but has not been touched for this
    // long is abandoned, not busy (real work holds the shared lock or keeps its row moving), so it
    // does not block cleanup.
    'busy_row_fresh_seconds' => (int) env('OE_CLEANUP_BUSY_FRESH', 6 * 3600),

    // After this long without any heartbeat the janitor stops re-driving a run and
    // closes it (PARTIAL if it deleted anything, else FAILED).
    'abandon_after_seconds' => (int) env('OE_CLEANUP_ABANDON_AFTER', 6 * 3600),

    // Cleanup History keeps this many days of finished runs (floor 30).
    'history_retention_days' => (int) env('OE_CLEANUP_HISTORY_DAYS', 180),
    'history_retention_floor_days' => 30,

    // Disk usage that raises the Health Check "disk" warning (percent used).
    'disk_alert_pct' => (int) env('OE_CLEANUP_DISK_ALERT_PCT', 80),
    'disk_fail_pct' => 95,

    // Save a CSV.gz of the rows about to be deleted first (opt-in; may hold personal data).
    'export_before_delete' => (bool) env('OE_CLEANUP_EXPORT_BEFORE_DELETE', false),

    // Notify admins when an automatic run ends partial/failed ('partial_failed' | 'failed' | 'never').
    'notify_on' => env('OE_CLEANUP_NOTIFY_ON', 'partial_failed'),

    // Log files in storage/logs: laravel-*.log (Monolog daily) and the engine's own
    // install-*/updates-* files. Recent files are truncated, never unlinked (an open
    // log handle keeps an unlinked file's disk space allocated).
    // Where a running cleanup keeps its per-run lock files and candidate lists (must be writable by the web user).
    'work_dir' => storage_path('framework/cleanup'),

    // Directory the application writes its log files to (the 'logs' root of the file targets).
    'log_dir' => storage_path('logs'),

    // Where a production restore stages the tree it swaps in (ProductionRestoreService / RestoreManager).
    'restore_dir' => storage_path('app/restore'),

    'file_log_days' => (int) env('OE_CLEANUP_FILE_LOG_DAYS', 14),
    'update_log_days' => (int) env('OE_UPDATE_LOG_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Targets (the allow-list)
    |--------------------------------------------------------------------------
    |
    | The ONLY things the engine can clean. An entry is either an array (a declarative
    | "delete rows older than N days" table target, see ConfiguredDbTarget) or the class
    | of a bespoke target. Business tables (orders, invoices, payments, users, products...)
    | are refused by TargetRegistry::NEVER_PRUNE even if listed here.
    |
    |   days   default retention        floor  the shortest an operator may set
    |   truncatable   "Truncate all" offered (debug/telemetry only; never audit trails)
    */
    'targets' => [
        // ── Visitor & staff activity ──────────────────────────────────────────────
        ['key' => 'search_logs', 'label' => 'Search logs', 'group' => 'logs', 'table' => 'search_logs', 'column' => 'created_at',
            'days' => 90, 'floor' => 7, 'truncatable' => true,
            'description' => 'What visitors searched for. Holds IP addresses and search text, so it is kept no longer than needed.'],
        ['key' => 'failed_search_logs', 'label' => 'Failed search logs', 'group' => 'logs', 'table' => 'failed_search_logs', 'column' => 'created_at',
            'days' => 90, 'floor' => 7, 'truncatable' => true,
            'description' => 'Searches that found nothing. Holds IP addresses and search text.'],
        ['key' => 'login_logs', 'label' => 'Login logs', 'group' => 'logs', 'table' => 'login_logs', 'column' => 'created_at',
            'days' => 90, 'floor' => 30,
            'description' => 'Sign-in attempts (successful and failed) with email and IP. A security audit trail, so it cannot be wiped all at once.'],
        ['key' => 'email_logs', 'label' => 'Email logs', 'group' => 'logs', 'table' => 'email_logs', 'column' => 'sent_at',
            'days' => 90, 'floor' => 30,
            'description' => 'Every email the shop sent or failed to send, with the recipient address.'],
        ['key' => 'activity_logs', 'label' => 'Admin activity log', 'group' => 'logs', 'table' => 'activity_logs', 'column' => 'created_at',
            'days' => 365, 'floor' => 30,
            'description' => 'Who changed what in the admin panel. The audit trail: kept a year by default and never wiped all at once.'],
        ['key' => 'cron_logs', 'label' => 'Scheduled task logs', 'group' => 'logs', 'table' => 'cron_logs', 'column' => 'ran_at',
            'days' => 30, 'floor' => 3, 'truncatable' => true,
            'description' => 'One row per scheduled-task run (thousands a day). Only the recent ones are useful.'],

        // ── SEO & catalog operations ──────────────────────────────────────────────
        ['key' => 'not_found_logs', 'label' => '404 logs', 'group' => 'logs', 'table' => 'not_found_logs', 'column' => 'last_seen_at',
            'days' => 90, 'floor' => 14,
            'description' => 'Pages visitors asked for that do not exist. Entries still being hit are newer than the cutoff, so they stay.'],
        ['key' => 'indexnow_push_logs', 'label' => 'IndexNow push logs', 'group' => 'logs', 'table' => 'indexnow_push_logs', 'column' => 'created_at',
            'days' => 30, 'floor' => 7, 'truncatable' => true,
            'description' => 'Search-engine ping results. The SEO dashboard only needs the last week.'],
        ['key' => 'bulk_update_logs', 'label' => 'Bulk update logs', 'group' => 'history', 'table' => 'bulk_update_logs', 'column' => 'created_at',
            'days' => 180, 'floor' => 30,
            'description' => 'Price/stock bulk actions and imports. An old entry can no longer be reverted once it is deleted.'],
        ['key' => 'inventory_logs', 'label' => 'Inventory logs', 'group' => 'history', 'table' => 'inventory_logs', 'column' => 'created_at',
            'days' => 180, 'floor' => 30,
            'description' => 'In/out-of-stock changes made by imports and bulk actions.'],
        ['key' => 'product_import_runs', 'label' => 'Product import history', 'group' => 'history', 'table' => 'product_import_runs', 'column' => 'finished_at',
            'days' => 180, 'floor' => 30, 'scope' => 'terminal_import_runs',
            'description' => 'Finished CSV imports (their row-level errors). A running import is never touched.'],

        // ── Monitoring snapshots ──────────────────────────────────────────────────
        ['key' => 'health_check_snapshots', 'label' => 'Health check history', 'group' => 'history', 'table' => 'health_check_snapshots', 'column' => 'checked_at',
            'days' => 30, 'floor' => 1, 'keep' => 'newest_per:check_key:20',
            'description' => 'A reading every 5 minutes per check. The latest ones per check always stay (alerts compare against them).'],
        ['key' => 'cache_metric_snapshots', 'label' => 'Cache metric history', 'group' => 'history', 'table' => 'cache_metric_snapshots', 'column' => 'recorded_at',
            'days' => 30, 'floor' => 1, 'truncatable' => true, 'keep' => 'newest:12',
            'description' => 'Cache hit-rate readings every 5 minutes. The newest 12 always stay for the dashboard.'],
        ['key' => 'core_web_vitals_snapshots', 'label' => 'Core Web Vitals history', 'group' => 'history', 'table' => 'core_web_vitals_snapshots', 'column' => 'recorded_at',
            'days' => 365, 'floor' => 30, 'truncatable' => true, 'keep' => 'newest:12',
            'description' => 'Weekly page-speed snapshots. The newest 12 always stay for the trend chart.'],
        ['key' => 'not_found_log_snapshots', 'label' => '404 trend history', 'group' => 'history', 'table' => 'not_found_log_snapshots', 'column' => 'recorded_at',
            'days' => 365, 'floor' => 30, 'truncatable' => true, 'keep' => 'newest:12',
            'description' => 'Weekly 404 counts for the SEO trend. The newest 12 always stay.'],

        // ── System bookkeeping ────────────────────────────────────────────────────
        ['key' => 'failed_jobs', 'label' => 'Failed queue jobs', 'group' => 'system', 'table' => 'failed_jobs', 'column' => 'failed_at',
            'days' => 30, 'floor' => 3,
            'description' => 'Background jobs that failed. Old ones are noise once the cause is fixed; recent ones can still be retried.'],
        ['key' => 'admin_sessions', 'label' => 'Admin session records', 'group' => 'system', 'table' => 'admin_sessions', 'column' => 'created_at',
            'days' => 7, 'floor' => 1,
            'description' => 'Records of past admin sign-ins that were never closed.'],
        ['key' => 'personal_access_tokens', 'label' => 'Expired API tokens', 'group' => 'system', 'table' => 'personal_access_tokens', 'column' => 'expires_at',
            'days' => 1, 'floor' => 0, 'scope' => 'expiring_tokens',
            'description' => 'API tokens whose expiry date has passed. Tokens with no expiry are never touched.'],
        NotificationsTarget::class,

        // ── Update & backup history ───────────────────────────────────────────────
        ['key' => 'update_histories', 'label' => 'Update history', 'group' => 'history', 'table' => 'update_histories', 'column' => 'finished_at',
            'days' => 365, 'floor' => 30, 'scope' => 'terminal_updates', 'keep' => 'newest:20',
            'description' => 'Finished updates and restores. The newest 20 always stay; one still in progress is never touched.'],
        ['key' => 'backup_history', 'label' => 'Old backup records', 'group' => 'backups', 'table' => 'backup_runs', 'column' => 'created_at',
            'days' => 180, 'floor' => 30, 'scope' => 'backup_history', 'keep' => 'backup_protected',
            'description' => 'History rows of backups whose files are already deleted. Rows an update may need for a rollback stay.'],

        // ── Files & folders (type = file; each names a REGISTERED root, never a path) ─────────
        ['type' => 'file', 'key' => 'file_logs', 'label' => 'Application log files', 'group' => 'files', 'root' => 'logs',
            'pattern' => '/^(laravel|schedule|worker|queue|php)[A-Za-z0-9_.-]*\.log$/', 'days' => 14, 'floor' => 3,
            'description' => 'Rotated storage/logs files (laravel-2026-10-01.log ...). Today\'s file is never touched.'],
        ['type' => 'file', 'key' => 'oversized_logs', 'label' => 'Oversized log files', 'group' => 'files', 'root' => 'logs',
            'pattern' => '/^[A-Za-z0-9][A-Za-z0-9_.-]*\.log$/', 'days' => 0, 'floor' => 0, 'action' => 'truncate',
            'description' => 'Any log over 50 MB (a log that never rotates, or one flooding): emptied IN PLACE so the logger keeps working and the space really comes back.'],
        ['type' => 'file', 'key' => 'update_logs', 'label' => 'Install & update log files', 'group' => 'files', 'root' => 'logs',
            'pattern' => '/^(install|updates|cleanup)[A-Za-z0-9_.-]*\.log$/', 'days' => 30, 'floor' => 7,
            'description' => 'install-*.log, updates-*.log and cleanup-*.log. Kept longer than the others: they explain what an update or cleanup did.'],
        BackupRetentionTarget::class,
        BackupPartialsTarget::class,
        ['type' => 'file', 'key' => 'update_downloads', 'label' => 'Downloaded update packages', 'group' => 'backups', 'root' => 'update_downloads',
            'pattern' => '/^oeparts-[A-Za-z0-9._-]+\.zip$/', 'days' => 7, 'floor' => 1, 'lock' => true, 'rule' => 'no_active_update',
            'description' => 'Release zips kept after an update was applied (zip installs only). One an update still needs is never removed.'],
        ['type' => 'file', 'key' => 'update_staging', 'label' => 'Update staging folders', 'group' => 'backups', 'root' => 'update_staging',
            'pattern' => '/^[A-Za-z0-9][A-Za-z0-9._-]*$/', 'dirs' => true, 'days' => 1, 'floor' => 1, 'lock' => true, 'rule' => 'no_active_update',
            'description' => 'Extracted releases left after an update finished or failed.'],
        ['type' => 'file', 'key' => 'update_lock_files', 'label' => 'Stale run lock files', 'group' => 'backups', 'root' => 'update_state',
            'pattern' => '/^advance-run-\d+\.lock$/', 'days' => 1, 'floor' => 1, 'rule' => 'run_terminal_or_gone',
            'description' => 'Per-run lock files of backups that already finished or no longer exist.'],
        ['type' => 'file', 'key' => 'restore_work', 'label' => 'Restore working folders', 'group' => 'backups', 'root' => 'restore',
            'pattern' => '/^run-\d+$/', 'dirs' => true, 'days' => 7, 'floor' => 1, 'lock' => true, 'rule' => 'restore_dir',
            'description' => 'Full copies of a restored tree (storage/app/restore/run-N) that nothing deletes after the restore.'],
        ['type' => 'file', 'key' => 'backup_orphans', 'label' => 'Orphaned backup folders', 'group' => 'backups', 'root' => 'backups',
            'pattern' => '/^\d+$/', 'dirs' => true, 'days' => 1, 'floor' => 1, 'lock' => true, 'rule' => 'orphan_backup_dir',
            'description' => 'Backup folders with no backup record, or whose backup failed or was already pruned. A backup that finished and is still meant to exist is never an orphan.'],
        ['type' => 'file', 'key' => 'import_csv', 'label' => 'Leftover import files', 'group' => 'files', 'root' => 'imports',
            'pattern' => '/^[A-Za-z0-9._-]+\.csv$/', 'days' => 1, 'floor' => 1, 'rule' => 'unreferenced_import',
            'description' => 'CSV uploads of imports that failed or never started. A running (resumable) import is never touched.'],
        ['type' => 'file', 'key' => 'livewire_tmp', 'label' => 'Temporary uploads', 'group' => 'files', 'root' => 'livewire_tmp',
            'pattern' => '/^[A-Za-z0-9._-]+$/', 'days' => 1, 'floor' => 1,
            'description' => 'Files uploaded into a form but never saved (livewire-tmp).'],
        ['type' => 'file', 'key' => 'cleanup_archives', 'label' => 'Cleanup export files', 'group' => 'files', 'root' => 'cleanup_archives',
            'pattern' => '/^\d+$/', 'dirs' => true, 'days' => 30, 'floor' => 7,
            'description' => 'The "export before delete" CSV files. They can hold personal data, so they do not stay forever.'],
        ['type' => 'file', 'key' => 'cleanup_workfiles', 'label' => 'Cleanup work files', 'group' => 'files', 'root' => 'cleanup_work',
            'pattern' => '/^\d+-[a-z0-9_]+\.ndjson$/', 'days' => 2, 'floor' => 1,
            'description' => 'Candidate lists a cleanup writes while it works; normally removed by the run itself.'],
        ['type' => 'file', 'key' => 'file_cache', 'label' => 'Expired file-cache entries', 'group' => 'files', 'root' => 'file_cache', 'recursive' => true,
            'pattern' => '/^[a-f0-9]{40}$/', 'days' => 0, 'floor' => 0, 'rule' => 'expired_cache',
            'description' => 'Cache files whose expiry has passed (only when the cache driver is "file"). Live entries and locks are never touched.'],
        ['type' => 'file', 'key' => 'file_sessions', 'label' => 'Expired session files', 'group' => 'files', 'root' => 'file_sessions',
            'pattern' => '/^[A-Za-z0-9]{40}$/', 'days' => 0, 'floor' => 0, 'rule' => 'session_expired',
            'description' => 'Session files nobody has used for longer than the session lifetime (only when sessions are stored as files).'],
        DevFilesTarget::class,

        // Test-only: a private folder the Playwright e2e suite fills with its OWN fixture files, so the real
        // cleanup UI can be driven end to end without ever touching real data. Registered only when
        // OE_CLEANUP_E2E_TARGET is set (local/dev e2e); it does not exist on a normal install.
        ...(env('OE_CLEANUP_E2E_TARGET') ? [
            ['type' => 'file', 'key' => 'e2e_scratch', 'label' => 'E2E scratch files', 'group' => 'files', 'root' => 'e2e_scratch',
                'pattern' => '/^e2e-[A-Za-z0-9._-]+$/', 'days' => 1, 'floor' => 1, 'auto' => false,
                'description' => 'Test-only folder used by the automated browser tests.'],
        ] : []),

        // ── The engine's own ledger ───────────────────────────────────────────────
        ['key' => 'cleanup_history', 'label' => 'Cleanup history', 'group' => 'history', 'table' => 'cleanup_runs', 'column' => 'finished_at',
            'days' => 180, 'floor' => 30, 'scope' => 'terminal_cleanup_runs', 'keep' => 'cleanup_history',
            'description' => 'Finished cleanup runs. The newest 50 and the last success of every target always stay.'],
        ['key' => 'cleanup_disk_samples', 'label' => 'Disk usage samples', 'group' => 'history', 'table' => 'cleanup_disk_samples', 'column' => 'measured_at',
            'days' => 400, 'floor' => 30,
            'description' => 'The disk readings behind the growth trend.'],
    ],
];
