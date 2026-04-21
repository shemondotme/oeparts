@extends('layouts.app')

@php
    $price_stats = is_array($price_stats ?? null)
        ? $price_stats
        : ['min' => null, 'max' => null, 'avg' => null, 'avg_numeric' => null];
    $siteName = settings('general.site_name', 'OEMHub');
    $oemForSeo = $normalized_query ?? '';
    $countForSeo = number_format($total ?? 0);
    $titleTpl = trim((string) settings('seo.search_results_title_template', ''));
    if ($titleTpl !== '') {
        $searchResultsPageTitle = str_replace(
            ['{oem}', '{count}', '{site}', '{min}', '{max}'],
            [
                $oemForSeo,
                $countForSeo,
                $siteName,
                (string) ($price_stats['min'] ?? ''),
                (string) ($price_stats['max'] ?? ''),
            ],
            $titleTpl
        );
    } else {
        $searchResultsPageTitle = __('search.page_title', [
            'oem' => $oemForSeo,
            'count' => $countForSeo,
            'result_word' => ($total ?? 0) === 1 ? __('search.result_word_single') : __('search.result_word_plural'),
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
        $searchResultsMetaDescription = __('search.meta_description', ['count' => $countForSeo, 'oem' => $oemForSeo]);
    }
    $searchResultsOgDescription = \Illuminate\Support\Str::limit(strip_tags($searchResultsMetaDescription), 300, '');
@endphp

{{-- ── SEO ──────────────────────────────────────────────────────────────── --}}
@section('title')
    {{ $searchResultsPageTitle }}
@endsection

@section('meta_description')
    {{ $searchResultsMetaDescription }}
@endsection

@section('og_title')
    {{ $searchResultsPageTitle }}
@endsection

@section('og_description')
    {{ $searchResultsOgDescription }}
@endsection

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
        ? $products->items()
        : $products;
    foreach ($iterProducts as $index => $product) {
        $jsonLdItems[] = [
            '@type' => 'ListItem',
            'position' => $index + 1,
            'item' => [
                '@type' => 'Product',
                'name' => $product->oem_number,
                'sku' => $product->oem_number,
                'brand' => [
                    '@type' => 'Brand',
                    'name' => $product->manufacturer ? trans_field($product->manufacturer->name) : __('search.unknown_brand'),
                ],
                'offers' => [
                    '@type' => 'Offer',
                    'price' => (string) $product->price,
                    'priceCurrency' => 'EUR',
                    'availability' => $product->is_in_stock
                        ? 'https://schema.org/InStock'
                        : 'https://schema.org/OutOfStock',
                ],
            ],
        ];
    }
    $jsonLdSchema = [
        '@context' => 'https://schema.org',
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

{{-- ── Custom Styles ───────────────────────────────────────────────────── --}}
@push('styles')
<style>
    .oem-results-row:hover > td { background-color: #F8FAFC; }
</style>
@endpush

{{-- ── Content ──────────────────────────────────────────────────────────── --}}
@section('content')
@php
    $lang = app()->getLocale();
    $car_model_filter = $car_model_filter ?? null;
    $car_model_filter_label = $car_model_filter_label ?? null;

    $matchBadge = match($search_type) {
        'exact'           => ['label' => __('search.match_exact'),    'bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'icon' => 'check-circle'],
        'cross_reference' => ['label' => __('search.match_cross'),    'bg' => 'bg-blue-100',    'text' => 'text-blue-700',    'icon' => 'arrow-path'],
        'partial'         => ['label' => __('search.match_partial'),  'bg' => 'bg-amber/15',    'text' => 'text-amber-text',  'icon' => 'magnifying-glass'],
        default           => ['label' => __('search.match_default'),   'bg' => 'bg-gray-100',    'text' => 'text-gray-600',    'icon' => 'check-circle'],
    };

    // VAT multiplier using bcmath — e.g. 21% → '1.2100'
    $vatMultiplier = bcadd('1', bcdiv((string) $vat_rate, '100', 4), 4);

    // Active filter count
    $activeFilterCount = ($condition_filter ? 1 : 0)
                       + ($in_stock_only ? 1 : 0)
                       + ($sort !== 'default' ? 1 : 0)
                       + ($manufacturer_filter ? 1 : 0)
                       + ($car_model_filter ? 1 : 0);

    // Condition labels/styles for filter buttons
    $conditionLabels = ['new' => __('search.condition_filter_new'), 'used' => __('search.condition_filter_used')];
    $conditionStyles = [
        'new'  => ['active' => 'bg-emerald-600 text-white border-emerald-600', 'idle' => 'bg-white text-emerald-700 border-emerald-200 hover:bg-emerald-50'],
        'used' => ['active' => 'bg-blue-600 text-white border-blue-600',   'idle' => 'bg-white text-blue-700 border-blue-200 hover:bg-blue-50'],
    ];

    // Condition badge classes for product rows
    $conditionBadgeMap = [
        'new'             => ['bg' => 'bg-condition-new-bg',             'text' => 'text-condition-new-text'],
        'used'            => ['bg' => 'bg-condition-used-a-bg',          'text' => 'text-condition-used-a-text'],
        'used_a'          => ['bg' => 'bg-condition-used-a-bg',          'text' => 'text-condition-used-a-text'],
        'used_b'          => ['bg' => 'bg-condition-used-b-bg',          'text' => 'text-condition-used-b-text'],
        'used_c'          => ['bg' => 'bg-condition-used-c-bg',          'text' => 'text-condition-used-c-text'],
        'remanufactured'  => ['bg' => 'bg-condition-remanufactured-bg',  'text' => 'text-condition-remanufactured-text'],
        'aftermarket'     => ['bg' => 'bg-condition-aftermarket-bg',     'text' => 'text-condition-aftermarket-text'],
        'new_old_stock'   => ['bg' => 'bg-condition-nos-bg',   'text' => 'text-condition-nos-text'],
    ];

    $conditionLabelMap = [
        'new'             => __('search.condition_label_new'),
        'used'            => __('search.condition_label_used'),
        'used_a'          => __('search.condition_label_used_a'),
        'used_b'          => __('search.condition_label_used_b'),
        'used_c'          => __('search.condition_label_used_c'),
        'remanufactured'  => __('search.condition_label_remanufactured'),
        'aftermarket'     => __('search.condition_label_aftermarket'),
        'new_old_stock'   => __('search.condition_label_new_old_stock'),
    ];
@endphp

<div class="min-h-screen bg-[#F8FAFC] pb-24">

    <div x-data="{
             lg: typeof matchMedia !== 'undefined' && matchMedia('(min-width: 1024px)').matches,
             init() {
                 if (typeof matchMedia === 'undefined') return;
                 const mq = matchMedia('(min-width: 1024px)');
                 this.lg = mq.matches;
                 mq.addEventListener('change', () => { this.lg = mq.matches });
             }
         }">

        {{-- ── Slim search bar (flat, no gradient) ─────────────────── --}}
        <div class="bg-white border-b border-gray-100">
            <div class="max-w-6xl mx-auto px-4 py-3"
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
                    <div class="flex-1 flex items-center gap-3 bg-[#F8FAFC] rounded-xl px-4 py-2.5 border border-gray-200 focus-within:border-navy focus-within:bg-white transition-colors">
                        <x-heroicon-o-magnifying-glass class="w-5 h-5 text-gray-400 shrink-0" />
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
                            class="shrink-0 inline-flex items-center justify-center gap-1.5 px-5 py-2.5 bg-navy text-white font-bold text-sm rounded-xl hover:bg-blue-900 transition-colors">
                        <span class="hidden sm:inline">{{ __('search.mini_search_button') }}</span>
                        <x-heroicon-m-magnifying-glass class="w-4 h-4" />
                    </button>
                </form>
            </div>
        </div>

        <div class="max-w-6xl mx-auto px-4 mt-6 md:mt-8">

        {{-- ── Result Header ──────────────────────────────────────────── --}}
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="font-display text-2xl md:text-3xl lg:text-4xl font-extrabold text-navy leading-tight">
                    {{ __('search.heading_results_for') }}
                    <span class="font-mono text-amber-text bg-amber/10 px-2.5 py-1 rounded-xl text-xl md:text-2xl lg:text-3xl">
                        {{ $normalized_query }}
                    </span>
                </h1>

                {{-- Result stats bar --}}
                <div class="flex flex-wrap items-center gap-2 mt-3">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-navy text-white text-xs font-bold shadow-sm">
                        <x-heroicon-o-cube class="w-3.5 h-3.5" />
                        {{ __('search.parts_found_sentence', [
                            'count' => number_format($total),
                            'parts_word' => $total === 1 ? __('search.part') : __('search.parts'),
                        ]) }}
                    </span>

                    @if($products instanceof \Illuminate\Pagination\LengthAwarePaginator && $products->hasPages())
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white border border-gray-200 text-xs font-semibold text-navy">
                            <x-heroicon-o-document-text class="w-3.5 h-3.5 text-muted" />
                            {{ __('search.page_indicator', ['current' => $products->currentPage(), 'last' => $products->lastPage()]) }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white border border-gray-200 text-xs font-semibold text-navy">
                            <x-heroicon-o-eye class="w-3.5 h-3.5 text-muted" />
                            {{ __('search.showing_range', ['from' => $products->firstItem(), 'to' => $products->lastItem()]) }}
                        </span>
                    @endif

                    @if($activeFilterCount > 0)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber/15 border border-amber/25 text-amber-text text-xs font-bold">
                            <x-heroicon-s-funnel class="w-3.5 h-3.5" />
                            {{ trans_choice('search.filters_active_choice', $activeFilterCount, ['count' => $activeFilterCount]) }}
                        </span>
                    @endif
                </div>
            </div>

            {{-- Match type badge --}}
            <span class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl text-xs font-bold uppercase tracking-wide shrink-0 shadow-sm
                         {{ $matchBadge['bg'] }} {{ $matchBadge['text'] }}">
                @switch($matchBadge['icon'])
                    @case('check-circle') <x-heroicon-s-check-circle class="w-4 h-4" /> @break
                    @case('arrow-path')   <x-heroicon-o-arrow-path class="w-4 h-4" />   @break
                    @default              <x-heroicon-o-magnifying-glass class="w-4 h-4" />
                @endswitch
                {{ $matchBadge['label'] }}
            </span>
        </div>

        {{-- ── Price Stats Hero Strip ────────────────────────────────── --}}
        @if($price_stats['min'] !== null && $price_stats['max'] !== null && !$filtered_empty)
        @php $priceMinMaxSame = (string) $price_stats['min'] === (string) $price_stats['max']; @endphp
        <div class="grid grid-cols-3 gap-3 mb-5">
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3">
                <p class="text-[10px] font-bold text-muted uppercase tracking-widest">{{ __('search.price_from') }}</p>
                <p class="font-display text-2xl font-extrabold text-navy mt-1 leading-none">€{{ $price_stats['min'] }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3">
                <p class="text-[10px] font-bold text-muted uppercase tracking-widest">{{ __('search.price_avg') }}</p>
                <p class="font-display text-2xl font-extrabold text-amber-text mt-1 leading-none">
                    €{{ $priceMinMaxSame ? $price_stats['min'] : $price_stats['avg'] }}
                </p>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3">
                <p class="text-[10px] font-bold text-muted uppercase tracking-widest">{{ __('search.price_to') }}</p>
                <p class="font-display text-2xl font-extrabold text-navy mt-1 leading-none">€{{ $price_stats['max'] }}</p>
            </div>
        </div>
        @endif

        {{-- ── Cross-reference / Partial notice ──────────────────────── --}}
        @if($search_type === 'cross_reference')
        <div class="flex items-start gap-4 p-5 rounded-2xl bg-blue-50 border border-blue-100 mb-4">
            <div class="w-8 h-8 rounded-xl bg-blue-100 flex items-center justify-center shrink-0 mt-0.5">
                <x-heroicon-o-arrow-path class="w-4 h-4 text-blue-600" />
            </div>
            <div>
                <p class="text-sm font-semibold text-blue-800">{{ __('search.notice_cross_title') }}</p>
                <p class="text-sm text-blue-700 mt-1">
                    {{ __('search.notice_cross_body', ['oem' => $normalized_query]) }}
                </p>
            </div>
        </div>
        @endif

        @if($search_type === 'partial')
        <div class="flex items-start gap-4 p-5 rounded-2xl bg-amber/10 border border-amber/20 mb-4">
            <div class="w-8 h-8 rounded-xl bg-amber/20 flex items-center justify-center shrink-0 mt-0.5">
                <x-heroicon-o-magnifying-glass class="w-4 h-4 text-amber-text" />
            </div>
            <div>
                <p class="text-sm font-semibold text-amber-text">{{ __('search.notice_partial_title') }}</p>
                <p class="text-sm text-amber-text mt-1">
                    {{ __('search.notice_partial_body', ['oem' => $normalized_query]) }}
                </p>
            </div>
        </div>
        @endif

        {{-- ════════════════════════════════════════════════════════════ --}}
        {{-- SORT & FILTER BAR (sticky)                                   --}}
        {{-- ════════════════════════════════════════════════════════════ --}}
        <div class="sticky top-0 sm:top-2 z-40 -mx-4 px-4 pb-4 pt-2 sm:pt-0"
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
                     this.sort !== 'default'
                         ? url.searchParams.set('sort', this.sort)
                         : url.searchParams.delete('sort');
                     this.condition
                         ? url.searchParams.set('condition', this.condition)
                         : url.searchParams.delete('condition');
                     this.inStock
                         ? url.searchParams.set('in_stock', '1')
                         : url.searchParams.delete('in_stock');
                     this.manufacturer
                         ? url.searchParams.set('manufacturer', this.manufacturer)
                         : url.searchParams.delete('manufacturer');
                     this.carModel
                         ? url.searchParams.set('model', this.carModel)
                         : url.searchParams.delete('model');
                     url.searchParams.delete('page');
                     window.location.href = url.toString();
                 }
             }"
        >
        <div class="bg-white border border-gray-100 rounded-xl px-4 sm:px-5 py-3 shadow-sm relative">
            {{-- Loading overlay --}}
            <div x-show="loading" x-cloak class="absolute inset-0 bg-white/80 backdrop-blur-sm rounded-2xl flex items-center justify-center z-50">
                <div class="flex items-center gap-3 text-navy">
                    <svg class="animate-spin h-5 w-5 text-amber" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span class="text-sm font-semibold">{{ __('search.updating_results') }}</span>
                </div>
            </div>

            {{-- Top row: Sort + View toggle + Mobile filter toggle --}}
            <div class="flex flex-wrap items-center gap-2 sm:gap-3">

                {{-- Sort label + buttons --}}
                <div class="flex items-center gap-2 text-xs font-semibold text-muted">
                    <x-heroicon-o-arrows-up-down class="w-3.5 h-3.5" />
                    {{ __('search.sort_label') }}
                </div>
                <div class="flex items-center gap-1.5 flex-wrap">
                    <button type="button"
                            @click="sort = 'default'; apply()"
                            :class="sort === 'default'
                                ? 'bg-navy text-white border-navy'
                                : 'bg-white text-navy border-gray-200 hover:border-navy/30 hover:bg-navy/5'"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-amber/50"
                            :aria-pressed="sort === 'default'">
                        {{ __('search.sort_relevance') }}
                    </button>
                    <button type="button"
                            @click="sort = 'price_asc'; apply()"
                            :class="sort === 'price_asc'
                                ? 'bg-navy text-white border-navy'
                                : 'bg-white text-navy border-gray-200 hover:border-navy/30 hover:bg-navy/5'"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-all duration-150 flex items-center gap-1 focus:outline-none focus:ring-2 focus:ring-amber/50"
                            :aria-pressed="sort === 'price_asc'">
                        <x-heroicon-o-arrow-small-up class="w-3 h-3" />
                        {{ __('search.sort_price') }}
                    </button>
                    <button type="button"
                            @click="sort = 'price_desc'; apply()"
                            :class="sort === 'price_desc'
                                ? 'bg-navy text-white border-navy'
                                : 'bg-white text-navy border-gray-200 hover:border-navy/30 hover:bg-navy/5'"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-all duration-150 flex items-center gap-1 focus:outline-none focus:ring-2 focus:ring-amber/50"
                            :aria-pressed="sort === 'price_desc'">
                        <x-heroicon-o-arrow-small-down class="w-3 h-3" />
                        {{ __('search.sort_price') }}
                    </button>
                </div>

                <div class="h-5 w-px bg-gray-200 hidden sm:block"></div>

                {{-- Mobile: Filter toggle --}}
                <button type="button"
                        @click="filterOpen = !filterOpen"
                        class="sm:hidden inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-gray-200 bg-white text-navy hover:bg-navy/5 transition-all">
                    <x-heroicon-o-funnel class="w-3.5 h-3.5" />
                    {{ __('search.filters') }}
                    @if($activeFilterCount > 0)
                    <span class="ml-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-amber text-[9px] font-bold text-white">{{ $activeFilterCount }}</span>
                    @endif
                </button>

                {{-- Desktop: Filter pills inline --}}
                <div class="hidden sm:flex items-center gap-2 flex-wrap">
                    {{-- Condition filter --}}
                    @foreach($conditionLabels as $val => $label)
                    @php $cnt = $condition_counts[$val] ?? 0; @endphp
                    @if($cnt > 0)
                    <button type="button"
                            @click="condition = (condition === '{{ $val }}' ? '' : '{{ $val }}'); apply()"
                            :class="condition === '{{ $val }}'
                                ? '{{ $conditionStyles[$val]['active'] }}'
                                : '{{ $conditionStyles[$val]['idle'] }}'"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-amber/50"
                            :aria-pressed="condition === '{{ $val }}'">
                        {{ $label }} ({{ $cnt }})
                    </button>
                    @endif
                    @endforeach

                    <div class="h-5 w-px bg-gray-200"></div>

                    {{-- In Stock toggle --}}
                    <label class="flex items-center gap-2 cursor-pointer select-none group">
                        <div class="relative">
                            <input type="checkbox" class="sr-only" x-model="inStock" @change="apply()">
                            <div class="w-9 h-5 rounded-full transition-colors duration-200"
                                 :class="inStock ? 'bg-emerald-500' : 'bg-gray-200'"></div>
                            <div class="absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-white shadow-sm
                                        transition-transform duration-200"
                                 :class="inStock ? 'translate-x-4' : 'translate-x-0'"></div>
                        </div>
                        <span class="text-xs font-semibold text-navy group-hover:text-amber-text transition-colors">{{ __('search.in_stock') }}</span>
                    </label>
                </div>

            </div>

            {{-- Mobile filter drawer --}}
            <div x-show="filterOpen" x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-end="opacity-0 -translate-y-2"
                 class="sm:hidden mt-3 pt-3 border-t border-gray-100 space-y-3">
                {{-- Condition --}}
                <div>
                    <p class="text-xs font-semibold text-muted mb-2">{{ __('search.condition') }}</p>
                    <div class="flex gap-1.5 flex-wrap">
                        @foreach($conditionLabels as $val => $label)
                        @php $cnt = $condition_counts[$val] ?? 0; @endphp
                        @if($cnt > 0)
                        <button type="button"
                                @click="condition = (condition === '{{ $val }}' ? '' : '{{ $val }}'); apply()"
                                :class="condition === '{{ $val }}'
                                    ? '{{ $conditionStyles[$val]['active'] }}'
                                    : '{{ $conditionStyles[$val]['idle'] }}'"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-all">
                            {{ $label }} ({{ $cnt }})
                        </button>
                        @endif
                        @endforeach
                    </div>
                </div>

                {{-- In Stock --}}
                <div>
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <div class="relative">
                            <input type="checkbox" class="sr-only" x-model="inStock" @change="apply()">
                            <div class="w-9 h-5 rounded-full transition-colors duration-200"
                                 :class="inStock ? 'bg-emerald-500' : 'bg-gray-200'"></div>
                            <div class="absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-white shadow-sm
                                        transition-transform duration-200"
                                 :class="inStock ? 'translate-x-4' : 'translate-x-0'"></div>
                        </div>
                        <span class="text-xs font-semibold text-navy">{{ __('search.in_stock') }}</span>
                    </label>
                </div>

                {{-- Manufacturer --}}
                @if(count($manufacturer_filter_options) > 1)
                <div>
                    <p class="text-xs font-semibold text-muted mb-2">{{ __('search.brand') }}</p>
                    <div class="flex gap-1.5 flex-wrap">
                        @foreach($manufacturer_filter_options as $mfr)
                        <button type="button"
                                @click="manufacturer = (manufacturer == '{{ $mfr['id'] }}' ? '' : '{{ $mfr['id'] }}'); carModel = ''; apply()"
                                :class="manufacturer == '{{ $mfr['id'] }}'
                                    ? 'bg-navy text-white border-navy shadow-sm'
                                    : 'bg-white text-navy border-gray-200 hover:border-navy/40 hover:bg-navy/5'"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-all">
                            {{ $mfr['name'] }} ({{ $mfr['count'] }})
                        </button>
                        @endforeach
                    </div>
                </div>
                @endif

                <button type="button" @click="apply()"
                        class="w-full btn-primary text-sm font-bold py-2.5">
                    {{ __('search.apply_filters') }}
                </button>
            </div>

            {{-- Manufacturer filter pills (desktop only) --}}
            @if(count($manufacturer_filter_options) > 1)
            <div class="hidden sm:flex flex-wrap items-center gap-2 pt-3 mt-2 border-t border-gray-100">
                <span class="text-xs font-semibold text-muted shrink-0">{{ __('search.brand') }}:</span>
                @foreach($manufacturer_filter_options as $mfr)
                <button type="button"
                        @click="manufacturer = (manufacturer == '{{ $mfr['id'] }}' ? '' : '{{ $mfr['id'] }}'); carModel = ''; apply()"
                        :class="manufacturer == '{{ $mfr['id'] }}'
                            ? 'bg-navy text-white border-navy shadow-sm'
                            : 'bg-white text-navy border-gray-200 hover:border-navy/40 hover:bg-navy/5'"
                        class="px-3 py-1.5 rounded-lg text-xs sm:text-sm font-semibold border transition-all duration-150 shrink-0 focus:outline-none focus:ring-2 focus:ring-amber/50"
                        :aria-pressed="manufacturer == '{{ $mfr['id'] }}'">
                    {{ $mfr['name'] }}
                    <span class="opacity-60 ml-0.5">({{ $mfr['count'] }})</span>
                </button>
                @endforeach
            </div>
            @endif

            {{-- Active filters chips --}}
            @if($activeFilterCount > 0)
            <div class="flex flex-wrap items-center gap-2.5 mt-3 pt-3 border-t border-gray-100">
                <span class="text-xs text-muted font-medium">{{ __('search.active_filters') }}</span>
                @if($sort !== 'default')
                <a href="{{ request()->fullUrlWithQuery(['sort' => null, 'page' => null]) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-navy/10 text-navy text-xs font-semibold hover:bg-navy/20 transition-colors focus:outline-none focus:ring-2 focus:ring-amber/50">
                    {{ __('search.sort_label') }} {{ $sort === 'price_asc' ? __('search.sort_chip_price_asc') : __('search.sort_chip_price_desc') }}
                    <x-heroicon-o-x-mark class="w-3 h-3" />
                </a>
                @endif
                @if($condition_filter)
                <a href="{{ request()->fullUrlWithQuery(['condition' => null, 'page' => null]) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-navy/10 text-navy text-xs font-semibold hover:bg-navy/20 transition-colors focus:outline-none focus:ring-2 focus:ring-amber/50">
                    {{ __('search.condition_chip', ['condition' => ucfirst($condition_filter)]) }}
                    <x-heroicon-o-x-mark class="w-3 h-3" />
                </a>
                @endif
                @if($in_stock_only)
                <a href="{{ request()->fullUrlWithQuery(['in_stock' => null, 'page' => null]) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-emerald-100 text-emerald-700 text-xs font-semibold hover:bg-emerald-200 transition-colors focus:outline-none focus:ring-2 focus:ring-amber/50">
                    {{ __('search.in_stock_only') }}
                    <x-heroicon-o-x-mark class="w-3 h-3" />
                </a>
                @endif
                @if($manufacturer_filter)
                @php
                    $mfrRow = collect($manufacturer_filter_options)->first(fn ($m) => (string) $m['id'] === (string) $manufacturer_filter);
                    $mfrLabel = $mfrRow['name'] ?? __('search.brand');
                @endphp
                <a href="{{ request()->fullUrlWithQuery(['manufacturer' => null, 'model' => null, 'page' => null]) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-navy/10 text-navy text-xs font-semibold hover:bg-navy/20 transition-colors focus:outline-none focus:ring-2 focus:ring-amber/50">
                    {{ $mfrLabel }}
                    <x-heroicon-o-x-mark class="w-3 h-3" />
                </a>
                @endif
                @if($car_model_filter)
                <a href="{{ request()->fullUrlWithQuery(['model' => null, 'page' => null]) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-navy/10 text-navy text-xs font-semibold hover:bg-navy/20 transition-colors focus:outline-none focus:ring-2 focus:ring-amber/50">
                    {{ __('search.model_chip', ['name' => $car_model_filter_label ?? __('search.model_unknown')]) }}
                    <x-heroicon-o-x-mark class="w-3 h-3" />
                </a>
                @endif
                <a href="{{ route('frontend.search.results', ['lang' => $lang, 'oem' => $normalized_query]) }}"
                   class="text-xs text-muted hover:text-red-500 transition-colors font-medium ml-1 focus:outline-none focus:ring-2 focus:ring-amber/50 rounded px-2 py-1">
                    {{ __('search.clear_all') }}
                </a>
            </div>
            @endif
        </div>
        </div>{{-- end sticky wrapper --}}

        {{-- ════════════════════════════════════════════════════════════ --}}
        {{-- FILTERED EMPTY STATE                                        --}}
        {{-- ════════════════════════════════════════════════════════════ --}}
        @if($filtered_empty)
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-8 text-center my-6">
            <div class="w-12 h-12 rounded-xl bg-amber/15 border border-amber/25 flex items-center justify-center mx-auto mb-4">
                <x-heroicon-o-funnel class="w-6 h-6 text-amber-text" />
            </div>
            <h2 class="font-display font-bold text-navy text-xl mb-2">{{ __('search.filtered_empty_title') }}</h2>
            <p class="text-muted text-sm max-w-sm mx-auto mb-5">
                {{ trans_choice('search.filtered_empty_paragraph', $unfiltered_total, ['oem' => $normalized_query, 'count' => $unfiltered_total]) }}
            </p>
            <a href="{{ route('frontend.search.results', ['lang' => $lang, 'oem' => $normalized_query]) }}"
               class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-navy text-white font-bold text-sm hover:bg-blue-900 transition-colors">
                <x-heroicon-o-x-mark class="w-4 h-4" />
                {{ __('search.clear_filters_cta', ['total' => $unfiltered_total]) }}
            </a>
        </div>
        @endif

        {{-- ════════════════════════════════════════════════════════════ --}}
        {{-- PRODUCT LISTING — Data Table Pro                            --}}
        {{-- ════════════════════════════════════════════════════════════ --}}
        @if(!$filtered_empty)
        @php
            $avgNumericForVs = isset($price_stats['avg_numeric']) && $price_stats['avg_numeric'] !== null
                ? (float) $price_stats['avg_numeric'] : null;
        @endphp
        <div class="mt-2">

            {{-- ═══ TABLE VIEW (Desktop) ══════════════════════════════ --}}
            <div x-show="lg" x-cloak>
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[760px] table-auto border-collapse text-sm" role="table">
                            <caption class="sr-only">{{ __('search.table_caption', ['oem' => $normalized_query]) }}</caption>
                            <thead>
                                <tr class="table-row bg-slate-100/80 backdrop-blur-sm border-b border-gray-200">
                                    <th class="text-left px-5 py-3.5 text-[11px] font-bold text-slate-700 uppercase tracking-widest align-middle" scope="col">
                                        <span class="flex items-center gap-2">
                                            <x-heroicon-s-tag class="w-3.5 h-3.5 text-slate-400" />
                                            {{ __('search.th_oem_brand') }}
                                        </span>
                                    </th>
                                    <th class="text-center px-4 py-3.5 text-[11px] font-bold text-slate-700 uppercase tracking-widest align-middle whitespace-nowrap" scope="col">
                                        {{ __('search.th_condition') }}
                                    </th>
                                    <th class="text-center px-4 py-3.5 text-[11px] font-bold text-slate-700 uppercase tracking-widest align-middle whitespace-nowrap" scope="col">
                                        {{ __('search.th_stock') }}
                                    </th>
                                    <th class="text-right px-4 py-3.5 text-[11px] font-bold text-slate-700 uppercase tracking-widest align-middle whitespace-nowrap" scope="col">
                                        <span class="flex items-center justify-end gap-1">
                                            {{ __('search.th_price') }}
                                            <x-heroicon-s-currency-euro class="w-3 h-3 text-slate-400" />
                                        </span>
                                    </th>
                                    <th class="text-center px-5 py-3.5 text-[11px] font-bold text-slate-700 uppercase tracking-widest align-middle whitespace-nowrap" scope="col">
                                        {{ __('search.th_action') }}
                                    </th>
                                </tr>
                            </thead>
                            @foreach($products as $index => $product)
                                @php
                                    $manufacturer = $product->manufacturer;
                                    $logoPath     = $manufacturer?->logo?->file_path;
                                    $crossRefs    = $product->crossReferences ?? collect();
                                    $priceWithVat = bcmul((string) $product->price, $vatMultiplier, 2);
                                    $cCond = $product->condition;
                                    $condKey = $cCond instanceof \BackedEnum
                                        ? $cCond->value
                                        : (is_scalar($cCond) ? (string) $cCond : (string) \Illuminate\Support\enum_value($cCond, 'new'));
                                    $condBadge    = $conditionBadgeMap[$condKey] ?? $conditionBadgeMap['new'];
                                    $condLabel    = $conditionLabelMap[$condKey] ?? __('search.condition_unknown');
                                @endphp

    <tbody
      class="border-b border-gray-100 transition-colors {{ $index === 0 && $sort === 'default' ? 'ring-1 ring-amber/30 ring-inset' : ($index % 2 === 1 ? 'bg-navy/[0.02]' : 'bg-transparent') }} hover:bg-gray-50/50"
      x-data="searchProductRow({ productId: {{ $product->id }}, oemNumber: '{{ $product->oem_number }}', manufacturerName: '{{ $manufacturer ? trans_field($manufacturer->name) : __('search.unknown_brand') }}' })"
    >
                                {{-- Best Match banner row (desktop table, first result only) --}}
                                @if($index === 0 && $sort === 'default')
                                <tr class="table-row">
                                    <td colspan="5" class="px-5 pt-2 pb-0">
                                        <div class="flex items-center gap-1.5 text-[10px] font-extrabold text-amber-text uppercase tracking-wider">
                                            <x-heroicon-s-star class="w-3 h-3 text-amber shrink-0" />
                                            {{ __('search.best_match') }}
                                        </div>
                                    </td>
                                </tr>
                                @endif

                                {{-- Main product row --}}
                                <tr
                                    class="table-row oem-results-row transition-colors duration-150"
                                    role="row"
                                >
                                    {{-- OEM Number + Brand --}}
                                    <td class="px-5 py-4 align-middle min-w-0 overflow-visible">
                                        <div class="flex items-center gap-3 min-w-0">
                                            @if($logoPath)
                                            <img src="{{ asset('storage/' . $logoPath) }}"
                                                 alt="{{ trans_field($manufacturer->name) }}"
                                                 class="h-9 w-9 shrink-0 object-contain rounded-lg border border-gray-100 p-1 bg-white"
                                                 loading="lazy"
                                                 width="36"
                                                 height="36">
                                            @else
                                            <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-navy/5 to-navy/10
                                                        border border-navy/10 flex items-center justify-center shrink-0">
                                                <x-heroicon-o-building-office-2 class="w-4 h-4 text-navy/40" />
                                            </div>
                                            @endif
                                            <div class="min-w-0">
                                                <p class="font-mono text-sm font-bold text-navy group-hover:text-amber-text transition-colors truncate">
                                                    {{ $product->oem_number }}
                                                </p>
                                                <p class="text-xs text-muted truncate mt-0.5">
                                                    {{ $manufacturer ? trans_field($manufacturer->name) : __('search.unknown_brand') }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Condition --}}
                                    <td class="px-4 py-4 text-center align-middle">
                                        <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide {{ $condBadge['bg'] }} {{ $condBadge['text'] }}">
                                            {{ $condLabel }}
                                        </span>
                                    </td>

                                    {{-- Stock --}}
                                    <td class="px-4 py-4 text-center align-middle">
                                        @if($product->is_in_stock)
                                        <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                            {{ __('search.stock_in') }}
                                        </span>
                                        @else
                                        <span class="inline-flex items-center gap-1 text-xs font-semibold text-gray-400">
                                            <span class="w-1.5 h-1.5 rounded-full bg-gray-300"></span>
                                            {{ __('search.stock_out') }}
                                        </span>
                                        @endif
                                    </td>

                                    {{-- Price --}}
                                    <td class="px-4 py-4 text-right align-middle">
                                        <div class="flex flex-col items-end">
                                            <p class="font-mono text-base font-bold text-navy leading-none">
                                                €{{ number_format($product->price, 2) }}
                                            </p>
                                        </div>
                                    </td>

                                    {{-- Action (Qty + Add to Cart) --}}
                                    <td class="px-5 py-4 text-center align-middle">
                                        @if($product->is_in_stock)
                                        <div class="flex items-center gap-2 justify-center">
                                            {{-- Quantity stepper --}}
                                            <div class="inline-flex items-center bg-white/50 rounded-[14px] p-1 border border-gray-200 shadow-inner">
                                                <button type="button"
                                                        @click="quantity = Math.max(1, quantity - 1)"
                                                        class="w-7 h-7 rounded-[10px] flex items-center justify-center text-navy/50 disabled:opacity-30 border border-transparent hover:border-gray-300 hover:bg-white hover:shadow-sm transition-all"
                                                        :disabled="quantity <= 1"
                                                        aria-label="{{ __('search.aria_decrease_qty') }}">
                                                    <x-heroicon-o-minus class="w-3 h-3" />
                                                </button>
                                                <input type="text"
                                                       inputmode="numeric"
                                                       x-model.number="quantity"
                                                       @keydown.up.prevent="quantity = Math.min(99, quantity + 1)"
                                                       @keydown.down.prevent="quantity = Math.max(1, quantity - 1)"
                                                       class="w-10 h-7 text-center text-xs font-bold text-navy bg-transparent border-0 focus:ring-0 p-0"
                                                       aria-label="{{ __('search.aria_quantity') }}">
                                                <button type="button"
                                                        @click="quantity = Math.min(99, quantity + 1)"
                                                        class="w-7 h-7 rounded-[10px] flex items-center justify-center text-navy/50 disabled:opacity-30 border border-transparent hover:border-gray-300 hover:bg-white hover:shadow-sm transition-all"
                                                        :disabled="quantity >= 99"
                                                        aria-label="{{ __('search.aria_increase_qty') }}">
                                                    <x-heroicon-o-plus class="w-3 h-3" />
                                                </button>
                                            </div>

                                            {{-- Add to Cart button --}}
                                            <button
                                                @click="addToCart"
                                                :disabled="cartState !== 'idle'"
                                                class="relative flex items-center gap-1.5 px-4 py-2 rounded-lg font-bold text-xs
                                                       transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-amber/60"
                                                :class="{
                                                    'bg-amber text-navy hover:bg-amber/90': cartState === 'idle',
                                                    'bg-amber/50 text-navy/60 cursor-wait': cartState === 'loading',
                                                    'bg-emerald-500 text-white': cartState === 'added'
                                                }"
                                            >
                                                <span x-show="cartState === 'idle'" class="flex items-center gap-1.5">
                                                    <x-heroicon-o-shopping-cart class="w-3.5 h-3.5" />
                                                    {{ __('search.btn_add') }}
                                                </span>
                                                <span x-show="cartState === 'loading'" x-cloak>
                                                    <svg class="animate-spin w-3.5 h-3.5" viewBox="0 0 24 24" fill="none">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                    </svg>
                                                </span>
                                                <span x-show="cartState === 'added'" x-cloak>
                                                    <x-heroicon-s-check class="w-3.5 h-3.5" />
                                                </span>
                                            </button>
                                        </div>
                                        <p x-show="cartError" x-text="cartError" x-cloak class="text-center text-[11px] text-red-600 font-medium mt-1 max-w-[220px] mx-auto leading-snug"></p>
                                        @else
                                        <button
                                            @click="$dispatch('open-inquiry-modal', { oem: '{{ $product->oem_number }}' })"
                                            type="button"
                                            class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl text-xs font-bold
                                                   bg-navy text-white shadow-sm border border-navy
                                                   hover:bg-navy/90 hover:shadow-md
                                                   transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-amber/60 focus:ring-offset-2">
                                            <x-heroicon-o-paper-airplane class="w-3.5 h-3.5 shrink-0" />
                                            {{ __('search.btn_request') }}
                                        </button>
                                        @endif
                                    </td>
                                </tr>

                                {{-- Expandable details row (no x-transition on <tr> — breaks table-row display in browsers) --}}
                                <tr x-show="detailsOpen" x-cloak class="table-row">
                                    <td colspan="5" class="px-5 py-5 border-t border-gray-100/50">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">

                                            {{-- Delivery Time --}}
                                            @if($product->delivery_time)
                                            <div class="flex items-start gap-3 p-3.5 rounded-xl bg-white border border-gray-100 shadow-sm">
                                                <div class="w-9 h-9 rounded-lg bg-blue-50 border border-blue-100 flex items-center justify-center shrink-0">
                                                    <x-heroicon-o-truck class="w-4 h-4 text-blue-600" />
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <p class="text-[10px] text-muted font-medium uppercase tracking-wide">{{ __('search.delivery') }}</p>
                                                    <p class="text-sm font-bold text-navy mt-0.5">{{ $product->delivery_time }}</p>
                                                </div>
                                            </div>
                                            @endif

                                            {{-- Condition Details --}}
                                            <div class="flex items-start gap-3 p-3.5 rounded-xl bg-white border border-gray-100 shadow-sm">
                                                <div class="w-9 h-9 rounded-lg bg-emerald-50 border border-emerald-100 flex items-center justify-center shrink-0">
                                                    <x-heroicon-o-shield-check class="w-4 h-4 text-emerald-600" />
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <p class="text-[10px] text-muted font-medium uppercase tracking-wide">{{ __('search.condition_detail') }}</p>
                                                    <p class="text-sm font-bold text-navy mt-0.5">{{ $condLabel }}</p>
                                                </div>
                                            </div>

                                            {{-- VAT Price --}}
                                            <div class="flex items-start gap-3 p-3.5 rounded-xl bg-white border border-gray-100 shadow-sm">
                                                <div class="w-9 h-9 rounded-lg bg-purple-50 border border-purple-100 flex items-center justify-center shrink-0">
                                                    <x-heroicon-o-calculator class="w-4 h-4 text-purple-600" />
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <p class="text-[10px] text-muted font-medium uppercase tracking-wide">{{ __('search.incl_vat', ['rate' => $vat_rate]) }}</p>
                                                    <p class="font-mono text-sm font-bold text-navy mt-0.5">€{{ number_format($priceWithVat, 2) }}</p>
                                                </div>
                                            </div>

                                        </div>

                                        {{-- Cross-References --}}
                                        @if($crossRefs->isNotEmpty())
                                        @php $refLimit = 4; $totalRefs = $crossRefs->count(); @endphp
                                        <div class="mt-5 pt-4 border-t border-gray-200">
                                            <div class="flex items-center gap-2 mb-3">
                                                <x-heroicon-o-arrow-path class="w-3.5 h-3.5 text-amber" />
                                                <span class="text-xs font-bold text-navy">{{ __('search.cross_refs_title') }}</span>
                                                <span class="text-[10px] text-muted">{{ __('search.cross_refs_hint') }}</span>
                                            </div>
                                            <div class="flex flex-wrap gap-2">
                                                @foreach($crossRefs as $cross)
                                                <a href="{{ route('frontend.search.results', ['lang' => $lang, 'oem' => $cross->normalized_cross_oem ?? $cross->cross_oem_number]) }}"
                                                   x-show="{{ $loop->index }} < {{ $refLimit }} || crossRefsOpen"
                                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg
                                                          bg-white border border-gray-200 text-xs font-mono font-semibold text-navy
                                                          hover:bg-amber/5 hover:border-amber/30 hover:text-amber-text
                                                          transition-all duration-200">
                                                    {{ $cross->cross_oem_number }}
                                                    <x-heroicon-o-arrow-right class="w-3 h-3 text-gray-300 hover:translate-x-0.5 transition-transform" />
                                                </a>
                                                @endforeach

                                                @if($totalRefs > $refLimit)
                                                <button type="button" @click="crossRefsOpen = !crossRefsOpen"
                                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-dashed border-gray-300 text-xs font-bold text-muted hover:border-amber/50 hover:text-amber-text transition-all">
                                                    <span x-show="!crossRefsOpen">{{ __('search.cross_refs_view_all', ['count' => $totalRefs]) }}</span>
                                                    <span x-show="crossRefsOpen" x-cloak>{{ __('search.cross_refs_show_less') }}</span>
                                                    <x-heroicon-o-chevron-down class="w-3 h-3 transition-transform" :class="crossRefsOpen ? 'rotate-180' : ''" />
                                                </button>
                                                @endif
                                            </div>
                                        </div>
                                        @endif

                                        {{-- Collapse button --}}
                                        <div class="mt-4 flex justify-center">
                                            <button @click="detailsOpen = false"
                                                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-muted hover:text-amber-text transition-colors">
                                                <x-heroicon-o-chevron-up class="w-3.5 h-3.5" />
                                                {{ __('search.hide_details') }}
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                {{-- Expand toggle row --}}
                                <tr x-show="!detailsOpen" class="table-row">
                                    <td colspan="5" class="px-5 pb-3 -mt-2">
                                        <button @click="detailsOpen = true"
                                                class="inline-flex items-center gap-1.5 text-xs font-semibold text-muted hover:text-amber-text transition-colors">
                                            <x-heroicon-o-chevron-down class="w-3 h-3" />
                                            <span class="hidden sm:inline">{{ __('search.more_details') }}</span>
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
            <div x-show="!lg" x-cloak class="space-y-3">
                @foreach($products as $product)
                @php
                    $manufacturer = $product->manufacturer;
                    $logoPath     = $manufacturer?->logo?->file_path;
                    $priceWithVat = bcmul((string) $product->price, $vatMultiplier, 2);
                    $cCond = $product->condition;
                    $condKey = $cCond instanceof \BackedEnum
                        ? $cCond->value
                        : (is_scalar($cCond) ? (string) $cCond : (string) \Illuminate\Support\enum_value($cCond, 'new'));
                    $condBadge    = $conditionBadgeMap[$condKey] ?? $conditionBadgeMap['new'];
                    $condLabel    = $conditionLabelMap[$condKey] ?? __('search.condition_unknown');
                    $crossRefs    = $product->crossReferences ?? collect();
                @endphp

<div
      x-data="searchProductRow({
        productId: {{ $product->id }},
        oemNumber: '{{ $product->oem_number }}',
        manufacturerName: '{{ $manufacturer ? trans_field($manufacturer->name) : __('search.unknown_brand') }}'
      })"
                    class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden
                           {{ $loop->index === 0 && $sort === 'default' ? 'ring-1 ring-amber/40' : '' }}"
                >
                    {{-- Best Match banner --}}
                    @if($loop->index === 0 && $sort === 'default')
                    <div class="flex items-center gap-2 bg-gradient-to-r from-amber/8 to-orange-50/60 border-b border-amber/15 px-4 py-2">
                        <x-heroicon-s-star class="w-3 h-3 text-amber shrink-0" />
                        <span class="text-[10px] font-extrabold text-amber-text uppercase tracking-wider">{{ __('search.best_match') }}</span>
                    </div>
                    @endif

                    {{-- Card content --}}
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-3 mb-3">
                            {{-- OEM + Brand --}}
                            <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                @if($logoPath)
                                <img src="{{ asset('storage/' . $logoPath) }}"
                                     alt="{{ trans_field($manufacturer->name) }}"
                                     class="w-8 h-8 object-contain rounded-lg border border-gray-100 p-0.5 shrink-0"
                                     loading="lazy"
                                     width="32"
                                     height="32">
                                @else
                                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-navy/5 to-navy/10 border border-navy/10 flex items-center justify-center shrink-0">
                                    <x-heroicon-o-building-office-2 class="w-4 h-4 text-navy/40" />
                                </div>
                                @endif
                                <div class="min-w-0">
                                    <p class="font-mono text-sm font-bold text-navy truncate">{{ $product->oem_number }}</p>
                                    <p class="text-xs text-muted truncate">{{ $manufacturer ? trans_field($manufacturer->name) : '—' }}</p>
                                </div>
                            </div>

                            {{-- Condition badge --}}
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wide shrink-0 {{ $condBadge['bg'] }} {{ $condBadge['text'] }}">
                                {{ $condLabel }}
                            </span>
                        </div>

                        {{-- Stock + Price row --}}
                        <div class="flex items-center justify-between mb-4">
                            @if($product->is_in_stock)
                            <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                {{ __('search.stock_in') }}
                            </span>
                            @else
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-gray-400">
                                <span class="w-1.5 h-1.5 rounded-full bg-gray-300"></span>
                                {{ __('search.stock_out_long') }}
                            </span>
                            @endif

                            <div class="text-right">
                                <p class="font-mono text-lg font-bold text-navy leading-none">€{{ number_format($product->price, 2) }}</p>
                                <p class="text-[10px] text-muted mt-0.5">{{ __('search.excl_vat_short') }}</p>
                            </div>
                        </div>

                        {{-- Action row --}}
                        @if($product->is_in_stock)
                        <div class="flex items-center gap-2">
                            {{-- Qty stepper --}}
                            <div class="inline-flex items-center bg-white/50 rounded-[14px] p-1 border border-gray-200 shadow-inner">
                                <button type="button"
                                        @click="quantity = Math.max(1, quantity - 1)"
                                        class="w-8 h-8 rounded-[10px] flex items-center justify-center text-navy/50 disabled:opacity-30 border border-transparent hover:border-gray-300 hover:bg-white hover:shadow-sm transition-all"
                                        :disabled="quantity <= 1"
                                        aria-label="{{ __('search.aria_decrease_qty') }}">
                                    <x-heroicon-o-minus class="w-4 h-4" />
                                </button>
                                <input type="text"
                                       inputmode="numeric"
                                       x-model.number="quantity"
                                       class="w-10 h-8 text-center text-sm font-bold text-navy bg-transparent border-0 focus:ring-0 p-0"
                                       aria-label="{{ __('search.aria_quantity') }}">
                                <button type="button"
                                        @click="quantity = Math.min(99, quantity + 1)"
                                        class="w-8 h-8 rounded-[10px] flex items-center justify-center text-navy/50 disabled:opacity-30 border border-transparent hover:border-gray-300 hover:bg-white hover:shadow-sm transition-all"
                                        :disabled="quantity >= 99"
                                        aria-label="{{ __('search.aria_increase_qty') }}">
                                    <x-heroicon-o-plus class="w-4 h-4" />
                                </button>
                            </div>

                            {{-- Add to Cart --}}
                            <button
                                @click="addToCart"
                                :disabled="cartState !== 'idle'"
                                class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl font-bold text-sm
                                       transition-colors duration-150"
                                :class="{
                                    'bg-amber text-navy hover:bg-amber/90': cartState === 'idle',
                                    'bg-amber/50 text-navy/60 cursor-wait': cartState === 'loading',
                                    'bg-emerald-500 text-white': cartState === 'added'
                                }"
                            >
                                <span x-show="cartState === 'idle'">
                                    <x-heroicon-o-shopping-cart class="w-4 h-4 inline" />
                                    {{ __('search.btn_add_to_cart') }}
                                </span>
                                <span x-show="cartState === 'loading'" x-cloak>
                                    <svg class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                </span>
                                <span x-show="cartState === 'added'" x-cloak>
                                    <x-heroicon-s-check class="w-4 h-4 inline" />
                                    {{ __('search.btn_added') }}
                                </span>
                            </button>
                        </div>
                        <p x-show="cartError" x-text="cartError" x-cloak class="text-xs text-red-600 font-medium mt-2 text-center"></p>
                        @else
                        <button
                            type="button"
                            @click="$dispatch('open-inquiry-modal', { oem: '{{ $product->oem_number }}' })"
                            class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-2xl text-sm font-bold
                                   bg-navy text-white shadow-sm border border-navy hover:bg-navy/90 hover:shadow-md transition-all
                                   focus:outline-none focus:ring-2 focus:ring-amber/60 focus:ring-offset-2">
                            <x-heroicon-o-paper-airplane class="w-4 h-4 shrink-0" />
                            {{ __('search.request_this_part') }}
                        </button>
                        @endif

                        {{-- Expand toggle --}}
                        <button @click="detailsOpen = !detailsOpen"
                                class="mt-3 w-full flex items-center justify-center gap-1.5 text-xs font-semibold text-muted hover:text-amber-text transition-colors py-1.5 rounded-lg hover:bg-gray-50">
                            <x-heroicon-o-chevron-down class="w-3 h-3 transition-transform" x-bind:class="detailsOpen ? 'rotate-180' : ''" />
                            <span x-show="!detailsOpen">{{ __('search.more_details') }}</span>
                            <span x-show="detailsOpen" x-cloak>{{ __('search.hide_details') }}</span>
                        </button>
                    </div>

                    {{-- Expanded details --}}
                    <div x-show="detailsOpen" x-cloak
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         class="border-t border-gray-100 bg-gray-50/50 px-4 py-4 space-y-3">

                        <div class="grid grid-cols-2 gap-3">
                            @if($product->delivery_time)
                            <div class="flex items-start gap-2 p-3 rounded-lg bg-white border border-gray-100">
                                <x-heroicon-o-truck class="w-4 h-4 text-blue-600 shrink-0 mt-0.5" />
                                <div>
                                    <p class="text-[10px] text-muted uppercase">{{ __('search.delivery') }}</p>
                                    <p class="text-xs font-bold text-navy">{{ $product->delivery_time }}</p>
                                </div>
                            </div>
                            @endif

                            <div class="flex items-start gap-2 p-3 rounded-lg bg-white border border-gray-100">
                                <x-heroicon-o-calculator class="w-4 h-4 text-purple-600 shrink-0 mt-0.5" />
                                <div>
                                    <p class="text-[10px] text-muted uppercase">{{ __('search.incl_vat', ['rate' => $vat_rate]) }}</p>
                                    <p class="font-mono text-xs font-bold text-navy">€{{ number_format($priceWithVat, 2) }}</p>
                                </div>
                            </div>
                        </div>

                        @if($crossRefs->isNotEmpty())
                        @php $refLimit = 4; $totalRefs = $totalRefs ?? $crossRefs->count(); @endphp
                        <div class="pt-2 border-t border-gray-200">
                            <p class="text-[10px] font-bold text-muted uppercase mb-2 flex items-center gap-1.5">
                                <x-heroicon-o-arrow-path class="w-3 h-3 text-amber" />
                                {{ __('search.cross_refs_title') }}
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($crossRefs as $cross)
                                <a href="{{ route('frontend.search.results', ['lang' => $lang, 'oem' => $cross->normalized_cross_oem ?? $cross->cross_oem_number]) }}"
                                   x-show="{{ $loop->index }} < {{ $refLimit }} || crossRefsOpen"
                                   class="px-2.5 py-1 rounded-md bg-white border border-gray-200 text-[10px] font-mono font-semibold text-navy hover:bg-amber/5 hover:border-amber/30 transition-all">
                                    {{ $cross->cross_oem_number }}
                                </a>
                                @endforeach

                                @if($totalRefs > $refLimit)
                                <button type="button" @click="crossRefsOpen = !crossRefsOpen"
                                        class="px-2 py-1 rounded-md border border-dashed border-gray-300 text-[9px] font-bold text-muted hover:border-amber/50 hover:text-amber-text transition-all">
                                    <span x-show="!crossRefsOpen">{{ __('search.cross_refs_view_all', ['count' => $totalRefs]) }}</span>
                                    <span x-show="crossRefsOpen" x-cloak>{{ __('search.cross_refs_show_less') }}</span>
                                </button>
                                @endif
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>

        </div>{{-- end listing (shared Alpine store for view) --}}
        @endif{{-- end @if(!$filtered_empty) --}}

        {{-- ── Pagination ──────────────────────────────────────────────── --}}
        @if($products instanceof \Illuminate\Pagination\LengthAwarePaginator && $products->hasPages())
        <div class="mt-10 pt-8">
            {{ $products->links('components.ui.pagination') }}
        </div>
        @endif

        {{-- ── Bottom CTA — Part Inquiry ─────────────────────────────── --}}
        <div class="mt-10 bg-white rounded-xl border border-gray-100 shadow-sm p-6 sm:p-8">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between sm:gap-6">
                <div class="flex flex-1 items-start gap-4 min-w-0">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber/15 border border-amber/25">
                        <x-heroicon-o-paper-airplane class="h-5 w-5 text-amber-text" />
                    </div>
                    <div class="min-w-0">
                        <p class="font-display text-lg font-bold text-navy">
                            {{ __('search.inquiry_title') }}
                        </p>
                        <p class="mt-1 text-sm text-body leading-relaxed">
                            {{ __('search.inquiry_subtitle', ['hours' => (int) settings('part_inquiry.response_hours', 24)]) }}
                        </p>
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-3 text-[11px] font-medium text-muted">
                            <span class="inline-flex items-center gap-1">
                                <x-heroicon-s-shield-check class="h-3 w-3 text-emerald-600" />
                                {{ __('search.inquiry_trust_suppliers') }}
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <x-heroicon-s-building-storefront class="h-3 w-3 text-blue-600" />
                                {{ __('search.inquiry_trust_warehouse') }}
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <x-heroicon-s-trophy class="h-3 w-3 text-amber-text" />
                                {{ __('search.inquiry_trust_quality') }}
                            </span>
                        </div>
                    </div>
                </div>
                <button type="button"
                        onclick="window.dispatchEvent(new CustomEvent('open-inquiry-modal'))"
                        class="shrink-0 inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-amber text-navy font-bold text-sm hover:bg-amber/90 transition-colors">
                    <x-heroicon-o-paper-airplane class="h-4 w-4" />
                    {{ __('search.inquiry_submit') }}
                </button>
            </div>
        </div>

    </div>{{-- end max-w-6xl --}}
    </div>{{-- end relative z-10 --}}
</div>{{-- end bg-F8FAFC --}}

{{-- ── Scroll to Top ────────────────────────────────────────────────────── --}}
<div
    x-data="{ show: false }"
    x-init="window.addEventListener('scroll', () => { show = window.scrollY > 400 }, { passive: true })"
    x-show="show"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 translate-y-4"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 translate-y-4"
    x-cloak
    class="fixed bottom-4 right-4 sm:bottom-6 sm:right-6 z-40"
>
    <button @click="window.scrollTo({ top: 0, behavior: 'smooth' })"
            class="w-10 h-10 rounded-full bg-navy shadow-lg shadow-navy/30
                   flex items-center justify-center text-white
                   hover:bg-amber hover:shadow-amber/40 hover:scale-110
                   transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-amber/70"
            title="{{ __('search.scroll_to_top') }}"
            aria-label="{{ __('search.scroll_to_top') }}">
        <x-heroicon-o-arrow-up class="w-5 h-5" />
    </button>
</div>

<x-modals.part-inquiry :normalized-query="$normalized_query ?? ''" />

@push('scripts')
<script>
function searchProductRow(config) {
    const lang = '{{ app()->getLocale() }}';
    const failMsg = @json(__('search.cart_add_failed'));

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
                const res = await fetch(`/${lang}/cart/add`, {
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
