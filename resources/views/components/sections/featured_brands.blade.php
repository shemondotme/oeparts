{{-- Section: featured_brands (Industrial Blueprint)
     content: eyebrow, headline(ml), subheadline(ml), view_all_text(ml)
     $sectionData['manufacturers'] injected by SectionRendererService
--}}
@php
    $manufacturers = $sectionData['manufacturers'] ?? collect();
    $lang = app()->getLocale();
    $featuredBrands = $manufacturers->take(8);

    $brandProductCounts = [];
    if ($featuredBrands->isNotEmpty()) {
        $brandIds = $featuredBrands->pluck('id')->toArray();
        $brandProductCounts = \App\Models\Product::whereIn('manufacturer_id', $brandIds)
            ->where('is_active', true)
            ->groupBy('manufacturer_id')
            ->selectRaw('manufacturer_id, COUNT(*) as count')
            ->pluck('count', 'manufacturer_id')
            ->toArray();
    }

    $alphabet = range('A', 'Z');
    $eyebrow = trans_field($section->content['eyebrow'] ?? null);
    $headline = trans_field($section->content['headline'] ?? null);
    $subheadline = trans_field($section->content['subheadline'] ?? null);
    $viewAllText = trans_field($section->content['view_all_text'] ?? null) ?: 'View All Brands';
    $sectionNumber = str_pad((int)(($section->sort_order ?? 10) / 10), 2, '0', STR_PAD_LEFT);
@endphp

@if($manufacturers->isNotEmpty())
<section class="relative bg-ivory text-ink border-b border-rule overflow-hidden">
    <div class="absolute inset-0 bg-grid-ivory bg-grid-lg opacity-50 pointer-events-none" aria-hidden="true"></div>

    <div class="relative max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-10 py-16 md:py-24">

        {{-- Header --}}
        <x-section-header
            :eyebrow="$eyebrow"
            :headline="$headline"
            :subheadline="$subheadline"
            :meta="'Index · ' . $manufacturers->count() . ' manufacturers · verified'" />

        {{-- Brand grid: 2/3/4 column ledger --}}
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 border border-ink bg-paper">
            @foreach($featuredBrands as $index => $brand)
            @php
                $partsCount = $brandProductCounts[$brand->id] ?? 0;
                $formattedCount = $partsCount >= 1000
                    ? number_format($partsCount / 1000, 1) . 'K+'
                    : $partsCount;
                $rowNum = str_pad($index + 1, 2, '0', STR_PAD_LEFT);
                $isLastRow = $index >= (count($featuredBrands) - (count($featuredBrands) % 4 ?: 4));
                $brandName = trans_field($brand->name);
            @endphp

            <a href="{{ url('/'.$lang.'/brand/'.$brand->slug) }}"
               class="group relative block p-5 sm:p-6
                      border-r border-b border-rule
                      hover:bg-ivory focus-visible:bg-ivory
                      focus-visible:outline focus-visible:outline-2 focus-visible:outline-amber focus-visible:outline-offset-[-2px]
                      transition-colors">

                {{-- Top meta row: index + part count --}}
                <div class="flex items-center justify-between mb-6">
                    <span class="bp-spec-mono">
                        № {{ $rowNum }}
                    </span>
                    <span class="font-mono text-[11px] tabular-nums text-ink-muted">
                        {{ $formattedCount }}&nbsp;<span class="uppercase tracking-wider">{{ __('pcs') }}</span>
                    </span>
                </div>

                {{-- Logo plate --}}
                <div class="aspect-[3/2] border border-rule bg-ivory-alt flex items-center justify-center p-4 mb-5
                            group-hover:bg-paper group-hover:border-ink transition-colors">
                    @if($brand->logo && ($brand->logo->file_path || $brand->logo->file_url))
                        @php
                            $logoSrc = $brand->logo->file_path
                                ? asset('storage/' . ltrim(preg_replace('#^storage/#', '', $brand->logo->file_path), '/'))
                                : $brand->logo->file_url;
                        @endphp
                        <img src="{{ $logoSrc }}"
                             alt="{{ $brandName }}"
                             loading="lazy"
                             class="max-h-[90px] w-auto object-contain
                                    grayscale opacity-85 group-hover:grayscale-0 group-hover:opacity-100
                                    transition-all duration-300">
                    @else
                        <span class="font-display text-4xl font-black text-ink-muted group-hover:text-ink transition-colors">
                            {{ strtoupper(substr($brandName, 0, 2)) }}
                        </span>
                    @endif
                </div>

                {{-- Brand name + arrow --}}
                <div class="flex items-end justify-between gap-2">
                    <h3 class="font-display text-base sm:text-lg font-bold text-ink leading-tight tracking-tight">
                        {{ $brandName }}
                    </h3>
                    <x-heroicon-s-arrow-up-right class="w-4 h-4 text-ink-muted group-hover:text-amber-ink transition-colors shrink-0 mb-1" />
                </div>

                {{-- Amber underscore on hover --}}
                <div class="mt-3 h-px w-0 bg-amber group-hover:w-full transition-all duration-500"></div>
            </a>
            @endforeach
        </div>

        {{-- Alphabet index + CTA --}}
        <div class="mt-12 pt-8 border-t border-ink">
            <div class="flex items-baseline gap-4 mb-6">
                <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-amber-ink">{{ __('Index by letter') }}</span>
                <span class="flex-1 h-px bg-rule"></span>
            </div>

            <div class="flex flex-wrap gap-1.5">
                @foreach($alphabet as $letter)
                @php
                    $hasBrands = $manufacturers->contains(function($brand) use ($letter) {
                        return strtoupper(substr(trans_field($brand->name), 0, 1)) === $letter;
                    });
                @endphp
                <a href="{{ url('/'.$lang.'/brands').'?letter='.$letter }}"
                   class="w-10 h-10 font-mono text-sm font-bold inline-flex items-center justify-center
                          border border-rule tabular-nums
                          {{ $hasBrands
                              ? 'text-ink hover:bg-ink hover:text-ivory hover:border-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-amber focus-visible:outline-offset-2'
                              : 'text-rule-strong cursor-not-allowed pointer-events-none' }}
                          transition-colors"
                   @if(!$hasBrands) tabindex="-1" aria-hidden="true" @endif
                   title="{{ $hasBrands ? 'Browse brands starting with ' . $letter : 'No brands' }}">
                    {{ $letter }}
                </a>
                @endforeach
                <a href="{{ url('/'.$lang.'/brands') }}"
                   class="h-10 px-4 font-mono text-xs font-bold inline-flex items-center gap-2
                          border border-ink bg-ink text-ivory hover:bg-amber hover:border-amber hover:text-ink
                          transition-colors uppercase tracking-[0.22em]">
                    All · Index
                    <x-heroicon-s-arrow-long-right class="w-4 h-4" />
                </a>
            </div>

            <div class="mt-10 flex flex-wrap items-center justify-between gap-4">
                <span class="bp-spec-mono">
                    {{ __('Source · OEM manufacturer catalogues · EU') }}
                </span>
                <a href="{{ url('/'.$lang.'/brands') }}" class="bp-btn-primary">
                    {{ $viewAllText }}
                    <x-heroicon-s-arrow-long-right class="w-5 h-5" />
                </a>
            </div>
        </div>
    </div>
</section>
@endif
