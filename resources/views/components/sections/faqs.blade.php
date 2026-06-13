{{-- Section: faqs (Industrial Blueprint)
     content: eyebrow, headline(ml), subheadline(ml)
     $sectionData['faqs'] injected by SectionRendererService.
--}}
@php
    $faqs = $sectionData['faqs'] ?? collect();
    $eyebrow = trans_field($section->content['eyebrow'] ?? null);
    $headline = trans_field($section->content['headline'] ?? null);
    $subheadline = trans_field($section->content['subheadline'] ?? null);
    $sectionNumber = str_pad((int)(($section->sort_order ?? 10) / 10), 2, '0', STR_PAD_LEFT);
@endphp

@if($faqs->isNotEmpty())
<section class="relative bg-ivory text-ink border-b border-rule overflow-hidden">
    <div class="absolute inset-0 bg-grid-ivory-fine bg-grid-md opacity-40 pointer-events-none" aria-hidden="true"></div>

    <div class="relative max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-10 py-20 md:py-28">

        <div class="grid grid-cols-12 gap-x-4 sm:gap-x-6 lg:gap-x-10 gap-y-10">
            {{-- Left: section intro --}}
            <aside class="col-span-12 lg:col-span-4 lg:sticky lg:top-28 lg:self-start">
                @if($eyebrow)
                <div class="flex items-center gap-4 mb-6">
                    <span class="w-10 h-[3px] bg-amber inline-block"></span>
                    <span class="bp-spec text-amber-ink">{{ $eyebrow }}</span>
                </div>
                @endif
                @if($headline)
                <h2 class="font-display font-extrabold text-ink leading-[0.95] tracking-[-0.03em]
                           text-4xl sm:text-5xl lg:text-6xl">
                    {{ $headline }}<span class="text-amber">.</span>
                </h2>
                @endif
                @if($subheadline)
                <p class="mt-6 text-base text-body leading-relaxed max-w-sm">
                    {{ $subheadline }}
                </p>
                @endif
                <p class="mt-8 bp-spec-mono">
                    Manual · Index · {{ $faqs->count() }} entries
                </p>
                <div class="mt-2 h-[3px] w-10 bg-amber"></div>
            </aside>

            {{-- Right: FAQ ledger --}}
            <dl class="col-span-12 lg:col-span-8 border border-ink bg-paper">
                @foreach($faqs as $index => $faq)
                @php
                    $num = str_pad($index + 1, 2, '0', STR_PAD_LEFT);
                    $isLast = $loop->last;
                @endphp
                <div x-data="{ open: {{ $index === 0 ? 'true' : 'false' }} }"
                     class="{{ !$isLast ? 'border-b border-rule' : '' }}">
                    <dt>
                        <button @click="open = !open"
                                :aria-expanded="open"
                                :aria-controls="'faq-answer-{{ $index }}'"
                                class="w-full flex items-start gap-4 sm:gap-6 px-6 py-5 text-left
                                       hover:bg-ivory focus-visible:bg-ivory
                                       focus-visible:outline focus-visible:outline-2 focus-visible:outline-amber focus-visible:outline-offset-[-2px]
                                       transition-colors">
                            <span class="font-mono text-sm font-bold tabular-nums text-amber-ink shrink-0 mt-0.5">
                                {{ $num }}
                            </span>
                            <span class="flex-1 font-display text-base sm:text-lg font-semibold text-ink tracking-tight leading-snug text-balance">
                                {{ trans_field($faq->question) }}
                            </span>
                            <span class="shrink-0 w-7 h-7 border border-ink flex items-center justify-center mt-0.5"
                                  :class="open ? 'bg-ink text-ivory' : 'bg-paper text-ink'">
                                <x-heroicon-s-plus class="w-3.5 h-3.5" x-show="!open" />
                                <x-heroicon-s-minus class="w-3.5 h-3.5" x-show="open" x-cloak />
                            </span>
                        </button>
                    </dt>
                    <dd x-show="open"
                        x-collapse
                        :id="'faq-answer-{{ $index }}'"
                        role="region"
                        class="px-6 pb-6 pt-0">
                        <div class="pl-12 border-l-2 border-amber">
                            <p class="text-base text-body leading-relaxed">
                                {{ trans_field($faq->answer) }}
                            </p>
                        </div>
                    </dd>
                </div>
                @endforeach
            </dl>
        </div>

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
