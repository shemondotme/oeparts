{{-- Icon Wrapper Component
     Props:
       variant (string) — 'gradient', 'solid', 'outline' (default: 'gradient')
       size    (string) — 'sm', 'md', 'lg' (default: 'md')
--}}
@props([
    'variant' => 'gradient',
    'size' => 'md',
])

@php
$sizes = [
    'sm' => 'w-8 h-8',
    'md' => 'w-12 h-12',
    'lg' => 'w-16 h-16',
];

$variants = [
    'gradient' => 'bg-gradient-to-br from-amber-100 to-orange-100 text-amber-600',
    'solid' => 'bg-gradient-to-br from-amber-500 to-orange-500 text-white',
    'outline' => 'border-2 border-amber-500 text-amber-500 bg-transparent',
];

$iconSizes = [
    'sm' => 'w-4 h-4',
    'md' => 'w-6 h-6',
    'lg' => 'w-8 h-8',
];

$wrapperClass = $sizes[$size] ?? $sizes['md'];
$variantClass = $variants[$variant] ?? $variants['gradient'];
$iconClass = $iconSizes[$size] ?? $iconSizes['md'];
@endphp

<div class="{{ $wrapperClass }} {{ $variantClass }} rounded-xl flex items-center justify-center flex-shrink-0">
    <div class="{{ $iconClass }}">
        {{ $slot }}
    </div>
</div>
