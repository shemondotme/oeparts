<x-filament-widgets::widget class="fi-wi-chart">
    <x-filament::section :heading="$heading ?? 'Loading...'">
        <div class="op-chart-skeleton">
            <div style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;">
                <span style="color: var(--color-text-muted); font-size: 0.875rem;">Loading chart data...</span>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
