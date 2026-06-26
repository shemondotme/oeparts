<x-filament-widgets::widget class="fi-wi-revenue-kpi">
    @php
        $vals   = array_map('floatval', $sparkline ?? array_fill(0, 7, '0'));
        $maxVal = max(max($vals), 0.01);
        $count  = count($vals);
        $step   = $count > 1 ? 100 / ($count - 1) : 100;
        $pts    = collect($vals)
            ->map(fn($v, $i) => round($i * $step, 2) . ',' . round((1 - $v / $maxVal) * 36 + 2, 2))
            ->implode(' ');
        $polyPts = '0,40 ' . $pts . ' 100,40';
    @endphp

    <x-admin.kpi-card :label="($periodLabel ?? 'Today') . ' Revenue'">
        <x-slot:icon>
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </x-slot:icon>

        @if (isset($trend) && $trend !== 'flat')
            <x-slot:headerEnd>
                <span class="inline-flex items-center gap-1 text-[11px] font-semibold"
                      style="color: {{ $trendColor ?? 'var(--color-text-secondary)' }};">
                    @if ($trend === 'up')
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941"/>
                        </svg>
                    @else
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6L9 12.75l4.306-4.307a11.95 11.95 0 015.814 5.519l2.74 1.22m0 0l-5.94 2.28m5.94-2.28l-2.28-5.941"/>
                        </svg>
                    @endif
                    {{ $trendLabel ?? '' }}
                </span>
            </x-slot:headerEnd>
        @endif

        {{-- Value --}}
        <div class="mt-3 mb-1 z-10 relative">
            @if ($hasData ?? false)
                <span class="op-kpi-value tabular-nums font-mono"
                      data-countup
                      data-prefix="€">
                    €{{ number_format((float) ($current ?? '0'), 2) }}
                </span>
            @else
                <span class="op-kpi-value font-mono" style="color: var(--color-text-muted);">—</span>
                <p class="text-xs mt-1" style="color: var(--color-text-muted);">No revenue in this period</p>
            @endif
        </div>

        {{-- Sparkline SVG — server-rendered, decorative, absolute at bottom --}}
        @if ($hasData ?? false)
            <svg viewBox="0 0 100 40"
                 preserveAspectRatio="none"
                 class="absolute inset-x-0 bottom-0 w-full pointer-events-none"
                 style="height: 48px; opacity: 0.22;"
                 aria-hidden="true">
                <defs>
                    <linearGradient id="sg-{{ $this->getId() }}" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="var(--widget-accent, var(--color-brand-500))" stop-opacity="0.75"/>
                        <stop offset="100%" stop-color="var(--widget-accent, var(--color-brand-500))" stop-opacity="0"/>
                    </linearGradient>
                </defs>
                <polygon points="{{ $polyPts }}" fill="url(#sg-{{ $this->getId() }})"/>
                <polyline points="{{ $pts }}"
                          fill="none"
                          stroke="var(--widget-accent, var(--color-brand-500))"
                          stroke-width="1.5"
                          stroke-linejoin="round"
                          stroke-linecap="round"/>
            </svg>
        @else
            {{-- Empty state decoration --}}
            <svg class="absolute inset-x-0 bottom-0 w-full pointer-events-none"
                 style="height: 48px; opacity: 0.03;"
                 viewBox="0 0 100 40" preserveAspectRatio="none" aria-hidden="true">
                <defs>
                    <pattern id="diag-{{ $this->getId() }}" patternUnits="userSpaceOnUse" width="6" height="6" patternTransform="rotate(45)">
                        <line x1="0" y1="0" x2="0" y2="6" stroke="var(--color-text-primary)" stroke-width="2"/>
                    </pattern>
                </defs>
                <rect width="100" height="40" fill="url(#diag-{{ $this->getId() }})"/>
            </svg>
        @endif
    </x-admin.kpi-card>
</x-filament-widgets::widget>
