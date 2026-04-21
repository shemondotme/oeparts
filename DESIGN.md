# OEMHub — Design System Reference

## 1. Visual Theme & Atmosphere

OEMHub is a **search-first e-commerce platform for genuine OEM auto parts** in Europe. The design communicates trust, precision, and speed — a professional parts catalog that feels reliable to both B2B workshops and B2C car owners.

The visual identity is built on a **navy + amber** pairing: deep navy (`#0B3A68`) for authority and structure, warm amber (`#F59E0B`) for energy and action. The page sits on a cool-neutral gray canvas (`#F8FAFC`) that keeps attention on content. Cards and sections float on white, creating clear visual layers.

Typography is split between two complementary families: **Plus Jakarta Sans** — a geometric display face with strong personality — anchors all headings, while **Inter** handles body text and UI with quiet professionalism. **JetBrains Mono** is reserved exclusively for OEM part numbers, making them instantly recognizable and scannable across the entire site.

The design leans into **gradient accents** and **soft depth**: amber-to-orange gradients on primary CTAs, blurred blob decorations for visual warmth, and generous border-radius (2xl–3xl) creating a modern, approachable feel. Sections use scroll-triggered fade-in-up animations via Alpine.js + IntersectionObserver, giving the page a progressive reveal rhythm.

**Key Characteristics:**
- Navy + amber dual-tone identity — authority meets energy
- Cool-neutral page canvas (`#F8FAFC`) with white card surfaces
- Split typography: Plus Jakarta Sans (display) / Inter (body) / JetBrains Mono (OEM numbers only)
- Gradient CTAs with amber-to-orange transitions and glow shadows
- Generous border-radius (`rounded-2xl` / `rounded-3xl`) throughout
- Scroll-triggered entrance animations (IntersectionObserver + Alpine.js)
- Decorative gradient blobs as section backgrounds (blurred, low-opacity)
- No product images — text-first, data-driven part listings
- Heroicons v2 (outline for UI, solid for active states)
- Mobile-first responsive with Tailwind utility classes

## 2. Color Palette & Roles

### Primary Brand
- **Navy** (`#0B3A68`): The primary brand color. Used for headings, hero backgrounds, navbar, footer, sidebar, primary text emphasis, and `.btn-secondary`. The foundation of trust and authority.
- **Amber** (`#F59E0B`): The accent/action color. Used for CTA buttons, active states, progress bars, badge backgrounds, icon wrappers, and decorative elements. Always draws the eye to the next action.
- **Amber Text** (`#B45309`): WCAG AA-compliant amber for text on white/light backgrounds. **NEVER use `#F59E0B` as text on white** — it fails contrast. Used in eyebrow badges, inline links, and any amber text on light surfaces.

### Semantic Text
- **Body** (`#334155`): Primary body text — a cool slate-gray. All paragraph text, descriptions, and content.
- **Muted** (`#64748B`): Secondary text — labels, timestamps, metadata, helper text, and de-emphasized content.
- **White** (`#FFFFFF`): Text on navy/dark backgrounds — headings, nav links, footer text.
- **Navy** (`#0B3A68`): Heading text on light backgrounds — all H1, H2, H3 on white/gray surfaces.

### Surface & Background
- **Page Background** (`#F8FAFC`): The default page canvas — a barely-blue cool gray.
- **White** (`#FFFFFF`): Card surfaces, inputs, modals, form containers.
- **Section Alt** (`#EEF4FF`): Alternating section background — a 6% navy tint for visual rhythm between sections.
- **Off-White** (`#FAFAF8`): Stats counter and subtle section backgrounds — warm near-white.
- **Amber 50 tints** (`amber-50/50`, `orange-50/30`): Soft warm gradients for trust bar, part inquiry, and newsletter section backgrounds.

### Dark Surfaces (Hero, Navbar, Footer)
- **Navy** (`#0B3A68`): Hero section, navbar, CTA sections.
- **Navy → Blue 900**: Footer gradient (`from-navy via-navy to-blue-950`).
- **Navy → Blue 700**: Dark card headers (`from-navy to-blue-700`).
- **White/10–30%**: Borders, hover states, and glassmorphism on dark surfaces (`border-white/10`, `bg-white/15`).

### Condition Badges (Product Listing)
| Condition | Background | Text |
|-----------|-----------|------|
| New | `#DCFCE7` | `#16A34A` (green) |
| Used Grade A | `#DBEAFE` | `#1D4ED8` (blue) |
| Used Grade B | `#FEF3C7` | `#D97706` (amber) |
| Used Grade C | `#F1F5F9` | `#64748B` (gray) |
| Remanufactured | `#F3E8FF` | `#7C3AED` (purple) |
| Aftermarket | `#FEE2E2` | `#DC2626` (red) |
| New Old Stock | `#ECFDF5` | `#059669` (teal) |

### Semantic Status
- **Success**: green-500/600 backgrounds with green text
- **Error**: red-50 bg, red-200 border, red-700 text
- **Warning**: amber-50 bg, amber-200 border, amber-700 text
- **Info**: blue-50 bg, blue-200 border, blue-700 text

### Gradient System
OEMHub uses gradients in specific, consistent ways:
- **Primary CTA**: `bg-gradient-to-r from-amber to-orange-500` with `shadow-lg shadow-amber/30`
- **Secondary CTA**: `bg-gradient-to-r from-navy to-navy/90` with `shadow-lg shadow-navy/30`
- **Hero background**: Solid navy with animated gradient blobs (`amber`, `blue-500`, `purple-500` at 20% opacity, blurred)
- **Navbar**: `bg-gradient-to-r from-navy via-navy to-blue-900`
- **Footer**: `bg-gradient-to-b from-navy via-navy to-blue-950`
- **Section badges**: `bg-gradient-to-r from-amber/15 to-orange-50/15` with `border border-amber/25`
- **Decorative blobs**: Low-opacity (10–20%) amber and blue circles with `filter blur-3xl`
- **NEVER** use gradients on body text, borders, or structural elements

## 3. Typography Rules

### Font Families (loaded via @fontsource — no Google CDN)
- **Display**: `Plus Jakarta Sans` (weights: 600, 700, 800) — H1, H2, H3, logo, hero text, section headings
- **Body / UI**: `Inter` (weights: 400, 500, 600) — body text, labels, navigation, descriptions, buttons
- **Mono**: `JetBrains Mono` (weights: 400, 500) — OEM part numbers ONLY. Every single OEM number on the site must use `font-mono`

### Hierarchy

| Role | Font | Size | Weight | Tailwind Class | Notes |
|------|------|------|--------|----------------|-------|
| Hero Heading | Plus Jakarta Sans | 4xl → 7xl responsive | 800 (extrabold) | `font-display text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-extrabold` | Gradient text on dark bg |
| Section Heading | Plus Jakarta Sans | 3xl → 5xl responsive | 900 (black) | `section-heading` = `font-display text-3xl md:text-4xl lg:text-5xl font-black` | Navy on light, white on dark |
| Card Title | Plus Jakarta Sans | xl–2xl | 700 (bold) | `font-display text-xl font-bold text-navy` | — |
| Feature Title | Plus Jakarta Sans | lg | 700 (bold) | `font-display text-lg font-bold text-navy` | — |
| Body Large | Inter | xl–2xl | 400 | `text-xl text-body` or `text-xl text-white/70` | Intro paragraphs, subheadlines |
| Body | Inter | base (16px) | 400–500 | `text-base text-body` | Standard content |
| Body Small | Inter | sm (14px) | 400–500 | `text-sm text-body` | Compact content |
| Label | Inter | sm (14px) | 600 (semibold) | `text-sm font-semibold text-navy` | Form labels |
| Muted / Meta | Inter | sm–xs | 400 | `text-sm text-muted` or `text-xs text-muted` | Timestamps, helper text |
| Eyebrow / Badge | Inter | xs (12px) | 700 (bold) | `section-badge` = `text-xs font-bold tracking-widest uppercase` | All-caps with amber styling |
| Navigation | Inter | sm (14px) | 600 (semibold) | `text-sm font-semibold text-white/90` | Navbar links |
| Button Text | Inter | sm–base | 600 (semibold) | `font-semibold` | All button variants |
| OEM Number | JetBrains Mono | sm–xl | 400–500 | `font-mono` | Always uppercase, always monospace |

### Typography Principles
- **Display for structure, sans for function**: Plus Jakarta Sans carries all structural headings (H1–H3), creating a bold, modern hierarchy. Inter handles everything functional — body, buttons, labels, nav.
- **OEM numbers are sacred**: Every OEM part number uses JetBrains Mono (`font-mono`). No exceptions. They render uppercase with `autocapitalize="characters"` in inputs.
- **Navy headings, slate body**: Headings are always navy (`text-navy`) on light backgrounds, white on dark. Body text is always slate (`text-body` / `#334155`).
- **Weight discipline**: Display text uses 600–900 weights. Body text uses 400–600. Never use 800+ on Inter body text.
- **Responsive scaling**: Hero headings scale from `text-4xl` on mobile to `text-7xl` on desktop. Section headings scale from `text-3xl` to `text-5xl`.

## 4. Component Stylings

### Buttons

**Primary (`.btn-primary`)**
- Background: `bg-gradient-to-r from-amber to-orange-500`
- Text: White, font-semibold
- Shadow: `shadow-lg shadow-amber/30`
- Hover: `scale-105`, `from-amber/90 to-orange-500/90`, `shadow-xl shadow-amber/40`
- Radius: `rounded-2xl`
- Padding: `px-6 py-3`
- The main CTA — amber gradient with glow shadow

**Secondary (`.btn-secondary`)**
- Background: `bg-gradient-to-r from-navy to-navy/90`
- Text: White, font-semibold
- Shadow: `shadow-lg shadow-navy/30`
- Hover: `scale-105`, inverted gradient direction, `shadow-xl`
- Radius: `rounded-2xl`
- For secondary actions — navy gradient

**Outline (`.btn-outline`)**
- Background: Transparent
- Border: `border-2 border-amber`
- Text: `text-amber`, font-semibold
- Hover: `bg-amber/5`, `scale-105`
- Radius: `rounded-2xl`
- For tertiary actions on light backgrounds

**Ghost (`.btn-ghost`)**
- Background: Transparent → `hover:bg-gray-100`
- Text: `text-gray-700`, font-semibold
- Radius: `rounded-2xl`
- For minimal-emphasis actions

**Button Sizes**
- Small: `px-4 py-2 text-sm rounded-2xl`
- Medium: `px-6 py-3 text-sm rounded-2xl`
- Large: `px-8 py-4 text-base rounded-2xl`

### Cards & Containers
- Background: White (`bg-white`) on page background
- Border: `border border-gray-100` (light, subtle)
- Radius: `rounded-2xl` (standard) or `rounded-3xl` (featured/large)
- Shadow: `shadow-sm` → `hover:shadow-lg` for interactive cards
- Larger cards: `shadow-lg` or `shadow-xl shadow-amber/5`
- Internal padding: `p-6` (compact) or `p-8 md:p-12` (spacious)
- Transition: `transition-all duration-300` with `hover:-translate-y-1` for lift effect

### Icon Wrappers
Three variants for consistent icon presentation:
- **Gradient**: `bg-gradient-to-br from-amber/10 to-orange-100 text-amber` — the default, warm and subtle
- **Solid**: `bg-amber text-white` — for emphasis and active states
- **Outline**: `border-2 border-amber text-amber bg-transparent` — for lighter contexts

Icon sizes: `w-8 h-8` (sm), `w-12 h-12` (md), `w-16 h-16` (lg)
Inner icon sizes: `w-4 h-4` (sm), `w-6 h-6` (md), `w-8 h-8` (lg)

### Inputs & Forms
- Background: `bg-gray-50` (subtle gray tint, not pure white)
- Border: `border border-gray-200`
- Focus: `focus:border-navy focus:ring-2 focus:ring-navy/10`
- Radius: `rounded-xl` (standard inputs) or `rounded-2xl` (search bars)
- Padding: `px-4 py-3` (standard) or `px-4 py-2.5` (compact)
- Text: `text-navy text-sm`
- Placeholder: `placeholder:text-gray-400`
- OEM inputs: Add `font-mono uppercase autocapitalize="characters" inputmode="text"`
- Email inputs: Add `inputmode="email"`
- Honeypot: `<input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">` on all public forms

### Section Heading Component (`<x-section-heading>`)
Reusable component with:
- **Eyebrow badge**: `section-badge` class — pill shape, amber gradient bg, uppercase tracking-widest, animated pulse dot
- **Headline**: `section-heading` class — Plus Jakarta Sans, font-black, navy, responsive 3xl→5xl
- **Subheadline**: `section-subheading` class — Inter, font-medium, `text-body`, max-w-2xl centered
- **Animation**: IntersectionObserver triggers `opacity-0 translate-y-4` → `opacity-100 translate-y-0` on scroll
- Props: `eyebrow`, `headline`, `subheadline`, `accentBar`, `align` (center/left), `dark` (bool)

### Navigation (Navbar)
- **Homepage**: Transparent initially, becomes `bg-navy/95 backdrop-blur-xl shadow-xl` on scroll
- **Inner pages**: Sticky, solid `bg-gradient-to-r from-navy via-navy to-blue-900`
- Top accent bar: `h-0.5 bg-gradient-to-r from-amber via-orange-400 to-amber` (inner pages only)
- Height: `h-20`
- Logo: Amber icon box (`bg-gradient-to-br from-amber to-orange-500 rounded-xl`) + "OEM" (amber) + "Hub" (white)
- Links: `text-sm font-semibold text-white/90 hover:text-white hover:bg-white/10 rounded-lg`
- CTA: Sign In button with amber styling
- Language switcher: Flag icon + dropdown
- Cart icon with badge counter
- Mobile: hamburger menu with slide-out panel

### Footer
- Background: `bg-gradient-to-b from-navy via-navy to-blue-950`
- 4-column grid: Brand info, Quick Links, My Account, Get in Touch
- Logo: Same as navbar
- Links: `text-white/70 hover:text-amber transition-colors`
- Phone/email with icons
- Language flags
- Payment badges (VISA, Mastercard, PayPal, Apple Pay)
- Bottom bar: Copyright, trust badges (Secure Checkout, EU Shipping, etc.), legal links
- Decorative: Low-opacity amber/blue blurred blobs

## 5. Homepage Sections (in order)

| # | Section Type | Background | Key Design Elements |
|---|-------------|-----------|-------------------|
| 1 | `hero` | Solid navy with animated gradient blobs | Gradient text heading, glassmorphism search bar with 3D tilt, typing placeholder, popular OEM pills, wave SVG divider |
| 2 | `stats_counter` | Off-white (`#FAFAF8`) | 4-column card grid, amber animated counters (countUp.js), icon wrappers, eyebrow badge, CTA button |
| 3 | `how_it_works` | White with decorative blobs | 3-step cards with numbered amber badges (01/02/03), icons, descriptions |
| 4 | `featured_brands` | Gradient gray-amber (`from-gray-50 via-amber-50/20 to-gray-50`) | Brand cards with logos, alphabet filter tabs, "View All Brands" CTA, scroll animations |
| 5 | `popular_searches` | Gradient gray-orange (`from-gray-50 via-orange-50/10 to-gray-50`) | Tiered list leaderboard with progress bars, rank badges, search counts |
| 6 | `part_inquiry` | Soft amber-cream gradient | Inline form card (OEM number + email + optional vehicle details), submit button, scroll animation |
| 7 | `banner` | Navy with animated blobs | Promo/feature grid with 6 feature cards, social proof indicators, CTA button, staggered card animations |
| 8 | `testimonials` | Gradient amber-white (`from-amber-50/30 via-white to-amber-50/30`) | 3-column review cards with star ratings, customer names, verified badges |
| 9 | `shipping_info` | Gradient blue-white (`from-white via-blue-50/20 to-white`) | 4 stat cards + carrier cards grid (DHL, DPD, GLS, FedEx, UPS) with brand colors |
| 10 | `blog_preview` | Section alt with decorative blobs | 3-column blog post cards with category badges, author avatars, read more CTA |
| 11 | `faqs` | Gradient blue-white (`from-blue-50/30 via-white to-blue-50/30`) | Accordion with Alpine.js x-show/x-collapse toggle, numbered badges |
| 12 | `contact_cta` | Navy gradient with decorative blobs | Dark section with white text, "Contact Us" + phone buttons, trust text, scroll animation |
| 13 | `newsletter` | Soft gray-amber gradient | White card with email input + subscribe button, trust text, scroll animation |
| 14 | `trust_bar` | Soft amber-cream gradient | Horizontal trust badges (truck, shield, returns, lock), scroll-triggered stagger animation |

## 6. Layout Principles

### Spacing System (Tailwind scale)
- Section vertical padding: `py-14 md:py-20` (standard) or `py-24 md:py-28` (spacious)
- Section content max-width: `max-w-6xl mx-auto` (standard), `max-w-5xl` (hero), `max-w-2xl` (forms)
- Card grid gap: `gap-4 md:gap-6` (tight) or `gap-6 md:gap-8` (standard)
- Card internal padding: `p-6` (compact) or `p-8 md:p-12` (spacious)
- Component spacing: `mb-4` (tight), `mb-8` (standard), `mb-10` (generous)
- Page horizontal padding: `px-4 sm:px-6`

### Grid System
- Hero: Single-column centered, `max-w-5xl`
- Stats: `grid grid-cols-2 lg:grid-cols-4 gap-4 md:gap-5`
- How It Works: `grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-8`
- Testimonials: `grid grid-cols-1 md:grid-cols-3 gap-6`
- Brands: `grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4`
- Shipping stats: `grid grid-cols-2 lg:grid-cols-4 gap-4`
- Footer: `grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-10`

### Border Radius Scale
- Inputs: `rounded-xl` (12px)
- Buttons: `rounded-2xl` (16px)
- Cards (standard): `rounded-2xl` (16px)
- Cards (featured): `rounded-3xl` (24px)
- Hero search bar: `rounded-[2.5rem]` (40px)
- Icon wrappers: `rounded-2xl` (16px)
- Badges/pills: `rounded-full`
- Navbar logo icon: `rounded-xl` (12px)

### Animation System
All animations use Alpine.js with IntersectionObserver:

```javascript
// Standard scroll-triggered entrance
x-data="{ shown: false }"
x-init="
    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    setTimeout(() => shown = true, delay);
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.2 }
    );
    observer.observe($el);
"
:class='shown ? "opacity-100 translate-y-0" : "opacity-0 translate-y-4"'
```

Animations available:
- **Fade in up**: `opacity-0 translate-y-4` → `opacity-100 translate-y-0` (most sections)
- **Scale in**: `opacity-0 scale-95` → `opacity-100 scale-100` (hero search)
- **Blob float**: `animate-blob` with delays (hero background)
- **Float particles**: `animate-float` (hero background)
- **Counter**: `countup` Alpine data component (stats section)
- **Marquee**: `animate-marquee` (infinite horizontal scroll)
- **Stagger**: Delay per index (`delay = index * 100`) for list items
- **Reduced motion**: All animations respect `prefers-reduced-motion: reduce`

## 7. Depth & Elevation

| Level | Treatment | Use |
|-------|-----------|-----|
| Flat (0) | No shadow | Page background, inline text, section backgrounds |
| Subtle (1) | `shadow-sm` | Default cards, list items |
| Interactive (2) | `shadow-sm hover:shadow-lg transition-shadow` | Clickable cards, brand cards |
| Elevated (3) | `shadow-lg` or `shadow-xl shadow-amber/5` | Featured cards, newsletter container, form cards |
| Hero (4) | `shadow-2xl shadow-navy/60` | Hero search bar (glassmorphism) |
| CTA Glow (5) | `shadow-lg shadow-amber/30` or `shadow-lg shadow-navy/30` | Primary/secondary CTA buttons |

**Shadow Philosophy**: OEMHub uses warm-tinted shadows (`shadow-amber/30`, `shadow-navy/30`, `shadow-amber/5`) on key interactive elements. Generic gray shadows (`shadow-sm`, `shadow-lg`) are used for structural cards. Buttons always have colored glow shadows matching their background. Hover states escalate shadow intensity (e.g., `shadow-sm` → `shadow-lg`). Cards with hover lift combine shadow escalation with `hover:-translate-y-1`.

### Glassmorphism (Hero only)
- Background: `bg-white/15 backdrop-blur-xl`
- Border: `border border-white/30`
- Shadow: `shadow-2xl shadow-navy/60`
- Hover: `bg-white/20 border-white/50`
- Used exclusively for the hero search bar

### Decorative Depth (Section Backgrounds)
- **Gradient blobs**: `bg-amber/15 rounded-full filter blur-3xl` at 10–20% opacity
- **Dot grid**: `bg-[radial-gradient(circle,rgba(255,255,255,0.05)_1px,transparent_1px)] bg-[size:24px_24px]` (hero only)
- **Wave divider**: SVG wave between hero and next section

## 8. Do's and Don'ts

### Do
- Use `font-mono` (JetBrains Mono) for every OEM part number — no exceptions
- Use `text-amber-text` (`#B45309`) when amber text appears on white/light backgrounds
- Use `btn-primary` (amber gradient) for the most important action on each page
- Use `section-heading` / `section-badge` / `section-subheading` classes for consistent section headers
- Use `rounded-2xl` or `rounded-3xl` for cards — the site is soft and modern
- Use `shadow-amber/30` glow on amber buttons and `shadow-navy/30` on navy buttons
- Keep all monetary values in `font-mono` for precision feel
- Use IntersectionObserver-based scroll animations for section entrances
- Use Alpine.js (`x-data`, `x-show`, `x-bind`) for all interactivity
- Respect `prefers-reduced-motion` — all animations must have `motion-reduce:` fallbacks
- Include honeypot field on all public forms: `<input type="text" name="website" class="hidden" tabindex="-1">`
- Use Heroicons v2 outline (`<x-heroicon-o-...>`) for UI, solid (`<x-heroicon-s-...>`) for active states only

### Don't
- **NEVER** use `#F59E0B` (amber) as text on white backgrounds — fails WCAG. Use `#B45309` instead
- **NEVER** use Vue.js, React, Livewire, jQuery, or any JS framework besides Alpine.js
- **NEVER** render SEO tags (`<meta>`, hreflang, canonical, JSON-LD) with JavaScript — server-side Blade only
- **NEVER** use product images — OEM parts are text-only by design
- **NEVER** use `Cache::flush()` — invalidate specific keys only
- Don't use sharp corners (`rounded-sm`, `rounded`) — minimum is `rounded-xl` for inputs, `rounded-2xl` for cards/buttons
- Don't use cool blue-gray backgrounds for sections — page bg is `#F8FAFC`, alt sections use `#EEF4FF`
- Don't use generic gray shadows on buttons — always use warm-tinted shadows
- Don't hardcode settings values — always use `settings('key', default)` helper
- Don't use Google Fonts CDN — fonts are bundled via `@fontsource` and Vite
- Don't create dedicated /login or /register pages — authentication uses modals only
- Don't add inline `style=""` attributes except for dynamic CSS variables from settings
- Don't use custom CSS classes for one-off styles — Tailwind utility classes only

## 9. Responsive Behavior

### Breakpoints (Tailwind defaults)
| Name | Width | Key Changes |
|------|-------|-------------|
| Default | < 640px | Single column, compact padding, hamburger nav, hero text 4xl |
| `sm` | ≥ 640px | 2-column grids begin, slightly wider content |
| `md` | ≥ 768px | 3-column layouts, expanded section padding, hero text 6xl |
| `lg` | ≥ 1024px | 4-column grids, desktop nav visible, full sidebar |
| `xl` | ≥ 1280px | Maximum content width, most generous spacing |

### Touch Targets
- Buttons: minimum `py-3 px-6` (48px+ height)
- Nav links: `px-4 py-2.5` with adequate spacing
- Card surfaces: entire card is clickable where applicable
- Mobile menu items: full-width with generous vertical padding

### Collapsing Strategy
- **Navbar**: Full horizontal nav → hamburger menu with slide-out panel
- **Hero heading**: `text-4xl` → `text-5xl` → `text-6xl` → `text-7xl` across breakpoints
- **Stats grid**: `grid-cols-2` mobile → `grid-cols-4` desktop
- **Feature grids**: `grid-cols-1` → `grid-cols-2` → `grid-cols-3` or `grid-cols-4`
- **Footer**: `grid-cols-1` → `grid-cols-2` → `grid-cols-4`
- **Section padding**: `py-14 px-4` mobile → `py-20 px-6` desktop
- **Card padding**: `p-6` mobile → `p-8 md:p-12` desktop

## 10. Agent Prompt Guide

### Quick Color Reference
```
Primary Brand:     Navy #0B3A68
Accent:            Amber #F59E0B
Amber Text:        #B45309 (on light bg ONLY)
Body Text:         #334155
Muted Text:        #64748B
Page Background:   #F8FAFC
Section Alt:       #EEF4FF
Card Surface:      #FFFFFF
```

### Quick Class Reference
```
Heading:     font-display text-3xl md:text-4xl lg:text-5xl font-black text-navy
Body:        text-base text-body
Muted:       text-sm text-muted
OEM Number:  font-mono uppercase
Button:      btn-primary / btn-secondary / btn-outline / btn-ghost
Card:        bg-white rounded-2xl shadow-sm border border-gray-100 p-6
Section:     py-14 md:py-20 px-4
Badge:       section-badge
Icon:        icon-wrapper-gradient icon-md + icon-inner-md
```

### Example Component Prompts
- "Create a section on white background with a `section-badge` eyebrow in amber, a `section-heading` in navy, and a `section-subheading` in body text. Below, add a 3-column grid of white cards with `rounded-2xl shadow-sm border border-gray-100`. Each card has an `icon-wrapper-gradient icon-md` at top, a `font-display text-lg font-bold text-navy` title, and `text-sm text-body` description."

- "Build a CTA section on navy background (`bg-gradient-to-r from-navy via-navy to-blue-800`). White headline in `font-display text-3xl font-black`. Subheading in `text-white/70`. Two buttons: `btn-primary` (amber gradient) and an outline button with `border-2 border-white/30 text-white hover:bg-white/10`. Add decorative blobs: `bg-amber/15 rounded-full filter blur-3xl` at low opacity."

- "Design an inline form in a white card (`bg-white rounded-2xl shadow-lg p-8`). Form fields: OEM number input with `font-mono uppercase bg-gray-50 border border-gray-200 rounded-xl focus:border-navy focus:ring-2 focus:ring-navy/10`. Email input below. Full-width `btn-primary` submit button. Trust badge below: lock icon + text in `text-xs text-muted`."

- "Create a product listing row with OEM number in `font-mono text-sm text-navy font-medium`, price in `font-mono text-lg font-bold text-navy`, condition badge using the condition color map (e.g., `bg-condition-new-bg text-condition-new-text rounded-full px-3 py-1 text-xs font-semibold`), and an 'Add to Cart' `btn-primary btn-sm`."

### Iteration Guide
1. Always specify `font-display` or `font-sans` or `font-mono` — never leave font family ambiguous
2. Reference Tailwind classes directly: "use `text-navy`" not "use navy color"
3. For dark sections, always add `text-white` base and `text-white/70` for secondary text
4. For amber text on light bg, always say `text-amber-text` not `text-amber`
5. For shadows on buttons, specify the glow: `shadow-lg shadow-amber/30` not just `shadow-lg`
6. For animations, say "IntersectionObserver fade-in-up with stagger delay" — never use CSS-only animations for entrance effects
7. For interactive states, always include `transition-all duration-300` and specify the hover transform
8. Every OEM number anywhere on the site must include `font-mono` — this is non-negotiable
