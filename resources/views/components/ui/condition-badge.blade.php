{{--
    Condition badge — maps a product condition slug to its design-token color pair.

    Usage:
        <x-ui.condition-badge condition="new" />
        <x-ui.condition-badge condition="used_grade_a" />
        <x-ui.condition-badge condition="{{ $product->condition }}" />

    Supported condition values (aligned with tailwind.config.js tokens):
        new | used_grade_a | used_grade_b | used_grade_c
        remanufactured | aftermarket | new_old_stock

    Optional slot — override the display label:
        <x-ui.condition-badge condition="new">Brand New</x-ui.condition-badge>
--}}
@props(['condition'])

@php
    $map = [
        'new'            => 'bg-condition-new-bg text-condition-new-text',
        'used_grade_a'   => 'bg-condition-used-a-bg text-condition-used-a-text',
        'used_grade_b'   => 'bg-condition-used-b-bg text-condition-used-b-text',
        'used_grade_c'   => 'bg-condition-used-c-bg text-condition-used-c-text',
        'remanufactured' => 'bg-condition-remanufactured-bg text-condition-remanufactured-text',
        'aftermarket'    => 'bg-condition-aftermarket-bg text-condition-aftermarket-text',
        'new_old_stock'  => 'bg-condition-nos-bg text-condition-nos-text',
    ];

    $cls = $map[$condition] ?? 'bg-slate-100 text-slate-600';

    // Default label: replace underscores and "grade " for readability
    $defaultLabel = ucwords(str_replace(['_', 'grade '], [' ', ''], $condition));
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wide $cls"]) }}>
    {{ $slot->isEmpty() ? $defaultLabel : $slot }}
</span>
