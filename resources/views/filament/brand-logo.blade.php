{{--
  Admin topbar brand mark — the SAME hex-bolt-and-cross composition, hover
  rotate + color-invert, corner badge, and split-weight wordmark as the
  storefront navbar (navbar.blade.php's logo block) and the admin login
  page (login-heading.blade.php), just compact for a topbar corner instead
  of a full focal-point lockup. Previously this view only had the bare
  hexagon with a hardcoded (non-hoverable) amber dot and a plain single-
  weight "OeParts" span — none of the hover/badge/split-weight treatment
  the other two brand marks share, so it read as a different, lesser logo.

  Raw SVG presentation attributes + a hand-written <style> block, NOT
  Tailwind fill-*/dark:/group-hover: utility classes — login-heading.blade.php
  confirmed live that arbitrary colour utilities on SVG fill/stroke don't
  reliably compile in Filament's admin theme CSS build (rendered as a flat
  solid hexagon, no dark: variant, zero error) even though ordinary
  utilities elsewhere in the same admin theme work fine. `html.dark …`
  hooks directly into the `dark` class Filament toggles on <html> (see its
  own resources/js/dark-mode.js), independent of Tailwind compilation.

  Dark-background hover colours match login-heading.blade.php's treatment
  (NOT a blind invert of the light version) — an ink-coloured hover badge
  would vanish into an already-dark sidebar.
--}}
<div class="oe-topbar-brand">
    <div class="oe-topbar-brand-icon">
        <svg viewBox="0 0 60 60" aria-hidden="true">
            <path d="M30 3 L53 16 L53 44 L30 57 L7 44 L7 16 Z" class="oe-tb-hex-outer" />
            <path d="M30 13 L44.5 21.5 L44.5 38.5 L30 47 L15.5 38.5 L15.5 21.5 Z" class="oe-tb-hex-inner" />
            <path d="M30 18 L30 42 M18 30 L42 30" stroke-width="2.5" stroke-linecap="square" class="oe-tb-hex-cross" />
            <circle cx="30" cy="30" r="3.2" class="oe-tb-hex-dot" />
        </svg>
        <span class="oe-tb-hex-badge"></span>
    </div>

    <span class="oe-topbar-brand-word">
        <span class="oe-tb-word-heavy">Oe</span><span class="oe-tb-word-light">Parts</span><span class="oe-tb-word-dot">.</span>
    </span>
</div>

<style>
    .oe-topbar-brand { display: flex; align-items: center; gap: 0.625rem; }
    .oe-topbar-brand-icon { position: relative; width: 1.75rem; height: 1.75rem; flex-shrink: 0; transition: transform .3s; }
    .oe-topbar-brand-icon svg { width: 100%; height: 100%; }
    .oe-topbar-brand:hover .oe-topbar-brand-icon { transform: rotate(30deg); }
    .oe-tb-hex-outer, .oe-tb-hex-inner, .oe-tb-hex-dot { transition: fill .2s; }
    .oe-tb-hex-cross { transition: stroke .2s; }
    .oe-tb-hex-badge {
        position: absolute; top: -1px; right: -1px; width: 0.375rem; height: 0.375rem;
        transition: background-color .2s;
    }

    /* ── Light sidebar background (default) — matches navbar.blade.php ── */
    .oe-tb-hex-outer { fill: #0A1228; }
    .oe-tb-hex-inner { fill: #F7F3E7; }
    .oe-tb-hex-cross { stroke: #0A1228; }
    .oe-tb-hex-dot { fill: #F59E0B; }
    .oe-tb-hex-badge { background: #F59E0B; }
    .oe-topbar-brand:hover .oe-tb-hex-outer { fill: #F59E0B; }
    .oe-topbar-brand:hover .oe-tb-hex-inner { fill: #0A1228; }
    .oe-topbar-brand:hover .oe-tb-hex-cross { stroke: #F59E0B; }
    .oe-topbar-brand:hover .oe-tb-hex-dot { fill: #F7F3E7; }
    .oe-topbar-brand:hover .oe-tb-hex-badge { background: #0A1228; }

    .oe-topbar-brand-word {
        font-size: 1.125rem; font-weight: 800; letter-spacing: -0.025em;
        font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;
    }
    .oe-tb-word-heavy { color: #0A1228; }
    .oe-tb-word-light { color: #4E5A74; font-weight: 400; }
    .oe-tb-word-dot { color: #F59E0B; }

    /* ── Dark sidebar background (html.dark, Filament's own toggle class) —
         matches login-heading.blade.php's light-on-dark hover treatment,
         NOT a blind colour-invert of the light version above. ── */
    html.dark .oe-tb-hex-outer { fill: #F7F3E7; }
    html.dark .oe-tb-hex-inner { fill: #0A1228; }
    html.dark .oe-tb-hex-cross { stroke: #F7F3E7; }
    html.dark .oe-topbar-brand:hover .oe-tb-hex-outer { fill: #F59E0B; }
    html.dark .oe-topbar-brand:hover .oe-tb-hex-inner { fill: #0A1228; }
    html.dark .oe-topbar-brand:hover .oe-tb-hex-cross { stroke: #F59E0B; }
    html.dark .oe-topbar-brand:hover .oe-tb-hex-dot { fill: #F7F3E7; }
    html.dark .oe-topbar-brand:hover .oe-tb-hex-badge { background: #F7F3E7; }
    html.dark .oe-tb-word-heavy { color: #F7F3E7; }
    html.dark .oe-tb-word-light { color: rgba(247, 243, 231, 0.55); }
</style>
