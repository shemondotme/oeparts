@props([
    'status' => 'neutral',
])

@php
    $classes = match ($status instanceof \BackedEnum ? $status->value : $status) {
        'active', 'published', 'paid', 'delivered', 'completed', 'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'pending', 'scheduled', 'processing', 'warning' => 'border-amber-200 bg-amber/10 text-amber-text',
        'inactive', 'draft', 'cancelled', 'failed', 'danger' => 'border-red-200 bg-red-50 text-red-800',
        default => 'border-slate-200 bg-slate-50 text-slate-600',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wide {$classes}"]) }}>
    {{ $slot->isEmpty() ? ucwords(str_replace('_', ' ', $status instanceof \BackedEnum ? $status->value : $status)) : $slot }}
</span>
