@props([
    'title',
    'description' => null,
    'icon'        => 'heroicon-o-inbox',
    'iconColor'   => 'text-slate-400',
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-14 text-center']) }}>
    {{-- Icon box --}}
    <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl border border-slate-200/80 bg-gradient-to-b from-white to-slate-50/90 shadow-sm shadow-slate-900/[0.04] ring-1 ring-slate-900/[0.03]">
        <x-dynamic-component :component="$icon" class="h-7 w-7 {{ $iconColor }}" />
    </div>

    <p class="font-display text-base font-bold tracking-tight text-slate-900">{{ $title }}</p>

    @if($description)
        <p class="mt-1.5 max-w-xs text-sm leading-relaxed text-slate-500">{{ $description }}</p>
    @endif

    {{-- Primary actions slot --}}
    @isset($actions)
        <div class="mt-5 flex flex-wrap items-center justify-center gap-2.5">
            {{ $actions }}
        </div>
    @endisset

    {{-- Secondary / help text slot --}}
    @isset($secondary)
        <div class="mt-3 text-xs text-slate-400">
            {{ $secondary }}
        </div>
    @endisset
</div>
