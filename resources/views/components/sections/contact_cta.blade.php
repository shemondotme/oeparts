{{-- Section: contact_cta
     content: headline, subheadline, button_text, phone
     Design mockup hardcoded values:
     - eyebrow: "GET IN TOUCH"
     - headline: "Need Help? Talk to an Expert."
     - subheadline: "Our parts specialists are available Monday–Friday, 9:00–18:00 CET."
--}}
<section class="relative py-14 md:py-20 px-4 overflow-hidden">

    {{-- Solid navy background for better contrast --}}
    <div class="absolute inset-0 bg-navy"></div>

    {{-- Decorative elements — subtle --}}
    <div class="absolute inset-0 opacity-20 pointer-events-none">
        <div class="absolute top-0 left-1/4 w-96 h-96 bg-amber/10 rounded-full filter blur-3xl"></div>
        <div class="absolute bottom-0 right-1/4 w-96 h-96 bg-blue-500/10 rounded-full filter blur-3xl"></div>
    </div>

    <div class="relative z-10 max-w-4xl mx-auto text-center">

        <x-section-heading
            :eyebrow="trans_field($section->content['eyebrow'] ?? null)"
            :headline="trans_field($section->content['headline'] ?? null)"
            :subheadline="trans_field($section->content['subheadline'] ?? null)"
            :accentBar="false"
            :dark="true"
            class="mb-12"
        />

        {{-- CTA Buttons — Clean design with solid amber button --}}
        <div class="flex flex-col sm:flex-row items-center justify-center gap-6">

            {{-- Email CTA --}}
            <a
                href="/{{ app()->getLocale() }}/contact"
                class="group btn-primary px-8 py-4 text-base"
            >
                <x-heroicon-o-envelope class="w-5 h-5 transform group-hover:scale-110 transition-transform" />
                <span>Contact Us</span>
                <x-heroicon-o-arrow-right class="w-5 h-5 transform group-hover:translate-x-1 transition-transform" />
            </a>

            {{-- Phone CTA --}}
            @php $phone = settings('general.site_phone', '+370 600 00000'); @endphp
            @if($phone)
            <a
                href="tel:{{ preg_replace('/\s+/', '', $phone) }}"
                class="group btn-outline px-8 py-4 text-base"
            >
                <x-heroicon-o-phone class="w-5 h-5 transform group-hover:scale-110 transition-transform" />
                <span class="font-mono text-base">{{ $phone }}</span>
            </a>
            @endif
        </div>

        {{-- Trust text — Improved contrast --}}
        <p class="mt-10 text-sm text-white/80 flex items-center justify-center gap-2">
            <x-heroicon-s-clock class="w-4 h-4 text-amber" />
            <span>Response within 24 hours</span>
            <span class="text-white/40">•</span>
            <x-heroicon-s-check-circle class="w-4 h-4 text-amber" />
            <span>Expert support</span>
        </p>
    </div>
</section>
