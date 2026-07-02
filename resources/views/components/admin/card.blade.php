@props([
    'title' => null,
    'eyebrow' => null,
])

<section {{ $attributes->merge(['class' => 'rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900 overflow-hidden']) }}>
    @if($title || $eyebrow || isset($actions))
        <header class="flex items-center justify-between gap-4 px-5 pt-4 pb-3">
            <div>
                @if($eyebrow)
                    <p class="text-[0.75rem] font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">{{ $eyebrow }}</p>
                @endif
                @if($title)
                    <h2 class="mt-1 text-lg font-bold tracking-tight text-zinc-900 dark:text-zinc-100">
                        {{ $title }}
                    </h2>
                @endif
            </div>
            @isset($actions)
                <div class="flex items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div class="p-5">
        {{ $slot }}
    </div>
</section>
