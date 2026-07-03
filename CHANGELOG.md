# Changelog — OeParts

All notable changes to this project are documented here.

## Unreleased

### Added
- **Update System end-to-end CI** (Update System **Chunk 5.3** — completes Phase 5 and the V1 Update & Recovery System) — a capstone end-to-end test builds a real release zip, serves it through a faked endpoint, and drives the real download → extract → swap → rollback pipeline, then runs the whole apply state machine with a real pre-update backup and a forced post-swap failure to prove the failure/rollback matrix reverses both files and the database. The CI test matrix now covers PHP 8.2/8.3/8.4 with a fresh-migration check. 2 new tests. **This ships the complete WordPress-class in-app Update & Recovery System: update checker, backup engine, one-click update engine, app-independent recovery console, and the release/CI pipeline.**
- **Release manifest generation + GitHub Release automation** (Update System **Chunk 5.2**) — the `oeparts:release:manifest` command (backed by `ReleasePublisher`) folds a built zip's SHA-256, size, and versioned download URL into `version.json` and upserts the `releases.json` catalog the in-app updater reads (newest-first, `latest` tracked, idempotent per version). A tag-triggered GitHub Actions workflow builds the zip, generates the manifests, publishes them to `main`, and attaches the zip plus both JSON files to a GitHub Release — replacing the earlier ad-hoc in-place zip. 6 new tests.
- **Release build pipeline** (Update System **Chunk 5.1**, Phase 5 begins) — a local/CI-only build that produces the distributable release zip: `build/build.sh` exports a clean tree, installs production dependencies, builds front-end assets, then the new `oeparts:build` command (backed by `ReleaseBuilder`) strips dev/secret/internal files, bundles every third-party license into `THIRD-PARTY-LICENSES.md`, and writes a per-file SHA-256 manifest (`file-manifest.json`) for modified-core detection and future delta updates — before zipping and checksumming. The command refuses to run against the live project root. 7 new tests.
- **Recovery Console — security hardening** (Update System **Chunk 4.3** — completes the Recovery Console) — the app-independent console gains a per-IP rate limiter (lockout after repeated failed attempts, checked before authentication), a structured audit log (one JSON line per access and action), single-use IP-bound confirm tokens that the action forms carry instead of the recovery key (the secret never appears in the page), POST-only key handling, and an explicit "disarm" action to close the recovery window. The update pre-flight now warns when `OE_RECOVERY_KEY` is unset. 7 new tests.
- **Recovery Console — recovery actions** (Update System **Chunk 4.2**) — the app-independent console gains four framework-free, POST-dispatched recovery actions: roll back an interrupted file swap (reverse the directory renames from `last-swap.json`), restore the database from the latest pre-update backup (read the manifest TOC, decrypt each part with a ported AES-256-GCM reader, gunzip, and apply schema-then-data with foreign-key checks off), force maintenance mode off (raw settings write + best-effort single-key cache purge), and reset OPcache. The DB restore handles local backup disks and proves byte-for-byte cipher parity with the Backup Engine. 8 new tests.
- **Recovery Console — arm-flag lifecycle & status view** (Update System **Chunk 4.1**, Phase 4 begins) — `public/oe-recovery.php`, a standalone, framework-free console (raw PDO + filesystem + its own `.env` parser, never composer-autoloaded) for when the upgraded app can no longer boot. `App\Services\Updates\RecoveryArm` manages the `arm.flag` window (armed by `UpdateApplier` on start; auto-disarmed on success and on a completed rollback; left armed on a hard failure). The console reads the swap-rollback map, update history, and restorable backups, gated opt-in-armed behind a constant-time `OE_RECOVERY_KEY` check and an optional IP allowlist. Read-only status in this chunk; destructive recovery actions are Chunk 4.2. 12 new tests.
- **Update verification, auto-rollback & history** (Update System **Chunk 3.6** — completes the Update Engine) — `PostUpdateVerifier` runs after an update (schema assertions, referential-integrity spot-checks, and a DB/table-readable smoke test); any failure auto-rolls back the update (reverse swap + database restore). Adds an audited `update_histories` trail and a read-only "Update History" admin page.
- **One-click update apply** (Update System **Chunk 3.5**) — `UpdateApplier`, the resumable, poll-driven apply FSM that wires pre-flight → pre-update backup → download → extract → swap → post-swap boot into one flow, with the failure/rollback matrix (a post-swap failure reverses the swap and restores the database). The System Updates page gains a password-re-authed "Apply update now" action with a live progress stepper that hard-reloads on success, a confirm preview (version jump, size, migration count, ETA, pre-flight report), and resume-on-reload. 9 new tests.
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