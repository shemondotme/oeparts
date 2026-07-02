@props([
    'status' => 'neutral',
])

@php
    $key = $status instanceof \BackedEnum ? $status->value : $status;

    $classes = match ($key) {
        'active', 'published', 'paid', 'delivered', 'completed', 'success'
            => 'bg-emerald-50 text-emerald-800 border-emerald-200 dark:bg-emerald-950 dark:text-emerald-300 dark:border-emerald-800',
        'pending', 'scheduled', 'processing', 'warning'
            => 'bg-amber-50 text-amber-800 border-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:border-amber-800',
        'inactive', 'draft', 'cancelled', 'failed', 'danger'
            => 'bg-rose-50 text-rose-800 border-rose-200 dark:bg-rose-950 dark:text-rose-300 dark:border-rose-800',
        default
            => 'bg-zinc-100 text-zinc-600 border-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:border-zinc-700',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wide {$classes}"]) }}>
    {{ $slot->isEmpty() ? ucwords(str_replace('_', ' ', $key)) : $slot }}
</span>
