# Changelog

All notable changes to OEMHub are documented in this file.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versioning follows [Semantic Versioning](https://semver.org).

---

## [Unreleased]

## [1.0.0] — TBD "Atlas"

### Added
- OEM number search engine with normalization (BTREE indexed)
- Search autocomplete (4+ chars, debounce 300ms)
- Cross-reference OEM matching
- Zero-results page with inline part inquiry form
- 5-language support: EN, DE, LT, FR, ES (auto-detect)
- Homepage with 14 editable sections
- B2B + B2C checkout with 5-step flow
- Guest checkout with OTP email verification
- B2B VAT exemption via VIES SOAP API
- Airwallex payment (card + bank transfer)
- Webhook security (HMAC + timestamp + idempotency)
- Invoice PDF generation (DomPDF)
- Admin panel with 4 roles (super_admin, manager, catalog_admin, support)
- 26-widget admin dashboard (customizable)
- Product management with CSV import/export
- Bulk price/stock update tool
- Order management with status history
- Refund request workflow
- Zone-based shipping engine
- Free shipping nudge with progress bar
- Coupon system (percentage + fixed, per-user limits)
- Abandoned cart recovery (email)
- SEO engine (hreflang, JSON-LD, sitemap, canonical)
- Multi-file sitemap (parts, brands, blog, pages)
- Blog with categories and tags
- CMS pages with drag-drop menu builder
- 301/302 redirect manager
- Media file manager
- Translation system (18 settings groups, 300+ defaults)
- Health check endpoint (/health)
- Admin health dashboard
- Activity logs, login logs, cron logs
- GDPR: soft delete, data anonymization, 90-day log retention
- Web-based installer wizard (6 steps)
- Demo data seeder
- GitHub Actions CI/CD (tests + release zip on tag)
- version.json + in-app version display
