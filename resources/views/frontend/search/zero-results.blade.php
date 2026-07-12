@extends('layouts.app')

@php
    $lang = app()->getLocale();
    $siteName = settings('general.site_name', 'OeParts');
    $inquiryHours = (int) settings('part_inquiry.response_hours', 24);
    $minChars = (int) settings('search.min_chars', 3);
    $zeroJsonLd = [
        '@@context' => 'https://schema.org',
        '@type' => 'SearchResultsPage',
        'name' => __('search.zero_jsonld_name', ['oem' => $normalized_query]),
        'description' => __('search.zero_jsonld_description', ['oem' => $normalized_query]),
        'mainEntity' => [
            '@type' => 'SearchAction',
            'query' => $normalized_query,
            'result' => [
                '@type' => 'ItemList',
                'numberOfItems' => 0,
            ],
        ],
    ];
@endphp

{{-- ── SEO ──────────────────────────────────────────────────────────────── --}}
@section('meta_robots')<meta name="robots" content="noindex,follow">@endsection

@section('title')
    {{ __('search.zero_page_title', ['oem' => $normalized_query, 'site' => $siteName]) }}
@endsection

@section('meta_description')
    {{ __('search.zero_meta_description', ['oem' => $normalized_query]) }}
@endsection

@section('og_title')
    {{ __('search.zero_og_title', ['oem' => $normalized_query]) }}
@endsection

@section('og_description')
    {{ __('search.zero_og_description', ['oem' => $normalized_query]) }}
@endsection

@section('canonical')
    <link rel="canonical" href="{{ route('frontend.search.results', ['lang' => app()->getLocale(), 'oem' => $normalized_query]) }}">
@endsection

@section('hreflang')
    @foreach(['en', 'de', 'lt', 'fr', 'es'] as $hLang)
        <link rel="alternate" hreflang="{{ $hLang }}" href="{{ route('frontend.search.results', ['lang' => $hLang, 'oem' => $normalized_query]) }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ route('frontend.search.results', ['lang' => 'en', 'oem' => $normalized_query]) }}">
@endsection

@section('og_type', 'website')

@section('json_ld')
<script type="application/ld+json">{!! json_encode($zeroJsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endsection

{{-- ══════════════════════════════════════════════════════════════════════
     INDUSTRIAL BLUEPRINT — ZERO RESULTS PAGE
     Treats "no match" like a formal field report: document-style framing,
     diagnostic details, and a concierge handoff.
     ══════════════════════════════════════════════════════════════════ --}}
@section('content')

<div class="relative bg-ivory text-ink min-h-screen">

    {{-- Blueprint grid background --}}
    <div class="fixed inset-0 bg-grid-ivory-fine bg-grid-sm opacity-70 pointer-events-none" aria-hidden="true"></div>

    <div class="relative max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-10 pt-10 pb-24">

        {{-- ═══ Document header: breadcrumb + doc ID ═══ --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-5 border-b border-rule mb-10 bp-rise">
            <x-ui.breadcrumb :items="[['label' => __('search.console_breadcrumb_current'), 'url' => route('frontend.search.console', ['lang' => $lang])], ['label' => 'No Match']]" />
            <div class="font-mono text-[10px] tracking-[0.2em] uppercase text-ink-muted">
                DOC · FIELD-REPORT · 404-A
            </div>
        </div>

        {{-- ═══ 12-column grid: header + spec panel ═══ --}}
        <div class="grid grid-cols-12 gap-x-4 sm:gap-x-6 lg:gap-x-8 gap-y-8 mb-16 bp-rise bp-rise-delay-1">

            {{-- Left: headline (8 cols) --}}
            <header class="col-span-12 lg:col-span-8">
                <div class="flex items-center gap-4 mb-8">
                    <span class="w-10 h-[3px] bg-amber inline-block"></span>
                    <span class="bp-spec text-amber-ink">{{ __('search.zero_eyebrow') }}</span>
                </div>

                <h1 class="font-display font-extrabold text-ink leading-[0.95] tracking-[-0.03em]
                           text-4xl sm:text-5xl lg:text-6xl max-w-[20ch]">
                    {{ __('search.zero_heading_new') }}<span class="text-amber-ink">.</span>
                </h1>

                <div class="mt-6 mb-8">
                    <div class="bp-rule-draw h-px bg-ink/70 origin-left"></div>
                </div>

                <p class="max-w-xl text-lg text-body leading-relaxed">
                    {{ __('search.zero_cta_subtitle', ['hours' => $inquiryHours]) }}
                </p>
            </header>

            {{-- Right: query spec panel (4 cols) --}}
            <aside class="col-span-12 lg:col-span-4">
                <div class="relative border border-ink bg-paper bp-register">
                    <div class="px-5 py-3 bg-ink text-ivory flex items-center justify-between">
                        <span class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase">QUERY · LOG</span>
                        <span class="font-mono text-[10px] tracking-[0.18em] uppercase text-ivory/60">{{ now()->format('Y.m.d · H:i') }}</span>
                    </div>
                    <div class="p-5 space-y-3">
                        <div>
                            <p class="bp-spec text-ink-muted mb-2">{{ ui_copy('search_submitted_label', 'search.submitted_label') }}</p>
                            <p class="font-mono text-xl sm:text-2xl font-bold text-ink tracking-wide uppercase break-all">
                                {{ $normalized_query }}
                            </p>
                        </div>
                        <div class="bp-leader pt-2">
                            <dt class="text-sm text-ink-muted">{{ ui_copy('search_normalized_label', 'search.normalized_label') }}</dt>
                            <span class="bp-leader-dots"></span>
                            <dd class="font-mono text-sm font-bold text-ink">✓</dd>
                        </div>
                        <div class="bp-leader">
                            <dt class="text-sm text-ink-muted">{{ ui_copy('search_catalogue_label', 'search.catalogue_label') }}</dt>
                            <span class="bp-leader-dots"></span>
                            <dd class="font-mono text-sm font-bold text-red-700">{{ ui_copy('search_zero_hits', 'search.zero_hits') }}</dd>
                        </div>
                        <div class="bp-leader">
                            <dt class="text-sm text-ink-muted">{{ ui_copy('search_console_status_crossrefs', 'search.console_status_crossrefs') }}</dt>
                            <span class="bp-leader-dots"></span>
                            <dd class="font-mono text-sm font-bold text-red-700">{{ ui_copy('search_zero_hits', 'search.zero_hits') }}</dd>
                        </div>
                        <div class="bp-leader">
                            <dt class="text-sm text-ink-muted">{{ ui_copy('search_status_label', 'search.status_label') }}</dt>
                            <span class="bp-leader-dots"></span>
                            <dd class="font-mono text-sm font-bold text-amber-ink uppercase tracking-wide">{{ ui_copy('search_concierge_label', 'search.concierge_label') }}</dd>
                        </div>
                    </div>
                </div>
            </aside>
        </div>

        {{-- ═══ Re-submit bar ═══ --}}
        <section class="mb-16 bp-rise bp-rise-delay-2">
            <div class="flex items-end justify-between pb-3 border-b border-ink">
                <h2 class="bp-spec text-ink">02 · {{ ui_copy('search_mini_search_label', 'search.mini_search_label') }}</h2>
                <span class="hidden sm:inline font-mono text-[10px] text-ink-muted tracking-[0.18em] uppercase">
                    {{ ui_copy('search_console_submit_meta', 'search.console_submit_meta', ['min' => $minChars]) }}
                </span>
            </div>
            <x-search.oem-input :value="$normalized_query" />
        </section>

        {{-- ═══ Primary: Concierge inquiry card ═══ --}}
        <section class="mb-16 bp-rise bp-rise-delay-3">
            <div class="flex items-end justify-between pb-3 border-b border-ink">
                <h2 class="bp-spec text-ink">03 · {{ __('search.zero_cta_title') }}</h2>
                <span class="hidden md:inline font-mono text-[10px] text-ink-muted tracking-[0.18em] uppercase">
                    {{ __('search.console_concierge_meta', ['hours' => $inquiryHours]) }}
                </span>
            </div>

            <div class="grid grid-cols-12 gap-x-4 sm:gap-x-6 lg:gap-x-8 border-x border-b border-ink bg-paper">

                {{-- Left: 3-step process (7 cols) --}}
                <div class="col-span-12 md:col-span-7 p-6 sm:p-10 md:border-r md:border-rule">
                    <ol class="space-y-0 divide-y divide-rule">
                        @foreach([
                            ['01', __('search.zero_step_1_title'), __('search.zero_step_1_body')],
                            ['02', __('search.zero_step_2_title'), __('search.zero_step_2_body')],
                            ['03', __('search.zero_step_3_title'), __('search.zero_step_3_body', ['hours' => $inquiryHours])],
                        ] as [$n, $title, $body])
                        <li class="flex items-start gap-5 py-5 first:pt-0 last:pb-0">
                            <span class="font-mono text-2xl sm:text-3xl font-medium text-ink tabular-nums leading-none pt-1">
                                {{ $n }}
                            </span>
                            <div class="flex-1 min-w-0 border-l border-rule pl-5">
                                <p class="font-display text-base sm:text-lg font-bold text-ink leading-snug">{{ $title }}</p>
                                <p class="mt-1.5 text-sm text-body leading-relaxed">{{ $body }}</p>
                            </div>
                        </li>
                        @endforeach
                    </ol>

                    {{-- Primary CTA --}}
                    <button type="button"
                            x-on:click="window.dispatchEvent(new CustomEvent('open-inquiry-modal', { detail: { oem: {{ json_encode($normalized_query) }} } }))"
                            class="mt-8 w-full group inline-flex items-center justify-center gap-3
                                   px-6 py-4
                                   bg-ink text-ivory font-sans text-[13px] font-bold uppercase tracking-[0.22em]
                                   border border-ink
                                   hover:bg-amber hover:text-ink
                                   transition-colors duration-150
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber focus-visible:ring-offset-2 focus-visible:ring-offset-paper">
                        <x-heroicon-o-paper-airplane class="w-4 h-4" aria-hidden="true" />
                        {{ __('search.inquiry_submit') }}
                        <x-heroicon-s-arrow-long-right class="w-5 h-5 transform transition-transform group-hover:translate-x-1" aria-hidden="true" />
                    </button>
                </div>

                {{-- Right: trust spec panel (5 cols) --}}
                <aside class="col-span-12 md:col-span-5 p-6 sm:p-10 bg-ivory border-t md:border-t-0 border-rule">
                    <p class="bp-spec text-ink mb-5">{{ ui_copy('search_trust_record_label', 'search.trust_record_label') }}</p>

                    <dl class="space-y-4">
                        <div class="flex items-start gap-3">
                            <x-heroicon-s-shield-check class="w-4 h-4 text-amber-ink mt-1 shrink-0" aria-hidden="true" />
                            <div>
                                <dt class="font-sans text-[13px] font-bold text-ink">{{ __('search.inquiry_trust_suppliers') }}</dt>
                                <dd class="font-mono text-[11px] text-ink-muted uppercase tracking-[0.14em] mt-0.5">{{ ui_copy('search_trust_sub_background_checked', 'search.trust_sub_background_checked') }}</dd>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <x-heroicon-s-building-storefront class="w-4 h-4 text-amber-ink mt-1 shrink-0" aria-hidden="true" />
                            <div>
                                <dt class="font-sans text-[13px] font-bold text-ink">{{ __('search.inquiry_trust_warehouse') }}</dt>
                                <dd class="font-mono text-[11px] text-ink-muted uppercase tracking-[0.14em] mt-0.5">{{ ui_copy('search_trust_sub_eu_despatch', 'search.trust_sub_eu_despatch') }}</dd>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <x-heroicon-s-trophy class="w-4 h-4 text-amber-ink mt-1 shrink-0" aria-hidden="true" />
                            <div>
                                <dt class="font-sans text-[13px] font-bold text-ink">{{ __('search.inquiry_trust_quality') }}</dt>
                                <dd class="font-mono text-[11px] text-ink-muted uppercase tracking-[0.14em] mt-0.5">{{ ui_copy('search_trust_sub_genuine_warranty', 'search.trust_sub_genuine_warranty') }}</dd>
                            </div>
                        </div>
                    </dl>

                    <div class="mt-8 pt-6 border-t border-rule">
                        <p class="bp-spec text-ink-muted mb-3">{{ ui_copy('search_typical_sla_label', 'search.typical_sla_label') }}</p>
                        <p class="font-mono text-4xl sm:text-5xl font-bold text-ink leading-none tabular-nums">
                            {{ $inquiryHours }}<span class="text-amber-ink">h</span>
                        </p>
                        <p class="mt-2 font-mono text-[11px] text-ink-muted uppercase tracking-[0.16em]">
                            {{ ui_copy('search_business_hours_response', 'search.business_hours_response') }}
                        </p>
                    </div>
                </aside>
            </div>
        </section>

        {{-- ═══ Popular OEMs · Indexed ═══ --}}
        @if($popularOems->isNotEmpty())
        <section class="mb-16 bp-rise">
            <div class="flex items-end justify-between pb-3 border-b border-ink">
                <h2 class="bp-spec text-ink">04 · {{ __('search.zero_popular_heading') }}</h2>
                <span class="hidden sm:inline font-mono text-[10px] text-ink-muted tracking-[0.18em] uppercase">
                    frequently indexed
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-0 border-x border-b border-ink bg-paper">
                @foreach($popularOems as $i => $oem)
                <a href="{{ route('frontend.search.results', ['lang' => $lang, 'oem' => $oem]) }}"
                   class="group relative flex items-center justify-between gap-3 p-5
                          border-rule
                          {{ $i < $popularOems->count() - 1 ? 'sm:border-r lg:border-r border-b sm:border-b-0 lg:border-b-0' : '' }}
                          {{ ($i + 1) % 2 === 0 ? 'sm:border-r-0 lg:border-r' : '' }}
                          hover:bg-ink hover:text-ivory transition-colors duration-150">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="font-mono text-[10px] font-bold text-ink-muted group-hover:text-amber tracking-[0.2em] uppercase shrink-0">
                            {{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}
                        </span>
                        <span class="font-mono text-sm sm:text-base font-bold tracking-wide uppercase truncate">
                            {{ $oem }}
                        </span>
                    </div>
                    <x-heroicon-s-arrow-long-right class="w-4 h-4 text-ink-muted group-hover:text-amber transform transition-transform group-hover:translate-x-1 shrink-0" aria-hidden="true" />
                </a>
                @endforeach
            </div>
        </section>
        @endif

        {{-- ═══ Diagnostic tips ═══ --}}
        <section class="mb-16 bp-rise">
            <div class="flex items-end justify-between pb-3 border-b border-ink">
                <h2 class="bp-spec text-ink">05 · {{ __('search.zero_tips_heading') }}</h2>
                <span class="hidden sm:inline font-mono text-[10px] text-ink-muted tracking-[0.18em] uppercase">
                    diagnostic
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-0 border-x border-b border-ink bg-paper">
                @foreach(['zero_tip_1', 'zero_tip_2', 'zero_tip_3', 'zero_tip_4'] as $i => $tipKey)
                <div class="flex items-start gap-4 p-5 sm:p-6
                            {{ $i % 2 === 0 ? 'md:border-r border-rule' : '' }}
                            {{ $i < 2 ? 'border-b border-rule' : '' }}">
                    <span class="font-mono text-xs font-bold tracking-[0.2em] text-amber-ink uppercase shrink-0 mt-1 w-8">
                        {{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}
                    </span>
                    <p class="text-sm text-body leading-relaxed">{{ __('search.' . $tipKey) }}</p>
                </div>
                @endforeach
            </div>
        </section>

        {{-- ═══ Back to home ═══ --}}
        <div class="pt-6 border-t border-rule flex items-center justify-between bp-rise">
            <a href="{{ url('/'.$lang.'/') }}"
               class="group inline-flex items-center gap-3 font-mono text-[12px] font-bold tracking-[0.2em] uppercase text-ink border-b border-ink hover:text-amber-ink hover:border-amber-ink pb-0.5 transition-colors">
                <x-heroicon-s-arrow-long-left class="w-4 h-4 transform transition-transform group-hover:-translate-x-1" />
                {{ __('search.zero_back') }}
            </a>
            <span class="font-mono text-[10px] tracking-[0.2em] uppercase text-ink-muted">
                END · FIELD-REPORT
            </span>
        </div>
    </div>
</div>

<x-modals.part-inquiry :normalized-query="$normalized_query ?? ''" />

@endsection
