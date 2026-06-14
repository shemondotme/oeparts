@php
    $rate = (float) ($getState() ?? 0);
    // Lower return rate is better — red above 10%, amber 5-10%, green < 5%
    $color = $rate >= 10 ? 'var(--accent-danger)' : ($rate >= 5 ? 'var(--accent-warning)' : 'var(--accent-success)');
    $label = $rate > 0 ? number_format($rate, 1) . '%' : '—';
@endphp

@if($rate > 0)
    <div class="flex flex-col items-center gap-0.5 w-full min-w-16">
        <span class="text-xs font-semibold tabular-nums" style="color: {{ $color }}">{{ $label }}</span>
        <div class="w-full h-1 rounded-full overflow-hidden" style="background: var(--color-border-subtle)">
            <div class="h-full rounded-full transition-all duration-500"
                 style="width: {{ min($rate * 5, 100) }}%; background: {{ $color }}"></div>
        </div>
    </div>
@else
    <span class="text-xs opacity-40">—</span>
@endif
