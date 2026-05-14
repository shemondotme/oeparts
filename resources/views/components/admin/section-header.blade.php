@props([
    'title',
    'description' => null,
    'eyebrow' => null,
])

<div {{ $attributes->merge(['class' => 'mb-6']) }}>
    @if($eyebrow)
        <p class="bp-spec text-amber-ink">{{ $eyebrow }}</p>
    @endif
    <h2 class="mt-1 font-display text-xl font-bold tracking-[-0.02em] text-ink">
        {{ $title }}<span class="text-amber">.</span>
    </h2>
    @if($description)
        <p class="mt-2 text-sm text-ink-muted">{{ $description }}</p>
    @endif
</div>