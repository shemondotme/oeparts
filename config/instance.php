<?php

/*
|--------------------------------------------------------------------------
| This installation's role, and what may run while it is held still
|--------------------------------------------------------------------------
|
| A site can be held still on purpose: a freeze export has put the shop in maintenance, an import is
| replacing the database, or (later) the installation is a staging copy that must not touch real
| customers. Something that holds the site still registers itself with App\Services\Instance\SystemGate,
| and the gate then decides, for every scheduled command, whether it may run.
|
| Every scheduled command belongs to exactly one class:
|
|   always    keeps the installation healthy and must run even when everything else is held:
|             reclaimers of abandoned runs and stale locks, the scheduler heartbeat, the migration
|             watchdog and janitor.
|   internal  housekeeping and monitoring that touches no customer: regenerating files, pruning logs,
|             snapshots, backups, cleanup. Held while a freeze or an import is under way (they would
|             change the data being copied, or fight for the same lock), but allowed on a staging copy.
|   customer  anything that reaches people or money: mail, newsletters, reminders, order
|             transitions, admin push, automatic updates. Held in every case; a staging copy must
|             never send a real customer anything.
|
| A scheduled command that is in none of the lists is "unknown" and is held whenever anything is holding
| the site (fail closed): a task added next month cannot slip through a freeze by not being classified.
| ScheduleGateTest fails the build until every scheduled command is listed here, and flags stale entries.
|
| Names are the bare command names, without arguments.
|
| What this installation IS (OE_MODE)
| -----------------------------------
|
| `mode` says whether this is the real shop or a copy that must stay harmless:
|
|   production  the real shop. The default, and what every existing install is.
|   staging     a working copy for testing: it runs like the shop but sends no mail, takes no real
|               money, tells search engines to stay away and does nothing that reaches a customer.
|   quarantine  a copy that has just been moved in and is not trusted yet: like staging, but stricter
|               (no scheduled work at all except what keeps it healthy). An import leaves a site here
|               until the launch checks pass.
|
| The value lives only in .env. A missing or empty value is production, so an installation that has
| never heard of this setting does not change. Any other value (a typo such as "stagging") is treated as
| quarantine and logged as critical: a mistake must never turn a copy back into the real shop.
|
| `overrides` is what a non-production copy hides from the rest of the application when it reads its
| settings. The stored values are never edited, so going live restores the real ones at once.
|
*/

return [

    'schedule' => [

        'always' => [
            'oeparts:backup:cleanup-stale',
            'oeparts:update:cleanup-stale',
            'oeparts:cleanup:cleanup-stale',
            'scheduler:heartbeat',
            'oeparts:migrate:watchdog',
            'oeparts:migrate:janitor',
        ],

        'internal' => [
            'sitemap:generate',
            'otp:clean',
            'cart:clean',
            'logs:clean',
            'oeparts:backup',
            'oeparts:cleanup:auto',
            'oeparts:cleanup:measure',
            'oeparts:update:check',
            'health:snapshot',
            'cache:snapshot',
            'cwv:snapshot',
            'notfound:snapshot',
            'oeparts:invoices:clean-cache',
            'oeparts:products:optimize-search-index',
        ],

        'customer' => [
            'oeparts:invoices:remind',
            'abandoned-cart:process',
            'oeparts:newsletter:send-due',
            'oeparts:orders:auto-complete',
            'oeparts:orders:expire-unpaid',
            'oeparts:update:auto-apply',
            'oeparts:refunds:clean-images',
            'oeparts:admin-push:flush',
        ],

    ],

    // production | staging | quarantine. Read raw: App\Services\Instance\SafeMode decides what it means.
    'mode' => env('OE_MODE'),

    // Short identifier of this installation. Becomes part of the cache and Redis prefixes of a copy, so two
    // sites sharing one Redis server cannot read each other's cache.
    'id' => env('OE_INSTANCE_ID'),

    'mail' => [
        // Reserved: what an installation does with mail besides the mode's default. Empty = follow the mode.
        'policy' => env('OE_MAIL_POLICY'),

        // A staging copy may still mail these addresses (exact address or "@domain"), comma separated.
        'allowlist' => env('OE_MAIL_ALLOWLIST'),
    ],

    // A staging or quarantine copy runs no scheduler through the web "cron fallback" unless this is true.
    'nonprod_http_cron' => (bool) env('OE_NONPROD_HTTP_CRON', false),

    // Prefix of order and invoice numbers on a staging or quarantine copy, so a test order can never be
    // mistaken for a real one. At most 30 characters in total with the real prefix.
    'sequence_prefix' => 'T-',

    // Settings that arm something on their own and belong to the OLD server: an imported site arrives with them OFF, in both launch
    // modes, and says so in its report ("switch them on when you are ready"). Imported settings arrive wholesale; an imported
    // `cleanup.auto_enabled = 1` would start deleting data on the new site at once, and the backup schedule and the security
    // auto-update point at disks and release channels of the old server. `cleanup.auto_<target>` is not listed here: it is
    // generated from the Cleanup Engine's target registry (App\Services\Instance\OriginResetRegistry), so a new target is covered.
    //
    // `keep` is every other on/off setting of those groups that was looked at and deliberately left as imported, with the reason:
    // OriginResetRegistryTest fails until each such seeded setting is in `settings`, in `overrides` above, or in `keep`.
    'origin_reset' => [
        'settings' => [
            'cleanup' => ['auto_enabled'],
            'backup' => ['schedule_enabled'],
            'updates' => ['auto_apply_security'],
        ],
        'keep' => [
            'seo.detail_pages_enabled' => 'A content feature of the shop, not tied to the old server.',
            'performance.cloudflare_enabled' => 'Hidden on a copy by the settings mask; on a live launch the owner decides whether the new site sits behind it.',
        ],
    ],

    // Settings a non-production copy hides while it reads them: group => key => the value it sees instead.
    // A key ending in `*` covers every key of that group that starts with the text before it.
    'overrides' => [
        'seo' => [
            'indexnow_enabled' => false,
            'indexnow_api_key' => '',
            'google_ping_enabled' => false,
            'bing_ping_enabled' => false,
            'default_robots' => 'noindex,nofollow',
            'canonical_host' => '',
            'gsc_*' => '',
            'crux_api_key' => '',
        ],
        'integrations' => [
            'gtm_id' => '',
            'ga4_measurement_id' => '',
            'fb_pixel_id' => '',
            'crisp_website_id' => '',
        ],
        'general' => [
            'header_scripts' => '',
            'footer_scripts' => '',
        ],
        'performance' => [
            'cloudflare_enabled' => false,
        ],
        'push' => [
            'enabled' => false,
        ],
        'updates' => [
            'auto_apply_security' => false,
        ],
        'backup' => [
            'schedule_enabled' => false,
        ],
        'auth' => [
            'google_client_id' => '',
            'facebook_client_id' => '',
        ],
        'payment' => [
            'airwallex_environment' => 'sandbox',
        ],
    ],

];
