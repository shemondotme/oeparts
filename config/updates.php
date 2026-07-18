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
        'manifest_url' => env('OE_UPDATE_CHECK_URL', 'https://raw.githubusercontent.com/shemondotme/oeparts/main/version.json'),
        'catalog_url'  => env('OE_UPDATE_CATALOG_URL', 'https://raw.githubusercontent.com/shemondotme/oeparts/main/releases.json'),
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

    // Release signature verification (Chunk 6.1). RSA-SHA256 via openssl (already a
    // required extension → guaranteed on shared hosting; no ext-sodium dependency).
    // The updater authenticates each release's (version + sha256) against an app-baked
    // public key, so a tampered manifest/zip can't be applied even if it hashes cleanly.
    'signing' => [
        'algo' => 'rsa-sha256',
        // App-baked TRUST ANCHOR (ships with the app so every install can verify). When
        // set, signature verification is ENFORCED — a missing/invalid signature BLOCKS the
        // update at pre-flight. Empty = signing not yet provisioned (verification skipped,
        // pre-flight WARNs) — opt-in rollout, like OE_RECOVERY_KEY. Provision via env or a
        // committed resources/keys/release-public.pem; generate with:
        //   openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:4096 -out release-private.pem
        //   openssl rsa -in release-private.pem -pubout -out release-public.pem
        'public_key' => env('OE_RELEASE_PUBLIC_KEY') ?: (
            is_file(resource_path('keys/release-public.pem'))
                ? (string) file_get_contents(resource_path('keys/release-public.pem'))
                : null
        ),
        // Release-SIGNING private key — build/CI ONLY, NEVER committed (a CI secret).
        // Read only by oeparts:release:manifest when producing a release.
        'private_key' => env('OE_RELEASE_PRIVATE_KEY'),
    ],

    // Post-update verification (Chunk 3.6) — run after finalize; ANY failure triggers
    // auto-rollback (reverse swap + restore DB).
    'verify' => [
        // Critical tables that must exist after migrations.
        'required_tables' => [
            'admins', 'users', 'orders', 'order_items', 'products', 'categories',
            'settings', 'update_histories', 'backup_runs', 'backup_parts',
        ],
        // Referential-integrity spot-checks: [child, fk, parent, parent_key] — no orphans.
        'referential' => [
            ['order_items', 'order_id', 'orders', 'id'],
        ],
        // In-process smoke (DB reachable + critical tables readable).
        'smoke' => true,
    ],

    // Framework-independent state files (dir-rename map, arm flag, single-update lock).
    'state_path' => storage_path('app/updates'),

    // Dedicated log channel (config/logging.php).
    'log_channel' => 'updates',

    // Release build (Chunk 5.1) — LOCAL / CI ONLY. Production never builds a release
    // (rules #18/#19). The build script (build/build.sh) exports a clean tree, installs
    // prod deps, builds assets, then `oeparts:build` strips dev files, bundles licenses,
    // and writes the per-file sha256 manifest before zipping.
    'build' => [
        // Paths removed from the release zip (dev-only / secret / internal docs). Matched
        // by EXACT path or directory-prefix, so '.env' never matches '.env.example'
        // (which MUST ship — new_env_keys are diffed from it, decision #3).
        'exclude' => [
            '.git', '.github', '.gitignore', '.gitattributes',
            'tests', 'node_modules', 'build', 'dist',
            '.env', '.env.testing', '.env.backup',
            '.env.docker.example', '.env.docker.hostingsim', '.env.docker.overrides',
            '.editorconfig', '.vscode', '.devcontainer', '.idea', '.cursor',
            'phpunit.xml', 'pint.json', '.php-cs-fixer.php', '.php-cs-fixer.dist.php', '.styleci.yml',
            'storage/app/backups', 'storage/app/updates', 'storage/logs',
            // Local Docker dev environment (Sail) — never relevant to an installed release.
            'compose.yaml', 'docker',
            // Internal dev docs (CHANGELOG.md + README.md still ship).
            'UPDATE_SYSTEM_MASTER_WORKFLOW.md', 'ADMIN_PANEL_MASTER_WORKFLOW.md',
            'PRD.md', 'ARCHITECTURE.md', 'CLAUDE.md',
        ],
        // Per-file sha256 manifest — enables modified-core detection (#44) + future delta.
        'manifest_file' => 'file-manifest.json',
        // Bundled third-party license text (open-source compliance, decision #14).
        'licenses_file' => 'THIRD-PARTY-LICENSES.md',
        // Release download URL template (Chunk 5.2). {version} = SemVer, {asset} = zip name.
        // Versioned (not /latest/) so the updater can resolve a sequential upgrade path.
        'release_url_template' => env('OE_RELEASE_URL', 'https://github.com/shemondotme/oeparts/releases/download/v{version}/{asset}'),
        'asset_name'           => 'oeparts-{version}.zip',
    ],
];
