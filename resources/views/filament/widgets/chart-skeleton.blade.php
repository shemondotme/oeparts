<x-filament-widgets::widget class="fi-wi-chart">
    <x-filament::section>
        <x-slot name="header">
            <div class="op-chart-frame-header">
                <div class="op-chart-frame-header-start">
                    <h4 class="op-chart-frame-title">{{ $heading ?? 'Loading…' }}</h4>
                </div>
            </div>
        </x-slot>

        <div class="op-chart-skel" role="status" aria-label="Loading chart data">
            @foreach ([58, 82, 44, 72, 92, 52, 66] as $h)
                <div class="op-chart-skel-bar" style="height: {{ $h }}%;"></div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
