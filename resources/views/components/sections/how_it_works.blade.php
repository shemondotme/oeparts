{{-- Section: how_it_works
     content: eyebrow, headline, subheadline,
              steps[] — each: icon, step_number, title(ml), description(ml)
     Design mockup hardcoded values:
     - eyebrow: "PROCESS"
     - headline: "How It Works"
     - subheadline: "Three simple steps to get the right part at the right price."
--}}
@php $steps = $section->content['steps'] ?? []; @endphp

@if(!empty($steps))
<section class="bg-white py-14 md:py-20 px-4 relative overflow-hidden">

    {{-- Decorative background elements --}}
    <div class="absolute inset-0 opacity-5">
        <div class="absolute top-10 right-10 w-96 h-96 bg-amber rounded-full filter blur-3xl"></div>
        <div class="absolute bottom-10 left-10 w-96 h-96 bg-blue-500 rounded-full filter blur-3xl"></div>
    </div>

    <div class="max-w-6xl mx-auto relative z-10">

        <x-section-heading
            :eyebrow="trans_field($section->content['eyebrow'] ?? null)"
            :headline="trans_field($section->content['headline'] ?? null)"
            :subheadline="trans_field($section->content['subheadline'] ?? null)"
            :accentBar="true"
            class="mb-12"
        />

        {{-- Steps Container --}}
        <div class="relative">

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 md:gap-8">
                @foreach($steps as $index => $step)
                @php
                    $delay = $index * 200;
                @endphp

                <div
                    class="h-full"
                    x-data="{ shown: false }"
                    x-init="
                        const observer = new IntersectionObserver(
                            (entries) => {
                                entries.forEach(entry => {
                                    if (entry.isIntersecting) {
                                        setTimeout(() => shown = true, {{ $delay }});
                                        observer.unobserve(entry.target);
                                    }
                                });
                            },
                            { threshold: 0.2 }
                        );
                        observer.observe($el);
                    "
                >
                    {{-- Card — fills parent height via grid stretch --}}
                    <div
                        class="group relative bg-white rounded-3xl p-6 pt-14 md:p-10 md:pt-16 h-full
                               border border-gray-100
                               shadow-md hover:shadow-xl
                               transform transition-all duration-500 ease-out
                               hover:-translate-y-1
                               :class='shown ? \"opacity-100 translate-y-0\" : \"opacity-0 translate-y-8\"'"
                    >

                        {{-- Step number badge — clean design, no pulsing ring --}}
                        <div class="absolute -top-6 left-1/2 -translate-x-1/2">
                            <div
                                class="relative w-16 h-16 rounded-full
                                       bg-gradient-to-br from-amber to-orange-500
                                       flex items-center justify-center
                                       shadow-lg shadow-amber/30
                                       group-hover:scale-110
                                       transition-all duration-300"
                            >
                                <span class="font-display text-2xl font-bold text-white">
                                    {{ $step['step_number'] ?? $loop->iteration }}
                                </span>
                            </div>
                        </div>

                        {{-- Content Container — Centered --}}
                        <div class="flex flex-col items-center text-center pt-4">

                            {{-- Step icon — flat bg, subtle border --}}
                            @if(!empty($step['icon']))
                            <div class="mb-6 relative">
                                <div class="relative w-16 h-16 rounded-2xl
                                            bg-amber/5
                                            flex items-center justify-center
                                            group-hover:scale-105
                                            transition-all duration-300
                                            border border-amber/20">
                                    @switch($step['icon'])
                                        @case('magnifying-glass')
                                            <x-heroicon-o-magnifying-glass class="w-8 h-8 text-amber" />
                                            @break
                                        @case('shopping-cart')
                                            <x-heroicon-o-shopping-cart class="w-8 h-8 text-amber" />
                                            @break
                                        @case('cube')
                                            <x-heroicon-o-cube class="w-8 h-8 text-amber" />
                                            @break
                                        @case('clipboard-document-list')
                                            <x-heroicon-o-clipboard-document-list class="w-8 h-8 text-amber" />
                                            @break
                                        @case('truck')
                                            <x-heroicon-o-truck class="w-8 h-8 text-amber" />
                                            @break
                                        @case('currency-dollar')
                                            <x-heroicon-o-currency-dollar class="w-8 h-8 text-amber" />
                                            @break
                                        @case('shield-check')
                                            <x-heroicon-o-shield-check class="w-8 h-8 text-amber" />
                                            @break
                                        @default
                                            <x-heroicon-o-check-circle class="w-8 h-8 text-amber" />
                                    @endswitch
                                </div>
                            </div>
                            @endif

                            {{-- Title — no hover color change --}}
                            @if(!empty($step['title']))
                            <h3 class="font-display text-xl font-bold text-navy mb-3 text-balance">
                                {{ trans_field($step['title']) }}
                            </h3>
                            @endif

                            {{-- Description — lighter, more subdued, max 3 lines --}}
                            @if(!empty($step['description']))
                            <p class="text-muted/70 text-sm leading-relaxed text-center max-w-xs text-balance line-clamp-3">
                                {{ trans_field($step['description']) }}
                            </p>
                            @endif

                        </div>
                    </div>

                </div>
                @endforeach
            </div>

        </div>

    </div>
</section>
@endif
