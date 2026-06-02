@props([
    'title',
    'description' => null,
    'eyebrow' => null,
])

<div {{ $attributes->merge(['class' => 'mb-6']) }}>
    @if($eyebrow)
        <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-brand-600">{{ $eyebrow }}</p>
    @endif
    <h2 class="mt-1.5 font-display text-xl font-bold tracking-tight text-slate-900">
        {{ $title }}
    </h2>
    @if($description)
        <p class="mt-2 text-admin-sm leading-relaxed text-slate-600">{{ $description }}</p>
    @endif
</div>
