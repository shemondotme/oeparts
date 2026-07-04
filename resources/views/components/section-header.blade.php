{{-- Industrial Blueprint section header (storefront).
     Consolidates the repeated eyebrow + headline + subheadline/meta header used by
     the home content sections. All strings must be PRE-TRANSLATED by the caller
     (via trans_field()). This is the on-brand header — do NOT confuse with the
     stale, off-brand `section-heading.blade.php` (pill/gradient) which is unused here.

     Props:
       eyebrow     (string|null) — short ALL-CAPS label above the headline
       headline    (string|null) — main h2 (an amber "." accent is appended)
       subheadline (string|null) — descriptive sentence
       meta        (string|null) — mono spec line (e.g. "Index · 24 manufacturers")
       variant     (string)      — 'split' (default 7/5 grid) | 'stacked' (left) | 'center'
--}}
@props([
    'eyebrow'     => null,
    'headline'    => null,
    'subheadline' => null,
    'meta'        => null,
    'variant'     => 'split',
])

@php
    $isCenter  = $variant === 'center';
    $isStacked = $variant === 'stacked';
@endphp

{{-- Reusable pieces --}}
@php
    $eyebrowBlock = $eyebrow;
    $headlineMax  = $isCenter ? 'max-w-[22ch] mx-auto' : 'max-w-[20ch]';
@endphp

@if($variant === 'split')
<div {{ $attributes->merge(['class' => 'grid grid-cols-12 gap-x-4 sm:gap-x-6 lg:gap-x-8 items-end pb-8 mb-12 border-b border-ink']) }}>
    <div class="col-span-12 md:col-span-7">
        @if($eyebrow)
        <div class="flex items-center gap-4 mb-6">
            <span class="w-10 h-[3px] bg-amber inline-block"></span>
            <span class="bp-spec text-amber-ink">{{ $eyebrow }}</span>
        </div>
        @endif
        @if($headline)
        <h2 class="font-display font-extrabold text-ink leading-[0.95] tracking-[-0.03em] text-4xl sm:text-5xl lg:text-6xl {{ $headlineMax }}">
            {{ $headline }}<span class="text-amber">.</span>
        </h2>
        @endif
    </div>
    @if($subheadline || $meta)
    <div class="col-span-12 md:col-span-5 mt-6 md:mt-0 md:pl-8 md:border-l md:border-rule">
        @if($subheadline)
        <p class="text-base text-body leading-relaxed">{{ $subheadline }}</p>
        @endif
        @if($meta)
        <p class="mt-4 bp-spec-mono">{{ $meta }}</p>
        @endif
    </div>
    @endif
</div>
@endif

@if($isStacked)
<div {{ $attributes->merge(['class' => 'pb-8 mb-12 border-b border-ink']) }}>
    @if($eyebrow)
    <div class="flex items-center gap-4 mb-6">
        <span class="w-10 h-[3px] bg-amber inline-block"></span>
        <span class="bp-spec text-amber-ink">{{ $eyebrow }}</span>
    </div>
    @endif
    @if($headline)
    <h2 class="font-display font-extrabold text-ink leading-[0.95] tracking-[-0.03em] text-4xl sm:text-5xl lg:text-6xl max-w-[24ch]">
        {{ $headline }}<span class="text-amber">.</span>
    </h2>
    @endif
    @if($subheadline)
    <p class="mt-6 text-base text-body leading-relaxed max-w-2xl">{{ $subheadline }}</p>
    @endif
    @if($meta)
    <p class="mt-4 bp-spec-mono">{{ $meta }}</p>
    @endif
</div>
@endif

@if($isCenter)
<div {{ $attributes->merge(['class' => 'pb-8 mb-12 border-b border-ink text-center']) }}>
    @if($eyebrow)
    <div class="flex items-center justify-center gap-4 mb-6">
        <span class="w-10 h-[3px] bg-amber inline-block"></span>
        <span class="bp-spec text-amber-ink">{{ $eyebrow }}</span>
        <span class="w-10 h-[3px] bg-amber inline-block"></span>
    </div>
    @endif
    @if($headline)
    <h2 class="font-display font-extrabold text-ink leading-[0.95] tracking-[-0.03em] text-4xl sm:text-5xl lg:text-6xl mx-auto max-w-[22ch]">
        {{ $headline }}<span class="text-amber">.</span>
    </h2>
    @endif
    @if($subheadline)
    <p class="mt-6 text-base text-body leading-relaxed mx-auto max-w-2xl">{{ $subheadline }}</p>
    @endif
    @if($meta)
    <p class="mt-4 bp-spec-mono">{{ $meta }}</p>
    @endif
</div>
@endif
