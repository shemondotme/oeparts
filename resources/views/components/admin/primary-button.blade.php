@props([
    'type' => 'button',
    'href' => null,
])

@if($href)
    <a href="{{ $href }}"
       {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 px-4 py-2 bg-navy border border-transparent rounded-lg text-sm font-medium text-white hover:bg-navy/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber transition-colors']) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}"
            {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 px-4 py-2 bg-navy border border-transparent rounded-lg text-sm font-medium text-white hover:bg-navy/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber transition-colors']) }}>
        {{ $slot }}
    </button>
@endif