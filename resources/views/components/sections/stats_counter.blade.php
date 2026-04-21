{{-- Section: stats_counter
     content: eyebrow(ml), headline(ml), subheadline(ml), items[] — each: key (settings key in stats_counter group),
              suffix, label(ml), cta_text(ml), cta_url
     Design: Clean white cards with amber numbers, subtle icons, soft shadows
--}}
@if(settings('stats_counter.show_section', true))
@php $items = $section->content['items'] ?? []; @endphp

<section class="bg-[#FAFAF8] py-14 md:py-20 px-4 relative overflow-hidden">

    {{-- Decorative background blobs --}}
    <div class="absolute inset-0 opacity-10 pointer-events-none" aria-hidden="true">
        <div class="absolute top-10 right-10 w-96 h-96 bg-amber rounded-full filter blur-3xl"></div>
        <div class="absolute bottom-10 left-10 w-72 h-72 bg-blue-500 rounded-full filter blur-3xl"></div>
    </div>

    <div class="max-w-6xl mx-auto relative z-10">

        {{-- Section Heading Component --}}
        @if(!empty($section->content['headline']))
        <x-section-heading
            :eyebrow="trans_field($section->content['eyebrow'] ?? null)"
            :headline="trans_field($section->content['headline'] ?? null)"
            :subheadline="trans_field($section->content['subheadline'] ?? null)"
            :accentBar="true"
            class="mb-12"
        />
        @endif

        {{-- Stats Grid - 2 cols mobile, 4 cols desktop --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-5">
            @foreach($items as $index => $item)
            @php
                $rawValue = settings('stats_counter.' . ($item['key'] ?? ''), 0);
                $key      = $item['key'] ?? '';
                $suffix   = $item['suffix'] ?? '';
                $delay    = $index * 100;

                // Handle Rating separately to preserve decimals (4.9★)
                if ($key === 'rating') {
                    $displayValue = $rawValue . $suffix;
                } else {
                    $numValue = (int) $rawValue;
                    // Format numbers for clean UI (1M+, 2.5K+, etc.)
                    if ($numValue >= 1000000) {
                        $displayValue = '1M+';
                    } elseif ($numValue >= 1000) {
                        $displayValue = number_format($numValue / 1000, 1) . 'K+';
                    } else {
                        $displayValue = $numValue . $suffix;
                    }
                }

                // Icon mapping
                $iconMap = [
                    'customers_count' => 'users',
                    'parts_count'     => 'cube',
                    'countries_count' => 'globe-europe-africa',
                    'rating'          => 'star',
                    'orders_count'    => 'shopping-cart',
                    'brands_count'    => 'building-office',
                    'categories_count'=> 'rectangle-stack',
                ];
                $iconName = $iconMap[$item['key'] ?? ''] ?? 'chart-bar';
            @endphp

            <div
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
            >
                <div
                    class="group relative bg-white rounded-2xl p-4 md:p-8 h-full
                           border-2 border-gray-100
                           shadow-md shadow-amber/5 hover:shadow-2xl hover:shadow-amber/15
                           transform transition-all duration-500 ease-out motion-reduce:transition-none motion-reduce:transform-none
                           hover:-translate-y-2 hover:border-amber/30
                           :class='shown ? \"opacity-100 translate-y-0\" : \"opacity-0 translate-y-6\"'"
                >
                {{-- Faded icon in background (top-right) --}}
                <div class="absolute top-2 right-3 md:top-4 md:right-5 opacity-10 group-hover:opacity-20 transition-opacity duration-500 pointer-events-none">
                    @switch($iconName)
                        @case('cube')
                            <x-heroicon-s-cube class="w-8 h-8 md:w-14 md:h-14 text-amber" />
                            @break
                        @case('building-office')
                            <x-heroicon-s-building-office class="w-8 h-8 md:w-14 md:h-14 text-amber" />
                            @break
                        @case('rectangle-stack')
                            <x-heroicon-s-rectangle-stack class="w-8 h-8 md:w-14 md:h-14 text-amber" />
                            @break
                        @case('globe-europe-africa')
                            <x-heroicon-s-globe-europe-africa class="w-8 h-8 md:w-14 md:h-14 text-amber" />
                            @break
                        @case('users')
                            <x-heroicon-s-users class="w-8 h-8 md:w-14 md:h-14 text-amber" />
                            @break
                        @case('star')
                            <x-heroicon-s-star class="w-8 h-8 md:w-14 md:h-14 text-amber" />
                            @break
                        @case('shopping-cart')
                            <x-heroicon-s-shopping-cart class="w-8 h-8 md:w-14 md:h-14 text-amber" />
                            @break
                        @default
                            <x-heroicon-s-chart-bar class="w-8 h-8 md:w-14 md:h-14 text-amber" />
                    @endswitch
                </div>

                {{-- Content --}}
                <div class="relative z-10">
                    {{-- Static formatted value --}}
                    <div class="relative mb-1 md:mb-2">
                        <div class="font-display text-2xl md:text-4xl font-black text-amber-text tracking-tight">
                            {{ $displayValue }}
                        </div>
                    </div>

                    {{-- Label --}}
                    <div class="text-navy font-bold text-xs md:text-sm uppercase tracking-wide">
                        {{ trans_field($item['label'] ?? null) }}
                    </div>
                </div>
            </div>
            </div>
            @endforeach
        </div>

        {{-- CTA Button --}}
        @if(!empty($section->content['cta_text']) && !empty($section->content['cta_url']))
        <div class="mt-12 text-center">
            <x-button variant="secondary" href="{{ $section->content['cta_url'] }}" size="lg">
                {{ trans_field($section->content['cta_text']) }}
                <x-heroicon-o-arrow-right class="w-5 h-5 transform group-hover:translate-x-1 transition-transform" aria-hidden="true" />
            </x-button>
        </div>
        @endif

    </div>
</section>
@endif
