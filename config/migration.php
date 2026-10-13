<?php

/*
|--------------------------------------------------------------------------
| Site migration (.oeparts packages)
|--------------------------------------------------------------------------
|
| Skeleton: only values whose defaults the owner and the plan have settled. Later phases
| add their own keys here and nowhere else (one config file for the feature).
|
*/

return [

    'import' => [
        // "Fresh target" and the recovery point count the SAME thing: rows in every table
        // outside the System group. A target above recovery_max_rows is refused with a message
        // that says what raising it costs, and the operator raises it knowingly, never silently.
        'fresh_max_rows' => (int) env('OE_MIGRATION_FRESH_MAX_ROWS', 500_000),
        'recovery_max_rows' => (int) env('OE_MIGRATION_RECOVERY_MAX_ROWS', 500_000),

        // A run that has not touched its state for this long is considered stalled/abandoned.
        'heartbeat_stale_seconds' => (int) env('OE_MIGRATION_IMPORT_HEARTBEAT', 1800),

        // A single statement that cannot be chunked (an ALTER ... ADD FULLTEXT over a million
        // products) announces how long it may legitimately take, so it is not mistaken for a stall.
        'long_stage_seconds' => (int) env('OE_MIGRATION_LONG_STAGE_SECONDS', 7200),
    ],

    'export' => [
        // References no foreign key can see, added by the operator for JSON columns this code does not know
        // about. Each: ['id' => 'custom_banner', 'table' => 'sections', 'column' => 'content', 'json_path' => 'image',
        // 'from' => 'content', 'target' => 'media']. The text for the warning is lang key migration.logical.<id>.
        'json_refs' => [],

        // A run that was started but whose driver never took its first step in this long is failed as "no worker": it
        // froze nothing (a freeze engages in the first step), and the operator is told which worker command to fix.
        'worker_pickup_seconds' => 120,

        // The watchdog gives a run nobody has advanced for this long one budgeted step. Far below the horizon below, so a
        // nudged run is never failed.
        'driver_idle_seconds' => 120,

        // One visit of the watchdog keeps stepping a run nobody else is driving for about this long (less than the five
        // minutes between visits), instead of one step per visit, which would take hours.
        'watchdog_budget_seconds' => 240,

        // Seconds a queue-driven export waits before its next step (0 in tests).
        'redispatch_delay_seconds' => 1,

        // The finished package is checked end to end in slices of about this many bytes per step.
        'verify_budget_bytes' => 64 * 1024 * 1024,

        // THE horizon of an export run: one that was not advanced (by a poll, the command line, a queue job or
        // a watchdog nudge) for this long is dead. Every advance saves the run, so its updated_at is the heartbeat.
        // The lock custody rule, the export janitor and ExportManager::advance() all use this one number.
        'heartbeat_stale_seconds' => (int) env('OE_MIGRATION_EXPORT_HEARTBEAT', 1800),

        // Freeze-cutover: the source stays in maintenance at most this long while exporting, and a
        // finished export holds the freeze for the operator for this long (0 = never auto-release).
        'freeze_max_minutes' => (int) env('OE_MIGRATION_FREEZE_MAX_MINUTES', 180),
        'hold_auto_release_minutes' => (int) env('OE_MIGRATION_HOLD_AUTO_RELEASE_MINUTES', 120),

        // A freeze is refused (a live copy is offered instead) when even the optimistic-to-pessimistic
        // estimate says the shop would show the maintenance page longer than this.
        'freeze_refuse_minutes' => (int) env('OE_MIGRATION_FREEZE_REFUSE_MINUTES', 720),

        // Disk the export needs: free_space_factor x (compressed database + compressed files) + one volume
        // + this much headroom, on the volume that holds storage/app/migration.
        'free_space_factor' => 1.15,
        'volume_bytes' => 512 * 1024 * 1024,
        'min_free_bytes' => 1024 * 1024 * 1024,

        // One step of the export (a request, a queue job, a watchdog nudge) works at most this long, then hands back a
        // checkpoint. Pages and segments are sized so a step never holds more than a few megabytes in memory.
        'batch_seconds' => 10,
        'page_rows' => 5000,
        'page_bytes' => 8 * 1024 * 1024,
        'segment_bytes' => 64 * 1024 * 1024,
        // The files stage hands back a checkpoint after about this many files (or batch_seconds, whichever first).
        'batch_files' => 256,
        'index_shard_rows' => 50_000,

        // How fast an export is assumed to go. These are STARTING values, not measurements: rehearsal Gate A
        // runs a real 1M-product export and writes the measured numbers back here. Until then every estimate is
        // shown as a range (low..high) and labelled as an estimate.
        'throughput' => [
            'db_rows_per_second' => 2500,
            'file_bytes_per_second' => 25 * 1024 * 1024,
            'files_per_second' => 120,
            'avg_row_bytes' => 400,
            'db_compress_ratio' => 0.2,
            'file_compress_ratio' => 0.97,
            'estimate_low_factor' => 0.6,
            'estimate_high_factor' => 1.8,
        ],
    ],

    'queues' => [
        // Migration work runs on its own queue so a freeze (which pauses the customer queues)
        // can never block the export that the freeze is for. Worker line:
        // --queue=migration,critical,default,low
        'migration' => 'migration',
        'customer' => ['critical', 'default', 'low'],
        // The worker probe job is dispatched on a CUSTOMER queue so it also works on hosts that
        // have not changed their worker command yet.
        'probe' => 'default',

        // A worker has "proven itself" for this long after it last picked up the probe job from the migration queue.
        'probe_fresh_seconds' => 600,
    ],

    'security' => [
        // Keys derived from a passphrase are kept between requests (one poll after another) wrapped
        // with the site key; a janitor removes anything older than this even if a run died.
        'escrow_ttl_seconds' => (int) env('OE_MIGRATION_ESCROW_TTL', 86400),

        // Wrong-passphrase throttle, per package and client address: after max_failures wrong
        // passphrases the next attempts are refused for base_lock_seconds, doubling with every
        // further failure up to max_lock_seconds. The key derivation cost is the real brake.
        'throttle' => [
            'max_failures' => 5,
            'base_lock_seconds' => 60,
            'max_lock_seconds' => 900,
            'forget_after_seconds' => 86400,
        ],
    ],

    'retention' => [
        // Exported packages are a full copy of customer data: removed from the source after this.
        'export_days' => (int) env('OE_MIGRATION_EXPORT_RETENTION_DAYS', 7),
    ],

];
