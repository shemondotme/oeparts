<div>
    @php
        $navigation           = filament()->getNavigation();
        $hasNavigation        = filament()->hasNavigation();
        $hasTopbar            = filament()->hasTopbar();
        $isSidebarCollapsible = filament()->isSidebarCollapsibleOnDesktop() || filament()->isSidebarFullyCollapsibleOnDesktop();

        // Named groups for the rail (skip ungrouped items — Dashboard is linked via topbar logo)
        $namedGroups = collect($navigation)->filter(fn ($g) => filled($g->getLabel()))->values();

        // Determine which group should be pre-opened based on URL (server-side)
        $serverActiveGroup = '';
        foreach ($namedGroups as $_g) {
            if ($_g->isActive()) {
                $serverActiveGroup = $_g->getLabel();
                break;
            }
        }

        // Sidebar footer user data
        $footerUser    = filament()->auth()->user();

        // Role-default fallback: when the current page belongs to no nav
        // group (e.g. the Dashboard), open to the signed-in admin's most
        // relevant group instead of leaving the panel closed. Computed
        // server-side -- alongside $serverActiveGroup, which Alpine's init()
        // below always snaps to -- so the first paint is already correct
        // and there's no client-only flash-of-wrong-group.
        if ($serverActiveGroup === '' && $footerUser) {
            $serverActiveGroup = \App\Filament\Support\AdminUi::defaultNavGroupFor($footerUser);
        }
        $footerRoles   = $footerUser?->roles ?? collect();
        $footerRole    = $footerRoles->first();
        $footerLabel   = $footerRole?->name ?? 'Admin';
        $roleColor     = match ($footerLabel) {
            'super_admin'  => '#F59E0B',
            'admin'        => '#3B82F6',
            'catalog_admin'=> '#10B981',
            default        => '#94A3B8',
        };
    @endphp

    {{-- format-ignore-start --}}
    <aside
        x-data="{
            activeGroup: $persist(@js($serverActiveGroup)).as('oeparts.navGroup'),
            init() {
                // Always snap to the server-determined active group on navigation
                const serverGroup = @js($serverActiveGroup);
                if (serverGroup && serverGroup !== this.activeGroup) {
                    this.activeGroup = serverGroup;
                }
            },
            openGroup(label) {
                this.activeGroup = (this.activeGroup === label) ? '' : label;
            },
            closePanel() {
                this.activeGroup = '';
            }
        }"
        x-on:keydown.escape.window="closePanel()"
        x-cloak
        x-bind:class="{ 'fi-sidebar-open': $store.sidebar.isOpen }"
        class="fi-sidebar fi-main-sidebar op-sidebar"
    >
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_START) }}

        {{-- ── Rail (56 px, always visible on desktop) ────────────────────────── --}}
        <div class="op-sidebar-rail" role="navigation" aria-label="Section navigation">

            {{-- Brand mark — links to dashboard --}}
            <div class="op-sidebar-rail-header">
                @if ($homeUrl = filament()->getHomeUrl())
                    <a {{ \Filament\Support\generate_href_html($homeUrl) }} class="op-sidebar-rail-logo" aria-label="OeParts — go to dashboard">
                        <span class="op-sidebar-rail-logo-mark">OE</span>
                    </a>
                @else
                    <span class="op-sidebar-rail-logo">
                        <span class="op-sidebar-rail-logo-mark">OE</span>
                    </span>
                @endif
            </div>

            {{-- Navigation group icons --}}
            <nav class="op-sidebar-rail-nav">
                @foreach ($namedGroups as $group)
                    @php
                        $groupLabel = $group->getLabel();
                        $groupIcon  = $group->getIcon() ?? 'heroicon-o-squares-2x2';
                    @endphp
                    <button
                        type="button"
                        x-on:click="openGroup(@js($groupLabel))"
                        x-bind:class="{ 'op-sidebar-rail-item--active': activeGroup === @js($groupLabel) }"
                        x-bind:aria-expanded="activeGroup === @js($groupLabel)"
                        class="op-sidebar-rail-item"
                        title="{{ $groupLabel }}"
                        aria-label="{{ $groupLabel }}"
                        aria-haspopup="true"
                    >
                        <x-filament::icon
                            :icon="$groupIcon"
                            class="op-sidebar-rail-icon"
                        />
                        @if ($group->isActive())
                            <span class="op-sidebar-rail-active-dot" aria-hidden="true"></span>
                        @endif
                    </button>
                @endforeach
            </nav>

            {{-- Rail footer — user avatar chip --}}
            @if ($footerUser)
                <div class="op-sidebar-rail-footer">
                    <a
                        href="{{ filament()->getProfileUrl() ?? '#' }}"
                        class="op-sidebar-rail-avatar-btn"
                        title="{{ filament()->getUserName($footerUser) }} — {{ $footerLabel }}"
                        aria-label="Profile: {{ filament()->getUserName($footerUser) }}"
                    >
                        <div class="op-sidebar-rail-avatar">
                            <x-filament-panels::avatar.user :user="$footerUser" loading="lazy" />
                        </div>
                    </a>
                </div>
            @endif
        </div>

        {{-- ── Flyout Panel (position:absolute, slides in from rail) ────────────── --}}
        <div
            class="op-sidebar-panel"
            x-bind:class="{ 'op-sidebar-panel--open': activeGroup !== '' }"
            x-bind:aria-hidden="activeGroup === ''"
            role="navigation"
            aria-label="Section items"
        >
            {{-- Panel header --}}
            <div class="op-sidebar-panel-header">
                <span class="op-sidebar-panel-title" x-text="activeGroup"></span>
                <button
                    type="button"
                    x-on:click="closePanel()"
                    class="op-sidebar-panel-close"
                    aria-label="Close navigation panel"
                    title="Close (Esc)"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Items for each group, shown only when group is active --}}
            <div class="op-sidebar-panel-items">
                {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_NAV_START) }}

                @foreach ($namedGroups as $group)
                    @php $groupLabel = $group->getLabel(); @endphp
                    <div x-show="activeGroup === @js($groupLabel)" x-cloak class="op-sidebar-panel-group">
                        @foreach ($group->getItems() as $item)
                            @php
                                $isItemActive         = $item->isActive();
                                $itemIcon             = $isItemActive ? ($item->getActiveIcon() ?? $item->getIcon()) : $item->getIcon();
                                $itemBadge            = $item->getBadge();
                                $itemBadgeColor       = $item->getBadgeColor($itemBadge);
                                $itemUrl              = $item->getUrl();
                                $openInNewTab         = $item->shouldOpenUrlInNewTab();
                                $itemExtraAttributes  = $item->getExtraAttributeBag();
                            @endphp
                            <x-filament-panels::sidebar.item
                                :active="$isItemActive"
                                :badge="$itemBadge"
                                :badge-color="$itemBadgeColor"
                                :icon="$itemIcon"
                                :should-open-url-in-new-tab="$openInNewTab"
                                :url="$itemUrl"
                                :attributes="\Filament\Support\prepare_inherited_attributes($itemExtraAttributes)"
                            >
                                {{ $item->getLabel() }}
                            </x-filament-panels::sidebar.item>
                        @endforeach
                    </div>
                @endforeach

                {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_NAV_END) }}
            </div>

            {{-- Panel footer — full user card --}}
            @if ($footerUser)
                <div class="op-sidebar-panel-footer">
                    <div class="op-sidebar-panel-user">
                        <div class="op-sidebar-panel-avatar">
                            <x-filament-panels::avatar.user :user="$footerUser" loading="lazy" />
                            <span class="fi-sidebar-status-dot"></span>
                        </div>
                        <div class="op-sidebar-panel-user-info">
                            <span class="op-sidebar-panel-user-name">{{ filament()->getUserName($footerUser) }}</span>
                            <span
                                class="op-sidebar-panel-user-role"
                                style="--role-color: {{ $roleColor }}"
                            >{{ $footerLabel }}</span>
                        </div>
                    </div>
                    <div class="op-sidebar-panel-version">OeParts v1.0</div>
                </div>
            @endif
        </div>

        {{-- Mobile backdrop: closes panel when tapping outside --}}
        <div
            class="op-sidebar-backdrop"
            x-show="activeGroup !== '' && $store.sidebar.isOpen"
            x-on:click="closePanel()"
            aria-hidden="true"
            style="display: none;"
        ></div>

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_FOOTER) }}

        <x-filament-actions::modals />
    </aside>
    {{-- format-ignore-end --}}
</div>
