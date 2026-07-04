{{-- Section: testimonials (Industrial Blueprint)
     content: eyebrow, headline(ml), subheadline(ml)
     $sectionData['testimonials'] is injected by SectionRendererService
--}}
@php
    $testimonials = $sectionData['testimonials'] ?? collect();
    $eyebrow = trans_field($section->content['eyebrow'] ?? null);
    $headline = trans_field($section->content['headline'] ?? null);
    $subheadline = trans_field($section->content['subheadline'] ?? null);
    $sectionNumber = str_pad((int)(($section->sort_order ?? 10) / 10), 2, '0', STR_PAD_LEFT);
@endphp

@if($testimonials->isNotEmpty())
<section class="relative bg-paper text-ink border-b border-rule">
    <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-10 py-16 md:py-24">

        {{-- Header — center variant (breaks the split-header rhythm) --}}
        <x-section-header
            variant="center"
            :eyebrow="$eyebrow"
            :headline="$headline"
            :subheadline="$subheadline"
            :meta="'Log · ' . $testimonials->count() . ' verified entries'" />

        {{-- Testimonial cards in ledger grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 border border-ink bg-paper">
            @foreach($testimonials as $index => $testimonial)
            @php
                $nameParts = explode(' ', trim($testimonial->name));
                $initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
                $entryNum = str_pad($index + 1, 2, '0', STR_PAD_LEFT);
                $rating = (int) ($testimonial->rating ?? 5);
                $meta = implode(' · ', array_filter([$testimonial->company ?? null, $testimonial->location ?? null]));
            @endphp

            <article class="relative p-6 sm:p-8 border-r border-b border-rule last:border-r-0 flex flex-col">

                {{-- Top meta: entry # + rating --}}
                <div class="flex items-center justify-between mb-6">
                    <span class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted">
                        Entry № {{ $entryNum }}
                    </span>
                    <div class="flex items-center gap-0.5" aria-label="{{ $rating }} out of 5 stars">
                        @for($i = 1; $i <= 5; $i++)
                            <x-heroicon-s-star class="w-3.5 h-3.5 {{ $i <= $rating ? 'text-amber' : 'text-rule' }}" />
                        @endfor
                    </div>
                </div>

                {{-- Amber tick --}}
                <div class="h-[2px] w-10 bg-amber mb-6"></div>

                {{-- Quote --}}
                <blockquote class="flex-1">
                    <p class="font-display text-lg sm:text-xl text-ink leading-snug tracking-tight text-balance mb-8">
                        &ldquo;{{ trans_field($testimonial->quote) }}&rdquo;
                    </p>
                </blockquote>

                {{-- Author footer --}}
                <footer class="flex items-center gap-4 pt-5 border-t border-rule">
                    {{-- Initials tile --}}
                    <div class="w-12 h-12 bg-ink text-ivory font-mono text-sm font-bold flex items-center justify-center tracking-wider shrink-0">
                        {{ $initials }}
                    </div>

                    <div class="flex-1 min-w-0">
                        <p class="font-display text-sm font-bold text-ink truncate">
                            {{ $testimonial->name }}
                        </p>
                        @if($meta)
                        <p class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-muted truncate mt-0.5">
                            {{ $meta }}
                        </p>
                        @endif
                    </div>

                    {{-- Verified tick --}}
                    <span class="inline-flex items-center gap-1.5 border border-rule px-2 py-1
                                 font-mono text-[9px] tracking-[0.2em] uppercase text-ink-muted"
                          title="{{ __('Verified customer') }}">
                        <x-heroicon-s-check-badge class="w-3 h-3 text-amber-ink" />
                        {{ __('Verified') }}
                    </span>
                </footer>
            </article>
            @endforeach
        </div>
    </div>
</section>
@endif
