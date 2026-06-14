@php
    $rate = (float) ($getState() ?? 0);
    $color = $rate >= 90 ? 'var(--accent-success)' : ($rate >= 70 ? 'var(--accent-warning)' : 'var(--accent-danger)');
    $label = $rate > 0 ? number_format($rate, 0) . '%' : '—';
@endphp

@if($rate > 0)
    <div class="flex flex-col items-center gap-0.5 w-full min-w-16">
        <span class="text-xs font-semibold tabular-nums" style="color: {{ $color }}">{{ $label }}</span>
        <div class="w-full h-1 rounded-full overflow-hidden" style="background: var(--color-border-subtle)">
            <div class="h-full rounded-full transition-all duration-500"
                 style="width: {{ min($rate, 100) }}%; background: {{ $color }}"></div>
        </div>
    </div>
@else
    <span class="text-xs opacity-40">—</span>
@endif
