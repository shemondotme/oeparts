{{-- Section: Promo Banner - Grid/Mosaic Layout with Social Proof
     content: eyebrow(ml), headline(ml), subheadline(ml), button_text(ml), button_url
     Design: Modern Grid Layout with Interactive Feature Cards & Social Proof
--}}
<section class="relative py-14 md:py-20 px-4 overflow-hidden bg-navy">
    {{-- Decorative Gradient Background --}}
    <div class="absolute inset-0 bg-gradient-to-br from-navy via-navy to-blue-900 pointer-events-none" aria-hidden="true"></div>
    <div class="absolute inset-0 bg-[radial-gradient(circle,rgba(255,255,255,0.03)_1px,transparent_1px)] bg-[size:32px_32px] pointer-events-none" aria-hidden="true"></div>

    {{-- Animated Gradient Blobs --}}
    <div class="absolute inset-0 opacity-10 pointer-events-none" aria-hidden="true">
        <div class="absolute top-10 left-1/4 w-96 h-96 bg-amber/30 rounded-full mix-blend-multiply filter blur-3xl animate-blob"></div>
        <div class="absolute bottom-10 right-1/4 w-80 h-80 bg-blue-500/30 rounded-full mix-blend-multiply filter blur-3xl animate-blob animation-delay-2000"></div>
    </div>

    <div class="relative max-w-6xl mx-auto z-10">

        {{-- Header Section --}}
        <x-section-heading
            :eyebrow="trans_field($section->content['eyebrow'] ?? null)"
            :headline="trans_field($section->content['headline'] ?? null)"
            :subheadline="trans_field($section->content['subheadline'] ?? null)"
            :dark="true"
            class="mb-8"
        />

        {{-- CTA Button --}}
        @if(!empty($section->content['button_text']) && !empty($section->content['button_url']))
        <div class="text-center mb-12">
            <a href="{{ $section->content['button_url'] }}"
               class="group btn-primary px-8 py-4 text-base">
                {{ trans_field($section->content['button_text']) }}
                <x-heroicon-o-arrow-right class="w-5 h-5 transform group-hover:translate-x-1 transition-transform" />
            </a>
        </div>
        @endif

        {{-- Feature Cards Grid --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-5">

            {{-- Card 1: Certified Workshops --}}
            <div class="group relative overflow-hidden rounded-2xl bg-white/5 backdrop-blur-sm border border-white/10 p-6 hover:bg-white/10 hover:border-amber/50 transition-all duration-300">
                <div class="w-16 h-16 rounded-xl bg-gradient-to-br from-amber to-orange-500 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform duration-300">
                    <x-heroicon-s-wrench-screwdriver class="w-8 h-8 text-navy" />
                </div>

                <h3 class="text-white text-lg font-bold mb-2">Certified Workshops</h3>
                <p class="text-white/60 text-sm leading-relaxed">Join 500+ verified workshops across Europe</p>
            </div>

            {{-- Card 2: No Setup Fees --}}
            <div class="group relative overflow-hidden rounded-2xl bg-white/5 backdrop-blur-sm border border-white/10 p-6 hover:bg-white/10 hover:border-blue-500/50 transition-all duration-300">
                <div class="w-16 h-16 rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform duration-300">
                    <x-heroicon-s-currency-dollar class="w-8 h-8 text-white" />
                </div>

                <h3 class="text-white text-lg font-bold mb-2">No Setup Fees</h3>
                <p class="text-white/60 text-sm leading-relaxed">Start free, scale as your business grows</p>
            </div>

            {{-- Card 3: 24/7 Support --}}
            <div class="group relative overflow-hidden rounded-2xl bg-white/5 backdrop-blur-sm border border-white/10 p-6 hover:bg-white/10 hover:border-purple-500/50 transition-all duration-300">
                <div class="w-16 h-16 rounded-xl bg-gradient-to-br from-purple-500 to-purple-600 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform duration-300">
                    <x-heroicon-s-chat-bubble-left-right class="w-8 h-8 text-white" />
                </div>

                <h3 class="text-white text-lg font-bold mb-2">24/7 Support</h3>
                <p class="text-white/60 text-sm leading-relaxed">Expert help available anytime, anywhere</p>
            </div>

            {{-- Card 4: Bulk Pricing --}}
            <div class="group relative overflow-hidden rounded-2xl bg-white/5 backdrop-blur-sm border border-white/10 p-6 hover:bg-white/10 hover:border-green-500/50 transition-all duration-300">
                <div class="w-16 h-16 rounded-xl bg-gradient-to-br from-green-500 to-green-600 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform duration-300">
                    <x-heroicon-s-clipboard-document-check class="w-8 h-8 text-white" />
                </div>

                <h3 class="text-white text-lg font-bold mb-2">Bulk Pricing</h3>
                <p class="text-white/60 text-sm leading-relaxed">Volume discounts up to 35% off</p>
            </div>

            {{-- Card 5: Fast Delivery --}}
            <div class="group relative overflow-hidden rounded-2xl bg-white/5 backdrop-blur-sm border border-white/10 p-6 hover:bg-white/10 hover:border-orange-500/50 transition-all duration-300">
                <div class="w-16 h-16 rounded-xl bg-gradient-to-br from-orange-500 to-orange-600 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform duration-300">
                    <x-heroicon-s-truck class="w-8 h-8 text-white" />
                </div>

                <h3 class="text-white text-lg font-bold mb-2">Fast Delivery</h3>
                <p class="text-white/60 text-sm leading-relaxed">2-3 days worldwide express shipping</p>
            </div>

            {{-- Card 6: Quality Guarantee --}}
            <div class="group relative overflow-hidden rounded-2xl bg-white/5 backdrop-blur-sm border border-white/10 p-6 hover:bg-white/10 hover:border-cyan-500/50 transition-all duration-300">
                <div class="w-16 h-16 rounded-xl bg-gradient-to-br from-cyan-500 to-cyan-600 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform duration-300">
                    <x-heroicon-s-shield-check class="w-8 h-8 text-white" />
                </div>

                <h3 class="text-white text-lg font-bold mb-2">Quality Guarantee</h3>
                <p class="text-white/60 text-sm leading-relaxed">100% certified OEM parts, ISO 9001</p>
            </div>

        </div>

        {{-- Minimal Trust Indicators --}}
        <div class="mt-12 flex flex-wrap justify-center gap-6 md:gap-10 text-white/50 text-sm">
            <div class="flex items-center gap-2">
                <x-heroicon-s-star class="w-4 h-4 text-amber" />
                <span class="font-semibold text-white/70">4.8/5 Rating</span>
            </div>
            <div class="flex items-center gap-2">
                <x-heroicon-s-users class="w-4 h-4 text-blue-400" />
                <span class="font-semibold text-white/70">2,400+ Customers</span>
            </div>
            <div class="flex items-center gap-2">
                <x-heroicon-s-lock-closed class="w-4 h-4 text-green-400" />
                <span class="font-semibold text-white/70">Secure Checkout</span>
            </div>
        </div>

    </div>
</section>
