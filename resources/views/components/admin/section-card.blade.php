@props([
    'title',
    'description' => null,
    'noPadding'   => false,
    'interactive' => false,
])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900 overflow-hidden' . ($interactive ? ' transition-transform duration-200 hover:-translate-y-0.5 hover:shadow-md' : '')]) }}>
    @if($title || isset($actions))
        <div class="flex items-start justify-between gap-4 px-5 pt-4 pb-3">
            <div class="min-w-0">
                <h2 class="truncate text-[0.9375rem] font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">
                    {{ $title }}
                </h2>
                @if($description)
                    <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $description }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex shrink-0 items-center gap-2">
                    {{ $actions }}
                </div>
            @endisset
        </div>
    @endif

    <div @class(['p-5' => !$noPadding])>
        {{ $slot }}
    </div>
</div>
