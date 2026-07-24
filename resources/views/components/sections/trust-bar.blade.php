{{-- Section: trust_bar (Industrial Blueprint)
     content: items[] — each: icon, text(ml)
--}}
@php $items = $section->content['items'] ?? []; @endphp

@if(!empty($items))
@php
    $itemCount = count($items);
    $tbGrid = match(true) {
        $itemCount === 1 => 'md:grid-cols-1',
        $itemCount === 2 => 'md:grid-cols-2',
        $itemCount === 3 => 'md:grid-cols-3',
        $itemCount === 4 => 'md:grid-cols-4',
        default          => 'md:grid-cols-5',
    };
@endphp
<section class="relative bg-ivory-alt border-b border-rule" aria-label="Trust signals">
    <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-10">
        {{-- Mobile: stack 1-per-row so labels are never truncated; md+: N across. --}}
        <ul class="grid grid-cols-1 {{ $tbGrid }} divide-y md:divide-y-0 md:divide-x divide-rule">
            @foreach($items as $index => $item)
            @php
                $icon = $item['icon'] ?? 'check-circle';
                $text = trans_field($item['text'] ?? null);
                $num = str_pad($index + 1, 2, '0', STR_PAD_LEFT);
            @endphp
            <li class="flex items-center gap-4 px-5 py-5 sm:px-6">

                <div class="w-9 h-9 border border-ink flex items-center justify-center shrink-0 bg-paper">
                    @switch($icon)
                        @case('truck')         <x-heroicon-o-truck class="w-4 h-4 text-ink" /> @break
                        @case('shield-check')  <x-heroicon-o-shield-check class="w-4 h-4 text-ink" /> @break
                        @case('arrow-path')    <x-heroicon-o-arrow-path class="w-4 h-4 text-ink" /> @break
                        @case('lock-closed')   <x-heroicon-o-lock-closed class="w-4 h-4 text-ink" /> @break
                        @case('check-circle')  <x-heroicon-o-check-circle class="w-4 h-4 text-ink" /> @break
                        @default               <x-heroicon-o-check-circle class="w-4 h-4 text-ink" />
                    @endswitch
                </div>

                <div class="min-w-0">
                    <p class="font-mono text-[11px] font-bold tracking-[0.14em] uppercase text-ink leading-tight">
                        {{ $text }}
                    </p>
                </div>
            </li>
            @endforeach
        </ul>
    </div>
</section>
@endif
