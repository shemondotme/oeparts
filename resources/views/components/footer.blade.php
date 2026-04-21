@php
    $lang      = app()->getLocale();
    $siteName  = settings('general.site_name', 'OEMHub');
    $tagline   = settings('general.site_tagline', 'The central hub for genuine OEM auto parts in Europe. Fast search, fair prices, verified sellers.');
    $phone     = settings('contact.phone', '');
    $email     = settings('contact.email', '');
    $facebook  = settings('contact.facebook_url', '');
    $linkedin  = settings('contact.linkedin_url', '');
    $year      = date('Y');
    // Language switch URL — preserves current page
    $langSwitchUrl = function($code) {
        $route = request()->route();
        if (!$route || !$route->getName()) {
            return "/{$code}/";
        }
        $params = $route->parameters();
        $params['lang'] = $code;
        try {
            return route($route->getName(), $params);
        } catch (\Exception $e) {
            $path = request()->path();
            $newPath = preg_replace('#^(en|de|lt|fr|es)(/|$)#', $code . '$2', $path);
            return '/' . $newPath;
        }
    };
@endphp

<footer class="relative bg-gradient-to-b from-navy via-navy to-blue-950 text-white mt-16 overflow-hidden">

    {{-- Decorative background elements --}}
    <div class="absolute inset-0 opacity-20 pointer-events-none">
        <div class="absolute top-0 right-0 w-96 h-96 bg-amber/10 rounded-full filter blur-3xl"></div>
        <div class="absolute bottom-0 left-0 w-96 h-96 bg-blue-500/10 rounded-full filter blur-3xl"></div>
    </div>

    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 py-16">

        {{-- Main Footer Grid --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-10 mb-12">

            {{-- Brand column --}}
            <div>
                {{-- Text Logo --}}
                <a href="/{{ $lang }}/" class="flex items-center gap-2.5 mb-5 group w-fit">
                    <div class="shrink-0 w-9 h-9 rounded-xl bg-gradient-to-br from-amber to-orange-500 flex items-center justify-center shadow-lg shadow-amber/20 group-hover:shadow-amber/40 group-hover:scale-105 transition-all duration-300">
                        <x-heroicon-s-wrench-screwdriver class="w-4 h-4 text-navy" />
                    </div>
                    <p class="font-display font-extrabold text-2xl tracking-tight leading-none">
                        <span class="text-amber">OEM</span><span class="text-white">Hub</span>
                    </p>
                </a>
                <p class="text-sm text-white/60 leading-relaxed mb-6 max-w-sm">
                    The central hub for genuine OEM auto parts in Europe. Fast search, fair prices, verified sellers.
                </p>

                {{-- Payment Methods --}}
                <div>
                    <p class="text-xs font-semibold text-white/40 uppercase tracking-wider mb-3">WE ACCEPT</p>
                    <div class="flex flex-wrap gap-2">
                        <div class="px-3 py-1.5 bg-white/10 rounded-md border border-white/10">
                            <span class="text-xs font-bold text-white/80">VISA</span>
                        </div>
                        <div class="px-3 py-1.5 bg-white/10 rounded-md border border-white/10">
                            <span class="text-xs font-bold text-white/80">Mastercard</span>
                        </div>
                        <div class="px-3 py-1.5 bg-white/10 rounded-md border border-white/10">
                            <span class="text-xs font-bold text-white/80">PayPal</span>
                        </div>
                        <div class="px-3 py-1.5 bg-white/10 rounded-md border border-white/10">
                            <span class="text-xs font-bold text-white/80">Apple Pay</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Quick Links column --}}
            <div>
                <h3 class="text-xs font-bold text-amber uppercase tracking-widest mb-4 flex items-center gap-2">
                    <span class="w-0.5 h-4 bg-amber"></span>
                    QUICK LINKS
                </h3>
                <ul class="space-y-2 text-sm">
                    <li><a href="/{{ $lang }}/" class="text-white/60 hover:text-amber transition-colors duration-200 flex items-center gap-2">
                        <span class="w-1 h-1 rounded-full bg-white/40"></span>
                        Search OEM Parts
                    </a></li>
                    <li><a href="/{{ $lang }}/brand/" class="text-white/60 hover:text-amber transition-colors duration-200 flex items-center gap-2">
                        <span class="w-1 h-1 rounded-full bg-white/40"></span>
                        Browse by Brand
                    </a></li>
                    <li><a href="/{{ $lang }}/blog/" class="text-white/60 hover:text-amber transition-colors duration-200 flex items-center gap-2">
                        <span class="w-1 h-1 rounded-full bg-white/40"></span>
                        Blog
                    </a></li>
                    <li><a href="/{{ $lang }}/contact" class="text-white/60 hover:text-amber transition-colors duration-200 flex items-center gap-2">
                        <span class="w-1 h-1 rounded-full bg-white/40"></span>
                        Contact Us
                    </a></li>
                </ul>
            </div>

            {{-- Account column --}}
            <div>
                <h3 class="text-xs font-bold text-amber uppercase tracking-widest mb-4 flex items-center gap-2">
                    <span class="w-0.5 h-4 bg-amber"></span>
                    MY ACCOUNT
                </h3>
                <ul class="space-y-2 text-sm">
                    @auth
                    <li><a href="/{{ $lang }}/account/dashboard" class="text-white/60 hover:text-amber transition-colors duration-200 flex items-center gap-2">
                        <span class="w-1 h-1 rounded-full bg-white/40"></span>
                        My Account
                    </a></li>
                    <li><a href="/{{ $lang }}/account/orders" class="text-white/60 hover:text-amber transition-colors duration-200 flex items-center gap-2">
                        <span class="w-1 h-1 rounded-full bg-white/40"></span>
                        My Orders
                    </a></li>
                    <li><a href="/{{ $lang }}/account/addresses" class="text-white/60 hover:text-amber transition-colors duration-200 flex items-center gap-2">
                        <span class="w-1 h-1 rounded-full bg-white/40"></span>
                        My Addresses
                    </a></li>
                    @else
                    <li>
                        <button @click="$dispatch('open-auth-modal')" class="text-white/60 hover:text-amber transition-colors duration-200 flex items-center gap-2">
                            <span class="w-1 h-1 rounded-full bg-white/40"></span>
                            Sign In
                        </button>
                    </li>
                    <li>
                        <button @click="$dispatch('open-auth-modal', { tab: 'register' })" class="text-white/60 hover:text-amber transition-colors duration-200 flex items-center gap-2">
                            <span class="w-1 h-1 rounded-full bg-white/40"></span>
                            Create Account
                        </button>
                    </li>
                    @endauth
                    <li><a href="/{{ $lang }}/cart" class="text-white/60 hover:text-amber transition-colors duration-200 flex items-center gap-2">
                        <span class="w-1 h-1 rounded-full bg-white/40"></span>
                        Shopping Cart
                    </a></li>
                </ul>
            </div>

            {{-- Contact column --}}
            <div>
                <h3 class="text-xs font-bold text-amber uppercase tracking-widest mb-4 flex items-center gap-2">
                    <span class="w-0.5 h-4 bg-amber"></span>
                    GET IN TOUCH
                </h3>
                <ul class="space-y-4 text-sm">
                    <li>
                        <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="group flex items-start gap-3">
                            <div class="w-9 h-9 rounded-lg bg-white/10 flex items-center justify-center shrink-0">
                                <x-heroicon-o-phone class="w-4 h-4 text-amber" />
                            </div>
                            <div>
                                <p class="text-xs text-white/40 uppercase font-semibold mb-0.5">CALL US</p>
                                <p class="text-white/80 font-mono">{{ $phone ?: '+370 600 00000' }}</p>
                            </div>
                        </a>
                    </li>
                    <li>
                        <a href="mailto:{{ $email }}" class="group flex items-start gap-3">
                            <div class="w-9 h-9 rounded-lg bg-white/10 flex items-center justify-center shrink-0">
                                <x-heroicon-o-envelope class="w-4 h-4 text-amber" />
                            </div>
                            <div>
                                <p class="text-xs text-white/40 uppercase font-semibold mb-0.5">EMAIL US</p>
                                <p class="text-white/80">{{ $email ?: 'info@oemhub.eu' }}</p>
                            </div>
                        </a>
                    </li>
                </ul>

                {{-- Language links with improved styling --}}
                <div class="mt-8">
                    <h3 class="text-xs font-bold text-white/40 uppercase tracking-wider mb-4">LANGUAGE</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach(['en' => 'EN', 'de' => 'DE', 'lt' => 'LT', 'fr' => 'FR', 'es' => 'ES'] as $code => $label)
                        <a
                            href="{{ $langSwitchUrl($code) }}"
                            class="text-xs px-4 py-2.5 min-h-[44px] rounded-xl font-bold transition-all duration-300
                                   {{ $code === $lang
                                      ? 'bg-gradient-to-r from-amber to-orange-500 text-navy shadow-lg shadow-amber/30 scale-105'
                                      : 'bg-white/10 text-white/60 hover:bg-white/20 hover:text-white hover:scale-105' }}"
                        >
                            {{ $label }}
                        </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Trust signals row --}}
        <div class="border-t border-white/10 py-8 flex flex-wrap items-center justify-center gap-6">
            <span class="flex items-center gap-2.5 text-sm text-white/60">
                <div class="w-8 h-8 rounded bg-amber/20 flex items-center justify-center">
                    <x-heroicon-s-lock-closed class="w-4 h-4 text-amber" />
                </div>
                Secure Checkout
            </span>
            <span class="flex items-center gap-2.5 text-sm text-white/60">
                <div class="w-8 h-8 rounded bg-amber/20 flex items-center justify-center">
                    <x-heroicon-o-truck class="w-4 h-4 text-amber" />
                </div>
                EU-Wide Shipping
            </span>
            <span class="flex items-center gap-2.5 text-sm text-white/60">
                <div class="w-8 h-8 rounded bg-amber/20 flex items-center justify-center">
                    <x-heroicon-o-arrow-path class="w-4 h-4 text-amber" />
                </div>
                14-Day Returns
            </span>
            <span class="flex items-center gap-2.5 text-sm text-white/60">
                <div class="w-8 h-8 rounded bg-amber/20 flex items-center justify-center">
                    <x-heroicon-s-shield-check class="w-4 h-4 text-amber" />
                </div>
                Genuine OEM Only
            </span>
        </div>

        {{-- Bottom bar --}}
        <div class="border-t border-white/10 pt-8 flex flex-col lg:flex-row items-center justify-between gap-4 text-xs text-white/40">
            <p>© {{ $year }} {{ $siteName }}. All rights reserved.</p>
            <div class="flex flex-wrap gap-6">
                <a href="/{{ $lang }}/privacy-policy" class="hover:text-amber transition-colors duration-200">Privacy Policy</a>
                <a href="/{{ $lang }}/terms-of-service" class="hover:text-amber transition-colors duration-200">Terms of Service</a>
                <a href="/{{ $lang }}/cookie-policy" class="hover:text-amber transition-colors duration-200">Cookies</a>
            </div>
        </div>
    </div>
</footer>
