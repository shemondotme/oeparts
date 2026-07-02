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
        'verify_sha256' => true, // never disable in production (rule #11-security)
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

    // Framework-independent state files (dir-rename map, arm flag, single-update lock).
    'state_path' => storage_path('app/updates'),

    // Dedicated log channel (config/logging.php).
    'log_channel' => 'updates',
];
