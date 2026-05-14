@props([
    'type' => 'button',
    'href' => null,
])

@if($href)
    <a href="{{ $href }}"
       {{ $attributes->merge(['class' => 'bp-btn-primary']) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}"
            {{ $attributes->merge(['class' => 'bp-btn-primary']) }}>
        {{ $slot }}
    </button>
@endif