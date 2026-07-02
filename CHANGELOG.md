# Changelog — OeParts

All notable changes to this project are documented here.

## Unreleased

### Added
- **Update post-swap boot** (Update System **Chunk 3.4**) — `UpdateFinalizer` runs the config-driven finalize plan on a fresh request after the swap: `migrate --force` (critical), package discovery / asset republish / storage link, idempotent seeders, a config/route/view/event cache rebuild (never `Cache::flush`), and a conditional `queue:restart`. A migration failure aborts (for rollback); other steps are best-effort. 8 new tests.
- **Update pre-update backup + atomic swap** (Update System **Chunk 3.3**) — `UpdateSwapper` replaces the live core with the extracted release via atomic per-directory renames (`root/D → backup/D`, `staging/D → root/D`), writing a `last-swap.json` rollback map for crash recovery and preserving `.env`/`storage/`. A `rollback()` reverses it, and OPcache is reset after the swap (migrations then run on a fresh request). The pre-update safety backup is a normal `update_safety` Backup Engine run. 4 new tests.
- **Update download + staging extract** (Update System **Chunk 3.2**) — `UpdateDownloader` streams the release zip resumably (HTTP Range), retries with backoff, and verifies its SHA-256 (deleting a corrupt file); `UpdateExtractor` unpacks it into a staging directory with a zip-slip guard on every entry and a free-disk re-check. Neither touches the live app. 7 new tests.
- **Update pre-flight gate** (Update System **Chunk 3.1**, Phase 3 begins) — `PreflightService` runs 12 pure-detection safety checks before any update applies (lock, version path, PHP/extensions, DB version, disk space, writability, OPcache reset, git/symlink deployment, multi-server, new env keys, schema drift). A FAIL blocks the update; a WARN requires acknowledgement. 10 new tests.
- **Backup Manager, retention & scheduling** (Update System **Chunk 2.6** — completes the Backup Engine) — a Filament "Backup Management" page listing backup runs with Run-now, Restore, Download (re-auth'd + audited PII exports) and Delete actions; GFS retention pruning (7 daily / 4 weekly / 6 monthly); a scheduled `oeparts:backup` command (supersedes the old `db:backup`) with super_admin failure alerts; and a "last backup age" health check. 13 new tests.
- **Backup restore engine** (Update System **Chunk 2.5**) — `RestoreManager` reassembles, verifies, and decrypts a backup: layered integrity checks (ciphertext → plaintext → per-file sha256), DB restore in creation order with FK checks disabled, file restore via per-volume decryption + segment extraction, and partial restores (DB-only / files-only / single-table). Cross-server restore rebuilds the run from the unencrypted `manifest.json` TOC (`importManifest`), and `app_version` skew is warned/guarded. 6 new tests.
- **Backup encryption + destinations** (Update System **Chunk 2.4**) — `BackupCipher` (streamed, framed AES-256-GCM keyed on the dedicated `OE_BACKUP_KEY`; mandatory — GDPR) and `EncryptTransportStage` (final pipeline stage). Every backup part is encrypted and shipped to its destination: kept locally, or streamed off-site (S3-EU / SFTP) with the local copy deleted (never staging a whole backup off-site). Introduces local-staging vs destination-disk separation (`config('backup.staging_disk')`), preserves plaintext checksums for post-restore verification, warns on non-EU S3 regions, and decrypts encrypted baselines for incremental diffs. 7 new tests.
- **File chunked backup stage** (Update System **Chunk 2.3**) — `FileBackupStage` (second concrete `BackupStage`, `full` profile only). Packs files into ≤512 MB gzip volumes (each file body block-streamed for flat memory), records per-file volume/segment offsets for single-file restore, and emits a `files-manifest.json.gz` hash baseline. Supports incremental backups (size+mtime diff vs the previous full: unchanged/changed/deleted), config-driven exclusions, and a per-file throttle. `update_safety` backups skip files by design. 7 new tests.
- **DB chunked backup stage** (Update System **Chunk 2.2**) — `DatabaseBackupStage`, the first concrete `BackupStage`: a pure-PHP, shared-hosting-safe database dump (replaces the old `mysqldump`/`exec` command). Resumable one-part-per-step — a table's schema, then keyset-PK-paged gzip data chunks (`OFFSET` fallback for composite/no-PK tables); structure-only for excluded log/session/cache/job tables; optional consistent-snapshot flag. Driver-aware `CREATE TABLE` (MySQL / SQLite) with portable backtick + PDO-quoted `INSERT`s. Registered in `config('backup.stages')` for both profiles. 6 new tests.
- **Backup Engine core** (Update System **Chunk 2.1**) — the chunked, resumable backup FSM: `BackupManager` (start → advance-one-chunk-per-poll → finalize/fail, checkpointed in `backup_runs.meta` for crash resume), `BackupStage` contract + `StageRegistry` (pluggable `db`/`files`/`env` stages register via `config('backup.stages')`), `BackupLock` (O_EXCL filesystem lock shared with the Update Engine), `BackupManifest` (self-describing part index + SHA-256), and `BackupJanitor` (reclaims failed/stale partials, releases stale locks). `BackupRun`/`BackupPart` Eloquent models. Concrete DB/file/encryption stages land in Chunks 2.2–2.4. 10 new tests.

### Changed
- **Framework upgrade** — Laravel 11.52 → 12.62, FilamentPHP 5.6.5 → 5.6.7 (Update System **Chunk 0.0**). Clean dependency resolution; the upgrade also cleared the 21 prior Composer security advisories. Full test suite: 530 passed / 0 failed.

### Fixed
- **JSON-LD rendering under Laravel 12** — escaped `@context` → `@@context` across 9 storefront views (home, blog index/show, car-model index/show, manufacturer show, search results/zero-results, faqs section). Laravel 12's new core `@context` Blade directive was hijacking JSON-LD `"@context"` keys, emitting an unclosed `@if` and causing a **500 on every page carrying structured data**. See CLAUDE.md rule #40.

## 1.0.2 — 2026-06-03

### Fixed
- **Installer button classes** — Refactored all 6 installer views to use inline Tailwind utilities instead of legacy gradient `btn-primary`/`btn-outline` classes, removing dependency on dead CSS
- **Admin font alignment** — `filament/admin/theme.css` now imports `@fontsource/geist-sans` instead of `@fontsource/inter`, unifying typography across storefront and admin panel
- **CLAUDE.md typography** — Updated to accurately document Geist Sans as the primary body font for both storefront and admin

### Cleaned Up
- **Removed 113 lines of dead gradient CSS** from `app.css` — deleted legacy `.btn-*`, `.icon-wrapper-*`, `.card-shadow-*`, `.card-radius*` classes (unused by any storefront view)
- **Deleted unused Blade components** — `components/icon-wrapper.blade.php` and `components/ui/button.blade.php` (zero usages in codebase)
- **Removed `@fontsource/inter` dependency** from `package.json` (no longer imported anywhere)
- **Replaced 132 inline tracking values** across 40 Blade files with `bp-spec-mono` utility class, eliminating maintenance risk from hardcoded `text-[10px] tracking-[0.22em]` patterns

---

## 1.0.1 — 2026-06-03

### Added
- **OrderService** — centralized order lifecycle management with status transition validation, invoice number generation, and payment handling
- **ShippingService** — EU shipping zone detection, country-based method selection, free shipping threshold calculation, delivery estimation
- **HealthCheckService** — comprehensive system health checks (database, cache, queue, storage, scheduler, assets)
- **HealthCheckDashboard** — FilamentPHP admin page under System cluster for viewing real-time system health status
- **CLAUDE.md** — comprehensive AI coding rules documenting all critical patterns (bcmath, auth guards, cache rules, SEO, OTP, VIES)
- **CHANGELOG.md** — this file

### Fixed
- **Font alignment** — `app.css` now correctly imports `@fontsource/geist-sans` instead of `@fontsource/inter`, aligning with Tailwind config font stack
- **Section badge gradients** — replaced `backdrop-blur-sm` and gradient fills in `.section-badge` and `.section-accent-bar-main` with flat Industrial Blueprint colors

### Cleaned Up
- Removed unused `components/button.blade.php` (old gradient button system) — only `bp-btn-*` system is used
- Removed unused `HealthCheckResource` — replaced with proper `HealthCheckDashboard` Page

## 1.0.0 — Initial Release

- Full Laravel 11 e-commerce platform for OEM auto parts
- 55 custom database tables + 8 framework tables
- OEM search engine with normalization, cross-references, and autocomplete
- 20 core modules (search, catalog, orders, payments, shipping, CMS, SEO, etc.)
- FilamentPHP admin panel with 35+ resources, 4 clusters, 19 settings pages
- 5-language support (EN, DE, LT, FR, ES)
- 12 mail classes, 15 queue jobs, 25 email templates (HTML + plain text)
- Industrial Blueprint storefront design with 14 homepage sections
- Airwallex payment integration (card + bank transfer)
- Web installer wizard (6-step)
- Demo data seeder
- GitHub Actions CI/CD workflows