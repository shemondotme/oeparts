<x-filament-panels::page>
    @php($summary = $this->getFailedJobsSummary())
    @if ($summary['total'] > 0)
        <div
            class="flex flex-wrap items-baseline gap-6 pb-2 text-sm"
            style="color: var(--color-text-muted); font-variant-numeric: tabular-nums;"
        >
            <span><strong style="color: var(--color-text-primary);">{{ $summary['total'] }}</strong> failed</span>
            <span><strong style="color: var(--color-text-primary);">{{ $summary['distinctJobClasses'] }}</strong> distinct job{{ $summary['distinctJobClasses'] === 1 ? '' : 's' }}</span>
            @if ($summary['oldestAgo'])
                <span>oldest <strong style="color: var(--color-text-primary);">{{ $summary['oldestAgo'] }}</strong> ago</span>
            @endif
            @if ($summary['retryableCount'] > 0)
                <span><strong style="color: var(--color-text-primary);">{{ $summary['retryableCount'] }}</strong> likely retryable</span>
            @endif
        </div>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
