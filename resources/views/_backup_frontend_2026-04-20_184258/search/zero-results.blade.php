@extends('layouts.app')

@php
    $lang = app()->getLocale();
    $siteName = settings('general.site_name', 'OEMHub');
    $inquiryHours = (int) settings('part_inquiry.response_hours', 24);
    $zeroJsonLd = [
        '@context' => 'https://schema.org',
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

{{-- ── Content ──────────────────────────────────────────────────────────── --}}
@section('content')

<div class="min-h-screen bg-bg-page pb-24">

    {{-- ── Navy search strip (matches site language) ────────────────── --}}
    <div class="bg-gradient-to-b from-navy to-blue-900 border-b border-white/10">
        <div class="max-w-5xl mx-auto px-4 py-5"
             x-data="{
                 q: '{{ $normalized_query }}',
                 submit() {
                     const oem = this.q.trim().replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
                     if (oem.length >= {{ settings('search.min_chars', 3) }}) {
                         window.location.href = '/{{ $lang }}/parts/' + oem;
                     }
                 }
             }"
        >
            <form @submit.prevent="submit" class="flex items-center gap-2">
                @csrf
                <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
                <div class="flex-1 flex items-center gap-3 bg-white rounded-full px-5 py-2.5 border border-white/20 shadow-sm">
                    <x-heroicon-o-magnifying-glass class="w-5 h-5 text-navy shrink-0" />
                    <input type="text"
                           x-model="q"
                           placeholder="{{ __('search.mini_search_placeholder') }}"
                           autocomplete="off"
                           autocapitalize="characters"
                           inputmode="text"
                           class="flex-1 text-navy font-mono font-bold text-sm uppercase
                                  placeholder:normal-case placeholder:font-sans placeholder:font-medium placeholder:text-gray-400
                                  border-0 focus:outline-none focus:ring-0 p-0 bg-transparent">
                    <button type="button"
                            x-show="q.length > 0"
                            x-cloak
                            @click="q = ''"
                            class="text-gray-400 hover:text-red-500 shrink-0">
                        <x-heroicon-o-x-mark class="w-4 h-4" />
                    </button>
                </div>
                <button type="submit"
                        class="shrink-0 inline-flex items-center justify-center gap-1.5 px-5 py-2.5 bg-amber text-navy font-extrabold text-sm rounded-full hover:bg-amber/90 transition-colors">
                    <span class="hidden sm:inline">{{ __('search.mini_search_button') }}</span>
                    <x-heroicon-m-magnifying-glass class="w-4 h-4" />
                </button>
            </form>
        </div>
    </div>

    <div class="max-w-3xl mx-auto px-4 mt-10">

        {{-- ── Hero ──────────────────────────────────────────────────── --}}
        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-white border border-amber/30 shadow-sm mb-5">
                <x-heroicon-o-magnifying-glass class="w-8 h-8 text-amber-text" />
            </div>
            <p class="text-[11px] font-bold text-amber-text uppercase tracking-widest mb-2">
                {{ __('search.zero_eyebrow') }}
            </p>
            <h1 class="font-display text-3xl md:text-4xl font-extrabold text-navy leading-tight mb-3">
                {{ __('search.zero_heading_new') }}
            </h1>
            <p class="text-sm text-muted">
                <span>{{ __('search.zero_searched_for') }}:</span>
                <span class="font-mono text-navy bg-amber/10 border border-amber/20 px-2.5 py-0.5 rounded-lg font-bold ml-1">{{ $normalized_query }}</span>
            </p>
        </div>

        {{-- ── Inquiry concierge card (primary) ───────────────────────── --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-6">
            {{-- Header strip --}}
            <div class="bg-gradient-to-r from-amber/10 via-amber/5 to-transparent px-6 sm:px-8 py-5 border-b border-amber/15">
                <div class="flex items-start gap-4">
                    <div class="w-11 h-11 rounded-xl bg-amber flex items-center justify-center shrink-0 shadow-sm">
                        <x-heroicon-o-paper-airplane class="w-5 h-5 text-navy" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <h2 class="font-display text-lg sm:text-xl font-bold text-navy mb-1">
                            {{ __('search.zero_cta_title') }}
                        </h2>
                        <p class="text-sm text-body leading-relaxed">
                            {{ __('search.zero_cta_subtitle', ['hours' => $inquiryHours]) }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Body --}}
            <div class="px-6 sm:px-8 py-6">
                {{-- What happens next (3-step timeline) --}}
                <ol class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
                    @foreach([
                        ['1', __('search.zero_step_1_title'), __('search.zero_step_1_body')],
                        ['2', __('search.zero_step_2_title'), __('search.zero_step_2_body')],
                        ['3', __('search.zero_step_3_title'), __('search.zero_step_3_body', ['hours' => $inquiryHours])],
                    ] as [$n, $title, $body])
                    <li class="flex items-start gap-3 p-3.5 rounded-xl bg-bg-page border border-gray-100">
                        <span class="w-7 h-7 rounded-lg bg-navy text-white text-xs font-extrabold flex items-center justify-center shrink-0 shadow-sm">{{ $n }}</span>
                        <div class="min-w-0">
                            <p class="text-xs font-bold text-navy leading-tight">{{ $title }}</p>
                            <p class="text-[11px] text-muted mt-1 leading-snug">{{ $body }}</p>
                        </div>
                    </li>
                    @endforeach
                </ol>

                {{-- Primary CTA --}}
                <button type="button"
                        onclick="window.dispatchEvent(new CustomEvent('open-inquiry-modal', { detail: { oem: {{ json_encode($normalized_query) }} } }))"
                        class="w-full inline-flex items-center justify-center gap-2 px-8 py-3.5 rounded-xl bg-amber text-navy font-extrabold text-sm shadow-sm hover:bg-amber/90 hover:shadow-md transition-all">
                    <x-heroicon-o-paper-airplane class="w-4 h-4" />
                    {{ __('search.inquiry_submit') }}
                </button>

                {{-- Trust signals --}}
                <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-2 mt-4 text-[11px] font-medium text-muted">
                    <span class="inline-flex items-center gap-1.5">
                        <x-heroicon-s-shield-check class="h-3.5 w-3.5 text-emerald-600" />
                        {{ __('search.inquiry_trust_suppliers') }}
                    </span>
                    <span class="text-gray-300">·</span>
                    <span class="inline-flex items-center gap-1.5">
                        <x-heroicon-s-building-storefront class="h-3.5 w-3.5 text-blue-600" />
                        {{ __('search.inquiry_trust_warehouse') }}
                    </span>
                    <span class="text-gray-300">·</span>
                    <span class="inline-flex items-center gap-1.5">
                        <x-heroicon-s-trophy class="h-3.5 w-3.5 text-amber-text" />
                        {{ __('search.inquiry_trust_quality') }}
                    </span>
                </div>
            </div>
        </div>

        {{-- ── Secondary: Popular OEMs ────────────────────────────────── --}}
        @if($popularOems->isNotEmpty())
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
            <h2 class="text-xs font-bold text-muted uppercase tracking-widest mb-3 flex items-center gap-2">
                <x-heroicon-s-fire class="w-3.5 h-3.5 text-amber-text" />
                {{ __('search.zero_popular_heading') }}
            </h2>
            <div class="flex flex-wrap gap-2">
                @foreach($popularOems as $oem)
                <a href="{{ route('frontend.search.results', ['lang' => $lang, 'oem' => $oem]) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-bg-page hover:bg-amber/10 border border-gray-200 hover:border-amber/30 rounded-lg transition-colors">
                    <span class="font-mono text-xs font-semibold text-navy">{{ $oem }}</span>
                    <x-heroicon-o-arrow-right class="w-3 h-3 text-gray-400" />
                </a>
                @endforeach
            </div>
        </div>
        @endif

        {{-- ── Tips (footer helper) ──────────────────────────────────── --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
            <p class="text-xs font-bold text-muted uppercase tracking-widest mb-3 flex items-center gap-2">
                <x-heroicon-o-light-bulb class="w-3.5 h-3.5 text-amber-text" />
                {{ __('search.zero_tips_heading') }}
            </p>
            <ul class="space-y-2">
                @foreach(['zero_tip_1', 'zero_tip_2', 'zero_tip_3', 'zero_tip_4'] as $tipKey)
                <li class="flex items-start gap-2 text-xs text-body leading-relaxed">
                    <x-heroicon-s-check-circle class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" />
                    {{ __('search.' . $tipKey) }}
                </li>
                @endforeach
            </ul>
        </div>

        {{-- ── Back to Home ─────────────────────────────────────────── --}}
        <div class="text-center">
            <a href="/{{ $lang }}/"
               class="inline-flex items-center gap-2 text-xs font-semibold text-muted hover:text-navy transition-colors">
                <x-heroicon-o-arrow-left class="w-3.5 h-3.5" />
                {{ __('search.zero_back') }}
            </a>
        </div>

    </div>
</div>

<x-modals.part-inquiry :normalized-query="$normalized_query ?? ''" />

@endsection
