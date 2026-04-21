{{-- Section: blog_preview
     content: headline(ml), subheadline(ml), eyebrow(ml), view_all_text(ml)
     $sectionData['blog_posts'] is injected by SectionRendererService.
     Hidden by default (is_active=false) until blog posts exist.
     Design: Clean cards with icon wrappers, following DESIGN.md standards.
--}}
@php $posts = $sectionData['blog_posts'] ?? collect(); @endphp

@if($posts->isNotEmpty())
@php
    $lang = app()->getLocale();
@endphp

<section class="py-14 md:py-20 px-4 bg-section-alt/50 relative overflow-hidden">

    {{-- Decorative background blobs --}}
    <div class="absolute inset-0 opacity-10 pointer-events-none" aria-hidden="true">
        <div class="absolute top-10 right-10 w-96 h-96 bg-amber rounded-full filter blur-3xl"></div>
        <div class="absolute bottom-10 left-10 w-72 h-72 bg-blue-500 rounded-full filter blur-3xl"></div>
    </div>

    <div class="max-w-6xl mx-auto relative z-10">

        <x-section-heading
            :eyebrow="trans_field($section->content['eyebrow'] ?? null)"
            :headline="trans_field($section->content['headline'] ?? null)"
            :subheadline="trans_field($section->content['subheadline'] ?? null)"
            :accentBar="true"
            class="mb-12"
        />

        {{-- Blog Posts Grid --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
            @foreach($posts as $index => $post)
            @php
                $category = $post->category ?? null;
                $categoryName = $category?->name['en'] ?? 'Article';
                $delay = $index * 100;
                $authorName = $post->author?->name ?? 'Admin';
            @endphp

            <article
                x-data="{ shown: false }"
                x-init="
                    const observer = new IntersectionObserver(
                        (entries) => {
                            entries.forEach(entry => {
                                if (entry.isIntersecting) {
                                    setTimeout(() => shown = true, {{ $delay }});
                                    observer.unobserve(entry.target);
                                }
                            });
                        },
                        { threshold: 0.2 }
                    );
                    observer.observe($el);
                "
                :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'"
                class="transition-all duration-700 ease-out"
            >
                <a href="/{{ $lang }}/blog/{{ $post->slug }}" class="group block h-full">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 h-full
                                hover:shadow-lg hover:-translate-y-1 transition-all duration-300">

                        {{-- Category Badge --}}
                        <div class="mb-4">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider
                                       bg-amber-50/50 text-amber-text border border-amber/25">
                                {{ $categoryName }}
                            </span>
                        </div>

                        {{-- Date --}}
                        @if($post->published_at)
                        <div class="flex items-center gap-2 text-xs text-muted mb-3">
                            <x-heroicon-o-calendar-days class="w-3.5 h-3.5" />
                            <time datetime="{{ $post->published_at->toDateString() }}">
                                {{ $post->published_at->format('d M Y') }}
                            </time>
                        </div>
                        @endif

                        {{-- Title --}}
                        <h3 class="font-display text-xl font-bold text-navy leading-snug mb-3 group-hover:text-amber-text transition-colors line-clamp-2">
                            {{ trans_field($post->title) }}
                        </h3>

                        {{-- Excerpt --}}
                        @if($post->excerpt)
                        <p class="text-sm text-body leading-relaxed line-clamp-3 mb-4">
                            {{ Str::limit(trans_field($post->excerpt), 100) }}
                        </p>
                        @endif

                        {{-- Bottom: Author + Read More --}}
                        <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-full bg-gradient-to-br from-amber/10 to-orange-100 flex items-center justify-center text-amber text-xs font-bold">
                                    {{ strtoupper(substr($authorName, 0, 1)) }}
                                </div>
                                <span class="text-xs font-semibold text-navy">{{ $authorName }}</span>
                            </div>

                            <x-heroicon-o-arrow-right class="w-5 h-5 text-amber-text opacity-0 group-hover:opacity-100 transform translate-x-2 group-hover:translate-x-0 transition-all duration-300" />
                        </div>

                    </div>
                </a>
            </article>
            @endforeach
        </div>

        {{-- CTA --}}
        <div class="text-center mt-12">
            <a href="/{{ $lang }}/blog/" class="group inline-flex items-center justify-center gap-2 px-8 py-4
                      bg-transparent border-2 border-amber text-amber font-semibold text-base
                      rounded-2xl hover:bg-amber/5 hover:scale-105 transition-all duration-300">
                {{ trans_field($section->content['view_all_text'] ?? null) ?: 'View All Articles' }}
                <x-heroicon-o-arrow-right class="w-5 h-5 transform group-hover:translate-x-1 transition-transform" />
            </a>
        </div>

    </div>
</section>
@endif
