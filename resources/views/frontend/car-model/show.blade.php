@extends('layouts.app')

@php
    $lang        = app()->getLocale();
    $siteName    = settings('general.site_name', 'OeParts');
    $brandName   = trans_field($manufacturer->name) ?: $manufacturer->slug;
    $modelName   = $carModel->name;
    $totalParts  = $products->total();
    $otherCount  = $otherModels->count();
@endphp

{{-- ── SEO ──────────────────────────────────────────────────────────────── --}}
@section('title'){{ $modelName }} · {{ $brandName }} · {{ __('Compatible Parts') }} · {{ $siteName }}@endsection
@section('meta_description'){{ __('Find compatible parts for :brand :model (:years). Verify fitment, cross-references, and stock with direct shipping in the EU.', ['brand' => $brandName, 'model' => $modelName, 'years' => ($carModel->year_from ?? '—') . '–' . ($carModel->year_to ?? __('now'))]) }}@endsection

@section('canonical')
    <link rel="canonical" href="{{ route('frontend.car-model.show', ['lang' => $lang, 'manufacturer' => $manufacturer->slug, 'model' => $carModel->slug]) }}">
@endsection

@section('hreflang')
    @foreach(['en', 'de', 'lt', 'fr', 'es'] as $hLang)
        <link rel="alternate" hreflang="{{ $hLang }}" href="{{ route('frontend.car-model.show', ['lang' => $hLang, 'manufacturer' => $manufacturer->slug, 'model' => $carModel->slug]) }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ route('frontend.car-model.show', ['lang' => 'en', 'manufacturer' => $manufacturer->slug, 'model' => $carModel->slug]) }}">
@endsection

@section('json_ld')
<script type="application/ld+json">
{!! json_encode([
    '@@context'        => 'https://schema.org',
    '@type'           => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => __('Home'),   'item' => url('/'.$lang.'/')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => __('Brands'), 'item' => route('frontend.manufacturer.index', ['lang' => $lang])],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $brandName,   'item' => route('frontend.manufacturer.show', ['lang' => $lang, 'manufacturer' => $manufacturer->slug])],
        ['@type' => 'ListItem', 'position' => 4, 'name' => __('Models'),  'item' => route('frontend.car-model.index', ['lang' => $lang, 'manufacturer' => $manufacturer->slug])],
        ['@type' => 'ListItem', 'position' => 5, 'name' => $modelName,   'item' => route('frontend.car-model.show', ['lang' => $lang, 'manufacturer' => $manufacturer->slug, 'model' => $carModel->slug])],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endsection

{{-- ══════════════════════════════════════════════════════════════════════
     INDUSTRIAL BLUEPRINT — MODEL DETAIL (car-model/show.blade.php)
     Model information header + compatible parts ledger + alternates.
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
                <a href="{{ route('frontend.manufacturer.show', ['lang' => $lang, 'manufacturer' => $manufacturer->slug]) }}" class="hover:text-ink transition-colors">{{ $brandName }}</a>
                <span class="text-rule-strong">/</span>
                <a href="{{ route('frontend.car-model.index', ['lang' => $lang, 'manufacturer' => $manufacturer->slug]) }}" class="hover:text-ink transition-colors">{{ __('Models') }}</a>
                <span class="text-rule-strong">/</span>
                <span class="text-ink truncate max-w-[14rem]">{{ $modelName }}</span>
            </nav>
            <div class="font-mono text-[10px] tracking-[0.2em] uppercase text-ink-muted">
                DOC · PLATFORM · {{ strtoupper($manufacturer->slug) }} · {{ strtoupper(str_replace('-', '·', $carModel->slug)) }}
            </div>
        </div>

        {{-- ═══ 12-col grid: identity + spec panel ═══ --}}
        <section class="grid grid-cols-12 gap-x-4 sm:gap-x-6 lg:gap-x-8 gap-y-8 mb-14 bp-rise bp-rise-delay-1">
            <div class="col-span-12 md:col-span-8">
                <div class="flex items-center gap-4 mb-6">
                    <span class="w-10 h-[3px] bg-amber inline-block"></span>
                    <span class="bp-spec text-amber-ink">{{ __('Chassis Platform · Covered Specification') }}</span>
                </div>

                <h1 class="font-display font-extrabold text-ink leading-[0.95] tracking-[-0.03em]
                           text-4xl sm:text-5xl lg:text-6xl break-words">
                    {{ $modelName }}<span class="text-amber-ink">.</span>
                </h1>

                <div class="mt-5 mb-6">
                    <div class="bp-rule-draw h-px bg-ink/70 origin-left"></div>
                </div>

                <p class="max-w-2xl text-base text-body leading-relaxed mb-6">
                    {{ __('Every certified part catalogued specifically for the :brand :model platform. Filter results by part condition or brand name to confirm compatibility.', ['brand' => $brandName, 'model' => $modelName]) }}
                </p>

                {{-- CTAs --}}
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('frontend.search.console', ['lang' => $lang]) }}"
                       class="inline-flex items-center gap-2 px-5 py-3 bg-ink text-ivory border border-ink
                              font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                              hover:bg-amber hover:text-ink hover:border-amber transition-colors">
                        <x-heroicon-s-magnifying-glass class="w-4 h-4" />
                        {{ __('Search OEM Number') }}
                    </a>
                    <a href="{{ route('frontend.car-model.index', ['lang' => $lang, 'manufacturer' => $manufacturer->slug]) }}"
                       class="inline-flex items-center gap-2 px-5 py-3 border border-ink text-ink bg-paper
                              font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                              hover:bg-ink hover:text-ivory transition-colors">
                        <x-heroicon-s-arrow-long-left class="w-4 h-4" />
                        {{ __('Other Platforms') }}
                    </a>
                </div>
            </div>

            {{-- Spec panel --}}
            <aside class="col-span-12 md:col-span-4">
                <div class="relative border border-ink bg-paper p-6 bp-register">
                    <p class="bp-spec text-ink-muted mb-4">{{ __('Platform Details') }}</p>
                    <dl class="space-y-3 text-sm">
                        <div class="bp-leader pt-0.5">
                            <dt class="text-ink-muted">{{ __('Brand') }}</dt>
                            <span class="bp-leader-dots"></span>
                            <dd class="font-mono font-bold text-ink uppercase">{{ $brandName }}</dd>
                        </div>
                        <div class="bp-leader">
                            <dt class="text-ink-muted">{{ __('Chassis') }}</dt>
                            <span class="bp-leader-dots"></span>
                            <dd class="font-mono font-bold text-ink truncate">{{ $modelName }}</dd>
                        </div>
                        <div class="bp-leader">
                            <dt class="text-ink-muted">{{ __('Years') }}</dt>
                            <span class="bp-leader-dots"></span>
                            <dd class="font-mono font-bold text-ink tabular-nums">
                                {{ $carModel->year_from ?? '—' }}–{{ $carModel->year_to ?? __('now') }}
                            </dd>
                        </div>
                        <div class="bp-leader">
                            <dt class="text-ink-muted">{{ __('Parts Indexed') }}</dt>
                            <span class="bp-leader-dots"></span>
                            <dd class="font-mono font-bold text-ink tabular-nums">{{ number_format($totalParts) }}</dd>
                        </div>
                        <div class="bp-leader">
                            <dt class="text-ink-muted">{{ __('Supplier Network') }}</dt>
                            <span class="bp-leader-dots"></span>
                            <dd class="font-mono font-bold text-amber-ink">EU VERIFIED</dd>
                        </div>
                    </dl>
                </div>
            </aside>
        </section>

        {{-- ═══ 12-col layout: parts list + other models ═══ --}}
        <div class="grid grid-cols-12 gap-x-4 sm:gap-x-6 lg:gap-x-12 gap-y-10">

            {{-- Left column: parts list ledger (8 cols) --}}
            <section class="col-span-12 lg:col-span-8 bp-rise bp-rise-delay-2">
                <div class="flex flex-wrap items-end justify-between gap-3 pb-3 border-b border-ink mb-6">
                    <span class="bp-spec text-ink">01 · {{ __('Compatible Parts Ledger') }}</span>
                    <div class="font-mono text-[10px] text-ink-muted tracking-[0.18em] uppercase">
                        {{ __('Page') }} {{ $products->currentPage() }}/{{ max(1,$products->lastPage()) }}
                        <span class="mx-2 text-rule-strong">│</span>
                        {{ number_format($totalParts) }} {{ __('compatible SKUs') }}
                    </div>
                </div>

                @if($products->isEmpty())
                    <div class="border border-ink bg-paper p-12 text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 border border-ink bg-ivory-alt mb-5">
                            <x-heroicon-o-cube class="w-7 h-7 text-ink-muted" />
                        </div>
                        <p class="font-display text-xl font-bold text-ink leading-tight">{{ __('No specific parts linked yet') }}</p>
                        <p class="mt-2 text-sm text-ink-muted max-w-md mx-auto">
                            {{ __("No parts have been explicitly mapped to this platform chassis yet. Sourcing is active. Search by OEM number directly — our indexing engine matches references dynamically.") }}
                        </p>
                        <a href="{{ route('frontend.search.console', ['lang' => $lang]) }}"
                           class="mt-6 inline-flex items-center gap-2 px-5 py-2.5 bg-ink text-ivory
                                  font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                                  hover:bg-amber hover:text-ink transition-colors">
                            <x-heroicon-s-magnifying-glass class="w-4 h-4" />
                            {{ __('Open Search Console') }}
                        </a>
                    </div>
                @else
                    {{-- Genuinely tabular data — ARIA table roles layered onto the
                         CSS grid/card markup so screen readers get real column/row
                         structure without disturbing the existing responsive layout. --}}
                    <div class="border border-ink bg-paper overflow-hidden" role="table" aria-label="{{ __('Parts · Ledger') }}">
                        {{-- Table header --}}
                        <div class="hidden md:grid grid-cols-[5rem_1.2fr_1fr_8rem_6rem_7rem] items-center gap-4 px-5 py-3 bg-ink text-ivory" role="rowgroup">
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
                                <li class="group grid grid-cols-1 md:grid-cols-[5rem_1.2fr_1fr_8rem_6rem_7rem] items-center gap-2 md:gap-4 px-5 py-4 hover:bg-ivory-alt transition-colors" role="row">
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
                                        <span x-show="copied" x-cloak x-transition
                                              class="text-[10px] font-mono font-bold text-emerald-600">Copied</span>
                                    </div>
                                    {{-- Name --}}
                                    <p class="hidden md:block text-sm text-body truncate" role="cell">
                                        {{ trans_field($product->name) ?: '—' }}
                                    </p>
                                    {{-- Condition --}}
                                    <div class="hidden md:flex items-center gap-2" role="cell">
                                        @if($product->condition)
                                            <x-ui.condition-badge :condition="$product->condition" />
                                        @else
                                            <span class="inline-flex items-center px-2 h-6 border border-rule-strong font-mono text-[9px] tracking-[0.18em] uppercase text-ink">
                                                —
                                            </span>
                                        @endif
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

            {{-- Right column: other platforms from manufacturer (4 cols) --}}
            <aside class="col-span-12 lg:col-span-4 space-y-6 lg:sticky lg:top-10 lg:h-fit bp-rise bp-rise-delay-3">
                <div class="border border-ink bg-paper">
                    <div class="px-5 py-3 bg-ink text-ivory flex items-center justify-between">
                        <span class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase">{{ __('Brand Models') }}</span>
                        <span class="font-mono text-[9px] tracking-[0.16em] uppercase text-ivory/60">LIST</span>
                    </div>

                    @if($otherModels->isEmpty())
                        <div class="p-5 text-center text-sm text-ink-muted leading-relaxed">
                            {{ __('No other platforms catalogued for this brand.') }}
                        </div>
                    @else
                        <ul class="divide-y divide-rule">
                            @foreach($otherModels as $oModel)
                                <li>
                                    <a href="{{ route('frontend.car-model.show', ['lang' => $lang, 'manufacturer' => $manufacturer->slug, 'model' => $oModel->slug]) }}"
                                       class="group flex items-center justify-between gap-3 px-5 py-3 hover:bg-ivory-alt transition-colors">
                                        <div class="min-w-0">
                                            <p class="font-display text-sm font-bold tracking-[-0.01em] text-ink truncate">
                                                {{ $oModel->name }}
                                            </p>
                                            @if($oModel->year_from || $oModel->year_to)
                                                <p class="font-mono text-[10px] text-ink-muted group-hover:text-ink mt-0.5 tabular-nums">
                                                    {{ $oModel->year_from ?? '—' }}–{{ $oModel->year_to ?? __('now') }}
                                                </p>
                                            @endif
                                        </div>
                                        <x-heroicon-s-arrow-long-right class="w-4 h-4 text-ink-muted group-hover:text-amber-ink transition-colors shrink-0" />
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        <div class="p-4 bg-ivory-alt border-t border-rule text-center">
                            <a href="{{ route('frontend.car-model.index', ['lang' => $lang, 'manufacturer' => $manufacturer->slug]) }}"
                               class="bp-link text-xs tracking-wider">
                                {{ __('View all models') }}
                            </a>
                        </div>
                    @endif
                </div>

                {{-- Technical drawing concierge sidebar widget --}}
                <div class="border border-ink bg-ink text-ivory p-5">
                    <p class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-amber mb-3">{{ __('Sourcing desk') }}</p>
                    <h3 class="font-display text-base font-extrabold tracking-[-0.02em] leading-tight">
                        {{ __('Can\'t find compatible parts?') }}
                    </h3>
                    <p class="mt-2 text-sm text-ivory/70 leading-relaxed">
                        {{ __('If your chassis code is rare or you require heavy components, submit a manual specification request to our sourcing desk.') }}
                    </p>
                    <button type="button"
                            x-on:click="window.dispatchEvent(new CustomEvent('open-inquiry-modal'))"
                            class="mt-4 inline-flex items-center gap-2 px-4 py-2.5 bg-amber text-ink
                                   font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                                   hover:bg-paper transition-colors w-full justify-center">
                        {{ __('Request Sourcing') }}
                        <x-heroicon-s-arrow-long-right class="w-4 h-4" />
                    </button>
                </div>
            </aside>
        </div>

    </div>
</div>

{{-- Sourcing desk inquiry modal --}}
<x-modals.part-inquiry :normalized-query="''" />

@endsection
