@props([
    'status' => 'neutral',
])

@php
    $classes = match ($status instanceof \BackedEnum ? $status->value : $status) {
        'active', 'published', 'paid', 'delivered', 'completed', 'success' => 'border-emerald-600/30 bg-emerald-50 text-emerald-700',
        'pending', 'scheduled', 'processing', 'warning' => 'border-amber/40 bg-amber/10 text-amber-ink',
        'inactive', 'draft', 'cancelled', 'failed', 'danger' => 'border-red-600/30 bg-red-50 text-red-700',
        default => 'border-rule bg-ivory-alt text-ink-muted',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-[0.16em] {$classes}"]) }}>
    {{ $slot->isEmpty() ? ucwords(str_replace('_', ' ', $status instanceof \BackedEnum ? $status->value : $status)) : $slot }}
</span>
