# FRONTEND MASTER TASKS — OeParts
> Single source of truth for all frontend optimization work.
> Update after every session. Never lose progress.

---

## 1. PROJECT OVERVIEW

**Project:** OeParts — B2B/B2C e-commerce platform for genuine OEM auto parts in Europe
**Stack:** Laravel 11 · Blade · Tailwind CSS 3.4.19 · Alpine.js 3.14.0 · Heroicons v2
**Design System:** Industrial Blueprint — flat, hairline borders, warm ivory/ink palette, monospace OEM data
**Languages:** EN · DE · LT · FR · ES (/{lang}/ URL prefix)
**Admin:** FilamentPHP 5.6.5 (separate theme, not covered here)
**Build:** Vite 6.0.11 (local only — public/build/ committed)
**Goal:** Systematically optimize UX · Content · SEO · Performance · Accessibility · Security — page by page

---

## 2. DESIGN SYSTEM REFERENCE

### Color Tokens (tailwind.config.js)
| Token | Hex | Usage |
|-------|-----|-------|
| `ink` | `#0A1228` | Primary text on ivory |
| `ivory` | `#F7F3E7` | Warm cream page background |
| `paper` | `#FFFFFF` | Card surfaces |
| `rule` | `#D8CFB6` | Hairline borders |
| `navy` | `#0B3A68` | Headings, hero, sidebar |
| `amber` | `#F59E0B` | CTAs, active states, accents |
| `amber-text` / `amber-ink` | `#B45309` / `#9A5A00` | WCAG AA amber text on light |
| `body` | `#334155` | Secondary prose text |
| `muted` / `ink-muted` | `#64748B` | Muted labels |

### Typography
| Role | Font | Weights |
|------|------|---------|
| Display/Headings | Plus Jakarta Sans | 600, 700, 800 |
| Body/UI | Geist Sans | 400, 500, 600 |
| Mono/Data | Geist Mono | 400, 500 |

### Key Blueprint Utility Classes (resources/css/app.css)
- `.bp-spec`, `.bp-spec-mono`, `.bp-spec-light` — micro labels (10px, 0.22em tracking)
- `.bp-rule`, `.bp-rule-dark` — hairline borders
- `.bp-card`, `.bp-card-ivory` — flat cards
- `.bp-btn`, `.bp-btn-primary`, `.bp-btn-amber`, `.bp-btn-outline`, `.bp-btn-ghost`
- `.bp-input`, `.bp-input-mono` — form fields
- `.bp-leader`, `.bp-leader-dots` — dotted leaders (spec sheet style)
- `.section`, `.section-heading`, `.section-subheading` — section layout utilities

### Breakpoints (Tailwind defaults)
- `sm`: 640px · `md`: 768px · `lg`: 1024px · `xl`: 1280px · `2xl`: 1536px
- Max content width: `max-w-[1440px]`

---

## 3. GLOBAL ELEMENTS INVENTORY

| # | Component | File | Status | Priority |
|---|-----------|------|--------|----------|
| G1 | Navbar / Header | `components/navbar.blade.php` | [x] Done | CRITICAL |
| G2 | Footer | `components/footer.blade.php` | [x] Done | CRITICAL |
| G3 | Master Layout (head/body) | `layouts/app.blade.php` | [x] Done | CRITICAL |
| G4 | Auth Modal (Login/Register) | `components/modals/auth-modal.blade.php` | [x] Done | CRITICAL |
| G5 | OTP Modal | `components/modals/otp-modal.blade.php` | [ ] Not Started | HIGH |
| G6 | Part Inquiry Modal | `components/modals/part-inquiry.blade.php` | [ ] Not Started | MEDIUM |
| G7 | Toast Notifications | `components/ui/toast.blade.php` | [x] Done | HIGH |
| G8 | Alert Component | `components/ui/alert.blade.php` | [ ] Not Started | MEDIUM |
| G9 | Breadcrumb | `components/ui/breadcrumb.blade.php` | [x] Done | HIGH |
| G10 | Pagination | `components/ui/pagination.blade.php` | [x] Reviewed — no changes needed | HIGH |
| G11 | Badge | `components/ui/badge.blade.php` | [ ] Not Started | LOW |
| G12 | Condition Badge | `components/ui/condition-badge.blade.php` | [ ] Not Started | LOW |
| G13 | Cookie Consent | `components/cookie-consent.blade.php` | [x] Done | HIGH |
| G14 | Language Switcher | `components/language-switcher.blade.php` | [ ] Not Started | MEDIUM |
| G15 | Section Heading | `components/section-heading.blade.php` | [ ] Not Started | MEDIUM |
| G16 | Star Rating | `components/star-rating.blade.php` | [ ] Not Started | LOW |
| G17 | Account Shell | `components/account/shell.blade.php` | [ ] Not Started | MEDIUM |
| G18 | Announcement Bar | Inside `layouts/app.blade.php` | [x] Done (part of app.blade review) | MEDIUM |
| G19 | Preloader | Inside `layouts/app.blade.php` | [x] Reviewed — production-ready as-is | LOW |
| G20 | Skip Navigation | Inside `layouts/app.blade.php` | [x] Done | HIGH |

---

## 4. PAGE CATEGORY INVENTORY

| # | Category | Page Count | Priority | Status |
|---|----------|-----------|----------|--------|
| 1 | Global Elements | 20 components | CRITICAL | [~] 50% done |
| 2 | Homepage | 1 page + 14 sections | CRITICAL | [x] Done |
| 3 | Search Pages | 3 pages | CRITICAL | [ ] |
| 4 | Browse/Catalog | 4 pages | HIGH | [ ] |
| 5 | Cart | 1 page | HIGH | [ ] |
| 6 | Checkout | 10 pages | HIGH | [ ] |
| 7 | Authentication | 3 pages | HIGH | [ ] |
| 8 | Customer Account | 8 pages | MEDIUM | [ ] |
| 9 | Blog | 2 pages | MEDIUM | [ ] |
| 10 | Informational/Contact | 3 pages | MEDIUM | [ ] |
| 11 | Error Pages | 7 pages | LOW | [ ] |
| 12 | Email Templates | 24 templates | LOW | [ ] |

---

## 5. COMPLETE PAGE INVENTORY (35 Pages)

### CAT 1 — Global Elements (see Section 3 above)

---

### CAT 2 — Homepage

| # | Page | File | Status | Priority |
|---|------|------|--------|----------|
| H1 | Homepage (wrapper + section loop) | `frontend/home.blade.php` | [x] Done | CRITICAL |

#### Homepage Sections (CMS-managed, all in `components/sections/`)

| # | Section | File | Status | Priority | Desktop Cols |
|---|---------|------|--------|----------|--------------|
| S1 | Hero (OEM Search Entry) | `sections/hero.blade.php` | [x] Done | CRITICAL | 12-col (8+4) |
| S2 | Trust Bar | `sections/trust_bar.blade.php` | [x] Done | CRITICAL | 4-col |
| S3 | Stats Counter | `sections/stats_counter.blade.php` | [x] Done | HIGH | 3-4 col |
| S4 | Featured Brands | `sections/featured_brands.blade.php` | [x] Done | HIGH | 4-6 col |
| S5 | Popular Searches | `sections/popular_searches.blade.php` | [x] Done | HIGH | flex-wrap |
| S6 | How It Works | `sections/how_it_works.blade.php` | [x] Done | HIGH | 3-col |
| S7 | Promotional Banner | `sections/banner.blade.php` | [x] Reviewed — no changes needed | MEDIUM | full-width |
| S8 | Shipping Info | `sections/shipping_info.blade.php` | [x] Done | MEDIUM | 3-4 col |
| S9 | Part Inquiry CTA | `sections/part_inquiry.blade.php` | [x] Done | MEDIUM | 2-col |
| S10 | Testimonials | `sections/testimonials.blade.php` | [x] Done | MEDIUM | 3-col |
| S11 | FAQ Accordion | `sections/faqs.blade.php` | [x] Reviewed — no changes needed | MEDIUM | 1-col |
| S12 | Newsletter | `sections/newsletter.blade.php` | [x] Done | HIGH | 2-col |
| S13 | Blog Preview | `sections/blog_preview.blade.php` | [x] Done | MEDIUM | 3-col |
| S14 | Contact CTA | `sections/contact_cta.blade.php` | [x] Done | MEDIUM | 2-col |

---

### CAT 3 — Search Pages

| # | Page | File | Status | Priority |
|---|------|------|--------|----------|
| P1 | Parts Search Console | `frontend/search/console.blade.php` | [ ] | CRITICAL |
| P2 | Search Results | `frontend/search/results.blade.php` | [ ] | CRITICAL |
| P3 | Zero Results | `frontend/search/zero-results.blade.php` | [ ] | HIGH |

---

### CAT 4 — Browse / Catalog Pages

| # | Page | File | Status | Priority |
|---|------|------|--------|----------|
| P4 | Manufacturers Index | `frontend/manufacturer/index.blade.php` | [ ] | HIGH |
| P5 | Manufacturer Detail | `frontend/manufacturer/show.blade.php` | [ ] | HIGH |
| P6 | Car Models Index | `frontend/car-model/index.blade.php` | [ ] | HIGH |
| P7 | Car Model Detail | `frontend/car-model/show.blade.php` | [ ] | HIGH |

---

### CAT 5 — Cart

| # | Page | File | Status | Priority |
|---|------|------|--------|----------|
| P8 | Shopping Cart | `frontend/cart/index.blade.php` | [ ] | HIGH |

---

### CAT 6 — Checkout Flow

| # | Page | File | Status | Priority |
|---|------|------|--------|----------|
| P9 | Checkout Layout | `frontend/checkout/layout.blade.php` | [ ] | HIGH |
| P10 | Step 1 — Contact | `frontend/checkout/step1.blade.php` | [ ] | HIGH |
| P11 | Step 2 — Address | `frontend/checkout/step2.blade.php` | [ ] | HIGH |
| P12 | Step 3 — Shipping | `frontend/checkout/step3.blade.php` | [ ] | HIGH |
| P13 | Step 4 — Review | `frontend/checkout/step4.blade.php` | [ ] | HIGH |
| P14 | Step 5 — Terms | `frontend/checkout/step5.blade.php` | [ ] | HIGH |
| P15 | Payment | `frontend/checkout/payment.blade.php` | [ ] | HIGH |
| P16 | Payment Return | `frontend/checkout/payment-return.blade.php` | [ ] | MEDIUM |
| P17 | Payment Failed | `frontend/checkout/payment-failed.blade.php` | [ ] | HIGH |
| P18 | Thank You / Confirmation | `frontend/checkout/thank-you.blade.php` | [ ] | HIGH |

---

### CAT 7 — Authentication Pages

| # | Page | File | Status | Priority |
|---|------|------|--------|----------|
| P19 | Auth Modal (Login + Register) | `components/modals/auth-modal.blade.php` | [ ] | HIGH |
| P20 | Password Reset Request | `auth/passwords/email.blade.php` | [ ] | HIGH |
| P21 | Password Reset Form | `auth/passwords/reset.blade.php` | [ ] | HIGH |

---

### CAT 8 — Customer Account

| # | Page | File | Status | Priority |
|---|------|------|--------|----------|
| P22 | Account Dashboard | `frontend/account/dashboard.blade.php` | [ ] | MEDIUM |
| P23 | Orders List | `frontend/account/orders.blade.php` | [ ] | MEDIUM |
| P24 | Order Detail | `frontend/account/order-detail.blade.php` | [ ] | MEDIUM |
| P25 | Addresses | `frontend/account/addresses.blade.php` | [ ] | MEDIUM |
| P26 | Address Form | `frontend/account/address-form.blade.php` | [ ] | MEDIUM |
| P27 | Refunds | `frontend/account/refunds.blade.php` | [ ] | MEDIUM |
| P28 | Refund Form | `frontend/account/refund-form.blade.php` | [ ] | MEDIUM |
| P29 | Account Settings | `frontend/account/settings.blade.php` | [ ] | MEDIUM |

---

### CAT 9 — Blog

| # | Page | File | Status | Priority |
|---|------|------|--------|----------|
| P30 | Blog Index | `frontend/blog/index.blade.php` | [ ] | MEDIUM |
| P31 | Blog Post | `frontend/blog/show.blade.php` | [ ] | MEDIUM |

---

### CAT 10 — Informational / Contact

| # | Page | File | Status | Priority |
|---|------|------|--------|----------|
| P32 | Contact Form | `frontend/contact/show.blade.php` | [ ] | MEDIUM |
| P33 | CMS Page | `frontend/page.blade.php` | [ ] | MEDIUM |
| P34 | HTML Sitemap | `frontend/sitemap.blade.php` | [ ] | LOW |

---

### CAT 11 — Error Pages

| # | Page | File | Status | Priority |
|---|------|------|--------|----------|
| P35 | 401 Unauthorized | `errors/401.blade.php` | [ ] | LOW |
| P36 | 403 Forbidden | `errors/403.blade.php` | [ ] | LOW |
| P37 | 404 Not Found | `errors/404.blade.php` | [ ] | LOW |
| P38 | 419 CSRF Expired | `errors/419.blade.php` | [ ] | LOW |
| P39 | 429 Rate Limited | `errors/429.blade.php` | [ ] | LOW |
| P40 | 500 Server Error | `errors/500.blade.php` | [ ] | LOW |
| P41 | Maintenance Mode | `errors/maintenance.blade.php` | [ ] | LOW |

---

### CAT 12 — Email Templates (24 files)

| # | Template | Status |
|---|----------|--------|
| E1 | Order Confirmation | [ ] |
| E2 | Order Shipped | [ ] |
| E3 | Order Status Update | [ ] |
| E4 | Refund Processed | [ ] |
| E5 | Refund Status Update | [ ] |
| E6 | Password Reset | [ ] |
| E7 | OTP Verification | [ ] |
| E8 | Welcome | [ ] |
| E9 | Contact Reply | [ ] |
| E10 | Part Inquiry Received | [ ] |
| E11 | Newsletter Confirmation | [ ] |
| E12 | Newsletter Campaign | [ ] |
| E13 | Abandoned Cart | [ ] |
| E14 | Email Layout (base) | [ ] |

---

## 6. CURRENT PROGRESS

```
Overall Progress: 25 / 62 items complete (40%)

Category Progress:
├── CAT 1 Global Elements:  10 / 20  (50%) ← IN PROGRESS (remaining: G5,G6,G8,G11,G12,G14,G15,G16,G17)
├── CAT 2 Homepage:         15 / 15  (100%) ✓ COMPLETE
├── CAT 3 Search:            0 / 3   (0%)  ← NEXT
├── CAT 4 Browse/Catalog:    0 / 4   (0%)
├── CAT 5 Cart:              0 / 1   (0%)
├── CAT 6 Checkout:          0 / 10  (0%)
├── CAT 7 Auth:              0 / 3   (0%)
├── CAT 8 Account:           0 / 8   (0%)
├── CAT 9 Blog:              0 / 2   (0%)
├── CAT 10 Informational:    0 / 3   (0%)
├── CAT 11 Errors:           0 / 7   (0%)
└── CAT 12 Emails:           0 / 14  (0%)
```

---

## 7. COMPLETED TASKS

### CAT 1 — Global Elements (Session 1 — 2026-06-11)

- [x] **G3 layouts/app.blade.php** — Fixed body background from `bg-bg-page` (#F8FAFC cool) to `bg-ivory` (#F7F3E7 warm) to match Blueprint design. Added `settings('seo.default_description')` fallback to meta description. Redesigned skip nav to Blueprint style (ink/ivory, amber ring, mono font).
- [x] **G1 components/navbar.blade.php** — Fixed CHECKOUT button in mini-cart (was `/cart#checkout`, now `/checkout` route). Fixed empty cart panel hardcoded header text to use `settings()`. Added `@click.away="mobileOpen = false"` to close mobile menu when clicking outside.
- [x] **G2 components/footer.blade.php** — Wrapped all column headings and link labels in `__()` for multi-language support (Catalogue, Account, Contact, Languages, Phone, Email, Hours, Payments, Search by OEM, Browse Brands, Journal, Contact, Dashboard, Orders, Addresses, Refunds, Sign in, Register, Basket). Fixed default payment methods: removed STRIPE (not used), added BANK TRANSFER.
- [x] **G4 components/modals/auth-modal.blade.php** — CRITICAL BUG FIXED: Register success redirect hardcoded `/en/account/dashboard` → now uses `$lang` variable. Added `x-trap.noscroll.inert="show"` for focus lock (uses @alpinejs/focus already imported). Added `aria-controls` + `id` to tabs and tabpanels. Added `inputmode="email"` and `autocomplete="email"` to reg email. Added `autocomplete="new-password"` to both password fields. Translated tab labels.
- [x] **G7 components/ui/toast.blade.php** — Error toasts now use dynamic `:role="toast.type === 'error' ? 'alert' : 'status'"` and `:aria-live="toast.type === 'error' ? 'assertive' : 'polite'"` for proper screen reader announcement urgency.
- [x] **G9 components/ui/breadcrumb.blade.php** — Added `aria-current="page"` to the current page span (last breadcrumb item without a URL).
- [x] **G10 components/ui/pagination.blade.php** — Reviewed. Already has `aria-current="page"`, `rel="prev"/"next"`, proper `aria-label` on all controls, accessible disabled states. No changes needed.
- [x] **G13 components/cookie-consent.blade.php** — Fixed `duration-400` (non-standard Tailwind) to `duration-300`. Fixed `save()` to also set `cookie_consent_accepted` in localStorage so the banner doesn't reappear after saving preferences. Improved Decline button border opacity (`border-ivory/25` → `border-ivory/50`) for GDPR button parity.
- [x] **G18 Announcement bar** — Reviewed inside app.blade.php. Correctly uses Alpine.js localStorage dismiss, CSP nonce, and settings-driven colors. No changes needed.
- [x] **G20 Skip navigation** — Updated to Blueprint design style (ink bg, ivory text, amber focus ring, mono font) from the mismatched `bg-navy rounded-lg` style.

---

## 8. REMAINING TASKS

**NEXT UP: Category 3 — Search Pages**

Remaining CAT 1 items (defer — lower priority than search flow):
- G5 — components/modals/otp-modal.blade.php
- G6 — components/modals/part-inquiry.blade.php
- G8 — components/ui/alert.blade.php
- G11, G12, G16 — Badge, Condition Badge, Star Rating
- G14 — components/language-switcher.blade.php
- G15 — components/section-heading.blade.php
- G17 — components/account/shell.blade.php

**Category 3 — Search Pages** (core product flow, next highest impact):
- P1 — frontend/search/console.blade.php
- P2 — frontend/search/results.blade.php
- P3 — frontend/search/zero-results.blade.php

---

## 9. BLOCKED TASKS

*(none currently)*

---

## 10. PER-PAGE REVIEW TEMPLATE

Apply this checklist to every page/component:

```
### [Page/Component Name]
File: path/to/file.blade.php
Reviewed: [date]
Status: [ ] Not Started / [~] In Progress / [x] Done

**UX Findings:**
- ...

**Content Findings:**
- ...

**SEO Findings:**
- Title: ...
- Meta description: ...
- H1-H6 structure: ...
- Structured data: ...
- Internal linking: ...

**Performance Findings:**
- LCP element: ...
- CLS risks: ...
- Render-blocking: ...
- Font loading: ...

**Security Findings:**
- CSRF: ...
- Output escaping: ...
- Form security: ...

**Design Findings:**
- Spacing: ...
- Contrast (WCAG AA): ...
- Mobile/Tablet/Desktop: ...

**Recommended Improvements:**
| Priority | Issue | Action |
|----------|-------|--------|
| CRITICAL | ... | ... |
| HIGH | ... | ... |
| MEDIUM | ... | ... |
| LOW | ... | ... |

**Changes Made:**
- ...
```

---

## 11. DECISIONS MADE

| Date | Decision | Reason |
|------|----------|--------|
| 2026-06-11 | No redesign — improve existing Blueprint design | System is production-ready; changes must improve not replace |
| 2026-06-11 | All SEO tags remain server-side Blade only | CLAUDE.md rule — never JavaScript-rendered |
| 2026-06-11 | No dark mode on storefront | CLAUDE.md rule — locked to ivory theme |
| 2026-06-11 | All page content must maintain card/grid balance | Content must fit layout, not force layout changes |

---

## 12. SESSION NOTES

### Session 1 — 2026-06-11

**Completed this session:**
- Full frontend inventory completed via automated exploration
- FRONTEND_MASTER_TASKS.md created (this file)
- Master plan established

**Key findings from exploration:**
- 191 Blade files total; 35 frontend pages; 71 components; 24 email templates; 7 error pages
- navbar.blade.php: Very well structured. Sticky, mobile-responsive, mini-cart dropdown with AJAX, correct Alpine.js usage. Aria labels present. Minor issues to review: cart CHECKOUT button links to `$cartUrl#checkout` instead of checkout route.
- footer.blade.php: Excellent Industrial Blueprint implementation. 4-column grid, trust row, oversize wordmark, legal nav. Footer Column 1-4 labels (Catalogue, Account, Contact, Languages) are hardcoded strings — not translatable.
- layouts/app.blade.php: Head is complete — CSRF, OG tags, Twitter Card, hreflang (server-side), JSON-LD yield, favicons, Vite assets, CSP nonce. Preloader is feature-rich. Skip nav present. One finding: `<body>` uses `bg-bg-page` but Blueprint pages use `bg-ivory` — may cause flash on paint. Toast is loaded AFTER footer scripts stack — correct order.

**Next session:** Begin CAT 1 remaining lower-priority components (OTP modal, alert, language-switcher, account/shell), then move to CAT 2 Homepage — hero section and all 13 CMS sections.

### Session 1 — Changes Summary
| File | Changes |
|------|---------|
| `layouts/app.blade.php` | Body bg fix, meta description default, skip nav Blueprint styling |
| `components/navbar.blade.php` | Checkout link fix, empty cart settings text, mobile menu click-away |
| `components/footer.blade.php` | i18n wrapping (all strings), payment methods default fix |
| `components/modals/auth-modal.blade.php` | Locale bug fix (critical), focus trap, ARIA tabs, autocomplete, inputmode |
| `components/ui/toast.blade.php` | Dynamic role/aria-live for error toasts |
| `components/ui/breadcrumb.blade.php` | aria-current="page" on current item |
| `components/cookie-consent.blade.php` | duration-400 fix, save() persistence fix, GDPR button parity |

---

### CAT 2 — Homepage (Session 2 — 2026-06-11)

- [x] **H1 frontend/home.blade.php** — Fixed SEO bug: SearchAction `urlTemplate` had literal `{lang}` variable (invalid — not in `query-input`); replaced with `{{ app()->getLocale() }}` for concrete locale value. Removed deprecated `actionPlatform` fields. Fixed Organization JSON-LD logo from hardcoded `/logo.svg` (may not exist) to `settings('general.logo_url', url('/favicon.svg'))`. Localized BreadcrumbList "Home" with `__('Home')`.
- [x] **S1 sections/hero.blade.php** — Added `prefers-reduced-motion` check to typewriter animation (`x-init` now checks `matchMedia` before running; shows first placeholder statically for reduced-motion users). Translated "How it works" anchor text with `__()`.
- [x] **S2 sections/trust_bar.blade.php** — Removed redundant `role="listitem"` from `<li>` elements (implicit role, adding it is bad practice).
- [x] **S3 sections/stats_counter.blade.php** — Translated "Live" and "Source · Verified · EU" with `__()`. Added `aria-label="{{ $displayValue }} {{ label }}"` to each stat card `<div>` so screen readers announce the number and its meaning together.
- [x] **S4 sections/featured_brands.blade.php** — Translated "§ Index by letter", "Source · OEM manufacturer catalogues · EU", and "pcs" unit label with `__()`.
- [x] **S5 sections/popular_searches.blade.php** — Translated `aria-label` for the `<ol>` element, all 4 column header labels (Rank, OEM · Number, Frequency, Hits), and the "Hot" badge with `__()`.
- [x] **S6 sections/how_it_works.blade.php** — Added `id="how-it-works"` to `<section>` element, fixing the broken anchor link from hero footer strip. Translated "§ Step", "NEXT", and "COMPLETE" connector labels with `__()`.
- [x] **S7 sections/banner.blade.php** — Reviewed. Feature card content is hardcoded PHP arrays — content-heavy B2B copy. Deferred to CMS migration. No template changes.
- [x] **S8 sections/shipping_info.blade.php** — Translated "§ Trusted Carriers", "EU · Tracked · Insured", "Carrier", "Fully Insured", "Real-time Tracking", "Free Returns" with `__()`.
- [x] **S9 sections/part_inquiry.blade.php** — Translated form labels "§ OEM Part Number", "§ Email address"; error messages "OEM part number is required.", "Email address is required."; security strip "Secure · TLS 1.3 · Response within 24 h"; success state "§ Status · Received", "Inquiry logged", "We will review your request...", "Submit another inquiry"; and loading state "Transmitting..." with `__()`.
- [x] **S10 sections/testimonials.blade.php** — Removed redundant `role="article"` from `<article>` element. Translated "Verified" badge and title attribute with `__()`.
- [x] **S11 sections/faqs.blade.php** — Reviewed. All content uses `trans_field()` from CMS. FAQPage JSON-LD is correctly server-side rendered. "Manual · Index · X entries" is decorative spec text — acceptable as-is. No changes needed.
- [x] **S12 sections/newsletter.blade.php** — Translated form label "§ Enter email address"; GDPR trust text; loading state "Transmitting"; success state "§ Status · Confirmed", "Subscription logged", "Check inbox · confirmation email sent" with `__()`.
- [x] **S13 sections/blog_preview.blade.php** — Translated "Read" link text and "Journal · Editorial · Updated weekly" footer spec with `__()`.
- [x] **S14 sections/contact_cta.blade.php** — Translated dt labels "Email", "Phone", "Hours", "SLA". Changed hardcoded "Mon-Fri · 09:00-18:00 CET" to `settings('general.business_hours', ...)`. Changed hardcoded "< 24 h response" to `settings('general.support_sla', ...)`. Translated "Call now" with `__()`.

### Session 2 — Changes Summary
| File | Changes |
|------|---------|
| `frontend/home.blade.php` | SearchAction urlTemplate fix (SEO), Organization logo fix, BreadcrumbList i18n |
| `sections/hero.blade.php` | prefers-reduced-motion for typewriter, "How it works" i18n |
| `sections/trust_bar.blade.php` | Remove redundant role="listitem" |
| `sections/stats_counter.blade.php` | "Live" + "Source · Verified · EU" i18n, a11y aria-label on stat cards |
| `sections/featured_brands.blade.php` | "Index by letter", "Source…", "pcs" i18n |
| `sections/popular_searches.blade.php` | ol aria-label, column headers, "Hot" badge i18n |
| `sections/how_it_works.blade.php` | id="how-it-works" (fixes broken anchor), "Step"/"NEXT"/"COMPLETE" i18n |
| `sections/shipping_info.blade.php` | Trust strip and carrier labels i18n |
| `sections/part_inquiry.blade.php` | Form labels, error messages, success state, security strip i18n |
| `sections/testimonials.blade.php` | Remove redundant role="article", "Verified" badge i18n |
| `sections/newsletter.blade.php` | Form label, GDPR text, loading/success state i18n |
| `sections/blog_preview.blade.php` | "Read" link, footer spec i18n |
| `sections/contact_cta.blade.php` | dt labels i18n, business hours + SLA from settings, "Call now" i18n |
