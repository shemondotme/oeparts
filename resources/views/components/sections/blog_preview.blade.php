{{-- Section: blog_preview (Industrial Blueprint)
     content: eyebrow, headline(ml), subheadline(ml), view_all_text(ml)
     $sectionData['blog_posts'] injected by SectionRendererService.
--}}
@php
    $posts = $sectionData['blog_posts'] ?? collect();
@endphp

@if($posts->isNotEmpty())
@php
    $lang = app()->getLocale();
    $eyebrow = trans_field($section->content['eyebrow'] ?? null);
    $headline = trans_field($section->content['headline'] ?? null);
    $subheadline = trans_field($section->content['subheadline'] ?? null);
    $viewAllText = trans_field($section->content['view_all_text'] ?? null) ?: 'View All Articles';
    $sectionNumber = str_pad((int)(($section->sort_order ?? 10) / 10), 2, '0', STR_PAD_LEFT);
@endphp

<section class="relative bg-ivory text-ink border-b border-rule">
    <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-10 pt-14 md:pt-20 pb-10 md:pb-14">

        <x-section-header
            :eyebrow="$eyebrow"
            :headline="$headline"
            :subheadline="$subheadline" />

        {{-- Posts ledger grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 border border-ink bg-paper">
            @foreach($posts as $index => $post)
            @php
                $category = $post->category ?? null;
                $categoryName = $category ? trans_field($category->name) : 'Article';
                $authorName = $post->author?->name ?? 'Admin';
                $articleNum = str_pad($index + 1, 2, '0', STR_PAD_LEFT);
            @endphp

            <article class="border-r border-b border-rule last:border-r-0">
                <a href="{{ url('/'.$lang.'/blog/'.$post->slug) }}" class="group block h-full p-6 sm:p-8
                           hover:bg-ivory focus-visible:bg-ivory
                           focus-visible:outline focus-visible:outline-2 focus-visible:outline-amber focus-visible:outline-offset-[-2px]
                           transition-colors">

                    {{-- Meta row: article # + category + date --}}
                    <div class="flex items-center justify-between mb-6">
                        <span class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted">
                            № {{ $articleNum }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 border border-rule
                                     font-mono text-[9px] font-bold tracking-[0.2em] uppercase text-ink">
                            {{ $categoryName }}
                        </span>
                    </div>

                    {{-- Amber tick --}}
                    <div class="h-[2px] w-10 bg-amber mb-6"></div>

                    {{-- Date --}}
                    @if($post->published_at)
                    <p class="bp-spec-mono mb-3">
                        <time datetime="{{ $post->published_at->toDateString() }}">
                            {{ $post->published_at->format('Y · m · d') }}
                        </time>
                    </p>
                    @endif

                    {{-- Title --}}
                    <h3 class="font-display text-xl sm:text-2xl font-bold text-ink leading-tight tracking-tight text-balance
                               group-hover:text-amber-ink transition-colors line-clamp-3 mb-4">
                        {{ trans_field($post->title) }}
                    </h3>

                    {{-- Excerpt --}}
                    @if($post->excerpt)
                    <p class="text-sm text-body leading-relaxed line-clamp-3 mb-6">
                        {{ Str::limit(trans_field($post->excerpt), 120) }}
                    </p>
                    @endif

                    {{-- Footer: author + read more --}}
                    <div class="flex items-center justify-between gap-4 pt-5 border-t border-rule">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-8 h-8 bg-ink text-ivory font-mono text-[10px] font-bold flex items-center justify-center tracking-wider shrink-0">
                                {{ strtoupper(substr($authorName, 0, 2)) }}
                            </div>
                            <span class="font-mono text-[10px] tracking-[0.2em] uppercase text-ink-muted truncate">
                                {{ $authorName }}
                            </span>
                        </div>
                        <span class="inline-flex items-center gap-1.5 font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink
                                     group-hover:text-amber-ink transition-colors shrink-0">
                            {{ __('Read') }}
                            <x-heroicon-s-arrow-long-right class="w-4 h-4" />
                        </span>
                    </div>
                </a>
            </article>
            @endforeach
        </div>

        {{-- CTA --}}
        <div class="mt-10 flex flex-wrap items-center justify-end gap-4">
            <a href="{{ url('/'.$lang.'/blog/') }}" class="bp-btn-outline">
                {{ $viewAllText }}
                <x-heroicon-s-arrow-long-right class="w-5 h-5" />
            </a>
        </div>
    </div>
</section>
@endif
