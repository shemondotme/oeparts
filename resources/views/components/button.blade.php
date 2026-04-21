{{-- Button Component
     Props:
       variant (string) — 'primary', 'secondary', 'outline', 'ghost' (default: 'primary')
       type    (string) — button type (default: 'button')
       href    (string) — if provided, renders as <a> tag
       size    (string) — 'sm', 'md', 'lg' (default: 'md')
--}}
@props([
    'variant' => 'primary',
    'type' => 'button',
    'href' => null,
    'size' => 'md',
])

@php
$variants = [
    'primary' => 'group relative bg-gradient-to-r from-amber to-orange-500 hover:from-amber/95 hover:to-orange-500/95 text-white shadow-lg shadow-amber/30 hover:shadow-xl hover:shadow-amber/40',
    'secondary' => 'group relative bg-gradient-to-r from-navy to-navy/90 hover:from-navy/90 hover:to-navy text-white shadow-lg shadow-navy/30 hover:shadow-xl',
    'outline' => 'bg-transparent border-2 border-amber text-amber hover:bg-amber/5 hover:border-amber/80',
    'ghost' => 'bg-transparent hover:bg-gray-100 text-gray-700',
];

$sizes = [
    'sm' => 'px-4 py-2 text-sm',
    'md' => 'px-6 py-3 text-sm',
    'lg' => 'px-8 py-4 text-base',
];

$baseClasses = 'inline-flex items-center justify-center gap-2 rounded-2xl font-semibold transition-all duration-300 transform hover:scale-105 relative overflow-hidden';
$variantClass = $variants[$variant] ?? $variants['primary'];
$sizeClass = $sizes[$size] ?? $sizes['md'];
@endphp

@if($href)
    <a href="{{ $href }}" type="{{ $type }}" {{ $attributes->merge(['class' => $baseClasses . ' ' . $variantClass . ' ' . $sizeClass]) }}>
        {{-- Shimmer effect for primary/secondary buttons --}}
        @if(in_array($variant, ['primary', 'secondary']))
        <span class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-700"></span>
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $baseClasses . ' ' . $variantClass . ' ' . $sizeClass]) }}>
        {{-- Shimmer effect for primary/secondary buttons --}}
        @if(in_array($variant, ['primary', 'secondary']))
        <span class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-700"></span>
        @endif
        {{ $slot }}
    </button>
@endif
