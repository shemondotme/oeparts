{{-- Section: trust_bar
     content: items[] — each: icon (heroicon name), text(ml)
     Design: Simple centered icons in rounded squares, no skewing
--}}
@php $items = $section->content['items'] ?? []; @endphp

<section class="bg-gradient-to-b from-amber-50/50 via-orange-50/30 to-amber-50/50 py-8 px-4 relative overflow-hidden">

    <div class="w-full px-4 relative z-10">
        <ul class="flex flex-wrap justify-center gap-4 md:gap-6">
            @foreach($items as $index => $item)
            @php
                $delay = $index * 100;
            @endphp

            <li
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
                class="group flex items-center gap-3 px-5 py-3.5
                       bg-white/80 backdrop-blur-sm
                       rounded-2xl border border-gray-100
                       shadow-sm hover:shadow-lg hover:shadow-amber/10
                       transform transition-all duration-500 ease-out
                       hover:-translate-y-1 hover:border-amber/30
                       focus-within:border-amber focus-within:ring-2 focus-within:ring-amber/20
                       :class='shown ? \"opacity-100 translate-y-0\" : \"opacity-0 translate-y-4\"'"
                role="listitem"
                aria-label="{{ trans_field($item['text'] ?? null) }}"
            >
                {{-- Icon container - centered, no skewing --}}
                <div class="relative w-11 h-11 rounded-xl bg-amber/5 flex items-center justify-center shrink-0">
                    @switch($item['icon'] ?? '')
                        @case('truck')
                            <x-heroicon-o-truck class="w-6 h-6 text-amber" />
                            @break
                        @case('shield-check')
                            <x-heroicon-o-shield-check class="w-6 h-6 text-amber" />
                            @break
                        @case('arrow-path')
                            <x-heroicon-o-arrow-path class="w-6 h-6 text-amber" />
                            @break
                        @case('lock-closed')
                            <x-heroicon-o-lock-closed class="w-6 h-6 text-amber" />
                            @break
                        @case('check-circle')
                            <x-heroicon-o-check-circle class="w-6 h-6 text-amber" />
                            @break
                        @default
                            <x-heroicon-o-check-circle class="w-6 h-6 text-amber" />
                    @endswitch
                </div>

                {{-- Text --}}
                <span class="text-sm text-body font-semibold group-hover:text-navy transition-colors duration-300">
                    {{ trans_field($item['text'] ?? null) }}
                </span>
            </li>
            @endforeach
        </ul>
    </div>
</section>
