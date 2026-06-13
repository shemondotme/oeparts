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
            <div
                x-load
                x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
                wire:ignore
                data-chart-type="{{ $type }}"
                x-data="chart({
                            cachedData: @js($this->getCachedData()),
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
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
