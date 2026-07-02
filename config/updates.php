<?php

/*
|--------------------------------------------------------------------------
| In-App Update System (Module 21)
|--------------------------------------------------------------------------
| Source of truth for behaviour/decisions: UPDATE_SYSTEM_MASTER_WORKFLOW.md.
| Pure-PHP, shared-hosting-safe one-click updater. Values here are the safe
| defaults; per-install overrides come from .env.
*/

return [

    'enabled' => env('OE_UPDATE_ENABLED', true),

    // Release channel (V1: stable only; beta = Phase 6).
    'channel' => env('OE_UPDATE_CHANNEL', 'stable'),

    // Where the updater looks for new releases (raw over HTTPS — no API rate limit).
    'check' => [
        'manifest_url' => env('OE_UPDATE_CHECK_URL', 'https://raw.githubusercontent.com/oeparts/oeparts/main/version.json'),
        'catalog_url'  => env('OE_UPDATE_CATALOG_URL', 'https://raw.githubusercontent.com/oeparts/oeparts/main/releases.json'),
        'frequency'    => env('OE_UPDATE_CHECK_FREQUENCY', 'daily'), // scheduled cadence
        'cache_ttl'    => (int) env('OE_UPDATE_CHECK_TTL', 21600),   // lazy-check cache (6h)
        'timeout'      => (int) env('OE_UPDATE_CHECK_TIMEOUT', 10),  // seconds
    ],

    // Opt-in auto-apply of security-flagged patch releases (OFF by default).
    'auto_apply_security' => env('OE_UPDATE_AUTO_SECURITY', false),

    'download' => [
        'timeout'       => (int) env('OE_UPDATE_DOWNLOAD_TIMEOUT', 300),
        'retries'       => (int) env('OE_UPDATE_DOWNLOAD_RETRIES', 3),
        'backoff'       => [1, 3, 5], // seconds between retries (resumable HTTP Range)
        'verify_sha256' => true,      // never disable in production (rule #11-security)
    ],

    // Require password re-auth (and 2FA if enabled) before applying (LOCKED DECISION #1).
    'require_reauth' => env('OE_UPDATE_REQUIRE_REAUTH', true),

    // Pre-flight environment gate.
    'required_extensions' => ['pdo_mysql', 'zip', 'openssl', 'mbstring', 'curl', 'fileinfo', 'json'],

    // Core vs user-data boundary (rule: never touch preserve_paths). See ARCHITECTURE.md.
    'core_paths' => [
        'app', 'bootstrap/app.php', 'config', 'database', 'lang',
        'public/build', 'resources', 'routes', 'vendor', 'artisan',
        'composer.json', 'composer.lock', 'version.json', 'CHANGELOG.md',
    ],
    'preserve_paths' => ['.env', 'storage'],

    // App-independent recovery console (public/oe-recovery.php). Disabled unless a
    // key is set (opt-in-armed). See CLAUDE.md rule #47.
    'recovery' => [
        'enabled'      => (bool) env('OE_RECOVERY_KEY'),
        'ip_allowlist' => array_values(array_filter(array_map('trim', explode(',', (string) env('OE_RECOVERY_IP_ALLOWLIST', ''))))),
    ],

    // Application root the updater operates on (dir-rename swap target, writability +
    // deployment-type checks). Overridable for tests / non-standard docroots.
    'root_path' => base_path(),

    // Operator flag: this instance sits behind a load balancer / has sibling app
    // servers. Pre-flight warns (never auto-applies) when set (LOCKED DECISION #9).
    'multi_server' => (bool) env('OE_UPDATE_MULTI_SERVER', false),

    // Pre-flight environment gate (Chunk 3.1).
    'preflight' => [
        // Free disk needed ≈ zip + extract + backup ⇒ size_bytes × this multiplier.
        'disk_multiplier' => (int) env('OE_UPDATE_DISK_MULTIPLIER', 3),
        // Absolute floor of free space required regardless of release size.
        'min_free_bytes'  => (int) env('OE_UPDATE_MIN_FREE_BYTES', 200 * 1024 * 1024), // 200 MB
    ],

    // Post-swap boot steps (Chunk 3.4) — run on a FRESH request after the file swap,
    // in listed order, after `migrate --force` (always first, critical). Each is
    // idempotent/safe to re-run. Framework caches are rebuilt here — NEVER via
    // Cache::flush() (rule #5/#46).
    'post_swap' => [
        // Optional artisan commands after migrate. critical=true aborts the update.
        'artisan' => [
            ['command' => 'package:discover', 'critical' => false],
            ['command' => 'filament:upgrade',  'critical' => false],
            ['command' => 'storage:link',      'critical' => false],
        ],
        // vendor:publish --tag=<tag> --force for each (default none — avoid clobbering).
        'vendor_publish_tags' => [],
        // Idempotent reference seeders (db:seed --class --force). Releases opt-in here;
        // default none, since re-running a non-idempotent seeder could reset user data.
        'seeders' => [],
        // Rebuild config/route/view/event caches (clear then cache).
        'rebuild_cache' => true,
        // queue:restart — only fires when a real worker driver is in use (not sync).
        'restart_queue' => true,
    ],

    // Framework-independent state files (dir-rename map, arm flag, single-update lock).
    'state_path' => storage_path('app/updates'),

    // Dedicated log channel (config/logging.php).
    'log_channel' => 'updates',
];
