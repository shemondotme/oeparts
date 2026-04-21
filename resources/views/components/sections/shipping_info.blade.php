{{-- Section: shipping_info (Industrial Blueprint)
     content: eyebrow, headline(ml), subheadline(ml),
              features[] — each: icon, value(ml), label(ml)
              carriers[] — e.g. ['DHL','DPD','GLS','FedEx','UPS']
--}}
@php
    $carriers = $section->content['carriers'] ?? [];
    $features = $section->content['features'] ?? [];
    $eyebrow = trans_field($section->content['eyebrow'] ?? null);
    $headline = trans_field($section->content['headline'] ?? null);
    $subheadline = trans_field($section->content['subheadline'] ?? null);
    $sectionNumber = str_pad((int)(($section->sort_order ?? 10) / 10), 2, '0', STR_PAD_LEFT);
@endphp

<section class="relative bg-ivory text-ink border-b border-rule overflow-hidden">
    <div class="absolute inset-0 bg-grid-ivory-fine bg-grid-md opacity-50 pointer-events-none" aria-hidden="true"></div>

    <div class="relative max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-10 py-20 md:py-28">

        {{-- Header --}}
        <div class="grid grid-cols-12 gap-x-4 sm:gap-x-6 lg:gap-x-8 items-end pb-8 mb-12 border-b border-ink">
            <div class="col-span-12 md:col-span-7">
                @if($eyebrow)
                <div class="flex items-center gap-4 mb-6">
                    <span class="w-10 h-[3px] bg-amber inline-block"></span>
                    <span class="bp-spec text-amber-ink">§ {{ $eyebrow }}</span>
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
            </div>
            @endif
        </div>

        {{-- Feature tiles --}}
        @if(!empty($features))
        <div class="grid grid-cols-2 lg:grid-cols-4 border border-ink bg-paper mb-16">
            @foreach($features as $index => $feature)
            @php
                $icon  = $feature['icon'] ?? 'check-circle';
                $value = trans_field($feature['value'] ?? null);
                $label = trans_field($feature['label'] ?? null);
                $num = str_pad($index + 1, 2, '0', STR_PAD_LEFT);
            @endphp

            <div class="relative p-6 sm:p-8
                        {{ !$loop->last ? 'border-r border-rule' : '' }}
                        {{ $index < count($features) - 2 ? 'border-b border-rule lg:border-b-0' : '' }}
                        {{ $index % 2 === 1 ? 'border-r-0 lg:border-r' : '' }}">

                {{-- Row number + icon --}}
                <div class="flex items-center justify-between mb-6">
                    <span class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted">
                        № {{ $num }}
                    </span>
                    <div class="w-8 h-8 border border-rule flex items-center justify-center shrink-0">
                        @switch($icon)
                            @case('truck')                <x-heroicon-o-truck class="w-4 h-4 text-ink" /> @break
                            @case('globe-europe-africa')
                            @case('globe-europe')          <x-heroicon-o-globe-europe-africa class="w-4 h-4 text-ink" /> @break
                            @case('clock')                <x-heroicon-o-clock class="w-4 h-4 text-ink" /> @break
                            @case('gift')                 <x-heroicon-o-gift class="w-4 h-4 text-ink" /> @break
                            @case('arrow-path')           <x-heroicon-o-arrow-path class="w-4 h-4 text-ink" /> @break
                            @case('map-pin')              <x-heroicon-o-map-pin class="w-4 h-4 text-ink" /> @break
                            @case('shield-check')         <x-heroicon-o-shield-check class="w-4 h-4 text-ink" /> @break
                            @default                      <x-heroicon-o-check-circle class="w-4 h-4 text-ink" />
                        @endswitch
                    </div>
                </div>

                {{-- Value --}}
                @if($value)
                <p class="font-mono font-medium text-ink tabular-nums leading-none tracking-tight
                          text-4xl sm:text-5xl">
                    {{ $value }}
                </p>
                @endif

                {{-- Label --}}
                @if($label)
                <p class="mt-4 bp-spec text-ink-muted">{{ $label }}</p>
                @endif

                {{-- Amber underscore --}}
                <div class="mt-5 h-[2px] w-8 bg-amber"></div>
            </div>
            @endforeach
        </div>
        @endif

        {{-- Carriers ledger --}}
        @if(!empty($carriers))
        <div class="border border-ink bg-paper">
            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-ink bg-ivory-alt">
                <div class="flex items-center gap-3">
                    <span class="w-8 h-[3px] bg-amber inline-block"></span>
                    <span class="font-mono text-[11px] font-bold tracking-[0.22em] uppercase text-ink">
                        § Trusted Carriers
                    </span>
                </div>
                <span class="hidden sm:inline font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">
                    EU · Tracked · Insured
                </span>
            </div>

            {{-- Carrier row --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 divide-x divide-rule">
                @foreach($carriers as $index => $carrier)
                <div class="p-6 flex flex-col items-center justify-center gap-3 text-center
                            {{ ($index >= 3 && count($carriers) > 3) ? 'md:border-t-0 border-t sm:border-t-0 border-rule' : '' }}">
                    <div class="w-10 h-10 border border-rule flex items-center justify-center">
                        <x-heroicon-o-truck class="w-5 h-5 text-ink" />
                    </div>
                    <span class="font-display text-lg font-bold text-ink tracking-tight">
                        {{ $carrier }}
                    </span>
                    <span class="font-mono text-[9px] tracking-[0.22em] uppercase text-ink-muted">
                        Carrier
                    </span>
                </div>
                @endforeach
            </div>

            {{-- Trust footer --}}
            <div class="flex flex-wrap items-center justify-center gap-x-8 gap-y-3 px-6 py-5 border-t border-rule">
                <span class="inline-flex items-center gap-2 font-mono text-[10px] tracking-[0.22em] uppercase text-ink">
                    <x-heroicon-s-shield-check class="w-3.5 h-3.5 text-amber-ink" />
                    Fully Insured
                </span>
                <span class="inline-flex items-center gap-2 font-mono text-[10px] tracking-[0.22em] uppercase text-ink">
                    <x-heroicon-s-map-pin class="w-3.5 h-3.5 text-amber-ink" />
                    Real-time Tracking
                </span>
                <span class="inline-flex items-center gap-2 font-mono text-[10px] tracking-[0.22em] uppercase text-ink">
                    <x-heroicon-s-arrow-path class="w-3.5 h-3.5 text-amber-ink" />
                    Free Returns
                </span>
            </div>
        </div>
        @endif

    </div>
</section>
