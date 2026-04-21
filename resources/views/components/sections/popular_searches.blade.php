{{-- Section: popular_searches
      content: eyebrow, headline, subheadline, search_cta_text(ml)
      Design: Tiered List Leaderboard - Progress bars, rank, search count
      Displays the top 5 searched OEM numbers from search_logs (last 30 days)
--}}
@php
    try {
        $limit = 5;
        $cacheKey = 'popular_searches_' . app()->getLocale() . '_' . $limit;
        $popular = \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addHours(6), function () use ($limit) {
            return \DB::table('search_logs')
                ->select('search_query', \DB::raw('COUNT(*) as hits'))
                ->where('created_at', '>=', now()->subDays(30))
                ->where('result_count', '>', 0)
                ->groupBy('search_query')
                ->orderByDesc('hits')
                ->limit($limit)
                ->get();
        });

        // Calculate max hits for progress bar scaling
        $maxHits = $popular->max('hits') ?? 1;
    } catch (\Exception $e) {
        $popular = collect();
        $maxHits = 1;
    }
@endphp

@if($popular->isNotEmpty())
<section class="bg-gradient-to-b from-gray-50 via-orange-50/10 to-gray-50 py-14 md:py-20 px-4 relative overflow-hidden">

    {{-- Decorative background --}}
    <div class="absolute inset-0 opacity-20" aria-hidden="true">
        <div class="absolute top-20 right-0 w-96 h-96 bg-amber/10 rounded-full filter blur-3xl"></div>
        <div class="absolute bottom-0 left-0 w-72 h-72 bg-blue-500/10 rounded-full filter blur-3xl"></div>
    </div>

    <div class="max-w-5xl mx-auto relative z-10">

        <x-section-heading
            :eyebrow="trans_field($section->content['eyebrow'] ?? null)"
            :headline="trans_field($section->content['headline'] ?? null)"
            :subheadline="trans_field($section->content['subheadline'] ?? null)"
            :accentBar="true"
            class="mb-12"
        />

        {{-- Framed container — outer border + rounded corners --}}
        <div class="relative bg-white/60 backdrop-blur-sm border-2 border-gray-200 rounded-3xl p-4 md:p-6 shadow-sm">

        <ol class="space-y-3" aria-label="Top searched OEM part numbers">
            @foreach($popular as $item)
            @php
                $rank = $loop->iteration;
                $isTop3 = $rank <= 3;
                $isLast = $loop->last;
                $progressWidth = ($item->hits / $maxHits) * 100;
                $delay = ($rank - 1) * 100;
            @endphp

            <li
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
                class="opacity-0 translate-y-4 transition-all duration-500 ease-out motion-reduce:transition-none motion-reduce:transform-none"
                :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'"
            >
            <a
                href="/{{ app()->getLocale() }}/parts/{{ $item->search_query }}"
                class="group relative overflow-hidden rounded-2xl
                       bg-gradient-to-b from-white to-gray-50/50
                       border-0
                       focus-visible:border-2 focus-visible:border-amber focus-visible:ring-4 focus-visible:ring-amber/20
                       shadow-sm hover:shadow-xl hover:shadow-amber/10
                       transform transition-all duration-300 motion-reduce:transition-none motion-reduce:transform-none
                       hover:-translate-y-1"
            >
                <div class="flex items-center gap-4 sm:gap-6 px-6 py-5 relative z-10">

                    {{-- Rank Badge — amber fill on hover --}}
                    <div
                        class="relative flex items-center justify-center w-14 h-14 rounded-2xl shrink-0
                               bg-gradient-to-b from-gray-50 to-gray-100 text-muted
                               group-hover:from-amber group-hover:to-orange-500 group-hover:text-white
                               group-hover:scale-105
                               transition-all duration-300"
                    >
                        <span class="text-xl font-black">{{ $rank }}</span>
                    </div>

                    {{-- OEM Number & Progress Bar --}}
                    <div class="flex-1 min-w-0">
                        {{-- OEM Number Row --}}
                        <div class="flex items-center gap-3 mb-2">
                            <span class="font-mono text-xl sm:text-2xl font-bold text-navy group-hover:text-amber transition-colors duration-300 relative">
                                {{ $item->search_query }}
                                {{-- Underline on hover --}}
                                <span class="absolute -bottom-0.5 left-0 w-0 h-0.5 bg-gradient-to-r from-amber to-orange-500
                                            group-hover:w-full transition-all duration-500 ease-out rounded-full"></span>
                            </span>
                            @if($isTop3)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-amber/15 text-amber-text rounded-full text-xs font-bold uppercase tracking-wide
                                            group-hover:scale-110 transition-transform duration-300">
                                    <span class="relative flex h-1.5 w-1.5 mr-1">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber/50"></span>
                                        <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-amber"></span>
                                    </span>
                                    <span>🔥</span> Hot
                                </span>
                            @endif
                        </div>

                        {{-- Progress Bar & Search Count --}}
                        <div class="flex items-center gap-4">
                            {{-- Progress Bar with glow on hover --}}
                            <div class="flex-1 h-2.5 bg-gray-200 rounded-full overflow-hidden">
                                <div
                                    class="h-full rounded-full bg-gradient-to-r from-amber to-orange-500 transition-all duration-700 ease-out relative overflow-hidden
                                           group-hover:shadow-md group-hover:shadow-amber/30"
                                    style="width: {{ $progressWidth }}%"
                                >
                                    {{-- Shimmer effect on hover --}}
                                    <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/30 to-transparent
                                                -translate-x-full group-hover:translate-x-full transition-transform duration-1000 ease-out"></div>
                                </div>
                            </div>
                            {{-- Search Count --}}
                            <div class="flex items-center gap-2 shrink-0">
                                <x-heroicon-o-magnifying-glass class="w-4 h-4 text-muted transition-all duration-300 group-hover:scale-110 group-hover:text-amber" aria-hidden="true" />
                                <span class="text-sm font-bold text-navy">{{ number_format($item->hits) }}</span>
                                <span class="text-xs text-muted">searches</span>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
            @if(!$isLast)
            <div class="border-t border-gray-100 mx-6"></div>
            @endif
            </li>
            @endforeach
        </ol>

        </div>{{-- end framed container --}}

        {{-- CTA Button --}}
        <div class="mt-12 text-center">
            <x-button variant="secondary" size="lg" href="/{{ app()->getLocale() }}/">
                {{ trans_field($section->content['search_cta_text'] ?? null) ?: 'Search by OEM Number' }}
                <x-heroicon-o-magnifying-glass class="w-4 h-4 transform group-hover:scale-110 transition-transform" />
            </x-button>
        </div>

    </div>
</section>
@endif
