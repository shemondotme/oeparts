@extends('layouts.app')

@php
    $siteName = settings('general.site_name', 'OeParts');
    $inquiryHours = (int) settings('part_inquiry.response_hours', 24);
@endphp

{{-- ── SEO ──────────────────────────────────────────────────────────────── --}}
@section('title'){{ __('search.console_seo_title') }} · {{ $siteName }}@endsection
@section('meta_description'){{ __('search.console_seo_description') }}@endsection
@section('og_title'){{ __('search.console_seo_title') }} · {{ $siteName }}@endsection
@section('og_description'){{ __('search.console_og_description') }}@endsection
@section('canonical')
    <link rel="canonical" href="{{ route('frontend.search.console', ['lang' => app()->getLocale()]) }}">
@endsection
@section('hreflang')
    @foreach(['en', 'de', 'lt', 'fr', 'es'] as $hLang)
        <link rel="alternate" hreflang="{{ $hLang }}" href="{{ route('frontend.search.console', ['lang' => $hLang]) }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ route('frontend.search.console', ['lang' => 'en']) }}">
@endsection
@section('og_type', 'website')

{{-- ══════════════════════════════════════════════════════════════════════
     INDUSTRIAL BLUEPRINT — PARTS SEARCH CONSOLE
     Universal landing page for any "Browse parts / Search parts / Parts
     Search" CTA across the site. Document-style framing with a prominent
     OEM input + popular queries + brand shortcuts.
     ══════════════════════════════════════════════════════════════════ --}}
@section('content')

<div class="relative bg-ivory text-ink min-h-screen">

    {{-- Blueprint grid background --}}
    <div class="fixed inset-0 bg-grid-ivory-fine bg-grid-sm opacity-70 pointer-events-none" aria-hidden="true"></div>

    <div class="relative max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-10 pt-10 pb-24">

        {{-- ═══ Document header: breadcrumb + doc ID ═══ --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-5 border-b border-rule mb-10 bp-rise">
            <x-ui.breadcrumb :items="[['label' => __('search.console_breadcrumb_current')]]" />
            <div class="font-mono text-[10px] tracking-[0.2em] uppercase text-ink-muted">
                {{ __('search.console_doc_id') }}
            </div>
        </div>

        {{-- ═══ 12-column grid: headline + console spec panel ═══ --}}
        <div class="grid grid-cols-12 gap-x-4 sm:gap-x-6 lg:gap-x-8 gap-y-8 mb-12 bp-rise bp-rise-delay-1">

            {{-- Left: headline (8 cols) --}}
            <header class="col-span-12 lg:col-span-8">
                <div class="flex items-center gap-4 mb-8">
                    <span class="w-10 h-[3px] bg-amber inline-block"></span>
                    <span class="bp-spec text-amber-ink">{{ __('search.console_eyebrow') }}</span>
                </div>

                <h1 class="font-display font-extrabold text-ink leading-[0.95] tracking-[-0.03em]
                           text-4xl sm:text-5xl lg:text-6xl max-w-[22ch]">
                    {{ __('search.console_headline') }}<span class="text-amber">.</span>
                </h1>

                <div class="mt-6 mb-8">
                    <div class="bp-rule-draw h-px bg-ink/70 origin-left"></div>
                </div>

                <p class="max-w-xl text-lg text-body leading-relaxed">
                    {{ __('search.console_intro', ['min' => $minChars]) }}
                </p>
            </header>

            {{-- Right: console spec panel (4 cols) --}}
            <aside class="col-span-12 lg:col-span-4">
                <div class="relative border border-ink bg-paper bp-register">
                    <div class="px-5 py-3 bg-ink text-ivory flex items-center justify-between">
                        <span class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase">{{ __('search.console_status_heading') }}</span>
                        <span class="font-mono text-[10px] tracking-[0.18em] uppercase text-ivory/60">{{ now()->format('Y.m.d · H:i') }}</span>
                    </div>
                    <div class="p-5 space-y-3">
                        <div class="bp-leader pt-0.5">
                            <dt class="text-sm text-ink-muted">{{ __('search.console_status_brands') }}</dt>
                            <span class="bp-leader-dots"></span>
                            <dd class="font-mono text-sm font-bold text-ink">{{ $brandCount }}+</dd>
                        </div>
                        <div class="bp-leader">
                            <dt class="text-sm text-ink-muted">{{ __('search.console_status_skus') }}</dt>
                            <span class="bp-leader-dots"></span>
                            <dd class="font-mono text-sm font-bold text-ink">{{ number_format($productCount) }}</dd>
                        </div>
                        <div class="bp-leader">
                            <dt class="text-sm text-ink-muted">{{ __('search.console_status_crossrefs') }}</dt>
                            <span class="bp-leader-dots"></span>
                            <dd class="font-mono text-sm font-bold text-ink">{{ __('search.console_status_crossrefs_value') }}</dd>
                        </div>
                        <div class="bp-leader">
                            <dt class="text-sm text-ink-muted">{{ __('search.console_status_coverage') }}</dt>
                            <span class="bp-leader-dots"></span>
                            <dd class="font-mono text-sm font-bold text-amber-ink uppercase tracking-wide">{{ __('search.console_status_coverage_value') }}</dd>
                        </div>
                        <div class="bp-leader">
                            <dt class="text-sm text-ink-muted">{{ __('search.console_status_response') }}</dt>
                            <span class="bp-leader-dots"></span>
                            <dd class="font-mono text-sm font-bold text-ink">≤ {{ $inquiryHours }}h</dd>
                        </div>
                    </div>
                </div>
            </aside>
        </div>

        {{-- ═══ 01 · Query input ═══ --}}
        <section class="mb-16 bp-rise bp-rise-delay-2">
            <div class="flex items-end justify-between pb-3 border-b border-ink">
                <span class="bp-spec text-ink">{{ __('search.console_submit_query') }}</span>
                <span class="hidden sm:inline font-mono text-[10px] text-ink-muted tracking-[0.18em] uppercase">
                    {{ __('search.console_submit_meta', ['min' => $minChars]) }}
                </span>
            </div>
            <x-search.oem-input :autofocus="true" size="lg" />
        </section>

        {{-- ═══ 02 · Popular OEMs ═══ --}}
        @if($popularOems && $popularOems->isNotEmpty())
        <section class="mb-16 bp-rise bp-rise-delay-3">
            <div class="flex items-end justify-between pb-3 border-b border-ink mb-6">
                <span class="bp-spec text-ink">{{ __('search.console_popular_heading') }}</span>
                <span class="hidden md:inline font-mono text-[10px] text-ink-muted tracking-[0.18em] uppercase">
                    {{ __('search.console_popular_meta') }}
                </span>
            </div>
            <ol class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2" aria-label="{{ __('search.console_popular_aria') }}">
                @foreach($popularOems as $index => $oem)
                <li>
                    <a href="{{ url('/'.$lang.'/parts/'.$oem) }}"
                       class="group flex items-center gap-3 px-4 py-3 border border-rule-strong bg-paper
                              hover:bg-ink hover:text-ivory hover:border-ink transition-colors">
                        <span class="font-mono text-[10px] tabular-nums tracking-[0.18em] uppercase text-ink-muted group-hover:text-ivory/60">
                            {{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}
                        </span>
                        <span class="flex-1 font-mono text-sm font-bold tabular-nums text-ink group-hover:text-ivory break-all">
                            {{ $oem }}
                        </span>
                        <x-heroicon-s-arrow-long-right class="w-3.5 h-3.5 text-ink-muted group-hover:text-ivory shrink-0" />
                    </a>
                </li>
                @endforeach
            </ol>
        </section>
        @endif

        {{-- ═══ 03 · Featured brands ═══ --}}
        @if($featuredBrands && $featuredBrands->isNotEmpty())
        <section class="mb-16 bp-rise bp-rise-delay-4">
            <div class="flex items-end justify-between pb-3 border-b border-ink mb-6">
                <span class="bp-spec text-ink">{{ __('search.console_brands_heading') }}</span>
                <a href="{{ url('/'.$lang.'/brands') }}" class="hidden md:inline font-mono text-[10px] text-amber-ink tracking-[0.18em] uppercase hover:text-ink transition-colors">
                    {{ __('search.console_all_brands') }}
                </a>
            </div>
            <ul class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                @foreach($featuredBrands as $brand)
                <li>
                    <a href="{{ url('/'.$lang.'/brand/'.$brand->slug) }}"
                       class="group flex items-center justify-between gap-3 px-4 py-3 border border-rule-strong bg-paper
                              hover:bg-ink hover:text-ivory hover:border-ink transition-colors">
                        <span class="font-display text-sm font-bold tracking-[-0.01em] text-ink group-hover:text-ivory truncate">
                            {{ trans_field($brand->name) }}
                        </span>
                        <x-heroicon-s-arrow-long-right class="w-3.5 h-3.5 text-ink-muted group-hover:text-ivory shrink-0" />
                    </a>
                </li>
                @endforeach
            </ul>
        </section>
        @endif

        {{-- ═══ 04 · Not finding it? concierge ═══ --}}
        <section class="bp-rise bp-rise-delay-5">
            <div class="flex items-end justify-between pb-3 border-b border-ink">
                <span class="bp-spec text-ink">{{ __('search.console_concierge_heading') }}</span>
                <span class="hidden md:inline font-mono text-[10px] text-ink-muted tracking-[0.18em] uppercase">
                    {{ __('search.console_concierge_meta', ['hours' => $inquiryHours]) }}
                </span>
            </div>
            <div class="grid grid-cols-12 border-x border-b border-ink bg-paper">
                <div class="col-span-12 md:col-span-8 p-6 sm:p-10 md:border-r md:border-rule">
                    <p class="font-display text-2xl font-extrabold text-ink tracking-[-0.02em] leading-tight">
                        {{ __('search.console_concierge_title') }}<span class="text-amber">.</span>
                    </p>
                    <p class="mt-3 text-base text-body leading-relaxed max-w-xl">
                        {{ __('search.console_concierge_body', ['hours' => $inquiryHours]) }}
                    </p>
                    <button type="button"
                            x-on:click="window.dispatchEvent(new CustomEvent('open-inquiry-modal'))"
                            class="mt-6 group inline-flex items-center justify-center gap-3
                                   px-6 py-3.5
                                   bg-ink text-ivory font-sans text-[12px] font-bold uppercase tracking-[0.22em]
                                   border border-ink
                                   hover:bg-amber hover:text-ink
                                   transition-colors duration-150">
                        <x-heroicon-o-paper-airplane class="w-4 h-4" aria-hidden="true" />
                        {{ __('search.console_concierge_cta') }}
                        <x-heroicon-s-arrow-long-right class="w-4 h-4 transform transition-transform group-hover:translate-x-1" aria-hidden="true" />
                    </button>
                </div>
                <div class="col-span-12 md:col-span-4 p-6 sm:p-8 bg-ivory-alt">
                    <p class="bp-spec text-amber-ink mb-4">{{ __('search.console_need_heading') }}</p>
                    <ul class="space-y-3 text-sm text-body">
                        <li class="flex items-start gap-2.5">
                            <span class="font-mono text-[10px] tabular-nums tracking-[0.18em] text-ink-muted mt-1">01</span>
                            <span>{{ __('search.console_need_oem') }}</span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <span class="font-mono text-[10px] tabular-nums tracking-[0.18em] text-ink-muted mt-1">02</span>
                            <span>{{ __('search.console_need_vehicle') }}</span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <span class="font-mono text-[10px] tabular-nums tracking-[0.18em] text-ink-muted mt-1">03</span>
                            <span>{{ __('search.console_need_vin') }}</span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <span class="font-mono text-[10px] tabular-nums tracking-[0.18em] text-ink-muted mt-1">04</span>
                            <span>{{ __('search.console_need_quantity') }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </section>

    </div>
</div>

{{-- Part Inquiry Modal — listens for `open-inquiry-modal` dispatched by the
     concierge CTA above. Without this component the button click is a no-op. --}}
<x-modals.part-inquiry :normalized-query="''" />

@endsection
