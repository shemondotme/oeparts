<x-filament-widgets::widget class="fi-wi-new-orders-kpi">
    @php
        $barData = $bars ?? array_fill(0, 7, 0);
        $maxBar  = max(max($barData), 1);
    @endphp

    <x-admin.kpi-card label="New Orders" :class="($exceedsThreshold ?? false) ? 'op-kpi-threshold-glow' : ''">
        <x-slot:icon>
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z"/>
            </svg>
        </x-slot:icon>

        <x-slot:headerEnd>
            <span class="inline-flex items-center gap-1 text-[11px] font-semibold"
                  style="color: {{ $trendColor ?? 'var(--color-text-secondary)' }};">
                @if (($trend ?? 'flat') === 'up')
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941"/>
                    </svg>
                @elseif (($trend ?? '') === 'down')
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6L9 12.75l4.306-4.307a11.95 11.95 0 015.814 5.519l2.74 1.22m0 0l-5.94 2.28m5.94-2.28l-2.28-5.941"/>
                    </svg>
                @else
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.75h16.5M3.75 9.75l3.75-3.75M3.75 9.75l3.75 3.75"/>
                    </svg>
                @endif
                {{ $trendLabel ?? '—' }}
            </span>
        </x-slot:headerEnd>

        {{-- Value + threshold badge --}}
        <div class="mt-3 flex items-baseline gap-2">
            <span class="op-kpi-value tabular-nums font-mono" data-countup>
                {{ number_format($count ?? 0) }}
            </span>
            @if ($exceedsThreshold ?? false)
                <span class="inline-flex items-center px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider rounded-full"
                      style="background: color-mix(in srgb, var(--color-success-500) 12%, transparent); color: var(--color-success-700);">
                    Above target
                </span>
            @endif
        </div>

        {{-- 7-day daily bar mini-chart --}}
        @if (!empty($barData) && array_sum($barData) > 0)
            <div class="flex items-end gap-1 mt-auto pt-3" style="height: 32px;" aria-hidden="true">
                @foreach ($barData as $j => $b)
                    <div class="flex-1 rounded-t-sm transition-all duration-300"
                         style="height: {{ max(3, round(($b / $maxBar) * 100)) }}%;
                                background: var(--widget-accent, var(--color-brand-500));
                                opacity: {{ $j === 6 ? '1' : '0.45' }};"
                         title="{{ $b }} order{{ $b !== 1 ? 's' : '' }}"></div>
                @endforeach
            </div>
        @else
            <div class="mt-auto pt-3 text-[11px]" style="color: var(--color-text-muted);">No orders in the last 7 days</div>
        @endif
    </x-admin.kpi-card>
</x-filament-widgets::widget>
