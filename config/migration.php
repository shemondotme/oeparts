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

        // A live-copy export nobody advanced (no tab, no worker, no watchdog nudge) for this long
        // is failed and its lock released.
        'stale_after_seconds' => (int) env('OE_MIGRATION_EXPORT_STALE', 1800),

        // Freeze-cutover: the source stays in maintenance at most this long while exporting, and a
        // finished export holds the freeze for the operator for this long (0 = never auto-release).
        'freeze_max_minutes' => (int) env('OE_MIGRATION_FREEZE_MAX_MINUTES', 180),
        'hold_auto_release_minutes' => (int) env('OE_MIGRATION_HOLD_AUTO_RELEASE_MINUTES', 120),
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
