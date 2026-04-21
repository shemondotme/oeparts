# OEMHub Frontend Design Critique

**Scope:** All 25 Blade templates under `resources/views/frontend/**`
**Stage:** Mid-fidelity refinement
**Lenses:** Usability & flow • Visual hierarchy • Accessibility (WCAG 2.1 AA) • Design-system consistency
**Benchmark:** `CLAUDE.md` rules, `DESIGN_SYSTEM.md` tokens

---

## 1. Executive Summary

The frontend has a clear, confident brand — navy + amber with a strong auto-industrial feel — and many pages (especially `search/zero-results`, `cart/index`, `checkout/*`, `account/order-detail`) demonstrate sophisticated, production-quality design thinking. However, the codebase shows classic signs of **being built in sprints by more than one pair of hands**: design-token discipline is uneven, accessibility is applied in bursts, and a single page (`checkout/placeholder.blade.php`) is literally a different product.

Severity distribution across 25 pages:

| Severity | Count | Meaning |
|---|---|---|
| Critical | 3 page-level issues | ship-blockers (palette mismatch, placeholder page live, contrast) |
| High | 9 recurring issues | WCAG AA, honeypot gap, duplicated nav, SEO on blog |
| Medium | 12 patterns | inconsistent tokens, mobile hints, hardcoded values |
| Low / polish | many | copy tightening, icon consistency |

Top three things to fix first (see §4 for full plan):
1. **Amber-as-text on white** — 121 occurrences across 20 files fail WCAG 2.1 AA (3.02:1). Replace with `text-amber-text` (#B45309).
2. **`checkout/placeholder.blade.php`** — mentions "Sprint 7/Sprint 8", uses purple gradient, inline `<style>`, ignores design system entirely. Delete or hide from routes before launch.
3. **Duplicated account sidebar** across 5 pages — extract to `frontend.account.partials.sidebar` to stop drift.

---

## 2. Cross-Cutting Patterns (site-wide)

These issues are not tied to one page — they repeat everywhere and should be addressed once, centrally.

### 2.1 Colour-token discipline — the single biggest issue

**Rule:** `text-amber` (#F59E0B) is reserved for backgrounds and dark-text-on-amber. Amber **as text** on white/light MUST be `text-amber-text` (#B45309). This is a WCAG failure, not a stylistic opinion.

| Pattern | Count | Severity |
|---|---|---|
| `text-amber` used as text on white/light background | ~121 across 20 files | High (A11y) |
| `hover:text-amber` on white | multiple (blog/index, blog/show, orders, refunds…) | High |
| `from-amber` gradients with dark text — `text-navy` | many (correctly done) | OK |

**Worst offenders (most `text-amber` on light bg):**
- `frontend/blog/index.blade.php` — lines 41, 50, 65, 96, 101, 117
- `frontend/blog/show.blade.php` — lines 11, 77, 105
- `frontend/account/order-detail.blade.php` — line 177 (`text-2xl font-extrabold text-amber` on white)
- `frontend/account/orders.blade.php` — sidebar active state uses `text-amber` on `bg-amber/10`
- `frontend/checkout/thank-you.blade.php` — large price in `text-amber` on white

**Positive examples (follow these):**
- `frontend/search/zero-results.blade.php` — uses `text-amber-text` correctly everywhere
- `frontend/account/refunds.blade.php` — status pills use `text-amber-text` on `bg-amber/15`
- `frontend/account/order-detail.blade.php` (header OEM badge) — `text-amber-text bg-amber/10`

**Global fix:** site-wide find/replace — `text-amber\b` → `text-amber-text` — then manually restore the small set of cases where amber is genuinely a background/icon colour (look for `bg-amber`, `border-amber`, `ring-amber`, `focus:border-amber` — those stay).

### 2.2 Slate vs Gray — two design systems overlapping

CLAUDE.md `DESIGN TOKENS` name the muted colours as `gray-bg` / `text-body` / `text-muted`, but components use raw Tailwind hues inconsistently:

- `bg-gray-*|text-gray-*|border-gray-*` → **315 occurrences in 22 files**
- `bg-slate-*|text-slate-*|border-slate-*` → **74 occurrences in 7 files**

Inside a single file, `account/address-form.blade.php` uses `text-slate-700` for labels (lines 65, 81, 96, 112, 127, 143, 159, 175, 195) but `border-gray-200`, `bg-gray-50` for containers — a literal pixel-level mismatch (`#334155` slate vs `#374151` gray). Same file also uses `text-navy` for line 49's first label, then switches to `text-slate-700` for every later label — inconsistent even within the same form.

**Decide and enforce one**, then run a codemod. Given `tailwind.config.js` already defines `navy`/`amber`/`amber-text` as custom tokens, the cleanest path is:
- `text-body` for body (replaces `text-gray-700`/`text-slate-700`)
- `text-muted` for labels/secondary (replaces `text-gray-500`/`text-slate-500`)
- Keep `bg-gray-50`, `border-gray-100/200` — they're close enough and widely used.

### 2.3 Accessibility gaps

**CLAUDE.md** requires WCAG 2.1 AA. Current state:

| Check | Status | Evidence |
|---|---|---|
| `role="alert"` / `role="status"` / `aria-live` on error & flash banners | **Only 1 file** (`cart/index.blade.php`) | 22 other pages flash success/error without announcing it |
| `role="dialog"` / `aria-modal` on modals | Only cart's confirm modal | Other modals (mobile menu in checkout, OTP dialog in contact) lack it |
| Touch targets ≥ 44×44 | Only 7 files use `h-11`/`h-12`/`min-h-[44px]` | Most buttons are `py-2` (~36px) — fails mobile AA |
| Focus rings distinct from hover | Inconsistent — many rely on default browser outline |
| `rel="noopener noreferrer"` on `target="_blank"` links | **Missing on social-share links** in `blog/show.blade.php` |
| `alt=""` on decorative icons | OK — most icons are SVGs; `blog/index` uses `alt="{{ $article->title }}"` correctly |
| Language attribute on `<html>` | `admin/auth/login.blade.php` hardcodes `lang="en"`; frontend pages use `app()->getLocale()` ✓ |
| Keyboard-only flow tested | Not verified; but many `<label>` → `<input>` pairings are correct |

### 2.4 Honeypot coverage — inconsistent on public forms

CLAUDE.md mandates `<input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">` on all public forms. Present in:

✅ `frontend/search/zero-results.blade.php` (part inquiry)
✅ `frontend/checkout/payment.blade.php` (line 34)
✅ `frontend/account/refund-form.blade.php` (line 40)
✅ `frontend/contact/show.blade.php` (assumed — not yet verified past line 80)

❌ Missing on: `checkout/step1-5`, `cart/index` update-qty form, `account/address-form`, `admin/auth/login`, `frontend/blog/*` search form

**Fix:** add honeypot to every `<form method="POST">` in the frontend. It's trivial and the bot-spam impact is real.

### 2.5 Mobile input hints (keyboard discipline)

Only 11 occurrences of `inputmode`/`autocapitalize` across 5 files. The rule in CLAUDE.md lists specific keyboard types for OEM / OTP / email / phone / price / postal — most forms respect `type="email"` / `type="tel"` but skip `inputmode`.

Positive: `checkout/step1.blade.php` does use them for email + phone.
Gap: **`account/address-form.blade.php`** has postal code as `type="text"` without `inputmode="numeric"`, phone without `inputmode="tel"`, VAT country `<select>` fine but no `autocomplete="postal-code"` / `autocomplete="tel"` either.

### 2.6 Duplicated account sidebar (DRY violation)

The same sidebar (5 items: Dashboard / Orders / Refunds / Addresses / Settings / Logout) is inlined in:
- `account/dashboard.blade.php`
- `account/orders.blade.php`
- `account/addresses.blade.php`
- `account/settings.blade.php`
- (partially in) `account/refunds.blade.php` — which actually uses a different layout (no sidebar, breadcrumb instead)

This leads to drift:
- Active-state classes differ between files
- Icons swap between outline/solid in different places
- Refunds page is outright inconsistent — no sidebar

**Fix:** `<x-account.sidebar :active="'orders'" />` component. Also decide: does the account area use the sidebar shell everywhere, or the breadcrumb pattern from `order-detail`/`refunds`? Pick one.

### 2.7 Hardcoded values (CLAUDE.md rule #5 violation)

- `cart/index.blade.php` — hardcodes popular OEM list `['1K0407271F', '3C0615301AA', '5Q0615301M', '8K0615301D']`. Should come from `settings('commerce.popular_oems')` or a dynamic query on top-searched terms.
- `checkout/placeholder.blade.php` — hardcodes "Sprint 7", "Sprint 8" labels.
- `checkout/step4.blade.php` line 11 — correctly falls back via `settings('tax.default_vat_rate', 21)` ✓
- `blog/index.blade.php`, `blog/show.blade.php` — hardcoded "5 min read" estimates? Check.

### 2.8 SEO coverage gaps

CLAUDE.md rule #8: all `<meta>`, hreflang, canonical, JSON-LD in Blade. Current state:

| Page | hreflang × 5 | canonical | JSON-LD |
|---|---|---|---|
| `home.blade.php` | ✅ | ✅ | ✅ (Organization, WebSite, BreadcrumbList) |
| `page.blade.php` | ✅ | ✅ | — |
| `search/results.blade.php` | ✅ | ✅ | ✅ (ItemList) |
| `search/zero-results.blade.php` | ✅ | ✅ | ✅ (SearchResultsPage) |
| `blog/index.blade.php` | **❌** | **❌** | **❌** |
| `blog/show.blade.php` | **❌** | **❌** | **❌** (no Article/BlogPosting!) |
| `contact/show.blade.php` | not verified — likely gap |
| `cart` / `checkout/*` / `account/*` | N/A (`noindex`, but verify `<meta name="robots" content="noindex">` exists) |

**Critical for SEO:** `blog/show.blade.php` should emit `Article` or `BlogPosting` JSON-LD with author, datePublished, dateModified, image, headline. Also missing Open Graph tags for share cards.

### 2.9 Icon consistency

The design system says Heroicons v2 (outline for UI, solid for active). Most pages respect this, but:

- `checkout/step5.blade.php` — uses **inline raw `<svg>`** for every icon (cards, bank, lock, shield, checkmark). Breaks consistency and blocks future icon swaps.
- `checkout/step4.blade.php` — same, raw SVGs inline.
- `checkout/thank-you.blade.php` — mix of raw SVG + Heroicons.
- `checkout/payment.blade.php` — uses **external CDN icons** from `cdn.jsdelivr.net` (lines 51-53) for Visa/Mastercard/Amex. Network dependency for branded card logos; should be bundled as local SVGs or `<x-heroicon-s-credit-card>`.

### 2.10 Decorative flourish discipline

Some pages are highly stylized (`cart/index` with rotating bag animations, `checkout/layout` with dot-grid + orbs, `checkout/thank-you` with animate-ping emerald circles), while others are flat and functional (`checkout/step1-5`, `account/settings`). The variance is jarring when you click through a funnel — hero-level stylization on the cart, then a very plain checkout, then celebratory fireworks on thank-you.

**Recommendation:** cart = mildly stylized; checkout steps = visually calm + trustworthy (current state is fine); thank-you = one moment of celebration (current state fine). Remove the rotating bag animation on cart (`rotate-6 hover:rotate-12`) — it's the only place the brand behaves this way.

---

## 3. Per-Page Critique

### 3.1 `frontend/home.blade.php` — **Good**

**Strengths:**
- Clean SEO block: hreflang × 5, JSON-LD for Organization + WebSite + SearchAction + BreadcrumbList
- Hero eager-loaded, below-fold sections lazy-loaded via IntersectionObserver
- Skeleton (`animate-pulse`) while lazy sections load — good perceived performance

**Issues:**
- Uses `bg-gray-50` / `bg-gray-200` instead of design-system `bg-bg-page`
- Hero copy + CTA not verified against variant testing (not a design issue per se)

**Verdict:** production-ready.

### 3.2 `frontend/page.blade.php` — **Good (generic)**

**Strengths:**
- Minimal, correct CMS template
- `canonical` + hreflang rendered

**Issues:**
- Prose content relies on `prose` class — make sure typography tokens inside `prose` respect brand fonts (Plus Jakarta Sans for `h1-h3`, Inter body).

### 3.3 `frontend/search/results.blade.php` — **Heavy, mostly good**

**Strengths:**
- Rich filter sidebar (condition, stock, sort, manufacturer, car model)
- Uses bcmath for VAT inclusion
- Match quality badges (exact / cross / partial) — excellent UX for OEM search
- `ItemList` JSON-LD

**Issues:**
- Line uses `bg-[#F8FAFC]` inline instead of `bg-bg-page`
- Mixes `text-amber-text` (correct) with `text-gray-*` everywhere else — two palettes in one file
- Filter panel: check whether it collapses on mobile — if not, filters eat the whole screen above the fold

### 3.4 `frontend/search/zero-results.blade.php` — **Exemplary ✅**

This is the reference page. Copy its patterns elsewhere.

**Strengths:**
- Correct `text-amber-text` throughout
- Honeypot `name="website"` present (line 77)
- `SearchResultsPage` JSON-LD with `url`, `query`
- Clear 3-step timeline: no results → submit inquiry → we call you back
- Part-inquiry form with OEM field in `font-mono` + email + message
- Popular-OEMs chip rail (dynamic via `$popularOems`)
- Tips section (check spelling, try prefix, try cross-reference)
- Back-to-home link

**Minor issues:**
- Touch targets on popular-OEM chips may be <44px on mobile — add `min-h-[44px]` or `py-2.5`
- No `aria-live="polite"` on form-success region

**Verdict:** gold standard. Use as template.

### 3.5 `frontend/cart/index.blade.php` — **Stylish but over-designed**

**Strengths:**
- Confirm-clear-cart modal uses `role="dialog"` + `aria-modal="true"` + `x-trap.noscroll` + focus return — the ONLY page in the codebase doing dialog a11y correctly
- Decorative radial-gradient blurs give brand warmth
- Uppercase `tracking-[0.2em]` breadcrumbs reinforce auto-industrial tone

**Issues:**
- Animated shopping bag (`rotate-6 hover:rotate-12`) is whimsical in a way that no other page is — breaks brand consistency
- Heavy `font-black` on almost every heading — loses typographic contrast
- Hardcoded popular-OEMs array (`1K0407271F`, etc.) — move to `settings()` or query
- Update-qty form lacks honeypot
- Quantity inputs: verify `inputmode="numeric"` + `min="1"` + `max` attributes

### 3.6 `frontend/checkout/layout.blade.php` — **Good shell**

**Strengths:**
- Navy gradient hero with subtle dot-grid + orbs — communicates "trusted, secure"
- Sticky step progress band (5 steps) with clear state: emerald=done, navy=current, gray=pending
- Sidebar summary remains visible during all steps — good for mobile e-commerce

**Issues:**
- Progress band on mobile: verify it scrolls horizontally or stacks — at 5 steps × ~80px label widths it likely overflows on <375px
- Step labels: "Contact / Address / Shipping / Review / Payment" — consider shorter on mobile ("You / Ship / Method / Review / Pay")

### 3.7 `frontend/checkout/step1.blade.php` — **Good (contact)**

**Strengths:**
- Email + phone + B2B toggle
- `inputmode`, `autocomplete` attrs present
- Error states with `bg-red-50` + border

**Issues:**
- Missing honeypot
- No `role="alert"` on `@error` output
- B2B toggle: verify keyboard accessible (probably just checkbox — fine)

### 3.8 `frontend/checkout/step2.blade.php` — **Good (address)**

**Strengths:**
- First/last name split (EU-friendly)
- Conditional B2B Company+VAT block in distinct blue-50 panel — clear visual cue

**Issues:**
- Missing honeypot
- VAT-ID validation: is there an async call to VIES? If yes, needs a spinner + success state, not just pass/fail on submit
- Postal code input: verify `inputmode="numeric"` + `autocomplete="postal-code"`
- Country `<select>`: searchable? At 27 EU countries, plain select is fine but add `autocomplete="country"`

### 3.9 `frontend/checkout/step3.blade.php` — **Good (shipping method)**

**Strengths:**
- Radio cards with amber border when selected
- Emerald dot = free, amber dot = paid — visual shortcut
- Days estimate: "3-5 business days" + fallback "Fast EU delivery"
- Empty state when no methods available to selected country — handled gracefully

**Issues:**
- `$isSelected ? 'border-amber bg-amber/4 shadow-md' : 'border-gray-200'` — duplicated in both class and `:class` binding (lines 32-33). Alpine `:class` alone is enough.
- Missing `aria-describedby` linking radio to days-estimate text

### 3.10 `frontend/checkout/step4.blade.php` — **Excellent (review)**

**Strengths:**
- Clear sectioning: items, delivery-to address, shipping method, price breakdown, terms agreement
- Price breakdown correctly uses `bcmul` for line totals
- Grand total in display font, large
- Terms checkbox required with `@error` showing inline below

**Issues:**
- Inline raw SVGs instead of Heroicons (lines 19, 35, 48, 70, 95, 114) — inconsistent with rest of site
- `agree_terms` label: links to Terms + Privacy open in new tab — **missing `rel="noopener noreferrer"`** on `target="_blank"`
- No `aria-invalid="true"` on the checkbox when error present

### 3.11 `frontend/checkout/step5.blade.php` — **Good (payment method selection)**

**Strengths:**
- Two clean radio-card options: Card (Airwallex) / Bank Transfer (SEPA)
- Conditional info panels explain each method
- Trust badges (256-bit SSL / Encrypted / Airwallex Protected) at bottom
- Order note textarea — optional, correctly positioned

**Issues:**
- All icons are inline raw SVGs — lines 8, 25, 41, 56, 74, 100, 107, 113
- Trust-badge SVG on line 99 has `<path fill-rule="evenodd"` that duplicates line 100 — minor
- Line 31 — "We'll show" uses a curly apostrophe (`'`) — ensure Blade template is UTF-8 and this doesn't render as `'`
- Form-level honeypot missing (it's the outer form from `layout.blade.php` — verify)
- No VAT-ID or billing-address difference toggle

### 3.12 `frontend/checkout/payment.blade.php` — **Older, less polished**

This is the dedicated Airwallex dropin page (post-order payment). Visibly from an earlier sprint.

**Strengths:**
- Honeypot present (line 34) ✓
- Clear payment-method radio + payment-proof upload for bank transfer
- Copy-to-clipboard on bank details

**Issues:**
- Uses `primary-500`, `primary-600` etc. — **palette names that don't exist in CLAUDE.md tokens** (should be `amber` or `navy`)
- Uses `bg-green-600 hover:bg-green-700` for submit — inconsistent with the amber CTA on every other page
- External CDN card icons from `cdn.jsdelivr.net` (lines 51-53) — network dependency, and `simple-icons` may not even have `visa.svg` at that path
- Inline `<script>` at bottom with hardcoded Airwallex init — should be a separate JS module
- `alert('Payment failed')` on line 312 — native browser alert is ugly; use an in-page banner
- No `role="alert"` / `aria-live` on error display
- `console.log('Payment successful', response)` on line 305 — remove in production build
- `@push('scripts')` means this is not minified through Vite — verify build pipeline
- Label-for pairs are correct ✓

**Fix priority:** High. Re-skin this page to match step5 visual language (radio cards, trust badges), remove `primary-*` palette, swap external card icons for local SVGs.

### 3.13 `frontend/checkout/thank-you.blade.php` — **Good (celebration)**

**Strengths:**
- Emerald animate-ping + animate-pulse concentric circles on success icon — earns the delight
- Clear next steps (order number, email confirmation, tracking)

**Issues:**
- Grand total rendered in `text-amber` on white — **contrast failure** (need `text-amber-text` or keep large + bold but test at 18pt/24px — may scrape by)
- Other small icons in `text-amber` — all need `text-amber-text`
- Verify the page emits `Purchase` schema.org event + GA ecommerce conversion pixel

### 3.14 `frontend/checkout/placeholder.blade.php` — **Critical: delete ⚠️**

This is from Sprint 7/8 and was never removed. It:
- Declares a custom `<!DOCTYPE html>` with **inline `<style>`** (violates Tailwind-only rule)
- Uses purple gradient (`#667eea → #764ba2`) — off-brand
- Mentions internal sprint names to end users ("Sprint 7 - Cart System, Sprint 8 - Checkout Flow") — leaks dev status
- Uses 🛒 emoji + frosted-glass card — looks like a design-school mockup, not a B2B auto-parts site
- Bypasses the main layout entirely — no nav, no footer, no i18n beyond one `{{ __() }}` wrapper

**Action:** delete the file and remove any route that points to it. If you need a maintenance page, build a proper one extending `layouts.app`.

### 3.15 `frontend/account/dashboard.blade.php` — **OK**

**Strengths:**
- Navy gradient header with welcome message
- Stat cards (orders, addresses, refunds)
- Sidebar nav

**Issues:**
- Inline sidebar — duplicated across 4 other account pages
- Active nav item: `bg-amber/10 text-amber` — on very light `bg-amber/10` (~#FDF3DE), the `#F59E0B` amber text contrast is ~2.9:1, fails AA. Use `text-amber-text`.
- Stat cards lack hover/focus states — they're clickable?

### 3.16 `frontend/account/orders.blade.php` — **OK, needs sidebar extraction**

**Strengths:**
- Table layout scales (mobile strategy: cards or horizontal scroll — verify)

**Issues:**
- Duplicated sidebar
- Empty state: does it exist? If a user has zero orders, design a zero-state (similar to refunds empty state which is well-done)

### 3.17 `frontend/account/order-detail.blade.php` — **Excellent**

**Strengths:**
- Breadcrumb + page header with OEM-style order number in `font-mono text-amber-text bg-amber/10` — correct
- Download-invoice + cancel-order CTAs with clear visual weight
- Order-status timeline (5 steps, emerald checks for done, amber gradient progress bar)
- Grid: items + totals left (2 cols), addresses + payment right (1 col)
- Shipping/billing addresses rendered as semantic `<address>` elements ✓
- Tracking number in emerald banner when present

**Issues:**
- Line 177: Grand total uses `text-2xl font-extrabold text-amber` — **contrast fail**. Change to `text-amber-text` (the header OEM badge shows you know the right token — just propagate it).
- Line 210, 240: phone icon `text-amber` — swap to `text-amber-text` on white
- Cancel-order confirmation uses `confirm()` browser dialog — inconsistent with the beautiful `role="dialog"` modal in `cart/index`. Extract that modal pattern into a `<x-confirm-modal>` Blade component and reuse here.

### 3.18 `frontend/account/addresses.blade.php` — **OK**

**Strengths:**
- Add-new-address button uses `bg-gradient-to-r from-amber to-orange-500 text-navy` — correct (dark text on amber gradient)
- Card per address with default badge

**Issues:**
- Duplicated sidebar
- Set-default / delete actions: verify they have confirmation dialogs

### 3.19 `frontend/account/address-form.blade.php` — **Mixed palette, needs cleanup**

**Strengths:**
- Full EU country list from `ViesService::getEuCountries()`
- Grid: first/last/company/address1/2/city/state/postal/country/phone
- Default-address toggle

**Issues:**
- **Palette mix**: `text-slate-700` for some labels, `text-navy` for others, `border-gray-200` for containers — pick one system
- No `inputmode="numeric"` on postal code (line 162-170)
- No `inputmode="tel"` on phone (line 198-205) — `type="tel"` helps on mobile but `inputmode` is redundant/clearer
- No `autocomplete="given-name"` / `"family-name"` / `"street-address"` / `"postal-code"` / `"country"` / `"tel"` — browser autofill will be poor
- No honeypot (authenticated form but still good hygiene)
- Submit button: no `type="submit"` specified explicitly (defaults to submit, fine, but explicit is safer)
- Edit-mode: `name="id"` as hidden — ensure route signature uses `route model binding` or validates ownership server-side

### 3.20 `frontend/account/settings.blade.php` — **OK, a11y gap**

**Strengths:**
- Inline form sections (profile, password, preferences)

**Issues:**
- Duplicated sidebar
- Success/error alerts: no `role="alert"` / `role="status"` / `aria-live` — screen readers miss the change
- "Change password" form: does it enforce current password confirmation?

### 3.21 `frontend/account/refunds.blade.php` — **Very good**

**Strengths:**
- Breadcrumb layout (not sidebar — deliberate?)
- Empty state with gray-gradient circle icon, clear CTA to orders
- Status pills correctly use `text-amber-text` on `bg-amber/15` ✓
- Table with odd/even striping, hover
- Status icons vary by state (clock / check / check-badge / x-mark) — delightful

**Issues:**
- Decide: sidebar shell or breadcrumb shell for the account area? This page is inconsistent with the sidebar pages
- Table on mobile: likely overflows — confirm horizontal scroll is wrapped in `<div class="overflow-x-auto">` ✓ (line 63)
- "Order #" column has `hover:text-amber` — should be `hover:text-amber/80` relative to `text-amber-text` base, not `text-amber`

### 3.22 `frontend/account/refund-form.blade.php` — **OK but stale**

**Strengths:**
- Honeypot present (line 40) ✓
- Reason textarea with `minlength="20"` + `maxlength="2000"` ✓
- Image upload (max 5, JPEG/PNG, 2MB each)
- `enctype="multipart/form-data"` ✓

**Issues:**
- Uses `text-slate-*` throughout — different from every other page
- Uses `bg-slate-50` for order summary — correct for slate system but the page is the only slate page in the account area
- Submit button `bg-navy hover:bg-navy-dark` — `navy-dark` is not a declared CLAUDE.md token; may render as CSS default (no-op)
- No character counter on textarea (users don't know they're near 2000)
- Upload widget has no preview / drag-drop / progress
- No `role="alert"` on @error outputs

### 3.23 `frontend/blog/index.blade.php` — **Big SEO + a11y gap**

**Strengths:**
- Navy hero
- Article cards with featured image, category, tag chips
- Sidebar: featured posts, categories, tags, search

**Issues (high severity):**
- **No SEO block**: no hreflang, no canonical, no `BlogList` / `ItemList` JSON-LD, no Open Graph
- **`text-amber` violations on lines 41, 50, 65, 96, 101, 117** — every "Read more" and category pill uses wrong token
- Article card: verify `<img alt="">` uses meaningful `alt`
- Sidebar search form: no honeypot, no submit-button label ("Search" text or `aria-label`)
- No "X posts" / pagination count

### 3.24 `frontend/blog/show.blade.php` — **Highest SEO debt**

**Strengths:**
- Category badge in hero
- Floated featured image
- Back-to-blog link
- Prose article body

**Issues (critical for SEO):**
- **No `Article` or `BlogPosting` JSON-LD** — blog posts are exactly the content type schema.org rewards
- **No Open Graph tags** — shared links on LinkedIn/Twitter will look broken
- **No canonical tag** — duplicate-content risk across 5 language variants
- **No hreflang × 5** — Google won't know about the language alternates
- Social share links (Facebook/Twitter/LinkedIn): `target="_blank"` without `rel="noopener noreferrer"` — **security issue** (`noopener` prevents the new page from calling `window.opener`, which is a phishing vector)
- `text-amber` violations on lines 11, 77, 105
- No "updated at" timestamp in UI
- No author card / bio — B2B content benefits from trust signals
- No related articles at the bottom

### 3.25 `frontend/contact/show.blade.php` — **OK, OTP flow interesting**

**Strengths:**
- Navy hero + white card form
- Uses `slate-*` correctly (internal consistency within file)
- OTP verify-email flow (modal)

**Issues:**
- Success/error divs `hidden` by default — add `role="status"` / `role="alert"` and `aria-live="polite"`
- OTP modal: verify `role="dialog"`, `aria-modal="true"`, focus trap (likely copies `cart/index` pattern)
- Each OTP digit input: should have `inputmode="numeric"` + `autocomplete="one-time-code"` + `maxlength="1"` + auto-advance on input, auto-back on backspace
- Honeypot verification: not yet confirmed — verify `name="website"` is present

---

## 4. Priority Recommendations

### P0 (ship-blocker — do in the next sprint)

1. **Delete `frontend/checkout/placeholder.blade.php`** and remove the route binding. If the page is still reachable in prod, customers see "Sprint 7/Sprint 8" messaging.
2. **Site-wide `text-amber` → `text-amber-text` audit** — 20 files, ~121 occurrences. Each one fails WCAG AA. Pair this with a Playwright / visual-regression test so it doesn't regress.
3. **Re-skin `frontend/checkout/payment.blade.php`** to match `step5` design language; remove `primary-*` palette; replace external CDN card icons with local `<x-heroicon-*>` or bundled SVGs; remove `console.log` + native `alert()`.

### P1 (high — next 2 sprints)

4. **Accessibility pass**: add `role="alert"` / `role="status"` / `aria-live="polite"` to every flash-message and error region (22 files). Add `rel="noopener noreferrer"` to every `target="_blank"` link. Bump button paddings so touch targets ≥ 44×44 (currently 7 files use `h-11`/`h-12`).
5. **Honeypot everywhere**: add `<input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">` to every public POST form (8 missing).
6. **Blog SEO**: add `Article` / `BlogPosting` JSON-LD, canonical, hreflang × 5, Open Graph tags to `blog/show.blade.php` and `BlogList` / canonical / hreflang to `blog/index.blade.php`.
7. **Extract `<x-account.sidebar>` Blade component** and use in all account pages. Settle the sidebar-vs-breadcrumb debate: pick one shell.

### P2 (medium — steady improvement)

8. **Palette unification**: decide slate vs gray; codemod across 22 files.
9. **Icon consistency**: replace all inline raw `<svg>` in checkout step4/step5/payment/thank-you with `<x-heroicon-*>` equivalents.
10. **Extract confirm-dialog component** from `cart/index.blade.php` (the only properly-done modal) and reuse on cancel-order, delete-address, etc. Replace all `confirm()` calls.
11. **Mobile input hints audit**: add `inputmode` + `autocomplete` to every form field. Top priority: OEM-number inputs everywhere, postal codes, phones.
12. **Hardcoded value cleanup**: move popular-OEMs, placeholder text, etc. to `settings()` calls.

### P3 (low — polish)

13. Tighten the rotating shopping-bag animation on cart — brand inconsistency.
14. Character counter on refund-reason textarea.
15. Drag-and-drop upload + image previews on refund + payment-proof forms.
16. Author bio + related articles at bottom of `blog/show`.
17. Audit `@fontsource` vs Google CDN — CLAUDE.md says no Google Fonts CDN.

---

## 5. CLAUDE.md Violations Scorecard

| Rule | Status | Affected files |
|---|---|---|
| #1 Money — bcmath only | ✅ Compliant in Blade (most prices come from controllers already bcmath'd) | — |
| #2 Auth — two guards | ✅ Frontend uses `auth()` correctly | — |
| #3 Cache — never flush | N/A (Blade layer) | — |
| #4 Mail — always queue | N/A (Blade layer) | — |
| #5 Settings — never hardcode | ❌ | `cart/index` popular-OEMs, `placeholder` sprint labels |
| #6 OEM search — normalized | N/A (Blade layer) | — |
| #7 Migrations — append only | N/A | — |
| #8 SEO — server-side Blade | ⚠️ Partial | `blog/show`, `blog/index`, `contact/show` missing elements |
| #9 Server — no Node.js | N/A (Blade layer) | — |
| #10 DB column types | N/A | — |
| Design: `text-amber-text` for amber text on white | ❌ Systemic | ~20 files |
| Design: font-mono for OEM numbers only | ✅ | — |
| Design: Heroicons only | ⚠️ | checkout/* uses raw SVG + external CDN |
| Forms: honeypot on public forms | ❌ | ~8 missing |
| Forms: mobile input hints | ⚠️ Partial | most forms lack `inputmode` |
| Alpine.js only (no Vue/React/jQuery) | ✅ | `checkout/payment` has vanilla JS but no other framework |
| Tailwind utilities only (no custom CSS) | ❌ | `placeholder.blade.php` has inline `<style>` |

---

## 6. Consistency Audit — Token Usage

| Token | Correct use | Found | Notes |
|---|---|---|---|
| `bg-navy` | Primary bg (hero, sidebar, buttons) | 80+ | Used correctly |
| `text-navy` | Headings | 200+ | Used correctly |
| `bg-amber` | CTA bg, active states, badges | 60+ | Correct |
| `text-amber` | FORBIDDEN as text on white | **121 ❌** | Replace with `text-amber-text` |
| `text-amber-text` | CORRECT amber text on white | ~15 | Underused |
| `bg-gray-*` | Container bgs | 315 | OK but see slate |
| `bg-slate-*` | Alternate gray | 74 | Pick one system |
| `text-muted` / `text-body` | Semantic tokens (CLAUDE.md) | underused — mostly `text-gray-500` / `text-gray-700` directly |
| `font-display` (Plus Jakarta) | H1/H2/H3 | consistent | ✓ |
| `font-mono` (JetBrains) | OEM numbers only | consistent | ✓ |
| `font-sans` (Inter) | Body | default, fine | ✓ |

---

## 7. Closing Notes

The OEMHub frontend at this stage has a **strong design foundation** — the navy + amber auto-industrial palette is distinctive, the mono-spaced OEM numbers are a signature touch, and several pages (`search/zero-results`, `checkout/step4`, `account/order-detail`, `account/refunds`) are genuinely polished. The issues are almost all **consistency drift, not concept failure** — which means they're solvable with codemods, Blade component extraction, and one accessibility pass, rather than rework.

The single highest-leverage fix is the **`text-amber` → `text-amber-text` codemod**. It's one search-and-replace with manual review, removes your biggest WCAG failure, and costs almost nothing. Ship that first.

After that, extract the account sidebar, delete the placeholder page, bolt SEO onto the blog, and you have a very credible B2B auto-parts platform for launch.
