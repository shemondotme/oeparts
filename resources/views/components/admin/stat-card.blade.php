@props([
    'title',
    'value',
    'change' => null,
    'subtitle' => null,
    'icon' => 'heroicon-o-chart-bar',
])

<section {{ $attributes->merge(['class' => 'bp-card overflow-hidden']) }}>
    <header class="bp-card-header flex items-center justify-between gap-3">
        <p class="bp-spec text-ink-muted">{{ $title }}</p>
        <x-dynamic-component :component="$icon" class="h-5 w-5 text-ink" />
    </header>
    <div class="p-5">
        <div class="flex items-end justify-between gap-4">
            <p class="font-mono text-3xl font-bold tabular-nums text-ink">{{ $value }}</p>
            @if($change)
                <span class="font-mono text-xs {{ str_starts_with((string) $change, '-') ? 'text-red-600' : 'text-emerald-600' }}">
                    {{ $change }}
                </span>
            @endif
        </div>
        @if($subtitle)
            <p class="mt-3 text-sm text-ink-muted">{{ $subtitle }}</p>
        @endif
    </div>
</section>
