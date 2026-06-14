<x-filament-widgets::widget class="fi-wi-widget">
    <div class="op-card op-card-hover p-5" style="border-left: 3px solid var(--accent-brand);">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold uppercase tracking-wider" style="color: var(--text-secondary);">
                New Orders
            </span>
            <span class="flex items-center gap-1 text-xs font-semibold" style="color: {{ $trendColor }};">
                @if ($trend === 'up')
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941"/></svg>
                @elseif ($trend === 'down')
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941"/></svg>
                @else
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.75h16.5M3.75 9.75l3.75-3.75M3.75 9.75l3.75 3.75"/></svg>
                @endif
                <span>{{ $trendLabel }}</span>
            </span>
        </div>
        <div class="flex items-baseline gap-1">
            <span class="text-3xl font-bold font-mono" data-countup style="color: var(--text-primary); font-family: var(--font-mono);">
                {{ number_format($count) }}
            </span>
            @if ($exceedsThreshold)
                <span class="ml-2 inline-flex items-center px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider rounded-full" style="background: var(--color-success-50); color: var(--color-success-700);">
                    Success
                </span>
            @endif
        </div>
        @if (!empty($hourly))
            <div class="mt-3 flex items-end gap-0.5 h-8">
                @foreach (range(0, 23) as $h)
                    @php($val = $hourly[$h] ?? 0)
                    @php($max = max(array_values($hourly)) ?: 1)
                    @php($pct = ($val / $max) * 100)
                    <div class="flex-1 rounded-t-sm" style="height: {{ max(2, $pct) }}%; background: var(--accent-brand); opacity: {{ 0.3 + ($val / max($max, 1)) * 0.7 }};"></div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
