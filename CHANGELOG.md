# Changelog — OeParts

All notable changes to this project are documented here.

## 1.0.14 — 2026-07-29

### Added
- **Bulk Update Products rebuilt** (Catalog → Bulk Update Products) — filter products by manufacturer, condition, car model, price range, OEM number, or date added, then apply a price change (% or fixed), condition change, active/inactive toggle, delivery time, or MOQ across every match, not just a checkbox-selected page of rows. Every apply is previewable, requires an explicit confirmation, warns on batches over 500 products, can be downloaded as CSV before committing, and is fully revertible with one click from the Bulk Update Log — split across three new permissions (`bulk update product prices/stock/details`) so roles can be scoped per action type. Large batches email `super_admin`s automatically.
- **Country-based VAT** (opt-in, off by default) — Tax Settings gained a toggle and a new Tax Rates page (Commerce) for per-country VAT rates; when enabled, checkout charges the rate of the customer's shipping country instead of one flat rate for everyone, falling back to the flat rate for any country with no active rate configured. Seeded with starting rates for all 32 EU/EEA countries (including the UK) — **verify every rate before enabling**, these are a documented starting point, not a live feed.
- **Missing `returns-policy` and `shipping-information` footer pages** — the storefront footer has linked to these since before this release, but neither page existed in the database (a seeder/footer drift), so both 404'd. Both now ship with real (editable) content in all 5 languages.
- **"Add All EU/EEA Countries" button** on a Shipping Zone's Countries tab — adds the standard 32-country list in one click, skipping any already assigned; doesn't touch existing countries or other zones.
- **"Back to list" button on every resource's Create/Edit/View page** — previously the only way back to a list was the small breadcrumb link at the top.
- **Google/Facebook OAuth credentials moved off `.env`** — set under Settings → Authentication Settings → Social Login (OAuth), stored encrypted in the database, same pattern as Airwallex payment credentials.
- **Nginx deployment support** — `deploy/nginx/oeparts.conf` (previously only Apache's `public/.htaccess` had any web-server config in the repo, despite the README listing Nginx as supported) and `deploy/php-fpm/oeparts-pool-overrides.conf` for upload/execution limits (`.htaccess`'s overrides silently do nothing under PHP-FPM, which is what Nginx and increasingly Apache both actually run). A root-level `.htaccess` now also blocks direct access if a host's document root is ever misconfigured to the project root instead of `public/`.
- **`TRUSTED_PROXIES`** — new opt-in `.env` key for deployments where Nginx/Apache sits in front of PHP-FPM as a reverse proxy, or behind a CDN/load balancer; without it, IP-based blocking and generated `https://` URLs see the proxy's IP/scheme instead of the real client's. Off (no behavior change) unless set.

### Fixed
- **Selecting a Manufacturer filter on Products, Car Models, Categories, or Blog Posts threw a 500** — Filament's default filter-indicator-chip logic string-interpolates the filtered relation's raw attribute, which is a translatable JSON array (not a string) for `manufacturer.name`/`category.name`; the resulting PHP warning is fatal under this app's error-reporting config. Worse, because the filter is session-persisted, the *next* page load 500'd too, with no way back except clearing cookies. All four filters (plus SeoMeta's incomplete `robots` filter dropdown) now render correctly.
- **The Bulk Update Log page 500'd the moment it had any rows** — same root cause as above (a different case of it): `action_type` is cast to a PHP enum, but the page's badge-formatting code compared the raw enum instance against string literals, which never matches, falling through to `ucfirst()` on an object. Existing test coverage never caught this because it only ever exercised the page with an empty table.
- **Fresh installs never ran `storage:link`** — only the update system's post-update steps did, so uploaded product/blog images 404'd on any brand-new install until someone manually ran the command.
- **Footer logo icon was hidden entirely on mobile** (`hidden sm:block`) — unlike the navbar, which always shows its icon — now shown at every screen size, matching the navbar.
- **`.env` cleanup**: removed `AIRWALLEX_*` (never read from `.env` — `PaymentService` only reads Settings → Payment Settings) and `VIES_TIMEOUT` (never referenced anywhere in the codebase).
- **The "Europe" shipping zone had zero countries after seeding** — `ShippingZonesAndMethodsSeeder`'s own docblock claimed a 6-zone pan-European matrix; the actual code created one zone and three methods but never created a single `ShippingCountry` row. Checkout still worked (a missing zone match silently falls back to "every active method"), but zone-based shipping rules had no effect at all. Fresh installs now seed all 32 countries into the zone; existing installs should use the new "Add All EU/EEA Countries" button instead of re-running the seeder (which truncates first).

## 1.0.13 — 2026-07-25

### Added
- **Health Check, Failed Jobs, Cache Dashboard, System Updates, and Backup Management dashboards reworked** — all five now share a consistent dense-row/status-pill visual language instead of ad-hoc layouts. Health Check and Cache Dashboard gained history snapshots (`health:snapshot`, `cache:snapshot`, scheduled every 5 minutes) backing sparkline trends; Failed Jobs gained job-class grouping, exception classification, search, and bulk retry/flush; System Updates gained a pre-flight readiness strip, a visual step-by-step apply progress indicator, and a recent-updates strip; Backup Management gained a storage/last-backup/retention/encryption overview strip.
- **Release channel, auto-apply-security, and backup retention/schedule/stale-lock-threshold are now editable from the admin panel** instead of `.env`-only (System Updates → Update Settings, Backup Management → Backup Settings). Existing `.env` values seed as the initial database defaults.
- **A stuck shared backup/update lock is now visible and recoverable from the admin panel** — previously a crashed or abandoned run could hold the lock indefinitely with zero visibility anywhere in the UI, silently blocking every future backup and update until the hourly cleanup cron happened to catch it. Backup Management now shows the lock's owner and age live, with a "Release stale lock" action that only ever appears for a lock re-confirmed stale at click time — a genuinely in-progress run can never be interrupted this way.
- **System and Content admin navigation flattened from clusters into sidebar nav groups** — every sub-page kept its URL, but dashboard-style pages (tables, multi-column stat rows) are no longer cramped by a secondary cluster navigation panel eating horizontal space.

### Fixed
- **Login could silently fail on any install still served over plain HTTP** — `SESSION_SECURE_COOKIE` defaulted to `true` and the admin panel forced `https://` URL generation on any `APP_ENV=production` regardless of the site's actual scheme, so the browser refused to persist the session cookie on a host without SSL provisioned yet. Login now appeared to succeed but silently never logged you in. Both now follow the app's actual configured scheme.
- **The web installer's progress bar could stall right after creating the admin account** — the "already installed" guard could fire on the very next progress poll (that step legitimately populates the admins table three steps before the run finishes), redirecting the in-progress install away from itself.
- **`ActivityLog::create()` never actually persisted `created_at`** — not mass-assignable, and the model has no automatic timestamps, so every "last cleared" / "last warmed" / "last action" style display reading it (including the pre-existing Health Check page) always showed nothing. Fixed at the model level; can't silently regress per-call-site again.
- **The "Clear Application Cache" action was scanning the wrong Redis connection and prefix**, silently purging nothing on some installs.

## 1.0.12 — 2026-07-24

### Changed
- **Redis is now a recommended production upgrade, not a hard requirement** — removed the boot-time assertion that crashed `/install` on any host without the redis extension. Cache/session/queue now degrade cleanly to file/database/sync drivers when Redis isn't available, and `.env.example` defaults to `file`/`file`/`sync` so a fresh install always boots regardless of what the host offers. Redis is still recommended for production performance — just no longer required to get started.
- **The installer now runs as a chunked, resumable process** (`InstallManager`) instead of one long synchronous request, fixing a bug where a POST-only route made the final "install complete" step unreachable from a real browser session on some hosts.

### Added
- **HTTP-triggered cron fallback** — if a host's real system cron was never configured (or stopped running), scheduled tasks (backups, sitemap refresh, abandoned-cart emails, update checks) now still fire from a normal page load once the scheduler heartbeat goes stale, instead of silently never running. On by default; disable with `CRON_FALLBACK_ENABLED=false` if you have real cron configured and want to skip the (small, throttled) overhead entirely.
- **Fatal-error email notifications** — an uncaught exception inside the admin panel now emails every `super_admin` the moment it happens, instead of leaving a silent white screen with no signal anything broke.
- **Opt-in unattended security updates** — set `OE_UPDATE_AUTO_SECURITY=true` to have a daily scheduled command auto-apply (and auto-roll-back on failure) any release flagged security-only, using the same backup-first, pre-flight-gated update FSM as a manual "Apply Update" click. Off by default; routine feature releases always still require a manual click, and you're emailed the outcome either way.
- **Cross-server restore is now reachable via a dedicated CLI command** (`php artisan oeparts:backup:restore --import-manifest=...`) — see the README's "Moving to a new server" section for the full walkthrough.
- **Admin panel UI strings are now translatable** — labels across all Filament admin resources are wired through `lang/{locale}/admin.php` for the existing 5 languages, instead of being hardcoded English.

## 1.0.11 — 2026-07-21

### Fixed
- **Admin panel topbar logo didn't match the storefront navbar / login page brand mark** — the topbar version was a bare hexagon with a hardcoded, non-hoverable amber dot and a plain single-weight "OeParts" wordmark, missing the hover-rotate, hover color-invert, corner badge, and split-weight wordmark treatment used everywhere else the brand mark appears. Brought it in line with both reference implementations, verified in both light and dark sidebar themes.
- **5 more `canAccess()` calls could crash a real admin page with "Call to a member function hasRole() on null"** — confirmed live again, this time at the last step of an update (`ErrorMonitor::canAccess()`). The v1.0.9 sweep only grepped for the one-liner `auth('admin')->user()->hasRole(...)` shape and missed the equally common two-line variant used by `Filament\Clusters\System`, `ErrorMonitor`, `ServerMonitor`, `QueueMonitor`, and `HelpPage`. All 5 fixed the same way; the regression test no longer hand-lists classes to check — it now scans `app/Filament/` and calls every declared `canAccess()` as a guest automatically, so this class of bug can't quietly reappear via a hand-maintained list again.
- **"Update available" banner text was illegible in dark mode** — it paired a fixed, pale Filament shade variable as background with a semantic text-color token that flips to near-white in dark mode, so light mode looked fine while dark mode rendered the banner (and its "View details" button) essentially invisible. Replaced with explicit Tailwind `dark:` variants matching the pattern already used elsewhere in the codebase.
- **Storefront maintenance page depended on an external CDN Tailwind script with an incomplete local color config** — missing a class used in three places (timestamp, footer strip, copyright line), so those rendered with none of the intended styling; and depending on a CDN load is a poor fit for the one page guaranteed to render during infrastructure trouble anyway. Rewritten to use the same self-hosted `@vite` build as every other page, with a manual "Refresh page" button added alongside the existing passive copy.

### Performance
- **Full/Database backups felt slow on even a small site** — the resumable backup FSM advances one chunk per poll tick by design (shared-hosting safety), but "one chunk" meant exactly one database table-unit and one encrypted part per step regardless of how little work that actually was — tens of poll cycles (each with a floor delay) for a site with 60+ small tables. The database and encryption stages now batch as many whole units as fit within a wall-clock budget (5 seconds each, configurable) before yielding, bounded by time rather than a fixed count so slower hosts and larger tables/parts are still safe.

## 1.0.10 — 2026-07-19

### Fixed
- **The update-preview "I've reviewed the warnings" checkbox never enabled "Confirm & apply"** — the checkbox used a plain `wire:model` (no `.live`), which only syncs to the server on the next Livewire round-trip, while the Confirm button's disabled state is computed server-side from that same value. Checking the box updated the visible checkbox but never triggered a re-render, so on any release with a pre-flight WARNING (e.g. no release signature key configured, or the Recovery Console disarmed — both common on a fresh install with no extra setup) the button stayed permanently disabled with no way to proceed. Confirmed live via a real apply attempt. Changed to `wire:model.live` so checking the box immediately re-evaluates the button.

## 1.0.9 — 2026-07-19

### Fixed
- **A real 500 ("Call to a member function hasRole() on null") could hit any admin page** — 13 `canAccess()` checks across 3 navigation clusters (Settings, Reports, Content) and 10 pages (Cache Dashboard, Health Check, Failed Jobs, Log Viewer, Server Monitor's Scheduled Tasks page, Permission Matrix, Setup Assistant, Content Revisions, Inventory Log, Bulk Update Log) called `auth('admin')->user()->hasRole(...)`/`->hasAnyRole(...)` without a null-safe operator, unlike the rest of the codebase's established `auth('admin')->user()?->hasRole(...) ?? false` pattern. Whenever `auth('admin')->user()` is null while Filament resolves navigation (confirmed live: immediately after applying an in-app update, likely while the session hadn't fully rehydrated post framework-cache-rebuild), this crashed instead of just hiding the nav item. All 13 now use the null-safe pattern, and a new sweep test (`AdminCanAccessNullSafetyTest`) calls every one of them as a guest to make sure this can't quietly regress.

## 1.0.8 — 2026-07-19

### Fixed
- **A multi-step upgrade applied the latest release directly instead of the required intermediate hop** — when an install was more than one release behind and an intermediate release required stepping through in order (e.g. installed 1.0.5, latest 1.0.7, with 1.0.7 only installable from 1.0.6), clicking "Apply update now" always built its manifest from the *latest* release's own fields, ignoring the resolved upgrade path entirely. This applied the final release's zip directly, would have skipped any intermediate hop's migrations, and — since that manifest never carried the intermediate hop's own `min_version_to_update_from` — silently defeated the pre-flight version-compatibility check meant to block exactly this. Confirmed live on a real two-versions-behind install: pre-flight passed and the jump proceeded with no warning shown. `UpdateChecker` now resolves and exposes the correct next release to apply (the first hop in the upgrade path, not the latest), and the apply flow uses it.

## 1.0.7 — 2026-07-19

### Fixed
- **Full Backup could error out with no progress shown** — the file-backup stage backed up (and encrypted) the entire `vendor/` tree by design, which could crash/time out on large installs and gave zero percentage feedback while running. `vendor/` is now excluded from file backups by default (`composer install --no-dev` reproduces it on restore; set `OE_BACKUP_INCLUDE_VENDOR=true` to opt back into the old fully self-contained behaviour), `BackupManager::finalize()` is now wrapped so a late failure surfaces a real error instead of a bare crash, and every backup stage now reports a completion fraction so the dashboard shows a real progress bar and percentage instead of a bare spinner.
- **"Clear Cache" on the Health Check and Cache dashboards did a full `cache:clear` flush** — a full flush can take out sessions sharing the same Redis store, contrary to this project's own "never broad-flush the cache" rule. Both actions now do a targeted, key-scoped purge instead.

### Added
- **Backup type selection** — "Run backup now" now lets an admin choose Database Only, Files Only, or Full (database + files), instead of always running a full backup.
- **Update apply preview** — the "Apply update now" confirm step now shows the actual version jump, download size, migration count, breaking changes, ETA, and every pre-flight check (with any warnings requiring explicit acknowledgement) before an update starts, instead of a generic browser confirm dialog. The in-progress apply view also now shows a step counter and progress bar.
- **SEO: Sitemap regenerate button** — SEO & Meta settings now has a "Regenerate sitemap now" action and shows when the sitemap was last built, instead of only ever running via the daily scheduled job.
- **SEO: 404 monitor** — frontend 404s are now logged (deduplicated by path, with hit counts) and browsable from the admin, with a one-click "Create redirect" action that resolves the log entry.

### Changed
- **Settings pages save immediately** — replaced the "Save Actions" dropdown → "Preview Changes" → "Confirm Save" three-click flow with a single, direct Save button across every settings page. The change is still recorded in the settings activity log entries written to the main Activity Log; "Reset to Defaults" (a bulk overwrite) still asks for confirmation first.
- Removed the standalone "Settings Activity Log" page from the Settings grid (redundant with the main Activity Log).

## 1.0.6 — 2026-07-19

### Fixed
- **Reports → Sales/Search pages 500'd on every real MySQL install ("clicking Reports shows an error")** — confirmed live: `SalesTopProducts`, `SearchFailedQueries`, and `SearchTopSearches` (the "Top Selling Products" / "Failed Queries" / "Top Searches" report tables) all `GROUP BY` a set of columns while only `SELECT`ing an aggregated `MIN(id)`/`COALESCE(...)` alias — but Filament's `TableWidget` appends `ORDER BY {table}.id` for pagination-stable sorting unless told not to (`Table::hasDefaultKeySort()`, default enabled), and that appended clause references the *raw*, non-aggregated `id` column, which isn't functionally dependent on the `GROUP BY`. Under MySQL's default `sql_mode=only_full_group_by` this is a hard SQL syntax error (`SQLSTATE[42000]`), producing a 500 on every visit to the Sales Report page and the Search Intelligence report's two widgets — 100% reproducible on any standard MySQL/MariaDB install, every time. The test suite runs on SQLite (`.env.testing`), which does not enforce `ONLY_FULL_GROUP_BY`, so this shipped completely undetected by `php artisan test`; it only surfaced live, in a real browser against real MySQL. Added `->defaultKeySort(false)` to all three widgets (each already has its own explicit, correct `orderByDesc()`) and a new regression test that checks the actual mechanism (`hasDefaultKeySort()`) rather than relying on a specific database engine's strictness to catch it. `CustomersTop`, the fourth "Top" widget with the same shape, was verified safe as-is — its `GROUP BY` already includes the real `users.id`, so Filament's appended order-by was never invalid there.

## 1.0.5 — 2026-07-19

### Fixed
- **A stalled/corrupt download could permanently block every future update attempt with "HTTP 416"** — `UpdateDownloader::fetch()`'s resume logic only reset the on-disk file when it was *larger* than the expected size (`$from > $size`); a file that ended up sitting at *exactly* the expected size but with the wrong content (`isComplete()`'s sha256 check rejects it — e.g. from a byte-shifted resume, or a race between two concurrent apply attempts) fell through untouched, so every retry — and every future apply attempt, since the file persists on disk across FSM runs — requested `Range: bytes={size}-`, a range past end-of-file that any server correctly rejects with 416. Confirmed live: two separate real update attempts against GitHub's release CDN failed with the identical "Download failed after 4 attempt(s): Download server returned HTTP 416," with no way to recover short of manually finding and deleting the file. Changed the guard to `$from >= $size` so a full-size-but-invalid file is deleted and retried with a clean, no-Range request instead of looping the same invalid range forever.

## 1.0.4 — 2026-07-19

### Fixed
- **A backup abandoned mid-progress could block every future backup AND update indefinitely** — `BackupJanitor::cleanupPartials()` exists precisely to reclaim a run left `running` forever (e.g. an admin navigates away while "Run backup now" is still AJAX-polling, see v1.0.1) and release the shared lock it holds, but nothing ever called it: `BackupRetentionService` explicitly does not ("Failed/partial runs are the BackupJanitor's job, not retention's"), and `BackupDashboard`'s only use of `BackupJanitor` is the unrelated delete action's `purgeFiles()`. A stuck lock made `PreflightService`'s lock check fail for every subsequent update attempt too, surfacing as "Pre-flight failed: A backup or update is already in progress" with no way to resolve it from the UI. Added `oeparts:backup:cleanup-stale` (wraps `cleanupPartials()`) and scheduled it hourly, matching `config('backup.stale_after_seconds')`'s default 1-hour staleness threshold — a stuck lock now self-heals within the hour instead of requiring manual database/filesystem intervention.

## 1.0.3 — 2026-07-19

### Fixed
- **System Updates page looked download-only — the one-click "Apply update now" button was easy to miss** — the page header still read "One-click updates are coming soon — for now, follow the release notes," stale copy left over from before the one-click apply FSM (Chunk 3.5) shipped, even though `startApply()`/`pollApply()` have worked since. The prominent "Download release" button also rendered above the "Apply update now" section, reinforcing the impression that downloading was the only option. Fixed the header copy to describe the actual one-click flow, moved "Apply update now" to render first (the primary, most-visible action), and demoted "Download release" to an outlined secondary link labelled "Download release (manual install)" for admins without `apply updates` permission or who prefer a manual install.

## 1.0.2 — 2026-07-19

### Fixed
- **Settings pages had no way back except a small breadcrumb link** — every one of the 34 pages under the Settings cluster (34 concrete `SettingsPage` subclasses + the Activity Log table page) had only Save/Discard/Reset-to-Defaults actions in the form footer; the only path back to the Settings overview was a single-word cluster breadcrumb above the header, easy to miss. `SettingsPage::getHeaderActions()` now adds an explicit "Back to Settings" header action (linking to the cluster index) inherited by every subclass; the 3 pages that already had their own header actions for page-specific tools (`EmailSettings`' Send Test Email, `PaymentSettings`' Test Connection, `PerformanceSettings`' Test Cache) now merge it in via `...parent::getHeaderActions()` instead of overriding it away. `SettingsActivityLog` (which extends `Filament\Pages\Page` directly, not `SettingsPage`, since it's a table page) gets its own copy of the same action for consistency.

## 1.0.1 — 2026-07-19

### Fixed
- **Backup Management — "Run backup now" no longer hangs on "Running"** — the button dispatched `RunBackupJob`, which runs the entire backup (full profile, including `vendor/` by design) synchronously via `Artisan::call('oeparts:backup')`. Under `QUEUE_CONNECTION=sync` (the documented shared-hosting setup with no supervisor — README, CLAUDE.md rule #41) this ran the whole backup inline within the web request, blocking well past the web server's/PHP's timeout and leaving the run stuck at `running` with no progress, exactly as the Backup Engine's own chunked design (rule #48) was built to avoid. `BackupDashboard::runNow` now only calls `BackupManager::start()` (fast) and hands off to a new `pollBackup()` method, AJAX-polled one chunk per tick — the same pattern already used by the Update Engine's apply flow (`SystemUpdates::pollApply`). Also resumes polling automatically if the admin reloads the page mid-backup. `RunBackupJob`/`oeparts:backup` are unchanged and still correct for the scheduled cron backup, which has no web-request timeout to respect.

## 1.0.0 — 2026-07-19

Initial public release.

### Added
- Full Laravel 12 B2B/B2C e-commerce platform for genuine OEM auto parts — search-first, no product images, Industrial Blueprint design system
- 55 custom database tables + 8 framework tables
- OEM search engine with normalization, cross-references, and autocomplete
- 20 core modules (search, catalog, orders, payments, shipping, CMS, SEO, etc.)
- FilamentPHP 5.6.7 admin panel with 35+ resources, 4 clusters, 19 settings pages
- 5-language support (EN, DE, LT, FR, ES)
- 12 mail classes, 15 queue jobs, 25 email templates (HTML + plain text)
- Industrial Blueprint storefront design with 14 homepage sections
- Airwallex payment integration (card + bank transfer)
- Web installer wizard (6-step) + demo data seeder
- **OrderService** — centralized order lifecycle management with status transition validation, invoice number generation, and payment handling
- **ShippingService** — EU shipping zone detection, country-based method selection, free shipping threshold calculation, delivery estimation
- **HealthCheckService** — comprehensive system health checks (database, cache, queue, storage, scheduler, assets), surfaced via a `/health` endpoint and an admin dashboard
- **In-App Update & Recovery System** — a one-click, pure-PHP update mechanism built for shared hosting: automatic release detection with in-panel notifications, a 12-point safety pre-flight gate, chunked/resumable and mandatorily-encrypted (AES-256-GCM) backups with off-site (S3/SFTP) support and GFS retention, atomic file-swap updates with post-update verification and automatic rollback on failure, and a framework-independent Recovery Console for disaster recovery when the app itself can't boot
- Cryptographically signed releases (RSA-SHA256) with SHA-256 checksum verification
- GitHub Actions CI/CD: automated test suite (PHP 8.2/8.3/8.4 matrix) + tag-triggered release build/publish pipeline
- Branding system (colors/logo configurable from admin settings)
- CLAUDE.md — comprehensive AI coding rules documenting all critical patterns (bcmath, auth guards, cache rules, SEO, OTP, VIES)
- GitHub-quality documentation (README, SECURITY.md, CODE_OF_CONDUCT.md)

### Fixed
- JSON-LD structured data rendering under Laravel 12's new `@context` Blade directive (escaped to `@@context` across 9 storefront views)
- Installer, admin font, and legacy gradient-CSS cleanups
