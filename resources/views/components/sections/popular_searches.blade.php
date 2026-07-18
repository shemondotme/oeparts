{{-- Section: popular_searches (Industrial Blueprint)
     content: eyebrow, headline, subheadline, search_cta_text(ml)
     Displays top 5 searched OEM numbers from search_logs (last 30 days)
--}}
@php
    try {
        $limit = (int) settings('search.popular_display_limit', 5);
        $cacheKey = 'popular_searches_' . app()->getLocale() . '_' . $limit;
        $popular = \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addHours((int) settings('search.cache_ttl_hours', 6)), function () use ($limit) {
            return \DB::table('search_logs')
                ->select('search_query', \DB::raw('COUNT(*) as hits'))
                ->where('created_at', '>=', now()->subDays((int) settings('search.popular_days_window', 30)))
                ->where('result_count', '>', 0)
                ->groupBy('search_query')
                ->orderByDesc('hits')
                ->limit($limit)
                ->get();
        });
        $maxHits = $popular->max('hits') ?? 1;
        $daysWindow = (int) settings('search.popular_days_window', 30);
        $cacheTtlHours = (int) settings('search.cache_ttl_hours', 6);
    } catch (\Exception $e) {
        $popular = collect();
        $maxHits = 1;
        $daysWindow = (int) settings('search.popular_days_window', 30);
        $cacheTtlHours = (int) settings('search.cache_ttl_hours', 6);
    }

    $eyebrow = trans_field($section->content['eyebrow'] ?? null);
    $headline = trans_field($section->content['headline'] ?? null);
    $subheadline = trans_field($section->content['subheadline'] ?? null);
    $searchCtaText = trans_field($section->content['search_cta_text'] ?? null) ?: 'Search by OEM Number';
    $sectionNumber = str_pad((int)(($section->sort_order ?? 10) / 10), 2, '0', STR_PAD_LEFT);
@endphp

@if($popular->isNotEmpty())
<section class="relative bg-paper text-ink border-b border-rule">
    <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-10 pt-16 md:pt-24 pb-12 md:pb-16">

        {{-- Header --}}
        <div class="grid grid-cols-12 gap-x-4 sm:gap-x-6 lg:gap-x-8 items-end pb-8 mb-12 border-b border-ink">
            <div class="col-span-12 md:col-span-7">
                @if($eyebrow)
                <div class="flex items-center gap-4 mb-6">
                    <span class="w-10 h-[3px] bg-amber inline-block"></span>
                    <span class="bp-spec text-amber-ink">{{ $eyebrow }}</span>
                </div>
                @endif
                @if($headline)
                <h2 class="font-display font-extrabold text-ink leading-[0.95] tracking-[-0.03em]
                           text-4xl sm:text-5xl lg:text-6xl max-w-[18ch]">
                    {{ $headline }}<span class="text-amber">.</span>
                </h2>
                @endif
            </div>
            @if($subheadline)
            <div class="col-span-12 md:col-span-5 mt-6 md:mt-0 md:pl-8 md:border-l md:border-rule">
                <p class="text-base text-body leading-relaxed">
                    {{ $subheadline }}
                </p>
                <p class="mt-4 bp-spec-mono">
                    Log window · {{ $daysWindow }} days · {{ $popular->sum('hits') }} hits
                </p>
            </div>
            @endif
        </div>

        {{-- Leaderboard: table-like ledger --}}
        <div class="border border-ink bg-paper">
            {{-- Header row --}}
            <div class="grid grid-cols-12 gap-4 px-6 py-3 border-b border-ink bg-ivory-alt
                        font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink">
                <span class="col-span-1">{{ __('Rank') }}</span>
                <span class="col-span-5 sm:col-span-4">{{ __('OEM · Number') }}</span>
                <span class="hidden sm:block col-span-5 text-ink-muted">{{ __('Frequency') }}</span>
                <span class="col-span-6 sm:col-span-2 text-right">{{ __('Hits') }}</span>
            </div>

            <ol aria-label="{{ __('Top searched OEM part numbers') }}">
                @foreach($popular as $item)
                @php
                    $rank = $loop->iteration;
                    $rankPad = str_pad($rank, 2, '0', STR_PAD_LEFT);
                    $isTop3 = $rank <= 3;
                    $isLast = $loop->last;
                    $progressWidth = max(($item->hits / $maxHits) * 100, 3);
                @endphp

                <li class="{{ !$isLast ? 'border-b border-rule' : '' }}">
                <a href="{{ url('/'.app()->getLocale().'/parts/'.$item->search_query) }}"
                   class="group grid grid-cols-12 gap-4 px-6 py-5 items-center
                          hover:bg-ivory focus-visible:bg-ivory
                          focus-visible:outline focus-visible:outline-2 focus-visible:outline-amber focus-visible:outline-offset-[-2px]
                          transition-colors">

                    {{-- Rank --}}
                    <div class="col-span-1 flex items-center gap-3">
                        <span class="font-mono text-base sm:text-lg font-bold tabular-nums text-ink">
                            {{ $rankPad }}
                        </span>
                    </div>

                    {{-- OEM number + Hot badge --}}
                    <div class="col-span-8 sm:col-span-4 flex items-center gap-3 min-w-0">
                        <span class="font-mono text-base sm:text-lg font-semibold tabular-nums text-ink
                                     group-hover:text-amber-ink transition-colors">
                            {{ $item->search_query }}
                        </span>
                        @if($isTop3)
                        <span class="shrink-0 inline-flex items-center gap-1.5 px-2 py-0.5
                                     border border-amber bg-amber/10 text-amber-ink
                                     font-mono text-[9px] font-bold tracking-[0.2em] uppercase">
                            {{ __('Hot') }}
                        </span>
                        @endif
                    </div>

                    {{-- Progress bar (desktop only) --}}
                    <div class="hidden sm:flex col-span-5 items-center">
                        <div class="flex-1 h-1.5 bg-rule relative overflow-hidden">
                            <div class="absolute inset-y-0 left-0 bg-ink group-hover:bg-amber transition-colors"
                                 style="width: {{ $progressWidth }}%"></div>
                        </div>
                    </div>

                    {{-- Hits --}}
                    <div class="col-span-3 sm:col-span-2 text-right flex items-center justify-end gap-2">
                        <x-heroicon-o-magnifying-glass class="w-4 h-4 text-ink-muted" />
                        <span class="font-mono text-sm font-bold tabular-nums text-ink">
                            {{ number_format($item->hits) }}
                        </span>
                    </div>
                </a>
                </li>
                @endforeach
            </ol>
        </div>

        {{-- Footer / CTA --}}
        <div class="mt-8 flex flex-wrap items-center justify-between gap-4">
            <span class="bp-spec-mono">
                Ledger updated · {{ now()->format('Y·m·d') }} · cached {{ $cacheTtlHours }}h
            </span>
            <a href="{{ route('frontend.search.console', ['lang' => app()->getLocale()]) }}" class="bp-btn-primary">
                {{ $searchCtaText }}
                <x-heroicon-s-magnifying-glass class="w-5 h-5" />
            </a>
        </div>
    </div>
</section>
@endif
