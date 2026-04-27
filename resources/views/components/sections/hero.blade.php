{{-- Section: hero (Industrial Blueprint)
     content: headline(ml), subheadline(ml), placeholder(ml), button_text(ml), popular_oem[]
--}}
@php
    $lang = app()->getLocale();
    $searchHintId = 'search-hint';
    $headline = trans_field($section->content['headline'] ?? null) ?: 'Every genuine OEM part. One hub. Precisely indexed.';
    $subheadline = trans_field($section->content['subheadline'] ?? null);
    $buttonText = trans_field($section->content['button_text'] ?? null) ?: 'DISPATCH QUERY';
    $popularOems = $section->content['popular_oem'] ?? ['1K0698151E', '3C0615301B', 'WHT005549A', '06H103405A'];
    $minChars = (int) settings('search.min_chars', 3);
    $heroIndexBadge = settings_trans('ui.hero_index_badge', '§ INDEX');
    $heroLive = settings_trans('ui.hero_live_status', 'CATALOGUE LIVE');
    $heroEyebrow = settings_trans('ui.hero_eyebrow', 'Genuine OEM Parts Index · 1,000,000+');
    $heroDefaultSub = settings_trans('ui.hero_subtext_default', 'Enter any OEM number. We return matches, cross-references, and verified suppliers across the European Union — or open a concierge inquiry if the part is rare.');
    $heroSpecTitle = settings_trans('ui.hero_spec_title', 'Specification');
    $heroR1l = settings_trans('ui.hero_spec_r1_label', 'Catalogue');
    $catCount = number_format((int) settings('stats_counter.parts_count', 1000000));
    $heroR2l = settings_trans('ui.hero_spec_r2_label', 'Manufacturers');
    $heroR2v = settings_trans('ui.hero_spec_r2_value', '214');
    $heroR3l = settings_trans('ui.hero_spec_r3_label', 'Cross-refs');
    $heroR3v = settings_trans('ui.hero_spec_r3_value', '3.2M');
    $heroR4l = settings_trans('ui.hero_spec_r4_label', 'Avg. despatch');
    $heroR4v = settings_trans('ui.hero_spec_r4_value', '24h');
    $heroR5l = settings_trans('ui.hero_spec_r5_label', 'Languages');
    $heroR5v = settings_trans('ui.hero_spec_r5_value', 'EN·DE·LT·FR·ES');
    $heroSrcL = settings_trans('ui.hero_source_label', 'Source');
    $heroSrcB = settings_trans('ui.hero_source_badge', 'VERIFIED · EU');
    $heroSearchStrip = settings_trans('ui.hero_search_strip', '§ ENTER OEM NUMBER');
    $heroSearchMeta = str_replace(':min', (string) $minChars, settings_trans('ui.hero_search_meta_hint', 'min :min chars · uppercase alphanumeric'));
    $heroIndexedLbl = settings_trans('ui.hero_indexed_label', 'Indexed:');
    $heroFoot1 = settings_trans('ui.hero_footer_pill_1', 'Verified Suppliers');
    $heroFoot2 = settings_trans('ui.hero_footer_pill_2', 'TLS 1.3 · SSL');
    $heroFoot3 = settings_trans('ui.hero_footer_pill_3', '27 EU Countries');
@endphp

{{-- ══════════════════════════════════════════════════════════════════════
     INDUSTRIAL BLUEPRINT HERO
     Technical document opening — like the cover of a parts manual
     ══════════════════════════════════════════════════════════════════ --}}
<section class="relative bg-ivory text-ink overflow-hidden border-b border-rule">

    {{-- Blueprint grid texture --}}
    <div class="absolute inset-0 bg-grid-ivory bg-grid-md opacity-70 pointer-events-none" aria-hidden="true"></div>

    {{-- Corner register marks --}}
    <div class="absolute top-6 left-6 w-4 h-4 border-l-2 border-t-2 border-ink/40 pointer-events-none" aria-hidden="true"></div>
    <div class="absolute top-6 right-6 w-4 h-4 border-r-2 border-t-2 border-ink/40 pointer-events-none" aria-hidden="true"></div>

    <div class="relative max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-10 py-16 md:py-24 lg:py-32">

        {{-- ═══ Spec-sheet header row ═══ --}}
        <div class="flex items-center justify-between mb-12 md:mb-16 pb-5 border-b border-rule">
            <div class="flex items-center gap-6 sm:gap-8">
                <span class="font-mono text-spec-sm font-bold text-ink-muted tracking-[0.22em] uppercase">
                    {{ $heroIndexBadge }}
                </span>
            </div>
            <div class="flex items-center gap-2 font-mono text-[10px] text-ink-muted tracking-[0.2em] uppercase">
                <span class="w-1.5 h-1.5 bg-emerald-600 animate-pulse motion-reduce:animate-none"></span>
                <span class="hidden sm:inline">{{ $heroLive }}</span>
            </div>
        </div>

        {{-- ═══ 12-column editorial grid ═══ --}}
        <div class="grid grid-cols-12 gap-x-4 sm:gap-x-6 lg:gap-x-8 gap-y-10 items-end">

            {{-- Left: headline + subheadline (8 cols) --}}
            <div class="col-span-12 lg:col-span-8">
                {{-- Eyebrow marker --}}
                <div class="bp-rise flex items-center gap-4 mb-8">
                    <span class="w-10 h-[3px] bg-amber inline-block"></span>
                    <span class="bp-spec text-amber-ink">{{ $heroEyebrow }}</span>
                </div>

                {{-- Headline — huge display --}}
                <h1 class="bp-rise bp-rise-delay-1 font-display font-extrabold text-ink text-blueprint-lg leading-[0.92] tracking-[-0.035em] max-w-[18ch]">
                    {{ $headline }}<span class="text-amber">.</span>
                </h1>

                {{-- Ruler-line under headline --}}
                <div class="relative mt-6 mb-8">
                    <div class="bp-rule-draw h-px bg-ink/70 origin-left"></div>
                </div>

                @if($subheadline)
                    <p class="bp-rise bp-rise-delay-2 max-w-xl text-lg text-body leading-relaxed">
                        {{ $subheadline }}
                    </p>
                @else
                    <p class="bp-rise bp-rise-delay-2 max-w-xl text-lg text-body leading-relaxed">
                        {{ $heroDefaultSub }}
                    </p>
                @endif
            </div>

            {{-- Right: spec panel (4 cols) — data as image --}}
            <aside class="col-span-12 lg:col-span-4 bp-rise bp-rise-delay-3">
                <div class="relative border border-ink p-6 sm:p-7 bg-paper bp-register">
                    <p class="bp-spec text-ink-muted mb-4">{{ $heroSpecTitle }}</p>
                    <dl class="space-y-3 text-sm">
                        <div class="bp-leader">
                            <dt class="text-ink-muted">{{ $heroR1l }}</dt>
                            <span class="bp-leader-dots"></span>
                            <dd class="font-mono font-bold text-ink tabular-nums">{{ $catCount }}</dd>
                        </div>
                        <div class="bp-leader">
                            <dt class="text-ink-muted">{{ $heroR2l }}</dt>
                            <span class="bp-leader-dots"></span>
                            <dd class="font-mono font-bold text-ink tabular-nums">{{ $heroR2v }}</dd>
                        </div>
                        <div class="bp-leader">
                            <dt class="text-ink-muted">{{ $heroR3l }}</dt>
                            <span class="bp-leader-dots"></span>
                            <dd class="font-mono font-bold text-ink tabular-nums">{{ $heroR3v }}</dd>
                        </div>
                        <div class="bp-leader">
                            <dt class="text-ink-muted">{{ $heroR4l }}</dt>
                            <span class="bp-leader-dots"></span>
                            <dd class="font-mono font-bold text-ink">{{ $heroR4v }}</dd>
                        </div>
                        <div class="bp-leader">
                            <dt class="text-ink-muted">{{ $heroR5l }}</dt>
                            <span class="bp-leader-dots"></span>
                            <dd class="font-mono font-bold text-ink">{{ $heroR5v }}</dd>
                        </div>
                    </dl>

                    {{-- Bottom amber tick --}}
                    <div class="mt-6 pt-4 border-t border-rule flex items-center justify-between">
                        <span class="bp-spec text-ink-muted">{{ $heroSrcL }}</span>
                        <span class="font-mono text-[11px] font-bold text-amber-ink tracking-[0.16em] uppercase">{{ $heroSrcB }}</span>
                    </div>
                </div>
            </aside>

            {{-- ═══ Full-width search form ═══ --}}
            <div class="col-span-12 bp-rise bp-rise-delay-4 mt-2 md:mt-4"
                 x-data="{
                    q: '',
                    placeholder: '',
                    placeholders: @js(array_values($popularOems)),
                    current: 0, charIndex: 0, isDeleting: false,
                    type() {
                        if (!this.placeholders.length) return;
                        const currentText = this.placeholders[this.current];
                        if (!this.isDeleting) {
                            this.placeholder = currentText.substring(0, this.charIndex++);
                            if (this.charIndex > currentText.length) {
                                this.isDeleting = true;
                                setTimeout(() => this.type(), 2200);
                                return;
                            }
                        } else {
                            this.placeholder = currentText.substring(0, this.charIndex--);
                            if (this.charIndex < 0) {
                                this.isDeleting = false;
                                this.current = (this.current + 1) % this.placeholders.length;
                            }
                        }
                        setTimeout(() => this.type(), this.isDeleting ? 50 : 90);
                    },
                 }"
                 x-init="type()">

                {{-- Form label strip --}}
                <div class="flex items-end justify-between pb-3 border-b border-ink">
                    <div class="flex items-center gap-3">
                        <span class="bp-spec text-ink">{{ $heroSearchStrip }}</span>
                    </div>
                    <span class="hidden sm:inline font-mono text-[10px] text-ink-muted tracking-[0.18em] uppercase">
                        {{ $heroSearchMeta }}
                    </span>
                </div>

                <form
                    @submit.prevent="
                        const oem = q.trim().replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
                        if (oem.length >= {{ $minChars }}) window.location.href = '{{ url('/'.$lang.'/parts') }}/' + oem;
                    "
                    class="flex flex-col sm:flex-row items-stretch bg-paper border-x border-b border-ink"
                >
                    {{-- Honeypot --}}
                    <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">

                    <div class="flex-1 flex items-center gap-4 px-5 sm:px-7 py-5 sm:py-6 min-w-0">
                        <x-heroicon-o-magnifying-glass class="w-6 h-6 text-ink shrink-0" aria-hidden="true" />
                        <input
                            type="text"
                            x-model="q"
                            @focus="placeholder = ''"
                            @blur="if(!q) type()"
                            id="hero-oem-search"
                            :placeholder="placeholder || 'Enter OEM number…'"
                            aria-label="Search by OEM part number"
                            aria-describedby="{{ $searchHintId }}"
                            autocomplete="off"
                            autocapitalize="characters"
                            inputmode="text"
                            class="flex-1 bg-transparent font-mono uppercase tracking-wider
                                   text-xl sm:text-2xl md:text-3xl font-medium text-ink
                                   placeholder:normal-case placeholder:tracking-normal placeholder:font-sans placeholder:text-ink-muted/60
                                   border-0 focus:outline-none focus:ring-0 min-w-0 py-1"
                        >
                    </div>
                    <button
                        type="submit"
                        class="group shrink-0 inline-flex items-center justify-center gap-3
                               px-7 sm:px-10 py-5 sm:py-6
                               bg-ink text-ivory font-sans text-[13px] font-bold uppercase tracking-[0.22em]
                               border-t sm:border-t-0 sm:border-l border-ink
                               hover:bg-amber hover:text-ink
                               transition-colors duration-150
                               focus-visible:outline-none focus-visible:bg-amber focus-visible:text-ink"
                    >
                        {{ $buttonText }}
                        <x-heroicon-s-arrow-long-right class="w-5 h-5 transform transition-transform group-hover:translate-x-1" aria-hidden="true" />
                    </button>
                </form>

                <p id="{{ $searchHintId }}" class="sr-only">
                    Type at least {{ $minChars }} characters. Press Enter to search. Alphanumeric only, no spaces or dashes.
                </p>

                {{-- Popular OEMs row --}}
                @if(!empty($popularOems))
                <div class="mt-6 flex flex-wrap items-center gap-x-3 gap-y-2">
                    <span class="bp-spec text-ink-muted mr-1">{{ $heroIndexedLbl }}</span>
                    @foreach($popularOems as $oem)
                        <a href="{{ url('/'.$lang.'/parts/'.$oem) }}"
                           class="group inline-flex items-center gap-2 py-1.5 px-3
                                  border border-rule hover:border-ink
                                  font-mono text-sm font-medium text-ink
                                  hover:bg-ink hover:text-ivory
                                  transition-colors duration-150">
                            <span class="text-[10px] text-ink-muted group-hover:text-amber">→</span>
                            {{ $oem }}
                        </a>
                    @endforeach
                </div>
                @endif
            </div>
        </div>

        {{-- ═══ Footer meta strip ═══ --}}
        <div class="mt-16 md:mt-20 pt-6 border-t border-rule flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 font-mono text-[11px] tracking-[0.16em] uppercase text-ink-muted">
                <span class="flex items-center gap-2">
                    <x-heroicon-s-check-badge class="w-4 h-4 text-amber-ink" />
                    {{ $heroFoot1 }}
                </span>
                <span class="text-rule">│</span>
                <span class="flex items-center gap-2">
                    <x-heroicon-s-lock-closed class="w-4 h-4 text-amber-ink" />
                    {{ $heroFoot2 }}
                </span>
                <span class="text-rule">│</span>
                <span class="flex items-center gap-2">
                    <x-heroicon-o-truck class="w-4 h-4 text-amber-ink" />
                    {{ $heroFoot3 }}
                </span>
            </div>
            <a href="#how-it-works"
               class="inline-flex items-center gap-2 font-mono text-[11px] font-bold tracking-[0.2em] uppercase text-ink border-b border-ink hover:text-amber-ink hover:border-amber-ink pb-0.5 transition-colors">
                How it works
                <x-heroicon-s-arrow-long-down class="w-4 h-4" />
            </a>
        </div>
    </div>
</section>
