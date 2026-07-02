# Changelog — OeParts

All notable changes to this project are documented here.

## Unreleased

### Added
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