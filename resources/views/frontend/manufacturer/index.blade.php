@extends('layouts.app')

@php
    $lang     = app()->getLocale();
    $siteName = settings('general.site_name', 'OeParts');
@endphp

{{-- ── SEO ──────────────────────────────────────────────────────────────── --}}
@section('title'){{ __('Browse Brands') }} · {{ $siteName }}@endsection
@section('meta_description'){{ __('Browse all verified OEM manufacturers in the OeParts catalogue — from Alfa Romeo to Volvo, sourced directly from genuine European parts suppliers.') }}@endsection
@section('og_title'){{ __('Browse Brands') }} · {{ $siteName }}@endsection
@section('og_description'){{ __('Every verified OEM manufacturer we carry, indexed and ready for cross-reference search.') }}@endsection
@section('canonical')
    <link rel="canonical" href="{{ route('frontend.manufacturer.index', ['lang' => $lang]) }}">
@endsection
@section('hreflang')
    @foreach(['en', 'de', 'lt', 'fr', 'es'] as $hLang)
        <link rel="alternate" hreflang="{{ $hLang }}" href="{{ route('frontend.manufacturer.index', ['lang' => $hLang]) }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ route('frontend.manufacturer.index', ['lang' => 'en']) }}">
@endsection

@section('json_ld')
<script type="application/ld+json">
{!! json_encode([
    '@@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => __('Home'),   'item' => url('/'.$lang.'/')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => __('Brands'), 'item' => route('frontend.manufacturer.index', ['lang' => $lang])],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@php
    $manufacturerIndexItems = [];
    foreach ($manufacturers->items() as $index => $m) {
        $manufacturerIndexItems[] = [
            '@type' => 'ListItem',
            'position' => $index + 1,
            'item' => [
                '@type' => 'Brand',
                'name' => trans_field($m->name) ?: $m->slug,
                'url' => route('frontend.manufacturer.show', ['lang' => $lang, 'manufacturer' => $m->slug]),
            ],
        ];
    }
@endphp
@if(!empty($manufacturerIndexItems))
<script type="application/ld+json">
{!! json_encode([
    '@@context' => 'https://schema.org',
    '@type' => 'ItemList',
    'numberOfItems' => $manufacturers->total(),
    'itemListElement' => $manufacturerIndexItems,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endif
@endsection

{{-- ══════════════════════════════════════════════════════════════════════
     INDUSTRIAL BLUEPRINT — BRANDS INDEX
     Alphabetical directory of every verified OEM manufacturer.
     ══════════════════════════════════════════════════════════════════ --}}
@section('content')

@php
    // Group current page into A-Z buckets for the ledger layout
    $grouped = $manufacturers->groupBy(fn($m) => strtoupper(mb_substr(trans_field($m->name) ?: $m->slug, 0, 1)))
        ->sortKeys();
    $allLetters = collect(range('A', 'Z'));
    $verifiedCount = $manufacturers->where('is_verified_oem', true)->count();
@endphp

<div class="relative bg-ivory text-ink min-h-screen">

    {{-- Blueprint grid background --}}
    <div class="fixed inset-0 bg-grid-ivory-fine bg-grid-sm opacity-70 pointer-events-none" aria-hidden="true"></div>

    <div class="relative max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-10 pt-10 pb-24">

        {{-- ═══ Document header ═══ --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-5 border-b border-rule mb-10 bp-rise">
            <nav class="flex items-center gap-3 font-mono text-[11px] uppercase tracking-[0.16em] text-ink-muted" aria-label="Breadcrumb">
                <a href="{{ url('/'.$lang.'/') }}" class="hover:text-ink transition-colors">{{ __('Home') }}</a>
                <span class="text-rule-strong">/</span>
                <span class="text-ink">{{ __('Brands') }}</span>
            </nav>
            <div class="font-mono text-[10px] tracking-[0.2em] uppercase text-ink-muted">
                DOC · BRANDS · INDEX-01
            </div>
        </div>

        {{-- ═══ 12-col hero: headline + stats ═══ --}}
        <div class="grid grid-cols-12 gap-x-4 sm:gap-x-6 lg:gap-x-8 gap-y-8 mb-12 bp-rise bp-rise-delay-1">
            <header class="col-span-12 lg:col-span-8">
                <div class="flex items-center gap-4 mb-8">
                    <span class="w-10 h-[3px] bg-amber inline-block"></span>
                    <span class="bp-spec text-amber-ink">Directory · Manufacturers</span>
                </div>

                <h1 class="font-display font-extrabold text-ink leading-[0.95] tracking-[-0.03em]
                           text-4xl sm:text-5xl lg:text-6xl max-w-[22ch]">
                    {{ __('Every verified brand') }}<span class="text-amber">.</span>
                </h1>

                <div class="mt-6 mb-8">
                    <div class="bp-rule-draw h-px bg-ink/70 origin-left"></div>
                </div>

                <p class="max-w-xl text-lg text-body leading-relaxed">
                    {{ __('Alphabetical index of every manufacturer we catalogue. Click a brand to open its cross-referenced parts catalogue.') }}
                </p>
            </header>

            {{-- Stats panel --}}
            <aside class="col-span-12 lg:col-span-4">
                <div class="relative border border-ink bg-paper bp-register">
                    <div class="px-5 py-3 bg-ink text-ivory flex items-center justify-between">
                        <span class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase">{{ __('DIRECTORY · STATS') }}</span>
                        <span class="font-mono text-[10px] tracking-[0.18em] uppercase text-ivory/60">{{ now()->format('Y.m.d') }}</span>
                    </div>
                    <div class="p-5 space-y-3">
                        <div class="bp-leader pt-0.5">
                            <dt class="text-sm text-ink-muted">{{ __('Listed') }}</dt>
                            <span class="bp-leader-dots"></span>
                            <dd class="font-mono text-sm font-bold text-ink tabular-nums">{{ number_format($manufacturers->total()) }}</dd>
                        </div>
                        <div class="bp-leader">
                            <dt class="text-sm text-ink-muted">{{ __('Verified OEM') }}</dt>
                            <span class="bp-leader-dots"></span>
                            <dd class="font-mono text-sm font-bold text-amber-ink tabular-nums">{{ $verifiedCount }}</dd>
                        </div>
                        <div class="bp-leader">
                            <dt class="text-sm text-ink-muted">{{ __('Coverage') }}</dt>
                            <span class="bp-leader-dots"></span>
                            <dd class="font-mono text-sm font-bold text-ink uppercase tracking-wide">EU · 27</dd>
                        </div>
                        <div class="bp-leader">
                            <dt class="text-sm text-ink-muted">{{ __('Page') }}</dt>
                            <span class="bp-leader-dots"></span>
                            <dd class="font-mono text-sm font-bold text-ink tabular-nums">{{ $manufacturers->currentPage() }}/{{ $manufacturers->lastPage() }}</dd>
                        </div>
                    </div>
                </div>
            </aside>
        </div>

        {{-- ═══ 01 · Alphabet jump ═══ --}}
        <section class="mb-12 bp-rise bp-rise-delay-2">
            <div class="flex items-end justify-between pb-3 border-b border-ink mb-5">
                <span class="bp-spec text-ink">01 · Jump · By letter</span>
                <span class="hidden sm:inline font-mono text-[10px] text-ink-muted tracking-[0.18em] uppercase">
                    A–Z · this page
                </span>
            </div>
            <div class="grid grid-cols-7 sm:grid-cols-[repeat(13,minmax(0,1fr))] lg:grid-cols-[repeat(26,minmax(0,1fr))] gap-[2px]">
                @foreach($allLetters as $letter)
                    @php $hasLetter = isset($grouped[$letter]); @endphp
                    @if($hasLetter)
                        <a href="#letter-{{ $letter }}"
                           class="flex items-center justify-center h-10 border border-ink bg-paper
                                  font-mono text-[12px] font-bold text-ink
                                  hover:bg-ink hover:text-amber hover:border-ink transition-colors">
                            {{ $letter }}
                        </a>
                    @else
                        <span class="flex items-center justify-center h-10 border border-rule bg-ivory-alt
                                     font-mono text-[12px] font-bold text-ink-muted/40 cursor-not-allowed">
                            {{ $letter }}
                        </span>
                    @endif
                @endforeach
            </div>
        </section>

        {{-- ═══ 02 · A-Z ledger ═══ --}}
        <section class="mb-10 bp-rise bp-rise-delay-3">
            <div class="flex items-end justify-between pb-3 border-b border-ink mb-6">
                <span class="bp-spec text-ink">02 · Ledger · Alphabetical</span>
                <span class="hidden md:inline font-mono text-[10px] text-ink-muted tracking-[0.18em] uppercase">
                    {{ $manufacturers->count() }} {{ __('on page') }}
                </span>
            </div>

            @if($grouped->isEmpty())
                {{-- Empty --}}
                <div class="border border-ink bg-paper p-12 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 border border-ink bg-ivory-alt mb-5">
                        <x-heroicon-o-archive-box-x-mark class="w-7 h-7 text-ink-muted" />
                    </div>
                    <p class="font-display text-xl font-bold text-ink leading-tight">{{ __('No brands yet') }}</p>
                    <p class="mt-2 text-sm text-ink-muted max-w-sm mx-auto">
                        {{ __('The manufacturer directory is still being populated. Check back soon or use the OEM search directly.') }}
                    </p>
                    <a href="{{ route('frontend.search.console', ['lang' => $lang]) }}"
                       class="mt-6 inline-flex items-center gap-2 px-5 py-2.5 bg-ink text-ivory
                              font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                              hover:bg-amber hover:text-ink transition-colors">
                        <x-heroicon-s-magnifying-glass class="w-4 h-4" />
                        {{ __('Search by OEM') }}
                    </a>
                </div>
            @else
                <div class="space-y-10">
                    @foreach($grouped as $letter => $brands)
                        <div id="letter-{{ $letter }}" class="scroll-mt-28">
                            {{-- Letter header --}}
                            <div class="flex items-end gap-4 pb-2.5 border-b border-ink mb-4">
                                <span class="font-display text-5xl font-extrabold leading-none text-ink tracking-[-0.03em]">
                                    {{ $letter }}
                                </span>
                                <span class="bp-spec-mono pb-2">
                                    {{ str_pad(array_search($letter, array_keys($grouped->toArray())) + 1, 2, '0', STR_PAD_LEFT) }} · {{ $brands->count() }} {{ Str::plural(__('brand'), $brands->count()) }}
                                </span>
                            </div>

                            <ul class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
                                @foreach($brands as $brand)
                                    <li>
                                        <a href="{{ route('frontend.manufacturer.show', ['lang' => $lang, 'manufacturer' => $brand->slug]) }}"
                                           class="group relative flex items-center justify-between gap-4 px-5 py-5 border border-ink bg-paper
                                                  hover:bg-ink hover:border-ink transition-colors">
                                            <div class="flex items-center gap-4 min-w-0">
                                                <span class="flex items-center justify-center w-14 h-14 shrink-0 border border-rule-strong bg-ivory-alt
                                                             group-hover:border-amber transition-colors">
                                                    @if($brand->logo && $brand->logo->file_url)
                                                        <img src="{{ $brand->logo->file_url }}"
                                                             alt="{{ trans_field($brand->name) }}"
                                                             class="max-w-[82%] max-h-[82%] object-contain" />
                                                    @else
                                                        <span class="font-mono text-base font-bold text-ink group-hover:text-amber">
                                                            {{ strtoupper(mb_substr(trans_field($brand->name) ?: $brand->slug, 0, 1)) }}
                                                        </span>
                                                    @endif
                                                </span>
                                                <div class="min-w-0">
                                                    <p class="font-display text-base font-bold tracking-[-0.01em] text-ink group-hover:text-ivory truncate">
                                                        {{ trans_field($brand->name) }}
                                                    </p>
                                                    @if($brand->is_verified_oem)
                                                        <p class="font-mono text-[9px] tracking-[0.22em] uppercase text-amber-ink group-hover:text-amber mt-1">
                                                            ✓ Verified OEM
                                                        </p>
                                                    @endif
                                                </div>
                                            </div>
                                            <x-heroicon-s-arrow-long-right class="w-4 h-4 text-ink-muted group-hover:text-amber shrink-0 transition-colors" />
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- ═══ Pagination ═══ --}}
        @if($manufacturers->hasPages())
            <div class="pt-6 border-t border-ink bp-rise bp-rise-delay-4">
                {{ $manufacturers->links() }}
            </div>
        @endif

        {{-- ═══ 03 · Can't find brand CTA ═══ --}}
        <section class="mt-16 bp-rise bp-rise-delay-5">
            <div class="flex items-end justify-between pb-3 border-b border-ink">
                <span class="bp-spec text-ink">03 · Fallback · Search</span>
            </div>
            <div class="grid grid-cols-12 border-x border-b border-ink bg-paper">
                <div class="col-span-12 md:col-span-8 p-6 sm:p-10 md:border-r md:border-rule">
                    <p class="font-display text-2xl font-extrabold text-ink tracking-[-0.02em] leading-tight">
                        {{ __("Don't know the brand?") }}<span class="text-amber">.</span>
                    </p>
                    <p class="mt-3 text-base text-body leading-relaxed max-w-xl">
                        {{ __('Skip the directory and query by OEM number directly — our cross-reference engine will identify the manufacturer and compatible alternatives automatically.') }}
                    </p>
                    <a href="{{ route('frontend.search.console', ['lang' => $lang]) }}"
                       class="mt-6 group inline-flex items-center justify-center gap-3
                              px-6 py-3.5
                              bg-ink text-ivory font-sans text-[12px] font-bold uppercase tracking-[0.22em]
                              border border-ink
                              hover:bg-amber hover:text-ink
                              transition-colors duration-150">
                        <x-heroicon-o-magnifying-glass class="w-4 h-4" aria-hidden="true" />
                        {{ __('Open search console') }}
                        <x-heroicon-s-arrow-long-right class="w-4 h-4 transform transition-transform group-hover:translate-x-1" aria-hidden="true" />
                    </a>
                </div>
                <div class="col-span-12 md:col-span-4 p-6 sm:p-8 bg-ivory-alt">
                    <p class="bp-spec text-amber-ink mb-4">{{ __('Quick tips') }}</p>
                    <ul class="space-y-3 text-sm text-body">
                        <li class="flex items-start gap-2.5">
                            <span class="font-mono text-[10px] tabular-nums tracking-[0.18em] text-ink-muted mt-1">01</span>
                            <span>{{ __('Enter any OEM number, case-insensitive.') }}</span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <span class="font-mono text-[10px] tabular-nums tracking-[0.18em] text-ink-muted mt-1">02</span>
                            <span>{{ __('We auto-detect the manufacturer.') }}</span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <span class="font-mono text-[10px] tabular-nums tracking-[0.18em] text-ink-muted mt-1">03</span>
                            <span>{{ __('Cross-references shown inline.') }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </section>

    </div>
</div>

@endsection
