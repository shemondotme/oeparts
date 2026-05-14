@props([
    'title',
    'description' => null,
    'icon' => 'heroicon-o-inbox',
])

<div {{ $attributes->merge(['class' => 'px-5 py-12 text-center']) }}>
    <x-dynamic-component :component="$icon" class="mx-auto h-10 w-10 text-ink-muted" />
    <p class="mt-3 font-display text-lg font-bold text-ink">{{ $title }}</p>
    @if($description)
        <p class="mt-1 text-sm text-ink-muted">{{ $description }}</p>
    @endif
    @isset($actions)
        <div class="mt-5 flex justify-center gap-3">{{ $actions }}</div>
    @endisset
</div>
