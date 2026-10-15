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
- [Site Migration (.oeparts)](#site-migration-oeparts)
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
| **Site Migration** | Move the whole shop, or make a safe testing copy of it, with one encrypted `.oeparts` file — see [below](#site-migration-oeparts) |
| **Installer** | Web-based 6-step wizard, demo data seeder, optional "Import an existing site" branch |

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

> **Git-managed deployment (self-updates via `git checkout`)?** Clone and run every command in this section as the *same* user your web server (PHP-FPM) runs as — not `root`. The one-click updater's `git checkout --force` needs to own every tracked file to replace it later; a file touched by a different user (most commonly: hand-editing a config with `sudo`) breaks future updates with `Permission denied`. See [SECURITY.md § File Ownership & Permissions](SECURITY.md#file-ownership--permissions) for the full list and a one-time setup script.

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
php artisan queue:work redis --queue=migration,critical,default,low
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

> **Upgrading from a release before site migration?** The site export/import jobs use a fourth queue, `migration`. Add it to your worker command (`--queue=migration,critical,default,low`, `migration` first) and restart the worker (`php artisan queue:restart`). Without it, an export that freezes the shop cannot be started from the admin page (the command-line export, `php artisan oeparts:migrate:export --freeze`, works either way): a freeze pauses the customer queues, and the job that drives the export must be on a queue the freeze does not pause. A very large site may run a second worker dedicated to it: `--queue=migration`.

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

By default, `docker compose up` runs the app behind PHP's own built-in server (matches `php artisan serve`), not nginx. The live server runs **nginx + PHP-FPM**, so an opt-in profile mirrors that exactly — same [`deploy/nginx/oeparts.conf`](deploy/nginx/oeparts.conf) routing rules, same [`deploy/php-fpm/oeparts-pool-overrides.conf`](deploy/php-fpm/oeparts-pool-overrides.conf), bind-mounted straight from `deploy/` so there's no second copy to drift out of sync:

```bash
docker compose --profile nginx up -d nginx php-fpm
# App now served through nginx at http://localhost:8081 (override with NGINX_PORT)
```

Use this whenever you need to verify nginx-specific behavior locally before it ships — the recovery console's `oe-recovery.php` carve-out, the `index.php`-only PHP-FPM routing hardening, `/build/` asset caching, or upload-size limits — rather than finding out on the live server.

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
| `OE_BACKUP_KEY` | **Any backup** | AES-256-GCM key for encrypting backups (customer PII/GDPR) — backups **refuse to run** without it. Auto-generated by the web installer on a fresh install; generate manually with `openssl rand -base64 32` if setting up by hand, and store it somewhere durable and separate from the database — losing it makes every existing backup unrecoverable |
| `OE_RECOVERY_KEY` | Recovery Console | Enables `public/oe-recovery.php`, the framework-free console used if an update leaves the app unable to boot. Unset = console fully disabled (404), not just unauthenticated |
| `OE_RELEASE_PUBLIC_KEY` | Signed update verification | Opt-in; once set, the updater cryptographically verifies release authenticity (RSA-SHA256), not just checksum |
| `AWS_*` | S3-backed storage/backups (optional) | Off-site backup disk must be an EU region for GDPR |

Airwallex (card/bank-transfer checkout) is deliberately **not** an `.env` variable — its Client ID, API Key, Webhook Secret, and Environment are entered in the Admin Panel under Settings → Payment Settings and stored in the database, so they can be rotated without touching the server filesystem.

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
| Memcached | ✅ | ❌ | ❌ | `memcached` PHP extension — Laravel *does* ship a Memcached session driver (via its cache-based session handler), but it's untested with this app's two auth guards (web/admin) and undocumented here on purpose; Laravel has no Memcached queue driver at all, full stop |

Host has Memcached but not Redis? Set `CACHE_STORE=memcached` and leave `SESSION_DRIVER`/`QUEUE_CONNECTION` on `file`/`sync` (or `database` if you'd rather not touch the filesystem) — Memcached is only a *documented, supported* choice for the cache role here, even though Laravel itself is technically capable of more.

### Web server (Nginx / Apache)

**Document root MUST be `public/`**, never the project root — `.env`, `storage/`, `vendor/`, `database/` and `.git` all live one level above `public/` and are only kept out of reach because neither web server ever serves anything outside its configured root. A root-level [`.htaccess`](.htaccess) blocks direct access under Apache as a defense-in-depth backstop if this is ever misconfigured, but the same misconfiguration under nginx has no backstop at all — get the root right.

| | Apache | Nginx |
|---|---|---|
| Config | [`public/.htaccess`](public/.htaccess) — ships in the repo, works out of the box (needs `mod_rewrite`) | [`deploy/nginx/oeparts.conf`](deploy/nginx/oeparts.conf) — copy, edit the two placeholders, done |
| Upload/execution limits (large CSV imports) | `.htaccess`'s `php_value` overrides work **only under `mod_php`**. Running PHP-FPM behind Apache (`mod_proxy_fcgi`, increasingly the default)? Same fix as nginx → | PHP-FPM ignores `.htaccess` entirely — set the pool overrides in [`deploy/php-fpm/oeparts-pool-overrides.conf`](deploy/php-fpm/oeparts-pool-overrides.conf), **and** raise nginx's own `client_max_body_size` (nginx enforces its cap before PHP ever sees the request) |
| Recovery console reachability | Works automatically — Apache executes any `.php` file under the docroot | [`deploy/nginx/oeparts.conf`](deploy/nginx/oeparts.conf) intentionally routes only `index.php` to PHP-FPM (hardening — an uploaded `.php` file can never execute) with one explicit carve-out for `oe-recovery.php`. Using a different nginx template? Make sure it has the same carve-out or the recovery console 404s exactly when you need it |

Behind a reverse proxy or CDN (nginx/Apache terminating TLS in front of a separate PHP-FPM pool, or Cloudflare/a load balancer in front of either)? Set `TRUSTED_PROXIES` in `.env` (see `.env.example`) so `IpBlocklist`, rate limiting, and generated `https://` URLs see the real client IP/scheme instead of the proxy's.

Want to test the nginx config itself, not just write it? `docker compose --profile nginx up -d nginx php-fpm` runs the real nginx + PHP-FPM stack locally against `deploy/nginx/oeparts.conf`'s actual routing rules — see [Docker](#docker-recommended-for-contributors) above.

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

### Disaster recovery restore (same keys)

This is the recovery recipe: it brings a site back from one of **its own backups**, on a server where you also hold the **same keys** (`OE_BACKUP_KEY` and `APP_KEY`) that made that backup. Moving a shop to a new server, or making a copy of it, **without carrying keys across**, is a different job with a safer tool: [Site Migration](#site-migration-oeparts).

A backup carries the site's **data** — the database and the uploaded files — and never the `.env` file, so `APP_KEY`, the database password and `OE_BACKUP_KEY` stay per server. The backup's `manifest.json` (unencrypted, self-describing) lets a fresh install find every part with no database rows and no shared history with the old server. This recipe was run end to end on a real backup (100,000 products) before it was written down:

1. **Old server.** Take a full backup (scheduled, or `php artisan oeparts:backup`) and copy the whole run folder from the backup disk, `manifest.json` included. Write down two values from the old `.env` — **`OE_BACKUP_KEY`** (without it the backup cannot be opened, ever) and **`APP_KEY`**.
2. **New server.** Deploy the **same OeParts version** as the backup (restore warns if the backup is from a newer version) up through creating `.env`, but **do not run the web installer**: restore replaces the admin accounts, and the installer would create its own and lock the site. In the new `.env` set:
   - `DB_*` and `APP_URL` for the new server and domain;
   - `OE_BACKUP_KEY` = the **old** value;
   - `APP_PREVIOUS_KEYS` = the **old** `APP_KEY`. Saved passwords and API keys (SMTP, payment gateways, push keys) are encrypted with `APP_KEY`; without this they come back **empty** and must be typed in again. Admin → Health → *Secrets* lists any that could not be read. (Leave the new server's own `APP_KEY` as it is.)
3. **Create the empty tables:** `php artisan migrate --force`. The restore's own bookkeeping tables (`backup_runs`, `backup_parts`) are created by migrations and are deliberately never part of a backup, so the restore cannot start on a completely empty database.
4. **Copy the run folder** to the new server's backup disk (`storage/app/private/backups/<n>/` for the default `local` disk), then run: `php artisan oeparts:backup:restore --import-manifest=backups/<n>/manifest.json --disk=local --force`. This recreates the run rows from the manifest, then restores every table in the backup. You log in afterwards with the **old** site's admin accounts.
5. **Put the uploaded files in place.** Restored files are written to `storage/app/restore/run-<id>/` and are **not** copied into the live site. Copy `storage/app/restore/run-<id>/storage/app/public/` into `storage/app/public/` (and `.../storage/app/private/` the same way for payment proofs and invoices), then run `php artisan storage:link`. Do not point `--files-to` at the project root: that would overwrite the new server's code and `.env`.
6. **Change the domain.** A restore brings the **old** domain with it, and nothing rewrites URLs automatically. In Admin → Settings update the site URL, and update or clear **SEO → Canonical Host** (the admin panel and installer are never redirected by it, but the public site is until you do). Check the redirects, email templates and any pages whose text contains the old address.
7. **Finish:** `php artisan optimize:clear`, `php artisan queue:restart`, `php artisan sitemap:generate` (the restored sitemap files still name the old domain), then check `/up` and the Health page.

**Making a test copy rather than recovering the site?** A restored copy is a full, live copy: it will send the same customer emails, run the same scheduled jobs and use the same payment settings as the original. Clear the SMTP and payment credentials, and switch the scheduler off, before the first request. [Site Migration](#site-migration-oeparts) does this for you: an import with `--launch=staging` starts the copy in a safe mode that sends nothing and takes no real payments.

`--run=<id>` restores from a run already recorded in the *current* database (same-server disaster recovery, not migration); `--import-manifest` is specifically the cross-server path. Both share one command — see `php artisan oeparts:backup:restore --help`.

---

## Site Migration (.oeparts)

OeParts packs a whole shop — the database and the uploaded files — into **one encrypted file** ending in `.oeparts`, and unpacks it on another OeParts install. Use it to move to a new server, to launch a shop that was built on a test server, or to make a testing copy of the real shop that cannot send mail or take money.

It has three doors, and they all run the same engine with the same safeguards:

- **The admin panel** — **System → Migration** (export, your packages, the history, the limits that apply on your server) and **System → Site mode** (the launch checks, releasing a moved-in site, going live). The Migration page needs the `export migration` or `import migration` permission, Site mode needs `import migration`; only `super_admin` has them unless you grant them to another role by hand.
- **The command line** — `php artisan oeparts:migrate:*` and `php artisan oeparts:instance:*` (listed below).
- **The web installer** — on a brand-new install the first screen offers **Import an existing site** next to **Install a new site**.

> **This is not the backup system.** A backup ([above](#in-app-update--recovery-system)) protects *this* site with keys that stay on *this* server. A package is made to leave: it is locked by a **passphrase you choose**, nothing else, so it can be opened on a server that has none of your keys.

### What travels, and what never does

You choose groups of data (the page and the commands call them *groups*; nobody has to think in tables) or a ready-made preset:

| Preset (`--preset=`) | What it takes |
|---|---|
| `full_live` | Everything except logs and carts. Use this to move or launch the real site. |
| `staging` | Like `full_live`, but customer details are replaced and no secrets travel. Invoices and payment proofs are left out unless you add them. |
| `new_store_same_catalog` | Catalog, media, pages, shipping and tax and the admin setup, without customers or orders. Order and invoice numbering starts again. |
| `catalog_only` | Site basics, products, product pictures and the media library. Numbering starts again. |

Or pick groups yourself with `--groups=catalog,content,...` (`core` — settings, languages, admin accounts — is always included, and a group that another one needs is added with a message). The groups are `core`, `catalog`, `catalog_files`, `media`, `content`, `customers`, `sales`, `sales_files`, `invoicing`, `shipping_tax`, `marketing`, `admin_prefs`, `carts` and `logs`.

**Never in a package:** the code, the `.env` file (so `APP_KEY`, the database password and `OE_BACKUP_KEY` stay per server) and system data (backups, updates, sessions, the queue). Only the folders of uploaded files that belong to the groups you chose are read.

**Secrets are left out by default.** Payment-gateway keys, the SMTP password and other saved credentials stay behind unless you add `--include-secrets` (or switch it on in the admin panel); then they are packed locked under a separate key inside the package. On import they are re-encrypted with the *new* site's `APP_KEY`, and any stored secret the new site cannot read is emptied and listed in the report under "Re-enter these settings". A `staging` package can never carry secrets.

### The short version

On the **old** site:

```bash
php artisan oeparts:migrate:export --preset=full_live --generate-passphrase
```

It shows the passphrase **once**. Write it down: we cannot recover it, and without it the file cannot be opened. The package is written to `storage/app/migration/exports/` and removed from the old site after `OE_MIGRATION_EXPORT_RETENTION_DAYS` days (7). By default the shop keeps running while it is copied (a *live copy*, which may be a few minutes behind). For a consistent cut-over on the day you switch servers add `--freeze`: the shop shows a maintenance page while it is copied (see [the worker note](#server-setup-for-migration) for the admin-page version), and `php artisan oeparts:migrate:unfreeze` reopens it. `--plan-only` shows what would be exported, the size and a time estimate, and stops.

Get the file to the **new** server (next section), then import it:

```bash
export OEPARTS_PASSPHRASE='the passphrase'      # or --passphrase-file=/path/to/file; --passphrase=VALUE is refused
php artisan oeparts:migrate:import site.oeparts --preset=full_live --launch=live --confirm=REPLACE
```

Run without `--confirm=REPLACE` it checks everything and changes nothing (`--preflight-only` does only that; `--dry-run` also prints the plan). Then sign in with the admin accounts from the **old** site, and follow the launch checks below.

Passphrases come only from a hidden prompt, `--passphrase-file=`, or the environment variable `OEPARTS_PASSPHRASE` (or `OE_MIGRATE_PASSPHRASE`). On a command line that cannot ask questions, `import` needs the package (a path, a name in `storage/app/import/`, or `--url=`), `--launch=live|staging` (there is deliberately no default) and `--preset=` or `--groups=`.

When an export finishes it also shows a **fingerprint** (`XXXX-XXXX-XXXX-XXXX`). `php artisan oeparts:migrate:inspect site.oeparts` shows the same one once you give the passphrase: compare the two by eye (read it aloud on the phone) before you import a file that travelled through other hands.

| Command | What it does |
|---|---|
| `oeparts:migrate:export` | Export the site, or chosen parts of it, into one encrypted package (`--preset`, `--groups`, `--freeze`, `--include-secrets`, `--anonymise`, `--reset-sequences`, `--generate-passphrase`, `--plan-only`) |
| `oeparts:migrate:inspect {package}` | Show what a package is and, with its passphrase, what it contains (`--header-only`, `--files`, `--verify`) |
| `oeparts:migrate:share [package]` | Make, list (`--list`) or revoke (`--revoke=ID`) a one-time download link |
| `oeparts:migrate:fetch {link}` | Download a package from a link into the import folder |
| `oeparts:migrate:list` | List package downloads and the packages waiting in the import folder |
| `oeparts:migrate:cancel {id}` | Cancel a package download |
| `oeparts:migrate:limits` | Show the limits that apply to downloading a package from a link |
| `oeparts:migrate:import [source]` | Replace this site with the contents of a package (`--resume`, `--rollback`, `--preflight-only`, `--dry-run`, …) |
| `oeparts:migrate:report [run]` | Show the report of an import run, or list the runs |
| `oeparts:migrate:unfreeze` | Reopen a shop that a freeze export is holding in maintenance |
| `oeparts:migrate:wizard-reset` | Release the installer's import branch so another browser can start it |
| `oeparts:migrate:watchdog`, `oeparts:migrate:janitor` | Run by the scheduler (see [Server setup](#server-setup-for-migration)) |
| `oeparts:instance:status` | Show this installation's mode, what it holds back, its ledger and any drift |
| `oeparts:instance:readiness` | Run the launch-readiness checks |
| `oeparts:instance:release` | Release a site from quarantine once the launch checks are clear |
| `oeparts:instance:go-live` | Take a staging copy live as the real shop |
| `oeparts:instance:scan-domain`, `oeparts:instance:rewrite-domain` | List where the old address is still mentioned; replace it with the new one everywhere it is stored |

Add `--help` to any of them for every option.

### Getting the file across

Three ways, all of which end with a `.oeparts` file in the new site's `storage/app/import/` folder:

1. **SFTP or FTP.** Upload it there yourself. Use **binary** mode (a text-mode transfer is detected and refused). A file that changed in the last 30 seconds is treated as "still uploading" by the installer.
2. **A one-time link.** On the old site `php artisan oeparts:migrate:share` makes a link to the newest package (`--ttl=` hours, default 24, at most 168; `--ip=` allows only one address; `--list` and `--revoke=ID` manage them). It looks like `https://old.example/_pkg/<token>#sha256=…&size=…&name=…`; the part after `#` never reaches the server and lets the new site check the file. A link is used by **one** downloader: the first request claims it, that downloader may resume for 12 hours, and everyone else gets the same plain `404` as for a link that does not exist. At most 5 links are alive at once. A link only lets one person download the encrypted file; it does not open it. Send the passphrase by a **different route** than the link. On the new site: `php artisan oeparts:migrate:fetch '<link>'` (resumable with `--resume=ID`; `php artisan oeparts:migrate:list` shows downloads and the packages waiting, `php artisan oeparts:migrate:cancel <id>` stops a download), or give the link to the import: `php artisan oeparts:migrate:import --url='<link>' …`. Quote the link: the `#` and `&` mean something to a shell.
3. **The admin panel.** Download your own packages from **System → Migration → Packages** (it asks for your password again).

A link works while the old site is frozen for an export, and the new site only ever fetches over `https` (see [`SECURITY.md`](SECURITY.md#site-migration-oeparts-packages)).

### Importing: the three doors

- **Command line** — the example above. `--site-url=` sets the new address (default: `APP_URL`), `--also-from=` adds another old host whose links should be rewritten, `--without=` leaves groups out, `--accept-warnings` accepts warnings that need an acknowledgement, `--budget=SECONDS` stops after a while (exit code 10; carry on with `--resume`), `--strict` turns "finished with warnings" into exit code 9, `--json` prints newline-delimited JSON events for scripts. Exit codes: `0` ok, `1` failed, `2` usage, `3` the checks block it, `4` package unreadable, `5` wrong passphrase, `6` the system is busy (a backup, update or another migration holds the lock), `7` not confirmed, `8` stopped by you, `9` warnings under `--strict`, `10` not finished (resumable).
- **The web installer** — upload the file with SFTP to `storage/app/import/`, open `/install`, choose **Import an existing site**, pick the file, type the passphrase, choose what to bring and whether this is the real, live site or a test copy. The import branch belongs to the first browser that starts it (an HttpOnly cookie, not the session); another browser sees "Someone else started this install", and can take it over after 30 minutes without activity, or on the server with `php artisan oeparts:migrate:wizard-reset`. The installer only runs on a site nobody has used, so it cannot replace a shop that has orders or customers — use the admin panel or the command line for that. After an import through the installer the new site gets its **own** `OE_BACKUP_KEY` in `.env`: save it, losing it makes every backup of the new site unrecoverable.
- **The admin panel** — **System → Migration**.

Two switches in `.env` (never a form field, because they weaken a protection):

| Variable | Default | What it does |
|---|---|---|
| `OE_INSTALLER_ALLOW_HTTP_PASSPHRASE` | `false` | The installer refuses to take a passphrase over a connection that is not secure (and is not `localhost`), because anyone on the way could read it. Use `https`, or the command line. Set this to `true` only if you accept that risk. |
| `OE_INSTALLER_LIST_INBOX` | `true` | Set to `false` to stop the installer listing the file names in `storage/app/import/` (a shared host where the names themselves are private); it then asks you to type the file name. |

#### Replacing a site that is already in use

The admin panel and the command line may replace **any** site, including one with orders, customers and payments. Nothing about this is quiet, and no flag skips a step:

1. **Take a normal backup first**: `php artisan oeparts:backup`.
2. **See what you are about to replace**: `php artisan oeparts:migrate:import site.oeparts --preflight-only`. A site is *populated* when it holds orders, payments, customers, invoices, messages, newsletter subscribers or refund requests — or more rows than the recovery point can hold.
3. **A recovery point is made automatically** before the first change. If the site has more than `OE_MIGRATION_RECOVERY_MAX_ROWS` rows (500,000, counted outside the System group) the import is refused with a message that says what raising it costs in disk and minutes; raise it knowingly, or import into a fresh install.
4. **Confirm in words**: `--confirm=REPLACE` and, for a populated site, also `--replace-populated` and `--accept-warnings` (it acknowledges the warning "This site has been used and ALL of it will be replaced"). Without them the command changes nothing and exits with code 7. In the admin panel you type `REPLACE` and your password.
5. **While it runs the site shows a maintenance page.** If your terminal dies, nothing is lost: `php artisan oeparts:migrate:import --resume` carries on, and `--rollback` puts the site back as it was (a *completed* import is rolled back only if the site was not used since, unless you add `--accept-loss`). While an import is in progress or stopped half way, `storage/app/migration/RECOVERY.txt` holds these exact commands.
6. **Afterwards**: sign in with the admin accounts from the *old* site (`--extra-admin-email=` also creates one more administrator, the break-glass account for a copy nobody knows the old passwords of). Files that were already on the site and are not in the package are left as they were and counted in the report as leftovers; the Cleanup page can remove files nothing references.

### Staging copies, "Site mode" and going live

An import asks one question that has no default: **is this your real, live site, or a test copy?** (`--launch=live` or `--launch=staging`). Either way the site arrives in *quarantine*, a safe mode that sends nothing and runs only the work that keeps the site healthy, and stays there until the launch checks pass. You then **release** it: a live launch becomes the real shop (`production`), a test copy becomes a **staging** copy.

A staging copy stays harmless on purpose: customer mail is dropped (except to an allow-list), the card gateway is forced to its sandbox, Paysera is refused, a payment copied from the real shop can never be captured, search engines are told to stay away (`robots.txt` disallows everything, `noindex` everywhere), tracking ids, search-engine pings and Cloudflare read as switched off, scheduled work that reaches customers does not run, and order and invoice numbers start with `T-`. Quarantine is stricter still: it drops all mail and runs only the work that keeps the site healthy. The stored settings are never changed — a copy only *reads* them as off — so going live brings the real ones back at once. (The full list is in **System → Site mode → Safe mode**.)

Separately, an import always switches **off** three settings that belong to the *old* server, in both launch modes: automatic cleaning, scheduled backups and automatic security updates. The report and the launch checks remind you to switch them on again when you are ready.

An import writes only these keys to `.env`: `APP_URL` (the new address), `OE_MODE`, `OE_INSTANCE_ID`, `CACHE_PREFIX` and `REDIS_PREFIX`, and `OE_MAIL_ALLOWLIST` when you gave one (never `APP_KEY`, `APP_ENV`, `DB_*`, `MAIL_*` or `TRUSTED_PROXIES`; the installer door also gives a brand-new site its own `OE_BACKUP_KEY`). Every change to the mode, the mail list or a prefix restarts the queue workers first (unless the queue is `sync`), because a worker reads `.env` only once, when it starts.

The mode lives only in `.env` as `OE_MODE=production|staging|quarantine` (missing or empty means production; a typo is treated as quarantine and logged as critical, so a mistake can never turn a copy back into the real shop). `OE_INSTANCE_ID` gives the copy its own cache and Redis prefixes so two sites sharing one Redis cannot read each other's cache. `OE_MAIL_ALLOWLIST=you@example.com,@yourcompany.com` lets a staging copy still mail those addresses (an exact address or a whole `@domain`), with `[STAGING]` in front of the subject; it is edited on **System → Site mode → Safe mode**. A staging or quarantined copy also does not run the scheduler through the web "cron fallback" unless you set `OE_NONPROD_HTTP_CRON=true`, so a copy with no real cron runs no scheduled work at all.

**Launch checks.** `php artisan oeparts:instance:readiness` (or **System → Site mode → Run checks**) looks at the data, the addresses, the secrets, the safe mode itself, the scheduler heartbeat, the queue worker and the web pages, and says what to do about anything that is wrong. Exit code `0`: nothing blocks the launch; `3`: something does; `9`: only warnings, and you asked for `--strict`. `--json` prints one JSON document. The page-level checks (do pages still mention the old host? does anything redirect to it?) ask the web server on the loopback address when `OE_READINESS_LOOPBACK` (for example `http://127.0.0.1`) is set, otherwise inside the application only, which the report marks as partial.

Then leave quarantine or go live — each runs every check again first, restarts the queue workers and does not keep the new mode unless a worker confirms it:

```bash
php artisan oeparts:instance:status                                  # what is this site, and what is held back?
php artisan oeparts:instance:release --to=production                 # quarantine → live shop (or --to=staging for a test copy)
php artisan oeparts:instance:go-live --host=shop.example.com         # staging → live shop: you type the site's host to confirm
```

`go-live` refuses if the copy was used since it was made (test orders, test customers) unless you add `--discard-activity` (it deletes exactly what was created on the copy and puts the order and invoice numbers back, so the real shop has no gap), and asks for `--confirm-live-payments` when the card gateway is set to its live environment. A check marked as waivable can be waived with `--waive=key:reason` (a reason of at least ten characters, kept in the record); the check that proves a queue worker follows the mode can never be waived. The same actions are on **System → Site mode**, behind your password.

Changing the old address inside the data is part of every import. The two commands behind it are also there on their own: `php artisan oeparts:instance:scan-domain` lists where the old address is still mentioned, and `php artisan oeparts:instance:rewrite-domain https://shop.example.com --dry-run` counts what would change without writing anything.

#### What a scrambled (staging) copy does *not* scrub

The `staging` preset (and `--anonymise`) replaces names, emails, phone numbers, addresses, IP addresses and codes with stand-ins that are the same for the same input, and blanks free text and JSON entirely. These are **deliberately kept as they are**, so look at them before you hand the copy to anyone:

| Table | Kept | Why |
|---|---|---|
| `admins` | name, email, password | Administrator accounts keep their real names and emails (and password hashes) so the team can log in. |
| `ip_blocklists` | ip_address, reason | The shop's own list of blocked visitor addresses is kept as it is. |
| `orders` | order_number, payment_reference, tracking_number, invoice_number, shipping_method_name_snapshot | Order numbers, payment and tracking references and invoice numbers are kept so orders stay recognisable. |
| `payments` | transaction_id | Payment gateway transaction ids are kept. |
| `contact_messages` | order_number, oem_number, manufacturer, car_model, year | The part, order number and car model a message refers to are kept. |
| `part_inquiries` | oem_number, manufacturer, car_model, year | The part and car model an inquiry asks about are kept. |
| `tax_rates` | notes | Notes staff wrote on tax rates are kept. |
| `inventory_logs` | note | Staff notes on stock changes are kept. |
| `testimonials` | name, company, location, quote | Testimonials are public website content and are kept. |
| `settings` | key, value | The shop's own settings, branding, addresses and bank details are kept. |

The export wizard and the package report show this same list.

### Server setup for migration

- **Queue worker.** Exports and downloads run on their own queue, `migration`, so a freeze (which pauses the customer queues) can never block the export it is for. The worker command is `php artisan queue:work redis --queue=migration,critical,default,low`, and the supervisor template ships with that line (`deploy/supervisor/oeparts-queue-worker.conf`): copy it as in [Quick Start step 4](#4-start-queue-workers). An install that keeps its old worker line works as before; the admin page then cannot start a *freeze* export (it shows the command-line command instead), because nothing proves a worker serves `migration`. **System → Migration → Check worker** proves it.
- **Scheduler.** `oeparts:migrate:watchdog` (every five minutes) lifts an expired freeze and nudges an export nobody is driving; `oeparts:migrate:janitor` (hourly) removes dead exports, expired packages and old keys. Both need the cron line from [step 5](#5-schedule-the-cron).
- **Web server timeouts for `/_pkg/`.** A one-time link streams a file that can be many gigabytes through PHP. The response tells the web server not to buffer it (`X-Accel-Buffering: no`) and PHP removes its own time limit when it is allowed to, but the web server's timeouts still apply:
  - **nginx + PHP-FPM**: add `fastcgi_read_timeout 3600s;` to the `location = /index.php` block of [`deploy/nginx/oeparts.conf`](deploy/nginx/oeparts.conf). Behind another web server use `proxy_read_timeout 3600s;` instead.
  - **Apache**: `Timeout 3600` in the virtual host, and with PHP-FPM behind `mod_proxy_fcgi` also `ProxyTimeout 3600`.
- **One download at a time.** PHP cannot see how many workers your web server has, so a link serves **one** download at a time by default; a second request gets `503` with `Retry-After`. If your PHP-FPM pool has eight or more workers, raise `OE_SHARE_MAX_STREAMS`.
- **Disk.** An export checks the free space of the volume that holds `storage/app/migration` first (about 1.15 times the compressed data, plus working room and 1 GiB of headroom) and refuses to start without it. The import's checks look at disk space too, and an import that replaces a site also needs room for its recovery point.

### Database targets: MySQL and MariaDB

A package imports into MySQL 8.0.16+ and into MariaDB 10.11+ (reached through `DB_CONNECTION=mysql`). One difference remains: MariaDB has no `ngram` FULLTEXT parser, so on MariaDB the OEM part-number search uses the slower `LIKE` fallback instead of the index. The import's preflight says so as a warning ("Product search will use the slower LIKE fallback because this server has no ngram FULLTEXT parser"); nothing is lost and the site works, only large-catalog search is slower. On MySQL the import drops the products search index before the load and builds it again after (`OE_MIGRATION_FULLTEXT_STRATEGY=drop_readd`, the default; `keep` leaves it in place and is more than twice as slow). On a 1,000,000-product rehearsal (MySQL 8.0, a four-CPU VM that was busy with other work) loading took about 18 minutes plus about 3 minutes to rebuild the index.

### Settings (`.env`)

All optional; the defaults are right for most sites. Every key is also in [`.env.example`](.env.example), commented out.

| Variable | Default | What it does |
|---|---|---|
| `OE_MODE` | production | `production`, `staging` or `quarantine` (above). |
| `OE_INSTANCE_ID` | — | A short identity for this installation; makes cache and Redis prefixes unique. |
| `OE_MAIL_ALLOWLIST` | — | Addresses and `@domains` a staging copy may still mail. |
| `OE_NONPROD_HTTP_CRON` | `false` | Allow the web "cron fallback" on a staging or quarantined copy. |
| `OE_READINESS_LOOPBACK` | — | An address that reaches this server (for example `http://127.0.0.1`) for the page-level launch checks. |
| `OE_INSTALLER_ALLOW_HTTP_PASSPHRASE` | `false` | Allow the installer to take a passphrase over plain `http` (above). |
| `OE_INSTALLER_LIST_INBOX` | `true` | List the inbox in the installer (above). |
| `OE_SHARE_MAX_STREAMS` | `1` | Downloads of a one-time link at the same time. |
| `OE_MIGRATION_EXPORT_RETENTION_DAYS` | `7` | Days an exported package stays on the old site. |
| `OE_MIGRATION_FREEZE_MAX_MINUTES` / `OE_MIGRATION_HOLD_AUTO_RELEASE_MINUTES` / `OE_MIGRATION_FREEZE_REFUSE_MINUTES` | `180` / `120` / `720` | How long a freeze export may keep the shop closed, how long a finished one holds it for you (`0` = until `oeparts:migrate:unfreeze`), and the estimate above which a freeze is refused. |
| `OE_MIGRATION_RECOVERY_MAX_ROWS` | `500000` | The largest site (rows outside the System group) the built-in recovery point can hold; a bigger one counts as *populated* and is refused until you raise this knowingly. |
| `OE_MIGRATION_FULLTEXT_STRATEGY` | `drop_readd` | `drop_readd` or `keep` (MySQL). |
| `OE_MIGRATION_ESCROW_TTL` | `86400` | Seconds a run keeps its passphrase-derived keys on disk at most (they are deleted when the run ends). |
| `OE_MIGRATION_ALLOW_PRIVATE_HOSTS` | `false` | Let a download reach private addresses, for an intranet or a rehearsal. Environment only; never in the admin panel. |
| `OE_MIGRATION_ALLOWED_HOSTS` / `OE_MIGRATION_ALLOWED_PORTS` | empty / `443` | Limit downloads to these hosts (and their subdomains) / allow these ports. |
| `OE_MIGRATION_FETCH_MAX_BYTES` | 32 GiB | The largest package a link may bring. |
| `OE_MIGRATION_LOG_DAYS` / `OE_INSTANCE_MAIL_LOG_DAYS` | `60` / `14` | Days to keep `storage/logs/migration.log` (what was exported, imported, rolled back, by whom) and `storage/logs/instance-mail.log` (mail a safe-mode copy suppressed: recipient, subject and class, never the body). |

The remaining `OE_MIGRATION_*` keys (`IMPORT_HEARTBEAT`, `EXPORT_HEARTBEAT`, `LONG_STAGE_SECONDS`, `QUEUE_HOLD_SECONDS`, `SITEMAP_INLINE_MAX`, `REBUILD_CACHE`) tune timeouts of long runs; see `config/migration.php`.

### If something stops half way

Nothing is lost: a recovery point exists before the first change. Run `php artisan oeparts:migrate:report` to list runs (or `oeparts:migrate:report <run>` for one), `php artisan oeparts:migrate:import --resume` to carry on, or `--rollback` to go back. **System → Migration → History** shows every event, read from an audit file that survives the database swap. Each error has a plain title, what it means and what to do about it.

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
Queue:     Redis — migration + critical + default + low queues
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
