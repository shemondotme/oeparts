@props([
    'title' => null,
    'eyebrow' => null,
])

<section {{ $attributes->merge(['class' => 'bp-card overflow-hidden']) }}>
    @if($title || $eyebrow || isset($actions))
        <header class="bp-card-header flex items-center justify-between gap-4">
            <div>
                @if($eyebrow)
                    <p class="bp-spec text-amber-ink">{{ $eyebrow }}</p>
                @endif
                @if($title)
                    <h2 class="mt-1 font-display text-lg font-bold tracking-[-0.02em] text-ink">
                        {{ $title }}<span class="text-amber">.</span>
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
