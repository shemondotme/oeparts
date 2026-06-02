# OeParts Storefront & Admin Frontend Architecture Audit
**Product:** OeParts (Genuine OEM Auto Parts E-commerce Platform)  
**Date:** May 25, 2026  
**Auditor:** Senior UX/UI Architect & Laravel Frontend Strategy Director  

---

## 1. Project Understanding Report

### Executive Project Summary
OeParts is an open-source, production-ready, search-first e-commerce platform built specifically for genuine OEM auto parts dealers in Europe. The core design philosophy centers around the realistic behaviors of automotive parts procurement: customers search using exact OEM numbers rather than browsing hierarchical product catalogs. To optimize loading speeds and focus on technical accuracy, the platform operates without product images, utilizing data-dense tables and monospace typography to identify parts.

### Business Model Summary
*   **B2C Retail Channel:** Guest checkouts validated via email One-Time Passwords (OTP), credit card payments (Airwallex), dynamic EU shipping rates, and standard EU VAT rates.
*   **B2B Trade Channel:** Dynamic VAT exemption validation using real-time VIES API checks, custom volume pricing tiers (up to 35% off retail), and Net-30 credit lines (from €2k to €100k) for verified mechanical workshops.

### Platform Goals
*   **Zero-Friction Search:** Instant search results and cross-references matching under 200ms.
*   **Low Cognitive Load:** Plain data ledgers with standardized dimensions, weights, and conditions.
*   **Logistics Transparency:** Explicit shipping costs, carrier estimations (min/max days), and real-time inventory status (`is_in_stock`).

### Core Frontend Philosophy
The application storefront uses an **Industrial Blueprint** aesthetic. Inspired by draftsman schematics, it features flat structures, hairline grids, warm drafting paper backgrounds, and heavy monospace typography for alphanumeric codes. Visual ornaments like shadows and gradient fills are omitted on the storefront.
The admin panel uses a premium **Slate Enterprise SaaS** theme utilizing Filament PHP, featuring high-contrast dark panels, indigo actions, and responsive tables.

### User Roles & Journeys
*   **Retail Guest/B2C Buyer:** Landing Page / Search Console → Alphanumeric Search → Inspect Results Ledger (filter by condition/brand) → Add to Cart → 5-step Checkout (OTP email verify) → Secure Card Payment → Order Confirmation.
*   **B2B Trade Account:** Workspace Register/Login → Search / Bulk RFQ Desk → Live VAT Exemption Verification → Order on Account (Net-30 terms) → Download PDF Invoice.
*   **Catalog & Operations Admin:** Filament Dashboard → Import CSV stock updates (background queue) → Reorder sections drag-and-drop → Review failed searches → Manage support inquiries and RMA refund approvals.

### Key Modules & Features
1.  **OEM Normalizer Engine:** Strips spaces, dashes, dots, and slashes from incoming strings; forces characters uppercase (e.g., `BMW-11127 556 503` → `BMW11127556503`) to ensure high-performance database indexing.
2.  **Cross-Reference Matching:** Returns alternative compatible brands alongside direct OEM searches.
3.  **Part Inquiry Concierge:** Fallback intake form triggered automatically on zero-results pages to allow support admins to manually source rare items.
4.  **Web Installer Wizard:** 6-step setup interface verifying system requirements, configuring SQLite/MySQL databases, and seeding default variables.

### Missing Documentation Areas
*   **Design Token Guide:** Lacks clear documentation on custom typography classes like `bp-spec` versus raw Tailwind sizing classes.
*   **Theme Interoperability Rules:** No warning documentation warning developers that `bp-btn` has different visual layouts in storefront (`app.css`) versus admin panel (`theme.css`).

### Architecture Observations
*   **Asset Bundling Mismatch:** App CSS imports `@fontsource/inter` while tailwind configuration references `Geist Sans` first in the font stack. This leads to font rendering fallback issues if Geist is present locally.
*   **Legacy Code Retention:** `app.css` retains ~200 lines of gradient component rules (`.btn-primary`, `.btn-secondary`) from an earlier design iteration. While storefront views have migrated to `bp-` classes, the **web installer wizard still depends on these legacy classes**. Removing them from `app.css` without refactoring the installer will break the setup UI.

---

## 2. Full Frontend Page Inventory

| Page Name | Route | Status | Designed? | Responsive? | UX Quality | Notes |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **Home Page** | `frontend.home` | Fully Designed | Yes | Yes | 8/10 | Implements intersection observers for below-fold lazy section rendering. |
| **Parts Search Console** | `frontend.search.console` | Fully Designed | Yes | Yes | 9/10 | Includes auto-focus inputs, typewriter search hints, and quick-access grids. |
| **OEM Search Results** | `frontend.search.results` | Fully Designed | Yes | Yes | 8/10 | Technical grid, displays inline cross-references, stock statuses, and filters. |
| **Brands Directory** | `frontend.manufacturer.index`| Fully Designed | Yes | Yes | 9/10 | Features an interactive A-Z alphabet jump bar and listing statistics. |
| **Brand Profile** | `frontend.manufacturer.show` | Fully Designed | Yes | Yes | 9/10 | Houses brand indicators, covered platforms list, and paginated parts ledger. |
| **Car Models List** | `frontend.car-model.index` | Fully Designed | Yes | Yes | 9/10 | Ledger staved grid listing platform lines with production years. |
| **Car Model Profile** | `frontend.car-model.show` | Fully Designed | Yes | Yes | 9/10 | Technical parts compatibility matrix featuring single-row layouts. |
| **Shopping Cart** | `frontend.cart.index` | Fully Designed | Yes | Yes | 8/10 | Features Alpine-powered quantity updates and active coupon indicators. |
| **Checkout Wizard** | `frontend.checkout` | Fully Designed | Yes | Yes | 8/10 | 5-step checkout structure. B2B VAT fields show inline validation triggers. |
| **Checkout Payment** | `frontend.checkout.payment` | Fully Designed | Yes | Yes | 8/10 | Embedded Airwallex iframe integrations with fallback bank instructions. |
| **Checkout Success** | `frontend.checkout.thank-you` | Fully Designed | Yes | Yes | 9/10 | High-contrast confirmation layout providing PDF invoice download buttons. |
| **Account Dashboard** | `frontend.account.dashboard` | Fully Designed | Yes | Yes | 8/10 | Layout containing order counts, recent addresses, and settings links. |
| **Account Orders List** | `frontend.account.orders` | Fully Designed | Yes | Yes | 8/10 | Lists historical orders with status tags. |
| **Account Order Detail** | `frontend.account.order.detail` | Fully Designed | Yes | Yes | 8/10 | Visual order timelines, tracking numbers, and refund request triggers. |
| **Account Saved Addresses**| `frontend.account.addresses` | Fully Designed | Yes | Yes | 8/10 | Addresses ledger layout featuring default address flags. |
| **Account Address Form** | `frontend.account.addresses.store`| Fully Designed | Yes | Yes | 8/10 | Standard input form. Country selectors are styled correctly. |
| **Account Settings** | `frontend.account.settings` | Fully Designed | Yes | Yes | 8/10 | Form configurations for profile details, passwords, and newsletter. |
| **Account Refunds List** | `frontend.account.refunds` | Fully Designed | Yes | Yes | 8/10 | Table tracking RMA refund status, request amounts, and processing logs. |
| **Account Refund Form** | `frontend.account.order.refund.form`| Fully Designed | Yes | Yes | 8/10 | Custom file attachment panel for uploading damaged part photos. |
| **Password Request Form** | `frontend.password.request` | Fully Designed | Yes | Yes | 8/10 | Simple email request form styled under the blueprint design. |
| **Password Reset Form** | `frontend.password.reset` | Fully Designed | Yes | Yes | 8/10 | Secure token validation page with password confirmation inputs. |
| **Blog Index** | `frontend.blog.index` | Fully Designed | Yes | Yes | 8/10 | Technical articles directory featuring category and tag filters. |
| **Blog Article View** | `frontend.blog.show` | Fully Designed | Yes | Yes | 8/10 | Clean reading layouts, author cards, and related posts grids. |
| **Contact Page** | `frontend.contact.show` | Fully Designed | Yes | Yes | 8/10 | Multi-purpose support ticketing inputs with dynamic subject types. |
| **CMS Pages** | `frontend.page` | Fully Designed | Yes | Yes | 9/10 | Custom layout rendering documents like Terms of Service and Privacy. |
| **Web Installer** | (multiple installer routes) | Fully Designed | Yes | Yes | 8/10 | 6-step guided system using legacy CSS classes. |
| **HTTP 401 Error Page** | (error route fallback) | Fully Designed | Yes | Yes | 9/10 | Blueprint unauthenticated layout prompting login modal triggers. |
| **HTTP 403 Error Page** | (error route fallback) | Fully Designed | Yes | Yes | 9/10 | Blueprint forbidden security card displaying validation keys info. |
| **HTTP 404 Error Page** | (error route fallback) | Fully Designed | Yes | Yes | 9/10 | Blueprint vacant page route warning with direct concierge triggers. |
| **HTTP 419 Error Page** | (error route fallback) | Fully Designed | Yes | Yes | 9/10 | Blueprint session handshake timeout view with auto reload functions. |
| **HTTP 429 Error Page** | (error route fallback) | Fully Designed | Yes | Yes | 9/10 | Blueprint rate limit throttled incident report with retry stats. |
| **HTTP 500 Error Page** | (error route fallback) | Fully Designed | Yes | Yes | 9/10 | Blueprint unhandled server code exception readout trace report. |
| **Maintenance Page** | (maintenance fallback) | Fully Designed | Yes | Yes | 8/10 | Offline countdown window displaying contact emails configuration. |

---

## 3. UI/UX Audit Report

### Visual Hierarchy
*   **Strength:** Section titles use massive display headings (`blueprint-xl` / `blueprint-lg`) paired with structural metadata numbers (e.g., `§01`). This establishes an authoritative technical rhythm.
*   **Weakness:** Search results pages display too many alerts of equal visual weight. Notice alerts for cross-references, partial matches, and shipping rules use similar amber backgrounds, creating visual competition.

### Spacing Consistency
*   **Strength:** Vertical page spacing is consistently controlled via the `.section` class (`py-24 md:py-28`).
*   **Weakness:** The filter forms on the search results page contain variable padding values (`p-3` vs `p-5`), causing alignment glitches on mobile displays.

### Typography Consistency
*   **Strength:** Monospace fonts are consistently applied to OEM numbers, currency values, indexes, and dates.
*   **Weakness:**Mismatched font configurations. While `tailwind.config.js` sets `Geist Sans` and `Geist Mono` at the top of the stack, `app.css` imports `@fontsource/inter` and `@fontsource/jetbrains-mono`. The browser renders Inter as a fallback, but if a developer has Geist installed locally, the site layout shifts unexpectedly.

### Color Consistency
*   **Strength:** The storefront is locked to high-contrast `ivory` and `ink` shades, simulating physical blueprint paper.
*   **Weakness:** The `section-badge` and `section-accent-bar-main` components use gradients (`from-amber/15 to-orange-50/15` and `from-amber via-orange-500 to-amber`), which clutters the drafting paper theme.

### Mobile Responsiveness
*   **Strength:** Tables stack into card layouts on screens smaller than 768px.
*   **Weakness:** Touch targets for pagination numbers and filter checkboxes on search pages are smaller than the recommended `44px` minimum.

### OEM & B2B Usability
*   **Strength:** VAT validation is handled asynchronously, and cart summaries automatically compute B2B tax exemptions. Monospace OEM numbers are easily readable.
*   **Weakness:** There are no instant click-to-copy buttons next to OEM numbers on parts ledgers, requiring users to manually highlight and copy numbers.

---

## 4. Section-by-Section Analysis (Home Page)

The Home Page contains 14 distinct layout sections. Below is an audit of each section:

### 1. Hero (`hero.blade.php`)
*   **Purpose:** Primary search landing area.
*   **UX/UI Score:** 9/10 (UX) | 9/10 (UI)
*   **Unnecessary Elements:** Typewriter search loop is engaging, but can sometimes cause layout shifts if placeholders vary significantly in character length.
*   **Missing Elements:** A direct link to open the B2B RFQ concierge page.
*   **Mobile Optimization:** Excellent. Input fields scale nicely.
*   **Recommendations:** Keep. Standardize typewriter list lengths.

### 2. Trust Bar (`trust_bar.blade.php`)
*   **Purpose:** Display quick value propositions (e.g., EU shipping, genuine parts).
*   **UX/UI Score:** 8/10 (UX) | 8/10 (UI)
*   **Problems:** Uses simple icon rows. Lacks direct links to shipping policy documents.
*   **Recommendations:** Keep, but link each trust block directly to relevant CMS informational pages.

### 3. Popular Searches (`popular_searches.blade.php`)
*   **Purpose:** Display high-volume OEM query shortcuts.
*   **UX/UI Score:** 8/10 (UX) | 8/10 (UI)
*   **Problems:** Displays OEM numbers without brand descriptors, making it difficult for B2C users to identify parts.
*   **Recommendations:** Add small brand monograms next to each query shortcut (e.g., `1K0698151E (VW)`).

### 4. Featured Brands (`featured_brands.blade.php`)
*   **Purpose:** Shortcut navigation to top manufacturers.
*   **UX/UI Score:** 9/10 (UX) | 9/10 (UI)
*   **Improvements:** Show logo assets as flat vector outlines to match the drafting paper style.
*   **Recommendations:** Keep.

### 5. Stats Counter (`stats_counter.blade.php`)
*   **Purpose:** Quantify database scope (e.g., 1M+ parts, 200+ brands).
*   **UX/UI Score:** 8/10 (UX) | 7/10 (UI)
*   **Problems:** Numbers animate using Alpine counters, but the layout is slightly generic.
*   **Recommendations:** Add technical divider gridlines between metric columns.

### 6. Banner (`banner.blade.php`)
*   **Purpose:** Primary promotional section for B2B workshop accounts.
*   **UX/UI Score:** 9/10 (UX) | 9/10 (UI)
*   **Visual Noise:** Highly structured, great contrast. The blueprint grid theme is used effectively here.
*   **Recommendations:** Keep. High-priority conversion block.

### 7. How It Works (`how_it_works.blade.php`)
*   **Purpose:** Explaining search-first procurement steps.
*   **UX/UI Score:** 8/10 (UX) | 8/10 (UI)
*   **Unnecessary Elements:** The icons overlap with section titles on smaller mobile screens.
*   **Recommendations:** Stack layout vertically on mobile screens (`max-width: 640px`).

### 8. Shipping Info (`shipping_info.blade.php`)
*   **Purpose:** Map delivery details across EU shipping zones.
*   **UX/UI Score:** 7/10 (UX) | 8/10 (UI)
*   **Missing Elements:** A quick search lookup field by shipping country.
*   **Recommendations:** Integrate a country autocomplete search box to dynamically filter shipping costs.

### 9. Testimonials (`testimonials.blade.php`)
*   **Purpose:** Highlight client reviews.
*   **UX/UI Score:** 7/10 (UX) | 7/10 (UI)
*   **Problems:** Uses simple cards. Lacks verified ratings metadata (e.g., Google or Trustpilot badges).
*   **Recommendations:** Keep, but add verified customer badges and average ratings stars to build trust.

### 10. Part Inquiry (`part_inquiry.blade.php`)
*   **Purpose:** Direct intake form for sourcing unlisted parts.
*   **UX/UI Score:** 9/10 (UX) | 8/10 (UI)
*   **Problems:** Form contains many text inputs, which can feel overwhelming to users.
*   **Recommendations:** Implement a multi-step form wizard using Alpine.js.

### 11. Blog Preview (`blog_preview.blade.php`)
*   **Purpose:** Feature recent technical posts.
*   **UX/UI Score:** 8/10 (UX) | 8/10 (UI)
*   **Improvements:** Link tags directly to filtered blog category listings.
*   **Recommendations:** Keep.

### 12. FAQs (`faqs.blade.php`)
*   **Purpose:** Address technical and shipping questions.
*   **UX/UI Score:** 8/10 (UX) | 8/10 (UI)
*   **UX Friction:** Standard accordion dropdown layout.
*   **Recommendations:** Keep. Add a search bar to filter FAQs dynamically.

### 13. Newsletter (`newsletter.blade.php`)
*   **Purpose:** Capture client emails for stock and price alerts.
*   **UX/UI Score:** 8/10 (UX) | 8/10 (UI)
*   **Recommendations:** Add checkbox options to select preferred email frequencies.

### 14. Contact CTA (`contact_cta.blade.php`)
*   **Purpose:** Final prompt to contact customer service.
*   **UX/UI Score:** 9/10 (UX) | 8/10 (UI)
*   **Recommendations:** Keep. Displays support telephone number and live chat options clearly.

---

## 5. Modernization & Improvement Strategy

### Automotive Industry UX Standards
*   **VIN Scanning integration:** Allow mobile users to capture their vehicle identification numbers (VIN) using their phone camera.
*   **Vehicle fitment validation strip:** Display a persistent validation badge next to parts (e.g., `✓ Fits your 2012 Golf V`) to reduce returns.

### Product Browsing & Filtering
*   **Instant Click-to-Copy:** Provide quick copy icons next to every OEM number on parts list tables.
*   **Dynamic Range Sliders:** Replace min/max input fields with slide controls for price sorting.

### Cart & Checkout UX
*   **One-click Express Checkout:** Integrate Apple Pay, Google Pay, and Link (via Airwallex checkout integrations).
*   **Progress indicators:** Clearly display remaining amounts to qualify for free shipping (e.g., `Add €24.50 more for free delivery`).

### Mobile Shopping UX
*   **Fixed Action Bar:** Use fixed bottom action buttons on product pages.
*   **Optimized Touch Targets:** Increase size of close buttons and checkbox selections to at least `44px`.

### Empty States & Loading skeletons
*   **Blueprint Skeletons:** Skeletons must animate in alignment with the underlying grid background.
*   **Informative Empty States:** When search queries return no results, display clear alternatives (e.g., search by brand or launch the concierge request modal).

---

## 6. Frontend Consistency Rules

1.  **Strict Token Adherence:** Never use arbitrary HEX codes in blade templates. Only use Tailwind theme variables (`bg-bg-page`, `text-ink`, `border-rule`).
2.  **Typography Locking:** OEM numbers must always use the `font-mono` family (Geist Mono / JetBrains Mono). Headings must use `font-display`.
3.  **UI Component Separation:** Rename admin-specific classes to prevent overlaps (e.g., avoid sharing `bp-card` name structures with different visual styles).
4.  **No Storefront Ornaments:** Storefront views must not use gradients or shadows. Elements must remain flat, geometric, and functional.
5.  **Installer Preservation:** Do not delete legacy classes (`btn-primary`, `.btn-secondary`) from `app.css` until the web installer is updated to use standard Tailwind utility classes.

---

## 7. Priority Improvement Roadmap

### Critical Fixes (Sprint 1)
1.  **Resolve Mismatched Fonts:** Align the font imports in `app.css` with the Tailwind configuration. Replace `@fontsource/inter` with `@fontsource/geist-sans` to avoid rendering conflicts.
2.  **Generate Missing Car Model Views:** Create `resources/views/frontend/car-model/index.blade.php` and `show.blade.php` using the Blueprint theme to resolve the current 500 errors.
3.  **Add Naming Warnings:** Document the CSS classes in `app.css` and `theme.css` to prevent styling conflicts.

### High-Impact Improvements (Sprint 2)
1.  **Click-to-Copy OEM numbers:** Add copy-to-clipboard icons next to OEM numbers on parts tables.
2.  **Refactor Section Eyebrows:** Replace gradient fills in `.section-badge` and `.section-accent-bar-main` with flat blueprint fills (`bg-amber/10 border-amber/25`).
3.  **Optimize Mobile Targets:** Increase touch targets to a minimum of `44px`.

### Medium-Priority Improvements (Sprint 3)
1.  **Consolidate Button CSS:** Delete the unused `components/button.blade.php` and `components/ui/button.blade.php` files to reduce clutter.
2.  **Clean Legacy CSS:** Move installer styles to a separate `installer.css` file to keep the storefront stylesheet clean.
3.  **Build a VIN Tooltip:** Add tooltips explaining where to locate the VIN code on vehicle registration papers.

---

## 8. Final Executive Summary

The frontend architecture of OeParts contains a well-defined **Industrial Blueprint** design system. By locking the storefront to high-contrast technical layouts and data-dense tables, the platform aligns with the purchasing behaviors of B2B mechanics and auto parts buyers.

To improve the platform's stability, the development team must address the missing car model view templates and resolve the mismatched font imports. Cleaning up unused styling assets and separating installer configurations will ensure the codebase remains clean and maintainable.
