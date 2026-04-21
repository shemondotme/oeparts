<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Title --}}
    <title>@yield('title', settings('general.site_name', 'OEMHub'))</title>

    {{-- Primary meta --}}
    <meta name="description" content="@yield('meta_description', '')">
    @hasSection('meta_robots')
        @yield('meta_robots')
    @else
        <meta name="robots" content="{{ settings('seo.default_robots', 'index,follow') }}">
    @endif

    {{-- Canonical --}}
    @yield('canonical')

    {{-- Open Graph --}}
    <meta property="og:site_name" content="{{ settings('general.site_name', 'OEMHub') }}">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="@yield('og_title', settings('general.site_name', 'OEMHub'))">
    <meta property="og:description" content="@yield('og_description', settings('seo.homepage_description') ?: 'Find genuine OEM auto parts fast. Search by OEM number, compare prices, ship across the EU.')">
    <meta property="og:url" content="{{ url()->current() }}">
    @hasSection('og_image')
        @yield('og_image')
    @else
        <meta property="og:image" content="{{ settings('seo.og_image', url('/og-default.png')) }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="{{ settings('general.site_name', 'OEMHub') }} — Genuine OEM Auto Parts">
    @endif
    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('og_title', settings('general.site_name', 'OEMHub'))">
    <meta name="twitter:description" content="@yield('og_description', settings('seo.homepage_description') ?: 'Find genuine OEM auto parts fast. Search by OEM number, compare prices, ship across the EU.')">
    <meta name="twitter:image" content="{{ settings('seo.og_image', url('/og-default.png')) }}">

    {{-- hreflang links (server-side, all 5 locales + x-default) --}}
    @yield('hreflang')

    {{-- JSON-LD structured data --}}
    @yield('json_ld')

    {{-- Favicon --}}
    <link rel="icon" type="image/x-icon" href="/favicon.ico">

    {{-- Vite: frontend CSS + JS --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Custom appearance from settings (CSS variable overrides) --}}
    @if(settings('appearance.custom_css_enabled', false) && settings('appearance.custom_css', ''))
    <style>
        :root {
            --color-primary: {{ settings('appearance.primary_color', '#0B3A68') }};
            --color-accent:  {{ settings('appearance.accent_color', '#F59E0B') }};
        }
        {!! settings('appearance.custom_css', '') !!}
    </style>
    @endif

    {{-- Header scripts (GTM etc.) from settings --}}
    @if(settings('general.header_scripts', ''))
    {!! settings('general.header_scripts', '') !!}
    @endif

    {{-- Per-page extra styles --}}
    @stack('styles')
</head>
<body class="font-sans text-body bg-bg-page antialiased">

    {{-- Skip navigation — keyboard / screen-reader accessibility --}}
    <a href="#main-content"
       class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus:px-4 focus:py-2 focus:bg-navy focus:text-white focus:rounded-lg focus:font-semibold focus:text-sm focus:shadow-lg">
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
        <span>{{ trans_field(settings('announcement.text', null)) }}</span>
        @if(settings('announcement.dismissable', true))
        <button
            @click="visible = false; localStorage.setItem('announcement_dismissed', '1')"
            class="absolute right-4 top-1/2 -translate-y-1/2 opacity-70 hover:opacity-100"
            aria-label="Dismiss"
        >
            <x-heroicon-o-x-mark class="w-4 h-4" />
        </button>
        @endif
    </div>
    @endif

    {{-- Navbar --}}
    @include('components.navbar')

    {{-- Main content --}}
    <main id="main-content">
        @yield('content')
    </main>

    {{-- Footer --}}
    @include('components.footer')

    {{-- Auth modal (login / register) — loaded globally, toggled via Alpine --}}
    @include('components.modals.auth-modal')

    {{-- OTP modal — triggered from auth modal or checkout --}}
    @include('components.modals.otp-modal')

    {{-- Cookie consent banner (GDPR compliance) --}}
    <x-cookie-consent :enabled="true" />

    {{-- Footer scripts from settings (analytics etc.) --}}
    @if(settings('general.footer_scripts', ''))
    {!! settings('general.footer_scripts', '') !!}
    @endif

    {{-- Per-page extra scripts --}}
    @stack('scripts')

    {{-- Toast notifications --}}
    <x-ui.toast />

</body>
</html>
