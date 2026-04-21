{{-- Star Rating Component
     Props:
       rating (int)   — number of filled stars (default: 5)
       max    (int)   — total stars (default: 5)
       size   (string)— 'sm', 'md', 'lg' (default: 'sm')
--}}
@props([
    'rating' => 5,
    'max' => 5,
    'size' => 'sm',
])

@php
$sizeClasses = [
    'sm' => 'w-3.5 h-3.5',
    'md' => 'w-4 h-4',
    'lg' => 'w-5 h-5',
];
$starSize = $sizeClasses[$size] ?? $sizeClasses['sm'];
@endphp

<div class="flex items-center gap-0.5">
    @for($i = 1; $i <= $max; $i++)
        @if($i <= $rating)
            <x-heroicon-s-star class="{{ $starSize }} text-amber fill-amber" />
        @else
            <x-heroicon-o-star class="{{ $starSize }} text-gray-300" />
        @endif
    @endfor
</div>
