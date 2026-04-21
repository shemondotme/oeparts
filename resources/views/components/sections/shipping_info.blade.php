{{-- Section: shipping_info
     content: eyebrow(ml), headline(ml), subheadline(ml),
              features[] — each: icon, value(ml), label(ml)
              carriers[] — e.g. ['DHL','DPD','GLS','FedEx','UPS']
--}}
@php
    $carriers = $section->content['carriers'] ?? [];
    $features = $section->content['features'] ?? [];
@endphp

<section class="bg-gradient-to-b from-white via-blue-50/20 to-white py-14 md:py-20 px-4 relative overflow-hidden">

    {{-- Decorative background --}}
    <div class="absolute inset-0 opacity-20 pointer-events-none">
        <div class="absolute top-20 right-0 w-96 h-96 bg-amber/10 rounded-full filter blur-3xl"></div>
        <div class="absolute bottom-0 left-0 w-72 h-72 bg-blue-500/10 rounded-full filter blur-3xl"></div>
    </div>

    <div class="max-w-6xl mx-auto relative z-10">

        <x-section-heading
            :eyebrow="trans_field($section->content['eyebrow'] ?? null)"
            :headline="trans_field($section->content['headline'] ?? null)"
            :subheadline="trans_field($section->content['subheadline'] ?? null)"
            :accentBar="true"
            class="mb-12"
        />

        {{-- 4 Feature Tiles --}}
        @if(!empty($features))
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            @foreach($features as $index => $feature)
            @php
                $icon  = $feature['icon'] ?? 'check-circle';
                $value = trans_field($feature['value'] ?? null);
                $label = trans_field($feature['label'] ?? null);
                $delay = $index * 100;
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
                    class="group relative bg-white rounded-2xl border border-gray-100 shadow-md shadow-amber/5 h-full
                           hover:shadow-2xl hover:shadow-amber/15
                           p-6 text-center flex flex-col items-center gap-4
                           transform transition-all duration-500 ease-out
                           hover:-translate-y-2 hover:border-amber/30
                           :class='shown ? \"opacity-100 translate-y-0\" : \"opacity-0 translate-y-8\"'"
                >
                {{-- Gradient border glow --}}
                <div class="absolute inset-0 rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none
                            bg-gradient-to-br from-amber/10 via-transparent to-transparent"></div>

                {{-- Icon — Centered without skewing --}}
                <div class="relative w-16 h-16 rounded-2xl bg-amber/5 flex items-center justify-center mx-auto">
                    @switch($icon)
                        @case('truck')
                            <x-heroicon-o-truck class="w-8 h-8 text-amber" />
                            @break
                        @case('globe-europe-africa')
                        @case('globe-europe')
                            <x-heroicon-o-globe-europe-africa class="w-8 h-8 text-amber" />
                            @break
                        @case('clock')
                            <x-heroicon-o-clock class="w-8 h-8 text-amber" />
                            @break
                        @case('gift')
                            <x-heroicon-o-gift class="w-8 h-8 text-amber" />
                            @break
                        @case('arrow-path')
                            <x-heroicon-o-arrow-path class="w-8 h-8 text-amber" />
                            @break
                        @case('map-pin')
                            <x-heroicon-o-map-pin class="w-8 h-8 text-amber" />
                            @break
                        @case('shield-check')
                            <x-heroicon-o-shield-check class="w-8 h-8 text-amber" />
                            @break
                        @default
                            <x-heroicon-o-check-circle class="w-8 h-8 text-amber" />
                    @endswitch
                </div>

                {{-- Value + Label — Solid color for readability --}}
                <div class="relative z-10">
                    @if($value)
                    <p class="font-display text-3xl font-extrabold text-navy leading-tight">
                        {{ $value }}
                    </p>
                    @endif
                    @if($label)
                    <p class="text-muted text-sm mt-1.5 font-medium">{{ $label }}</p>
                    @endif
                </div>

                {{-- Decorative bottom bar --}}
                <div class="absolute bottom-4 left-1/2 -translate-x-1/2 w-12 h-1 bg-gradient-to-r from-amber/0 via-amber/50 to-amber/0
                            rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
            </div>
            </div>
            @endforeach
        </div>
        @endif

        {{-- Carrier row — Redesigned with brand-style cards --}}
        @if(!empty($carriers))
        <div class="mt-16">
            <div class="relative bg-gradient-to-br from-white via-gray-50/50 to-white rounded-3xl p-8 md:p-10 border-2 border-gray-100 shadow-xl shadow-amber/5 overflow-hidden">
                {{-- Decorative corner accent --}}
                <div class="absolute top-0 right-0 w-40 h-40 bg-gradient-to-bl from-amber/5 to-transparent rounded-bl-full pointer-events-none"></div>
                <div class="absolute bottom-0 left-0 w-32 h-32 bg-gradient-to-tr from-navy/5 to-transparent rounded-tr-full pointer-events-none"></div>

                {{-- Section label --}}
                <div class="relative text-center mb-8">
                    <div class="inline-flex items-center gap-2 px-4 py-1.5 bg-amber/10 rounded-full mb-4">
                        <x-heroicon-o-truck class="w-4 h-4 text-amber" />
                        <span class="text-xs font-bold tracking-widest uppercase text-amber-text">Trusted Shipping Partners</span>
                    </div>
                    <p class="text-muted text-sm max-w-md mx-auto">
                        Your order ships via Europe's most reliable carriers — tracked from warehouse to doorstep.
                    </p>
                </div>

                {{-- Carrier cards grid --}}
                <div class="relative flex flex-wrap items-center justify-center gap-3 md:gap-4">
                    @foreach($carriers as $carrier)
                    @php
                        // Brand-inspired color accents per carrier
                        $carrierStyles = [
                            'DHL'   => ['accent' => 'from-red-500 to-red-600', 'bg' => 'from-red-50/80 to-white', 'border' => 'border-red-200', 'hoverBorder' => 'hover:border-red-300', 'hoverBg' => 'hover:from-red-100/80', 'text' => 'text-red-700', 'iconBg' => 'bg-red-50', 'iconText' => 'text-red-600'],
                            'DPD'   => ['accent' => 'from-blue-600 to-blue-700', 'bg' => 'from-blue-50/80 to-white', 'border' => 'border-blue-200', 'hoverBorder' => 'hover:border-blue-300', 'hoverBg' => 'hover:from-blue-100/80', 'text' => 'text-blue-700', 'iconBg' => 'bg-blue-50', 'iconText' => 'text-blue-600'],
                            'GLS'   => ['accent' => 'from-green-500 to-green-600', 'bg' => 'from-green-50/80 to-white', 'border' => 'border-green-200', 'hoverBorder' => 'hover:border-green-300', 'hoverBg' => 'hover:from-green-100/80', 'text' => 'text-green-700', 'iconBg' => 'bg-green-50', 'iconText' => 'text-green-600'],
                            'FedEx' => ['accent' => 'from-purple-600 to-indigo-600', 'bg' => 'from-purple-50/80 to-white', 'border' => 'border-purple-200', 'hoverBorder' => 'hover:border-purple-300', 'hoverBg' => 'hover:from-purple-100/80', 'text' => 'text-purple-700', 'iconBg' => 'bg-purple-50', 'iconText' => 'text-purple-600'],
                            'UPS'   => ['accent' => 'from-amber-600 to-amber-700', 'bg' => 'from-amber-50/80 to-white', 'border' => 'border-amber-200', 'hoverBorder' => 'hover:border-amber-300', 'hoverBg' => 'hover:from-amber-100/80', 'text' => 'text-amber-700', 'iconBg' => 'bg-amber-50', 'iconText' => 'text-amber-600'],
                        ];
                        $style = $carrierStyles[$carrier] ?? ['accent' => 'from-navy to-navy/90', 'bg' => 'from-gray-50/80 to-white', 'border' => 'border-gray-200', 'hoverBorder' => 'hover:border-gray-300', 'hoverBg' => 'hover:from-gray-100/80', 'text' => 'text-navy', 'iconBg' => 'bg-gray-50', 'iconText' => 'text-navy'];
                    @endphp
                    <div
                        class="group flex items-center gap-3 px-5 py-4 md:px-6 md:py-5 h-full
                               bg-gradient-to-br {{ $style['bg'] }}
                               border-2 {{ $style['border'] }} {{ $style['hoverBorder'] }} {{ $style['hoverBg'] }}
                               rounded-2xl
                               shadow-sm hover:shadow-lg hover:shadow-amber/10
                               transform transition-all duration-300
                               hover:-translate-y-1">
                        {{-- Brand color icon box --}}
                        <div class="w-10 h-10 rounded-xl {{ $style['iconBg'] }} flex items-center justify-center shrink-0 transition-all duration-300 group-hover:scale-110">
                            <x-heroicon-o-truck class="w-5 h-5 {{ $style['iconText'] }} transition-colors duration-300 group-hover:{{ $style['text'] }}" />
                        </div>

                        {{-- Carrier name --}}
                        <div>
                            <span class="font-display text-lg font-black {{ $style['text'] }} tracking-tight transition-colors duration-300">
                                {{ $carrier }}
                            </span>
                            <p class="text-xs font-semibold text-muted uppercase tracking-wide -mt-0.5">Carrier</p>
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- Bottom trust line --}}
                <div class="relative mt-8 pt-6 border-t border-gray-100 flex flex-wrap items-center justify-center gap-6">
                    <span class="flex items-center gap-2 text-xs font-semibold text-muted">
                        <x-heroicon-s-shield-check class="w-4 h-4 text-emerald-500" />
                        Fully Insured
                    </span>
                    <span class="flex items-center gap-2 text-xs font-semibold text-muted">
                        <x-heroicon-s-map-pin class="w-4 h-4 text-blue-500" />
                        Real-time Tracking
                    </span>
                    <span class="flex items-center gap-2 text-xs font-semibold text-muted">
                        <x-heroicon-s-arrow-path class="w-4 h-4 text-amber" />
                        Free Returns
                    </span>
                </div>
            </div>
        </div>
        @endif

    </div>
</section>
