<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Admin') — {{ settings('general.site_name', 'OEMHub') }}</title>

    @vite(['resources/css/admin.css', 'resources/js/admin.js'])

    @stack('styles')
</head>
<body class="h-full font-sans text-body bg-slate-100 antialiased">

    {{-- Skip navigation --}}
    <a href="#admin-main"
       class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus:px-4 focus:py-2 focus:bg-navy focus:text-white focus:rounded-lg focus:font-semibold focus:text-sm focus:shadow-lg">
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
        class="fixed inset-0 z-30 bg-black/50 lg:hidden"
    ></div>

    {{-- Sidebar --}}
    <aside
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        class="fixed inset-y-0 left-0 z-40 w-64 bg-navy flex flex-col transition-transform duration-200 ease-in-out lg:relative lg:translate-x-0 lg:z-auto"
    >
        {{-- Logo --}}
        <div class="flex items-center h-16 px-6 border-b border-white/10 shrink-0">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2">
                <span class="font-display font-bold text-xl text-white tracking-tight">OEMHub</span>
                <span class="text-xs text-amber font-medium uppercase tracking-widest">Admin</span>
            </a>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 overflow-y-auto py-4 px-3 sidebar-nav">

            {{-- Dashboard --}}
            <a href="{{ route('admin.dashboard') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-white/80 hover:text-white hover:bg-white/10 transition-colors {{ request()->routeIs('admin.dashboard') ? 'bg-white/10 text-white' : '' }}">
                <x-heroicon-o-squares-2x2 class="w-5 h-5 shrink-0" />
                Dashboard
            </a>

            <div class="mt-4 mb-2 px-3 text-xs font-semibold text-white/40 uppercase tracking-wider">Catalog</div>

            <a href="{{ route('admin.catalog.products.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-white/80 hover:text-white hover:bg-white/10 transition-colors {{ request()->routeIs('admin.catalog.products*') ? 'bg-white/10 text-white' : '' }}">
                <x-heroicon-o-tag class="w-5 h-5 shrink-0" />
                Products
            </a>
            <a href="{{ route('admin.catalog.manufacturers.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-white/80 hover:text-white hover:bg-white/10 transition-colors {{ request()->routeIs('admin.catalog.manufacturers*') ? 'bg-white/10 text-white' : '' }}">
                <x-heroicon-o-building-office class="w-5 h-5 shrink-0" />
                Manufacturers
            </a>
            <a href="{{ route('admin.catalog.car-models.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-white/80 hover:text-white hover:bg-white/10 transition-colors {{ request()->routeIs('admin.catalog.car-models*') ? 'bg-white/10 text-white' : '' }}">
                <x-heroicon-o-truck class="w-5 h-5 shrink-0" />
                Car Models
            </a>
            <div class="mt-4 mb-2 px-3 text-xs font-semibold text-white/40 uppercase tracking-wider">Orders</div>

            <a href="{{ route('admin.orders.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-white/80 hover:text-white hover:bg-white/10 transition-colors {{ request()->routeIs('admin.orders*') ? 'bg-white/10 text-white' : '' }}">
                <x-heroicon-o-shopping-bag class="w-5 h-5 shrink-0" />
                Orders
            </a>
            <a href="{{ route('admin.refunds.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-white/80 hover:text-white hover:bg-white/10 transition-colors {{ request()->routeIs('admin.refunds*') ? 'bg-white/10 text-white' : '' }}">
                <x-heroicon-o-arrow-uturn-left class="w-5 h-5 shrink-0" />
                Refunds
            </a>
            <a href="{{ route('admin.coupons.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-white/80 hover:text-white hover:bg-white/10 transition-colors {{ request()->routeIs('admin.coupons*') ? 'bg-white/10 text-white' : '' }}">
                <x-heroicon-o-ticket class="w-5 h-5 shrink-0" />
                Coupons
            </a>

            <div class="mt-4 mb-2 px-3 text-xs font-semibold text-white/40 uppercase tracking-wider">Customers</div>

            <a href="{{ route('admin.customers.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-white/80 hover:text-white hover:bg-white/10 transition-colors {{ request()->routeIs('admin.customers*') ? 'bg-white/10 text-white' : '' }}">
                <x-heroicon-o-users class="w-5 h-5 shrink-0" />
                Customers
            </a>
            <a href="{{ route('admin.cms.inquiries.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-white/80 hover:text-white hover:bg-white/10 transition-colors {{ request()->routeIs('admin.cms.inquiries*') ? 'bg-white/10 text-white' : '' }}">
                <x-heroicon-o-question-mark-circle class="w-5 h-5 shrink-0" />
                Inquiries
            </a>
            <a href="{{ route('admin.cms.contact.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-white/80 hover:text-white hover:bg-white/10 transition-colors {{ request()->routeIs('admin.cms.contact*') ? 'bg-white/10 text-white' : '' }}">
                <x-heroicon-o-envelope class="w-5 h-5 shrink-0" />
                Messages
            </a>

            <div class="mt-4 mb-2 px-3 text-xs font-semibold text-white/40 uppercase tracking-wider">Content</div>

            <a href="{{ route('admin.cms.sections.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-white/80 hover:text-white hover:bg-white/10 transition-colors {{ request()->routeIs('admin.cms.sections*') ? 'bg-white/10 text-white' : '' }}">
                <x-heroicon-o-squares-plus class="w-5 h-5 shrink-0" />
                Sections
            </a>
            <a href="{{ route('admin.cms.blog.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-white/80 hover:text-white hover:bg-white/10 transition-colors {{ request()->routeIs('admin.cms.blog*') ? 'bg-white/10 text-white' : '' }}">
                <x-heroicon-o-newspaper class="w-5 h-5 shrink-0" />
                Blog
            </a>
            <a href="{{ route('admin.cms.pages.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-white/80 hover:text-white hover:bg-white/10 transition-colors {{ request()->routeIs('admin.cms.pages*') ? 'bg-white/10 text-white' : '' }}">
                <x-heroicon-o-document-text class="w-5 h-5 shrink-0" />
                Pages
            </a>
            <a href="{{ route('admin.cms.menus.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-white/80 hover:text-white hover:bg-white/10 transition-colors {{ request()->routeIs('admin.cms.menus*') ? 'bg-white/10 text-white' : '' }}">
                <x-heroicon-o-bars-3 class="w-5 h-5 shrink-0" />
                Menus
            </a>
            <a href="{{ route('admin.cms.media.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-white/80 hover:text-white hover:bg-white/10 transition-colors {{ request()->routeIs('admin.cms.media*') ? 'bg-white/10 text-white' : '' }}">
                <x-heroicon-o-photo class="w-5 h-5 shrink-0" />
                Media
            </a>

            <div class="mt-4 mb-2 px-3 text-xs font-semibold text-white/40 uppercase tracking-wider">Reports</div>

            <a href="{{ route('admin.reports.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-white/80 hover:text-white hover:bg-white/10 transition-colors {{ request()->routeIs('admin.reports*') ? 'bg-white/10 text-white' : '' }}">
                <x-heroicon-o-chart-bar class="w-5 h-5 shrink-0" />
                Reports
            </a>

            <div class="mt-4 mb-2 px-3 text-xs font-semibold text-white/40 uppercase tracking-wider">System</div>

            <a href="{{ route('admin.settings.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-white/80 hover:text-white hover:bg-white/10 transition-colors {{ request()->routeIs('admin.settings*') ? 'bg-white/10 text-white' : '' }}">
                <x-heroicon-o-cog-6-tooth class="w-5 h-5 shrink-0" />
                Settings
            </a>
            <a href="{{ route('admin.translations.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-white/80 hover:text-white hover:bg-white/10 transition-colors {{ request()->routeIs('admin.translations*') ? 'bg-white/10 text-white' : '' }}">
                <x-heroicon-o-language class="w-5 h-5 shrink-0" />
                Translations
            </a>
            <a href="{{ route('admin.health.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-white/80 hover:text-white hover:bg-white/10 transition-colors {{ request()->routeIs('admin.health*') ? 'bg-white/10 text-white' : '' }}">
                <x-heroicon-o-heart class="w-5 h-5 shrink-0" />
                Health
            </a>
            <a href="{{ route('admin.logs.activity') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-white/80 hover:text-white hover:bg-white/10 transition-colors {{ request()->routeIs('admin.logs*') ? 'bg-white/10 text-white' : '' }}">
                <x-heroicon-o-clipboard-document-list class="w-5 h-5 shrink-0" />
                Logs
            </a>
        </nav>

        {{-- Admin user footer --}}
        <div class="shrink-0 border-t border-white/10 px-3 py-3">
            @if($adminUser = auth('admin')->user())
            <div class="flex items-center gap-3 px-3 py-2">
                <div class="w-8 h-8 rounded-full bg-amber flex items-center justify-center text-navy font-bold text-sm shrink-0">
                    {{ strtoupper(substr($adminUser->name, 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-white truncate">{{ $adminUser->name }}</p>
                    <p class="text-xs text-white/50 truncate">{{ $adminUser->email }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.logout') }}" class="mt-1">
                @csrf
                <button type="submit"
                        class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-white/60 hover:text-white hover:bg-white/10 transition-colors">
                    <x-heroicon-o-arrow-right-on-rectangle class="w-4 h-4 shrink-0" />
                    Sign out
                </button>
            </form>
            @endif
        </div>
    </aside>

    {{-- Main panel --}}
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

        {{-- Top bar --}}
        <header class="h-16 bg-white border-b border-slate-200 flex items-center gap-4 px-4 lg:px-6 shrink-0">
            {{-- Mobile menu toggle --}}
            <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden text-muted hover:text-body p-1">
                <x-heroicon-o-bars-3 class="w-6 h-6" />
            </button>

            {{-- Page title --}}
            <div class="flex-1 min-w-0">
                <h1 class="text-lg font-semibold text-navy truncate">@yield('page_title', 'Dashboard')</h1>
                @hasSection('breadcrumbs')
                <nav class="flex items-center gap-1 text-xs text-muted mt-0.5">
                    @yield('breadcrumbs')
                </nav>
                @endif
            </div>

            {{-- Top-bar actions slot --}}
            <div class="flex items-center gap-2 shrink-0">
                @yield('header_actions')
            </div>
        </header>

        {{-- Flash messages --}}
        @if(session('success'))
        <div class="mx-4 lg:mx-6 mt-4 flex items-center gap-3 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-800 text-sm">
            <x-heroicon-o-check-circle class="w-5 h-5 text-green-500 shrink-0" />
            {{ session('success') }}
        </div>
        @endif
        @if(session('error'))
        <div class="mx-4 lg:mx-6 mt-4 flex items-center gap-3 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-red-800 text-sm">
            <x-heroicon-o-x-circle class="w-5 h-5 text-red-500 shrink-0" />
            {{ session('error') }}
        </div>
        @endif

        {{-- Page content --}}
        <main id="admin-main" class="flex-1 overflow-y-auto p-4 lg:p-6">
            @yield('content')
        </main>
    </div>
</div>

@stack('scripts')
</body>
</html>
