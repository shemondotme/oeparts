<x-filament-widgets::widget class="fi-wi-widget">
    <div class="op-card op-card-hover p-5" style="border-left: 3px solid {{ $statusColor }};">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold uppercase tracking-wider" style="color: var(--text-secondary);">
                Pending Orders
            </span>
            <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider rounded-full" style="background: {{ $statusColor }}15; color: {{ $statusColor }}; border: 1px solid {{ $statusColor }}30;">
                @if ($status === 'Delayed')
                    <span class="w-1.5 h-1.5 rounded-full mr-1 op-badge-pulse" style="background: {{ $statusColor }};"></span>
                @else
                    <span class="w-1.5 h-1.5 rounded-full mr-1" style="background: {{ $statusColor }};"></span>
                @endif
                {{ $status }}
            </span>
        </div>
        <div class="flex items-baseline gap-1">
            <span class="text-3xl font-bold font-mono" data-countup style="color: var(--text-primary); font-family: var(--font-mono);">
                {{ number_format($count) }}
            </span>
        </div>
        <div class="mt-1 text-xs" style="color: var(--text-secondary);">
            {{ $waitLabel }}
        </div>
    </div>
</x-filament-widgets::widget>
