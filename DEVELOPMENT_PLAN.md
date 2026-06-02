# OeParts - Development Plan
## Version 1.0 | 20 Sprint Order

---

## HOW TO USE

Each sprint = one Cursor Agent Mode session.
Before each sprint: re-read .cursorrules + relevant PRD section.
After each sprint: php artisan test must pass before moving on.

---

## SPRINT 1 — Project Setup & Foundation
```
Laravel 11 install → packages → tailwind.config.js →
vite.config.js → auth.php (two guards) →
AppServiceProvider (bcscale(2)) → middleware →
helpers.php (settings(), trans_field()) →
npm run build → verify
```

---

## SPRINT 2 — All 55 Migrations & Models
```
All 55 custom tables (dependency order) →
Spatie publish + migrate →
All Eloquent Models (casts, relationships, SoftDeletes) →
All Enums (OrderStatus, ProductCondition, etc.)

Verify: php artisan migrate:fresh → 63 tables
```

---

## SPRINT 3 — Seeders & Core Services
```
SettingsSeeder (300+ defaults) → LanguagesSeeder →
RolesSeeder (super_admin/manager/catalog_admin/support, guard='admin') →
SequencesSeeder → CarriersSeeder →
OemNormalizerService → SequenceService →
CacheService → SettingsService → OtpService →
TranslationService → ViesService

Tests: OemNormalizerTest, SequenceServiceTest, BcmathPriceTest
```

---

## SPRINT 4 — Design System & Layouts
```
CSS design tokens (Tailwind + admin Slate theme) → Tailwind components →
Alpine.js modules (otp-input, countup, clipboard) →
Frontend layout (app.blade.php) → Admin layout →
All Blade UI components →
npm run build → php artisan view:cache
```

---

## SPRINT 5 — Homepage & Sections
```
SectionRenderer service →
All 14 section Blade components →
HomeController + home.blade.php →
Sections seeder (default content) →

Test: GET /en/ → 200, all sections render
```

---

## SPRINT 6 — OEM Search Engine
```
SearchService (normalize → exact → cross-ref → partial) →
SearchController → search/results.blade.php →
search/zero-results.blade.php →
ManufacturerController + CarModelController →
NormalizeOemUrl middleware →
Autocomplete AJAX endpoint

Tests: OemSearchTest (redirect, results, zero, logging)
```

---

## SPRINT 7 — Cart System
```
CartService (create, add, remove, merge, price-change) →
CartController + API endpoints →
cart/index.blade.php (2-col, nudge bar, Alpine qty) →
Cart badge in navbar

Tests: CartTest (add, merge, coupon, price change)
```

---

## SPRINT 8 — Checkout Flow
```
CheckoutService (validate → address → shipping → VAT → order) →
CheckoutController (5 steps, session-based) →
OtpService for guest step 1 →
All 5 checkout step views →
SequenceService for order numbers →
Thank you page + auto-create guest account

Tests: CheckoutFlowTest (OTP, B2B VAT, order number format)
```

---

## SPRINT 9 — Payment System
```
PaymentService (Airwallex card + bank transfer) →
WebhookController (verify HMAC + idempotency) →
ProcessAirwallexWebhook job (queue: critical) →
Payment views (iframe + bank transfer copy buttons)

Tests: WebhookTest (valid sig, invalid sig, duplicate)
```

---

## SPRINT 10 — Customer Account
```
AuthController (login modal, register modal, OTP) →
Password reset (/en/reset-password/{token}) →
AccountController (dashboard, orders, addresses, settings) →
InvoiceService (DomPDF) →
All account views

Tests: AuthTest, invoice download
```

---

## SPRINT 11 — FilamentPHP Admin Panel (Navigation & Clusters)
```
FilamentPHP 5.6.5 install & config →
AdminPanelProvider with 4 Clusters:
  Settings, System, Content, Reports →
8 Navigation Groups (Commerce, Catalog, Customers, Marketing,
  Content, Reports, System, Settings) →
SPA mode enabled →
All Resources registered with proper navigation →
AdminResource + RoleResource (custom, no FilamentShield) →
Clusters use getNavigationIcon() methods (PHP 8.2 compat)

Tests: config:cache, route:list, view:cache all pass
```

---

## SPRINT 12 — Filament Resources (Commerce & Catalog)
```
OrderResource (list, view, status management) →
RefundRequestResource →
ProductResource (CRUD, inline edit, CSV import) →
ManufacturerResource →
CarModelResource →
CategoryResource →
PartInquiryResource →
All with Filament tables, filters, actions

Tests: Resources render, CRUD operations work
```

---

## SPRINT 13 — Filament Resources (Customers & Marketing)
```
CustomerResource (list, view, address management) →
CouponResource (CRUD, usage tracking) →
AbandonedCartResource →
EmailLogResource →
SearchLogResource →
All with Filament tables, filters, actions
```

---

## SPRINT 14 — Filament Resources (Content & System)
```
SectionResource (reorder, edit multilang content) →
BlogPostResource →
PageResource →
MediaFileResource →
MenuResource (drag-drop ordering) →
RedirectResource →
TestimonialResource →
FaqResource →
NewsletterSubscriberResource →
ContactMessageResource →
ActivityLogResource →
CronLogResource →
TranslationResource →
LoginLogResource →
IpBlocklistResource →
HealthCheckResource →
All with Filament tables, filters, actions
```

---

## SPRINT 15 — Filament Settings Pages (25 pages)
```
SettingsPage (abstract base with save/fillForm) →
GeneralSettings → AppearanceSettings → ContactSettings →
TaxSettings → ShippingSettings → PaymentSettings →
EmailSettings → AuthSecuritySettings → SearchSettings →
CartSettings → OrdersSettings → PerformanceSettings →
SecuritySettings → IntegrationsSettings →
AnnouncementSettings → StatsCounterSettings →
SeoSettings → MaintenanceSettings → AboutLicenseSettings →
CacheManagementSettings → DatabaseSettings →
LogViewerSettings → SchedulerSettings →
MenuSettings → SocialLinkSettings

Settings cluster: all 25 pages under Settings cluster
Reports cluster: Sales, Customers, Search, Checkout reports
System cluster: SetupAssistant, Health dashboard
```

---

## SPRINT 16 — SEO Engine
```
SeoService (meta, OG, JSON-LD, hreflang, canonical) →
SitemapService + daily command →
SEO in ALL views →
Robots.txt controller
```

---

## SPRINT 17 — Email Templates
```
All 10 Mailables →
All Blade email templates (HTML + text) →
Multilang support for all emails →
EmailLog auto-logging →

Tests: EmailTest (queued, logged, correct language)
```

---

## SPRINT 18 — Installer Wizard
```
InstallerController (6 steps) →
Installer views (no CDN) →
InstallerMiddleware →
DemoDataSeeder →
php artisan demo:setup

Tests: InstallerTest (flow, lock file, redirect)
```

---

## SPRINT 19 — Open Source Distribution
```
version.json + CHANGELOG.md →
README.md (GitHub quality) →
.github/workflows/tests.yml →
.github/workflows/release.yml →
.github/CONTRIBUTING.md + templates →
GET /health endpoint
```

---

## SPRINT 20 — Final Testing & Polish
```
php artisan test --parallel → 100% pass
Anti-pattern audit (no flush, no float, no auth() in admin)
Performance: OEM search < 200ms
Accessibility: skip nav, ARIA, focus styles, mobile touch targets
Final npm run build
Deployment package ready
```

---

## RULES FOR EVERY SPRINT
```
BEFORE: read .cursorrules + relevant PRD section
DURING: write tests alongside code
        never Cache::flush()
        never float arithmetic
        bcscale(2) already set — don't set again
AFTER:  php artisan test → all green
        php artisan view:cache → no errors
```
