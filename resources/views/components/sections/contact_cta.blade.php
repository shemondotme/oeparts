{{-- Section: contact_cta (Industrial Blueprint)
     content: eyebrow, headline(ml), subheadline(ml), button_text(ml)
--}}
@php
    $eyebrow = trans_field($section->content['eyebrow'] ?? null);
    $headline = trans_field($section->content['headline'] ?? null);
    $subheadline = trans_field($section->content['subheadline'] ?? null);
    $buttonText = trans_field($section->content['button_text'] ?? null) ?: 'Contact Us';
    $phone = settings('general.site_phone', '+370 600 00000');
    $email = settings('general.site_email', 'support@oemhub.eu');
    $sectionNumber = str_pad((int)(($section->sort_order ?? 10) / 10), 2, '0', STR_PAD_LEFT);
@endphp

<section class="relative bg-ink text-ivory border-b border-rule-dark overflow-hidden">
    <div class="absolute inset-0 bg-grid-navy bg-grid-lg opacity-60 pointer-events-none" aria-hidden="true"></div>

    {{-- Amber tick strip --}}
    <div class="relative h-[3px] bg-amber"></div>

    <div class="relative max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-10 py-20 md:py-28">

        <div class="grid grid-cols-12 gap-x-4 sm:gap-x-6 lg:gap-x-10 gap-y-10 items-end">
            {{-- Headline block --}}
            <div class="col-span-12 lg:col-span-7">
                @if($eyebrow)
                <div class="flex items-center gap-4 mb-6">
                    <span class="w-10 h-[3px] bg-amber inline-block"></span>
                    <span class="font-mono text-[10px] tracking-[0.28em] uppercase text-amber">§ {{ $eyebrow }}</span>
                </div>
                @endif
                @if($headline)
                <h2 class="font-display font-extrabold text-ivory leading-[0.95] tracking-[-0.03em]
                           text-4xl sm:text-5xl lg:text-6xl max-w-[16ch]">
                    {{ $headline }}<span class="text-amber">.</span>
                </h2>
                @endif
                @if($subheadline)
                <p class="mt-6 text-base text-ivory/75 leading-relaxed max-w-lg">
                    {{ $subheadline }}
                </p>
                @endif
            </div>

            {{-- Action column --}}
            <div class="col-span-12 lg:col-span-5 lg:pl-10 lg:border-l lg:border-white/20">
                {{-- Contact rows --}}
                <dl class="space-y-0 border-t border-white/20">
                    @if($email)
                    <div class="flex items-baseline justify-between gap-4 py-4 border-b border-white/20">
                        <dt class="font-mono text-[10px] tracking-[0.22em] uppercase text-ivory/60 shrink-0">
                            Email
                        </dt>
                        <span class="flex-1 border-b border-dotted border-white/25 translate-y-[-4px]"></span>
                        <dd class="font-mono text-sm text-ivory shrink-0">
                            <a href="mailto:{{ $email }}" class="hover:text-amber transition-colors">
                                {{ $email }}
                            </a>
                        </dd>
                    </div>
                    @endif
                    @if($phone)
                    <div class="flex items-baseline justify-between gap-4 py-4 border-b border-white/20">
                        <dt class="font-mono text-[10px] tracking-[0.22em] uppercase text-ivory/60 shrink-0">
                            Phone
                        </dt>
                        <span class="flex-1 border-b border-dotted border-white/25 translate-y-[-4px]"></span>
                        <dd class="font-mono text-sm tabular-nums text-ivory shrink-0">
                            <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="hover:text-amber transition-colors">
                                {{ $phone }}
                            </a>
                        </dd>
                    </div>
                    @endif
                    <div class="flex items-baseline justify-between gap-4 py-4 border-b border-white/20">
                        <dt class="font-mono text-[10px] tracking-[0.22em] uppercase text-ivory/60 shrink-0">
                            Hours
                        </dt>
                        <span class="flex-1 border-b border-dotted border-white/25 translate-y-[-4px]"></span>
                        <dd class="font-mono text-sm tabular-nums text-ivory shrink-0">Mon-Fri · 09:00-18:00 CET</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-4 py-4 border-b border-white/20">
                        <dt class="font-mono text-[10px] tracking-[0.22em] uppercase text-ivory/60 shrink-0">
                            SLA
                        </dt>
                        <span class="flex-1 border-b border-dotted border-white/25 translate-y-[-4px]"></span>
                        <dd class="font-mono text-sm tabular-nums text-ivory shrink-0">&lt; 24 h response</dd>
                    </div>
                </dl>

                {{-- CTAs --}}
                <div class="mt-8 flex flex-col sm:flex-row gap-3">
                    <a href="{{ url('/'.app()->getLocale().'/contact') }}" class="bp-btn-amber flex-1 justify-center">
                        <x-heroicon-s-envelope class="w-5 h-5" />
                        {{ $buttonText }}
                    </a>
                    @if($phone)
                    <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}"
                       class="inline-flex items-center justify-center gap-2 px-5 py-3 border border-ivory/30 text-ivory
                              font-mono text-xs font-bold uppercase tracking-[0.22em]
                              hover:border-amber hover:text-amber transition-colors">
                        <x-heroicon-s-phone class="w-4 h-4" />
                        Call now
                    </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
