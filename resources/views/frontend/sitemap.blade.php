@extends('layouts.app')

@php
    $siteName = settings('general.site_name', 'OeParts');
    $pageTitle = __('sitemap.page_title');

    $languageNames = [
        'en' => 'English',
        'de' => 'Deutsch',
        'lt' => 'Lietuvių',
        'fr' => 'Français',
        'es' => 'Español',
    ];

    $coreLinks = [
        ['href' => url('/'.$lang.'/'),                                  'num' => '01.01', 'label' => __('sitemap.link_home')],
        ['href' => route('frontend.search.console', ['lang' => $lang]), 'num' => '01.02', 'label' => __('sitemap.link_parts')],
        ['href' => url('/'.$lang.'/brands'),                            'num' => '01.03', 'label' => __('sitemap.link_brands')],
        ['href' => url('/'.$lang.'/blog'),                              'num' => '01.04', 'label' => __('sitemap.link_journal')],
        ['href' => url('/'.$lang.'/contact'),                           'num' => '01.05', 'label' => __('sitemap.link_contact')],
        ['href' => url('/'.$lang.'/cart'),                              'num' => '01.06', 'label' => __('sitemap.link_cart')],
    ];

    $accountLinks = [
        ['num' => '02.01', 'label' => __('sitemap.link_dashboard'), 'href' => url('/'.$lang.'/account/dashboard')],
        ['num' => '02.02', 'label' => __('sitemap.link_orders'),    'href' => url('/'.$lang.'/account/orders')],
        ['num' => '02.03', 'label' => __('sitemap.link_addresses'), 'href' => url('/'.$lang.'/account/addresses')],
        ['num' => '02.04', 'label' => __('sitemap.link_refunds'),   'href' => url('/'.$lang.'/account/refunds')],
        ['num' => '02.05', 'label' => __('sitemap.link_settings'),  'href' => url('/'.$lang.'/account/settings')],
    ];

    $jumpNav = [
        ['#core',      '01', __('sitemap.section_core')],
        ['#account',   '02', __('sitemap.section_account')],
        ['#brands',    '03', __('sitemap.section_brands')],
        ['#journal',   '04', __('sitemap.section_journal')],
        ['#content',   '05', __('sitemap.section_pages')],
        ['#legal',     '06', __('sitemap.section_legal')],
        ['#languages', '07', __('sitemap.section_languages')],
    ];
@endphp

@section('title'){{ $pageTitle }} · {{ $siteName }}@endsection
@section('meta_description'){{ __('sitemap.meta_description', ['site' => $siteName]) }}@endsection

@section('canonical')
    <link rel="canonical" href="{{ url('/' . $lang . '/sitemap') }}">
@endsection

@section('hreflang')
    @foreach(['en', 'de', 'lt', 'fr', 'es'] as $hLang)
        <link rel="alternate" hreflang="{{ $hLang }}" href="{{ url('/' . $hLang . '/sitemap') }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ url('/en/sitemap') }}">
@endsection

{{-- ══════════════════════════════════════════════════════════════════════
     INDUSTRIAL BLUEPRINT — HTML SITEMAP / DOCUMENT INDEX
     Spec-sheet table-of-contents for the whole public site
     ══════════════════════════════════════════════════════════════════ --}}
@section('content')

<div class="relative bg-ivory text-ink min-h-screen">

    <div class="fixed inset-0 bg-grid-ivory-fine bg-grid-sm opacity-60 pointer-events-none" aria-hidden="true"></div>

    <div class="relative max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-10 pt-10 pb-24">

        {{-- ═══ Doc header ═══ --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-5 border-b border-rule mb-10">
            <x-ui.breadcrumb :items="[['label' => __('sitemap.breadcrumb_self')]]" :home-label="__('sitemap.breadcrumb_home')" />
            <div class="font-mono text-[10px] tracking-[0.2em] uppercase text-ink-muted">
                DOC · INDEX
            </div>
        </div>

        {{-- ═══ Hero ═══ --}}
        <header class="mb-14">
            <div class="flex items-center gap-4 mb-8">
                <span class="w-10 h-[3px] bg-amber inline-block"></span>
                <span class="bp-spec text-amber-ink">{{ __('sitemap.eyebrow_section') }} · {{ __('sitemap.eyebrow_subject') }}</span>
            </div>
            <h1 class="font-display text-4xl sm:text-5xl md:text-6xl font-extrabold tracking-[-0.03em] leading-[0.95] max-w-4xl">
                {{ $pageTitle }}<span class="text-amber-ink">.</span>
            </h1>
            <p class="mt-6 text-lg text-ink/80 leading-relaxed max-w-2xl">
                {{ __('sitemap.intro', ['site' => $siteName]) }}
            </p>

            {{-- Stats ledger --}}
            <dl class="mt-10 grid grid-cols-2 md:grid-cols-4 border border-ink">
                <div class="px-5 py-4 border-b md:border-b-0 md:border-r border-rule bg-paper">
                    <dt class="bp-spec text-ink-muted">{{ __('sitemap.ledger_brands') }}</dt>
                    <dd class="mt-1 font-mono text-2xl font-bold text-ink tabular-nums leading-none">{{ str_pad((string) $manufacturerCount, 2, '0', STR_PAD_LEFT) }}</dd>
                </div>
                <div class="px-5 py-4 border-b md:border-b-0 md:border-r border-rule bg-paper">
                    <dt class="bp-spec text-ink-muted">{{ __('sitemap.ledger_articles') }}</dt>
                    <dd class="mt-1 font-mono text-2xl font-bold text-ink tabular-nums leading-none">{{ str_pad((string) $blogPostCount, 2, '0', STR_PAD_LEFT) }}</dd>
                </div>
                <div class="px-5 py-4 border-b md:border-b-0 md:border-r border-rule bg-paper">
                    <dt class="bp-spec text-ink-muted">{{ __('sitemap.ledger_pages') }}</dt>
                    <dd class="mt-1 font-mono text-2xl font-bold text-ink tabular-nums leading-none">{{ str_pad((string) ($legalPages->count() + $generalPages->count() + count($coreLinks)), 2, '0', STR_PAD_LEFT) }}</dd>
                </div>
                <div class="px-5 py-4 bg-paper">
                    <dt class="bp-spec text-ink-muted">{{ __('sitemap.ledger_languages') }}</dt>
                    <dd class="mt-1 font-mono text-2xl font-bold text-ink tabular-nums leading-none">{{ str_pad((string) count($languageNames), 2, '0', STR_PAD_LEFT) }}</dd>
                </div>
            </dl>
        </header>

        {{-- ═══ Quick-jump anchor nav ═══ --}}
        <nav aria-label="{{ __('sitemap.jump_to') }}" class="mb-16 border-t border-b border-rule py-4">
            <ul class="flex flex-wrap items-center gap-x-6 gap-y-2 font-mono text-[11px] uppercase tracking-[0.18em]">
                @foreach($jumpNav as [$href, $num, $label])
                    <li>
                        <a href="{{ $href }}" class="group inline-flex items-center gap-2 text-ink-muted hover:text-amber-ink transition-colors">
                            <span class="text-amber-ink">{{ $num }}</span>
                            <span class="border-b border-transparent group-hover:border-amber pb-[1px]">{{ $label }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        {{-- ═══ 01 · CORE ═══ --}}
        <section id="core" class="scroll-mt-24 mb-16">
            <header class="flex items-baseline justify-between pb-4 mb-6 border-b border-ink">
                <div class="flex items-baseline gap-4">
                    <span class="font-mono text-sm font-bold tracking-[0.22em] text-amber-ink">01</span>
                    <h2 class="font-display text-2xl sm:text-3xl font-extrabold tracking-[-0.02em] text-ink">{{ __('sitemap.section_core') }}</h2>
                </div>
                <span class="bp-spec text-ink-muted">{{ str_pad((string) count($coreLinks), 2, '0', STR_PAD_LEFT) }} {{ __('sitemap.entries_suffix') }}</span>
            </header>
            <ul class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 border border-rule">
                @foreach($coreLinks as $item)
                    <li class="border-b lg:[&:nth-last-child(-n+3)]:border-b-0 sm:[&:nth-last-child(-n+2)]:border-b-0 border-r-0 sm:border-r lg:[&:nth-child(3n)]:border-r-0 sm:[&:nth-child(2n)]:border-r-0 lg:[&:nth-child(2n)]:border-r border-rule last:border-b-0">
                        <a href="{{ $item['href'] }}" class="group flex items-center justify-between gap-4 p-5 hover:bg-paper transition-colors">
                            <span class="flex items-center gap-4 min-w-0">
                                <span class="font-mono text-[10px] font-bold tracking-[0.18em] text-amber-ink shrink-0">{{ $item['num'] }}</span>
                                <span class="text-ink font-medium truncate">{{ $item['label'] }}</span>
                            </span>
                            <span class="font-mono text-sm text-ink-muted group-hover:text-amber-ink transition-colors shrink-0">→</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- ═══ 02 · ACCOUNT ═══ --}}
        <section id="account" class="scroll-mt-24 mb-16" x-data>
            <header class="flex items-baseline justify-between pb-4 mb-6 border-b border-ink">
                <div class="flex items-baseline gap-4">
                    <span class="font-mono text-sm font-bold tracking-[0.22em] text-amber-ink">02</span>
                    <h2 class="font-display text-2xl sm:text-3xl font-extrabold tracking-[-0.02em] text-ink">{{ __('sitemap.section_account') }}</h2>
                </div>
                @auth
                    <span class="bp-spec text-ink-muted">{{ __('sitemap.signed_in') }}</span>
                @else
                    <span class="bp-spec text-ink-muted">{{ __('sitemap.signed_out') }}</span>
                @endauth
            </header>

            @auth
                <ul class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 border border-rule">
                    @foreach($accountLinks as $item)
                        <li class="border-b lg:[&:nth-last-child(-n+3)]:border-b-0 sm:[&:nth-last-child(-n+2)]:border-b-0 sm:border-r lg:[&:nth-child(3n)]:border-r-0 sm:[&:nth-child(2n)]:border-r-0 lg:[&:nth-child(2n)]:border-r border-rule last:border-b-0">
                            <a href="{{ $item['href'] }}" class="group flex items-center justify-between gap-4 p-5 hover:bg-paper transition-colors">
                                <span class="flex items-center gap-4 min-w-0">
                                    <span class="font-mono text-[10px] font-bold tracking-[0.18em] text-amber-ink shrink-0">{{ $item['num'] }}</span>
                                    <span class="text-ink font-medium truncate">{{ $item['label'] }}</span>
                                </span>
                                <span class="font-mono text-sm text-ink-muted group-hover:text-amber-ink transition-colors">→</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 border border-rule">
                    <a href="{{ url('/'.$lang.'/?auth=signin') }}#signin"
                       @click.prevent="$dispatch('open-auth-modal')"
                       class="group flex items-center justify-between gap-4 p-6 border-b sm:border-b-0 sm:border-r border-rule hover:bg-paper transition-colors">
                        <span class="flex items-center gap-4 min-w-0">
                            <span class="font-mono text-[10px] font-bold tracking-[0.18em] text-amber-ink">02.01</span>
                            <span class="text-ink font-medium">{{ __('sitemap.link_signin') }}</span>
                        </span>
                        <span class="font-mono text-sm text-ink-muted group-hover:text-amber-ink transition-colors">→</span>
                    </a>
                    <a href="{{ url('/'.$lang.'/?auth=register') }}#register"
                       @click.prevent="$dispatch('open-auth-modal', { tab: 'register' })"
                       class="group flex items-center justify-between gap-4 p-6 hover:bg-paper transition-colors">
                        <span class="flex items-center gap-4 min-w-0">
                            <span class="font-mono text-[10px] font-bold tracking-[0.18em] text-amber-ink">02.02</span>
                            <span class="text-ink font-medium">{{ __('sitemap.link_register') }}</span>
                        </span>
                        <span class="font-mono text-sm text-ink-muted group-hover:text-amber-ink transition-colors">→</span>
                    </a>
                </div>
            @endauth
        </section>

        {{-- ═══ 03 · BRANDS (A–Z) ═══ --}}
        <section id="brands" class="scroll-mt-24 mb-16">
            <header class="flex items-baseline justify-between pb-4 mb-6 border-b border-ink">
                <div class="flex items-baseline gap-4">
                    <span class="font-mono text-sm font-bold tracking-[0.22em] text-amber-ink">03</span>
                    <h2 class="font-display text-2xl sm:text-3xl font-extrabold tracking-[-0.02em] text-ink">{{ __('sitemap.section_brands') }}</h2>
                </div>
                <span class="bp-spec text-ink-muted">{{ str_pad((string) $manufacturerCount, 2, '0', STR_PAD_LEFT) }} {{ __('sitemap.entries_suffix') }}</span>
            </header>

            @if($manufacturerCount === 0)
                <p class="text-ink-muted text-sm italic">{{ __('sitemap.empty_brands') }}</p>
            @else
                {{-- Letter index chips --}}
                <div class="flex flex-wrap gap-1 mb-6">
                    @foreach($manufacturersByLetter->keys() as $letter)
                        <a href="#brands-{{ $letter }}" class="inline-flex items-center justify-center w-8 h-8 border border-rule hover:bg-ink hover:text-ivory hover:border-ink font-mono text-[11px] font-bold transition-colors">
                            {{ $letter }}
                        </a>
                    @endforeach
                </div>

                <div class="space-y-8">
                    @foreach($manufacturersByLetter as $letter => $group)
                        <div id="brands-{{ $letter }}" class="scroll-mt-24">
                            <div class="flex items-baseline gap-4 mb-3 pb-2 border-b border-rule">
                                <span class="font-display text-3xl font-extrabold text-amber-ink leading-none tracking-[-0.02em]">{{ $letter }}</span>
                                <span class="bp-spec text-ink-muted">{{ str_pad((string) $group->count(), 2, '0', STR_PAD_LEFT) }}</span>
                            </div>
                            <ul class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-6 gap-y-2">
                                @foreach($group as $mfr)
                                    <li>
                                        <a href="{{ url('/'.$lang.'/brand/'.$mfr['slug']) }}"
                                           class="group inline-flex items-center gap-2 py-1 text-sm text-ink hover:text-amber-ink transition-colors">
                                            <span class="font-mono text-[10px] text-ink-muted group-hover:text-amber-ink">→</span>
                                            <span class="border-b border-transparent group-hover:border-amber pb-[1px] truncate">{{ $mfr['label'] }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- ═══ 04 · JOURNAL ═══ --}}
        <section id="journal" class="scroll-mt-24 mb-16">
            <header class="flex items-baseline justify-between pb-4 mb-6 border-b border-ink">
                <div class="flex items-baseline gap-4">
                    <span class="font-mono text-sm font-bold tracking-[0.22em] text-amber-ink">04</span>
                    <h2 class="font-display text-2xl sm:text-3xl font-extrabold tracking-[-0.02em] text-ink">{{ __('sitemap.section_journal') }}</h2>
                </div>
                <span class="bp-spec text-ink-muted">{{ str_pad((string) $blogPostCount, 2, '0', STR_PAD_LEFT) }} {{ __('sitemap.entries_suffix') }}</span>
            </header>

            @if($blogPosts->isEmpty())
                <p class="text-ink-muted text-sm italic">{{ __('sitemap.empty_articles') }}</p>
            @else
                <ul class="divide-y divide-rule border-y border-rule">
                    @foreach($blogPosts as $i => $post)
                        @php $postLabel = trans_field($post->title) ?: $post->slug; @endphp
                        <li>
                            <a href="{{ url('/'.$lang.'/blog/'.$post->slug) }}"
                               class="group grid grid-cols-[auto_1fr_auto] gap-4 items-baseline p-4 hover:bg-paper transition-colors">
                                <span class="font-mono text-[10px] font-bold tracking-[0.18em] text-amber-ink">04.{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                <span class="text-ink truncate">
                                    <span class="border-b border-transparent group-hover:border-amber pb-[1px]">{{ $postLabel }}</span>
                                </span>
                                <time class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-muted tabular-nums" datetime="{{ $post->published_at?->toIso8601String() }}">
                                    {{ $post->published_at?->clone()->locale($lang)->translatedFormat('d M Y') }}
                                </time>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-4">
                    <a href="{{ url('/'.$lang.'/blog') }}"
                       class="inline-flex items-center gap-2 font-mono text-[11px] font-bold tracking-[0.18em] uppercase text-ink-muted hover:text-amber-ink transition-colors">
                        <span class="text-amber-ink">→</span>
                        <span class="border-b border-transparent hover:border-amber pb-[1px]">{{ __('sitemap.view_all_articles') }}</span>
                    </a>
                </div>
            @endif
        </section>

        {{-- ═══ 05 · PAGES (general CMS) ═══ --}}
        @if($generalPages->isNotEmpty())
        <section id="content" class="scroll-mt-24 mb-16">
            <header class="flex items-baseline justify-between pb-4 mb-6 border-b border-ink">
                <div class="flex items-baseline gap-4">
                    <span class="font-mono text-sm font-bold tracking-[0.22em] text-amber-ink">05</span>
                    <h2 class="font-display text-2xl sm:text-3xl font-extrabold tracking-[-0.02em] text-ink">{{ __('sitemap.section_pages') }}</h2>
                </div>
                <span class="bp-spec text-ink-muted">{{ str_pad((string) $generalPages->count(), 2, '0', STR_PAD_LEFT) }} {{ __('sitemap.entries_suffix') }}</span>
            </header>

            <ul class="grid grid-cols-1 sm:grid-cols-2 border border-rule">
                @foreach($generalPages as $i => $page)
                    @php $pageLabel = trans_field($page->title) ?: $page->slug; @endphp
                    <li class="border-b sm:[&:nth-last-child(-n+2)]:border-b-0 sm:[&:nth-child(2n-1)]:border-r border-rule last:border-b-0">
                        <a href="{{ url('/'.$lang.'/'.$page->slug) }}" class="group flex items-center justify-between gap-4 p-5 hover:bg-paper transition-colors">
                            <span class="flex items-center gap-4 min-w-0">
                                <span class="font-mono text-[10px] font-bold tracking-[0.18em] text-amber-ink shrink-0">05.{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                <span class="text-ink font-medium truncate">{{ $pageLabel }}</span>
                            </span>
                            <span class="font-mono text-sm text-ink-muted group-hover:text-amber-ink transition-colors">→</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
        @endif

        {{-- ═══ 06 · LEGAL ═══ --}}
        <section id="legal" class="scroll-mt-24 mb-16">
            <header class="flex items-baseline justify-between pb-4 mb-6 border-b border-ink">
                <div class="flex items-baseline gap-4">
                    <span class="font-mono text-sm font-bold tracking-[0.22em] text-amber-ink">06</span>
                    <h2 class="font-display text-2xl sm:text-3xl font-extrabold tracking-[-0.02em] text-ink">{{ __('sitemap.section_legal') }}</h2>
                </div>
                <span class="bp-spec text-ink-muted">{{ str_pad((string) $legalPages->count(), 2, '0', STR_PAD_LEFT) }} {{ __('sitemap.entries_suffix') }}</span>
            </header>

            @if($legalPages->isEmpty())
                <p class="text-ink-muted text-sm italic">{{ __('sitemap.empty_legal') }}</p>
            @else
                <ul class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 border border-rule">
                    @foreach($legalPages as $i => $page)
                        @php $pageLabel = trans_field($page->title) ?: $page->slug; @endphp
                        <li class="border-b lg:[&:nth-last-child(-n+3)]:border-b-0 sm:[&:nth-last-child(-n+2)]:border-b-0 sm:border-r lg:[&:nth-child(3n)]:border-r-0 sm:[&:nth-child(2n)]:border-r-0 lg:[&:nth-child(2n)]:border-r border-rule last:border-b-0">
                            <a href="{{ url('/'.$lang.'/'.$page->slug) }}" class="group flex items-center justify-between gap-4 p-5 hover:bg-paper transition-colors">
                                <span class="flex items-center gap-4 min-w-0">
                                    <span class="font-mono text-[10px] font-bold tracking-[0.18em] text-amber-ink shrink-0">06.{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                    <span class="text-ink font-medium truncate">{{ $pageLabel }}</span>
                                </span>
                                <span class="font-mono text-sm text-ink-muted group-hover:text-amber-ink transition-colors">→</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- ═══ 07 · LANGUAGES ═══ --}}
        <section id="languages" class="scroll-mt-24 mb-16">
            <header class="flex items-baseline justify-between pb-4 mb-6 border-b border-ink">
                <div class="flex items-baseline gap-4">
                    <span class="font-mono text-sm font-bold tracking-[0.22em] text-amber-ink">07</span>
                    <h2 class="font-display text-2xl sm:text-3xl font-extrabold tracking-[-0.02em] text-ink">{{ __('sitemap.section_languages') }}</h2>
                </div>
                <span class="bp-spec text-ink-muted">{{ str_pad((string) count($languageNames), 2, '0', STR_PAD_LEFT) }} {{ __('sitemap.entries_suffix') }}</span>
            </header>

            <ul class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 border border-rule">
                @foreach($languageNames as $code => $name)
                    <li class="border-b lg:border-b-0 lg:[&:not(:last-child)]:border-r sm:[&:nth-child(odd)]:border-r border-rule {{ $loop->last ? 'border-b-0' : '' }}">
                        <a href="{{ url('/'.$code.'/sitemap') }}"
                           class="group flex items-center justify-between gap-3 p-5 hover:bg-paper transition-colors {{ $code === $lang ? 'bg-ink text-ivory hover:bg-ink' : '' }}">
                            <span class="flex items-center gap-3 min-w-0">
                                <span class="font-mono text-[10px] font-bold tracking-[0.18em] {{ $code === $lang ? 'text-amber' : 'text-amber-ink' }} shrink-0">{{ strtoupper($code) }}</span>
                                <span class="font-medium truncate">{{ $name }}</span>
                            </span>
                            @if($code === $lang)
                                <span class="font-mono text-[10px] tracking-[0.2em] uppercase text-amber">{{ __('sitemap.current') }}</span>
                            @else
                                <span class="font-mono text-sm text-ink-muted group-hover:text-amber-ink transition-colors">→</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- ═══ Footer stamp ═══ --}}
        <footer class="mt-20 pt-6 border-t border-rule flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 font-mono text-[10px] uppercase tracking-[0.2em] text-ink-muted">
            <span>{{ __('sitemap.doc_end') }}</span>
            <span>
                {{ __('sitemap.generated') }} · {{ $generatedAt->clone()->locale($lang)->translatedFormat('d M Y · H:i') }} ·
                <a href="{{ url('/sitemap.xml') }}" class="hover:text-amber-ink transition-colors border-b border-transparent hover:border-amber pb-[1px]">{{ __('sitemap.for_crawlers') }}</a>
            </span>
        </footer>
    </div>
</div>

@endsection
