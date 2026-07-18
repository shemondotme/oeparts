@extends('layouts.app')

@php
    $lang     = app()->getLocale();
    $siteName = settings('general.site_name', 'OeParts');
    $pageTitle   = trans('blog.title', [], $lang);
    $pageDescr   = trans('blog.description', [], $lang);
    $activeCat   = request('category');
    $activeTag   = request('tag');
    $searchQuery = request('search');
    $hasFilter   = $activeCat || $activeTag || $searchQuery;
@endphp

@section('title'){{ $pageTitle }} · {{ $siteName }}@endsection
@section('meta_description'){{ $pageDescr }}@endsection

@section('og_type', 'website')
@section('og_title'){{ $pageTitle }}@endsection
@section('og_description'){{ $pageDescr }}@endsection

@php
    // Canonical/hreflang must reflect active filters and pagination — otherwise
    // every filtered/paginated view canonicalizes to the bare unfiltered page 1.
    $filterQuery = array_filter(request()->only(['category', 'tag', 'search', 'page']));
@endphp

@section('canonical')
    <link rel="canonical" href="{{ route('frontend.blog.index', array_merge(['lang' => $lang], $filterQuery)) }}">
@endsection

@section('hreflang')
    @foreach(['en', 'de', 'lt', 'fr', 'es'] as $hLang)
        <link rel="alternate" hreflang="{{ $hLang }}" href="{{ route('frontend.blog.index', array_merge(['lang' => $hLang], $filterQuery)) }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ route('frontend.blog.index', array_merge(['lang' => 'en'], $filterQuery)) }}">
@endsection

@if($posts->hasPages())
    @section('pagination_links')
        @if(!$posts->onFirstPage())
            <link rel="prev" href="{{ route('frontend.blog.index', array_merge(request()->query(), ['lang' => $lang, 'page' => $posts->currentPage() - 1])) }}">
        @endif
        @if($posts->hasMorePages())
            <link rel="next" href="{{ route('frontend.blog.index', array_merge(request()->query(), ['lang' => $lang, 'page' => $posts->currentPage() + 1])) }}">
        @endif
    @endsection
@endif

@section('json_ld')
<script type="application/ld+json">
{
    "@@context": "https://schema.org",
    "@type": "Blog",
    "name": @json($pageTitle),
    "description": @json($pageDescr),
    "url": "{{ route('frontend.blog.index', ['lang' => $lang]) }}",
    "publisher": {
        "@type": "Organization",
        "name": @json($siteName),
        "logo": {
            "@type": "ImageObject",
            "url": "{{ settings('general.site_url', url('/')) }}/logo.svg"
        }
    },
    "inLanguage": "{{ $lang }}"
}
</script>
<script type="application/ld+json">
{
    "@@context": "https://schema.org",
    "@type": "BreadcrumbList",
    "itemListElement": [
        {"@type": "ListItem", "position": 1, "name": "{{ trans('blog.breadcrumb_home') }}", "item": "{{ url('/'.$lang.'/') }}"},
        {"@type": "ListItem", "position": 2, "name": "{{ trans('blog.breadcrumb_journal') }}", "item": "{{ route('frontend.blog.index', ['lang' => $lang]) }}"}
    ]
}
</script>
@endsection

{{-- ══════════════════════════════════════════════════════════════════════
     INDUSTRIAL BLUEPRINT — JOURNAL (BLOG INDEX)
     Editorial ledger, published entries listed as technical articles.
     ══════════════════════════════════════════════════════════════════ --}}
@section('content')

<div class="relative bg-ivory text-ink min-h-screen">

    <div class="fixed inset-0 bg-grid-ivory-fine bg-grid-sm opacity-70 pointer-events-none" aria-hidden="true"></div>

    <div class="relative max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-10 pt-10 pb-24">

        {{-- ═══ Doc header ═══ --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-5 border-b border-rule mb-10 bp-rise">
            <nav class="flex items-center gap-3 font-mono text-[11px] uppercase tracking-[0.16em] text-ink-muted" aria-label="Breadcrumb">
                <a href="{{ url('/'.$lang.'/') }}" class="hover:text-ink transition-colors">{{ trans('blog.breadcrumb_home') }}</a>
                <span class="text-rule-strong">/</span>
                <span class="text-ink">{{ trans('blog.breadcrumb_journal') }}</span>
            </nav>
        </div>

        {{-- ═══ Hero ═══ --}}
        <header class="grid grid-cols-12 gap-x-4 sm:gap-x-6 lg:gap-x-8 gap-y-8 mb-12 bp-rise bp-rise-delay-1">
            <div class="col-span-12 lg:col-span-8">
                <div class="flex items-center gap-4 mb-8">
                    <span class="w-10 h-[3px] bg-amber inline-block"></span>
                    <span class="bp-spec text-amber-ink">{{ trans('blog.editorial_ledger_eyebrow') }}</span>
                </div>
                <h1 class="font-display font-extrabold text-ink leading-[0.95] tracking-[-0.03em]
                           text-4xl sm:text-5xl lg:text-6xl max-w-[18ch]">
                    {{ $pageTitle }}<span class="text-amber">.</span>
                </h1>
                <div class="mt-6 mb-6">
                    <div class="bp-rule-draw h-px bg-ink/70 origin-left"></div>
                </div>
                <p class="max-w-xl text-lg text-body leading-relaxed">
                    {{ $pageDescr }}
                </p>
            </div>

            {{-- Search form --}}
            <aside class="col-span-12 lg:col-span-4">
                <form action="{{ route('frontend.blog.index', ['lang' => $lang]) }}" method="GET"
                      class="border border-ink bg-paper bp-register">
                    <div class="px-5 py-3 bg-ink text-ivory flex items-center justify-between">
                        <span class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase">{{ trans('blog.search_archive_eyebrow') }}</span>
                        <span class="font-mono text-[10px] tracking-[0.18em] uppercase text-ivory/60">{{ trans_choice('blog.entry_count', $posts->total(), ['count' => $posts->total()]) }}</span>
                    </div>
                    <div class="p-5 space-y-3">
                        <label for="blog-search" class="bp-spec text-ink-muted">{{ trans('blog.query_label') }}</label>
                        <div class="flex">
                            <input id="blog-search" type="text" name="search"
                                   value="{{ $searchQuery }}"
                                   placeholder="{{ trans('blog.search_placeholder') }}"
                                   class="flex-1 px-3 py-2.5 border border-ink bg-ivory
                                          font-mono text-sm text-ink
                                          focus:outline-none focus:bg-paper focus:border-amber" />
                            <button type="submit"
                                    class="inline-flex items-center justify-center px-4 py-2.5
                                           bg-ink text-ivory border border-ink border-l-0
                                           hover:bg-amber hover:text-ink hover:border-amber
                                           transition-colors">
                                <x-heroicon-s-magnifying-glass class="w-4 h-4" />
                            </button>
                        </div>
                        @if($hasFilter)
                            <a href="{{ route('frontend.blog.index', ['lang' => $lang]) }}"
                               class="inline-flex items-center gap-1.5 pt-1 font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted hover:text-amber-ink">
                                <x-heroicon-s-x-mark class="w-3 h-3" />
                                {{ trans('blog.clear_filters') }}
                            </a>
                        @endif
                    </div>
                </form>
            </aside>
        </header>

        {{-- Active filter chips --}}
        @if($hasFilter)
            @php
                $activeCatName = $activeCat ? trans_field(optional($categories->firstWhere('slug', $activeCat))->name) : null;
                $activeTagName = $activeTag ? trans_field(optional($tags->firstWhere('slug', $activeTag))->name) : null;
            @endphp
            <div class="mb-8 flex flex-wrap items-center gap-2 bp-rise bp-rise-delay-2">
                <span class="bp-spec text-ink-muted mr-2">{{ trans('blog.active_filter_label') }}</span>
                @if($activeCat)
                    <span class="inline-flex items-center gap-2 px-3 py-1.5 border border-ink bg-paper font-mono text-[10px] tracking-[0.18em] uppercase text-ink">
                        {{ trans('blog.category_label') }} · {{ $activeCatName ?: $activeCat }}
                    </span>
                @endif
                @if($activeTag)
                    <span class="inline-flex items-center gap-2 px-3 py-1.5 border border-ink bg-paper font-mono text-[10px] tracking-[0.18em] uppercase text-ink">
                        {{ trans('blog.tag_label') }} · {{ $activeTagName ?: $activeTag }}
                    </span>
                @endif
                @if($searchQuery)
                    <span class="inline-flex items-center gap-2 px-3 py-1.5 border border-ink bg-paper font-mono text-[10px] tracking-[0.18em] uppercase text-ink">
                        {{ trans('blog.query_label') }} · "{{ $searchQuery }}"
                    </span>
                @endif
            </div>
        @endif

        {{-- ═══ Main grid ═══ --}}
        <div class="grid grid-cols-12 gap-x-4 sm:gap-x-6 lg:gap-x-10 gap-y-10 bp-rise bp-rise-delay-3">

            {{-- ── Entries column ── --}}
            <main class="col-span-12 lg:col-span-8">
                <div class="flex items-end justify-between pb-3 border-b border-ink mb-6">
                    <span class="bp-spec text-ink">01 · {{ trans('blog.entries_label') }}</span>
                    <span class="font-mono text-[10px] text-ink-muted tracking-[0.18em] uppercase">
                        {{ trans('blog.page_label') }} {{ $posts->currentPage() }}/{{ max(1, $posts->lastPage()) }}
                    </span>
                </div>

                @if($posts->count() > 0)
                    <ul class="space-y-8">
                        @foreach($posts as $post)
                            @php
                                $postUrl = route('frontend.blog.show', ['lang' => $lang, 'slug' => $post->slug]);
                                $indexNum = ($posts->currentPage() - 1) * $posts->perPage() + $loop->iteration;
                            @endphp
                            <li>
                                <article class="relative grid grid-cols-12 gap-4 sm:gap-6 border border-ink bg-paper p-5 sm:p-6
                                                 hover:bg-ivory-alt transition-colors">
                                    {{-- Image --}}
                                    <div class="col-span-12 sm:col-span-4 md:col-span-3">
                                        <a href="{{ $postUrl }}" class="block border border-rule-strong bg-ivory-alt aspect-[4/3] overflow-hidden">
                                            @if($post->featured_image_id && $post->featuredImage)
                                                <img src="{{ $post->featuredImage->file_url }}"
                                                     alt="{{ trans_field($post->title) }}"
                                                     class="w-full h-full object-cover grayscale hover:grayscale-0 transition-all duration-300" />
                                            @else
                                                <div class="w-full h-full flex items-center justify-center">
                                                    <span class="bp-spec-mono">
                                                        {{ str_pad($indexNum, 3, '0', STR_PAD_LEFT) }}
                                                    </span>
                                                </div>
                                            @endif
                                        </a>
                                    </div>

                                    {{-- Body --}}
                                    <div class="col-span-12 sm:col-span-8 md:col-span-9 min-w-0 flex flex-col">
                                        {{-- Meta --}}
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted">
                                            <span class="text-amber-ink">{{ str_pad($indexNum, 3, '0', STR_PAD_LEFT) }}</span>
                                            @if($post->category)
                                                <span class="text-rule-strong">│</span>
                                                <a href="{{ route('frontend.blog.index', ['lang' => $lang, 'category' => $post->category->slug]) }}"
                                                   class="text-ink hover:text-amber-ink">
                                                    {{ trans_field($post->category->name) }}
                                                </a>
                                            @endif
                                            <span class="text-rule-strong">│</span>
                                            <time datetime="{{ $post->published_at }}" class="tabular-nums">
                                                {{ \Carbon\Carbon::parse($post->published_at)->clone()->locale($lang)->translatedFormat('d M Y') }}
                                            </time>
                                        </div>

                                        {{-- Title --}}
                                        <h2 class="mt-3 font-display text-xl sm:text-2xl font-extrabold tracking-[-0.02em] leading-tight text-ink">
                                            <a href="{{ $postUrl }}" class="hover:text-amber-ink transition-colors">
                                                {{ trans_field($post->title) }}
                                            </a>
                                        </h2>

                                        {{-- Excerpt --}}
                                        @if(trans_field($post->excerpt))
                                            <p class="mt-2 text-sm text-body leading-relaxed line-clamp-3">
                                                {{ trans_field($post->excerpt) }}
                                            </p>
                                        @endif

                                        {{-- Footer: author + read more --}}
                                        <div class="mt-auto pt-4 flex items-center justify-between gap-3">
                                            <div class="flex items-center gap-2.5 min-w-0">
                                                <span class="inline-flex w-7 h-7 border border-ink bg-ink text-amber items-center justify-center
                                                             font-display text-xs font-extrabold shrink-0">
                                                    {{ strtoupper(substr($post->author->name ?? 'A', 0, 1)) }}
                                                </span>
                                                <span class="font-mono text-[10px] tracking-[0.16em] uppercase text-ink-muted truncate">
                                                    {{ $post->author->name ?? trans('blog.anonymous') }}
                                                </span>
                                            </div>
                                            <a href="{{ $postUrl }}"
                                               class="group inline-flex items-center gap-1.5 font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink
                                                      border-b border-amber pb-0.5 hover:text-amber-ink">
                                                {{ trans('blog.read_more') }}
                                                <x-heroicon-s-arrow-long-right class="w-3 h-3 transition-transform group-hover:translate-x-0.5" />
                                            </a>
                                        </div>
                                    </div>
                                </article>
                            </li>
                        @endforeach
                    </ul>

                    {{-- Pagination --}}
                    @if($posts->hasPages())
                        <div class="mt-10 pt-5 border-t border-ink">
                            {{ $posts->links('components.ui.pagination') }}
                        </div>
                    @endif
                @else
                    {{-- Empty --}}
                    <div class="border border-ink bg-paper p-12 text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 border border-ink bg-ivory-alt mb-5">
                            <x-heroicon-o-document-text class="w-7 h-7 text-ink-muted" />
                        </div>
                        <p class="font-display text-xl font-bold text-ink leading-tight">{{ trans('blog.no_posts') }}</p>
                        <p class="mt-2 text-sm text-ink-muted max-w-md mx-auto">
                            {{ trans('blog.empty_note') }}
                        </p>
                        @if($hasFilter)
                            <a href="{{ route('frontend.blog.index', ['lang' => $lang]) }}"
                               class="mt-6 inline-flex items-center gap-2 px-5 py-2.5 bg-ink text-ivory
                                      font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                                      hover:bg-amber hover:text-ink transition-colors">
                                <x-heroicon-s-x-mark class="w-4 h-4" />
                                {{ trans('blog.reset_filters') }}
                            </a>
                        @endif
                    </div>
                @endif
            </main>

            {{-- ── Sidebar ── --}}
            <aside class="col-span-12 lg:col-span-4 space-y-8">

                {{-- Featured --}}
                @if($featuredPost)
                    <div class="border border-ink bg-paper bp-shadow-sm">
                        <div class="px-5 py-3 bg-amber text-ink flex items-center">
                            <span class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase">{{ trans('blog.featured') }}</span>
                        </div>
                        @if($featuredPost->featuredImage)
                            <div class="aspect-[16/9] bg-ivory-alt overflow-hidden">
                                <img src="{{ $featuredPost->featuredImage->file_url }}"
                                     alt="{{ trans_field($featuredPost->title) }}"
                                     class="w-full h-full object-cover" />
                            </div>
                        @endif
                        <div class="p-5">
                            <h3 class="font-display text-lg font-extrabold tracking-[-0.02em] leading-tight text-ink">
                                <a href="{{ route('frontend.blog.show', ['lang' => $lang, 'slug' => $featuredPost->slug]) }}"
                                   class="hover:text-amber-ink transition-colors">
                                    {{ trans_field($featuredPost->title) }}
                                </a>
                            </h3>
                            <p class="mt-3 font-mono text-[10px] tracking-[0.2em] uppercase text-ink-muted">
                                {{ \Carbon\Carbon::parse($featuredPost->published_at)->clone()->locale($lang)->translatedFormat('d M Y') }}
                            </p>
                        </div>
                    </div>
                @endif

                {{-- Categories --}}
                @if($categories->count() > 0)
                    <div class="border border-ink bg-paper">
                        <div class="px-5 py-3 bg-ink text-ivory">
                            <span class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase">{{ trans('blog.categories') }}</span>
                        </div>
                        <ul class="divide-y divide-rule">
                            @foreach($categories as $category)
                                @php $isActiveCat = $activeCat === $category->slug; @endphp
                                <li>
                                    <a href="{{ route('frontend.blog.index', ['lang' => $lang, 'category' => $category->slug]) }}"
                                       class="group flex items-center justify-between gap-3 px-5 py-3
                                              {{ $isActiveCat ? 'bg-ink text-ivory' : 'text-ink hover:bg-ivory-alt' }} transition-colors">
                                        <span class="font-display text-sm font-bold tracking-[-0.01em] truncate">
                                            {{ trans_field($category->name) }}
                                        </span>
                                        <span class="font-mono text-[10px] font-bold tracking-[0.2em] tabular-nums
                                                     {{ $isActiveCat ? 'text-amber' : 'text-ink-muted group-hover:text-ink' }}">
                                            {{ str_pad($category->blog_posts_count, 2, '0', STR_PAD_LEFT) }}
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Tags --}}
                @if($tags->count() > 0)
                    <div class="border border-ink bg-paper">
                        <div class="px-5 py-3 bg-ink text-ivory">
                            <span class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase">{{ trans('blog.tags') }}</span>
                        </div>
                        <div class="p-5 flex flex-wrap gap-2">
                            @foreach($tags as $tag)
                                @php $isActiveTag = $activeTag === $tag->slug; @endphp
                                <a href="{{ route('frontend.blog.index', ['lang' => $lang, 'tag' => $tag->slug]) }}"
                                   class="inline-flex items-center px-3 py-1.5 border border-rule-strong
                                          font-mono text-[10px] font-bold tracking-[0.18em] uppercase
                                          {{ $isActiveTag ? 'bg-ink text-ivory border-ink' : 'bg-paper text-ink hover:bg-ink hover:text-ivory hover:border-ink' }}
                                          transition-colors">
                                    #{{ trans_field($tag->name) }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Newsletter / CTA --}}
                <div class="border border-ink bg-ink text-ivory p-5">
                    <p class="font-display text-lg font-extrabold tracking-[-0.02em] leading-tight">
                        {{ trans('blog.newsletter_heading') }}
                    </p>
                    <p class="mt-2 text-sm text-ivory/70 leading-relaxed">
                        {{ trans('blog.newsletter_body') }}
                    </p>
                    <a href="{{ url('/'.$lang.'/#newsletter') }}"
                       class="mt-4 inline-flex items-center gap-2 px-4 py-2.5 bg-amber text-ink border border-amber
                              font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                              hover:bg-paper hover:border-paper transition-colors">
                        {{ trans('blog.subscribe_btn') }}
                        <x-heroicon-s-arrow-long-right class="w-4 h-4" />
                    </a>
                </div>
            </aside>
        </div>

    </div>
</div>

@endsection
