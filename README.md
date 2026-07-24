<h1 align="center">OeParts</h1>

<p align="center">
  Open-source Laravel e-commerce platform for genuine OEM auto parts in Europe.
  <br>
  Search-first · B2B/B2C · 5 languages · Self-hosted
</p>

<p align="center">
  <a href="https://github.com/shemondotme/oeparts/actions/workflows/tests.yml">
    <img src="https://github.com/shemondotme/oeparts/actions/workflows/tests.yml/badge.svg" alt="Tests">
  </a>
  <a href="https://github.com/shemondotme/oeparts/releases/latest">
    <img src="https://img.shields.io/github/v/release/shemondotme/oeparts" alt="Latest Release">
  </a>
  <a href="https://github.com/shemondotme/oeparts/blob/main/LICENSE">
    <img src="https://img.shields.io/badge/license-MIT-green" alt="MIT License">
  </a>
  <img src="https://img.shields.io/badge/PHP-8.3%2B-blue" alt="PHP 8.3+">
  <img src="https://img.shields.io/badge/Laravel-12-red" alt="Laravel 12">
  <a href=".github/CONTRIBUTING.md">
    <img src="https://img.shields.io/badge/PRs-welcome-brightgreen" alt="PRs Welcome">
  </a>
</p>

---

## Table of Contents

- [What is OeParts?](#what-is-oeparts)
- [Features](#features)
- [Requirements](#requirements)
- [Quick Start](#quick-start)
- [Local Development](#local-development)
- [Configuration](#configuration)
- [In-App Update & Recovery System](#in-app-update--recovery-system)
- [Tech Stack](#tech-stack)
- [Architecture](#architecture)
- [Testing & CI](#testing--ci)
- [Security](#security)
- [Contributing](#contributing)
- [Community](#community)
- [License](#license)

---

## What is OeParts?

OeParts is a production-ready e-commerce platform built specifically for **genuine OEM auto parts** dealers in Europe. It is designed around the reality of the parts business: customers search by OEM number, not by category — so the search engine is the product.

**Key design decisions:**
- OEM numbers are normalized before indexing and searching (strips dashes/spaces, uppercases) so `1K0-407-271-E` and `1K0407271E` are the same part
- No product images — OEM parts are identified by number, not photo
- Binary stock status (`is_in_stock`) — no quantity tracking
- B2B VAT exemption via live VIES SOAP validation
- All money in `bcmath` — no float arithmetic anywhere
- Ships its own update, backup, and disaster-recovery system, built for shared hosting — no CLI, SSH, or cron worker required to stay current

---

## Features

| Module | Details |
|---|---|
| **OEM Search** | Normalization, cross-reference matching, autocomplete, zero-results inquiry |
| **Catalog** | Products, manufacturers, car model fitment, bulk price/stock update |
| **Checkout** | 5-step, session-based, guest OTP, B2B VAT exempt |
| **Payments** | Airwallex (card + bank transfer), HMAC webhook, idempotency |
| **Orders** | Status history, tracking, refunds, PDF invoices |
| **Customer Account** | Dashboard, order history, saved addresses, settings |
| **Admin Panel** | 4 roles, 26-widget dashboard, full CRUD for all entities |
| **CMS** | Pages, blog, sections, menus, FAQs, testimonials, media |
| **SEO** | hreflang (5 langs), JSON-LD, sitemap, canonical, robots.txt |
| **Email** | 11 transactional templates, queued, logged, multilang |
| **Multilang** | EN, DE, LT, FR, ES — auto-detect from browser |
| **Health** | Public `/health` endpoint + admin health dashboard |
| **Updates & Backups** | One-click updater, encrypted backups, app-independent recovery console — see [below](#in-app-update--recovery-system) |
| **Installer** | Web-based 6-step wizard, demo data seeder |

---

## Requirements

| Dependency | Minimum |
|---|---|
| PHP | 8.3 |
| MySQL | 8.0.16 (or MariaDB 10.11+) |
| Redis | 6.0 (production; not required for local dev — see below) |
| Web server | Nginx, Apache, or any PHP-capable host (shared hosting supported) |
| Node.js | 18 (local build only — never required on the production server) |

Required PHP extensions: `pdo_mysql`, `zip`, `openssl`, `mbstring`, `curl`, `fileinfo`, `json`, `bcmath`.

---

## Quick Start

### 1. Clone and install

```bash
git clone https://github.com/shemondotme/oeparts.git
cd oeparts
composer install
cp .env.example .env
php artisan key:generate
```

### 2. Configure `.env`

At minimum, set your database credentials:

```env
DB_HOST=127.0.0.1
DB_DATABASE=oeparts
DB_USERNAME=root
DB_PASSWORD=

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

MAIL_MAILER=smtp
MAIL_HOST=...
```

See [Configuration](#configuration) below for the full picture, including which variables are mandatory before your first backup or update.

### 3. Run the web installer

```
http://your-domain.com/install
```

> **Shared hosting, no SSH/CLI access?** Skip steps 1–2 entirely. Download the packaged
> release asset (`oeparts-<version>.zip` on the [Releases page](https://github.com/shemondotme/oeparts/releases) —
> **not** GitHub's "Code > Download ZIP", which is source-only and has no `vendor/`),
> extract it, point your document root at `public/`, and visit `/install` directly.
> The `vendor/` dependencies are already bundled in that asset, and the entrypoint
> auto-creates `.env` with a fresh random `APP_KEY` on first request if one doesn't
> exist yet — so the installer's first screen renders with zero manual setup.

A 6-step wizard: requirements check → database (test the connection, optionally create the
database itself if your user has that privilege) → site settings (timezone/language
pre-filled from your server/browser) → admin account → email (with a "send test email"
button, so a typo'd SMTP password shows up now, not on your first real order) → install.

The install step itself runs as a chunked, AJAX-polled progress bar, not one long request —
each migration/seeder/setup step is a separate small HTTP call, so it can't be killed by a
host's `max_execution_time` partway through. It:
- Runs all migrations (125+ and counting — schema changes are append-only, see [Architecture](#architecture))
- Seeds settings, languages, roles, carriers, and sections
- Creates your admin account and assigns the `super_admin` role
- Persists your site settings and writes mail settings to `.env`
- Optionally loads demo catalog data
- Writes `storage/installed.lock` and logs the whole run to `storage/logs/install-<date>.log`

The installer refuses to run `migrate:fresh` a second time if the database already has a
completed install (an `admins` table with rows) — even if `storage/installed.lock` is
deleted — so it can't be used to accidentally wipe a live site.

### 4. Start queue workers

```bash
php artisan queue:work redis --queue=critical,default,low
```

In production, run this under a process supervisor so it auto-restarts on crash instead of silently stopping all queue processing (order confirmation emails go out through this queue). A Supervisor config template is provided in `deploy/supervisor/oeparts-queue-worker.conf`:

```bash
cp deploy/supervisor/oeparts-queue-worker.conf /etc/supervisor/conf.d/
# edit the file and replace /path/to/oeparts with your real deployment path
supervisorctl reread
supervisorctl update
supervisorctl start oeparts-queue-worker:*
```

> **On shared hosting without a supervisor?** Set `QUEUE_CONNECTION=sync` instead — emails and jobs run inline. Slower per-request, but requires zero background process management.

### 5. Schedule the cron

Add to crontab — this drives the sitemap refresh, scheduled backups, and abandoned-cart emails:

```cron
* * * * * cd /path/to/oeparts && php artisan schedule:run >> /dev/null 2>&1
```

---

## Local Development

### Docker (recommended for contributors)

```bash
cp .env.docker.example .env
docker compose up -d
docker compose exec laravel.test composer install
docker compose exec laravel.test php artisan migrate --seed
```

Opening this repo in VS Code and choosing "Reopen in Container" sets everything up automatically via `.devcontainer/devcontainer.json` — no manual steps at all. The dev container matches the PHP 8.3/8.4/8.5 support matrix and simulates shared-hosting constraints (no Redis, no long-running processes) so what passes locally matches what a real self-hoster gets.

### XAMPP / local PHP (alternative)

```bash
# Install JS deps and build assets (once)
npm install
npm run build

# Run with XAMPP/local PHP (no Redis required)
# Set in .env:
CACHE_STORE=array
SESSION_DRIVER=file
QUEUE_CONNECTION=sync

# Run tests
php artisan test

# Seed fresh demo data
php artisan demo:setup --fresh --seed --yes
```

---

## Configuration

Most runtime behavior (VAT rates, OTP length, rate limits, search thresholds) lives in the **`settings` database table**, editable from the admin panel — never hardcoded, and never something you need to redeploy to change.

`.env` is reserved for environment-level concerns: credentials, drivers, and a few security keys that must exist *before* the app can safely run certain features.

| Variable | Required for | Notes |
|---|---|---|
| `DB_*` | Everything | Standard Laravel database connection |
| `CACHE_STORE` / `SESSION_DRIVER` / `QUEUE_CONNECTION` | Everything | Default to `file`/`file`/`sync` — zero setup, works on any host. `redis` is a recommended performance upgrade once you have Redis available, not a requirement (see table below) |
| `MAIL_*` | Order/account emails | SMTP; all emails are queued, never sent inline |
| `AIRWALLEX_*` | Checkout | Card + bank transfer payments; HMAC-verified webhooks |
| `OE_BACKUP_KEY` | **Any backup** | AES-256-GCM key for encrypting backups (customer PII/GDPR) — backups **refuse to run** without it. Generate with `openssl rand -base64 32` and store it somewhere durable and separate from the database — losing it makes every existing backup unrecoverable |
| `OE_RECOVERY_KEY` | Recovery Console | Enables `public/oe-recovery.php`, the framework-free console used if an update leaves the app unable to boot. Unset = console fully disabled (404), not just unauthenticated |
| `OE_RELEASE_PUBLIC_KEY` | Signed update verification | Opt-in; once set, the updater cryptographically verifies release authenticity (RSA-SHA256), not just checksum |
| `AWS_*` | S3-backed storage/backups (optional) | Off-site backup disk must be an EU region for GDPR |

See [`.env.example`](.env.example) for the complete, annotated list, and [`SECURITY.md`](SECURITY.md#hardening-notes-for-self-hosters) for the reasoning behind the security-sensitive keys.

Notable `settings` groups (editable in-app, no redeploy): `tax`, `shipping`, `search`, `performance`, `seo`, `payment`, `stats_counter`, `social`, `contact`.

### Cache / session / queue driver options

No caching backend is required to install or run OeParts — the shipped defaults (`file`/`file`/`sync`) work on any PHP host with zero extra setup. Redis or Memcached are opt-in performance upgrades, not prerequisites; the admin dashboard's health strip tells you whether the one you picked is actually reachable, it never blocks the app from booting either way.

| Backend | `CACHE_STORE` | `SESSION_DRIVER` | `QUEUE_CONNECTION` | Needs |
|---|---|---|---|---|
| File (default) | ✅ | ✅ | — | Nothing — always available |
| Database | ✅ | ✅ | ✅ | Nothing — uses your existing DB connection |
| Sync (no queue worker) | — | — | ✅ | Nothing — jobs run inline in the request |
| Redis | ✅ | ✅ | ✅ | `redis` PHP extension, **or** set `REDIS_CLIENT=predis` to use the bundled pure-PHP client with no extension at all |
| Memcached | ✅ | ❌ | ❌ | `memcached` PHP extension — Laravel has no Memcached session or queue driver, so those two stay on another option regardless |

Host has Memcached but not Redis? Set `CACHE_STORE=memcached` and leave `SESSION_DRIVER`/`QUEUE_CONNECTION` on `file`/`sync` (or `database` if you'd rather not touch the filesystem) — Memcached can only ever cover the cache role.

---

## In-App Update & Recovery System

Most self-hosted platforms leave "how do I update this in production" as an exercise for the sysadmin. OeParts ships a one-click update system built for the reality of shared hosting: no SSH, no CLI, often no background worker.

- **Pre-flight safety checks** — PHP/extension/DB version, disk space, writability, schema drift — run before anything downloads, with a hard **FAIL** blocking the update
- **Automatic pre-update backup** — every update takes a full encrypted backup first; a failed update triggers an automatic rollback of both files and database
- **Resumable, chunked execution** — the whole apply flow is an AJAX-polled state machine, one small unit of work per request, so it survives `max_execution_time` limits and a closed browser tab
- **Signed releases (optional)** — RSA-SHA256 signature verification in addition to SHA-256 checksums, so a compromised mirror can't serve a tampered build
- **App-independent Recovery Console** (`public/oe-recovery.php`) — pure PDO + filesystem, no framework bootstrap — for the rare case where an update leaves the app unable to boot at all. Opt-in-armed only during an active update window, key-gated, rate-limited, and audit-logged
- **Mandatory encrypted backups** — AES-256-GCM, keyed separately from `APP_KEY`, because backups contain customer PII
- **Opt-in unattended security updates** — set `OE_UPDATE_AUTO_SECURITY=true` and a daily scheduled command auto-applies (and auto-rolls-back on failure) any release flagged `security`, using the exact same pre-flight-gated, backup-first FSM as the "Apply Update" button in the dashboard. Off by default; routine feature releases always still require a manual click. You're emailed the outcome either way — success or failure — never silent.
- **HTTP fallback for a missing cron** — if the real system cron (below) was never set up, `App\Http\Middleware\TriggerDueScheduledTasks` fires overdue scheduled tasks (backups, sitemap, update checks, …) from a normal page load instead, so nothing silently stops just because cron was never configured. Only kicks in once the scheduler heartbeat goes stale; a host with real cron configured never triggers it. Disable with `CRON_FALLBACK_ENABLED=false` if you'd rather it never run.

See [`SECURITY.md`](SECURITY.md) for the security properties this gives you as a self-hoster.

### Moving to a new server

The Backup Engine's restore path was built for this from the start — a backup's `manifest.json` (unencrypted, self-describing) lets a completely fresh install reconstruct which parts exist and restore them, with no database rows and no shared history with the old server required:

1. On the **old** server, take a full backup (scheduled, or `php artisan oeparts:backup`) and locate its files on the configured backup disk — you need the whole run's folder, including the unencrypted `manifest.json`.
2. Copy that folder to the **new** server (same relative path on whatever disk you'll restore from — `local` is simplest).
3. Deploy OeParts on the new server as usual (Quick Start above) up through creating `.env` — but **stop before running the web installer**, since restore recreates the schema itself.
4. Run: `php artisan oeparts:backup:restore --import-manifest=path/to/manifest.json --disk=local`. This recreates the `backup_runs`/`backup_parts` rows from the TOC, then restores the database and files. Add `--force` to skip the confirmation prompt for a scripted move.
5. Update `.env` on the new server: `APP_URL`, `DB_*` (if different from the old server), and `OE_BACKUP_KEY` must be the **same** key used to take the backup — restore refuses to decrypt with the wrong one.
6. Log into the admin panel and update the `site_url` / domain-related settings — a database restore brings the **old** domain with it; nothing rewrites URLs automatically.
7. Run `php artisan optimize:clear` and verify `/up` and `/health` report OK.

`--run=<id>` restores from a run already recorded in the *current* database (same-server disaster recovery, not migration); `--import-manifest` is specifically the cross-server path. Both share one command — see `php artisan oeparts:backup:restore --help`.

---

## Tech Stack

```
Backend:   Laravel 12, PHP 8.3+
Database:  MySQL 8.0.16+ (utf8mb4_unicode_ci)
Cache:     Redis 6+
Frontend:  Blade + Tailwind CSS + Alpine.js
Admin:     FilamentPHP 5.6.7 (light + dark mode)
Icons:     Heroicons v2
Payments:  Airwallex
Build:     Vite (local only — public/build/ is committed, server never runs Node)
Queue:     Redis — critical + default + low queues
PDF:       barryvdh/laravel-dompdf
Roles:     spatie/laravel-permission (Admin model only)
```

---

## Architecture

- **Controllers** are thin: validate → service → respond
- **Services** hold all business logic
- **bcmath only** for every money calculation — `bcscale(2)` set globally
- **Two auth guards**: `web` (customers) and `admin` (Admin model) — never mixed
- **Multilang JSON columns**: all translatable fields are `json` cast to `array`, rendered via `trans_field()`
- **OEM normalization**: always search/store via `OemNormalizerService::normalize()`
- **Append-only migrations**: schema changes are additive; destructive changes follow expand → migrate → contract across separate releases

See [`.github/CONTRIBUTING.md`](.github/CONTRIBUTING.md) for the full list of non-negotiable coding rules this codebase enforces.

---

## Testing & CI

```bash
php artisan test              # PHPUnit — full suite
npm run test:e2e              # Playwright — storefront/admin e2e (needs npm install)
```

GitHub Actions runs the full suite against a PHP 8.3/8.4/8.5 matrix with a fresh-migration check on every push and pull request — see [`.github/workflows/tests.yml`](.github/workflows/tests.yml).

---

## Security

- **Report vulnerabilities privately** — see [SECURITY.md](SECURITY.md) for the disclosure process. Do not open a public issue for security bugs.
- HMAC webhook verification on all payment callbacks, processed idempotently
- OTP brute-force protection (max attempts + cooldown, configurable via `settings`)
- IP blocklist middleware on all frontend routes
- Honeypot on every public form
- Mandatory-encrypted backups (AES-256-GCM, GDPR-driven — see [Configuration](#configuration))

---

## Contributing

Contributions are welcome — see [CONTRIBUTING.md](.github/CONTRIBUTING.md) for the development setup, non-negotiable coding rules, and PR process. Please also read our [Code of Conduct](CODE_OF_CONDUCT.md).

Good places to start:
- Check [open issues](https://github.com/shemondotme/oeparts/issues), especially any labeled `good first issue`
- Bug reports and feature requests use the templates under [`.github/ISSUE_TEMPLATE/`](.github/ISSUE_TEMPLATE/)

---

## Community

- **Bugs & features**: [GitHub Issues](https://github.com/shemondotme/oeparts/issues)
- **Security reports**: [SECURITY.md](SECURITY.md) — private disclosure only
- **Code of Conduct**: [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md)
- **Changelog**: [CHANGELOG.md](CHANGELOG.md)

---

## License

MIT — see [LICENSE](LICENSE).
