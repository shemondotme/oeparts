{{-- Section: banner (Industrial Blueprint)
     content: eyebrow(ml), headline(ml), subheadline(ml), button_text(ml), button_url
     6 feature cards with trust indicators.
--}}
@php
    $eyebrow = trans_field($section->content['eyebrow'] ?? null);
    $headline = trans_field($section->content['headline'] ?? null);
    $subheadline = trans_field($section->content['subheadline'] ?? null);
    $buttonText = trans_field($section->content['button_text'] ?? null) ?: __('Open a Workshop Account');
    $buttonUrl = $section->content['button_url'] ?: null;

    $features = [
        ['icon' => 'wrench-screwdriver', 'title' => 'Workshop Pricing',     'desc' => 'Volume tiers up to 35% off retail — automatic on every invoice once you qualify.'],
        ['icon' => 'document-text',      'title' => 'Net-30 Terms',         'desc' => 'Order on account, pay monthly. Credit lines from ' . settings('store.currency_symbol', '€') . '2K to ' . settings('store.currency_symbol', '€') . '100K based on history.'],
        ['icon' => 'clipboard-check',    'title' => 'Bulk RFQ Desk',        'desc' => 'Quote 50+ OEM numbers in one request. Answers within 4 working hours.'],
        ['icon' => 'chat-bubble',        'title' => 'Dedicated B2B Support', 'desc' => 'Named account manager, direct line, DE · EN · FR · LT · ES.'],
        ['icon' => 'truck',              'title' => 'Scheduled Delivery',   'desc' => 'Daily courier runs across the EU. Morning-order, next-day-arrival on stocked SKUs.'],
        ['icon' => 'shield-check',       'title' => 'Certified Genuine',    'desc' => 'Only OEM-authorised distributors. ISO 9001 supply chain, traceable lot numbers.'],
    ];
    $sectionNumber = str_pad((int)(($section->sort_order ?? 10) / 10), 2, '0', STR_PAD_LEFT);
@endphp

<section class="relative bg-ink text-ivory border-b border-rule-dark overflow-hidden">
    <div class="absolute inset-0 bg-grid-navy bg-grid-lg opacity-60 pointer-events-none" aria-hidden="true"></div>

    <div class="relative max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-10 py-20 md:py-28">

        {{-- Header --}}
        <div class="grid grid-cols-12 gap-x-4 sm:gap-x-6 lg:gap-x-8 items-end pb-8 mb-12 border-b border-white/20">
            <div class="col-span-12 md:col-span-7">
                @if($eyebrow)
                <div class="flex items-center gap-4 mb-6">
                    <span class="w-10 h-[3px] bg-amber inline-block"></span>
                    <span class="font-mono text-[10px] tracking-[0.28em] uppercase text-amber">{{ $eyebrow }}</span>
                </div>
                @endif
                @if($headline)
                @php $headlineClean = rtrim($headline, '.'); @endphp
                <h2 class="font-display font-extrabold text-ivory leading-[0.95] tracking-[-0.03em]
                           text-4xl sm:text-5xl lg:text-6xl max-w-[20ch]">
                    {{ $headlineClean }}<span class="text-amber">.</span>
                </h2>
                @endif
            </div>
            <div class="col-span-12 md:col-span-5 mt-6 md:mt-0 md:pl-8 md:border-l md:border-white/20">
                @if($subheadline)
                <p class="text-base text-ivory/75 leading-relaxed">
                    {{ $subheadline }}
                </p>
                @endif
                @if($buttonText)
                    @if($buttonUrl)
                        <a href="{{ $buttonUrl }}" class="mt-6 bp-btn-amber">
                            {{ $buttonText }}
                            <x-heroicon-s-arrow-long-right class="w-5 h-5" />
                        </a>
                    @else
                        <button type="button"
                                @click="$dispatch('open-auth-modal', { tab: 'register', role: 'b2b' })"
                                class="mt-6 bp-btn-amber">
                            {{ $buttonText }}
                            <x-heroicon-s-arrow-long-right class="w-5 h-5" />
                        </button>
                    @endif
                @endif
            </div>
        </div>

        {{-- Feature ledger --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 border border-white/20">
            @foreach($features as $index => $f)
            @php
                $num = str_pad($index + 1, 2, '0', STR_PAD_LEFT);
            @endphp
            <div class="relative p-6 sm:p-8 border-white/20
                        {{ !$loop->last ? 'border-b lg:border-b' : '' }}
                        {{ ($index + 1) % 3 !== 0 ? 'lg:border-r' : '' }}
                        {{ ($index + 1) % 2 !== 0 ? 'sm:border-r lg:border-r' : '' }}
                        {{ $index < count($features) - 3 ? 'lg:border-b' : 'lg:border-b-0' }}">

                {{-- Row num + icon --}}
                <div class="flex items-center justify-between mb-6">
                    <span class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ivory/50">
                        № {{ $num }}
                    </span>
                    <div class="w-9 h-9 border border-white/30 flex items-center justify-center">
                        @switch($f['icon'])
                            @case('wrench-screwdriver') <x-heroicon-o-wrench-screwdriver class="w-4 h-4 text-amber" /> @break
                            @case('document-text')      <x-heroicon-o-document-text class="w-4 h-4 text-amber" /> @break
                            @case('chat-bubble')        <x-heroicon-o-chat-bubble-left-right class="w-4 h-4 text-amber" /> @break
                            @case('clipboard-check')    <x-heroicon-o-clipboard-document-check class="w-4 h-4 text-amber" /> @break
                            @case('truck')              <x-heroicon-o-truck class="w-4 h-4 text-amber" /> @break
                            @case('shield-check')       <x-heroicon-o-shield-check class="w-4 h-4 text-amber" /> @break
                            @default                    <x-heroicon-o-check-circle class="w-4 h-4 text-amber" />
                        @endswitch
                    </div>
                </div>

                {{-- Title --}}
                <h3 class="font-display text-xl font-bold text-ivory leading-tight tracking-tight mb-3 text-balance">
                    {{ $f['title'] }}
                </h3>

                {{-- Description --}}
                <p class="text-sm text-ivory/70 leading-relaxed">
                    {{ $f['desc'] }}
                </p>

                {{-- Amber underscore --}}
                <div class="mt-5 h-[2px] w-8 bg-amber"></div>
            </div>
            @endforeach
        </div>

        {{-- B2B Trust indicators --}}
        <div class="mt-12 pt-8 border-t border-white/20 flex flex-wrap items-center justify-center gap-x-10 gap-y-3">
            <span class="inline-flex items-center gap-2 font-mono text-[10px] tracking-[0.22em] uppercase text-ivory/70">
                <x-heroicon-s-building-storefront class="w-3.5 h-3.5 text-amber" />
                500+ Active Workshops
            </span>
            <span class="inline-flex items-center gap-2 font-mono text-[10px] tracking-[0.22em] uppercase text-ivory/70">
                <x-heroicon-s-globe-europe-africa class="w-3.5 h-3.5 text-amber" />
                27 EU Countries
            </span>
            <span class="inline-flex items-center gap-2 font-mono text-[10px] tracking-[0.22em] uppercase text-ivory/70">
                <x-heroicon-s-check-badge class="w-3.5 h-3.5 text-amber" />
                ISO 9001 Supply Chain
            </span>
            <span class="inline-flex items-center gap-2 font-mono text-[10px] tracking-[0.22em] uppercase text-ivory/70">
                <x-heroicon-s-clock class="w-3.5 h-3.5 text-amber" />
                4h RFQ Response
            </span>
        </div>
    </div>
</section>
