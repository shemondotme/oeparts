@extends('layouts.app')

@php
    $lang         = app()->getLocale();
    $siteName     = settings('general.site_name', 'OeParts');
    $brandName    = trans_field($manufacturer->name) ?: $manufacturer->slug;
    $totalModels  = $carModels->total();
@endphp

{{-- ── SEO ──────────────────────────────────────────────────────────────── --}}
@section('title'){{ __('car_model.meta_title_index', ['brand' => $brandName]) }} · {{ $siteName }}@endsection
@section('meta_description'){{ __('car_model.meta_description_index', ['brand' => $brandName]) }}@endsection
@section('og_title'){{ __('car_model.meta_title_index', ['brand' => $brandName]) }} · {{ $siteName }}@endsection
@section('og_description'){{ __('car_model.og_description_index', ['count' => $totalModels, 'brand' => $brandName]) }}@endsection

@section('canonical')
    <link rel="canonical" href="{{ route('frontend.car-model.index', ['lang' => $lang, 'manufacturer' => $manufacturer->slug]) }}">
@endsection

@section('hreflang')
    @foreach(['en', 'de', 'lt', 'fr', 'es'] as $hLang)
        <link rel="alternate" hreflang="{{ $hLang }}" href="{{ route('frontend.car-model.index', ['lang' => $hLang, 'manufacturer' => $manufacturer->slug]) }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ route('frontend.car-model.index', ['lang' => 'en', 'manufacturer' => $manufacturer->slug]) }}">
@endsection

@section('json_ld')
<script type="application/ld+json">
{!! json_encode([
    '@@context'        => 'https://schema.org',
    '@type'           => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => __('car_model.breadcrumb_home'),   'item' => url('/'.$lang.'/')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => __('car_model.breadcrumb_brands'), 'item' => route('frontend.manufacturer.index', ['lang' => $lang])],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $brandName,   'item' => route('frontend.manufacturer.show', ['lang' => $lang, 'manufacturer' => $manufacturer->slug])],
        ['@type' => 'ListItem', 'position' => 4, 'name' => __('car_model.breadcrumb_models'),  'item' => route('frontend.car-model.index', ['lang' => $lang, 'manufacturer' => $manufacturer->slug])],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endsection

{{-- ══════════════════════════════════════════════════════════════════════
     INDUSTRIAL BLUEPRINT — MODELS INDEX (car-model/index.blade.php)
     Alphabetical directory of platforms for a specific manufacturer.
     ══════════════════════════════════════════════════════════════════ --}}
@section('content')

<div class="relative bg-ivory text-ink min-h-screen">

    {{-- Blueprint grid background --}}
    <div class="fixed inset-0 bg-grid-ivory-fine bg-grid-sm opacity-70 pointer-events-none" aria-hidden="true"></div>

    <div class="relative max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-10 pt-10 pb-24">

        {{-- ═══ Document header ═══ --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-5 border-b border-rule mb-10 bp-rise">
            <nav class="flex items-center gap-3 font-mono text-[11px] uppercase tracking-[0.16em] text-ink-muted" aria-label="Breadcrumb">
                <a href="{{ url('/'.$lang.'/') }}" class="hover:text-ink transition-colors">{{ __('car_model.breadcrumb_home') }}</a>
                <span class="text-rule-strong">/</span>
                <a href="{{ route('frontend.manufacturer.index', ['lang' => $lang]) }}" class="hover:text-ink transition-colors">{{ __('car_model.breadcrumb_brands') }}</a>
                <span class="text-rule-strong">/</span>
                <a href="{{ route('frontend.manufacturer.show', ['lang' => $lang, 'manufacturer' => $manufacturer->slug]) }}" class="hover:text-ink transition-colors">{{ $brandName }}</a>
                <span class="text-rule-strong">/</span>
                <span class="text-ink">{{ __('car_model.breadcrumb_models') }}</span>
            </nav>
            <div class="font-mono text-[10px] tracking-[0.2em] uppercase text-ink-muted">
                {{ __('car_model.doc_models_index') }} · {{ strtoupper($manufacturer->slug) }}
            </div>
        </div>

        {{-- ═══ 12-col hero: headline + stats ═══ --}}
        <div class="grid grid-cols-12 gap-x-4 sm:gap-x-6 lg:gap-x-8 gap-y-8 mb-12 bp-rise bp-rise-delay-1">
            <header class="col-span-12 lg:col-span-8">
                <div class="flex items-center gap-4 mb-8">
                    <span class="w-10 h-[3px] bg-amber inline-block"></span>
                    <span class="bp-spec text-amber-ink">{{ __('car_model.eyebrow_directory_platforms') }}</span>
                </div>

                <h1 class="font-display font-extrabold text-ink leading-[0.95] tracking-[-0.03em]
                           text-4xl sm:text-5xl lg:text-6xl max-w-[22ch]">
                    {{ __('car_model.headline_brand_platforms', ['brand' => $brandName]) }}<span class="text-amber">.</span>
                </h1>

                <div class="mt-6 mb-8">
                    <div class="bp-rule-draw h-px bg-ink/70 origin-left"></div>
                </div>

                <p class="max-w-xl text-lg text-body leading-relaxed">
                    {{ __('car_model.subhead_select_model') }}
                </p>
            </header>

            {{-- Stats panel --}}
            <aside class="col-span-12 lg:col-span-4">
                <div class="relative border border-ink bg-paper bp-register">
                    <div class="px-5 py-3 bg-ink text-ivory flex items-center justify-between">
                        <span class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase">{{ __('car_model.stats_models_stats') }}</span>
                        <span class="font-mono text-[10px] tracking-[0.18em] uppercase text-ivory/60">{{ now()->format('Y.m.d') }}</span>
                    </div>
                    <dl class="p-5 space-y-3">
                        <div class="bp-leader pt-0.5">
                            <dt class="text-sm text-ink-muted">{{ __('car_model.stats_manufacturer') }}</dt>
                            <dd class="font-mono text-sm font-bold text-ink uppercase tracking-wide">{{ $brandName }}</dd>
                        </div>
                        <div class="bp-leader">
                            <dt class="text-sm text-ink-muted">{{ __('car_model.stats_chassis_lines') }}</dt>
                            <dd class="font-mono text-sm font-bold text-ink tabular-nums">{{ number_format($totalModels) }}</dd>
                        </div>
                        <div class="bp-leader">
                            <dt class="text-sm text-ink-muted">{{ __('car_model.stats_page') }}</dt>
                            <dd class="font-mono text-sm font-bold text-ink tabular-nums">{{ $carModels->currentPage() }}/{{ $carModels->lastPage() }}</dd>
                        </div>
                    </dl>
                </div>
            </aside>
        </div>

        {{-- ═══ 01 · Models List ═══ --}}
        <section class="mb-10 bp-rise bp-rise-delay-2">
            <div class="flex items-end justify-between pb-3 border-b border-ink mb-6">
                <span class="bp-spec text-ink">{{ __('car_model.covered_chassis_ledger') }}</span>
                <span class="hidden md:inline font-mono text-[10px] text-ink-muted tracking-[0.18em] uppercase">
                    {{ $carModels->count() }} {{ __('car_model.displayed_on_page') }}
                </span>
            </div>

            @if($carModels->isEmpty())
                {{-- Empty --}}
                <div class="border border-ink bg-paper p-12 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 border border-ink bg-ivory-alt mb-5">
                        <x-heroicon-o-archive-box-x-mark class="w-7 h-7 text-ink-muted" />
                    </div>
                    <p class="font-display text-xl font-bold text-ink leading-tight">{{ __('car_model.no_models_listed_yet') }}</p>
                    <p class="mt-2 text-sm text-ink-muted max-w-sm mx-auto">
                        {{ __('car_model.no_models_registered_body') }}
                    </p>
                    <a href="{{ route('frontend.search.console', ['lang' => $lang]) }}"
                       class="mt-6 inline-flex items-center gap-2 px-5 py-2.5 bg-ink text-ivory
                              font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                              hover:bg-amber hover:text-ink transition-colors">
                        <x-heroicon-s-magnifying-glass class="w-4 h-4" />
                        {{ __('manufacturer.search_by_oem') }}
                    </a>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    @foreach($carModels as $index => $model)
                        @php
                            $sequenceNum = str_pad(($carModels->currentPage() - 1) * $carModels->perPage() + $loop->iteration, 2, '0', STR_PAD_LEFT);
                        @endphp
                        <a href="{{ route('frontend.car-model.show', ['lang' => $lang, 'manufacturer' => $manufacturer->slug, 'model' => $model->slug]) }}"
                           class="group relative flex flex-col justify-between p-5 border border-ink bg-paper hover:bg-ink hover:border-ink transition-colors">
                            <div>
                                <span class="font-mono text-[9px] tracking-[0.22em] uppercase text-ink-muted group-hover:text-amber">
                                    {{ $sequenceNum }} · {{ __('car_model.platform_word') }}
                                </span>
                                <h3 class="font-display text-lg font-bold tracking-[-0.01em] text-ink group-hover:text-ivory mt-2 truncate">
                                    {{ $model->name }}
                                </h3>
                                @if($model->year_from || $model->year_to)
                                    <p class="font-mono text-xs text-ink-muted group-hover:text-ivory/70 mt-1 tabular-nums">
                                        {{ $model->year_from ?? '—' }} – {{ $model->year_to ?? __('car_model.now_word') }}
                                    </p>
                                @endif
                            </div>
                            <div class="mt-6 flex items-center justify-between border-t border-rule group-hover:border-white/15 pt-3">
                                <span class="font-mono text-[9px] tracking-[0.16em] uppercase text-ink-muted group-hover:text-amber">
                                    {{ __('car_model.browse_parts_btn') }}
                                </span>
                                <x-heroicon-s-arrow-long-right class="w-4 h-4 text-ink-muted group-hover:text-amber transition-colors" />
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- ═══ Pagination ═══ --}}
        @if($carModels->hasPages())
            <div class="pt-6 border-t border-ink bp-rise bp-rise-delay-3">
                {{ $carModels->links('components.ui.pagination') }}
            </div>
        @endif

    </div>
</div>

@endsection
