@extends('layouts.app')

@php
    $lang      = app()->getLocale();
    $siteName  = settings('general.site_name', 'OeParts');
    $pageTitle = trans_field($page->title);
    $metaTitle = trans_field($page->meta_title) ?: $pageTitle;
    $metaDescr = trans_field($page->meta_description) ?: Str::limit(strip_tags(trans_field($page->content)), 160);
    $slugUpper = strtoupper(str_replace('-', '·', $page->slug));
    $updatedAt = $page->updated_at ?? $page->created_at;

    $slugKey = strtolower($page->slug);
    $iconMap = [
        'about'            => 'heroicon-s-information-circle',
        'about-us'         => 'heroicon-s-information-circle',
        'privacy-policy'   => 'heroicon-s-lock-closed',
        'privacy'          => 'heroicon-s-lock-closed',
        'terms-of-service' => 'heroicon-s-document-text',
        'terms'            => 'heroicon-s-document-text',
        'cookie-policy'    => 'heroicon-s-shield-check',
        'cookies'          => 'heroicon-s-shield-check',
    ];
    $icon = $iconMap[$slugKey] ?? 'heroicon-s-document-text';

    $labelMap = [
        'about'            => __('pages.eyebrow_about'),
        'about-us'         => __('pages.eyebrow_about'),
        'privacy-policy'   => __('pages.eyebrow_privacy'),
        'privacy'          => __('pages.eyebrow_privacy'),
        'terms-of-service' => __('pages.eyebrow_terms'),
        'terms'            => __('pages.eyebrow_terms'),
        'cookie-policy'    => __('pages.eyebrow_cookies'),
        'cookies'          => __('pages.eyebrow_cookies'),
    ];
    $eyebrow = $labelMap[$slugKey] ?? __('pages.eyebrow_document');
@endphp

@section('title'){{ $metaTitle }} · {{ $siteName }}@endsection
@section('meta_description'){{ $metaDescr }}@endsection

@section('canonical')
    <link rel="canonical" href="{{ url('/' . $lang . '/' . $page->slug) }}">
@endsection

@section('hreflang')
    @foreach(['en', 'de', 'lt', 'fr', 'es'] as $hLang)
        <link rel="alternate" hreflang="{{ $hLang }}" href="{{ url('/' . $hLang . '/' . $page->slug) }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ url('/en/' . $page->slug) }}">
@endsection

@section('json_ld')
<script type="application/ld+json">
{!! json_encode(array_filter([
    '@@context' => 'https://schema.org',
    '@type' => 'WebPage',
    'name' => $pageTitle,
    'description' => $metaDescr,
    'url' => url('/'.$lang.'/'.$page->slug),
    'dateModified' => $updatedAt->toIso8601String(),
])) !!}
</script>
<script type="application/ld+json">
{!! json_encode([
    '@@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => __('pages.breadcrumb_home'), 'item' => url('/'.$lang.'/')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $pageTitle, 'item' => url('/'.$lang.'/'.$page->slug)],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endsection

{{-- ══════════════════════════════════════════════════════════════════════
     INDUSTRIAL BLUEPRINT — CMS PAGE
     Catch-all renderer for About / Privacy / Terms / Cookies / etc.
     ══════════════════════════════════════════════════════════════════ --}}
@section('content')

<div class="relative bg-ivory text-ink min-h-screen">

    <div class="fixed inset-0 bg-grid-ivory-fine bg-grid-sm opacity-60 pointer-events-none" aria-hidden="true"></div>

    <div class="relative max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-10 pt-10 pb-24">

        {{-- ═══ Doc header ═══ --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-5 border-b border-rule mb-10 bp-rise">
            <nav class="flex items-center gap-3 font-mono text-[11px] uppercase tracking-[0.16em] text-ink-muted" aria-label="Breadcrumb">
                <a href="{{ url('/'.$lang.'/') }}" class="hover:text-ink transition-colors">{{ __('pages.breadcrumb_home') }}</a>
                <span class="text-rule-strong">/</span>
                <span class="text-ink truncate max-w-[14rem]">{{ $pageTitle }}</span>
            </nav>
            <div class="font-mono text-[10px] tracking-[0.2em] uppercase text-ink-muted">
                DOC · {{ $slugUpper }} · REV. {{ $updatedAt->format('Y.m.d') }}
            </div>
        </div>

        {{-- ═══ Hero ═══ --}}
        <header class="mb-14 bp-rise bp-rise-delay-1">
            <div class="flex items-center gap-4 mb-8">
                <span class="w-10 h-[3px] bg-amber inline-block"></span>
                <span class="bp-spec text-amber-ink">{{ $eyebrow }}</span>
            </div>

            <h1 class="font-display font-extrabold text-ink leading-[0.95] tracking-[-0.03em]
                       text-4xl sm:text-5xl lg:text-6xl max-w-[22ch] break-words">
                {{ $pageTitle }}<span class="text-amber">.</span>
            </h1>

            <div class="mt-8 mb-6">
                <div class="bp-rule-draw h-px bg-ink/70 origin-left"></div>
            </div>

            {{-- Meta ledger --}}
            <dl class="grid grid-cols-2 sm:grid-cols-4 gap-0 border border-ink bg-paper divide-x divide-rule max-w-3xl">
                <div class="px-4 py-3">
                    <dt class="bp-spec text-ink-muted">{{ __('pages.meta_document') }}</dt>
                    <dd class="mt-1 font-mono text-sm font-bold text-ink uppercase tracking-[0.12em] truncate leading-tight">{{ $page->slug }}</dd>
                </div>
                <div class="px-4 py-3">
                    <dt class="bp-spec text-ink-muted">{{ __('pages.meta_language') }}</dt>
                    <dd class="mt-1 font-mono text-sm font-bold text-ink uppercase tabular-nums leading-tight">{{ strtoupper($lang) }}</dd>
                </div>
                <div class="px-4 py-3">
                    <dt class="bp-spec text-ink-muted">{{ __('pages.meta_revised') }}</dt>
                    <dd class="mt-1 font-mono text-sm font-bold text-ink tabular-nums leading-tight">{{ $updatedAt->clone()->locale($lang)->translatedFormat('d M Y') }}</dd>
                </div>
                <div class="px-4 py-3">
                    <dt class="bp-spec text-ink-muted">{{ __('pages.meta_status') }}</dt>
                    <dd class="mt-1 font-mono text-xs font-bold text-emerald-700 uppercase tracking-[0.18em] leading-tight flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 bg-emerald-600"></span>
                        {{ __('pages.meta_published') }}
                    </dd>
                </div>
            </dl>
        </header>

        {{-- ═══ Featured image ═══ --}}
        @if($page->featured_image_id && $page->featuredImage)
            <figure class="mb-14 bp-rise bp-rise-delay-2">
                <div class="border border-ink bg-paper p-2 bp-shadow">
                    <img src="{{ $page->featuredImage->file_url }}"
                         alt="{{ $pageTitle }}"
                         class="w-full h-auto block" />
                </div>
                <figcaption class="mt-2 font-mono text-[10px] tracking-[0.2em] uppercase text-ink-muted">
                    FIG · {{ __('pages.featured_asset') }}
                </figcaption>
            </figure>
        @endif

        {{-- ═══ Main content grid ═══ --}}
        <div class="grid grid-cols-12 gap-x-4 sm:gap-x-6 lg:gap-x-12 gap-y-10 bp-rise bp-rise-delay-3">

            {{-- ── Body ── --}}
            <article class="col-span-12 lg:col-span-8">
                <div class="flex items-end justify-between pb-3 border-b border-ink mb-8">
                    <span class="bp-spec text-ink">01 · {{ __('pages.body_content') }}</span>
                    <span class="hidden sm:inline font-mono text-[10px] text-ink-muted tracking-[0.18em] uppercase">
                        {{ __('pages.revision') }} {{ $updatedAt->format('Y.m.d') }}
                    </span>
                </div>

                <div class="prose prose-lg prose-slate max-w-none
                            prose-headings:font-display prose-headings:font-extrabold prose-headings:tracking-[-0.02em] prose-headings:text-ink
                            prose-h1:text-3xl prose-h1:mt-10 prose-h1:mb-4
                            prose-h2:text-2xl prose-h2:mt-10 prose-h2:mb-4 prose-h2:border-b prose-h2:border-ink prose-h2:pb-3
                            prose-h3:text-xl prose-h3:mt-8 prose-h3:mb-3
                            prose-h4:text-lg prose-h4:mt-6 prose-h4:mb-2
                            prose-p:text-body prose-p:leading-relaxed
                            prose-a:text-ink prose-a:underline prose-a:underline-offset-4 prose-a:decoration-amber prose-a:decoration-2 hover:prose-a:text-amber-ink
                            prose-strong:text-ink prose-strong:font-bold
                            prose-code:font-mono prose-code:bg-ivory-alt prose-code:border prose-code:border-rule-strong prose-code:px-1.5 prose-code:py-0.5 prose-code:rounded-none prose-code:before:content-none prose-code:after:content-none
                            prose-pre:bg-ink prose-pre:text-ivory prose-pre:border prose-pre:border-ink prose-pre:rounded-none
                            prose-blockquote:border-l-amber prose-blockquote:border-l-4 prose-blockquote:pl-6 prose-blockquote:text-body prose-blockquote:not-italic
                            prose-ul:marker:text-amber prose-ol:marker:text-ink-muted prose-li:text-body
                            prose-img:border prose-img:border-ink prose-img:rounded-none
                            prose-table:border prose-table:border-ink prose-th:border prose-th:border-rule prose-th:bg-ivory-alt prose-th:px-3 prose-th:py-2 prose-th:font-mono prose-th:text-[10px] prose-th:uppercase prose-th:tracking-[0.18em]
                            prose-td:border prose-td:border-rule prose-td:px-3 prose-td:py-2">
                    {!! clean(trans_field($page->content)) !!}
                </div>

                {{-- Sign-off --}}
                <div class="mt-12 pt-6 border-t border-ink flex flex-wrap items-center justify-between gap-3 bp-spec-mono">
                    <span>{{ __('pages.end_of_document') }} · {{ $page->slug }}</span>
                    <span class="tabular-nums">{{ $updatedAt->format('d M Y') }}</span>
                </div>
            </article>

            {{-- ── Aside ── --}}
            <aside class="col-span-12 lg:col-span-4 space-y-6 lg:sticky lg:top-10 lg:h-fit">

                {{-- Doc identity --}}
                <div class="border border-ink bg-paper bp-shadow-sm">
                    <div class="px-5 py-3 bg-ink text-ivory flex items-center">
                        <span class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase">{{ __('pages.document_id') }}</span>
                    </div>
                    <div class="p-5 flex items-start gap-4">
                        <div class="w-12 h-12 border border-ink bg-ivory-alt text-ink flex items-center justify-center shrink-0">
                            <x-dynamic-component :component="$icon" class="w-6 h-6" />
                        </div>
                        <div class="min-w-0">
                            <p class="font-display text-base font-extrabold tracking-[-0.01em] text-ink truncate">
                                {{ $pageTitle }}
                            </p>
                            <p class="mt-1 font-mono text-[10px] tracking-[0.2em] uppercase text-ink-muted">
                                /{{ $lang }}/{{ $page->slug }}
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Legal/CMS nav links --}}
                <div class="border border-ink bg-paper">
                    <div class="px-5 py-3 bg-ink text-ivory">
                        <span class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase">{{ __('pages.related_docs') }}</span>
                    </div>
                    <ul class="divide-y divide-rule">
                        @foreach([
                            ['about',            __('pages.nav_about')],
                            ['privacy-policy',   __('pages.nav_privacy')],
                            ['terms-of-service', __('pages.nav_terms')],
                            ['cookie-policy',    __('pages.nav_cookies')],
                        ] as [$relSlug, $relLabel])
                            @php $isActive = $relSlug === $page->slug; @endphp
                            <li>
                                <a href="{{ url('/'.$lang.'/'.$relSlug) }}"
                                   class="group flex items-center justify-between gap-3 px-5 py-3 transition-colors
                                          {{ $isActive ? 'bg-ink text-ivory' : 'text-ink hover:bg-ivory-alt' }}">
                                    <span class="font-display text-sm font-bold tracking-[-0.01em]">{{ $relLabel }}</span>
                                    @if($isActive)
                                        <x-heroicon-s-arrow-long-right class="w-4 h-4 text-amber" />
                                    @else
                                        <x-heroicon-s-arrow-long-right class="w-4 h-4 text-ink-muted group-hover:text-ink transition-colors" />
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- Language alternates --}}
                <div class="border border-ink bg-paper">
                    <div class="px-5 py-3 bg-ink text-ivory">
                        <span class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase">{{ __('pages.languages') }}</span>
                    </div>
                    <div class="p-4 grid grid-cols-5 gap-1">
                        @foreach(['en' => 'EN', 'de' => 'DE', 'lt' => 'LT', 'fr' => 'FR', 'es' => 'ES'] as $code => $label)
                            <a href="{{ url('/'.$code.'/'.$page->slug) }}"
                               class="inline-flex items-center justify-center h-9 font-mono text-[11px] font-bold tracking-[0.14em]
                                      border transition-colors
                                      {{ $code === $lang
                                          ? 'bg-ink text-amber border-ink'
                                          : 'bg-paper text-ink border-rule-strong hover:bg-ink hover:text-amber hover:border-ink' }}">
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>
                </div>

                {{-- Contact CTA --}}
                <div class="border border-ink bg-ink text-ivory p-5">
                    <p class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-amber mb-3">{{ __('pages.questions') }}</p>
                    <p class="font-display text-base font-extrabold tracking-[-0.02em] leading-tight">
                        {{ __('pages.contact_our_desk') }}
                    </p>
                    <p class="mt-2 text-sm text-ivory/70 leading-relaxed">
                        {{ __('pages.clarification_note') }}
                    </p>
                    <a href="{{ url('/'.$lang.'/contact') }}"
                       class="mt-4 inline-flex items-center gap-2 px-4 py-2.5 bg-amber text-ink
                              font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                              hover:bg-paper transition-colors">
                        {{ __('pages.open_contact') }}
                        <x-heroicon-s-arrow-long-right class="w-4 h-4" />
                    </a>
                </div>
            </aside>
        </div>
    </div>
</div>

@endsection
