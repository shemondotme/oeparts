<?php

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
    'file_log_days' => (int) env('OE_CLEANUP_FILE_LOG_DAYS', 14),
    'update_log_days' => (int) env('OE_UPDATE_LOG_DAYS', 30),
];
