{{-- Section: testimonials
     content: headline(ml)
     $sectionData['testimonials'] is injected by SectionRendererService
--}}
@php $testimonials = $sectionData['testimonials'] ?? collect(); @endphp

@if($testimonials->isNotEmpty())
<section class="bg-gradient-to-b from-amber-50/30 via-white to-amber-50/30 py-14 md:py-20 px-4 relative overflow-hidden">

    {{-- Decorative background elements --}}
    <div class="absolute inset-0 opacity-20 pointer-events-none">
        <div class="absolute top-40 left-10 w-96 h-96 bg-amber/15 rounded-full filter blur-3xl"></div>
        <div class="absolute bottom-40 right-10 w-96 h-96 bg-blue-500/15 rounded-full filter blur-3xl"></div>
    </div>

    <div class="max-w-6xl mx-auto relative z-10">

        <x-section-heading
            :eyebrow="trans_field($section->content['eyebrow'] ?? null)"
            :headline="trans_field($section->content['headline'] ?? null)"
            :subheadline="trans_field($section->content['subheadline'] ?? null)"
            :accentBar="true"
            class="mb-12"
        />

        {{-- Testimonial Cards Grid — Equal height cards via grid auto-stretch --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($testimonials as $index => $testimonial)
            @php
                // Generate initials from name
                $nameParts = explode(' ', trim($testimonial->name));
                $initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));

                // Avatar colors — solid hex colors for consistent display
                $avatarHexColors = [
                    '#F59E0B',  // amber-500
                    '#1E3A8A',  // navy-800
                    '#059669',  // emerald-600
                    '#9333EA',  // purple-600
                    '#E11D48',  // rose-600
                    '#0891B2',  // cyan-600
                ];
                $avatarBgColor = $avatarHexColors[$index % count($avatarHexColors)];

                // Stagger animation delay
                $delay = $index * 150;
            @endphp

            <div
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
                role="article"
                :aria-label="'Testimonial from {{ addslashes($testimonial->name) }}'"
                class="group relative bg-gradient-to-br from-white via-amber-50/30 to-white rounded-3xl p-8 h-full
                       border-2 border-amber/10
                       shadow-lg shadow-amber/5 hover:shadow-2xl hover:shadow-amber/20
                       transform transition-all duration-500 ease-out motion-reduce:transition-none motion-reduce:transform-none
                       hover:-translate-y-2
                       :class='shown ? \"opacity-100 translate-y-0 scale-100\" : \"opacity-0 translate-y-8 scale-95\"'"
            >
                {{-- Gradient border glow on hover --}}
                <div class="absolute inset-0 rounded-3xl opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none
                            bg-gradient-to-br from-amber/20 via-transparent to-transparent"></div>

                {{-- Rating Badge (Top Left) with improved design --}}
                <div class="absolute top-6 left-6 z-20">
                    <div class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white/95 backdrop-blur-sm rounded-xl shadow-md shadow-amber/10 border border-amber/20">
                        <x-star-rating :rating="$testimonial->rating" size="sm" />
                    </div>
                </div>

                {{-- Quote Icon (Top Right) --}}
                <div class="absolute top-6 right-6 opacity-10 group-hover:opacity-20 transition-opacity duration-500">
                    <x-heroicon-s-chat-bubble-left-right class="w-16 h-16 text-amber" />
                </div>

                {{-- Content --}}
                <div class="relative z-10 pt-10">

                    {{-- Quote Text with decorative quotes --}}
                    <div class="relative mb-8">
                        {{-- Opening quote --}}
                        <span class="absolute -top-4 -left-3 text-7xl text-amber/10 font-serif leading-none select-none">"</span>

                        <p class="text-body text-base leading-relaxed pt-6 pb-4 relative z-10 line-clamp-4">
                            {{ trans_field($testimonial->quote) }}
                        </p>

                        {{-- Closing quote --}}
                        <span class="absolute -bottom-6 -right-3 text-7xl text-amber/10 font-serif leading-none select-none rotate-180">"</span>
                    </div>

                    {{-- Divider --}}
                    <div class="relative mb-8">
                        <div class="h-px w-full bg-gradient-to-r from-amber/50 via-amber/30 to-transparent"></div>
                        <div class="absolute top-1/2 -translate-y-1/2 left-0 flex gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber"></span>
                            <span class="w-1.5 h-1.5 rounded-full bg-amber/60"></span>
                            <span class="w-1.5 h-1.5 rounded-full bg-amber/30"></span>
                        </div>
                    </div>

                    {{-- Author Info --}}
                    <div class="flex items-center gap-4">
                        {{-- Avatar - Solid color background using CSS variables --}}
                        <div class="relative">
                            {{-- Avatar circle --}}
                            <div class="relative w-14 h-14 rounded-full flex items-center justify-center shadow-lg border-2 border-white text-white font-bold text-lg tracking-wider"
                                 style="--avatar-bg: {{ $avatarBgColor }}; background-color: var(--avatar-bg);">
                                <span>{{ $initials }}</span>
                            </div>
                        </div>

                        {{-- Name & Details --}}
                        <div class="flex-1 min-w-0">
                            <p class="font-display font-bold text-navy text-sm truncate">
                                {{ $testimonial->name }}
                            </p>
                            @if($testimonial->company || $testimonial->location)
                            <p class="text-muted text-xs mt-0.5 truncate">
                                {{ implode(', ', array_filter([$testimonial->company, $testimonial->location])) }}
                            </p>
                            @endif
                        </div>

                        {{-- Verified Badge - More prominent --}}
                        <div class="shrink-0" title="Verified Customer">
                            <div class="relative">
                                <div class="absolute inset-0 bg-emerald-500/20 rounded-full blur-md"></div>
                                <x-heroicon-s-check-circle class="relative w-6 h-6 text-emerald-500 drop-shadow-sm" />
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            @endforeach
        </div>

    </div>
</section>
@endif
