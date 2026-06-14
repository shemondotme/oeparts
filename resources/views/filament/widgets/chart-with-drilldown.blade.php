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
    $drilldownUrl = $this->getDrilldownUrl();
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
                            class="fi-btn fi-btn-color-gray fi-btn-size-sm fi-btn-outlined inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 text-xs font-medium transition"
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
                        <x-filament::input.wrapper
                            inline-prefix
                            wire:target="filter"
                            class="fi-wi-chart-filter"
                        >
                            <x-filament::input.select
                                inline-prefix
                                wire:model.live="filter"
                            >
                                @foreach ($filters as $value => $label)
                                    <option value="{{ $value }}">
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
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
                <div class="op-chart-empty">
                    <x-heroicon-o-chart-bar class="w-8 h-8" style="color: var(--widget-accent); opacity: 0.6;" />
                    <div class="op-chart-empty-title">No data for this period</div>
                    <div class="op-chart-empty-desc">Once there's activity in this range, it'll show up here.</div>
                </div>
            </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
