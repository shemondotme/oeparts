{{-- Section: featured_brands
     content: headline(ml), subheadline(ml)
     $sectionData['manufacturers'] is injected by SectionRendererService
     Design: Logo-Centric Minimal - Large logos, grayscale→color on hover, clean layout
--}}
@php
    $manufacturers = $sectionData['manufacturers'] ?? collect();
    $lang = app()->getLocale();

    // Get top 6 brands by product count for featured section
    $featuredBrands = $manufacturers->take(6);

    // Preload product counts to avoid N+1 queries
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

    // Generate alphabet letters
    $alphabet = range('A', 'Z');
@endphp

@if($manufacturers->isNotEmpty())
<section class="bg-gradient-to-b from-gray-50 via-amber-50/20 to-gray-50 py-14 md:py-20 px-4 relative overflow-hidden">

    {{-- Decorative background elements --}}
    <div class="absolute inset-0 opacity-5" aria-hidden="true">
        <div class="absolute top-10 right-10 w-96 h-96 bg-amber rounded-full filter blur-3xl"></div>
        <div class="absolute bottom-10 left-10 w-96 h-96 bg-blue-500 rounded-full filter blur-3xl"></div>
    </div>

    <div class="max-w-6xl mx-auto relative z-10">

        {{-- Section Heading --}}
        <x-section-heading
            :eyebrow="trans_field($section->content['eyebrow'] ?? null)"
            :headline="trans_field($section->content['headline'] ?? null)"
            :subheadline="trans_field($section->content['subheadline'] ?? null)"
            :accentBar="true"
            class="mb-12"
        />

        {{-- Featured Brands Grid - Logo-Centric Minimal Design --}}
        <div class="mb-12">
            <div class="flex items-center gap-3 mb-8">
                <div class="w-1 h-10 bg-gradient-to-b from-amber to-orange-500 rounded-full"></div>
                <h3 class="font-display text-xl font-bold text-navy uppercase tracking-wide">
                    Popular Brands
                </h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($featuredBrands as $brand)
                @php
                    // Use preloaded count to avoid N+1 queries
                    $partsCount = $brandProductCounts[$brand->id] ?? 0;
                    $formattedCount = $partsCount >= 1000
                        ? number_format($partsCount / 1000, 1) . 'K+'
                        : $partsCount . '+';
                @endphp

                <div class="h-full">
                <a
                    href="/{{ $lang }}/brand/{{ $brand->slug }}"
                    class="group relative overflow-hidden rounded-2xl bg-white border-2 border-gray-100 h-full
                           shadow-md shadow-amber/5 hover:shadow-2xl hover:shadow-amber/15
                           focus-visible:border-amber focus-visible:ring-4 focus-visible:ring-amber/20
                           transform transition-all duration-500 hover:-translate-y-2 hover:border-amber/30
                           flex flex-col"
                >
                    <div class="p-6 flex flex-col gap-4 flex-1">
                        {{-- Brand Logo - Balanced size --}}
                        <div class="mb-6 flex items-center justify-center min-h-[140px]">
                            @if($brand->logo && ($brand->logo->file_path || $brand->logo->file_url))
                                @php
                                    $logoSrc = $brand->logo->file_path
                                        ? asset('storage/' . ltrim(preg_replace('#^storage/#', '', $brand->logo->file_path), '/'))
                                        : $brand->logo->file_url;
                                @endphp
                                <img
                                    src="{{ $logoSrc }}"
                                    alt="{{ trans_field($brand->name) }}"
                                    loading="lazy"
                                    class="max-h-[120px] w-auto object-contain transition-all duration-500
                                           grayscale group-hover:grayscale-0 group-hover:scale-110"
                                >
                            @else
                                {{-- Fallback: Brand initials in circle --}}
                                <div class="w-28 h-28 rounded-2xl bg-gradient-to-br from-gray-100 to-gray-200
                                            flex items-center justify-center group-hover:from-amber/20 group-hover:to-orange-50/20
                                            transition-all duration-500">
                                    <span class="font-display text-5xl font-black text-gray-400 group-hover:text-amber-text
                                                 transition-all duration-500">
                                        {{ strtoupper(substr(trans_field($brand->name), 0, 2)) }}
                                    </span>
                                </div>
                            @endif
                        </div>

                        {{-- Divider --}}
                        <div class="w-16 h-0.5 bg-gradient-to-r from-gray-200 to-transparent mx-auto mb-5
                                    group-hover:from-amber group-hover:to-orange-500 transition-all duration-500"></div>

                        {{-- Brand Name --}}
                        <h4 class="font-display text-xl font-bold text-center text-navy mb-3
                                   group-hover:text-amber-text transition-colors duration-300">
                            {{ trans_field($brand->name) }}
                        </h4>

                        {{-- Parts Count with Icon --}}
                        <div class="flex items-center justify-center gap-2.5 mb-5">
                            <div class="w-8 h-8 rounded-lg bg-amber/10
                                        flex items-center justify-center
                                        group-hover:bg-amber group-hover:scale-110 transition-all duration-300">
                                <x-heroicon-o-cube class="w-4 h-4 text-amber group-hover:text-white transition-colors" aria-hidden="true" />
                            </div>
                            <div class="text-center">
                                <p class="text-lg font-bold text-amber-text">
                                    {{ $formattedCount }}
                                </p>
                                <p class="text-xs text-muted font-medium uppercase tracking-wide">
                                    {{ $partsCount === 1 ? 'Part' : 'Parts' }}
                                </p>
                            </div>
                        </div>

                        {{-- Arrow Icon (shows on hover) --}}
                        <div class="flex items-center justify-center gap-2 text-amber-text
                                    opacity-0 group-hover:opacity-100
                                    transform translate-y-2 group-hover:translate-y-0
                                    transition-all duration-300">
                            <span class="text-sm font-semibold">Browse Parts</span>
                            <x-heroicon-o-arrow-right class="w-4 h-4" aria-hidden="true" />
                        </div>

                        {{-- Decorative corner accent --}}
                        <div class="absolute top-0 right-0 w-24 h-24 bg-gradient-to-bl
                                    from-amber/5 to-transparent rounded-bl-full
                                    opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                    </div>
                </a>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Browse by Letter --}}
        <div class="mb-10">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-1 h-8 bg-gradient-to-b from-navy to-blue-500 rounded-full"></div>
                <h3 class="font-display text-lg font-bold text-navy uppercase tracking-wide">
                    Browse by Letter
                </h3>
            </div>

            <div class="flex flex-wrap gap-2">
                @foreach($alphabet as $letter)
                @php
                    // Check if any brand starts with this letter
                    $hasBrands = $manufacturers->contains(function($brand) use ($letter) {
                        return strtoupper(substr(trans_field($brand->name), 0, 1)) === $letter;
                    });
                @endphp
                <a
                    href="/{{ $lang }}/brand?letter={{ $letter }}"
                    class="group w-11 h-11 sm:w-12 sm:h-12 rounded-xl flex items-center justify-center
                           {{ $hasBrands
                               ? 'bg-white border-2 border-gray-200 text-navy font-bold hover:bg-amber hover:border-amber hover:text-white hover:scale-110 focus-visible:ring-4 focus-visible:ring-amber/20'
                               : 'bg-gray-100 border-2 border-gray-100 text-gray-300 cursor-not-allowed' }}"
                    @if(!$hasBrands) tabindex="-1" aria-hidden="true" @endif
                    title="{{ $hasBrands ? 'Browse brands starting with ' . $letter : 'No brands' }}"
                >
                    <span class="text-sm sm:text-base font-bold transition-all duration-300">
                        {{ $letter }}
                    </span>
                </a>
                @endforeach
                <a
                    href="/{{ $lang }}/brand"
                    class="group min-w-[3rem] h-10 sm:h-12 px-4 rounded-xl flex items-center justify-center
                           bg-gradient-to-r from-navy to-blue-600 text-white font-bold
                           hover:from-amber hover:to-orange-500 hover:scale-105
                           shadow-md hover:shadow-lg transition-all duration-300"
                    title="View all brands"
                >
                    <span class="text-xs sm:text-sm">ALL</span>
                </a>
            </div>
        </div>

        {{-- CTA Button --}}
        <div class="text-center">
            <x-button variant="secondary" href="/{{ $lang }}/brand/" size="lg">
                {{ trans_field($section->content['view_all_text'] ?? null) ?: 'View All Brands' }}
                <x-heroicon-o-arrow-right class="w-5 h-5 transform group-hover:translate-x-1 transition-transform" aria-hidden="true" />
            </x-button>
        </div>

    </div>
</section>
@endif
