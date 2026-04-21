{{-- Section: faqs
     content: headline(ml)
     $sectionData['faqs'] is injected by SectionRendererService.
     Alpine.js accordion — each item opens independently.
--}}
@php $faqs = $sectionData['faqs'] ?? collect(); @endphp

@if($faqs->isNotEmpty())
<section class="bg-gradient-to-b from-blue-50/30 via-white to-blue-50/30 py-14 md:py-20 px-4 relative overflow-hidden">

    {{-- Decorative background --}}
    <div class="absolute inset-0 opacity-20" aria-hidden="true">
        <div class="absolute bottom-0 right-0 w-96 h-96 bg-amber/10 rounded-full filter blur-3xl"></div>
        <div class="absolute top-0 left-0 w-72 h-72 bg-blue-500/10 rounded-full filter blur-3xl"></div>
    </div>

    <div class="max-w-4xl mx-auto relative z-10">

        <x-section-heading
            :eyebrow="trans_field($section->content['eyebrow'] ?? null)"
            :headline="trans_field($section->content['headline'] ?? null)"
            :subheadline="trans_field($section->content['subheadline'] ?? null)"
            :accentBar="true"
            class="mb-12"
        />

        <dl class="space-y-4">
            @foreach($faqs as $index => $faq)
            @php
                $delay = $index * 100;
            @endphp

            <div
                x-data="{ open: false }"
                x-init="
                    const observer = new IntersectionObserver(
                        (entries) => {
                            entries.forEach(entry => {
                                if (entry.isIntersecting) {
                                    setTimeout(() => $refs.item.style.opacity = '1', {{ $delay }});
                                    observer.unobserve(entry.target);
                                }
                            });
                        },
                        { threshold: 0.2 }
                    );
                    observer.observe($el);
                "
                x-ref="item"
                class="opacity-0 transform translate-y-4 transition-all duration-500 ease-out motion-reduce:transition-none motion-reduce:transform-none"
            >
                <div
                    class="group relative bg-gradient-to-br from-white via-gray-50/50 to-white
                           rounded-2xl border-2 border-gray-100
                           shadow-md shadow-amber/5 hover:shadow-xl hover:shadow-amber/10
                           transition-all duration-300
                           hover:border-amber/30"
                >
                    <dt>
                        <button
                            @click="open = !open"
                            class="w-full flex items-center justify-between px-6 py-5 text-left
                                   group-hover:bg-gradient-to-r group-hover:from-amber/5 group-hover:to-transparent
                                   rounded-2xl transition-all duration-300"
                            :aria-expanded="open"
                            :aria-controls="'faq-answer-{{ $index }}'"
                        >
                            <div class="flex items-center gap-4">
                                {{-- Question number badge with improved design --}}
                                <div class="w-9 h-9 rounded-xl
                                          bg-gradient-to-br from-amber/20 to-orange-50/20
                                          flex items-center justify-center
                                          group-hover:from-amber group-hover:to-orange-500
                                          transition-all duration-300
                                          border border-amber/10 group-hover:border-transparent">
                                    <span class="text-sm font-bold text-amber group-hover:text-white transition-colors">
                                        {{ $loop->iteration }}
                                    </span>
                                </div>

                                <span class="font-display font-semibold text-navy text-base
                                           group-hover:text-amber-text transition-colors duration-300">
                                    {{ trans_field($faq->question) }}
                                </span>
                            </div>

                            {{-- Icon with improved circular background --}}
                            <div class="w-11 h-11 rounded-xl
                                      bg-gray-50 group-hover:bg-amber
                                      flex items-center justify-center
                                      transition-all duration-300
                                      border border-gray-100 group-hover:border-transparent">
                                <x-heroicon-o-chevron-down
                                    class="w-5 h-5 text-gray-400 group-hover:text-white
                                           transform transition-transform duration-300 ease-out"
                                    ::class="{ 'rotate-180': open }"
                                />
                            </div>
                        </button>
                    </dt>

                    {{-- Answer with smooth reveal --}}
                    <dd
                        x-show="open"
                        x-collapse
                        :id="'faq-answer-{{ $index }}'"
                        role="region"
                        class="px-6 pb-5 pt-2"
                    >
                        <div class="ml-12 pl-4 border-l-4 border-amber/40">
                            <p class="text-body text-base leading-relaxed">
                                {{ trans_field($faq->answer) }}
                            </p>
                        </div>
                    </dd>
                </div>
            </div>
            @endforeach
        </dl>

        {{-- JSON-LD FAQPage --}}
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "FAQPage",
            "mainEntity": [
                @foreach($faqs as $i => $faq)
                {
                    "@type": "Question",
                    "name": {{ json_encode(trans_field($faq->question)) }},
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": {{ json_encode(trans_field($faq->answer)) }}
                    }
                }{{ !$loop->last ? ',' : '' }}
                @endforeach
            ]
        }
        </script>

    </div>
</section>
@endif
