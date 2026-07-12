{{--
    Custom ChartWidget view: identical to filament-widgets::chart-widget except
    the period filter renders as a Filament-themed segmented pill control
    (design tokens) instead of a native <select> whose option popup the browser
    renders unstyled. Bound to the widget's $filter property via $set(), which
    fires updatedFilter(). Keep in sync with the vendor blade on Filament upgrades.
--}}
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
@endphp

<x-filament-widgets::widget class="fi-wi-chart">
    <x-filament::section
        :description="$description"
        :heading="$heading"
        :collapsible="$isCollapsible"
    >
        @if ($filters)
            <x-slot name="afterHeader">
                <div
                    class="flex items-center gap-0.5 rounded-lg p-0.5"
                    style="background: var(--color-bg-inset);"
                >
                    @foreach ($filters as $value => $label)
                        @php $active = (string) ($this->filter ?? '') === (string) $value; @endphp
                        <button
                            type="button"
                            wire:click="$set('filter', '{{ $value }}')"
                            wire:loading.attr="disabled"
                            class="op-focus-ring rounded-md px-2.5 py-1 text-xs font-semibold transition-colors"
                            @style([
                                'color: var(--color-text-secondary)' => ! $active,
                                'background: var(--color-bg-surface-raised); color: var(--color-text-primary); box-shadow: 0 1px 2px rgba(0,0,0,.12)' => $active,
                            ])
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </x-slot>
        @endif

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
