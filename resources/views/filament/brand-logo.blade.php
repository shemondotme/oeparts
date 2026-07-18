{{--
  Admin brand mark: the SAME hex-bolt-and-cross mark used on the storefront
  (navbar.blade.php's logo block), not a separate placeholder hexagon.

  Raw SVG presentation attributes + a hand-written <style> block, NOT
  Tailwind fill-gray-950/dark:fill-white utility classes — the login page's
  identical icon (login-heading.blade.php) confirmed live that arbitrary
  colour utilities on SVG fill/stroke don't reliably compile in Filament's
  admin theme CSS build (rendered as a flat solid hexagon, no dark:
  variant, zero error). This version was never actually checked in dark
  mode and almost certainly had the same bug. `html.dark …` hooks directly
  into the `dark` class Filament toggles on <html> (see its own
  resources/js/dark-mode.js), independent of Tailwind compilation.
--}}
<div class="oe-topbar-brand">
    <svg viewBox="0 0 60 60" aria-hidden="true">
        <path d="M30 3 L53 16 L53 44 L30 57 L7 44 L7 16 Z" class="oe-tb-hex-outer" />
        <path d="M30 13 L44.5 21.5 L44.5 38.5 L30 47 L15.5 38.5 L15.5 21.5 Z" class="oe-tb-hex-inner" />
        <path d="M30 18 L30 42 M18 30 L42 30" stroke-width="2.5" stroke-linecap="square" class="oe-tb-hex-cross" />
        <circle cx="30" cy="30" r="3.2" fill="#F59E0B" />
    </svg>

    <span class="oe-topbar-brand-word">OeParts</span>
</div>

<style>
    .oe-topbar-brand { display: flex; align-items: center; gap: 0.625rem; }
    .oe-topbar-brand svg { width: 1.75rem; height: 1.75rem; flex-shrink: 0; }

    /* Light sidebar background (default) */
    .oe-tb-hex-outer { fill: #030712; }
    .oe-tb-hex-inner { fill: #ffffff; }
    .oe-tb-hex-cross { stroke: #030712; }
    .oe-topbar-brand-word {
        font-size: 1.125rem; font-weight: 700; letter-spacing: -0.025em; color: #030712;
        font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;
    }

    /* Dark sidebar background */
    html.dark .oe-tb-hex-outer { fill: #ffffff; }
    html.dark .oe-tb-hex-inner { fill: #030712; }
    html.dark .oe-tb-hex-cross { stroke: #ffffff; }
    html.dark .oe-topbar-brand-word { color: #ffffff; }
</style>
