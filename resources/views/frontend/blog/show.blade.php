@extends('layouts.app')

@php
    $lang       = app()->getLocale();
    $siteName   = settings('general.site_name', 'OeParts');
    $metaTitle  = trans_field($post->meta_title) ?: trans_field($post->title);
    $metaDescr  = trans_field($post->meta_description) ?: Str::limit(strip_tags(trans_field($post->content)), 160);
    // str_word_count() only recognises plain A–Z/a–z, splitting accented words
    // (ä, é, š, ą…) apart and inflating the count for every non-English post —
    // a UTF-8-aware whitespace split counts words correctly in all 5 locales.
    $wordCount  = count(array_filter(preg_split('/\s+/u', trim(strip_tags(trans_field($post->content))))));
    $readTime   = max(1, (int) ceil($wordCount / 200));
    $publishedAt = \Carbon\Carbon::parse($post->published_at);
    $postUrl    = route('frontend.blog.show', ['lang' => $lang, 'slug' => $post->slug]);
@endphp

@section('title'){{ $metaTitle }} · {{ $siteName }}@endsection
@section('meta_description'){{ $metaDescr }}@endsection

@section('og_type', 'article')
@section('og_title'){{ $metaTitle }}@endsection
@section('og_description'){{ $metaDescr }}@endsection

@php
    $ogImage = $post->featuredImage?->file_url ?: null;
@endphp
@if($ogImage)
@section('og_image')
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ $metaTitle }}">
@endsection
@endif

@section('canonical')
    <link rel="canonical" href="{{ $postUrl }}">
@endsection

@section('hreflang')
    @foreach(['en', 'de', 'lt', 'fr', 'es'] as $hLang)
        <link rel="alternate" hreflang="{{ $hLang }}" href="{{ route('frontend.blog.show', ['lang' => $hLang, 'slug' => $post->slug]) }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ route('frontend.blog.show', ['lang' => 'en', 'slug' => $post->slug]) }}">
@endsection

@section('json_ld')
<script type="application/ld+json">
{!! json_encode(array_filter([
    '@@context'      => 'https://schema.org',
    '@type'         => 'BlogPosting',
    'headline'      => $metaTitle,
    'description'   => $metaDescr,
    'image'         => $ogImage ?: null,
    'author'        => [
        '@type' => 'Person',
        'name'  => $post->author->name ?? trans('blog.anonymous'),
    ],
    'publisher'     => [
        '@type' => 'Organization',
        'name'  => $siteName,
        'logo'  => [
            '@type' => 'ImageObject',
            'url'   => settings('general.site_url', url('/')) . '/logo.svg',
        ],
    ],
    'datePublished' => $publishedAt->toIso8601String(),
    'dateModified'  => $post->updated_at?->toIso8601String() ?? $publishedAt->toIso8601String(),
    'mainEntityOfPage' => [
        '@type' => 'WebPage',
        '@id'   => $postUrl,
    ],
    'inLanguage'    => $lang,
    'wordCount'     => $wordCount,
]), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}
</script>
<script type="application/ld+json">
{
    "@@context": "https://schema.org",
    "@type": "BreadcrumbList",
    "itemListElement": [
        {"@type": "ListItem", "position": 1, "name": "{{ trans('blog.breadcrumb_home') }}", "item": "{{ url('/'.$lang.'/') }}"},
        {"@type": "ListItem", "position": 2, "name": "{{ trans('blog.breadcrumb_journal') }}", "item": "{{ route('frontend.blog.index', ['lang' => $lang]) }}"},
        {"@type": "ListItem", "position": 3, "name": @json($metaTitle), "item": "{{ $postUrl }}"}
    ]
}
</script>
@endsection

{{-- ══════════════════════════════════════════════════════════════════════
     INDUSTRIAL BLUEPRINT — BLOG POST DETAIL
     Long-form editorial with spec-sheet metadata.
     ══════════════════════════════════════════════════════════════════ --}}
@section('content')

<div class="relative bg-ivory text-ink min-h-screen">

    <div class="fixed inset-0 bg-grid-ivory-fine bg-grid-sm opacity-60 pointer-events-none" aria-hidden="true"></div>

    <div class="relative max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-10 pt-10 pb-24">

        {{-- ═══ Doc header ═══ --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-5 border-b border-rule mb-10 bp-rise">
            <nav class="flex items-center gap-3 font-mono text-[11px] uppercase tracking-[0.16em] text-ink-muted" aria-label="Breadcrumb">
                <a href="{{ url('/'.$lang.'/') }}" class="hover:text-ink transition-colors">{{ trans('blog.breadcrumb_home') }}</a>
                <span class="text-rule-strong">/</span>
                <a href="{{ route('frontend.blog.index', ['lang' => $lang]) }}" class="hover:text-ink transition-colors">{{ trans('blog.breadcrumb_journal') }}</a>
                <span class="text-rule-strong">/</span>
                <span class="text-ink truncate max-w-[14rem]">{{ Str::limit(trans_field($post->title), 40) }}</span>
            </nav>
            <div class="font-mono text-[10px] tracking-[0.2em] uppercase text-ink-muted">
                DOC · ENTRY · {{ strtoupper(substr($post->slug, 0, 16)) }}
            </div>
        </div>

        {{-- ═══ 00 · Article header ═══ --}}
        <header class="mb-12 bp-rise bp-rise-delay-1">
            <div class="flex items-center gap-4 mb-8">
                <span class="w-10 h-[3px] bg-amber inline-block"></span>
                @if($post->category)
                    <a href="{{ route('frontend.blog.index', ['lang' => $lang, 'category' => $post->category->slug]) }}"
                       class="bp-spec text-amber-ink hover:text-ink transition-colors">
                        {{ trans_field($post->category->name) }}
                    </a>
                @else
                    <span class="bp-spec text-amber-ink">{{ trans('blog.journal_entry_eyebrow') }}</span>
                @endif
            </div>

            <h1 class="font-display font-extrabold text-ink leading-[0.95] tracking-[-0.03em]
                       text-4xl sm:text-5xl lg:text-6xl max-w-[22ch]">
                {{ trans_field($post->title) }}<span class="text-amber">.</span>
            </h1>

            <div class="mt-8 mb-6">
                <div class="bp-rule-draw h-px bg-ink/70 origin-left"></div>
            </div>

            {{-- Spec-sheet metadata ledger --}}
            <dl class="grid grid-cols-2 sm:grid-cols-4 gap-0 border border-ink bg-paper divide-x divide-rule max-w-3xl">
                <div class="px-4 py-3">
                    <dt class="bp-spec text-ink-muted">{{ trans('blog.author_label') }}</dt>
                    <dd class="mt-1 font-display text-sm font-bold text-ink leading-tight truncate">
                        {{ $post->author->name ?? trans('blog.anonymous') }}
                    </dd>
                </div>
                <div class="px-4 py-3">
                    <dt class="bp-spec text-ink-muted">{{ trans('blog.published_label') }}</dt>
                    <dd class="mt-1 font-mono text-sm font-bold text-ink tabular-nums leading-tight">
                        {{ $publishedAt->clone()->locale($lang)->translatedFormat('d M Y') }}
                    </dd>
                </div>
                <div class="px-4 py-3">
                    <dt class="bp-spec text-ink-muted">{{ trans('blog.read_time_label') }}</dt>
                    <dd class="mt-1 font-mono text-sm font-bold text-ink tabular-nums leading-tight">
                        {{ trans('blog.min_read', ['count' => $readTime]) }}
                    </dd>
                </div>
                <div class="px-4 py-3">
                    <dt class="bp-spec text-ink-muted">{{ trans('blog.words_label') }}</dt>
                    <dd class="mt-1 font-mono text-sm font-bold text-ink tabular-nums leading-tight">
                        {{ number_format($wordCount) }}
                    </dd>
                </div>
            </dl>

            {{-- Content-freshness signal — only when an editor has explicitly
                 set a review date (last_reviewed_at), not on every incidental save. --}}
            @if($post->last_reviewed_at && $post->last_reviewed_at->gt($publishedAt))
                <p class="mt-3 font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted">
                    {{ trans('blog.updated_on', ['date' => $post->last_reviewed_at->clone()->locale($lang)->translatedFormat('d M Y')]) }}
                </p>
            @endif

            {{-- Tags --}}
            @if($post->tags->count() > 0)
                <div class="mt-5 flex flex-wrap items-center gap-2">
                    <span class="bp-spec text-ink-muted mr-1">{{ trans('blog.tags') }}</span>
                    @foreach($post->tags as $tag)
                        <a href="{{ route('frontend.blog.index', ['lang' => $lang, 'tag' => $tag->slug]) }}"
                           class="inline-flex items-center px-2.5 py-1 border border-rule-strong bg-paper
                                  font-mono text-[10px] font-bold tracking-[0.18em] uppercase text-ink
                                  hover:bg-ink hover:text-ivory hover:border-ink transition-colors">
                            #{{ trans_field($tag->name) }}
                        </a>
                    @endforeach
                </div>
            @endif
        </header>

        {{-- Featured image --}}
        @if($post->featuredImage)
            <figure class="mb-12 bp-rise bp-rise-delay-2">
                <div class="border border-ink bg-paper p-2 bp-shadow">
                    <img src="{{ $post->featuredImage->file_url }}"
                         alt="{{ trans_field($post->title) }}"
                         class="w-full h-auto block" />
                </div>
                <figcaption class="mt-2 font-mono text-[10px] tracking-[0.2em] uppercase text-ink-muted">
                    FIG · {{ trans('blog.featured_asset_label') }}
                </figcaption>
            </figure>
        @endif

        {{-- ═══ Content + Aside grid ═══ --}}
        <div class="grid grid-cols-12 gap-x-4 sm:gap-x-6 lg:gap-x-12 gap-y-10 bp-rise bp-rise-delay-3">

            {{-- ── Article body ── --}}
            <article class="col-span-12 lg:col-span-8">
                <div class="flex items-end pb-3 border-b border-ink mb-8">
                    <span class="bp-spec text-ink">01 · {{ trans('blog.body_label') }}</span>
                </div>

                <div class="prose prose-lg prose-slate max-w-none
                            prose-headings:font-display prose-headings:font-extrabold prose-headings:tracking-[-0.02em] prose-headings:text-ink
                            prose-h2:text-3xl prose-h2:mt-12 prose-h2:mb-4 prose-h2:border-b prose-h2:border-ink prose-h2:pb-3
                            prose-h3:text-xl prose-h3:mt-8 prose-h3:mb-3
                            prose-p:text-body prose-p:leading-relaxed
                            prose-a:text-ink prose-a:underline prose-a:underline-offset-4 prose-a:decoration-amber prose-a:decoration-2 hover:prose-a:text-amber-ink
                            prose-strong:text-ink prose-strong:font-bold
                            prose-code:font-mono prose-code:bg-ivory-alt prose-code:border prose-code:border-rule-strong prose-code:px-1.5 prose-code:py-0.5 prose-code:rounded-none prose-code:before:content-none prose-code:after:content-none
                            prose-pre:bg-ink prose-pre:text-ivory prose-pre:border prose-pre:border-ink prose-pre:rounded-none
                            prose-blockquote:border-l-amber prose-blockquote:border-l-4 prose-blockquote:pl-6 prose-blockquote:text-body prose-blockquote:not-italic
                            prose-ul:marker:text-amber prose-ol:marker:text-ink-muted prose-li:text-body
                            prose-img:border prose-img:border-ink prose-img:rounded-none">
                    {!! clean(trans_field($post->content)) !!}
                </div>

                {{-- Share row --}}
                <div class="mt-12 pt-6 border-t border-ink">
                    <div class="flex items-end pb-3 border-b border-rule mb-5">
                        <span class="bp-spec text-ink">02 · {{ trans('blog.share') }}</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($postUrl) }}"
                           target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-2 px-4 py-2.5 border border-ink bg-paper text-ink
                                  font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                                  hover:bg-ink hover:text-ivory transition-colors">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z"/></svg>
                            Facebook
                        </a>
                        <a href="https://twitter.com/intent/tweet?url={{ urlencode($postUrl) }}&text={{ urlencode(trans_field($post->title)) }}"
                           target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-2 px-4 py-2.5 border border-ink bg-paper text-ink
                                  font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                                  hover:bg-ink hover:text-ivory transition-colors">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                            Twitter
                        </a>
                        <a href="https://www.linkedin.com/shareArticle?mini=true&url={{ urlencode($postUrl) }}"
                           target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-2 px-4 py-2.5 border border-ink bg-paper text-ink
                                  font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                                  hover:bg-ink hover:text-ivory transition-colors">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M4.98 3.5c0 1.381-1.11 2.5-2.48 2.5s-2.48-1.119-2.48-2.5c0-1.38 1.11-2.5 2.48-2.5s2.48 1.12 2.48 2.5zm.02 4.5h-5v16h5v-16zm7.982 0h-4.968v16h4.969v-8.399c0-4.67 6.029-5.052 6.029 0v8.399h4.988v-10.131c0-7.88-8.922-7.593-11.018-3.714v-2.155z"/></svg>
                            LinkedIn
                        </a>
                    </div>
                </div>

                {{-- Back nav --}}
                <div class="mt-10">
                    <a href="{{ route('frontend.blog.index', ['lang' => $lang]) }}"
                       class="inline-flex items-center gap-2 px-5 py-3 border border-ink bg-paper
                              font-mono text-[11px] font-bold tracking-[0.22em] uppercase text-ink
                              hover:bg-ink hover:text-ivory transition-colors">
                        <x-heroicon-s-arrow-long-left class="w-4 h-4" />
                        {{ trans('blog.back_to_blog') }}
                    </a>
                </div>
            </article>

            {{-- ── Aside · Author panel + TOC placeholder ── --}}
            <aside class="col-span-12 lg:col-span-4 space-y-6 lg:sticky lg:top-10 lg:h-fit">

                {{-- Author card --}}
                <div class="border border-ink bg-paper bp-shadow-sm">
                    <div class="px-5 py-3 bg-ink text-ivory flex items-center">
                        <span class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase">{{ trans('blog.author_label') }}</span>
                    </div>
                    <div class="p-5 flex items-start gap-4">
                        <div class="w-12 h-12 border border-ink bg-ink text-amber flex items-center justify-center
                                    font-display text-xl font-extrabold shrink-0 bp-shadow-sm" style="--bp-shadow-color: rgba(245,158,11,1);">
                            {{ strtoupper(substr($post->author->name ?? 'A', 0, 1)) }}
                        </div>
                        <div class="min-w-0">
                            <p class="font-display text-base font-extrabold tracking-[-0.01em] text-ink">
                                {{ $post->author->name ?? trans('blog.anonymous') }}
                            </p>
                            <p class="mt-1 font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted">
                                {{ trans('blog.contributor_label') }}
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Mini directory: explore more --}}
                <div class="border border-ink bg-paper">
                <div class="px-5 py-3 bg-ink text-ivory">
                        <span class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase">{{ trans('blog.quick_nav_eyebrow') }}</span>
                    </div>
                    <ul class="divide-y divide-rule">
                        <li>
                            <a href="{{ route('frontend.blog.index', ['lang' => $lang]) }}"
                               class="group flex items-center justify-between px-5 py-3 text-ink hover:bg-ivory-alt transition-colors">
                                <span class="font-display text-sm font-bold tracking-[-0.01em]">{{ trans('blog.all_entries') }}</span>
                                <x-heroicon-s-arrow-long-right class="w-4 h-4 text-ink-muted group-hover:text-ink transition-colors" />
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('frontend.search.console', ['lang' => $lang]) }}"
                               class="group flex items-center justify-between px-5 py-3 text-ink hover:bg-ivory-alt transition-colors">
                                <span class="font-display text-sm font-bold tracking-[-0.01em]">{{ trans('blog.search_oem_btn') }}</span>
                                <x-heroicon-s-arrow-long-right class="w-4 h-4 text-ink-muted group-hover:text-ink transition-colors" />
                            </a>
                        </li>
                        <li>
                            <a href="{{ url('/'.$lang.'/brands') }}"
                               class="group flex items-center justify-between px-5 py-3 text-ink hover:bg-ivory-alt transition-colors">
                                <span class="font-display text-sm font-bold tracking-[-0.01em]">{{ trans('blog.browse_brands_btn') }}</span>
                                <x-heroicon-s-arrow-long-right class="w-4 h-4 text-ink-muted group-hover:text-ink transition-colors" />
                            </a>
                        </li>
                    </ul>
                </div>

                {{-- CTA --}}
                <div class="border border-ink bg-ink text-ivory p-5">
                    <p class="font-display text-lg font-extrabold tracking-[-0.02em] leading-tight">
                        {{ trans('blog.need_part_heading') }}
                    </p>
                    <p class="mt-2 text-sm text-ivory/70 leading-relaxed">
                        {{ trans('blog.need_part_body') }}
                    </p>
                    <a href="{{ route('frontend.search.console', ['lang' => $lang]) }}"
                       class="mt-4 inline-flex items-center gap-2 px-4 py-2.5 bg-amber text-ink
                              font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                              hover:bg-paper transition-colors">
                        {{ trans('blog.open_search_btn') }}
                        <x-heroicon-s-arrow-long-right class="w-4 h-4" />
                    </a>
                </div>
            </aside>
        </div>

        {{-- ═══ 03 · Related entries ═══ --}}
        @if($relatedPosts->count() > 0)
            <section class="mt-20 bp-rise bp-rise-delay-4">
                <div class="flex items-end justify-between pb-3 border-b border-ink mb-6">
                    <span class="bp-spec text-ink">03 · {{ trans('blog.related_posts') }}</span>
                    <span class="font-mono text-[10px] text-ink-muted tracking-[0.18em] uppercase">
                        {{ $relatedPosts->count() }} {{ trans('blog.nearby_label') }}
                    </span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach($relatedPosts as $relatedPost)
                        <article class="group border border-ink bg-paper flex flex-col hover:bg-ivory-alt transition-colors">
                            @if($relatedPost->featuredImage)
                                <a href="{{ route('frontend.blog.show', ['lang' => $lang, 'slug' => $relatedPost->slug]) }}"
                                   class="block aspect-[16/10] overflow-hidden bg-ivory-alt border-b border-ink">
                                    <img src="{{ $relatedPost->featuredImage->file_url }}"
                                         alt="{{ trans_field($relatedPost->title) }}"
                                         class="w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-300" />
                                </a>
                            @endif
                            <div class="p-5 flex-1 flex flex-col">
                                <div class="flex items-center gap-2 font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted">
                                    <span class="text-amber-ink">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                    <span class="text-rule-strong">│</span>
                                    <time datetime="{{ $relatedPost->published_at }}" class="tabular-nums">
                                        {{ \Carbon\Carbon::parse($relatedPost->published_at)->clone()->locale($lang)->translatedFormat('d M Y') }}
                                    </time>
                                </div>
                                <h3 class="mt-3 font-display text-lg font-extrabold tracking-[-0.02em] leading-tight text-ink">
                                    <a href="{{ route('frontend.blog.show', ['lang' => $lang, 'slug' => $relatedPost->slug]) }}"
                                       class="hover:text-amber-ink transition-colors">
                                        {{ trans_field($relatedPost->title) }}
                                    </a>
                                </h3>
                                <a href="{{ route('frontend.blog.show', ['lang' => $lang, 'slug' => $relatedPost->slug]) }}"
                                   class="mt-auto pt-4 inline-flex items-center gap-1.5 font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink
                                          border-b border-transparent group-hover:border-amber pb-0.5 w-fit">
                                    {{ trans('blog.read_more') }}
                                    <x-heroicon-s-arrow-long-right class="w-3 h-3 transition-transform group-hover:translate-x-0.5" />
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

    </div>
</div>

@endsection
