{{-- Reusable section heading: eyebrow + h2 + subheadline
     All strings must be pre-translated by the caller via trans_field().

     Props:
       eyebrow     (string|null) — short ALL-CAPS label above h2
       headline    (string|null) — the main h2 text
       subheadline (string|null) — descriptive sentence below
       accentBar   (bool)        — show decorative bar below h2; default true
       align       (string)      — 'center' (default) | 'left'
       dark        (bool)        — use white text for dark backgrounds; default false
--}}
@props([
    'eyebrow'     => null,
    'headline'    => null,
    'subheadline' => null,
    'accentBar'   => true,
    'align'       => 'center',
    'dark'        => false,
])
@php
    $isCenter = $align === 'center';
    $barClass = $isCenter ? 'mx-auto' : '';
    $textAlignClass = $isCenter ? 'text-center' : 'text-left';
@endphp

<div
    {{ $attributes->merge(['class' => $textAlignClass]) }}
    x-data="{ shown: false }"
    x-init="
        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        setTimeout(() => shown = true, 200);
                        observer.unobserve(entry.target);
                    }
                });
            },
            { threshold: 0.2 }
        );
        observer.observe($el);
    "
    class="opacity-0 transform translate-y-4 transition-all duration-700 ease-out"
    :class='shown ? \"opacity-100 translate-y-0\" : \"opacity-0 translate-y-4\"'"
>

    {{-- Eyebrow with Pill Badge (using section-badge class) --}}
    @if($eyebrow)
    <div class="mb-4">
        <span class="section-badge">
            {{-- Decorative dot --}}
            <span class="w-1.5 h-1.5 rounded-full bg-amber animate-pulse"></span>
            {{ $eyebrow }}
            <span class="w-1.5 h-1.5 rounded-full bg-amber/50"></span>
        </span>
    </div>
    @endif

    {{-- Headline with Gradient Text (or solid white for dark backgrounds) --}}
    @if($headline)
    <h2 class="section-heading mb-4 {{ $dark ? 'text-white' : '' }}">
        {{ $headline }}
    </h2>
    @endif

    {{-- Decorative Accent Bar — REMOVED per user request --}}
    {{-- @if($accentBar && $headline)
    <div class="section-accent-bar {{ $barClass }}">
        <div class="section-accent-bar-main"></div>
        <div class="absolute -bottom-2 left-0 right-0 flex justify-center gap-2">
            <span class="w-1 h-1 rounded-full bg-amber/60"></span>
            <span class="w-1 h-1 rounded-full bg-amber/40"></span>
            <span class="w-1 h-1 rounded-full bg-amber/20"></span>
        </div>
    </div>
    @endif --}}

    {{-- Subheadline (using section-subheading class) --}}
    @if($subheadline)
    <p class="section-subheading {{ $barClass }} {{ $dark ? 'text-white/70' : '' }}">
        {{ $subheadline }}
    </p>
    @endif

</div>
