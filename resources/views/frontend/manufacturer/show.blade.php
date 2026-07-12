@extends('layouts.app')

@php
    $lang        = app()->getLocale();
    $siteName    = settings('general.site_name', 'OeParts');
    $brandName   = trans_field($manufacturer->name) ?: $manufacturer->slug;
    $totalParts  = $products->total();
    $modelCount  = $carModels->count();
@endphp

{{-- ── SEO ──────────────────────────────────────────────────────────────── --}}
@section('title'){{ $brandName }} · {{ __('Genuine OEM Parts') }} · {{ $siteName }}@endsection
@section('meta_description'){{ __(':brand genuine OEM parts — cross-reference every part number, verify fitment, and ship across the EU.', ['brand' => $brandName]) }}@endsection
@section('og_title'){{ $brandName }} · {{ __('OEM Parts Catalogue') }}@endsection
@section('og_description'){{ __(':count verified :brand parts indexed in the OeParts catalogue.', ['count' => $totalParts, 'brand' => $brandName]) }}@endsection
@section('canonical')
    <link rel="canonical" href="{{ route('frontend.manufacturer.show', ['lang' => $lang, 'manufacturer' => $manufacturer->slug]) }}">
@endsection

@section('hreflang')
    @foreach(['en', 'de', 'lt', 'fr', 'es'] as $hLang)
        <link rel="alternate" hreflang="{{ $hLang }}" href="{{ route('frontend.manufacturer.show', ['lang' => $hLang, 'manufacturer' => $manufacturer->slug]) }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ route('frontend.manufacturer.show', ['lang' => 'en', 'manufacturer' => $manufacturer->slug]) }}">
@endsection

@section('json_ld')
<script type="application/ld+json">
{!! json_encode([
    '@@context' => 'https://schema.org',
    '@type'    => 'Organization',
    'name'     => $brandName,
    'url'      => route('frontend.manufacturer.show', ['lang' => $lang, 'manufacturer' => $manufacturer->slug]),
    'brand'    => [
        '@type' => 'Brand',
        'name'  => $brandName,
    ],
    'numberOfEmployees' => null,
    'description' => __(':brand genuine OEM parts — cross-reference every part number, verify fitment, and ship across the EU.', ['brand' => $brandName]),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
</script>
<script type="application/ld+json">
{!! json_encode([
    '@@context'        => 'https://schema.org',
    '@type'           => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => __('Home'),   'item' => url('/'.$lang.'/')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => __('Brands'), 'item' => route('frontend.manufacturer.index', ['lang' => $lang])],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $brandName,   'item' => route('frontend.manufacturer.show', ['lang' => $lang, 'manufacturer' => $manufacturer->slug])],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@php
    $manufacturerJsonLdItems = [];
    foreach ($products->items() as $index => $product) {
        $manufacturerJsonLdItems[] = [
            '@type' => 'ListItem',
            'position' => $index + 1,
            'item' => [
                '@type' => 'Product',
                'name' => $product->oem_number,
                'sku' => $product->oem_number,
                'url' => url('/'.$lang.'/parts/'.urlencode($product->oem_number)),
                'brand' => ['@type' => 'Brand', 'name' => $brandName],
                'offers' => [
                    '@type' => 'Offer',
                    'price' => (string) $product->price,
                    'priceCurrency' => settings('store.currency', 'EUR'),
                    'availability' => $product->is_in_stock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                ],
            ],
        ];
    }
@endphp
@if(!empty($manufacturerJsonLdItems))
<script type="application/ld+json">
{!! json_encode([
    '@@context' => 'https://schema.org',
    '@type' => 'ItemList',
    'numberOfItems' => $products->total(),
    'itemListElement' => $manufacturerJsonLdItems,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endif
@endsection

{{-- ══════════════════════════════════════════════════════════════════════
     INDUSTRIAL BLUEPRINT — BRAND DETAIL (show.blade.php)
     Brand identity card + compatible models + paginated parts ledger.
     ══════════════════════════════════════════════════════════════════ --}}
@section('content')

<div class="relative bg-ivory text-ink min-h-screen">

    <div class="fixed inset-0 bg-grid-ivory-fine bg-grid-sm opacity-70 pointer-events-none" aria-hidden="true"></div>

    <div class="relative max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-10 pt-10 pb-24">

        {{-- ═══ Doc header ═══ --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-5 border-b border-rule mb-10 bp-rise">
            <nav class="flex items-center gap-3 font-mono text-[11px] uppercase tracking-[0.16em] text-ink-muted" aria-label="Breadcrumb">
                <a href="{{ url('/'.$lang.'/') }}" class="hover:text-ink transition-colors">{{ __('Home') }}</a>
                <span class="text-rule-strong">/</span>
                <a href="{{ route('frontend.manufacturer.index', ['lang' => $lang]) }}" class="hover:text-ink transition-colors">{{ __('Brands') }}</a>
                <span class="text-rule-strong">/</span>
                <span class="text-ink truncate max-w-[14rem]">{{ $brandName }}</span>
            </nav>
            <div class="font-mono text-[10px] tracking-[0.2em] uppercase text-ink-muted">
                DOC · BRAND · {{ strtoupper($manufacturer->slug) }}
            </div>
        </div>

        {{-- ═══ 00 · Identity panel ═══ --}}
        <section class="grid grid-cols-12 gap-x-4 sm:gap-x-6 lg:gap-x-8 gap-y-6 mb-14 bp-rise bp-rise-delay-1">

            {{-- Large logo/monogram --}}
            <div class="col-span-12 md:col-span-4">
                <div class="relative border border-ink bg-paper aspect-square flex items-center justify-center overflow-hidden"
                     style="box-shadow: 6px 6px 0 rgba(20,22,29,1);">
                    <span class="absolute top-3 left-3 bp-spec-mono">
                        00 · {{ __('Mark') }}
                    </span>
                    @if($manufacturer->logo && $manufacturer->logo->file_url)
                        <img src="{{ $manufacturer->logo->file_url }}"
                             alt="{{ $brandName }}"
                             class="max-w-[70%] max-h-[70%] object-contain" />
                    @else
                        <div class="flex flex-col items-center">
                            <span class="font-display text-7xl sm:text-8xl font-extrabold text-ink tracking-[-0.04em] leading-none">
                                {{ strtoupper(mb_substr($brandName, 0, 2)) }}
                            </span>
                            <span class="mt-4 bp-spec-mono">
                                {{ __('Logo pending') }}
                            </span>
                        </div>
                    @endif
                    {{-- Corner amber tick --}}
                    <span class="absolute top-0 right-0 w-8 h-[3px] bg-amber"></span>
                    <span class="absolute top-0 right-0 w-[3px] h-8 bg-amber"></span>
                </div>
            </div>

            {{-- Identity + meta ledger --}}
            <div class="col-span-12 md:col-span-8">
                <div class="flex items-center gap-4 mb-6">
                    <span class="w-10 h-[3px] bg-amber inline-block"></span>
                    <span class="bp-spec text-amber-ink">{{ __('Manufacturer · Identity') }}</span>
                </div>

                <h1 class="font-display font-extrabold text-ink leading-[0.95] tracking-[-0.03em]
                           text-4xl sm:text-5xl lg:text-6xl break-words">
                    {{ $brandName }}<span class="text-amber">.</span>
                </h1>

                <div class="mt-5 mb-6">
                    <div class="bp-rule-draw h-px bg-ink/70 origin-left"></div>
                </div>

                <p class="max-w-2xl text-base text-body leading-relaxed mb-8">
                    {{ __('Every :brand part indexed in our catalogue — authenticated by OEM number and cross-referenced against compatible fitments.', ['brand' => $brandName]) }}
                </p>

                {{-- Spec ledger --}}
                <dl class="grid grid-cols-2 sm:grid-cols-4 gap-0 border border-ink bg-paper divide-x divide-rule max-w-3xl">
                    <div class="px-4 py-3">
                        <dt class="bp-spec text-ink-muted">{{ __('Parts') }}</dt>
                        <dd class="mt-1 font-mono text-xl font-bold text-ink tabular-nums leading-none">{{ number_format($totalParts) }}</dd>
                    </div>
                    <div class="px-4 py-3">
                        <dt class="bp-spec text-ink-muted">{{ __('Models') }}</dt>
                        <dd class="mt-1 font-mono text-xl font-bold text-ink tabular-nums leading-none">{{ $modelCount }}</dd>
                    </div>
                    <div class="px-4 py-3">
                        <dt class="bp-spec text-ink-muted">{{ __('Status') }}</dt>
                        <dd class="mt-1 font-mono text-xs font-bold text-emerald-700 uppercase tracking-[0.18em] leading-none flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 bg-emerald-600"></span>
                            {{ __('Active') }}
                        </dd>
                    </div>
                    <div class="px-4 py-3">
                        <dt class="bp-spec text-ink-muted">{{ __('Source') }}</dt>
                        <dd class="mt-1 font-mono text-xs font-bold {{ $manufacturer->is_verified_oem ? 'text-amber-ink' : 'text-ink-muted' }} uppercase tracking-[0.18em] leading-none">
                            {{ $manufacturer->is_verified_oem ? '✓ Verified OEM' : 'Listed' }}
                        </dd>
                    </div>
                </dl>

                {{-- CTAs --}}
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="{{ route('frontend.search.console', ['lang' => $lang]) }}"
                       class="inline-flex items-center gap-2 px-5 py-3 bg-ink text-ivory border border-ink
                              font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                              hover:bg-amber hover:text-ink hover:border-amber transition-colors">
                        <x-heroicon-s-magnifying-glass class="w-4 h-4" />
                        {{ __('Search OEM') }}
                    </a>
                    <a href="{{ route('frontend.manufacturer.index', ['lang' => $lang]) }}"
                       class="inline-flex items-center gap-2 px-5 py-3 border border-ink text-ink bg-paper
                              font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                              hover:bg-ink hover:text-ivory transition-colors">
                        <x-heroicon-s-arrow-long-left class="w-4 h-4" />
                        {{ __('All brands') }}
                    </a>
                </div>
            </div>
        </section>

        {{-- ═══ 01 · Compatible car models ═══ --}}
        @if($modelCount > 0)
            <section class="mb-14 bp-rise bp-rise-delay-2">
                <div class="flex items-end justify-between pb-3 border-b border-ink mb-5">
                    <span class="bp-spec text-ink">01 · {{ __('Models · Covered') }}</span>
                    <span class="font-mono text-[10px] text-ink-muted tracking-[0.18em] uppercase">
                        {{ $modelCount }} · {{ __('platforms') }}
                    </span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2">
                    @foreach($carModels as $model)
                        <div class="group flex flex-col gap-1 px-3 py-2.5 border border-rule-strong bg-paper
                                    hover:bg-ink transition-colors">
                            <span class="font-mono text-[9px] tracking-[0.22em] uppercase text-ink-muted group-hover:text-amber">
                                {{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}
                            </span>
                            <p class="font-display text-sm font-bold tracking-[-0.01em] text-ink group-hover:text-ivory truncate">
                                {{ $model->name }}
                            </p>
                            @if($model->year_from || $model->year_to)
                                <p class="font-mono text-[10px] text-ink-muted group-hover:text-ivory/70 tabular-nums">
                                    {{ $model->year_from ?? '—' }}–{{ $model->year_to ?? 'now' }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- ═══ 02 · Parts ledger ═══ --}}
        <section class="mb-10 bp-rise bp-rise-delay-3">
            <div class="flex flex-wrap items-end justify-between gap-3 pb-3 border-b border-ink mb-6">
                <div class="flex items-center gap-3">
                    <span class="bp-spec text-ink">02 · {{ __('Parts · Ledger') }}</span>
                </div>
                <div class="font-mono text-[10px] text-ink-muted tracking-[0.18em] uppercase">
                    {{ __('Page') }} {{ $products->currentPage() }}/{{ max(1,$products->lastPage()) }}
                    <span class="mx-2 text-rule-strong">│</span>
                    {{ number_format($totalParts) }} {{ __('entries') }}
                </div>
            </div>

            @if($products->isEmpty())
                <div class="border border-ink bg-paper p-12 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 border border-ink bg-ivory-alt mb-5">
                        <x-heroicon-o-cube class="w-7 h-7 text-ink-muted" />
                    </div>
                    <p class="font-display text-xl font-bold text-ink leading-tight">{{ __('No parts indexed yet') }}</p>
                    <p class="mt-2 text-sm text-ink-muted max-w-md mx-auto">
                        {{ __("We haven't catalogued any parts for this brand yet. Try the OEM search — the catalogue expands daily.") }}
                    </p>
                </div>
            @else
                {{-- Desktop: table / Mobile: cards.
                     Genuinely tabular data (#, OEM, name, condition, price, action) —
                     ARIA table roles layered onto the CSS grid/card markup so
                     screen readers get real column/row structure without
                     disturbing the existing responsive layout. --}}
                <div class="border border-ink bg-paper overflow-hidden" role="table" aria-label="{{ __('Parts · Ledger') }}">
                    {{-- Table header --}}
                    <div class="hidden md:grid grid-cols-[5rem_1fr_1fr_8rem_6rem_7rem] items-center gap-4 px-5 py-3 bg-ink text-ivory" role="rowgroup">
                        <div class="contents" role="row">
                            <span class="font-mono text-[9px] font-bold tracking-[0.22em] uppercase text-ivory/70" role="columnheader">#</span>
                            <span class="font-mono text-[9px] font-bold tracking-[0.22em] uppercase text-ivory/70" role="columnheader">{{ __('OEM number') }}</span>
                            <span class="font-mono text-[9px] font-bold tracking-[0.22em] uppercase text-ivory/70" role="columnheader">{{ __('Name') }}</span>
                            <span class="font-mono text-[9px] font-bold tracking-[0.22em] uppercase text-ivory/70" role="columnheader">{{ __('Condition') }}</span>
                            <span class="font-mono text-[9px] font-bold tracking-[0.22em] uppercase text-ivory/70 text-right" role="columnheader">{{ __('Price') }}</span>
                            <span class="font-mono text-[9px] font-bold tracking-[0.22em] uppercase text-ivory/70 text-right" role="columnheader">{{ __('Action') }}</span>
                        </div>
                    </div>

                    <ul class="divide-y divide-rule" role="rowgroup">
                        @foreach($products as $product)
                            <li class="group grid grid-cols-1 md:grid-cols-[5rem_1fr_1fr_8rem_6rem_7rem] items-center gap-2 md:gap-4 px-5 py-4 hover:bg-ivory-alt transition-colors" role="row">
                                {{-- # index --}}
                                <span class="font-mono text-[10px] font-bold text-ink-muted tabular-nums tracking-[0.18em] uppercase" role="cell">
                                    #{{ str_pad(($products->currentPage() - 1) * $products->perPage() + $loop->iteration, 3, '0', STR_PAD_LEFT) }}
                                </span>
                                {{-- OEM --}}
                                <div class="min-w-0" x-data="clipboard()" role="cell">
                                    <button type="button" class="appearance-none bg-transparent border-0 p-0 m-0 font-mono text-sm font-bold text-ink truncate cursor-pointer focus:outline-none focus:ring-2 focus:ring-inset focus:ring-amber-ink rounded-sm"
                                       @click="copy('{{ $product->oem_number }}')"
                                       title="Copy OEM number">
                                        {{ $product->oem_number }}
                                    </button>
                                    <p class="md:hidden mt-0.5 text-xs text-ink-muted truncate">
                                        {{ trans_field($product->name) }}
                                    </p>
                                    <span x-show="copied" x-cloak x-transition role="status" aria-live="polite"
                                          class="text-[10px] font-mono font-bold text-emerald-600">Copied</span>
                                </div>
                                {{-- Name (desktop) --}}
                                <p class="hidden md:block text-sm text-body truncate" role="cell">
                                    {{ trans_field($product->name) ?: '—' }}
                                </p>
                                {{-- Condition --}}
                                <div class="hidden md:flex items-center gap-2" role="cell">
                                    @php
                                        $cCond = $product->condition;
                                        $condBg = $cCond?->bg_color ?? '#DCFCE7';
                                        $condText = $cCond?->text_color ?? '#16A34A';
                                        $condLabel = $cCond?->name ?? '—';
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 bp-spec-mono font-bold rounded-sm"
                                          style="background-color: {{ $condBg }}; color: {{ $condText }};">
                                        {{ $condLabel }}
                                    </span>
                                </div>
                                {{-- Price --}}
                                <div class="md:text-right" role="cell">
                                    @if($product->price)
                                        <p class="font-mono text-sm font-bold text-ink tabular-nums">
                                            {{ format_price($product->price) }}
                                        </p>
                                    @else
                                        <p class="font-mono text-xs text-ink-muted tabular-nums">
                                            {{ __('On request') }}
                                        </p>
                                    @endif
                                </div>
                                {{-- CTA --}}
                                <div class="md:text-right" role="cell">
                                    <a href="{{ url('/'.$lang.'/parts/'.urlencode($product->oem_number)) }}"
                                       class="inline-flex items-center justify-center gap-1.5 px-3 py-2
                                              border border-ink bg-paper
                                              font-mono text-[10px] font-bold tracking-[0.2em] uppercase text-ink
                                              group-hover:bg-ink group-hover:text-amber
                                              transition-colors">
                                        {{ __('View') }}
                                        <x-heroicon-s-arrow-long-right class="w-3 h-3" />
                                    </a>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- Pagination --}}
                @if($products->hasPages())
                    <div class="mt-6">
                        {{ $products->links() }}
                    </div>
                @endif
            @endif
        </section>

    </div>
</div>

@endsection
