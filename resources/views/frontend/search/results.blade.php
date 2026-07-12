@extends('layouts.app')

@php
    $price_stats = is_array($price_stats ?? null)
        ? $price_stats
        : ['min' => null, 'max' => null, 'avg' => null, 'avg_numeric' => null];
    $siteName = settings('general.site_name', 'OeParts');
    $oemForSeo = $normalized_query ?? '';
    $countForSeo = number_format($total ?? 0);
    $titleTpl = trim((string) settings('seo.search_results_title_template', ''));
    if ($titleTpl !== '') {
        $searchResultsPageTitle = str_replace(
            ['{oem}', '{count}', '{site}', '{min}', '{max}'],
            [$oemForSeo, $countForSeo, $siteName, (string) ($price_stats['min'] ?? ''), (string) ($price_stats['max'] ?? '')],
            $titleTpl
        );
    } else {
        $searchResultsPageTitle = ui_copy('search_page_title', 'search.page_title', [
            'oem' => $oemForSeo,
            'count' => $countForSeo,
            'result_word' => ($total ?? 0) === 1 ? ui_copy('search_result_word_single', 'search.result_word_single') : ui_copy('search_result_word_plural', 'search.result_word_plural'),
            'site' => $siteName,
        ]);
    }
    $metaTpl = trim((string) settings('seo.search_results_meta_template', ''));
    if ($metaTpl !== '') {
        $searchResultsMetaDescription = str_replace(
            ['{oem}', '{count}', '{site}', '{min}', '{max}'],
            [$oemForSeo, $countForSeo, $siteName, (string) ($price_stats['min'] ?? ''), (string) ($price_stats['max'] ?? '')],
            $metaTpl
        );
    } else {
        $searchResultsMetaDescription = ui_copy('search_meta_description', 'search.meta_description', ['count' => $countForSeo, 'oem' => $oemForSeo]);
    }
    $searchResultsOgDescription = \Illuminate\Support\Str::limit(strip_tags($searchResultsMetaDescription), 300, '');
@endphp

{{-- ── SEO ──────────────────────────────────────────────────────────────── --}}
@section('title'){{ $searchResultsPageTitle }}@endsection
@section('meta_description'){{ $searchResultsMetaDescription }}@endsection
@section('og_title'){{ $searchResultsPageTitle }}@endsection
@section('og_description'){{ $searchResultsOgDescription }}@endsection

@if(($search_type ?? '') === 'partial')
@section('meta_robots')
    <meta name="robots" content="noindex,follow">
@endsection
@endif

@section('canonical')
    <link rel="canonical" href="{{ route('frontend.search.results', ['lang' => app()->getLocale(), 'oem' => $normalized_query]) }}">
@endsection

@section('hreflang')
    @foreach(['en', 'de', 'lt', 'fr', 'es'] as $hLang)
        <link rel="alternate" hreflang="{{ $hLang }}" href="{{ route('frontend.search.results', ['lang' => $hLang, 'oem' => $normalized_query]) }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ route('frontend.search.results', ['lang' => 'en', 'oem' => $normalized_query]) }}">
@endsection

@section('json_ld')
@php
    $jsonLdItems = [];
    $iterProducts = $products instanceof \Illuminate\Contracts\Pagination\Paginator
        ? $products->items() : $products;
    foreach ($iterProducts as $index => $product) {
        $jsonLdItems[] = [
            '@type' => 'ListItem',
            'position' => $index + 1,
            'item' => [
                '@type' => 'Product',
                'name' => $product->oem_number,
                'sku' => $product->oem_number,
                'url' => url('/'.app()->getLocale().'/parts/'.urlencode($product->oem_number)),
                'brand' => ['@type' => 'Brand', 'name' => $product->manufacturer ? trans_field($product->manufacturer->name) : ui_copy('search_unknown_brand', 'search.unknown_brand')],
                'offers' => [
                    '@type' => 'Offer',
                    'price' => (string) $product->price,
                    'priceCurrency' => settings('store.currency', 'EUR'),
                    'availability' => $product->is_in_stock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                ],
            ],
        ];
    }
    $jsonLdSchema = [
        '@@context' => 'https://schema.org',
        '@type' => 'ItemList',
        'numberOfItems' => (int) ($total ?? count($jsonLdItems)),
        'itemListElement' => $jsonLdItems,
    ];
@endphp
@if(!empty($jsonLdItems))
<script type="application/ld+json">{!! json_encode($jsonLdSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endif
@endsection

@section('og_type', 'product.group')

{{-- ── Content ──────────────────────────────────────────────────────────── --}}
@section('content')
@php
    $lang = app()->getLocale();
    $car_model_filter = $car_model_filter ?? null;
    $car_model_filter_label = $car_model_filter_label ?? null;

    $matchBadge = match($search_type) {
        'exact'           => ['label' => ui_copy('search_match_exact', 'search.match_exact'),   'icon' => 'check-circle',        'code' => 'EXACT'],
        'cross_reference' => ['label' => ui_copy('search_match_cross', 'search.match_cross'),   'icon' => 'arrow-path',          'code' => 'CROSS-REF'],
        'partial'         => ['label' => ui_copy('search_match_partial', 'search.match_partial'), 'icon' => 'magnifying-glass',    'code' => 'PARTIAL'],
        default           => ['label' => ui_copy('search_match_default', 'search.match_default'), 'icon' => 'check-circle',        'code' => 'MATCH'],
    };

    $vatMultiplier = bcadd('1', bcdiv((string) $vat_rate, '100', 4), 4);

    $activeFilterCount = ($condition_filter ? 1 : 0)
                       + ($in_stock_only ? 1 : 0)
                       + ($sort !== 'default' ? 1 : 0)
                       + ($manufacturer_filter ? 1 : 0)
                       + ($car_model_filter ? 1 : 0);

    $conditionLabels = $conditions->pluck('name', 'slug')->toArray();
@endphp

<div class="relative min-h-screen bg-ivory text-ink pb-20">
    <div class="fixed inset-0 bg-grid-ivory-fine bg-grid-md opacity-40 pointer-events-none" aria-hidden="true"></div>

    <div class="relative">

        <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-10 pt-8">

            {{-- ── Document header ─────────────────────────────────────── --}}
            <div class="flex flex-wrap items-center justify-between gap-4 pb-4 mb-6 border-b border-rule">
                <nav class="flex items-center gap-2 bp-spec-mono" aria-label="Breadcrumbs">
                    <a href="{{ url('/'.$lang.'/') }}" class="hover:text-amber-ink transition-colors">Home</a>
                    <span class="text-rule-strong">/</span>
                    <a href="{{ route('frontend.search.console', ['lang' => $lang]) }}" class="hover:text-amber-ink transition-colors">Catalogue</a>
                    <span class="text-rule-strong">/</span>
                    <span class="text-ink">Results</span>
                </nav>
                <span class="bp-spec-mono">
                    DOC · SPEC-SHEET · {{ $matchBadge['code'] }}
                </span>
            </div>

            {{-- ── Result Header: headline + query + badge ─────────────── --}}
            <div class="grid grid-cols-12 gap-x-4 sm:gap-x-6 lg:gap-x-8 gap-y-8 pb-8 mb-8 border-b border-ink">

                <div class="col-span-12 md:col-span-7">
                    <div class="flex items-center gap-4 mb-6">
                        <span class="w-10 h-[3px] bg-amber inline-block"></span>
                        <span class="bp-spec text-amber-ink">01 · {{ ui_copy('search_heading_results_for', 'search.heading_results_for') }}</span>
                    </div>

                    <h1 class="font-display font-extrabold text-ink leading-[0.9] tracking-[-0.03em]
                               text-4xl sm:text-5xl lg:text-6xl break-all">
                        {{ $normalized_query }}<span class="text-amber">.</span>
                    </h1>

                    <p class="mt-6 font-mono text-base tabular-nums text-body">
                        {{ ui_copy('search_parts_found_sentence', 'search.parts_found_sentence', [
                            'count' => number_format($total),
                            'parts_word' => $total === 1 ? ui_copy('search_part', 'search.part') : ui_copy('search_parts', 'search.parts'),
                        ]) }}
                    </p>
                </div>

                {{-- Spec Panel: match type + pagination + filters --}}
                <aside class="col-span-12 md:col-span-5">
                    <div class="bp-card-ivory p-5 h-full flex flex-col">
                        <div class="flex items-center justify-between mb-4">
                            <span class="bp-spec text-amber-ink">01.a · Query Log</span>
                            <span class="bp-spec-mono">
                                {{ now()->format('Y·m·d') }}
                            </span>
                        </div>

                        <dl class="space-y-0 flex-1">
                            <div class="flex items-baseline justify-between gap-3 py-2.5 border-b border-rule">
                                <dt class="bp-spec-mono shrink-0">{{ ui_copy('search_query_label', 'search.query_label') }}</dt>
                                <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                                <dd class="font-mono text-sm font-bold text-ink shrink-0 truncate max-w-[180px]">{{ $normalized_query }}</dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-3 py-2.5 border-b border-rule">
                                <dt class="bp-spec-mono shrink-0">{{ ui_copy('search_match_type_label', 'search.match_type_label') }}</dt>
                                <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                                <dd class="font-mono text-sm font-bold text-amber-ink shrink-0">{{ $matchBadge['code'] }}</dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-3 py-2.5 border-b border-rule">
                                <dt class="bp-spec-mono shrink-0">{{ ui_copy('search_hits_label', 'search.hits_label') }}</dt>
                                <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                                <dd class="font-mono text-sm font-bold tabular-nums text-ink shrink-0">{{ number_format($total) }}</dd>
                            </div>
                            @if($products instanceof \Illuminate\Pagination\LengthAwarePaginator && $products->hasPages())
                            <div class="flex items-baseline justify-between gap-3 py-2.5 border-b border-rule">
                                <dt class="bp-spec-mono shrink-0">{{ ui_copy('search_page_label', 'search.page_label') }}</dt>
                                <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                                <dd class="font-mono text-sm tabular-nums text-ink shrink-0">
                                    {{ $products->currentPage() }} / {{ $products->lastPage() }}
                                </dd>
                            </div>
                            @endif
                            <div class="flex items-baseline justify-between gap-3 py-2.5">
                                <dt class="bp-spec-mono shrink-0">{{ ui_copy('search_filters', 'search.filters') }}</dt>
                                <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                                <dd class="font-mono text-sm font-bold text-ink shrink-0">
                                    {{ ui_trans_choice('search_filters_active_choice', 'search.filters_active_choice', $activeFilterCount) }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </aside>
            </div>

            {{-- ── Re-submit query bar (same style & position as zero-results) ── --}}
            <section class="mb-8 bp-rise bp-rise-delay-2">
                <div class="flex items-end justify-between pb-3 border-b border-ink">
                    <span class="bp-spec text-ink">02 · {{ ui_copy('search_mini_search_label', 'search.mini_search_label') }}</span>
                    <span class="hidden sm:inline font-mono text-[10px] text-ink-muted tracking-[0.18em] uppercase">
                        min {{ settings('search.min_chars', 3) }} chars · alphanumeric
                    </span>
                </div>
                <x-search.oem-input :value="$normalized_query" />
            </section>

            {{-- ── Price Stats Strip ───────────────────────────────────── --}}
            @if($price_stats['min'] !== null && $price_stats['max'] !== null && !$filtered_empty)
            @php $priceMinMaxSame = (string) $price_stats['min'] === (string) $price_stats['max']; @endphp
            <div class="grid grid-cols-1 sm:grid-cols-3 border border-ink bg-paper mb-8">
                @foreach([
                    ['label' => ui_copy('search_price_from', 'search.price_from'), 'value' => settings('store.currency_symbol', '€') . $price_stats['min'], 'em' => false],
                    ['label' => ui_copy('search_price_avg', 'search.price_avg'),  'value' => settings('store.currency_symbol', '€') . ($priceMinMaxSame ? $price_stats['min'] : $price_stats['avg']), 'em' => true],
                    ['label' => ui_copy('search_price_to', 'search.price_to'),   'value' => settings('store.currency_symbol', '€') . $price_stats['max'], 'em' => false],
                ] as $idx => $stat)
                <div class="p-5 sm:p-6 {{ !$loop->last ? 'border-b sm:border-b-0 sm:border-r border-rule' : '' }}">
                    <p class="bp-spec text-ink-muted mb-3">{{ $stat['label'] }}</p>
                    <p class="font-mono font-medium text-ink tabular-nums leading-none tracking-tight
                              text-3xl sm:text-4xl lg:text-5xl
                              {{ $stat['em'] ? 'text-amber-ink' : '' }}">
                        {{ $stat['value'] }}
                    </p>
                    @if($stat['em'])
                    <div class="mt-3 h-[2px] w-8 bg-amber"></div>
                    @endif
                </div>
                @endforeach
            </div>
            @endif

            {{-- ── Notice banners ──────────────────────────────────────── --}}
            @if($search_type === 'cross_reference')
            <div class="mb-6 flex items-start gap-4 p-5 border border-ink bg-ivory-alt">
                <div class="w-9 h-9 border border-ink flex items-center justify-center shrink-0 bg-paper">
                    <x-heroicon-o-arrow-path class="w-4 h-4 text-ink" />
                </div>
                <div>
                    <p class="bp-spec text-amber-ink mb-1">Notice · Cross-Reference</p>
                    <p class="font-display text-base font-bold text-ink mb-1">{{ ui_copy('search_notice_cross_title', 'search.notice_cross_title') }}</p>
                    <p class="text-sm text-body leading-relaxed">{{ ui_copy('search_notice_cross_body', 'search.notice_cross_body', ['oem' => $normalized_query]) }}</p>
                </div>
            </div>
            @endif

            @if($search_type === 'partial')
            <div class="mb-6 flex items-start gap-4 p-5 border border-amber bg-amber/10">
                <div class="w-9 h-9 border border-amber-ink flex items-center justify-center shrink-0 bg-paper">
                    <x-heroicon-o-magnifying-glass class="w-4 h-4 text-amber-ink" />
                </div>
                <div>
                    <p class="bp-spec text-amber-ink mb-1">Notice · Partial Match</p>
                    <p class="font-display text-base font-bold text-ink mb-1">{{ ui_copy('search_notice_partial_title', 'search.notice_partial_title') }}</p>
                    <p class="text-sm text-body leading-relaxed">{{ ui_copy('search_notice_partial_body', 'search.notice_partial_body', ['oem' => $normalized_query]) }}</p>
                </div>
            </div>
            @endif

            {{-- ════════════════════════════════════════════════════════════ --}}
            {{-- SORT & FILTER BAR                                            --}}
            {{-- ════════════════════════════════════════════════════════════ --}}
            <div class="sticky top-0 z-40 -mx-4 sm:-mx-6 lg:-mx-10 px-4 sm:px-6 lg:px-10 pt-0 pb-4 bg-ivory/95 backdrop-blur-md"
                 x-data="{
                     sort: '{{ $sort ?? 'default' }}',
                     condition: '{{ $condition_filter ?? '' }}',
                     inStock: {{ isset($in_stock_only) && $in_stock_only ? 'true' : 'false' }},
                     manufacturer: '{{ $manufacturer_filter ?? '' }}',
                     carModel: '{{ $car_model_filter ?? '' }}',
                     loading: false,
                     filterOpen: false,
                     apply() {
                         this.loading = true;
                         const url = new URL(window.location.href);
                         this.sort !== 'default' ? url.searchParams.set('sort', this.sort) : url.searchParams.delete('sort');
                         this.condition ? url.searchParams.set('condition', this.condition) : url.searchParams.delete('condition');
                         this.inStock ? url.searchParams.set('in_stock', '1') : url.searchParams.delete('in_stock');
                         this.manufacturer ? url.searchParams.set('manufacturer', this.manufacturer) : url.searchParams.delete('manufacturer');
                         this.carModel ? url.searchParams.set('model', this.carModel) : url.searchParams.delete('model');
                         url.searchParams.delete('page');
                         window.location.href = url.toString();
                     }
                 }">
                <div class="relative border border-ink bg-paper">
                    {{-- Loading overlay --}}
                    <div x-show="loading" x-cloak class="absolute inset-0 bg-paper/90 backdrop-blur-sm flex items-center justify-center z-50">
                        <div class="flex items-center gap-3 text-ink">
                            <svg class="animate-spin h-5 w-5 text-amber-ink" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <span class="font-mono text-[11px] font-bold uppercase tracking-[0.22em]">{{ ui_copy('search_updating_results', 'search.updating_results') }}</span>
                        </div>
                    </div>

                    {{-- Header row --}}
                    <div class="flex items-center justify-between px-4 py-2 border-b border-rule bg-ivory-alt">
                        <span class="bp-spec text-ink">03 · Filters & Sort</span>
                        @if($activeFilterCount > 0)
                        <a href="{{ route('frontend.search.results', ['lang' => $lang, 'oem' => $normalized_query]) }}"
                           class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted hover:text-red-600 transition-colors">
                            {{ ui_copy('search_clear_all', 'search.clear_all') }}
                            <x-heroicon-s-x-mark class="w-3 h-3 inline-block" />
                        </a>
                        @endif
                    </div>

                    <div class="p-4 flex flex-wrap items-center gap-x-4 gap-y-3">
                        {{-- Sort --}}
                        <div class="flex items-center gap-2">
                            <span class="bp-spec text-ink-muted">Sort</span>
                            <div class="flex border border-ink">
                                <button type="button" @click="sort = 'default'; apply()"
                                        :class="sort === 'default' ? 'bg-ink text-ivory' : 'bg-paper text-ink hover:bg-ivory'"
                                        class="px-3 py-1.5 font-mono text-[11px] font-bold uppercase tracking-[0.14em] transition-colors"
                                        :aria-pressed="sort === 'default'">
                                    {{ ui_copy('search_sort_relevance', 'search.sort_relevance') }}
                                </button>
                                <button type="button" @click="sort = 'price_asc'; apply()"
                                        :class="sort === 'price_asc' ? 'bg-ink text-ivory' : 'bg-paper text-ink hover:bg-ivory'"
                                        class="px-3 py-1.5 font-mono text-[11px] font-bold uppercase tracking-[0.14em] border-l border-ink transition-colors inline-flex items-center gap-1"
                                        :aria-pressed="sort === 'price_asc'">
                                    <x-heroicon-s-arrow-small-up class="w-3 h-3" />
                                    {{ settings('store.currency_symbol', '€') }}
                                </button>
                                <button type="button" @click="sort = 'price_desc'; apply()"
                                        :class="sort === 'price_desc' ? 'bg-ink text-ivory' : 'bg-paper text-ink hover:bg-ivory'"
                                        class="px-3 py-1.5 font-mono text-[11px] font-bold uppercase tracking-[0.14em] border-l border-ink transition-colors inline-flex items-center gap-1"
                                        :aria-pressed="sort === 'price_desc'">
                                    <x-heroicon-s-arrow-small-down class="w-3 h-3" />
                                    {{ settings('store.currency_symbol', '€') }}
                                </button>
                            </div>
                        </div>

                        {{-- Condition (desktop) --}}
                        <div class="hidden sm:flex items-center gap-2">
                            <span class="w-px h-5 bg-rule"></span>
                            <span class="bp-spec text-ink-muted">{{ ui_copy('search_condition', 'search.condition') }}</span>
                            @foreach($conditionLabels as $val => $label)
                            @php $cnt = $condition_counts[$val] ?? 0; @endphp
                            @if($cnt > 0)
                            <button type="button"
                                    @click="condition = (condition === '{{ $val }}' ? '' : '{{ $val }}'); apply()"
                                    :class="condition === '{{ $val }}' ? 'bg-ink text-ivory border-ink' : 'bg-paper text-ink border-rule-strong hover:border-ink'"
                                    class="px-3 py-1.5 border font-mono text-[11px] font-bold uppercase tracking-[0.14em] transition-colors inline-flex items-center gap-1.5"
                                    :aria-pressed="condition === '{{ $val }}'">
                                {{ $label }}
                                <span class="font-mono text-[9px] tabular-nums opacity-70">({{ $cnt }})</span>
                            </button>
                            @endif
                            @endforeach
                        </div>

                        {{-- In Stock --}}
                        <div class="hidden sm:flex items-center gap-2">
                            <span class="w-px h-5 bg-rule"></span>
                            <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                                <input type="checkbox" class="sr-only peer" x-model="inStock" @change="apply()">
                                <span class="w-4 h-4 border border-ink flex items-center justify-center transition-colors"
                                      :class="inStock ? 'bg-ink' : 'bg-paper'">
                                    <x-heroicon-s-check class="w-3 h-3 text-ivory" x-show="inStock" />
                                </span>
                                <span class="font-mono text-[11px] font-bold uppercase tracking-[0.14em] text-ink">{{ ui_copy('search_in_stock', 'search.in_stock') }}</span>
                            </label>
                        </div>

                        {{-- Mobile filter toggle --}}
                        <button type="button"
                                @click="filterOpen = !filterOpen"
                                class="sm:hidden ml-auto inline-flex items-center gap-2 px-3 py-1.5 border border-ink bg-paper
                                       font-mono text-[11px] font-bold uppercase tracking-[0.14em] text-ink">
                            <x-heroicon-o-funnel class="w-3 h-3" />
                            {{ ui_copy('search_filters', 'search.filters') }}
                            @if($activeFilterCount > 0)
                            <span class="font-mono text-[9px] tabular-nums px-1 bg-amber text-ink">{{ $activeFilterCount }}</span>
                            @endif
                        </button>
                    </div>

                    {{-- Mobile drawer --}}
                    <div x-show="filterOpen" x-cloak
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-2"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="sm:hidden border-t border-rule px-4 py-4 space-y-5">

                        <div>
                            <p class="bp-spec text-ink-muted mb-2">{{ ui_copy('search_condition', 'search.condition') }}</p>
                            <div class="flex gap-1.5 flex-wrap">
                                @foreach($conditionLabels as $val => $label)
                                @php $cnt = $condition_counts[$val] ?? 0; @endphp
                                @if($cnt > 0)
                                <button type="button"
                                        @click="condition = (condition === '{{ $val }}' ? '' : '{{ $val }}'); apply()"
                                        :class="condition === '{{ $val }}' ? 'bg-ink text-ivory border-ink' : 'bg-paper text-ink border-rule-strong'"
                                        class="px-3 py-1.5 border font-mono text-[11px] font-bold uppercase tracking-[0.14em]">
                                    {{ $label }} ({{ $cnt }})
                                </button>
                                @endif
                                @endforeach
                            </div>
                        </div>

                        <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" class="sr-only peer" x-model="inStock" @change="apply()">
                            <span class="w-4 h-4 border border-ink flex items-center justify-center"
                                  :class="inStock ? 'bg-ink' : 'bg-paper'">
                                <x-heroicon-s-check class="w-3 h-3 text-ivory" x-show="inStock" />
                            </span>
                            <span class="font-mono text-[11px] font-bold uppercase tracking-[0.14em] text-ink">{{ ui_copy('search_in_stock', 'search.in_stock') }}</span>
                        </label>

                        @if(count($manufacturer_filter_options) > 1)
                        <div>
                            <p class="bp-spec text-ink-muted mb-2">{{ ui_copy('search_brand', 'search.brand') }}</p>
                            <div class="flex gap-1.5 flex-wrap">
                                @foreach($manufacturer_filter_options as $mfr)
                                <button type="button"
                                        @click="manufacturer = (manufacturer == '{{ $mfr['id'] }}' ? '' : '{{ $mfr['id'] }}'); carModel = ''; apply()"
                                        :class="manufacturer == '{{ $mfr['id'] }}' ? 'bg-ink text-ivory border-ink' : 'bg-paper text-ink border-rule-strong'"
                                        class="px-3 py-1.5 border font-mono text-[11px] font-bold uppercase tracking-[0.14em]">
                                    {{ $mfr['name'] }} ({{ $mfr['count'] }})
                                </button>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </div>

                    {{-- Manufacturer (desktop) --}}
                    @if(count($manufacturer_filter_options) > 1)
                    <div class="hidden sm:flex flex-wrap items-center gap-2 px-4 py-3 border-t border-rule">
                        <span class="bp-spec text-ink-muted shrink-0">{{ ui_copy('search_brand', 'search.brand') }}</span>
                        @foreach($manufacturer_filter_options as $mfr)
                        <button type="button"
                                @click="manufacturer = (manufacturer == '{{ $mfr['id'] }}' ? '' : '{{ $mfr['id'] }}'); carModel = ''; apply()"
                                :class="manufacturer == '{{ $mfr['id'] }}' ? 'bg-ink text-ivory border-ink' : 'bg-paper text-ink border-rule-strong hover:border-ink'"
                                class="px-3 py-1.5 border font-mono text-[11px] font-bold uppercase tracking-[0.14em] transition-colors inline-flex items-center gap-1.5"
                                :aria-pressed="manufacturer == '{{ $mfr['id'] }}'">
                            {{ $mfr['name'] }}
                            <span class="font-mono text-[9px] tabular-nums opacity-70">({{ $mfr['count'] }})</span>
                        </button>
                        @endforeach
                    </div>
                    @endif

                    {{-- Active filter chips (sort, condition, stock, brand, car model) --}}
                    @if($activeFilterCount > 0)
                    <div class="px-4 py-3 border-t border-rule bg-ivory-alt/60">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted shrink-0">{{ ui_copy('search_active_filters', 'search.active_filters') }}</span>
                            @if($sort !== 'default')
                            <a href="{{ request()->fullUrlWithQuery(['sort' => null, 'page' => null]) }}"
                               class="inline-flex items-center gap-1.5 px-2.5 py-1 border border-ink bg-paper font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-ink hover:bg-ivory transition-colors">
                                {{ ui_copy('search_sort_label', 'search.sort_label') }} {{ $sort === 'price_asc' ? ui_copy('search_sort_chip_price_asc', 'search.sort_chip_price_asc') : ui_copy('search_sort_chip_price_desc', 'search.sort_chip_price_desc') }}
                                <x-heroicon-s-x-mark class="w-3 h-3 shrink-0" />
                            </a>
                            @endif
                            @if($condition_filter)
                            <a href="{{ request()->fullUrlWithQuery(['condition' => null, 'page' => null]) }}"
                               class="inline-flex items-center gap-1.5 px-2.5 py-1 border border-ink bg-paper font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-ink hover:bg-ivory transition-colors">
                                {{ ui_copy('search_condition_chip', 'search.condition_chip', ['condition' => $conditionLabels[$condition_filter] ?? ucfirst($condition_filter)]) }}
                                <x-heroicon-s-x-mark class="w-3 h-3 shrink-0" />
                            </a>
                            @endif
                            @if($in_stock_only)
                            <a href="{{ request()->fullUrlWithQuery(['in_stock' => null, 'page' => null]) }}"
                               class="inline-flex items-center gap-1.5 px-2.5 py-1 border border-ink bg-paper font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-ink hover:bg-ivory transition-colors">
                                {{ ui_copy('search_in_stock_only', 'search.in_stock_only') }}
                                <x-heroicon-s-x-mark class="w-3 h-3 shrink-0" />
                            </a>
                            @endif
                            @if($manufacturer_filter)
                            @php
                                $mfrRow = collect($manufacturer_filter_options)->first(fn ($m) => (string) $m['id'] === (string) $manufacturer_filter);
                                $mfrLabel = $mfrRow['name'] ?? ui_copy('search_brand', 'search.brand');
                            @endphp
                            <a href="{{ request()->fullUrlWithQuery(['manufacturer' => null, 'model' => null, 'page' => null]) }}"
                               class="inline-flex items-center gap-1.5 px-2.5 py-1 border border-ink bg-paper font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-ink hover:bg-ivory transition-colors">
                                {{ $mfrLabel }}
                                <x-heroicon-s-x-mark class="w-3 h-3 shrink-0" />
                            </a>
                            @endif
                            @if($car_model_filter)
                            <a href="{{ request()->fullUrlWithQuery(['model' => null, 'page' => null]) }}"
                               class="inline-flex items-center gap-1.5 px-2.5 py-1 border border-ink bg-paper font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-ink hover:bg-ivory transition-colors">
                                {{ ui_copy('search_model_chip', 'search.model_chip', ['name' => $car_model_filter_label ?? ui_copy('search_model_unknown', 'search.model_unknown')]) }}
                                <x-heroicon-s-x-mark class="w-3 h-3 shrink-0" />
                            </a>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            {{-- ════════════════════════════════════════════════════════════ --}}
            {{-- FILTERED EMPTY STATE                                        --}}
            {{-- ════════════════════════════════════════════════════════════ --}}
            @if($filtered_empty)
            <div class="bp-card p-10 my-8 text-center">
                <span class="bp-spec text-amber-ink block mb-4">Report · Filtered-Empty</span>
                <div class="inline-flex w-12 h-12 border-2 border-ink items-center justify-center mb-6">
                    <x-heroicon-o-funnel class="w-6 h-6 text-ink" />
                </div>
                <h2 class="font-display text-3xl font-extrabold text-ink tracking-tight mb-4 text-balance">
                    {{ ui_copy('search_filtered_empty_title', 'search.filtered_empty_title') }}<span class="text-amber">.</span>
                </h2>
                <p class="text-body max-w-md mx-auto mb-8">
                    {{ ui_trans_choice('search_filtered_empty_paragraph', 'search.filtered_empty_paragraph', $unfiltered_total, ['oem' => $normalized_query, 'count' => $unfiltered_total]) }}
                </p>
                <a href="{{ route('frontend.search.results', ['lang' => $lang, 'oem' => $normalized_query]) }}"
                   class="bp-btn-primary">
                    <x-heroicon-s-x-mark class="w-5 h-5" />
                    {{ ui_copy('search_clear_filters_cta', 'search.clear_filters_cta', ['total' => $unfiltered_total]) }}
                </a>
            </div>
            @endif

            {{-- ════════════════════════════════════════════════════════════ --}}
            {{-- PRODUCT LISTING                                              --}}
            {{-- ════════════════════════════════════════════════════════════ --}}
            @if(!$filtered_empty)
            <div class="mt-6">

                {{-- ═══ TABLE VIEW (Desktop) ══════════════════════════════ --}}
                <div class="hidden lg:block">
                    <div class="border border-ink bg-paper overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[900px] table-auto border-collapse text-sm" role="table">
                                <caption class="sr-only">{{ ui_copy('search_table_caption', 'search.table_caption', ['oem' => $normalized_query]) }}</caption>
                                <thead>
                                    <tr class="bg-ivory-alt border-b border-ink">
                                        <th class="text-left px-5 py-3 font-mono text-[10px] font-bold uppercase tracking-[0.22em] text-ink align-middle" scope="col">
                                            <span class="flex items-center gap-2">
                                                <span class="font-mono text-[10px] text-ink-muted">№</span>
                                                {{ ui_copy('search_th_oem_brand', 'search.th_oem_brand') }}
                                            </span>
                                        </th>
                                        <th class="text-center px-4 py-3 font-mono text-[10px] font-bold uppercase tracking-[0.22em] text-ink align-middle whitespace-nowrap" scope="col">{{ ui_copy('search_th_condition', 'search.th_condition') }}</th>
                                        <th class="text-center px-4 py-3 font-mono text-[10px] font-bold uppercase tracking-[0.22em] text-ink align-middle whitespace-nowrap" scope="col">{{ ui_copy('search_th_stock', 'search.th_stock') }}</th>
                                        <th class="text-right px-4 py-3 font-mono text-[10px] font-bold uppercase tracking-[0.22em] text-ink align-middle whitespace-nowrap" scope="col">{{ ui_copy('search_th_price', 'search.th_price') }} · {{ settings('store.currency_symbol', '€') }}</th>
                                        <th class="text-center px-5 py-3 font-mono text-[10px] font-bold uppercase tracking-[0.22em] text-ink align-middle whitespace-nowrap" scope="col">{{ ui_copy('search_th_action', 'search.th_action') }}</th>
                                    </tr>
                                </thead>
                                @foreach($products as $index => $product)
                                    @php
                                        $manufacturer = $product->manufacturer;
                                        $logoPath     = $manufacturer?->logo?->file_path;
                                        $crossRefs    = $product->crossReferences ?? collect();
                                        $priceWithVat = bcmul((string) $product->price, $vatMultiplier, 2);
                                        $cCond = $product->condition;
                                        $condKey = $cCond?->slug ?? 'new';
                                        $condLabel = $cCond?->name ?? 'New';
                                        $condBg = $cCond?->bg_color ?? '#DCFCE7';
                                        $condText = $cCond?->text_color ?? '#16A34A';
                                        $rowNum = str_pad($index + 1, 3, '0', STR_PAD_LEFT);
                                        if ($products instanceof \Illuminate\Pagination\LengthAwarePaginator) {
                                            $rowNum = str_pad(($products->firstItem() + $index), 3, '0', STR_PAD_LEFT);
                                        }
                                    @endphp

                                <tbody
                                    class="border-b border-rule transition-colors {{ $index === 0 && $sort === 'default' ? 'bg-amber/5' : '' }} hover:bg-ivory-alt/50"
                                    x-data="searchProductRow({ productId: {{ $product->id }}, oemNumber: '{{ $product->oem_number }}', manufacturerName: '{{ $manufacturer ? trans_field($manufacturer->name) : ui_copy('search_unknown_brand', 'search.unknown_brand') }}' })">

                                    @if($index === 0 && $sort === 'default')
                                    <tr>
                                        <td colspan="5" class="px-5 pt-3 pb-0">
                                            <div class="flex items-center gap-2">
                                                <span class="w-6 h-[2px] bg-amber"></span>
                                                <span class="font-mono text-[10px] font-bold uppercase tracking-[0.22em] text-amber-ink">
                                                    {{ ui_copy('search_best_match_row', 'search.best_match_row', ['row' => $rowNum]) }}
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                    @endif

                                    <tr class="transition-colors">
                                        {{-- Row number + OEM + Brand --}}
                                        <td class="px-5 py-4 align-middle min-w-0">
                                            <div class="flex items-center gap-4 min-w-0">
                                                <span class="font-mono text-[11px] tabular-nums text-ink-muted shrink-0 w-10">
                                                    {{ $rowNum }}
                                                </span>
                                                @if($logoPath)
                                                <img src="{{ asset('storage/' . $logoPath) }}"
                                                     alt="{{ trans_field($manufacturer->name) }}"
                                                     class="h-9 w-9 shrink-0 object-contain border border-rule p-1 bg-paper"
                                                     loading="lazy" width="36" height="36">
                                                @else
                                                <div class="w-9 h-9 border border-rule flex items-center justify-center shrink-0 bg-ivory-alt">
                                                    <x-heroicon-o-building-office-2 class="w-4 h-4 text-ink-muted" />
                                                </div>
                                                @endif
                                                <div class="min-w-0" x-data="clipboard()">
                                                    <button type="button" class="appearance-none bg-transparent border-0 p-0 m-0 font-mono text-base font-bold text-ink tabular-nums truncate cursor-pointer focus:outline-none focus:ring-2 focus:ring-inset focus:ring-amber-ink rounded-sm"
                                                       @click="copy('{{ $product->oem_number }}')"
                                                       title="Copy OEM number">
                                                        {{ $product->oem_number }}
                                                    </button>
                                                    <p class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-muted truncate mt-0.5">
                                                        {{ $manufacturer ? trans_field($manufacturer->name) : ui_copy('search_unknown_brand', 'search.unknown_brand') }}
                                                    </p>
                                                    <span x-show="copied" x-cloak x-transition role="status" aria-live="polite"
                                                          class="text-[10px] font-mono font-bold text-emerald-600">Copied</span>
                                                </div>
                                            </div>
                                        </td>

                                        {{-- Condition --}}
                                        <td class="px-4 py-4 text-center align-middle">
                                            <span class="inline-flex items-center justify-center px-2 py-0.5 bp-spec-mono font-bold rounded-sm"
                                                  style="background-color: {{ $condBg }}; color: {{ $condText }};">
                                                {{ $condLabel }}
                                            </span>
                                        </td>

                                        {{-- Stock --}}
                                        <td class="px-4 py-4 text-center align-middle">
                                            @if($product->is_in_stock)
                                            <span class="inline-flex items-center gap-1.5 font-mono text-[10px] font-bold uppercase tracking-[0.18em] text-ink">
                                                <span class="w-2 h-2 bg-amber"></span>
                                                {{ ui_copy('search_stock_in', 'search.stock_in') }}
                                            </span>
                                            @else
                                            <span class="inline-flex items-center gap-1.5 font-mono text-[10px] font-bold uppercase tracking-[0.18em] text-ink-muted">
                                                <span class="w-2 h-2 border border-rule-strong"></span>
                                                {{ ui_copy('search_stock_out', 'search.stock_out') }}
                                            </span>
                                            @endif
                                        </td>

                                        {{-- Price --}}
                                        <td class="px-4 py-4 text-right align-middle">
                                            <p class="font-mono text-lg font-bold text-ink tabular-nums leading-none">
                                                {{ format_price($product->price) }}
                                            </p>
                                        </td>

                                        {{-- Action --}}
                                        <td class="px-5 py-4 text-center align-middle">
                                            @if($product->is_in_stock)
                                            <div class="flex items-center gap-2 justify-center">
                                                {{-- Qty stepper --}}
                                                <div class="inline-flex items-center border border-ink">
                                                    <button type="button"
                                                            @click="quantity = Math.max(1, quantity - 1)"
                                                            class="w-7 h-7 flex items-center justify-center text-ink hover:bg-ink hover:text-ivory disabled:opacity-30 transition-colors"
                                                            :disabled="quantity <= 1"
                                                            aria-label="{{ ui_copy('search_aria_decrease_qty', 'search.aria_decrease_qty') }}">
                                                        <x-heroicon-s-minus class="w-3 h-3" />
                                                    </button>
                                                    <input type="text"
                                                           inputmode="numeric"
                                                           x-model.number="quantity"
                                                           @keydown.up.prevent="quantity = Math.min(99, quantity + 1)"
                                                           @keydown.down.prevent="quantity = Math.max(1, quantity - 1)"
                                                           class="w-9 h-7 text-center font-mono text-xs font-bold text-ink bg-paper border-0 border-x border-ink focus:ring-0 focus:outline-none p-0"
                                                           aria-label="{{ ui_copy('search_aria_quantity', 'search.aria_quantity') }}">
                                                    <button type="button"
                                                            @click="quantity = Math.min(99, quantity + 1)"
                                                            class="w-7 h-7 flex items-center justify-center text-ink hover:bg-ink hover:text-ivory disabled:opacity-30 transition-colors"
                                                            :disabled="quantity >= 99"
                                                            aria-label="{{ ui_copy('search_aria_increase_qty', 'search.aria_increase_qty') }}">
                                                        <x-heroicon-s-plus class="w-3 h-3" />
                                                    </button>
                                                </div>

                                                {{-- Add to Cart --}}
                                                <button @click="addToCart"
                                                        :disabled="cartState !== 'idle'"
                                                        class="inline-flex items-center gap-1.5 px-4 py-1.5 border font-mono text-[11px] font-bold uppercase tracking-[0.18em] transition-colors"
                                                        :class="{
                                                            'bg-amber border-amber text-ink hover:bg-ink hover:text-ivory hover:border-ink': cartState === 'idle',
                                                            'bg-amber/50 border-amber/50 text-ink/50 cursor-wait': cartState === 'loading',
                                                            'bg-ink border-ink text-ivory': cartState === 'added'
                                                        }">
                                                    <span x-show="cartState === 'idle'" class="inline-flex items-center gap-1.5">
                                                        <x-heroicon-s-plus class="w-3 h-3" />
                                                        {{ ui_copy('search_btn_add', 'search.btn_add') }}
                                                    </span>
                                                    <span x-show="cartState === 'loading'" x-cloak>
                                                        <svg class="animate-spin w-3 h-3" viewBox="0 0 24 24" fill="none">
                                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                        </svg>
                                                    </span>
                                                    <span x-show="cartState === 'added'" x-cloak class="inline-flex items-center gap-1.5">
                                                        <x-heroicon-s-check class="w-3 h-3" />
                                                        OK
                                                    </span>
                                                </button>
                                            </div>
                                            <p x-show="cartError" x-text="cartError" x-cloak role="alert" aria-live="assertive" class="text-center font-mono text-[10px] uppercase tracking-wider text-red-600 mt-1"></p>
                                            @else
                                            <button type="button"
                                                    @click="$dispatch('open-inquiry-modal', { oem: '{{ $product->oem_number }}' })"
                                                    class="inline-flex items-center gap-1.5 px-4 py-1.5 border border-ink bg-ink text-ivory
                                                           font-mono text-[11px] font-bold uppercase tracking-[0.18em]
                                                           hover:bg-amber hover:text-ink hover:border-amber transition-colors">
                                                <x-heroicon-s-paper-airplane class="w-3 h-3" />
                                                {{ ui_copy('search_btn_request', 'search.btn_request') }}
                                            </button>
                                            @endif
                                        </td>
                                    </tr>

                                    {{-- Expandable details --}}
                                    <tr x-show="detailsOpen" x-cloak>
                                        <td colspan="5" class="px-5 py-5 bg-ivory-alt/60 border-t border-rule">
                                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-0 border border-rule bg-paper">
                                                @if($product->delivery_time)
                                                <div class="p-4 border-rule border-b sm:border-b-0 sm:border-r">
                                                    <p class="bp-spec text-ink-muted mb-2">{{ ui_copy('search_delivery', 'search.delivery') }}</p>
                                                    <p class="font-mono text-sm font-bold tabular-nums text-ink">{{ $product->delivery_time }}</p>
                                                </div>
                                                @endif
                                                <div class="p-4 border-rule border-b sm:border-b-0 sm:border-r">
                                                    <p class="bp-spec text-ink-muted mb-2">{{ ui_copy('search_condition', 'search.condition') }}</p>
                                                    <span class="inline-flex items-center px-2 py-0.5 bp-spec-mono font-bold rounded-sm"
                                                          style="background-color: {{ $condBg }}; color: {{ $condText }};">{{ $condLabel }}</span>
                                                </div>
                                                <div class="p-4">
                                                    <p class="bp-spec text-ink-muted mb-2">{{ ui_copy('search_incl_vat', 'search.incl_vat', ['rate' => $vat_rate]) }}</p>
                                                    <p class="font-mono text-sm font-bold tabular-nums text-ink">{{ format_price($priceWithVat) }}</p>
                                                </div>
                                            </div>

                                            @if($crossRefs->isNotEmpty())
                                            @php $refLimit = 4; $totalRefs = $crossRefs->count(); @endphp
                                            <div class="mt-5 pt-4 border-t border-rule">
                                                <div class="flex items-center gap-3 mb-3">
                                                    <x-heroicon-o-arrow-path class="w-3.5 h-3.5 text-amber-ink" />
                                                    <span class="bp-spec text-amber-ink">{{ ui_copy('search_cross_refs_title_count', 'search.cross_refs_title_count', ['count' => $totalRefs]) }}</span>
                                                </div>
                                                <div class="flex flex-wrap gap-2">
                                                    @foreach($crossRefs as $cross)
                                                    <a href="{{ route('frontend.search.results', ['lang' => $lang, 'oem' => $cross->normalized_cross_oem ?? $cross->cross_oem_number]) }}"
                                                       x-show="{{ $loop->index }} < {{ $refLimit }} || crossRefsOpen"
                                                       class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-rule-strong bg-paper
                                                              font-mono text-xs font-semibold tabular-nums text-ink
                                                              hover:bg-ink hover:text-ivory hover:border-ink transition-colors">
                                                        {{ $cross->cross_oem_number }}
                                                        <x-heroicon-s-arrow-long-right class="w-3 h-3" />
                                                    </a>
                                                    @endforeach
                                                    @if($totalRefs > $refLimit)
                                                    <button type="button" @click="crossRefsOpen = !crossRefsOpen"
                                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-dashed border-rule-strong
                                                                   font-mono text-[11px] font-bold uppercase tracking-[0.14em] text-ink-muted hover:text-ink hover:border-ink transition-colors">
                                                        <span x-show="!crossRefsOpen">{{ ui_copy('search_cross_refs_view_all', 'search.cross_refs_view_all', ['count' => $totalRefs]) }}</span>
                                                        <span x-show="crossRefsOpen" x-cloak>{{ ui_copy('search_cross_refs_show_less', 'search.cross_refs_show_less') }}</span>
                                                    </button>
                                                    @endif
                                                </div>
                                            </div>
                                            @endif

                                            <div class="mt-4 flex justify-end">
                                                <button @click="detailsOpen = false"
                                                        class="inline-flex items-center gap-1.5 font-mono text-[10px] font-bold uppercase tracking-[0.22em] text-ink-muted hover:text-ink transition-colors">
                                                    <x-heroicon-s-chevron-up class="w-3 h-3" />
                                                    {{ ui_copy('search_hide_details', 'search.hide_details') }}
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    {{-- Expand toggle --}}
                                    <tr x-show="!detailsOpen">
                                        <td colspan="5" class="px-5 pb-2 pt-0">
                                            <button @click="detailsOpen = true"
                                                    class="inline-flex items-center gap-1.5 font-mono text-[10px] font-bold uppercase tracking-[0.22em] text-ink-muted hover:text-ink transition-colors">
                                                <x-heroicon-s-chevron-down class="w-3 h-3" />
                                                {{ ui_copy('search_more_details', 'search.more_details') }}
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                                @endforeach
                            </table>
                        </div>
                    </div>
                </div>

                {{-- ═══ CARD VIEW (Mobile/Tablet) ══════════════════════════ --}}
                <div class="lg:hidden space-y-4">
                    @foreach($products as $product)
                    @php
                        $manufacturer = $product->manufacturer;
                        $logoPath     = $manufacturer?->logo?->file_path;
                        $priceWithVat = bcmul((string) $product->price, $vatMultiplier, 2);
                        $cCond = $product->condition;
                        $condKey = $cCond?->slug ?? 'new';
                        $condLabel = $cCond?->name ?? 'New';
                        $condBg = $cCond?->bg_color ?? '#DCFCE7';
                        $condText = $cCond?->text_color ?? '#16A34A';
                        $crossRefs = $product->crossReferences ?? collect();
                        $rowNum = str_pad($loop->index + 1, 3, '0', STR_PAD_LEFT);
                        if ($products instanceof \Illuminate\Pagination\LengthAwarePaginator) {
                            $rowNum = str_pad(($products->firstItem() + $loop->index), 3, '0', STR_PAD_LEFT);
                        }
                    @endphp

                    <div x-data="searchProductRow({ productId: {{ $product->id }}, oemNumber: '{{ $product->oem_number }}', manufacturerName: '{{ $manufacturer ? trans_field($manufacturer->name) : ui_copy('search_unknown_brand', 'search.unknown_brand') }}' })"
                         class="border border-ink bg-paper {{ $loop->index === 0 && $sort === 'default' ? 'ring-1 ring-amber ring-offset-0' : '' }}">

                        @if($loop->index === 0 && $sort === 'default')
                        <div class="flex items-center gap-2 bg-amber/10 border-b border-amber px-4 py-2">
                            <span class="w-4 h-[2px] bg-amber"></span>
                            <span class="font-mono text-[10px] font-bold uppercase tracking-[0.22em] text-amber-ink">
                                {{ ui_copy('search_best_match', 'search.best_match') }}
                            </span>
                        </div>
                        @endif

                        <div class="p-4">
                            {{-- Row num + Condition + brand logo + OEM --}}
                            <div class="flex items-start justify-between gap-3 mb-4 pb-4 border-b border-rule">
                                <div class="flex items-start gap-3 min-w-0 flex-1">
                                    <span class="font-mono text-[11px] tabular-nums text-ink-muted shrink-0 mt-1.5">
                                        {{ $rowNum }}
                                    </span>
                                    @if($logoPath)
                                    <img src="{{ asset('storage/' . $logoPath) }}"
                                         alt="{{ trans_field($manufacturer->name) }}"
                                         class="w-9 h-9 object-contain border border-rule p-1 shrink-0"
                                         loading="lazy" width="36" height="36">
                                    @else
                                    <div class="w-9 h-9 border border-rule flex items-center justify-center shrink-0 bg-ivory-alt">
                                        <x-heroicon-o-building-office-2 class="w-4 h-4 text-ink-muted" />
                                    </div>
                                    @endif
                                    <div class="min-w-0" x-data="clipboard()">
                                        <button type="button" class="appearance-none bg-transparent border-0 p-0 m-0 font-mono text-base font-bold text-ink tabular-nums truncate cursor-pointer focus:outline-none focus:ring-2 focus:ring-inset focus:ring-amber-ink rounded-sm"
                                           @click="copy('{{ $product->oem_number }}')"
                                           title="Copy OEM number">
                                            {{ $product->oem_number }}
                                        </button>
                                        <p class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-muted truncate mt-0.5">
                                            {{ $manufacturer ? trans_field($manufacturer->name) : '—' }}
                                        </p>
                                        <span x-show="copied" x-cloak x-transition role="status" aria-live="polite"
                                              class="text-[10px] font-mono font-bold text-emerald-600">Copied</span>
                                    </div>
                                </div>
                                <span class="inline-flex items-center justify-center px-2 py-0.5 bp-spec-mono font-bold rounded-sm shrink-0"
                                      style="background-color: {{ $condBg }}; color: {{ $condText }};">
                                    {{ $condLabel }}
                                </span>
                            </div>

                            {{-- Stock + Price --}}
                            <div class="flex items-center justify-between mb-4">
                                @if($product->is_in_stock)
                                <span class="inline-flex items-center gap-1.5 font-mono text-[10px] font-bold uppercase tracking-[0.18em] text-ink">
                                    <span class="w-2 h-2 bg-amber"></span>
                                    {{ ui_copy('search_stock_in', 'search.stock_in') }}
                                </span>
                                @else
                                <span class="inline-flex items-center gap-1.5 font-mono text-[10px] font-bold uppercase tracking-[0.18em] text-ink-muted">
                                    <span class="w-2 h-2 border border-rule-strong"></span>
                                    {{ ui_copy('search_stock_out_long', 'search.stock_out_long') }}
                                </span>
                                @endif

                                <div class="text-right">
                                    <p class="font-mono text-xl font-bold text-ink tabular-nums leading-none">
                                        {{ format_price($product->price) }}
                                    </p>
                                    <p class="font-mono text-[9px] tracking-[0.2em] uppercase text-ink-muted mt-1">
                                        {{ ui_copy('search_excl_vat_short', 'search.excl_vat_short') }}
                                    </p>
                                </div>
                            </div>

                            {{-- Action --}}
                            @if($product->is_in_stock)
                            <div class="flex items-center gap-2">
                                <div class="inline-flex items-center border border-ink">
                                    <button type="button" @click="quantity = Math.max(1, quantity - 1)"
                                            class="w-9 h-9 flex items-center justify-center text-ink hover:bg-ink hover:text-ivory disabled:opacity-30 transition-colors"
                                            :disabled="quantity <= 1"
                                            aria-label="{{ ui_copy('search_aria_decrease_qty', 'search.aria_decrease_qty') }}">
                                        <x-heroicon-s-minus class="w-3.5 h-3.5" />
                                    </button>
                                    <input type="text" inputmode="numeric" x-model.number="quantity"
                                           class="w-11 h-9 text-center font-mono text-sm font-bold text-ink bg-paper border-0 border-x border-ink focus:ring-0 focus:outline-none p-0"
                                           aria-label="{{ ui_copy('search_aria_quantity', 'search.aria_quantity') }}">
                                    <button type="button" @click="quantity = Math.min(99, quantity + 1)"
                                            class="w-9 h-9 flex items-center justify-center text-ink hover:bg-ink hover:text-ivory disabled:opacity-30 transition-colors"
                                            :disabled="quantity >= 99"
                                            aria-label="{{ ui_copy('search_aria_increase_qty', 'search.aria_increase_qty') }}">
                                        <x-heroicon-s-plus class="w-3.5 h-3.5" />
                                    </button>
                                </div>

                                <button @click="addToCart"
                                        :disabled="cartState !== 'idle'"
                                        class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 border
                                               font-mono text-xs font-bold uppercase tracking-[0.22em] transition-colors"
                                        :class="{
                                            'bg-amber border-amber text-ink': cartState === 'idle',
                                            'bg-amber/50 border-amber/50 text-ink/50 cursor-wait': cartState === 'loading',
                                            'bg-ink border-ink text-ivory': cartState === 'added'
                                        }">
                                    <span x-show="cartState === 'idle'" class="inline-flex items-center gap-2">
                                        <x-heroicon-s-plus class="w-3.5 h-3.5" />
                                        {{ ui_copy('search_btn_add_to_cart', 'search.btn_add_to_cart') }}
                                    </span>
                                    <span x-show="cartState === 'loading'" x-cloak>
                                        <svg class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                    </span>
                                    <span x-show="cartState === 'added'" x-cloak class="inline-flex items-center gap-2">
                                        <x-heroicon-s-check class="w-3.5 h-3.5" />
                                        {{ ui_copy('search_btn_added', 'search.btn_added') }}
                                    </span>
                                </button>
                            </div>
                            <p x-show="cartError" x-text="cartError" x-cloak class="mt-2 font-mono text-[10px] uppercase tracking-wider text-red-600 text-center"></p>
                            @else
                            <button type="button"
                                    @click="$dispatch('open-inquiry-modal', { oem: '{{ $product->oem_number }}' })"
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 border border-ink bg-ink text-ivory
                                           font-mono text-xs font-bold uppercase tracking-[0.22em]
                                           hover:bg-amber hover:text-ink hover:border-amber transition-colors">
                                <x-heroicon-s-paper-airplane class="w-3.5 h-3.5" />
                                {{ ui_copy('search_request_this_part', 'search.request_this_part') }}
                            </button>
                            @endif

                            {{-- Expand toggle --}}
                            <button @click="detailsOpen = !detailsOpen"
                                    class="mt-4 w-full inline-flex items-center justify-center gap-1.5 py-2 border-t border-rule
                                           font-mono text-[10px] font-bold uppercase tracking-[0.22em] text-ink-muted hover:text-ink transition-colors">
                                <x-heroicon-s-chevron-down class="w-3 h-3 transition-transform" x-bind:class="detailsOpen ? 'rotate-180' : ''" />
                                <span x-show="!detailsOpen">{{ ui_copy('search_more_details', 'search.more_details') }}</span>
                                <span x-show="detailsOpen" x-cloak>{{ ui_copy('search_hide_details', 'search.hide_details') }}</span>
                            </button>
                        </div>

                        {{-- Expanded --}}
                        <div x-show="detailsOpen" x-cloak
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             class="border-t border-ink bg-ivory-alt/60 px-4 py-4 space-y-4">

                            <div class="grid grid-cols-2 border border-rule bg-paper">
                                @if($product->delivery_time)
                                <div class="p-3 border-r border-rule">
                                    <p class="bp-spec text-ink-muted mb-1">{{ ui_copy('search_delivery', 'search.delivery') }}</p>
                                    <p class="font-mono text-xs font-bold text-ink">{{ $product->delivery_time }}</p>
                                </div>
                                @endif
                                <div class="p-3 {{ !$product->delivery_time ? 'col-span-2' : '' }}">
                                    <p class="bp-spec text-ink-muted mb-1">{{ ui_copy('search_incl_vat', 'search.incl_vat', ['rate' => $vat_rate]) }}</p>
                                    <p class="font-mono text-xs font-bold tabular-nums text-ink">{{ format_price($priceWithVat) }}</p>
                                </div>
                            </div>

                            @if($crossRefs->isNotEmpty())
                            @php $refLimit = 4; $totalRefs = $crossRefs->count(); @endphp
                            <div class="pt-2">
                                <p class="bp-spec text-amber-ink mb-3">{{ ui_copy('search_cross_refs_title', 'search.cross_refs_title') }}</p>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($crossRefs as $cross)
                                    <a href="{{ route('frontend.search.results', ['lang' => $lang, 'oem' => $cross->normalized_cross_oem ?? $cross->cross_oem_number]) }}"
                                       x-show="{{ $loop->index }} < {{ $refLimit }} || crossRefsOpen"
                                       class="inline-flex items-center gap-1 px-2 py-1 border border-rule-strong bg-paper font-mono text-[10px] font-semibold tabular-nums text-ink hover:bg-ink hover:text-ivory hover:border-ink transition-colors">
                                        {{ $cross->cross_oem_number }}
                                    </a>
                                    @endforeach
                                    @if($totalRefs > $refLimit)
                                    <button type="button" @click="crossRefsOpen = !crossRefsOpen"
                                            class="inline-flex items-center gap-1 px-2 py-1 border border-dashed border-rule-strong font-mono text-[10px] font-bold uppercase tracking-wider text-ink-muted">
                                        <span x-show="!crossRefsOpen">{{ ui_copy('search_cross_refs_view_all', 'search.cross_refs_view_all', ['count' => $totalRefs]) }}</span>
                                        <span x-show="crossRefsOpen" x-cloak>{{ ui_copy('search_cross_refs_show_less', 'search.cross_refs_show_less') }}</span>
                                    </button>
                                    @endif
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>

            </div>
            @endif

            {{-- ── Pagination ──────────────────────────────────────────── --}}
            @if($products instanceof \Illuminate\Pagination\LengthAwarePaginator && $products->hasPages())
            <div class="mt-10 pt-8 border-t border-rule">
                {{ $products->links('components.ui.pagination') }}
            </div>
            @endif

            {{-- ── Inquiry CTA ─────────────────────────────────────────── --}}
            <div class="mt-12 border border-ink bg-paper">
                <div class="flex items-center justify-between px-5 py-3 border-b border-rule bg-ivory-alt">
                    <span class="bp-spec text-amber-ink">99 · {{ ui_copy('search_inquiry_title', 'search.inquiry_title') }}</span>
                    <span class="bp-spec-mono">
                        SLA · {{ (int) settings('part_inquiry.response_hours', 24) }} h
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-0">
                    <div class="p-6 md:p-8 md:border-r md:border-rule">
                        <h2 class="font-display text-2xl md:text-3xl font-extrabold text-ink tracking-tight mb-3 text-balance">
                            Can't find it? Ask our team<span class="text-amber">.</span>
                        </h2>
                        <p class="text-base text-body leading-relaxed">
                            {{ ui_copy('search_inquiry_subtitle', 'search.inquiry_subtitle', ['hours' => (int) settings('part_inquiry.response_hours', 24)]) }}
                        </p>

                        <dl class="mt-6 space-y-0 border-t border-rule">
                            <div class="flex items-center gap-3 py-3 border-b border-rule">
                                <x-heroicon-s-shield-check class="w-4 h-4 text-amber-ink shrink-0" />
                                <span class="font-mono text-[10px] tracking-[0.2em] uppercase text-ink">{{ ui_copy('search_inquiry_trust_suppliers', 'search.inquiry_trust_suppliers') }}</span>
                            </div>
                            <div class="flex items-center gap-3 py-3 border-b border-rule">
                                <x-heroicon-s-building-storefront class="w-4 h-4 text-amber-ink shrink-0" />
                                <span class="font-mono text-[10px] tracking-[0.2em] uppercase text-ink">{{ ui_copy('search_inquiry_trust_warehouse', 'search.inquiry_trust_warehouse') }}</span>
                            </div>
                            <div class="flex items-center gap-3 py-3">
                                <x-heroicon-s-trophy class="w-4 h-4 text-amber-ink shrink-0" />
                                <span class="font-mono text-[10px] tracking-[0.2em] uppercase text-ink">{{ ui_copy('search_inquiry_trust_quality', 'search.inquiry_trust_quality') }}</span>
                            </div>
                        </dl>
                    </div>

                    <div class="p-6 md:p-8 bg-ivory-alt/40 flex flex-col justify-center">
                        <button type="button"
                                x-on:click="window.dispatchEvent(new CustomEvent('open-inquiry-modal'))"
                                class="bp-btn-primary w-full justify-center">
                            <x-heroicon-s-paper-airplane class="w-5 h-5" />
                            {{ ui_copy('search_inquiry_submit', 'search.inquiry_submit') }}
                        </button>
                        <p class="mt-4 bp-spec-mono text-center">
                            Secure · TLS 1.3 · Response within {{ (int) settings('part_inquiry.response_hours', 24) }} h
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

{{-- ── Scroll to Top ────────────────────────────────────────────────────── --}}
<div x-data="{ show: false }"
     x-init="window.addEventListener('scroll', () => { show = window.scrollY > 400 }, { passive: true })"
     x-show="show"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 translate-y-4"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-cloak
     class="fixed bottom-4 right-4 sm:bottom-6 sm:right-6 z-40">
    <button @click="window.scrollTo({ top: 0, behavior: 'smooth' })"
            class="w-10 h-10 bg-ink border border-ink text-ivory flex items-center justify-center
                   hover:bg-amber hover:text-ink transition-colors"
            title="{{ ui_copy('search_scroll_to_top', 'search.scroll_to_top') }}"
            aria-label="{{ ui_copy('search_scroll_to_top', 'search.scroll_to_top') }}">
        <x-heroicon-s-arrow-up class="w-4 h-4" />
    </button>
</div>

<x-modals.part-inquiry :normalized-query="$normalized_query ?? ''" />

@push('scripts')
<script>
function searchProductRow(config) {
    const lang = '{{ app()->getLocale() }}';
    const cartAddUrl = @json(route('frontend.cart.add', ['lang' => app()->getLocale()]));
    const failMsg = @json(ui_copy('search_cart_add_failed', 'search.cart_add_failed'));

    return {
        detailsOpen: false,
        crossRefsOpen: false,
        cartState: 'idle',
        quantity: 1,
        cartError: '',
        productId: config.productId,
        oemNumber: config.oemNumber,
        manufacturerName: config.manufacturerName,
        async addToCart() {
            if (this.cartState !== 'idle') return;
            this.cartState = 'loading';
            this.cartError = '';

            try {
                const res = await fetch(cartAddUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ product_id: this.productId, quantity: this.quantity })
                });

                let data = {};
                try { data = await res.json(); } catch (e) {}

                if (res.ok && data.success) {
                    this.cartState = 'added';
                    window.dispatchEvent(new CustomEvent('cart-updated', {
                        detail: { itemCount: data.cart?.item_count ?? 0 }
                    }));
                    window.dispatchEvent(new CustomEvent('cart-toast', {
                        detail: {
                            productName: `${this.manufacturerName} ${this.oemNumber}`,
                            quantity: this.quantity,
                            itemCount: data.cart?.item_count ?? 0
                        }
                    }));
                    setTimeout(() => this.cartState = 'idle', 3000);
                } else {
                    this.cartState = 'idle';
                    this.cartError = (data && data.message) ? data.message : failMsg;
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { message: this.cartError, type: 'error' }
                    }));
                    setTimeout(() => { this.cartError = ''; }, 7000);
                }
            } catch(e) {
                this.cartState = 'idle';
                this.cartError = failMsg;
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { message: failMsg, type: 'error' }
                }));
                setTimeout(() => { this.cartError = ''; }, 7000);
            }
        }
    };
}
</script>
@endpush

@endsection
