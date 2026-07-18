{{--
  Full storefront-matching brand lockup for the login card — replaces both
  Filament's default small framework logo AND the plain text heading (see
  CustomLogin::hasLogo() / getHeading()). Same composition, hover effect,
  and exact hex values as the storefront navbar's logo block
  (resources/views/components/navbar.blade.php) — icon, hover rotate +
  color-invert, split-weight wordmark, monospace subline — just bigger and
  centered, since here it's the page's whole focal point instead of a
  compact corner mark.

  Plain SVG presentation attributes + a hand-written <style> block, NOT
  Tailwind fill-[#xxxxxx]/stroke-[#xxxxxx]/dark:/group-hover: utility
  classes: confirmed live those silently did nothing here (flat solid-black
  hexagon, no inner hex/cross/dot, no hover effect) even though ordinary
  utilities elsewhere in the same file worked fine — Filament's admin theme
  CSS build doesn't reliably pick up color utilities on this page. Raw
  attributes + real CSS have no such dependency, including for dark mode:
  Filament toggles a plain `dark` class on <html> (see its own
  resources/js/dark-mode.js), so `html.dark …` below hooks into that
  directly rather than needing a Tailwind dark: variant to compile.

  Light-background colours match the navbar's rest/hover treatment.
  Dark-background colours match the storefront footer's treatment of this
  same mark (light-on-dark context) — see footer.blade.php's own comment
  on why the inner hex and corner badge specifically DON'T just invert the
  navbar's values verbatim (an ink-coloured hover badge would vanish into
  an already-ink background).
--}}
<div class="oe-login-brand">
    <div class="oe-login-brand-icon">
        <svg viewBox="0 0 60 60" aria-hidden="true">
            <path d="M30 3 L53 16 L53 44 L30 57 L7 44 L7 16 Z" class="oe-hex-outer" />
            <path d="M30 13 L44.5 21.5 L44.5 38.5 L30 47 L15.5 38.5 L15.5 21.5 Z" class="oe-hex-inner" />
            <path d="M30 18 L30 42 M18 30 L42 30" stroke-width="2.5" stroke-linecap="square" class="oe-hex-cross" />
            <circle cx="30" cy="30" r="3.2" class="oe-hex-dot" />
        </svg>
        <span class="oe-hex-badge"></span>
    </div>

    <p class="oe-login-brand-word">
        <span class="oe-word-heavy">Oe</span><span class="oe-word-light">Parts</span><span class="oe-word-dot">.</span>
    </p>
    <p class="oe-login-brand-sub">
        {{ ui_copy('nav_logo_subline', 'navbar.logo_subline') }}
    </p>
</div>

<style>
    .oe-login-brand { display: flex; flex-direction: column; align-items: center; text-align: center; }
    .oe-login-brand-icon { position: relative; width: 4rem; height: 4rem; flex-shrink: 0; transition: transform .3s; }
    .oe-login-brand-icon svg { width: 100%; height: 100%; }
    .oe-login-brand:hover .oe-login-brand-icon { transform: rotate(30deg); }
    .oe-hex-outer, .oe-hex-inner, .oe-hex-dot { transition: fill .2s; }
    .oe-hex-cross { transition: stroke .2s; }
    .oe-hex-badge {
        position: absolute; top: -2px; right: -2px; width: 0.5rem; height: 0.5rem;
        transition: background-color .2s;
    }

    /* ── Light background (default) — matches navbar.blade.php ── */
    .oe-hex-outer { fill: #0A1228; }
    .oe-hex-inner { fill: #F7F3E7; }
    .oe-hex-cross { stroke: #0A1228; }
    .oe-hex-dot { fill: #F59E0B; }
    .oe-hex-badge { background: #F59E0B; }
    .oe-login-brand:hover .oe-hex-outer { fill: #F59E0B; }
    .oe-login-brand:hover .oe-hex-inner { fill: #0A1228; }
    .oe-login-brand:hover .oe-hex-cross { stroke: #F59E0B; }
    .oe-login-brand:hover .oe-hex-dot { fill: #F7F3E7; }
    .oe-login-brand:hover .oe-hex-badge { background: #0A1228; }

    /* ── Dark background (html.dark, Filament's own toggle class) —
         matches footer.blade.php's light-on-dark treatment of this same
         mark, NOT a blind colour-invert of the light version above. ── */
    html.dark .oe-hex-outer { fill: #F7F3E7; }
    html.dark .oe-hex-inner { fill: #0A1228; }
    html.dark .oe-hex-cross { stroke: #F7F3E7; }
    html.dark .oe-login-brand:hover .oe-hex-outer { fill: #F59E0B; }
    html.dark .oe-login-brand:hover .oe-hex-inner { fill: #0A1228; }
    html.dark .oe-login-brand:hover .oe-hex-cross { stroke: #F59E0B; }
    html.dark .oe-login-brand:hover .oe-hex-dot { fill: #F7F3E7; }
    html.dark .oe-login-brand:hover .oe-hex-badge { background: #F7F3E7; }

    .oe-login-brand-word {
        margin-top: 1rem; font-size: 2.25rem; font-weight: 800; letter-spacing: -0.02em;
        line-height: 1; font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;
    }
    .oe-word-heavy { color: #0A1228; }
    .oe-word-light { color: #4E5A74; font-weight: 400; }
    .oe-word-dot { color: #F59E0B; }
    html.dark .oe-word-heavy { color: #F7F3E7; }
    html.dark .oe-word-light { color: rgba(247, 243, 231, 0.55); }

    .oe-login-brand-sub {
        margin-top: 0.5rem; font-size: 10px; font-weight: 600; letter-spacing: 0.24em;
        text-transform: uppercase; color: #4E5A74;
        font-family: 'Geist Mono', ui-monospace, SFMono-Regular, Menlo, monospace;
    }
    html.dark .oe-login-brand-sub { color: rgba(247, 243, 231, 0.55); }
</style>
