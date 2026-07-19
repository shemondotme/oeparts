# Changelog — OeParts

All notable changes to this project are documented here.

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
- **In-App Update & Recovery System** — a WordPress-class, one-click, pure-PHP update mechanism built for shared hosting: automatic release detection with in-panel notifications, a 12-point safety pre-flight gate, chunked/resumable and mandatorily-encrypted (AES-256-GCM) backups with off-site (S3/SFTP) support and GFS retention, atomic file-swap updates with post-update verification and automatic rollback on failure, and a framework-independent Recovery Console for disaster recovery when the app itself can't boot
- Cryptographically signed releases (RSA-SHA256) with SHA-256 checksum verification
- GitHub Actions CI/CD: automated test suite (PHP 8.2/8.3/8.4 matrix) + tag-triggered release build/publish pipeline
- Branding system (colors/logo configurable from admin settings)
- CLAUDE.md — comprehensive AI coding rules documenting all critical patterns (bcmath, auth guards, cache rules, SEO, OTP, VIES)
- GitHub-quality documentation (README, SECURITY.md, CODE_OF_CONDUCT.md)

### Fixed
- JSON-LD structured data rendering under Laravel 12's new `@context` Blade directive (escaped to `@@context` across 9 storefront views)
- Installer, admin font, and legacy gradient-CSS cleanups
