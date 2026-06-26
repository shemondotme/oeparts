<div class="fi-topbar-ctn">
    @php
        $isRtl = __('filament-panels::layout.direction') === 'rtl';
        $isSidebarCollapsibleOnDesktop = filament()->isSidebarCollapsibleOnDesktop();
        $isSidebarFullyCollapsibleOnDesktop = filament()->isSidebarFullyCollapsibleOnDesktop();
        $hasTopNavigation = filament()->hasTopNavigation();
        $hasNavigation = filament()->hasNavigation();

        // Derive breadcrumb from the active navigation item
        $crumbGroup = null;
        $crumbPage  = null;
        if ($hasNavigation) {
            foreach (filament()->getNavigation() as $_navGroup) {
                foreach ($_navGroup->getItems() as $_navItem) {
                    if ($_navItem->isActive()) {
                        $_gl       = $_navGroup->getLabel();
                        $crumbGroup = ($_gl && $_gl !== '') ? $_gl : null;
                        $crumbPage  = $_navItem->getLabel();
                        break 2;
                    }
                }
            }
        }
    @endphp

    <nav class="fi-topbar">
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::TOPBAR_START) }}

        {{-- ── Zone A: Left — Logo + Breadcrumb ──────────────────────────────── --}}
        <div class="op-topbar-left">

            {{-- Mobile sidebar drawer toggle (hidden on desktop) --}}
            @if ($hasNavigation)
                <x-filament::icon-button
                    color="gray"
                    :icon="\Filament\Support\Icons\Heroicon::OutlinedBars3"
                    :icon-alias="\Filament\View\PanelsIconAlias::TOPBAR_OPEN_SIDEBAR_BUTTON"
                    icon-size="lg"
                    :label="__('filament-panels::layout.actions.sidebar.expand.label')"
                    x-cloak
                    x-data="{}"
                    x-on:click="$store.sidebar.open()"
                    x-show="! $store.sidebar.isOpen"
                    class="fi-topbar-open-sidebar-btn lg:!hidden"
                />
                <x-filament::icon-button
                    color="gray"
                    :icon="\Filament\Support\Icons\Heroicon::OutlinedXMark"
                    :icon-alias="\Filament\View\PanelsIconAlias::TOPBAR_CLOSE_SIDEBAR_BUTTON"
                    icon-size="lg"
                    :label="__('filament-panels::layout.actions.sidebar.collapse.label')"
                    x-cloak
                    x-data="{}"
                    x-on:click="$store.sidebar.close()"
                    x-show="$store.sidebar.isOpen"
                    class="fi-topbar-close-sidebar-btn lg:!hidden"
                />
            @endif

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::TOPBAR_LOGO_BEFORE) }}

            {{-- Brand logo — links to home --}}
            @if ($homeUrl = filament()->getHomeUrl())
                <a {{ \Filament\Support\generate_href_html($homeUrl) }} class="op-topbar-brand-link">
                    <x-filament-panels::logo />
                </a>
            @else
                <x-filament-panels::logo />
            @endif

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::TOPBAR_LOGO_AFTER) }}

            {{-- Breadcrumb: only on desktop, only when a nav item is active --}}
            @if ($crumbPage)
                <div class="op-topbar-breadcrumb" aria-label="Breadcrumb" role="navigation">
                    <span class="op-topbar-breadcrumb-sep" aria-hidden="true">/</span>
                    @if ($crumbGroup)
                        <span class="op-topbar-breadcrumb-group">{{ $crumbGroup }}</span>
                        <span class="op-topbar-breadcrumb-sep" aria-hidden="true">›</span>
                    @endif
                    <span class="op-topbar-breadcrumb-page">{{ $crumbPage }}</span>
                </div>
            @endif
        </div>

        {{-- ── Zone B: Center — Global Search ─────────────────────────────────── --}}
        <div class="op-topbar-center">
            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::GLOBAL_SEARCH_BEFORE) }}

            <button
                type="button"
                x-data="{}"
                x-on:click="$dispatch('open-spotlight')"
                class="op-topbar-search"
                aria-label="Search everything (⌘K)"
                title="Search everything (⌘K)"
            >
                <svg class="op-topbar-search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
                <span class="op-topbar-search-placeholder">Search everything...</span>
                <kbd class="op-topbar-search-kbd">⌘K</kbd>
            </button>

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::GLOBAL_SEARCH_AFTER) }}
        </div>

        {{-- ── Zone C: Right — Actions ─────────────────────────────────────────── --}}
        <div
            @if (filament()->hasTenancy())
                x-persist="topbar.end.panel-{{ filament()->getId() }}.tenant-{{ filament()->getTenant()?->getKey() }}"
            @else
                x-persist="topbar.end.panel-{{ filament()->getId() }}"
            @endif
            class="op-topbar-right"
        >
            {{-- Quick-create dropdown (role-aware) --}}
            <x-admin.quick-create />

            {{-- Custom notification center rendered via TOPBAR_END hook --}}

            {{-- Environment indicator --}}
            <x-admin.environment-indicator />

            {{-- Keyboard shortcuts --}}
            <button
                type="button"
                @click="window.dispatchEvent(new Event('open-keyboard-shortcuts'))"
                class="fi-topbar-item-button flex items-center justify-center w-9 h-9 transition-all duration-200"
                title="Keyboard shortcuts (?)"
                aria-label="Show keyboard shortcuts"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" style="color: var(--color-text-muted);">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
                </svg>
            </button>

            {{-- Light / dark mode toggle --}}
            <x-admin.theme-toggle />

            {{-- User account menu --}}
            @if (filament()->auth()->check() && filament()->hasUserMenu() && filament()->getUserMenuPosition() === \Filament\Enums\UserMenuPosition::Topbar)
                <x-filament-panels::user-menu />
            @endif
        </div>

        {{-- Keep hook for plugin extensibility (AdminPanelProvider no longer injects here) --}}
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::TOPBAR_END) }}
    </nav>

    <x-filament-actions::modals />
</div>
