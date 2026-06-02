@props([
    'title',
    'value',
    'change' => null,
    'subtitle' => null,
    'icon' => 'heroicon-o-chart-bar',
])

<section {{ $attributes->merge(['class' => 'bp-card overflow-hidden']) }}>
    <header class="bp-card-header flex items-center justify-between gap-3">
        <p class="bp-spec text-slate-500">{{ $title }}</p>
        <div class="rounded-lg border border-slate-200/80 bg-gradient-to-br from-white to-slate-50 p-2 shrink-0 shadow-sm">
            <x-dynamic-component :component="$icon" class="h-4 w-4 text-slate-400" />
        </div>
    </header>
    <div class="p-5">
        <div class="flex items-end justify-between gap-4">
            <p class="font-mono text-3xl font-bold tabular-nums text-slate-900 tracking-tight">{{ $value }}</p>
            @if($change)
                <span class="inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider {{ str_starts_with((string) $change, '-') ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700' }}">
                    {{ $change }}
                </span>
            @endif
        </div>
        @if($subtitle)
            <p class="mt-3 text-sm text-slate-500">{{ $subtitle }}</p>
        @endif
    </div>
</section>
