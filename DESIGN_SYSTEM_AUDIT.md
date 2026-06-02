# OeParts Design System Audit
**Date:** 2026-05-16  
**Scope:** Frontend storefront + admin panel — tokens, components, patterns  
**Files reviewed:** `tailwind.config.js`, `resources/css/app.css`, `resources/css/admin.css`, all Blade views under `resources/views/` (excluding `_backup_*`)

---

## Summary

| | |
|---|---|
| **Components reviewed** | 3 button systems, 2 badge systems, 1 alert, 1 table, section patterns |
| **Critical issues** | 3 |
| **Medium issues** | 4 |
| **Low / polish** | 3 |
| **Overall score** | 62 / 100 |

The system has a solid token foundation and a well-executed Industrial Blueprint design language. The main problems are dead code from an earlier design language that was never removed, a font stack discrepancy between CLAUDE.md and the actual config, and a naming collision on shared class names (`bp-btn`, `bp-card`) that renders differently in the two CSS entry points.

---

## 🔴 Critical Issues

### 1. Three competing button systems — two of them dead code

The codebase contains three separate button implementations, but only one is actively used.

**System A — `components/button.blade.php`** (old gradient system):
- Props: `variant`, `size`, `href`
- Visual style: `rounded-2xl`, gradient backgrounds (`from-amber to-orange-500`, `from-navy to-navy/90`), `hover:scale-105` animation, shimmer overlay span
- **Usage count: 0** — `<x-button>` appears nowhere in live views

**System B — `components/ui/button.blade.php`** (token-based solid system):
- Props: `variant` (primary/secondary/outline/ghost/danger), `size` (sm/md/lg), `href`, `loading`
- Visual style: `rounded-lg`, flat solid fills (`bg-navy`, `bg-amber`), proper `disabled` states, loading spinner
- **Usage count: 0** — `<x-ui.button>` appears nowhere in live views

**System C — `bp-btn-*` CSS classes** (Industrial Blueprint):
- Defined as CSS utilities in both `app.css` and `admin.css`
- Visual style: flat, sharp corners, uppercase tracking, no shadows on storefront; rounded-xl + shadows on admin
- **This is the system actually used everywhere** — 25+ usages across frontend and admin views

**Recommendation:** Delete `components/button.blade.php` and `components/ui/button.blade.php`. They add confusion without serving any current purpose. Standardize on `bp-btn-*` classes. If a Blade component wrapper is needed in the future, create a single `<x-bp-button>` that wraps `bp-btn-*`.

---

### 2. `bp-btn` and `bp-card` have divergent definitions in `app.css` vs `admin.css`

The same class names produce completely different visual results depending on which CSS file is loaded:

| Class | In `app.css` (storefront) | In `admin.css` (admin panel) |
|---|---|---|
| `bp-btn` | No border-radius, uppercase tracking, flat — "draftsman" aesthetic | `rounded-xl`, `shadow-md`, `text-admin-sm`, SaaS aesthetic |
| `bp-card` | `bg-paper border border-rule` — flat, hairline, no radius | `rounded-2xl border border-slate-200/80 shadow-admin-card backdrop-blur-sm` |
| `bp-btn-primary` | `bg-ink text-ivory hover:bg-amber` | `bg-gradient-to-b from-brand-500 to-brand-600 text-white` — indigo |
| `bp-btn-outline` | `bg-transparent text-ink border-ink hover:bg-ink hover:text-ivory` | `bg-white/90 text-slate-700 hover:bg-slate-50` |

Both files are intentionally separate (storefront vs admin), and the divergence is partly intentional design. However, **the shared naming is a trap**: if a developer copy-pastes a `bp-btn-primary` button from an admin view into a storefront view expecting the same look, they'll get the ink/ivory Blueprint style, not the indigo gradient — and vice versa.

**Recommendation:** Rename admin-specific classes to `sl-btn-*` (sl = Slate Enterprise) or add a comment block at the top of each file documenting that `bp-*` classes have context-specific overrides. Failing that, at minimum add a prominent comment warning.

---

### 3. Font stack in CLAUDE.md is stale — documents Inter, codebase has migrated to Geist

`CLAUDE.md` documents the typography as:
```
font-display: Plus Jakarta Sans  — H1, H2, H3
font-sans:    Inter              — body, labels, nav
font-mono:    JetBrains Mono    — OEM numbers ONLY
```

The actual `tailwind.config.js` is:
```js
sans:    ['Geist Sans', 'ui-sans-serif', ...],
display: ['Plus Jakarta Sans', 'Geist Sans', ...],
mono:    ['Geist Mono', 'JetBrains Mono', ...],
```

And `app.css` imports `@fontsource/inter` (three weights) while `admin.css` imports `@fontsource/geist-sans`. This means the **storefront loads Inter but the config stack puts Geist first** — on any system that has Geist installed (or if `@fontsource/geist-sans` is later added to `app.css`), the actual rendered font would change. The admin is correctly aligned to Geist.

**Recommendation:**
1. Update `CLAUDE.md` typography section to reflect Geist Sans / Geist Mono
2. Replace the three `@fontsource/inter/*` imports in `app.css` with `@fontsource/geist-sans` variants (same weights: 400, 500, 600)
3. Keep JetBrains Mono as the fallback mono — Geist Mono may lack some OEM number glyphs

---

## 🟡 Medium Issues

### 4. Legacy "gradient" CSS system is dead code in `app.css`

The first `@layer components` block in `app.css` (lines 48–244) defines a full pre-Blueprint system:

- `.btn-primary` — `rounded-2xl`, `bg-gradient-to-r from-amber to-orange-500`, `hover:scale-105`
- `.btn-secondary` — `rounded-2xl`, navy gradient
- `.btn-outline`, `.btn-ghost`
- `.btn-sm`, `.btn-md`, `.btn-lg`
- `.icon-wrapper`, `.icon-wrapper-gradient`, `.icon-wrapper-solid`, `.icon-wrapper-outline`
- `.card-shadow-sm/md/lg`, `.card-radius`, `.card-radius-lg`

These classes are **not used in any live Blade file**. They survive from the pre-Blueprint era and add ~100 lines of dead CSS to every storefront page load.

**Recommendation:** Remove the entire legacy section (lines 48–243 of `app.css`). Keep the Blueprint section (`/* INDUSTRIAL BLUEPRINT — v2 */`) and everything below it.

---

### 5. `section-badge` uses `backdrop-blur-sm` and gradient — violates Blueprint rules

The `.section-badge` component class in `app.css`:
```css
.section-badge {
    @apply bg-gradient-to-r from-amber/15 to-orange-50/15 border border-amber/25 backdrop-blur-sm text-amber-text;
}
```

The Industrial Blueprint design language explicitly avoids gradients (replaced by flat fills) and decorative effects. The `section-heading` Blade component uses `section-badge`, which means every section eyebrow label breaks the Blueprint aesthetic.

**Also:** `.section-accent-bar-main` uses `bg-gradient-to-r from-amber via-orange-500 to-amber`.

**Recommendation:**
```css
/* Replace section-badge with */
.section-badge {
    @apply inline-flex items-center gap-2 px-4 py-1.5 text-xs font-bold tracking-widest uppercase
           bg-amber/10 border border-amber/25 text-amber-text;
}

/* Replace section-accent-bar-main with */
.section-accent-bar-main {
    @apply w-16 h-[3px] bg-amber;
}
```

---

### 6. 457 instances of arbitrary tracking values instead of `spec-sm` / `spec-xs` tokens

The `tailwind.config.js` defines named spec label sizes:
```js
"spec-xs": ["10px", { lineHeight: "1.2", letterSpacing: "0.22em" }],
"spec-sm": ["11px", { lineHeight: "1.3", letterSpacing: "0.20em" }],
```

These sizes include letter-spacing. The `.bp-spec` class uses `text-spec-sm`. However, across 457 locations in storefront views, developers are writing:
```html
<span class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">
```
instead of:
```html
<span class="bp-spec text-ink-muted">
```

This creates maintenance risk: changing the spec label size requires updating 457 sites instead of one token.

**Recommendation:** Audit usages and replace inline `text-[10px] tracking-[0.22em] uppercase` combos with `bp-spec` or `text-spec-sm` + `tracking-[0.22em]`. The most common pattern is `font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted` — introduce a `.bp-spec-mono` variant:
```css
.bp-spec-mono {
    @apply font-mono text-spec-xs uppercase text-ink-muted;
}
```

---

### 7. Two badge systems with overlapping scope

Frontend has two badge components:

**`<x-ui.badge>`** — rounded-full pill, colors: gray/blue/green/amber/red/purple/teal/navy. Uses `text-amber-text` correctly for amber variant.

**`<x-admin.status-badge>`** — flat mono chip (border, no border-radius on the pill), status-based: active/pending/inactive/failed. Uses `amber/10` + `text-amber-text`.

These serve different semantic purposes (color labeling vs status labeling), but there's no shared "condition badge" component for the product condition badges defined in the token system. The condition tokens exist in `tailwind.config.js`:
```js
"condition-new-bg": "#DCFCE7",
"condition-new-text": "#16A34A",
// ... 7 conditions
```

But there is **no `<x-condition-badge>` component** — condition badges must be assembled manually every time they appear in search results and product views.

**Recommendation:** Create `components/ui/condition-badge.blade.php`:
```php
@props(['condition'])
@php
$map = [
    'new'            => 'bg-condition-new-bg text-condition-new-text',
    'used_grade_a'   => 'bg-condition-used-a-bg text-condition-used-a-text',
    'used_grade_b'   => 'bg-condition-used-b-bg text-condition-used-b-text',
    'used_grade_c'   => 'bg-condition-used-c-bg text-condition-used-c-text',
    'remanufactured' => 'bg-condition-remanufactured-bg text-condition-remanufactured-text',
    'aftermarket'    => 'bg-condition-aftermarket-bg text-condition-aftermarket-text',
    'new_old_stock'  => 'bg-condition-nos-bg text-condition-nos-text',
];
$cls = $map[$condition] ?? 'bg-slate-100 text-slate-600';
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center px-2 py-0.5 text-xs font-semibold font-mono uppercase tracking-wide rounded $cls"]) }}>
    {{ $slot->isEmpty() ? str_replace(['_', 'grade '], [' ', ''], $condition) : $slot }}
</span>
```

---

## 🟢 Low / Polish

### 8. `components/icon-wrapper.blade.php` exists but variant classes are in CSS

The `<x-icon-wrapper>` Blade component exists (`.icon-wrapper`, `.icon-wrapper-gradient`, etc.), but the CSS class-based variants from the old gradient system are what actually power it. Since those CSS classes are dead code targets (issue #4), the component itself will break if the legacy CSS is removed.

**Recommendation:** Before deleting the legacy CSS, either update `<x-icon-wrapper>` to use Blueprint-compatible classes or delete it too (check if it has any live usages first).

---

### 9. `admin-flash-*` alert classes use `border-l-4` with `rounded-xl` — corner inconsistency

```css
.admin-flash-success {
    @apply ... rounded-xl border border-emerald-200/70 border-l-4 border-l-emerald-500 ...;
}
```

Using `border-l-4` with `rounded-xl` creates a thicker left border that looks misaligned at the rounded corners. This is a browser rendering artifact — the left border blends awkwardly into the rounded corner.

**Recommendation:** Use a pseudo-element or an inner `div` for the accent stripe instead:
```html
<div class="flex items-stretch gap-3 ...">
    <div class="w-1 rounded-full bg-emerald-500 shrink-0"></div>
    <div class="flex items-start gap-3 flex-1">...</div>
</div>
```

---

### 10. CLAUDE.md `DO NOT BUILD` list should be updated to reflect Geist Mono

The rule currently says:
> OEM numbers MUST always use `font-mono` / JetBrains Mono

With Geist Mono now first in the stack, this should say "Geist Mono (JetBrains fallback)" to avoid confusion about which font renders OEM numbers.

---

## Token Coverage Audit

| Category | Defined | Status |
|---|---|---|
| Brand colors (navy, amber, amber-text) | ✅ in `tailwind.config.js` | Correctly used via tokens |
| Semantic grays (body, muted, bg-page) | ✅ | Correctly used |
| Blueprint tokens (ink, ivory, rule, etc.) | ✅ | Well-used in bp-* system |
| Admin brand (brand-50…900 indigo) | ✅ | Admin only — correct |
| Condition badge tokens | ✅ defined | ⚠️ No component wrapper |
| Typography scale (admin-sm, spec-sm, blueprint-xl) | ✅ | ⚠️ 457 inline overrides |
| Shadows (admin-card, admin-sidebar) | ✅ | Admin only — correct |
| Font families | ⚠️ Config ≠ CLAUDE.md ≠ CSS imports | Needs reconciliation |

---

## Component Completeness

| Component | Variants | States | Docs | Score |
|---|---|---|---|---|
| `bp-btn-*` (primary, amber, outline, ghost) | ✅ | ✅ disabled, active | ❌ | 7/10 |
| `<x-ui.button>` | ✅ 5 variants | ✅ loading, disabled | ❌ | 0/10 (unused) |
| `<x-button>` (legacy) | ⚠️ 4 variants | ❌ no disabled | ❌ | 0/10 (unused) |
| `<x-ui.badge>` | ✅ 8 colors | — | ❌ | 6/10 |
| `<x-admin.status-badge>` | ✅ status-driven | — | ❌ | 7/10 |
| `<x-ui.alert>` | ✅ 4 types | ✅ dismissable | ❌ | 8/10 |
| `<x-condition-badge>` | ❌ does not exist | — | — | 0/10 |
| `bp-table` | ✅ thead/td/empty | ✅ hover, responsive stack | ❌ | 8/10 |
| `bp-input`, `bp-input-mono` | ✅ 2 types | ✅ focus | ❌ | 7/10 |
| `<x-section-heading>` | ✅ dark/light | — | ❌ | 7/10 |

---

## Priority Actions

**Do immediately:**

1. **Delete the two unused Blade button components** (`components/button.blade.php`, `components/ui/button.blade.php`) — they are actively misleading. The single button system is `bp-btn-*`.

2. **Fix the font stack** — replace `@fontsource/inter` imports in `app.css` with `@fontsource/geist-sans`, then update `CLAUDE.md` to document Geist Sans as primary body font.

3. **Add a warning comment** to both CSS files at the `bp-btn` / `bp-card` definitions: `/* NOTE: This class is redefined in admin.css with different visual treatment */`.

**Do soon:**

4. **Create `<x-condition-badge>`** — condition badges appear in search results (the highest-traffic page) and are assembled manually. One component for all 7 conditions.

5. **Remove dead gradient CSS** from `app.css` lines 48–243 (`.btn-primary`, `.btn-secondary`, `.icon-wrapper-*`, `.card-shadow-*`, `.card-radius`). Run `php artisan view:cache` + visual spot-check to confirm nothing breaks.

6. **Replace `backdrop-blur-sm` and gradient in `.section-badge`** with flat Blueprint equivalents.

**Backlog:**

7. **Introduce `.bp-spec-mono`** utility to absorb the 457 inline `font-mono text-[10px] tracking-[0.22em] uppercase` combos.

8. **Fix admin flash alert** corner radius + border-l rendering artifact.

9. **Audit `<x-icon-wrapper>`** usages before removing legacy icon-wrapper CSS.

10. **Update CLAUDE.md** `DO NOT BUILD` and design token sections to reflect current state.
