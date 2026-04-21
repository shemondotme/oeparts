@props(['condition'])

@php
use App\Enums\ProductCondition;

$enum = $condition instanceof ProductCondition
    ? $condition
    : ProductCondition::tryFrom((string) $condition);

$bg    = $enum?->badgeBg()   ?? '#F1F5F9';
$color = $enum?->badgeText() ?? '#64748B';
$label = $enum?->label()     ?? ucfirst(str_replace('_', ' ', (string) $condition));
@endphp

<span
    {{ $attributes->merge(['class' => 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold']) }}
    style="background-color: {{ $bg }}; color: {{ $color }}"
>
    {{ $label }}
</span>
