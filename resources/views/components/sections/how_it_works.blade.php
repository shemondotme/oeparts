{{-- Section: how_it_works (Industrial Blueprint)
     content: eyebrow, headline, subheadline, steps[] — each: icon, step_number, title(ml), description(ml)
--}}
@php
    $steps = $section->content['steps'] ?? [];
    $eyebrow = trans_field($section->content['eyebrow'] ?? null);
    $headline = trans_field($section->content['headline'] ?? null);
    $subheadline = trans_field($section->content['subheadline'] ?? null);
    $sectionNumber = str_pad((int)(($section->sort_order ?? 10) / 10), 2, '0', STR_PAD_LEFT);
@endphp

@if(!empty($steps))
<section class="relative bg-paper text-ink border-b border-rule">

    <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-10 py-20 md:py-28">

        {{-- Header row --}}
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
                           text-4xl sm:text-5xl lg:text-6xl max-w-[20ch]">
                    {{ $headline }}<span class="text-amber">.</span>
                </h2>
                @endif
            </div>
            @if($subheadline)
            <div class="col-span-12 md:col-span-5 mt-6 md:mt-0 md:pl-8 md:border-l md:border-rule">
                <p class="text-base text-body leading-relaxed">
                    {{ $subheadline }}
                </p>
                <p class="mt-4 font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">
                    Protocol · {{ count($steps) }} steps · avg 2 min
                </p>
            </div>
            @endif
        </div>

        {{-- Steps: horizontal rail on desktop, stacked on mobile --}}
        @php
            $stepCount = count($steps);
            // Safelist-friendly static classes (Tailwind JIT requires literals)
            $gridClass = match(true) {
                $stepCount === 1 => 'md:grid-cols-1',
                $stepCount === 2 => 'md:grid-cols-2',
                $stepCount === 4 => 'md:grid-cols-2 lg:grid-cols-4',
                $stepCount >= 5  => 'md:grid-cols-3 lg:grid-cols-5',
                default          => 'md:grid-cols-3',
            };
        @endphp
        <ol class="grid grid-cols-1 {{ $gridClass }} border border-ink bg-ivory/40">
            @foreach($steps as $index => $step)
            @php
                $stepNum = $step['step_number'] ?? $loop->iteration;
                $numPad = str_pad((int) $stepNum, 2, '0', STR_PAD_LEFT);
                $icon = $step['icon'] ?? null;
                $isLast = $loop->last;
            @endphp

            <li class="group relative p-6 sm:p-8 lg:p-10 bg-paper flex flex-col
                       {{ !$isLast ? 'border-b md:border-b-0 md:border-r border-rule' : '' }}
                       hover:bg-ivory-alt transition-colors duration-200">

                {{-- Corner register marks --}}
                <span class="absolute top-2 left-2 w-3 h-3 border-l border-t border-rule-strong" aria-hidden="true"></span>
                <span class="absolute top-2 right-2 w-3 h-3 border-r border-t border-rule-strong" aria-hidden="true"></span>
                <span class="absolute bottom-2 left-2 w-3 h-3 border-l border-b border-rule-strong" aria-hidden="true"></span>
                <span class="absolute bottom-2 right-2 w-3 h-3 border-r border-b border-rule-strong" aria-hidden="true"></span>

                {{-- Step number + rule --}}
                <div class="flex items-baseline gap-4 mb-8">
                    <span class="font-display font-black text-ink leading-none text-5xl sm:text-6xl tracking-tight
                                 group-hover:text-amber-ink transition-colors duration-200">
                        {{ $numPad }}
                    </span>
                    <span class="flex-1 h-px bg-ink"></span>
                    <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-amber-ink">
                        § Step
                    </span>
                </div>

                {{-- Icon --}}
                @if($icon)
                <div class="mb-6 w-14 h-14 border border-ink flex items-center justify-center bg-ivory-alt
                            group-hover:bg-ink group-hover:border-ink transition-colors duration-200">
                    @switch($icon)
                        @case('magnifying-glass')         <x-heroicon-o-magnifying-glass class="w-6 h-6 text-ink group-hover:text-amber transition-colors" /> @break
                        @case('shopping-cart')            <x-heroicon-o-shopping-cart class="w-6 h-6 text-ink group-hover:text-amber transition-colors" /> @break
                        @case('cube')                     <x-heroicon-o-cube class="w-6 h-6 text-ink group-hover:text-amber transition-colors" /> @break
                        @case('clipboard-document-list')  <x-heroicon-o-clipboard-document-list class="w-6 h-6 text-ink group-hover:text-amber transition-colors" /> @break
                        @case('truck')                    <x-heroicon-o-truck class="w-6 h-6 text-ink group-hover:text-amber transition-colors" /> @break
                        @case('currency-dollar')          <x-heroicon-o-currency-dollar class="w-6 h-6 text-ink group-hover:text-amber transition-colors" /> @break
                        @case('shield-check')             <x-heroicon-o-shield-check class="w-6 h-6 text-ink group-hover:text-amber transition-colors" /> @break
                        @case('paper-airplane')           <x-heroicon-o-paper-airplane class="w-6 h-6 text-ink group-hover:text-amber transition-colors" /> @break
                        @case('chat-bubble-left')         <x-heroicon-o-chat-bubble-left-ellipsis class="w-6 h-6 text-ink group-hover:text-amber transition-colors" /> @break
                        @case('credit-card')              <x-heroicon-o-credit-card class="w-6 h-6 text-ink group-hover:text-amber transition-colors" /> @break
                        @case('user-circle')              <x-heroicon-o-user-circle class="w-6 h-6 text-ink group-hover:text-amber transition-colors" /> @break
                        @default                          <x-heroicon-o-check-circle class="w-6 h-6 text-ink group-hover:text-amber transition-colors" />
                    @endswitch
                </div>
                @endif

                {{-- Title --}}
                @if(!empty($step['title']))
                <h3 class="font-display text-xl sm:text-2xl font-bold text-ink leading-tight tracking-tight text-balance mb-3">
                    {{ trans_field($step['title']) }}
                </h3>
                @endif

                {{-- Description --}}
                @if(!empty($step['description']))
                <p class="text-sm sm:text-base text-body leading-relaxed max-w-sm">
                    {{ trans_field($step['description']) }}
                </p>
                @endif

                {{-- Amber underscore + connector arrow --}}
                <div class="mt-auto pt-6 flex items-center gap-3">
                    <div class="h-[3px] w-10 bg-amber"></div>
                    @if(!$isLast)
                    <span class="hidden md:inline-flex items-center gap-1 font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted/60">
                        NEXT
                        <x-heroicon-s-arrow-long-right class="w-3.5 h-3.5" />
                    </span>
                    @else
                    <span class="inline-flex items-center gap-1 font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-amber-ink">
                        <x-heroicon-s-check-circle class="w-3.5 h-3.5" />
                        COMPLETE
                    </span>
                    @endif
                </div>
            </li>
            @endforeach
        </ol>
    </div>
</section>
@endif
