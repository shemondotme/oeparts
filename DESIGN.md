# OEMHub — Design System
## Industrial Blueprint v2 — Single Source of Truth

This document governs **every** visual surface of OEMHub: public frontend, transactional emails, admin panel, and any future page, component, or template. If a pattern is not in this file, it does not exist — add it here first, then build it.

**Read order:**
1. §1–3: Philosophy + tokens + typography (read once, internalize)
2. §4–5: Primitives + components (reference while building)
3. §8: Accessibility (non-negotiable)
4. §11–12: Email + admin adaptations (only when building those surfaces)
5. §13: Page-creation checklist (use every time you create a new page)

---

## 1. Brand Philosophy

OEMHub is a **search-first parts catalog** for European mechanics and workshops. The interface looks and feels like a **technical specification sheet** — flat, hairline-ruled, monospace-dense, documentary. No rounded corners on structural elements. No drop shadows as visual candy. No gradients for their own sake. Every ornament earns its place by signalling category, status, or hierarchy.

We call this aesthetic **Industrial Blueprint**. It borrows from:
- Engineering drawings (callouts, section markers, numbered indices)
- Parts manuals (monospaced part numbers, leader dots, tabular alignment)
- Swiss typography (strict grid, hairline rules, asymmetric weight)
- Early-web documentary design (`§` glyphs, small caps, tracked uppercase labels)

**Core principles:**

| Principle | What it means in practice |
|---|---|
| **Flat, not skeuomorphic** | No box-shadows except the signature `4px 4px 0 ink` stamp. No gradients except intentional ink-to-ink blueprint fields. No glassmorphism. |
| **Hairline rules over fills** | 1px `border-rule` lines separate content. Backgrounds stay ivory/paper; separation comes from rules, not tinted blocks. |
| **Monospace carries authority** | Part numbers, spec labels, breadcrumbs, timestamps, IDs — all `font-mono`. Display type (`font-display`) is reserved for headlines and product names. |
| **Amber is a scalpel, not a highlighter** | Amber marks **one** action or **one** status per viewport. Used everywhere it stops signalling anything. |
| **Dense, tabular, scannable** | B2B users scan tables. Columns align on tabular numerals. Leader dots connect label to value. Whitespace comes from rule density, not padding. |
| **Progressive reveal, not spectacle** | Subtle `fade-in-up` and `rule-draw` entrance animations. No parallax. No hero video. No confetti. |

**The aesthetic pledge:** If a component looks like it could appear on a modern SaaS landing page, it is wrong for OEMHub. If it could appear on a printed parts catalogue from a precision manufacturer, it is right.

---

## 2. Design Tokens

All tokens are defined in `tailwind.config.js` and consumed via Tailwind utilities. Never hardcode hex values in Blade templates.

### 2.1 Color Tokens

#### Structural ink & paper
| Token | Hex | Use |
|---|---|---|
| `ink` | `#0A1228` | Primary text on light surfaces. Dark header backgrounds. Borders on cards (`border-ink`). |
| `ink-muted` | `#4E5A74` | Secondary text, labels, metadata, de-emphasized content. |
| `ivory` | `#F7F3E7` | Primary page background. Warm off-white. |
| `ivory-alt` | `#EFE9D6` | Section alt background, card header strips, hover states. |
| `paper` | `#FFFFFF` | Card surfaces, inputs, modals, form containers. |

#### Rules
| Token | Hex | Use |
|---|---|---|
| `rule` | `#D8CFB6` | Default hairline rule. Dividers, subtle borders. |
| `rule-strong` | `#B8AE90` | Stronger rule on ivory. Dotted leader lines, emphasized separators. |
| `rule-dark` | `#1D2A44` | Rule on dark (`ink`) surfaces. |

#### Accent — amber
| Token | Hex | Use | Contrast |
|---|---|---|---|
| `amber` | `#F59E0B` | Amber fills, accent strips, active-state backgrounds on **dark ink**. Also as text on `ink`/dark surfaces. | ❌ Fails on ivory/paper as text |
| `amber-text` | `#B45309` | Minimum WCAG AA amber text on paper/ivory. Use for body-text inline links. | ✅ AA on paper |
| `amber-ink` | `#9A5A00` | Default amber text token on ivory/paper. Used in `.bp-spec`, eyebrows, inline emphasis. | ✅ AA on ivory/paper |

**Amber contrast rule — non-negotiable:**
- On `ink`/dark surface → `text-amber` is correct
- On `ivory`/`paper` → `text-amber-ink` (preferred) or `text-amber-text` (bolder body links)
- **Never** `text-amber` on paper/ivory — only permitted as decorative non-text (e.g., `bg-amber` fills, the amber period after headings when adjacent to ink-colored siblings at large size).

#### Legacy (do not use for new work)
| Token | Hex | Status |
|---|---|---|
| `navy` | `#0B3A68` | Kept for back-compat only. Use `ink` for new components. |
| `bg-page` | `#F8FAFC` | Kept for back-compat. Use `ivory` for new pages. |

#### Condition badges
Dual-token pairs (bg + text), always used together:
| Condition | Background | Text |
|---|---|---|
| `new` | `#DCFCE7` | `#16A34A` |
| `used_grade_a` | `#DBEAFE` | `#1D4ED8` |
| `used_grade_b` | `#FEF3C7` | `#D97706` |
| `used_grade_c` | `#F1F5F9` | `#64748B` |
| `remanufactured` | `#F3E8FF` | `#7C3AED` |
| `aftermarket` | `#FEE2E2` | `#DC2626` |
| `new_old_stock` | `#ECFDF5` | `#059669` |

#### Semantic (flash banners, states)
| Token | Use |
|---|---|
| `text-emerald-600` / `border-emerald-600` / `bg-emerald-50` | Success banner |
| `text-red-600` / `border-red-600` / `bg-red-50` | Error banner |
| `text-amber-ink` / `border-amber` / `bg-amber-50` | Warning / notice |
| `text-ink` / `border-rule` / `bg-ivory-alt` | Neutral / info |

### 2.2 Border & Shadow

**Borders** are the primary structural device. Use `border-ink` (1px solid) for cards, `border-rule` for dividers, `border-rule-strong` for emphasized separators.

**Radius**: `rounded-none` is the default for all structural elements (cards, buttons, inputs, badges). The only acceptable curves are:
- `rounded-full` for status dots, avatar initials, and chip-style status pills
- `rounded-sm` on a **very** limited set (never on cards)

**Shadows**: one shadow only, the blueprint stamp:
```css
box-shadow: 4px 4px 0 rgba(20,22,29,1);   /* default ink stamp */
box-shadow: 3px 3px 0 rgba(241,145,58,1); /* amber stamp on avatar initials */
box-shadow: 2px 2px 0 rgba(20,22,29,1);   /* small components */
```
No `shadow-lg`, no `drop-shadow`, no glows. The stamp is applied via inline `style` because Tailwind's shadow utilities cannot express `0 blur`.

### 2.3 Background Patterns
Defined in `tailwind.config.js` under `backgroundImage`:
- `bg-grid-ivory` / `bg-grid-ivory-fine` — faint ivory grid for body/hero backgrounds
- `bg-grid-navy` — grid on dark `ink` surfaces
- `bg-dotted-leader` — horizontal dotted rule for leader lines
- `bg-grid-lg` / `bg-grid-md` — paired size utilities

Always layer patterns behind content with `pointer-events-none` and `opacity-40`–`60`.

### 2.4 Spacing
Tailwind default scale (4px base). No custom spacing tokens. Density rules:
- Card header strip: `px-4 py-3`
- Card body: `p-4` (compact) or `p-5`–`p-6` (spacious)
- Form field vertical rhythm: `py-2.5` between rows in a definition list
- Section gap: `gap-y-8` in grid layouts, `space-y-6` between major sections
- Hero padding: `pt-10 pb-6` on dark headers

### 2.5 Z-index
- Preloader: `z-[9999]`
- Modal overlay: `z-50`
- Sticky header / sidebar: `z-40` (or `lg:sticky lg:top-10` for in-page sidebars)
- Flash region: `z-30`
- Default: `z-auto`

---

## 3. Typography System

### 3.1 Families
| Family | CSS | Use |
|---|---|---|
| `font-display` | Plus Jakarta Sans | H1, H2, H3, card titles, product names, body in hero. Weight 800 for hero, 700 for cards, 600 for inline. |
| `font-sans` | Inter | Body text, paragraphs, helper text, button labels. |
| `font-mono` | JetBrains Mono | Spec labels, part numbers, breadcrumbs, timestamps, indices, amounts, form hints. |

Fonts are bundled via `@fontsource/*` imported in `resources/css/app.css`. Never use Google Fonts CDN.

### 3.2 Scale

**Custom fluid scale** (defined in `tailwind.config.js`):
- `text-blueprint-xl` — hero titles (clamp ~48–80px)
- `text-blueprint-lg` — section titles (clamp ~36–56px)
- `text-blueprint-md` — subsection titles (clamp ~24–40px)
- `text-spec-sm` / `text-spec-xs` — spec labels, micro-captions (10–12px with wide tracking)

**Standard Tailwind scale**:
- Body: `text-sm` (14px) default, `text-base` (16px) for prose pages
- Buttons: `text-[11px]` tracked uppercase for spec buttons, `text-sm` for primary actions

### 3.3 Heading Pattern

The signature OEMHub heading:

```blade
<h1 class="font-display font-extrabold text-ink leading-[0.95] tracking-[-0.03em] text-4xl md:text-5xl lg:text-6xl">
    Review order<span class="text-amber">.</span>
</h1>
```

- `font-display` always
- `font-extrabold` (H1) or `font-bold` (H2, H3)
- `tracking-[-0.03em]` (H1) / `tracking-[-0.02em]` (H2) — tight tracking is part of the mark
- `leading-[0.95]` on display sizes
- **Always** terminate with `<span class="text-amber">.</span>` or `<span class="text-amber-ink">.</span>` (pick based on surface contrast)

### 3.4 Spec Labels — `.bp-spec`

Defined in `resources/css/app.css`:
```css
.bp-spec {
    @apply font-mono text-[10px] tracking-[0.22em] uppercase;
}
```
Used for:
- Section eyebrow labels: `§ Manifest · Order items`
- Card header strips
- Form labels above inputs
- Breadcrumbs
- Status chips (`Active`, `Delivered`)

**The `§` glyph** is the OEMHub section marker. Use it to introduce any labeled region. Pair with `·` (middle dot) as separator between classifier tokens.

### 3.5 Monospace rules
- Every OEM part number: `font-mono tabular-nums`
- Every amount, count, timestamp: `font-mono tabular-nums`
- Indices (`01`, `02`, …): `font-mono tabular-nums tracking-[0.18em] uppercase`
- Use `str_pad($n, 2, '0', STR_PAD_LEFT)` to render `01`, `02` — never `1.`, `2.`

### 3.6 Prose (blog, CMS pages)

Use Tailwind `prose` plugin with overrides matched to the design tokens:
```html
<article class="prose prose-neutral max-w-none
                prose-headings:font-display prose-headings:font-bold prose-headings:text-ink prose-headings:tracking-[-0.02em]
                prose-a:text-amber-ink prose-a:font-medium prose-a:underline-offset-2
                prose-strong:text-ink prose-strong:font-bold
                prose-code:font-mono prose-code:bg-ivory-alt prose-code:border prose-code:border-rule prose-code:px-1 prose-code:rounded-none
                prose-hr:border-rule">
  {!! $htmlBody !!}
</article>
```

---

## 4. Core Blueprint Primitives

These are the reusable foundations. Any new component must compose from these — do not invent parallel styles.

### 4.1 `.bp-card` and `.bp-card-ivory`
```blade
{{-- Standard paper card --}}
<div class="border border-ink bg-paper" style="box-shadow: 4px 4px 0 rgba(20,22,29,1);">
    <header class="px-4 py-3 border-b border-ink bg-ivory-alt flex items-center justify-between">
        <span class="bp-spec text-amber-ink flex items-center gap-2">
            <x-heroicon-o-cube class="w-3.5 h-3.5" />
            § Section · Title
        </span>
        {{-- optional right-side count/chip --}}
    </header>
    <div class="p-5">
        {{-- body --}}
    </div>
</div>
```

The card is the primary container for **every** substantive content block on the site. Variants:
- `.bp-card-ivory` — ivory body instead of paper, for lower-priority context blocks
- No-header variant — omit the header strip for content-dominant cards (e.g., long prose)

### 4.2 Buttons

Defined in `resources/css/app.css` (see `@layer components`). Canonical snippets:

| Variant | Use | Class |
|---|---|---|
| Primary | Main CTA | `bp-btn bp-btn-primary` |
| Amber | High-vis action | `bp-btn bp-btn-amber` |
| Outline | Secondary action | `bp-btn bp-btn-outline` |
| Ghost | Tertiary / text-only | `bp-btn bp-btn-ghost` |

```blade
<button class="bp-btn bp-btn-primary">
    Dispatch Query
</button>
```

### 4.3 Inputs

```blade
<input type="text" class="bp-input" placeholder="Enter OEM number..." />
<input type="text" class="bp-input-mono" placeholder="VIN / CHASSIS" />
```

- `bp-input`: Standard text input. Transparent bg, rule border, focus becomes ink border.
- `bp-input-mono`: For technical data (VIN, OEM, Phone). Uppercase, mono font.

### 4.4 Leader Dots (`.bp-leader` / `.bp-leader-dots`)

Used for spec lists where label and value must align visually.

```blade
<div class="bp-leader">
    <dt class="text-ink-muted">Catalogue</dt>
    <span class="bp-leader-dots"></span>
    <dd class="font-mono font-bold text-ink tabular-nums">1,024,837</dd>
</div>
```

### 4.5 Amber Rule (`.bp-amber-rule`)

A short amber underline used to accent headings or key terms.

```blade
<h2 class="bp-amber-rule">Technical Specifications</h2>
```

### 4.6 Inline Link (`.bp-link`)

```blade
<a href="#" class="bp-link">View full details</a>
```

### 4.7 Status Pill

```blade
<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-condition-new-bg text-condition-new-text">
    New
</span>
```

### 4.8 Index Number

```blade
<span class="font-mono text-[10px] font-bold tracking-[0.2em] text-ink-muted">§01</span>
```

### 4.9 Tape (`.bp-tape`)

Diagonal safety tape divider. Used sparingly for section breaks.

```blade
<div class="h-2 w-full bp-tape"></div>
```

### 4.10 Motion — `.bp-rise` / `.bp-rule-draw`

- `.bp-rise`: Fade up entrance. Use on page load for major blocks.
- `.bp-rule-draw`: Animates width of a rule line from left to right. Use under headings.

---

## 5. Component Library

### 5.1 Doc Header (hero)

See `components/sections/hero.blade.php`. Key features:
- Grid texture background
- Corner register marks
- Spec-sheet header row with meta info
- 12-column editorial grid
- Full-width search form with mono input

### 5.2 Breadcrumb

```blade
<nav aria-label="Breadcrumb">
    <ol class="flex items-center gap-2 text-sm font-mono text-ink-muted">
        <li><a href="/" class="hover:text-amber-ink">Home</a></li>
        <li><span class="text-rule">/</span></li>
        <li><a href="/parts" class="hover:text-amber-ink">Parts</a></li>
        <li><span class="text-rule">/</span></li>
        <li class="text-ink">1K0698151E</li>
    </ol>
</nav>
```

### 5.3 Form Field (full)

```blade
<div class="space-y-2">
    <label for="email" class="bp-spec text-ink">Email Address</label>
    <input type="email" id="email" class="bp-input" />
    <p class="text-xs text-ink-muted">We'll never share your email.</p>
</div>
```

### 5.4 Flash Banners

```blade
{{-- Success --}}
<div class="border border-emerald-600 bg-emerald-50 text-emerald-600 px-4 py-3">
    <span class="bp-spec">Success</span>
    <p class="text-sm mt-1">Order placed successfully.</p>
</div>

{{-- Error --}}
<div class="border border-red-600 bg-red-50 text-red-600 px-4 py-3">
    <span class="bp-spec">Error</span>
    <p class="text-sm mt-1">Payment failed. Please try again.</p>
</div>
```

### 5.5 Tables (data-dense)

```blade
<table class="w-full text-sm text-left">
    <thead class="bg-ivory-alt border-b border-ink">
        <tr>
            <th class="px-4 py-3 font-mono text-xs uppercase tracking-wider text-ink-muted">OEM</th>
            <th class="px-4 py-3 font-mono text-xs uppercase tracking-wider text-ink-muted">Price</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-rule">
        <tr class="hover:bg-ivory-alt/50 transition-colors">
            <td class="px-4 py-3 font-mono text-ink">1K0698151E</td>
            <td class="px-4 py-3 font-mono text-ink tabular-nums">€120.00</td>
        </tr>
    </tbody>
</table>
```

### 5.6 Empty State

```blade
<div class="text-center py-12 border border-rule bg-paper">
    <x-heroicon-o-magnifying-glass class="w-12 h-12 mx-auto text-rule-strong" />
    <h3 class="mt-4 font-display font-bold text-ink">No results found</h3>
    <p class="mt-2 text-sm text-ink-muted">Try adjusting your search criteria.</p>
</div>
```

### 5.7 Modal

```blade
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    <div class="bg-paper border border-ink w-full max-w-lg p-6" style="box-shadow: 4px 4px 0 rgba(20,22,29,1);">
        <header class="flex items-center justify-between mb-4">
            <h2 class="font-display font-bold text-ink">Modal Title</h2>
            <button class="text-ink-muted hover:text-ink">&times;</button>
        </header>
        <div class="text-sm text-body">
            Content goes here.
        </div>
    </div>
</div>
```

### 5.8 Modal-only auth

Auth modals follow the same structure but include honeypot fields and specific input types (tel for OTP, email for email).

---

## 6. Layout System

### 6.1 Container widths
- Max width: `max-w-[1440px]`
- Padding: `px-4 sm:px-6 lg:px-10`

### 6.2 Account/dashboard grid
- Sidebar: `w-64` fixed on desktop, hidden on mobile (toggleable)
- Main content: `flex-1`

### 6.3 Body background texture
- Default: `bg-ivory` with `bg-grid-ivory` overlay at low opacity.

### 6.4 Section rhythm
- Vertical spacing between sections: `py-16 md:py-24`
- Internal section padding: `px-4 sm:px-6 lg:px-10`

---

## 7. State Patterns

- **Hover**: Change text color to `amber-ink` or background to `ink` with `text-ivory`.
- **Active**: Translate Y by 1px (`active:translate-y-[1px]`) for buttons.
- **Focus**: `focus-visible:ring-2 focus-visible:ring-ink focus-visible:ring-offset-2 focus-visible:ring-offset-ivory`.
- **Disabled**: `opacity-50 cursor-not-allowed`.

---

## 8. Accessibility — WCAG 2.1 AA (Non-Negotiable)

### 8.1 Contrast
- All text must meet AA contrast ratios.
- Amber text on white/ivory MUST use `amber-ink` (#9A5A00) or `amber-text` (#B45309). Never `amber` (#F59E0B).

### 8.2 Focus
- All interactive elements must have visible focus rings.
- Use `focus-visible` to avoid rings on mouse clicks.

### 8.3 Semantics
- Use proper HTML5 tags: `header`, `nav`, `main`, `footer`, `section`, `article`.
- Headings must be hierarchical (H1 → H2 → H3).

### 8.4 ARIA
- Use `aria-label` for icon-only buttons.
- Use `aria-describedby` for form hints.
- Use `role="alert"` for flash messages.

### 8.5 Touch & keyboard
- Touch targets minimum 44x44px.
- All functionality accessible via keyboard.

### 8.6 Reduced motion
- Respect `prefers-reduced-motion: reduce`.
- Disable animations/transitions for users who prefer reduced motion.

### 8.7 External links
- Indicate external links with an icon or text.

### 8.8 Language
- Declare `lang` attribute on `<html>`.
- Use `hreflang` for multilingual pages.

---

## 9. Motion & Animation

- **Entrance**: `bp-rise` (fade up) for page loads.
- **Drawing**: `bp-rule-draw` for underlines.
- **Hover**: Smooth color transitions (150ms).
- **Loading**: Shimmer effect for skeletons.
- **Reduced motion**: Disable all animations if `prefers-reduced-motion` is set.

---

## 10. Frontend Patterns

### 10.1 Page skeleton
1. SEO Meta Tags (Title, Description, OG, Canonical, Hreflang, JSON-LD)
2. Layout Extension (`@extends('layouts.app')`)
3. Content Section (`@section('content')`)
4. Hero/Header (if applicable)
5. Main Content Grid
6. Footer

### 10.2 Public forms (contact, newsletter, inquiry)
- Always include honeypot field: `<input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">`
- Use CSRF token: `@csrf`
- Use appropriate input types: `email`, `tel`, `text` with `inputmode` hints.

### 10.3 Mobile input hints
- OEM number: `type="text" inputmode="text" autocapitalize="characters"`
- OTP digit: `type="tel" inputmode="numeric" maxlength="1"`
- Email: `type="email" inputmode="email"`
- Phone: `type="tel" inputmode="tel"`
- Price: `type="text" inputmode="decimal"`
- Postal: `type="text" inputmode="numeric"`

### 10.4 Checkout pattern
- Single URL, session-based steps.
- Progress indicator using numbered steps (`§01`, `§02`, etc.).

### 10.5 Trust strip / spec row
- Row of icons/text indicating trust signals (SSL, Verified, Shipping).
- Use `font-mono` and `text-ink-muted`.

### 10.6 Part-number card (catalog listing)
- Image (if available, otherwise placeholder)
- OEM Number (mono, bold)
- Manufacturer
- Price (mono, tabular)
- Condition Badge
- "Add to Cart" button

---

## 11. Email Template Design

### 11.1 Client rules
- Table-based layout for compatibility.
- Inline CSS only.
- Max width 600px.

### 11.2 Email color palette
- Use same tokens as web: `ink`, `ivory`, `amber`, `rule`.
- Background: `#F7F3E7` (ivory).
- Text: `#0A1228` (ink).

### 11.3 Email layout skeleton (600px)
```html
<table width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#F7F3E7">
    <tr>
        <td align="center">
            <table width="600" cellpadding="0" cellspacing="0" border="0" bgcolor="#FFFFFF" style="border: 1px solid #D8CFB6;">
                <!-- Header -->
                <tr>
                    <td style="padding: 20px; border-bottom: 1px solid #D8CFB6;">
                        <h1 style="font-family: sans-serif; color: #0A1228;">OEMHub</h1>
                    </td>
                </tr>
                <!-- Body -->
                <tr>
                    <td style="padding: 20px;">
                        <p style="font-family: sans-serif; color: #334155;">Content...</p>
                    </td>
                </tr>
                <!-- Footer -->
                <tr>
                    <td style="padding: 20px; border-top: 1px solid #D8CFB6; text-align: center;">
                        <p style="font-family: sans-serif; color: #64748B; font-size: 12px;">© {{ date('Y') }} OEMHub</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
```

### 11.4 Email block components
- **Header**: Logo + Site Name
- **Hero**: Headline + Subheadline
- **Order Details**: Table with items, prices, totals
- **CTA Button**: Amber background, Ink text
- **Footer**: Contact info, Unsubscribe link

### 11.5 Per-template guide
- **Order Confirmation**: Include order summary, shipping address, tracking link.
- **Shipping Update**: Include tracking number, carrier, expected delivery.
- **Password Reset**: Include reset link, expiration time.
- **OTP**: Include code, expiration time.

### 11.6 Email anti-patterns
- No JavaScript.
- No external CSS files.
- No complex layouts (floats, grids).
- No large images (optimize for slow connections).

---

## 12. Admin Panel Design

### 12.1 Layout shell
- Sidebar navigation (left, fixed)
- Top bar (search, user menu)
- Main content area

### 12.2 Sidebar nav item
- Icon + Label
- Active state: `bg-white/10 text-white`
- Hover state: `bg-white/5 text-white`

### 12.3 Admin page header
- Title (H1)
- Breadcrumb
- Actions (buttons)

### 12.4 Admin data table
- Sortable columns
- Pagination
- Filters

### 12.5 Stat card
- Label
- Value (large, mono)
- Trend (up/down arrow)

### 12.6 Admin form
- Grouped fields
- Clear labels
- Validation messages

### 12.7 Admin a11y & keyboard
- Same as frontend: focus rings, semantic HTML, ARIA.

### 12.8 Admin-only patterns
- Bulk actions
- Export buttons
- Log viewers

---

## 13. Page-Creation Checklist

### Structure
- [ ] Extend correct layout (`app.blade.php` or `admin.blade.php`)
- [ ] Define SEO meta tags
- [ ] Use semantic HTML5 tags

### Brand signatures
- [ ] Use `ink`, `ivory`, `amber`, `rule` tokens
- [ ] Use `font-display` for headings, `font-mono` for data
- [ ] Add `§` section markers where appropriate
- [ ] Terminate headings with amber period

### Forms (if applicable)
- [ ] Include CSRF token
- [ ] Include honeypot field
- [ ] Use correct input types and modes
- [ ] Add validation messages

### SEO
- [ ] Title tag
- [ ] Meta description
- [ ] Canonical URL
- [ ] Hreflang tags (if multilingual)
- [ ] JSON-LD schema

### Accessibility
- [ ] Alt text for images
- [ ] ARIA labels for icons
- [ ] Focus states visible
- [ ] Contrast ratios met

### Content
- [ ] Headings hierarchical
- [ ] Links descriptive
- [ ] Lists structured

### Performance
- [ ] Images optimized
- [ ] Lazy loading for below-fold content
- [ ] Minimal JS/CSS

### Verification
- [ ] Test on mobile
- [ ] Test on tablet
- [ ] Test on desktop
- [ ] Check console for errors

---

## 14. Anti-Patterns

- ❌ Using `navy` instead of `ink`
- ❌ Using `bg-page` instead of `ivory`
- ❌ Rounded corners on cards/buttons
- ❌ Soft shadows (`shadow-lg`)
- ❌ Gradients for decoration
- ❌ `text-amber` on white/ivory backgrounds
- ❌ Missing honeypot fields on public forms
- ❌ Hardcoded hex colors in Blade
- ❌ JavaScript-rendered SEO tags
- ❌ Float arithmetic for money

---

## Appendix A — File Map

### Tokens & global styles
- `tailwind.config.js` — Color, font, animation definitions
- `resources/css/app.css` — Custom classes (`.bp-*`), imports

### Layouts
- `resources/views/layouts/app.blade.php` — Frontend layout
- `resources/views/layouts/admin.blade.php` — Admin layout

### Shared components
- `resources/views/components/navbar.blade.php`
- `resources/views/components/footer.blade.php`
- `resources/views/components/button.blade.php`
- `resources/views/components/section-heading.blade.php`

### Email templates
- `resources/views/emails/*.blade.php`

### Admin views
- `resources/views/admin/**/*.blade.php`

---

## Appendix B — Review Cadence

- **Weekly**: Audit new pages for token compliance
- **Monthly**: Review accessibility scores
- **Quarterly**: Update design system with new patterns

---

**Last Updated:** 2026-04-20
**Version:** 2.0 (Industrial Blueprint)