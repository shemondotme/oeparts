@props([
    'type' => 'button',
    'href' => null,
])

@php
$classes = 'inline-flex items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors duration-150 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 bg-indigo-600 hover:bg-indigo-700 focus-visible:outline-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400';
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
