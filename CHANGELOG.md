# Changelog — OeParts

All notable changes to this project are documented here.

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