<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
@php
    $preloaderService = app(\App\Services\PreloaderService::class);
    $showPreloader = $preloaderService->shouldRender();
    $preloaderTiming = $preloaderService->timingConfig();
    $plMin = $preloaderTiming['min_ms'];
    $plMax = $preloaderTiming['max_ms'];

    $defaultOgImagePath = settings('seo.default_og_image', '');
    $defaultOgImageUrl = $defaultOgImagePath
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($defaultOgImagePath)
        : null;

    $faviconPath = settings('general.favicon_id', '');
    $faviconUrl = $faviconPath
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($faviconPath)
        : null;
    $faviconMime = match (strtolower(pathinfo($faviconPath, PATHINFO_EXTENSION))) {
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'jpg', 'jpeg' => 'image/jpeg',
        default => 'image/png',
    };

    // trim(): every page sets these via block-style @section('title')...
    // @endsection (not the inline @section('title', 'x') form), so the
    // captured section content includes the newline + indentation on
    // either side of the {{ }} expression — confirmed live via curl, e.g.
    // <title>    Genuine OEM Auto Parts...\n</title>. $__env->yieldContent()
    // (what @yield compiles to) has no built-in trim, so every title/
    // description/OG/Twitter tag needs it wrapped here once rather than
    // reformatting all 14 page templates to the inline @section form.
    $pageTitleTag = trim($__env->yieldContent('title', settings('general.site_name', 'OeParts')));
    $pageMetaDescription = trim($__env->yieldContent('meta_description', settings('seo.default_description', '')));
    $pageOgTitle = trim($__env->yieldContent('og_title', settings('general.site_name', 'OeParts')));
    $pageOgDescription = trim($__env->yieldContent('og_description', settings('seo.homepage_description') ?: 'Find genuine OEM auto parts fast. Search by OEM number, compare prices, ship across the EU.'));
@endphp

    {{-- Title --}}
    <title>{{ $pageTitleTag }}</title>

    {{-- Primary meta --}}
    <meta name="description" content="{{ $pageMetaDescription }}">
    @hasSection('meta_robots')
        @yield('meta_robots')
    @else
        <meta name="robots" content="{{ settings('seo.default_robots', 'index,follow') }}">
    @endif

    {{-- Canonical --}}
    @yield('canonical')

    {{-- Open Graph --}}
    <meta property="og:site_name" content="{{ settings('general.site_name', 'OeParts') }}">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="{{ $pageOgTitle }}">
    <meta property="og:description" content="{{ $pageOgDescription }}">
    <meta property="og:url" content="{{ url()->current() }}">
    @hasSection('og_image')
        @yield('og_image')
    @elseif($defaultOgImageUrl)
        <meta property="og:image" content="{{ $defaultOgImageUrl }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="{{ settings('general.site_name', 'OeParts') }} — Genuine OEM Auto Parts">
    @endif
    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $pageOgTitle }}">
    <meta name="twitter:description" content="{{ $pageOgDescription }}">
    @if($defaultOgImageUrl)
        <meta name="twitter:image" content="{{ $defaultOgImageUrl }}">
    @endif
    @if(settings('seo.twitter_handle', ''))
        <meta name="twitter:site" content="{{ settings('seo.twitter_handle') }}">
    @endif

    {{-- Webmaster verification --}}
    @if(settings('seo.google_verification', ''))
        <meta name="google-site-verification" content="{{ settings('seo.google_verification') }}">
    @endif
    @if(settings('seo.bing_verification', ''))
        <meta name="msvalidate.01" content="{{ settings('seo.bing_verification') }}">
    @endif

    {{-- hreflang links (server-side, all 5 locales + x-default) --}}
    @yield('hreflang')

    {{-- rel=prev/next for paginated listing pages --}}
    @yield('pagination_links')

    {{-- JSON-LD structured data --}}
    @yield('json_ld')

    {{-- Favicon · operator-uploaded override, falling back to the coded Industrial Blueprint mark --}}
    @if($faviconUrl)
    <link rel="icon" type="{{ $faviconMime }}" href="{{ $faviconUrl }}">
    <link rel="apple-touch-icon" href="{{ $faviconUrl }}">
    @else
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="apple-touch-icon" href="/apple-touch-icon.svg">
    @endif
    <link rel="mask-icon" href="/favicon.svg" color="#0B1A29">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#0B1A29">
    <meta name="msapplication-TileColor" content="#0B1A29">

    {{-- Vite: frontend CSS + JS --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Custom appearance from settings (CSS variable overrides) --}}
    @if(settings('appearance.custom_css_enabled', false) && settings('appearance.custom_css', ''))
    <style nonce="{{ csp_nonce() }}">
        :root {
            --color-primary: {{ settings('appearance.primary_color', '#0B3A68') }};
            --color-accent:  {{ settings('appearance.accent_color', '#F59E0B') }};
        }
        {!! settings('appearance.custom_css', '') !!}
    </style>
    @endif

    {{-- Google Tag Manager --}}
    @if(settings('integrations.gtm_id', ''))
    <script nonce="{{ csp_nonce() }}">(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer',{{ \Illuminate\Support\Js::from(settings('integrations.gtm_id')) }});</script>
    @endif

    {{-- Google Analytics 4 --}}
    @if(settings('integrations.ga4_measurement_id', ''))
    <script async nonce="{{ csp_nonce() }}" src="https://www.googletagmanager.com/gtag/js?id={{ urlencode(settings('integrations.ga4_measurement_id')) }}"></script>
    <script nonce="{{ csp_nonce() }}">
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', {{ \Illuminate\Support\Js::from(settings('integrations.ga4_measurement_id')) }});
    </script>
    @endif

    {{-- Facebook Pixel --}}
    @if(settings('integrations.fb_pixel_id', ''))
    <script nonce="{{ csp_nonce() }}">
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', {{ \Illuminate\Support\Js::from(settings('integrations.fb_pixel_id')) }});
        fbq('track', 'PageView');
    </script>
    <noscript><img height="1" width="1" style="display:none" alt=""
        src="https://www.facebook.com/tr?id={{ urlencode(settings('integrations.fb_pixel_id')) }}&ev=PageView&noscript=1"
    /></noscript>
    @endif

    {{-- Crisp Chat — grounded in Crisp's own published embed snippet;
         CSP origins added in ContentSecurityPolicy middleware are grounded
         in Crisp's documented domains but NOT live-verified (no browser
         tool available this session) — validate in a real browser before
         relying on this in production, same caveat as the Airwallex CSP. --}}
    @if(settings('integrations.crisp_website_id', ''))
    <script nonce="{{ csp_nonce() }}">
        window.$crisp = [];
        window.CRISP_WEBSITE_ID = {{ \Illuminate\Support\Js::from(settings('integrations.crisp_website_id')) }};
        (function(){
            var d = document, s = d.createElement('script');
            s.src = 'https://client.crisp.chat/l.js';
            s.async = 1;
            d.getElementsByTagName('head')[0].appendChild(s);
        })();
    </script>
    @endif

    {{-- Header scripts (custom) from settings --}}
    @if(settings('general.header_scripts', ''))
    <script nonce="{{ csp_nonce() }}">{!! settings('general.header_scripts', '') !!}</script>
    @endif

    {{-- Per-page extra styles --}}
    @stack('styles')

    @if($showPreloader)
    {{-- ─────────────────────────────────────────────────────────────
         INDUSTRIAL BLUEPRINT · PRELOADER (Admin → Settings → preloader)
    ───────────────────────────────────────────────────────────────── --}}
    <style>
        html.bp-preloading, html.bp-preloading body { overflow: hidden; }

        #bp-preloader {
            position: fixed;
            inset: 0;
            z-index: 99999;
            background: #F7F3E7;
            color: #0A1228;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: "Geist Mono", "JetBrains Mono", ui-monospace, SFMono-Regular, Menlo, Monaco, monospace;
            transition: opacity .45s ease, visibility .45s ease;
        }
        #bp-preloader.is-hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }
        #bp-preloader::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(10,18,40,.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(10,18,40,.04) 1px, transparent 1px);
            background-size: 32px 32px;
            pointer-events: none;
        }

        /* drafting-frame corner ticks */
        .bp-pre-corner {
            position: absolute;
            width: 28px;
            height: 28px;
            border: 0 solid #0A1228;
        }
        .bp-pre-corner--tl { top: 24px;    left: 24px;    border-top-width: 2px; border-left-width: 2px; }
        .bp-pre-corner--tr { top: 24px;    right: 24px;   border-top-width: 2px; border-right-width: 2px; }
        .bp-pre-corner--bl { bottom: 24px; left: 24px;    border-bottom-width: 2px; border-left-width: 2px; }
        .bp-pre-corner--br { bottom: 24px; right: 24px;   border-bottom-width: 2px; border-right-width: 2px; }

        .bp-pre-inner {
            position: relative;
            text-align: center;
            padding: 0 24px;
            max-width: 520px;
            width: 100%;
        }

        .bp-pre-spec {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .24em;
            text-transform: uppercase;
            color: rgba(10,18,40,.55);
            margin-bottom: 28px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }
        .bp-pre-spec i {
            display: inline-block;
            width: 6px; height: 6px;
            background: #F59E0B;
            animation: bpPulse 1.1s ease-in-out infinite;
        }

        .bp-pre-mark {
            font-family: "Plus Jakarta Sans", ui-sans-serif, system-ui, sans-serif;
            font-weight: 800;
            font-size: clamp(40px, 7vw, 64px);
            letter-spacing: -.02em;
            line-height: 1;
            color: #0A1228;
            display: flex;
            justify-content: center;
            align-items: baseline;
            gap: .14em;
        }
        .bp-pre-mark b { font-weight: 800; }
        .bp-pre-mark .dot,
        .bp-pre-mark .end { color: #F59E0B; font-weight: 800; }
        .bp-pre-mark .end { margin-left: .04em; }

        .bp-pre-sub {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .28em;
            text-transform: uppercase;
            color: #0A1228;
            margin-top: 12px;
            margin-bottom: 40px;
        }

        .bp-pre-bar {
            position: relative;
            height: 10px;
            width: 100%;
            max-width: 360px;
            margin: 0 auto;
            border: 1.5px solid #0A1228;
            background: #FFFFFF;
            overflow: hidden;
        }
        .bp-pre-bar::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image: repeating-linear-gradient(
                -45deg,
                transparent 0 6px,
                rgba(10,18,40,.07) 6px 7px
            );
        }
        .bp-pre-bar-fill {
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 0%;
            background: #0A1228;
            transition: width .25s ease-out;
        }
        .bp-pre-bar-fill::after {
            content: "";
            position: absolute;
            top: 0; bottom: 0; right: 0;
            width: 2px;
            background: #F59E0B;
        }

        .bp-pre-status {
            margin-top: 18px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .22em;
            text-transform: uppercase;
            color: rgba(10,18,40,.7);
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            font-variant-numeric: tabular-nums;
        }
        .bp-pre-dots { display: inline-flex; gap: 3px; }
        .bp-pre-dots i {
            width: 3px; height: 3px; background: #0A1228;
            display: inline-block;
            opacity: .25;
            animation: bpDots 1.2s ease-in-out infinite;
        }
        .bp-pre-dots i:nth-child(2) { animation-delay: .15s; }
        .bp-pre-dots i:nth-child(3) { animation-delay: .30s; }

        .bp-pre-pct {
            color: #0A1228;
            font-size: 11px;
            padding-left: 10px;
            border-left: 1px solid rgba(10,18,40,.25);
        }
        .bp-pre-pct .sym { color: #F59E0B; margin-left: 2px; }

        .bp-pre-foot {
            position: absolute;
            left: 24px; right: 24px; bottom: 24px;
            display: flex;
            justify-content: space-between;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: .24em;
            text-transform: uppercase;
            color: rgba(10,18,40,.45);
            pointer-events: none;
        }

        @keyframes bpPulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%      { opacity: .30; transform: scale(.80); }
        }
        @keyframes bpDots {
            0%, 100% { opacity: .20; transform: translateY(0); }
            50%      { opacity: 1;   transform: translateY(-1px); }
        }

        @media (prefers-reduced-motion: reduce) {
            .bp-pre-spec i, .bp-pre-dots i { animation: none; }
            #bp-preloader { transition: opacity .2s linear, visibility .2s linear; }
            .bp-pre-bar-fill { transition: none; }
        }

        @media (max-width: 480px) {
            .bp-pre-corner { width: 20px; height: 20px; }
            .bp-pre-corner--tl, .bp-pre-corner--tr { top: 16px; }
            .bp-pre-corner--bl, .bp-pre-corner--br { bottom: 16px; }
            .bp-pre-corner--tl, .bp-pre-corner--bl { left: 16px; }
            .bp-pre-corner--tr, .bp-pre-corner--br { right: 16px; }
            .bp-pre-foot { left: 16px; right: 16px; bottom: 12px; font-size: 8px; }
        }
    </style>
    <noscript><style>#bp-preloader{display:none!important;}html.bp-preloading,html.bp-preloading body{overflow:auto!important;}</style></noscript>
    <script>document.documentElement.classList.add('bp-preloading');</script>
    @endif
</head>
<body class="font-sans text-body bg-ivory antialiased min-h-screen flex flex-col">

    {{-- Google Tag Manager (noscript) --}}
    @if(settings('integrations.gtm_id', ''))
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ urlencode(settings('integrations.gtm_id')) }}"
        height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    @endif

    @if($showPreloader)
    {{-- ─── Industrial Blueprint · Preloader (copy from settings) ─── --}}
    <div id="bp-preloader" role="status" aria-live="polite" aria-label="{{ e(settings_trans('preloader.aria_label', 'Loading')) }}">
        <span class="bp-pre-corner bp-pre-corner--tl" aria-hidden="true"></span>
        <span class="bp-pre-corner bp-pre-corner--tr" aria-hidden="true"></span>
        <span class="bp-pre-corner bp-pre-corner--bl" aria-hidden="true"></span>
        <span class="bp-pre-corner bp-pre-corner--br" aria-hidden="true"></span>

        <div class="bp-pre-inner">
            <div class="bp-pre-spec"><i></i><span>{{ e(settings_trans('preloader.spec_line', 'SYS · INIT / EU')) }}</span></div>

            <div class="bp-pre-mark">{{ e(settings_trans('preloader.headline', 'Oe·Parts.')) }}</div>

            <div class="bp-pre-sub">{{ e(settings_trans('preloader.subline', 'Genuine Parts Index')) }}</div>

            <div class="bp-pre-bar" aria-hidden="true">
                <span class="bp-pre-bar-fill" id="bp-pre-fill"></span>
            </div>

            <div class="bp-pre-status">
                <span>{{ e(settings_trans('preloader.status_line', 'Calibrating Index')) }}</span>
                <span class="bp-pre-dots" aria-hidden="true"><i></i><i></i><i></i></span>
                <span class="bp-pre-pct"><span id="bp-pre-pct">00</span><span class="sym">%</span></span>
            </div>
        </div>

        <div class="bp-pre-foot">
            <span>{{ e(settings_trans('preloader.foot_left', 'OeParts · EU')) }}</span>
            <span>{{ e(settings_trans('preloader.foot_right', 'LIVE CATALOGUE')) }}</span>
        </div>
    </div>
    <script>
        (function () {
            var el    = document.getElementById('bp-preloader');
            var pct   = document.getElementById('bp-pre-pct');
            var fill  = document.getElementById('bp-pre-fill');
            var root  = document.documentElement;
            if (!el) return;

            var start       = Date.now();
            var minDuration = {{ (int) $plMin }};
            var maxDuration = {{ (int) $plMax }};
            var progress    = 0;
            var ready       = false;
            var finished    = false;

            // Design-review mode: add ?preview_loader=1 to freeze the splash.
            if (/[?&]preview_loader=1\b/.test(window.location.search)) {
                return;
            }

            var timer = setInterval(function () {
                if (!ready) {
                    // accelerate to ~88% while assets are still loading
                    var inc = Math.max(1, (90 - progress) * 0.08);
                    progress = Math.min(progress + inc, 88);
                } else {
                    progress = Math.min(progress + 6, 100);
                }
                var n = Math.round(progress);
                if (pct)  pct.textContent  = (n < 10 ? '0' : '') + n;
                if (fill) fill.style.width = n + '%';
                if (ready && progress >= 100) {
                    clearInterval(timer);
                    finish();
                }
            }, 70);

            function markReady() { ready = true; }

            function finish() {
                if (finished) return;
                finished = true;
                var elapsed = Date.now() - start;
                var delay   = Math.max(0, minDuration - elapsed);
                setTimeout(function () {
                    el.classList.add('is-hidden');
                    root.classList.remove('bp-preloading');
                    setTimeout(function () {
                        if (el && el.parentNode) el.parentNode.removeChild(el);
                    }, 500);
                }, delay);
            }

            // Ready on DOM-parsed (not window `load`): `load` waits for every
            // image/iframe/third-party script to finish, which ties reveal
            // time to subresources that have nothing to do with the page
            // being visually/functionally ready — a real Core Web Vitals
            // regression the moment this preloader is enabled. DOMContentLoaded
            // fires once the DOM + deferred scripts are parsed, which is the
            // actual "ready to show" signal for a server-rendered Blade page.
            if (document.readyState !== 'loading') {
                markReady();
            } else {
                document.addEventListener('DOMContentLoaded', markReady, { once: true });
            }

            // Hard safety — if DOMContentLoaded never fires (slow asset, error), finish anyway.
            setTimeout(function () {
                markReady();
                if (!finished) {
                    clearInterval(timer);
                    progress = 100;
                    if (pct)  pct.textContent = '100';
                    if (fill) fill.style.width = '100%';
                    finish();
                }
            }, maxDuration);

            // Restore instantly if page is shown from bfcache (back/forward nav).
            window.addEventListener('pageshow', function (e) {
                if (e.persisted) {
                    clearInterval(timer);
                    if (el) el.classList.add('is-hidden');
                    root.classList.remove('bp-preloading');
                }
            });
        })();
    </script>
    @endif

    {{-- Skip navigation — keyboard / screen-reader accessibility --}}
    <a href="#main-content"
       class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus:px-4 focus:py-2 focus:bg-ink focus:text-ivory focus:ring-2 focus:ring-amber focus:ring-offset-0 focus:font-mono focus:text-xs focus:font-bold focus:uppercase focus:tracking-widest">
        {{ __('Skip to main content') }}
    </a>

    {{-- Announcement bar --}}
    @if(settings('announcement.enabled', false))
    <div
        x-data="{ visible: !localStorage.getItem('announcement_dismissed') }"
        x-show="visible"
        x-cloak
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        style="background-color: {{ settings('announcement.color', '#F59E0B') }}; color: {{ settings('announcement.text_color', '#1E293B') }}"
        class="text-sm text-center py-2 px-4 relative"
    >
        <span>{{ settings_trans('announcement.text') }}</span>
        @if(settings('announcement.dismissable', true))
        <button
            @click="visible = false; localStorage.setItem('announcement_dismissed', '1')"
            class="absolute right-4 top-1/2 -translate-y-1/2 opacity-70 hover:opacity-100"
            aria-label="{{ ui_copy('navbar_dismiss_announcement', 'navbar.dismiss_announcement') }}"
        >
            <x-heroicon-o-x-mark class="w-4 h-4" />
        </button>
        @endif
    </div>
    @endif

    {{-- Navbar --}}
    @include('components.navbar')

    {{-- Main content --}}
    <main id="main-content" class="flex-1 bg-ivory">
        @yield('content')

        {{-- Global modals live inside main — children are position:fixed so DOM placement has no visual effect --}}
        @include('components.modals.auth-modal')
    </main>

    {{-- Footer --}}
    @include('components.footer')

    {{-- Cookie consent banner (GDPR compliance) --}}
    <x-cookie-consent :enabled="true" />

    {{-- Footer scripts from settings (analytics etc.) --}}
    @if(settings('general.footer_scripts', ''))
    <script nonce="{{ csp_nonce() }}">{!! settings('general.footer_scripts', '') !!}</script>
    @endif

    {{-- Per-page extra scripts --}}
    @stack('scripts')

    {{-- Toast notifications --}}
    <x-ui.toast />

    {{-- Scroll to top (all pages) --}}
    <x-ui.scroll-to-top />

</body>
</html>
