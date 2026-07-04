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
<section id="how-it-works" class="relative bg-paper text-ink border-b border-rule">

    <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-10 py-16 md:py-24">

        {{-- Header — stacked variant (breaks the split-header rhythm) --}}
        <x-section-header
            variant="stacked"
            :eyebrow="$eyebrow"
            :headline="$headline"
            :subheadline="$subheadline"
            :meta="'Protocol · ' . count($steps) . ' steps'" />

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

                {{-- Step number + rule --}}
                <div class="flex items-baseline gap-4 mb-8">
                    <span class="font-display font-black text-ink leading-none text-5xl sm:text-6xl tracking-tight
                                 group-hover:text-amber-ink transition-colors duration-200">
                        {{ $numPad }}
                    </span>
                    <span class="flex-1 h-px bg-ink"></span>
                    <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-amber-ink">
                        {{ __('Step') }}
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

                {{-- Amber underscore --}}
                <div class="mt-auto pt-6">
                    <div class="h-[3px] w-10 bg-amber"></div>
                </div>
            </li>
            @endforeach
        </ol>
    </div>
</section>
@endif
