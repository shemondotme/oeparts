@props([
    'status' => 'neutral',
])

@php
    $styles = match ($status instanceof \BackedEnum ? $status->value : $status) {
        'active', 'published', 'paid', 'delivered', 'completed', 'success' => 'background: var(--color-success-50, #f0fdf4); color: var(--color-success-800, #166534); border-color: var(--color-success-200, #bbf7d0);',
        'pending', 'scheduled', 'processing', 'warning' => 'background: var(--color-warning-50, #fffbeb); color: var(--color-warning-800, #92400e); border-color: var(--color-warning-200, #fde68a);',
        'inactive', 'draft', 'cancelled', 'failed', 'danger' => 'background: var(--color-danger-50, #fef2f2); color: var(--color-danger-800, #991b1b); border-color: var(--color-danger-200, #fecaca);',
        default => 'background: var(--color-bg-inset); color: var(--color-text-muted); border-color: var(--color-border-default);',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wide", 'style' => $styles]) }}>
    {{ $slot->isEmpty() ? ucwords(str_replace('_', ' ', $status instanceof \BackedEnum ? $status->value : $status)) : $slot }}
</span>
