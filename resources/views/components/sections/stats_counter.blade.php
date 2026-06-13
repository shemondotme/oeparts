{{-- Section: stats_counter (Industrial Blueprint)
     content: eyebrow(ml), headline(ml), subheadline(ml), items[] — each: key, suffix, label(ml), cta_text(ml), cta_url
--}}
@if(settings('stats_counter.show_section', true))
@php
    $items = $section->content['items'] ?? [];
    $eyebrow = trans_field($section->content['eyebrow'] ?? null);
    $headline = trans_field($section->content['headline'] ?? null);
    $subheadline = trans_field($section->content['subheadline'] ?? null);
    $ctaText = trans_field($section->content['cta_text'] ?? null);
    $ctaUrl = $section->content['cta_url'] ?? null;

    $iconMap = [
        'customers_count' => 'users',
        'parts_count'     => 'cube',
        'countries_count' => 'globe-europe-africa',
        'rating'          => 'star',
        'orders_count'    => 'shopping-cart',
        'brands_count'    => 'building-office',
        'categories_count'=> 'rectangle-stack',
    ];
    $sectionNumber = str_pad((int)(($section->sort_order ?? 10) / 10), 2, '0', STR_PAD_LEFT);
@endphp

<section class="relative bg-ivory text-ink border-b border-rule overflow-hidden">
    <div class="absolute inset-0 bg-grid-ivory-fine bg-grid-md opacity-60 pointer-events-none" aria-hidden="true"></div>

    <div class="relative max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-10 py-20 md:py-28">

        {{-- Section header --}}
        @if($headline || $eyebrow)
        <div class="grid grid-cols-12 gap-x-4 sm:gap-x-6 lg:gap-x-8 items-end pb-8 border-b border-ink mb-0">
            <div class="col-span-12 md:col-span-8">
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
            <div class="col-span-12 md:col-span-4 mt-6 md:mt-0">
                <p class="text-base text-body leading-relaxed max-w-sm md:ml-auto">
                    {{ $subheadline }}
                </p>
            </div>
            @endif
        </div>
        @endif

        {{-- Stats grid: flat borders, monospace numbers --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 border-x border-b border-ink bg-paper">
            @foreach($items as $index => $item)
            @php
                $rawValue = settings('stats_counter.' . ($item['key'] ?? ''), 0);
                $key      = $item['key'] ?? '';
                $suffix   = $item['suffix'] ?? '';

                if ($key === 'rating') {
                    $displayValue = $rawValue . $suffix;
                } else {
                    $numValue = (int) $rawValue;
                    if ($numValue >= 1000000) {
                        $displayValue = '1M+';
                    } elseif ($numValue >= 1000) {
                        $displayValue = number_format($numValue / 1000, 1) . 'K+';
                    } else {
                        $displayValue = $numValue . $suffix;
                    }
                }
                $iconName = $iconMap[$key] ?? 'chart-bar';
                $num = str_pad($index + 1, 2, '0', STR_PAD_LEFT);
            @endphp

            <div class="relative p-6 sm:p-8 lg:p-10
                        {{ $index !== count($items) - 1 ? 'border-r border-rule' : '' }}
                        {{ $index < count($items) - 2 ? 'border-b border-rule lg:border-b-0' : '' }}
                        {{ $index % 2 === 1 ? 'border-r-0 lg:border-r' : '' }}"
                 aria-label="{{ $displayValue }} {{ trans_field($item['label'] ?? null) }}">

                {{-- Header row: label + row number + icon --}}
                <div class="flex items-start justify-between mb-6">
                    <div class="flex items-center gap-3">
                        <span class="font-mono text-[10px] font-bold tracking-[0.22em] text-ink-muted">{{ $num }}</span>
                        <span class="w-6 h-px bg-rule-strong"></span>
                    </div>
                    <div class="w-8 h-8 border border-rule flex items-center justify-center shrink-0 opacity-70">
                        @switch($iconName)
                            @case('cube')                <x-heroicon-o-cube class="w-4 h-4 text-ink" /> @break
                            @case('building-office')     <x-heroicon-o-building-office class="w-4 h-4 text-ink" /> @break
                            @case('rectangle-stack')     <x-heroicon-o-rectangle-stack class="w-4 h-4 text-ink" /> @break
                            @case('globe-europe-africa') <x-heroicon-o-globe-europe-africa class="w-4 h-4 text-ink" /> @break
                            @case('users')               <x-heroicon-o-users class="w-4 h-4 text-ink" /> @break
                            @case('star')                <x-heroicon-o-star class="w-4 h-4 text-ink" /> @break
                            @case('shopping-cart')       <x-heroicon-o-shopping-cart class="w-4 h-4 text-ink" /> @break
                            @default                     <x-heroicon-o-chart-bar class="w-4 h-4 text-ink" />
                        @endswitch
                    </div>
                </div>

                {{-- Massive mono number --}}
                <p class="font-mono font-medium text-ink tabular-nums leading-none tracking-tight
                          text-5xl sm:text-6xl lg:text-[5rem]">
                    {{ $displayValue }}
                </p>

                {{-- Label --}}
                <p class="mt-4 bp-spec text-ink-muted">
                    {{ trans_field($item['label'] ?? null) }}
                </p>

                {{-- Amber tick accent --}}
                <div class="mt-5 flex items-center gap-2">
                    <span class="h-px w-8 bg-amber"></span>
                    <span class="font-mono text-[10px] tracking-[0.2em] uppercase text-amber-ink">{{ __('Live') }}</span>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Optional CTA --}}
        @if($ctaText && $ctaUrl)
        <div class="mt-10 flex items-center justify-between pt-6 border-t border-rule">
            <span class="hidden sm:inline bp-spec-mono">
                {{ __('Source · Verified · EU') }}
            </span>
            <a href="{{ $ctaUrl }}" class="bp-btn-outline">
                {{ $ctaText }}
                <x-heroicon-s-arrow-long-right class="w-5 h-5" />
            </a>
        </div>
        @endif
    </div>
</section>
@endif
