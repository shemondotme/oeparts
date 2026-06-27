<div class="op-health-indicator">
    @if ($url)
        <a
            href="{{ $url }}"
            wire:navigate
            class="op-health-dot op-health-dot-{{ $status }}"
            title="System health: {{ $status === 'ok' ? 'All systems normal' : 'Degraded — click to view health dashboard' }}"
            aria-label="System health: {{ $status }}"
        ></a>
    @else
        <span
            class="op-health-dot op-health-dot-{{ $status }}"
            title="System health: {{ $status === 'ok' ? 'All systems normal' : 'Degraded' }}"
            aria-label="System health: {{ $status }}"
        ></span>
    @endif
</div>
