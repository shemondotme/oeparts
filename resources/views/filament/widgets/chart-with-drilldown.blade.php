@php
    use Filament\Widgets\View\Components\ChartWidgetComponent;
    use Illuminate\View\ComponentAttributeBag;

    $color = $this->getColor();
    $heading = $this->getHeading();
    $description = $this->getDescription();
    $filters = $this->getFilters();
    $isCollapsible = $this->isCollapsible();
    $type = $this->getType();
    $maxHeight = $this->getMaxHeight();
    $hasMaxHeight = filled($maxHeight) && $maxHeight !== '100%';
    $drilldownUrl = method_exists($this, 'getDrilldownUrl') ? $this->getDrilldownUrl() : null;
    $hasExport = method_exists($this, 'exportPng');

    $cachedData = $this->getCachedData();
    $chartHasData = collect($cachedData['datasets'] ?? [])
        ->flatMap(fn ($d) => $d['data'] ?? [])
        ->contains(fn ($v) => is_numeric($v) && (float) $v != 0.0);
@endphp

<x-filament-widgets::widget class="fi-wi-chart">
    <x-filament::section
        :collapsible="$isCollapsible"
    >
        <x-slot name="header">
            <div class="op-chart-frame-header">
                <div class="op-chart-frame-header-start">
                    <h4 class="op-chart-frame-title">{{ $heading }}</h4>
                    @if ($description)
                        <p class="op-chart-frame-subtitle">{{ $description }}</p>
                    @endif
                </div>
                <div class="op-chart-frame-actions">
                    @if ($hasExport)
                        <button
                            wire:click="exportPng"
                            wire:loading.attr="disabled"
                            type="button"
                            class="fi-btn fi-btn-color-gray fi-btn-size-sm fi-btn-outlined inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 text-xs font-medium transition"
                            title="Download chart as PNG"
                        >
                            <x-heroicon-o-arrow-down-tray class="h-4 w-4" />
                            PNG
                        </button>
                    @endif

                    @if ($drilldownUrl)
                        <a href="{{ $drilldownUrl }}" wire:navigate class="op-chart-frame-drilldown">
                            View All &rarr;
                        </a>
                    @endif

                    @if ($filters)
                        {{-- Segmented date-range control --}}
                        <div class="flex rounded-md overflow-hidden op-chart-filter-pills"
                             style="border: 1px solid var(--color-border-default);"
                             role="group"
                             aria-label="Date range">
                            @foreach ($filters as $value => $label)
                                @php $active = ($this->filter ?? '30') === (string) $value; @endphp
                                <button
                                    type="button"
                                    wire:click="$set('filter', '{{ $value }}')"
                                    wire:key="filter-{{ $value }}"
                                    class="px-3 py-1.5 text-xs font-semibold transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-inset"
                                    style="
                                        {{ $active
                                            ? 'background: var(--color-brand-500); color: #ffffff;'
                                            : 'background: transparent; color: var(--color-text-muted);' }}
                                        focus-ring-color: var(--color-brand-500);
                                    "
                                    aria-pressed="{{ $active ? 'true' : 'false' }}"
                                >{{ $label }}</button>
                            @endforeach
                        </div>
                    @endif

                    @if (method_exists($this, 'getFiltersSchema'))
                        <x-filament::dropdown
                            placement="bottom-end"
                            shift
                            width="xs"
                            class="fi-wi-chart-filter"
                        >
                            <x-slot name="trigger">
                                {{ $this->getFiltersTriggerAction() }}
                            </x-slot>

                            <div class="fi-wi-chart-filter-content">
                                {{ $this->getFiltersSchema() }}

                                @if (method_exists($this, 'hasDeferredFilters') && $this->hasDeferredFilters())
                                    <div class="fi-wi-chart-filter-content-actions-ctn">
                                        {{ $this->getFiltersApplyAction() }}
                                        {{ $this->getFiltersResetAction() }}
                                    </div>
                                @endif
                            </div>
                        </x-filament::dropdown>
                    @endif
                </div>
            </div>
        </x-slot>

        <div
            @if ($pollingInterval = $this->getPollingInterval())
                wire:poll.{{ $pollingInterval }}="updateChartData"
            @endif
        >
            @if ($chartHasData)
            <div
                x-load
                x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
                wire:ignore
                data-chart-type="{{ $type }}"
                x-data="chart({
                            cachedData: @js($cachedData),
                            options: @js($this->getOptions()),
                            type: @js($type),
                        })"
                {{
                    (new ComponentAttributeBag)
                        ->color(ChartWidgetComponent::class, $color)
                        ->class([
                            'fi-wi-chart-canvas-ctn',
                            'fi-wi-chart-canvas-ctn-no-aspect-ratio' => $hasMaxHeight,
                        ])
                }}
            >
                <canvas
                    x-ref="canvas"
                    @style([
                        'width: 100%',
                        'height: 100%; max-height: 100%' => ! $hasMaxHeight,
                        "max-height: {$maxHeight}" => $hasMaxHeight,
                    ])
                ></canvas>

                <span x-ref="backgroundColorElement" class="fi-wi-chart-bg-color"></span>
                <span x-ref="borderColorElement" class="fi-wi-chart-border-color"></span>
                <span x-ref="gridColorElement" class="fi-wi-chart-grid-color"></span>
                <span x-ref="textColorElement" class="fi-wi-chart-text-color"></span>
            </div>
            @else
            <div style="position: relative; min-height: 20rem; height: 100%;">
                @include('filament.widgets.empty-state', [
                    'icon' => 'heroicon-o-chart-bar',
                    'heading' => 'No data for this period',
                    'description' => "Once there's activity in this range, it'll show up here.",
                ])
            </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
