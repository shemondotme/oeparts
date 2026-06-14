<x-filament-panels::page class="fi-dashboard">
    {{-- Living aurora-mesh backdrop --}}
    <div class="op-dashboard-aurora" aria-hidden="true"></div>

    @php($dashboards = $this->getDashboardList())

    @if (count($dashboards) > 1)
        @php($tabIcons = [
            'Command Center' => 'heroicon-o-cpu-chip',
            'Operations' => 'heroicon-o-truck',
            'Inventory & Sourcing' => 'heroicon-o-magnifying-glass',
            'System & Admin' => 'heroicon-o-wrench-screwdriver',
        ])
        <nav class="op-dashboard-tabs flex items-center gap-1 mb-6 overflow-x-auto" aria-label="Dashboard sections"
            style="padding: 4px; border-radius: 12px; background: var(--glass-bg); -webkit-backdrop-filter: var(--glass-blur); backdrop-filter: var(--glass-blur); border: 1px solid var(--glass-border);"
            x-data x-init="$nextTick(() => { const a = $el.querySelector('.is-active'); if (a) a.scrollIntoView({ behavior: 'instant', inline: 'nearest', block: 'nearest' }); })">
            @foreach ($dashboards as $dashboard)
                @php($icon = $tabIcons[$dashboard['name']] ?? 'heroicon-o-squares-2x2')
                <button
                    type="button"
                    wire:click="switchDashboard({{ $dashboard['id'] }})"
                    @class([
                        'op-dashboard-tab inline-flex items-center gap-2 px-3.5 py-2 text-xs font-semibold rounded-lg transition-all duration-200 whitespace-nowrap',
                        'is-active' => $dashboard['id'] === $this->activeDashboardId,
                    ])
                    @style([
                        'background: linear-gradient(135deg, var(--aurora-indigo) 0%, var(--aurora-violet) 100%); color: white; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);' => $dashboard['id'] === $this->activeDashboardId,
                        'color: var(--color-text-muted);' => $dashboard['id'] !== $this->activeDashboardId,
                    ])
                    aria-current="{{ $dashboard['id'] === $this->activeDashboardId ? 'page' : 'false' }}"
                >
                    @svg($icon, 'w-4 h-4')
                    <span>{{ $dashboard['name'] }}</span>
                </button>
            @endforeach
        </nav>
    @endif

    <div x-data="{ period: '{{ $this->period }}', loading: false }" class="flex items-center justify-between mb-6"
         x-on:period-changed.window="loading = false">
        {{-- Period-change loading bar --}}
        <div x-show="loading" class="op-period-loading" x-transition:enter="transition-opacity duration-100" x-transition:leave="transition-opacity duration-300" aria-hidden="true"></div>

        <p class="text-xs" style="color: var(--color-text-muted);">
            Showing data for: <span class="font-bold" style="background: linear-gradient(90deg, var(--aurora-indigo) 0%, var(--aurora-violet) 50%, var(--aurora-cyan) 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;" x-text="({'1':'Today','7':'Last 7 days','30':'Last 30 days','90':'Last 90 days','365':'Last year'})[period]"></span>
        </p>
        <div class="flex items-center gap-1.5" style="padding: 5px; border-radius: 12px; background: var(--glass-bg); -webkit-backdrop-filter: var(--glass-blur); backdrop-filter: var(--glass-blur); border: 1px solid var(--glass-border); box-shadow: var(--glass-shadow);">
            @foreach(['1' => 'Today', '7' => '7d', '30' => '30d', '90' => '90d', '365' => '1y'] as $value => $label)
                <button
                    type="button"
                    @click="period = '{{ $value }}'; loading = true; $wire.call('setPeriod', '{{ $value }}')"
                    :aria-pressed="period === '{{ $value }}'"
                    aria-label="Show data for {{ $label }}"
                    :style="period === '{{ $value }}'
                        ? 'background: linear-gradient(135deg, var(--aurora-indigo) 0%, var(--aurora-violet) 100%); color: white; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3); border-radius: 7px; font-weight: 700;'
                        : 'color: var(--color-text-muted); border-radius: 7px; transition: all 150ms ease;'"
                    class="px-2.5 py-1 text-xs font-semibold transition-all duration-150"
                >{{ $label }}</button>
            @endforeach
        </div>
    </div>

    @if ($this->editMode)
        <div class="mb-4 px-4 py-2.5 rounded-lg text-sm flex items-center gap-2 flex-wrap"
             style="background: var(--color-warning-50, #fffbeb); border: 1px dashed var(--color-warning-400, #fbbf24); color: var(--color-warning-700, #b45309);">
            <x-heroicon-o-arrows-pointing-out class="w-4 h-4 shrink-0" />
            <span>Edit mode — drag widgets to move, resize with the corner handle. Changes save automatically.</span>
        </div>
    @endif

    {{-- Skeleton canvas: shown while op-tab-switching class is on <body> -----}}
    <div class="op-canvas-skeleton" aria-hidden="true" role="presentation">
        <div class="op-skeleton" style="grid-column:span 12;height:60px;border-radius:8px;"></div>
        <div class="op-skeleton" style="grid-column:span 12;height:12px;border-radius:6px;margin-top:2px;opacity:0.35;"></div>
        <div class="op-skeleton op-skeleton-card" style="grid-column:span 4;"></div>
        <div class="op-skeleton op-skeleton-card" style="grid-column:span 4;"></div>
        <div class="op-skeleton op-skeleton-card" style="grid-column:span 4;"></div>
        <div class="op-skeleton op-skeleton-card" style="grid-column:span 8;height:320px;"></div>
        <div class="op-skeleton op-skeleton-card" style="grid-column:span 4;height:320px;"></div>
        <div class="op-skeleton op-skeleton-card" style="grid-column:span 6;"></div>
        <div class="op-skeleton op-skeleton-card" style="grid-column:span 6;"></div>
    </div>

    <div
        id="dashboard-canvas"
        wire:ignore.self
        class="grid-stack"
        data-edit-mode="{{ $this->editMode ? '1' : '0' }}"
        data-dashboard-id="{{ $this->activeDashboardId }}"
    >
        @foreach ($this->getCanvasItems() as $item)
            @php($locked = in_array($item['id'], ['dashboard_header', 'health_strip']))
            <div
                class="grid-stack-item {{ $locked ? 'gs-locked' : '' }}"
                gs-id="{{ $item['id'] }}"
                gs-x="{{ $item['x'] }}"
                gs-y="{{ $item['y'] }}"
                gs-w="{{ $item['w'] }}"
                gs-h="{{ $item['h'] }}"
                gs-min-w="{{ $item['minW'] ?? min($item['w'], 4) }}"
                gs-min-h="{{ $item['minH'] ?? $item['h'] }}"
                gs-no-move="{{ $locked ? 'true' : 'false' }}"
                gs-no-resize="{{ $locked ? 'true' : 'false' }}"
                data-widget-type="{{ $item['type'] ?? '' }}"
            >
                <div class="grid-stack-item-content">
                    @if ($this->editMode && !$locked)
                        <div class="op-drag-handle" aria-label="Move {{ $item['id'] }}" title="Drag to reorder">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 5.25h16.5m-16.5 4.5h16.5m-16.5 4.5h16.5m-16.5 4.5h16.5"/></svg>
                        </div>
                    @endif
                    @livewire($item['class'], key("widget-{$this->activeDashboardId}-{$item['id']}"))
                </div>
            </div>
        @endforeach
    </div>

    @if ($this->getCanvasItems() === [])
        <div class="flex flex-col items-center justify-center py-16 text-center">
            <x-heroicon-o-squares-2x2 class="w-10 h-10 mb-3" style="color: var(--color-text-secondary);" />
            <p class="text-sm font-semibold" style="color: var(--color-text-primary);">This dashboard is empty</p>
            <p class="text-xs mt-1" style="color: var(--color-text-secondary);">Use "Manage Widgets" to add widgets to this canvas.</p>
        </div>
    @endif
</x-filament-panels::page>
