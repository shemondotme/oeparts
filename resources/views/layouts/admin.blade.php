<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Admin') — {{ settings('general.site_name', 'OEMHub') }}</title>

    @vite(['resources/css/admin.css', 'resources/js/admin.js'])

    @stack('styles')

    <style>
        /* Admin-specific Blueprint overrides */
        body {
            background-color: #F7F3E7; /* Ivory */
            color: #0A1228; /* Ink */
        }
        .admin-sidebar {
            background-color: #0A1228; /* Ink */
            border-right: 1px solid #1D2A44; /* Rule Dark */
        }
        .admin-nav-item {
            border-left: 3px solid transparent;
        }
        .admin-nav-item.active {
            background-color: rgba(255, 255, 255, 0.05);
            border-left-color: #F59E0B; /* Amber */
            color: #F59E0B !important;
        }
        .admin-nav-item:hover:not(.active) {
            background-color: rgba(255, 255, 255, 0.03);
            color: #FFFFFF !important;
        }
        .admin-section-label {
            font-family: 'JetBrains Mono', monospace;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            color: #64748B; /* Muted */
        }
        .admin-topbar {
            background-color: #FFFFFF; /* Paper */
            border-bottom: 1px solid #D8CFB6; /* Rule */
        }
        .admin-content-area {
            background-image: linear-gradient(to right, rgba(10,18,40,0.03) 1px, transparent 1px),
                              linear-gradient(to bottom, rgba(10,18,40,0.03) 1px, transparent 1px);
            background-size: 24px 24px;
        }
    </style>
</head>
<body class="h-full font-sans antialiased overflow-hidden">

    {{-- Skip navigation --}}
    <a href="#admin-main"
       class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-[9999] focus:px-4 focus:py-2 focus:bg-ink focus:text-ivory focus:font-mono focus:text-sm focus:border focus:border-amber">
        Skip to main content
    </a>

<div x-data="{ sidebarOpen: false }" class="flex h-full">

    {{-- Mobile sidebar backdrop --}}
    <div
        x-show="sidebarOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="sidebarOpen = false"
        class="fixed inset-0 z-40 bg-black/60 lg:hidden"
    ></div>

    {{-- ══════════════════════════════════════════════════════════════════════
         SIDEBAR: Industrial Blueprint Navigation
         Dense, ink-colored, monospace section headers.
         ═══════════════════════════════════════════════════════════════════ --}}
    <aside
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        class="admin-sidebar fixed inset-y-0 left-0 z-50 w-64 flex flex-col transition-transform duration-200 ease-in-out lg:relative lg:translate-x-0 lg:z-auto shrink-0"
    >
        {{-- Logo Block --}}
        <div class="flex items-center h-16 px-6 border-b border-white/10 shrink-0">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 group">
                <div class="relative w-8 h-8 shrink-0 transition-transform duration-300 group-hover:rotate-[30deg]">
                    <svg viewBox="0 0 60 60" class="w-full h-full" aria-hidden="true">
                        <path d="M30 3 L53 16 L53 44 L30 57 L7 44 L7 16 Z" class="fill-amber group-hover:fill-white transition-colors duration-200"/>
                        <path d="M30 13 L44.5 21.5 L44.5 38.5 L30 47 L15.5 38.5 L15.5 21.5 Z" class="fill-ink group-hover:fill-ink transition-colors duration-200"/>
                        <path d="M30 18 L30 42 M18 30 L42 30" class="stroke-white group-hover:stroke-amber transition-colors duration-200" stroke-width="2.5" stroke-linecap="square"/>
                    </svg>
                </div>
                <div class="leading-none">
                    <p class="font-display font-bold text-lg text-white tracking-tight">
                        OEM<span class="text-amber">·</span>HUB
                    </p>
                    <p class="mt-0.5 font-mono text-[9px] tracking-[0.2em] uppercase text-white/50">
                        ADMIN CONSOLE
                    </p>
                </div>
            </a>
        </div>

        {{-- Navigation Scroll Area --}}
        <nav class="flex-1 overflow-y-auto py-6 px-3 space-y-6 custom-scrollbar">

            {{-- Dashboard --}}
            <div>
                <a href="{{ route('admin.dashboard') }}"
                   class="admin-nav-item flex items-center gap-3 px-3 py-2 text-sm font-medium text-white/70 transition-colors rounded-none {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <x-heroicon-o-squares-2x2 class="w-5 h-5 shrink-0 opacity-80" />
                    <span>Dashboard</span>
                </a>
            </div>

            {{-- Catalog Section --}}
            <div>
                <div class="admin-section-label px-3 mb-2">§ Catalog</div>
                <div class="space-y-0.5">
                    <a href="{{ route('admin.catalog.products.index') }}"
                       class="admin-nav-item flex items-center gap-3 px-3 py-2 text-sm font-medium text-white/70 transition-colors rounded-none {{ request()->routeIs('admin.catalog.products*') ? 'active' : '' }}">
                        <x-heroicon-o-tag class="w-4 h-4 shrink-0 opacity-80" />
                        <span>Products</span>
                    </a>
                    <a href="{{ route('admin.catalog.manufacturers.index') }}"
                       class="admin-nav-item flex items-center gap-3 px-3 py-2 text-sm font-medium text-white/70 transition-colors rounded-none {{ request()->routeIs('admin.catalog.manufacturers*') ? 'active' : '' }}">
                        <x-heroicon-o-building-office class="w-4 h-4 shrink-0 opacity-80" />
                        <span>Manufacturers</span>
                    </a>
                    <a href="{{ route('admin.catalog.car-models.index') }}"
                       class="admin-nav-item flex items-center gap-3 px-3 py-2 text-sm font-medium text-white/70 transition-colors rounded-none {{ request()->routeIs('admin.catalog.car-models*') ? 'active' : '' }}">
                        <x-heroicon-o-truck class="w-4 h-4 shrink-0 opacity-80" />
                        <span>Car Models</span>
                    </a>
                </div>
            </div>

            {{-- Orders Section --}}
            <div>
                <div class="admin-section-label px-3 mb-2">§ Orders</div>
                <div class="space-y-0.5">
                    <a href="{{ route('admin.orders.index') }}"
                       class="admin-nav-item flex items-center gap-3 px-3 py-2 text-sm font-medium text-white/70 transition-colors rounded-none {{ request()->routeIs('admin.orders*') ? 'active' : '' }}">
                        <x-heroicon-o-shopping-bag class="w-4 h-4 shrink-0 opacity-80" />
                        <span>Orders</span>
                    </a>
                    <a href="{{ route('admin.refunds.index') }}"
                       class="admin-nav-item flex items-center gap-3 px-3 py-2 text-sm font-medium text-white/70 transition-colors rounded-none {{ request()->routeIs('admin.refunds*') ? 'active' : '' }}">
                        <x-heroicon-o-arrow-uturn-left class="w-4 h-4 shrink-0 opacity-80" />
                        <span>Refunds</span>
                    </a>
                    <a href="{{ route('admin.coupons.index') }}"
                       class="admin-nav-item flex items-center gap-3 px-3 py-2 text-sm font-medium text-white/70 transition-colors rounded-none {{ request()->routeIs('admin.coupons*') ? 'active' : '' }}">
                        <x-heroicon-o-ticket class="w-4 h-4 shrink-0 opacity-80" />
                        <span>Coupons</span>
                    </a>
                </div>
            </div>

            {{-- Customers Section --}}
            <div>
                <div class="admin-section-label px-3 mb-2">§ Customers</div>
                <div class="space-y-0.5">
                    <a href="{{ route('admin.customers.index') }}"
                       class="admin-nav-item flex items-center gap-3 px-3 py-2 text-sm font-medium text-white/70 transition-colors rounded-none {{ request()->routeIs('admin.customers*') ? 'active' : '' }}">
                        <x-heroicon-o-users class="w-4 h-4 shrink-0 opacity-80" />
                        <span>Customers</span>
                    </a>
                    <a href="{{ route('admin.cms.inquiries.index') }}"
                       class="admin-nav-item flex items-center gap-3 px-3 py-2 text-sm font-medium text-white/70 transition-colors rounded-none {{ request()->routeIs('admin.cms.inquiries*') ? 'active' : '' }}">
                        <x-heroicon-o-question-mark-circle class="w-4 h-4 shrink-0 opacity-80" />
                        <span>Inquiries</span>
                    </a>
                    <a href="{{ route('admin.cms.contact.index') }}"
                       class="admin-nav-item flex items-center gap-3 px-3 py-2 text-sm font-medium text-white/70 transition-colors rounded-none {{ request()->routeIs('admin.cms.contact*') ? 'active' : '' }}">
                        <x-heroicon-o-envelope class="w-4 h-4 shrink-0 opacity-80" />
                        <span>Messages</span>
                    </a>
                </div>
            </div>

            {{-- Content Section --}}
            <div>
                <div class="admin-section-label px-3 mb-2">§ Content</div>
                <div class="space-y-0.5">
                    <a href="{{ route('admin.cms.sections.index') }}"
                       class="admin-nav-item flex items-center gap-3 px-3 py-2 text-sm font-medium text-white/70 transition-colors rounded-none {{ request()->routeIs('admin.cms.sections*') ? 'active' : '' }}">
                        <x-heroicon-o-squares-plus class="w-4 h-4 shrink-0 opacity-80" />
                        <span>Sections</span>
                    </a>
                    <a href="{{ route('admin.cms.blog.index') }}"
                       class="admin-nav-item flex items-center gap-3 px-3 py-2 text-sm font-medium text-white/70 transition-colors rounded-none {{ request()->routeIs('admin.cms.blog*') ? 'active' : '' }}">
                        <x-heroicon-o-newspaper class="w-4 h-4 shrink-0 opacity-80" />
                        <span>Blog</span>
                    </a>
                    <a href="{{ route('admin.cms.pages.index') }}"
                       class="admin-nav-item flex items-center gap-3 px-3 py-2 text-sm font-medium text-white/70 transition-colors rounded-none {{ request()->routeIs('admin.cms.pages*') ? 'active' : '' }}">
                        <x-heroicon-o-document-text class="w-4 h-4 shrink-0 opacity-80" />
                        <span>Pages</span>
                    </a>
                    <a href="{{ route('admin.cms.menus.index') }}"
                       class="admin-nav-item flex items-center gap-3 px-3 py-2 text-sm font-medium text-white/70 transition-colors rounded-none {{ request()->routeIs('admin.cms.menus*') ? 'active' : '' }}">
                        <x-heroicon-o-bars-3 class="w-4 h-4 shrink-0 opacity-80" />
                        <span>Menus</span>
                    </a>
                    <a href="{{ route('admin.cms.media.index') }}"
                       class="admin-nav-item flex items-center gap-3 px-3 py-2 text-sm font-medium text-white/70 transition-colors rounded-none {{ request()->routeIs('admin.cms.media*') ? 'active' : '' }}">
                        <x-heroicon-o-photo class="w-4 h-4 shrink-0 opacity-80" />
                        <span>Media</span>
                    </a>
                </div>
            </div>

            {{-- System Section --}}
            <div>
                <div class="admin-section-label px-3 mb-2">§ System</div>
                <div class="space-y-0.5">
                    <a href="{{ route('admin.settings.index') }}"
                       class="admin-nav-item flex items-center gap-3 px-3 py-2 text-sm font-medium text-white/70 transition-colors rounded-none {{ request()->routeIs('admin.settings*') ? 'active' : '' }}">
                        <x-heroicon-o-cog-6-tooth class="w-4 h-4 shrink-0 opacity-80" />
                        <span>Settings</span>
                    </a>
                    <a href="{{ route('admin.translations.index') }}"
                       class="admin-nav-item flex items-center gap-3 px-3 py-2 text-sm font-medium text-white/70 transition-colors rounded-none {{ request()->routeIs('admin.translations*') ? 'active' : '' }}">
                        <x-heroicon-o-language class="w-4 h-4 shrink-0 opacity-80" />
                        <span>Translations</span>
                    </a>
                    <a href="{{ route('admin.health.index') }}"
                       class="admin-nav-item flex items-center gap-3 px-3 py-2 text-sm font-medium text-white/70 transition-colors rounded-none {{ request()->routeIs('admin.health*') ? 'active' : '' }}">
                        <x-heroicon-o-heart class="w-4 h-4 shrink-0 opacity-80" />
                        <span>Health</span>
                    </a>
                    <a href="{{ route('admin.logs.activity') }}"
                       class="admin-nav-item flex items-center gap-3 px-3 py-2 text-sm font-medium text-white/70 transition-colors rounded-none {{ request()->routeIs('admin.logs*') ? 'active' : '' }}">
                        <x-heroicon-o-clipboard-document-list class="w-4 h-4 shrink-0 opacity-80" />
                        <span>Logs</span>
                    </a>
                </div>
            </div>
        </nav>

        {{-- Admin User Footer --}}
        <div class="shrink-0 border-t border-white/10 p-4 bg-black/20">
            @if($adminUser = auth('admin')->user())
            <div class="flex items-center gap-3 mb-3">
                <div class="w-8 h-8 rounded-none bg-amber flex items-center justify-center text-ink font-mono font-bold text-sm shrink-0 border border-amber">
                    {{ strtoupper(substr($adminUser->name, 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-white truncate">{{ $adminUser->name }}</p>
                    <p class="text-xs text-white/50 truncate font-mono">{{ $adminUser->email }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit"
                        class="w-full flex items-center justify-center gap-2 px-3 py-2 text-xs font-bold uppercase tracking-wider text-white/60 hover:text-white hover:bg-white/10 border border-transparent hover:border-white/20 transition-all rounded-none">
                    <x-heroicon-o-arrow-right-on-rectangle class="w-4 h-4 shrink-0" />
                    Sign Out
                </button>
            </form>
            @endif
        </div>
    </aside>

    {{-- ══════════════════════════════════════════════════════════════════════
         MAIN PANEL
         ═══════════════════════════════════════════════════════════════════ --}}
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden bg-ivory relative">

        {{-- Grid Texture Background --}}
        <div class="absolute inset-0 admin-content-area pointer-events-none z-0"></div>

        {{-- Top Bar (Doc Header Style) --}}
        <header class="admin-topbar h-16 flex items-center justify-between px-4 lg:px-8 shrink-0 z-10 relative">
            <div class="flex items-center gap-4">
                {{-- Mobile menu toggle --}}
                <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden text-ink hover:text-amber p-1 rounded-none border border-transparent hover:border-rule transition-colors">
                    <x-heroicon-o-bars-3 class="w-6 h-6" />
                </button>

                {{-- Breadcrumbs / Title --}}
                <div class="flex flex-col">
                    <h1 class="text-lg font-display font-bold text-ink tracking-tight leading-none">
                        @yield('page_title', 'Dashboard')
                    </h1>
                    @hasSection('breadcrumbs')
                    <nav class="flex items-center gap-2 text-xs font-mono text-ink-muted mt-1">
                        @yield('breadcrumbs')
                    </nav>
                    @endif
                </div>
            </div>

            {{-- Top-bar actions slot --}}
            <div class="flex items-center gap-3">
                @yield('header_actions')
            </div>
        </header>

        {{-- Flash Messages (Blueprint Style) --}}
        <div class="px-4 lg:px-8 pt-4 z-10 relative space-y-3">
            @if(session('success'))
            <div class="flex items-start gap-3 px-4 py-3 bg-emerald-50 border border-emerald-600 text-emerald-800 text-sm rounded-none shadow-[2px_2px_0_rgba(20,22,29,0.1)]">
                <x-heroicon-s-check-circle class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" />
                <div>
                    <p class="font-bold text-xs uppercase tracking-wide mb-0.5">Success</p>
                    <p>{{ session('success') }}</p>
                </div>
            </div>
            @endif
            @if(session('error'))
            <div class="flex items-start gap-3 px-4 py-3 bg-red-50 border border-red-600 text-red-800 text-sm rounded-none shadow-[2px_2px_0_rgba(20,22,29,0.1)]">
                <x-heroicon-s-exclamation-triangle class="w-5 h-5 text-red-600 shrink-0 mt-0.5" />
                <div>
                    <p class="font-bold text-xs uppercase tracking-wide mb-0.5">Error</p>
                    <p>{{ session('error') }}</p>
                </div>
            </div>
            @endif
            @if(session('warning'))
            <div class="flex items-start gap-3 px-4 py-3 bg-amber-50 border border-amber-600 text-amber-900 text-sm rounded-none shadow-[2px_2px_0_rgba(20,22,29,0.1)]">
                <x-heroicon-s-exclamation-circle class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
                <div>
                    <p class="font-bold text-xs uppercase tracking-wide mb-0.5">Warning</p>
                    <p>{{ session('warning') }}</p>
                </div>
            </div>
            @endif
        </div>

        {{-- Page Content --}}
        <main id="admin-main" class="flex-1 overflow-y-auto p-4 lg:p-8 z-10 relative scroll-smooth">
            @yield('content')
        </main>
    </div>
</div>

@stack('scripts')
</body>
</html>
