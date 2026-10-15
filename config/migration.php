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

        // How much work one call of the runner page does before answering (never more than half the web server's own
        // time limit). The command line and the watchdog pass their own budget.
        'advance_budget_seconds' => 10,

        // The integrity pass reads and checks the package in slices of about this many bytes between time checks.
        'verify_budget_bytes' => 64 * 1024 * 1024,

        // 'drop_readd' drops the products search index before the load and builds it again after (MySQL only; a server
        // without that index is unaffected); 'keep' leaves it in place while the rows load. Measured on 1,000,000 products
        // (Gate A, MySQL 8.0): dropping and re-adding took 1087 s to load plus 159 s to rebuild; keeping the index had loaded
        // 58% after 1503 s (about 2570 s projected), so the load is more than twice as slow with the index in place.
        'fulltext_strategy' => env('OE_MIGRATION_FULLTEXT_STRATEGY', 'drop_readd'),

        // Calibration for the time estimates, measured on the same 1M-product rehearsal (a 4-CPU VM that was busy with other
        // work, so these are on the cautious side): loading rows, copying the way-back (recovery point), and the one thing that
        // scales with the content, changing the old site address inside records (worst case: every product names it).
        'throughput_rows_per_second' => 1000,
        'recovery_rows_per_second' => 10000,
        'rewrite_rows_per_second' => 400,

        // Below this many products the sitemap is built inline while the import finishes; above it the nightly schedule does it.
        'sitemap_inline_max' => (int) env('OE_MIGRATION_SITEMAP_INLINE_MAX', 50_000),

        // After the cache prefix changes the framework's own caches (config, routes, views, events) are cleared and rebuilt, as an
        // update does. Switched off in the test environment, where rebuilding would cache the test configuration.
        'rebuild_framework_cache' => (bool) env('OE_MIGRATION_REBUILD_CACHE', true),

        // The background drivers of a run nobody is polling. The watchdog (every five minutes, needs cron) nudges a run whose
        // heartbeat is older than watchdog_idle_seconds, for at most watchdog_budget_seconds; it marks a run stalled after
        // watchdog_stalled_seconds without a heartbeat. Hosts with no cron at all get a last-resort self-drive from the 503 gate,
        // bounded so that visitors cannot amplify load: only when the scheduler is silent and the run idle, behind a persisted
        // exponential backoff, one non-blocking lock, and stopped for good after max_failures attempts that moved nothing.
        'watchdog_idle_seconds' => 120,
        'watchdog_stalled_seconds' => 900,
        'watchdog_budget_seconds' => 240,
        'self_drive' => [
            'heartbeat_seconds' => 120,
            'scheduler_fresh_seconds' => 600,
            'max_failures' => 5,
            'backoff_base_seconds' => 30,
            'backoff_max_seconds' => 900,
            'budget_seconds' => 10,
        ],

        // How long a client is told a held queue stays held at most (a safety net: the marker file is what really holds it).
        'queue_hold_seconds' => (int) env('OE_MIGRATION_QUEUE_HOLD_SECONDS', 86400),
    ],

    'export' => [
        // How many exports the admin page lets be STARTED per hour, for the whole site: every export is a full copy of the shop,
        // so a hijacked session must not be able to make a stream of them. (The command line is not limited: it needs a shell.)
        'starts_per_hour' => 3,

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

    'transport' => [
        // Getting a package onto this server from a link. The protection against reaching internal addresses is NOT a
        // setting and cannot be switched off from the admin panel: allow_private_hosts is read from the environment only,
        // for an intranet or a rehearsal where both sites live on private addresses.
        'allow_private_hosts' => (bool) env('OE_MIGRATION_ALLOW_PRIVATE_HOSTS', false),

        // https only; port 443 unless the owner lists others here (env, comma separated).
        'allowed_ports' => array_values(array_filter(array_map('intval', explode(',', (string) env('OE_MIGRATION_ALLOWED_PORTS', '443'))))),

        // When not empty, only these hosts (and their subdomains) can be fetched.
        'allowed_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('OE_MIGRATION_ALLOWED_HOSTS', ''))))),

        'max_url_length' => 2048,

        // The largest package a link may bring (the real disk check happens once the size is known).
        'max_bytes' => (int) env('OE_MIGRATION_FETCH_MAX_BYTES', 32 * 1024 * 1024 * 1024),

        // One request asks for at most this many bytes; a slow link or a short budget asks for less.
        'range_bytes' => 32 * 1024 * 1024,

        // How long one call of the runner page / a poll works before answering (never more than half the web server's
        // time limit). The command line and the queue job pass their own, longer budgets.
        'budget_seconds' => 10,
        'job_budget_seconds' => 25,

        // Wait between attempts after a failure that can pass (5 s, 15 s, 45 s, 2 min, 5 min), and how many attempts in all.
        'backoff_seconds' => [5, 15, 45, 120, 300],
        'max_attempts' => 8,

        // The checksum pass reads the file in slices of this many bytes between time checks.
        'verify_slice_bytes' => 64 * 1024 * 1024,

        // Finished and failed transfers leave their state for this many days; an abandoned download is closed after
        // abandoned_hours without a single step.
        'retention_days' => 7,
        'abandoned_hours' => 48,
    ],

    // One-time download links served by THIS site for its own exported packages (/_pkg/{token}).
    'share' => [
        'ttl_hours' => 24,
        'max_ttl_hours' => 168,
        'max_active' => 5,
        // After the first request the same downloader may resume for this long, even past the link's own expiry.
        'claim_window_hours' => 12,
        // Downloads running at once. One is the safe default: PHP cannot see the size of the web server's worker pool, and a
        // few long downloads can starve a small one. Raise it only when the pool is known to be big (env only).
        'max_streams' => (int) env('OE_SHARE_MAX_STREAMS', 1),
        // Wrong tries per address per hour before every further try is refused with 429.
        'deny_limit_per_hour' => 20,
        'chunk_bytes' => 1024 * 1024,
    ],

    // Groups the installer fills with reference data. A group left out of an import ends in the state a fresh install
    // leaves it in: its tables are emptied and these seeders run (class names under Database\Seeders).
    'seed_backed' => [
        'content' => ['SectionsSeeder'],
        'shipping_tax' => ['CarriersSeeder'],
    ],

    'retention' => [
        // Exported packages are a full copy of customer data: removed from the source after this.
        'export_days' => (int) env('OE_MIGRATION_EXPORT_RETENTION_DAYS', 7),
    ],

    // The web installer's "import an existing site" branch. Both switches are environment-only on purpose: they weaken
    // a protection, so they are never a form field.
    'installer' => [
        // The branch belongs to the first browser that starts it. Another browser may take it over after this long without
        // activity (or on the server with oeparts:migrate:wizard-reset).
        'wizard_idle_seconds' => 1800,

        // A file in the inbox that changed less than this long ago is still being uploaded and cannot be chosen.
        'stable_seconds' => 30,

        // Off: the installer does not list the inbox and asks for the file name instead (a shared host where the names
        // themselves are private).
        'list_inbox' => (bool) env('OE_INSTALLER_LIST_INBOX', true),

        // A passphrase typed over a connection that is not secure can be read on the way. Refused unless the operator
        // accepts that risk here (ignored for a loopback address).
        'allow_http_passphrase' => (bool) env('OE_INSTALLER_ALLOW_HTTP_PASSPHRASE', false),
    ],

];
