# Changelog — OeParts

All notable changes to this project are documented here.

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
- **System Updates page looked download-only — the one-click "Apply update now" button was easy to miss** — the page header still read "One-click updates are coming soon — for now, follow the release notes," stale copy left over from before the one-click apply FSM (Chunk 3.5) shipped, even though `startApply()`/`pollApply()` have worked since. The prominent "Download release" button also rendered above the "Apply update now" section, reinforcing the impression that downloading was the only option. Fixed the header copy to describe the actual one-click flow, moved "Apply update now" to render first (the primary action, matching a familiar one-click update UX), and demoted "Download release" to an outlined secondary link labelled "Download release (manual install)" for admins without `apply updates` permission or who prefer a manual install.

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
