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
    <img src="https://img.shields.io/github/v/release/oeparts/oeparts" alt="Latest Release">
  </a>
  <img src="https://img.shields.io/badge/PHP-8.3%2B-blue" alt="PHP 8.3+">
  <img src="https://img.shields.io/badge/Laravel-12-red" alt="Laravel 12">
  <img src="https://img.shields.io/badge/license-MIT-green" alt="MIT License">
</p>

---

## What is OeParts?

OeParts is a production-ready e-commerce platform built specifically for **genuine OEM auto parts** dealers in Europe. It is designed around the reality of the parts business: customers search by OEM number, not by category — so the search engine is the product.

**Key design decisions:**
- OEM numbers are normalized before indexing and searching (strips dashes/spaces, uppercases) so `1K0-407-271-E` and `1K0407271E` are the same part
- No product images — OEM parts are identified by number, not photo
- Binary stock status (`is_in_stock`) — no quantity tracking
- B2B VAT exemption via live VIES SOAP validation
- All money in `bcmath` — no float arithmetic anywhere

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
| **Installer** | Web-based 6-step wizard, demo data seeder |

---

## Requirements

| Dependency | Minimum |
|---|---|
| PHP | 8.2 |
| MySQL | 8.0.16 |
| Redis | 6.0 |
| Nginx | any |
| Node.js | 18 (local build only — not required on server) |

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

```env
DB_HOST=127.0.0.1
DB_DATABASE=oeparts
DB_USERNAME=root
DB_PASSWORD=

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

MAIL_MAILER=smtp
MAIL_HOST=...
```

### 3. Run the web installer

```
http://your-domain.com/install
```

The installer will:
- Test your database connection
- Run all 55 migrations
- Seed settings, languages, roles, carriers, and sequences
- Create your admin account
- Optionally load demo data

### 4. Start queue workers

```bash
php artisan queue:work redis --queue=critical,default,low
```

In production, run this under a process supervisor so it auto-restarts
on crash instead of silently stopping all queue processing. A Supervisor
config template is provided in `deploy/supervisor/oeparts-queue-worker.conf`:

```bash
cp deploy/supervisor/oeparts-queue-worker.conf /etc/supervisor/conf.d/
# edit the file and replace /path/to/oeparts with your real deployment path
supervisorctl reread
supervisorctl update
supervisorctl start oeparts-queue-worker:*
```

### 5. Schedule the sitemap

Add to crontab:
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

Full guide, including the PHP 8.3/8.4/8.5 compatibility matrix and the
shared-hosting simulation used before every release: see
[`docs/DOCKER_DEV_ENVIRONMENT.md`](docs/DOCKER_DEV_ENVIRONMENT.md). Opening
this repo in VS Code and choosing "Reopen in Container" sets everything up
automatically — no manual steps at all.

### XAMPP / local PHP (alternative)

```bash
# Install JS deps and build assets (once)
npm install
npm run build

# Run with XAMPP/local PHP (no Redis required)
# Set in .env:
CACHE_DRIVER=array
SESSION_DRIVER=file
QUEUE_CONNECTION=sync

# Run tests
php artisan test

# Seed fresh demo data
php artisan demo:setup --fresh --seed --yes
```

---

## Tech Stack

```
Backend:   Laravel 12, PHP 8.3+
Database:  MySQL 8.0.16+ (utf8mb4_unicode_ci)
Cache:     Redis 6+
Frontend:  Blade + Tailwind CSS + Alpine.js
Icons:     Heroicons v2
Payments:  Airwallex
Build:     Vite (local only)
Queue:     Redis — two queues: critical + default
PDF:       barryvdh/laravel-dompdf
Roles:     spatie/laravel-permission (Admin model only)
```

---

## Architecture

- **Controllers** are thin: validate → service → respond
- **Services** hold all business logic
- **bcmath only** for every money calculation — `bcscale(2)` set globally
- **Two auth guards**: `web` (customers) and `admin` (Admin model)
- **Multilang JSON columns**: all translatable fields are `json` cast to `array`, rendered via `trans_field()`
- **OEM normalization**: always search/store via `OemNormalizerService::normalize()`

See [ARCHITECTURE.md](ARCHITECTURE.md) for full patterns and folder structure.

---

## Configuration

All runtime settings are stored in the `settings` table and accessed via `settings('group.key', $default)`. Never hardcode VAT rates, OTP length, rate limits, or thresholds.

Notable setting groups: `tax`, `shipping`, `search`, `performance`, `seo`, `payment`, `stats_counter`, `social`, `contact`.

---

## Security

- Report vulnerabilities privately via GitHub Security Advisories
- Do not open public issues for security bugs
- HMAC webhook verification on all payment callbacks
- OTP brute-force protection (max attempts + cooldown from settings)
- IP blocklist middleware on all frontend routes
- Honeypot on every public form

---

## Contributing

See [CONTRIBUTING.md](.github/CONTRIBUTING.md).

---

## License

MIT — see [LICENSE](LICENSE).
