@props([
    'color' => 'gray',  // gray | blue | green | amber | red | purple | teal | navy
])

@php
$colors = [
    'gray'   => 'bg-slate-100 text-slate-600',
    'blue'   => 'bg-blue-100 text-blue-700',
    'green'  => 'bg-green-100 text-green-700',
    'amber'  => 'bg-amber/20 text-amber-text',
    'red'    => 'bg-red-100 text-red-700',
    'purple' => 'bg-purple-100 text-purple-700',
    'teal'   => 'bg-teal-100 text-teal-700',
    'navy'   => 'bg-navy/10 text-navy',
];
$classes = 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ' . ($colors[$color] ?? $colors['gray']);
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</span>
