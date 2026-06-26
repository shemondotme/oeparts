<x-filament-widgets::widget class="fi-wi-pending-orders-kpi">
    @php
        $isDelayed = ($status ?? '') === 'Delayed';
        $accentVar = $isDelayed ? 'var(--color-danger-500)' : 'var(--widget-accent, var(--color-brand-500))';
        $isEmpty   = ($count ?? 0) === 0;
    @endphp

    <x-admin.kpi-card label="Pending Orders" :accent="$accentVar">
        <x-slot:icon>
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </x-slot:icon>

        @if (!$isEmpty)
            <x-slot:headerEnd>
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-[10px] font-bold uppercase tracking-widest rounded-full"
                      style="background: color-mix(in srgb, {{ $accentVar }} 10%, transparent); color: {{ $accentVar }}; border: 1px solid color-mix(in srgb, {{ $accentVar }} 25%, transparent);"
                      role="status"
                      aria-label="Order status: {{ $status ?? 'On Track' }}">
                    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $isDelayed ? 'op-badge-pulse' : '' }}"
                          style="background: {{ $accentVar }};"></span>
                    {{ $status ?? 'On Track' }}
                </span>
            </x-slot:headerEnd>
        @endif

        {{-- Value + wait label --}}
        @if (!$isEmpty)
            <div class="mt-3 z-10 relative">
                <span class="op-kpi-value tabular-nums font-mono" data-countup>
                    {{ number_format($count ?? 0) }}
                </span>
                <p class="text-xs mt-1" style="color: var(--color-text-secondary);">
                    {{ $waitLabel ?? '' }}
                </p>
            </div>
        @else
            {{-- Empty state --}}
            <div class="flex flex-col items-center justify-center flex-1 py-4 text-center gap-1">
                <svg class="w-7 h-7 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"
                     style="color: var(--color-text-muted); opacity: 0.5;" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm font-semibold" style="color: var(--color-text-secondary);">All clear</p>
                <p class="text-xs" style="color: var(--color-text-muted);">No orders awaiting action</p>
            </div>
        @endif
    </x-admin.kpi-card>
</x-filament-widgets::widget>
