@props([
    'variant' => 'primary',   // primary | secondary | outline | ghost | danger
    'size'    => 'md',        // sm | md | lg
    'type'    => 'button',
    'href'    => null,
    'loading' => false,
])

@php
$base = 'inline-flex items-center justify-center gap-2 font-semibold rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed';

$variants = [
    'primary'   => 'bg-navy text-white hover:bg-navy/90 focus:ring-navy',
    'secondary' => 'bg-amber text-navy hover:bg-amber/90 focus:ring-amber',
    'outline'   => 'border border-navy text-navy hover:bg-navy/5 focus:ring-navy',
    'ghost'     => 'text-body hover:bg-slate-100 focus:ring-slate-300',
    'danger'    => 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-600',
];

$sizes = [
    'sm' => 'px-3 py-1.5 text-xs',
    'md' => 'px-4 py-2.5 text-sm',
    'lg' => 'px-6 py-3 text-base',
];

$classes = implode(' ', [$base, $variants[$variant] ?? $variants['primary'], $sizes[$size] ?? $sizes['md']]);
@endphp

@if($href)
<a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
    @if($loading)
    <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
    </svg>
    @endif
    {{ $slot }}
</a>
@else
<button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
    @if($loading)
    <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
    </svg>
    @endif
    {{ $slot }}
</button>
@endif
