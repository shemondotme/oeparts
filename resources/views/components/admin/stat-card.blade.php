@props([
    'title',
    'value',
    'change' => null,
    'subtitle' => null,
    'icon' => 'heroicon-o-chart-bar',
])

<section {{ $attributes->merge(['class' => 'bp-card overflow-hidden']) }}>
    <header class="bp-card-header flex items-center justify-between gap-3">
        <p class="bp-spec" style="color: var(--color-text-muted);">{{ $title }}</p>
        <div style="border-radius: var(--radius-lg); border: 1px solid var(--color-border-default); background: var(--color-bg-surface); padding: 0.5rem; flex-shrink: 0;">
            <x-dynamic-component :component="$icon" style="height: 1rem; width: 1rem; color: var(--color-text-muted);" />
        </div>
    </header>
    <div class="p-5">
        <div class="flex items-end justify-between gap-4">
            <p class="font-mono text-3xl font-bold tabular-nums tracking-tight" style="color: var(--color-text-primary);">{{ $value }}</p>
            @if($change)
                <span class="inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider"
                    style="{{ str_starts_with((string) $change, '-') ? 'background: var(--color-danger-50, #fef2f2); color: var(--color-danger-700); border-color: var(--color-danger-200, #fecaca);' : 'background: var(--color-success-50, #f0fdf4); color: var(--color-success-700); border-color: var(--color-success-200, #bbf7d0);' }}">
                    {{ $change }}
                </span>
            @endif
        </div>
        @if($subtitle)
            <p class="mt-3 text-sm" style="color: var(--color-text-muted);">{{ $subtitle }}</p>
        @endif
    </div>
</section>
