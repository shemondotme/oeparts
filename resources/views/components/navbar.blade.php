@php
    $lang    = app()->getLocale();
    $cartUrl = "/{$lang}/cart";
    $homeUrl = "/{$lang}/";
    $siteName = settings('general.site_name', 'OEMHub');

    // Check if this is the homepage
    $isHomepage = request()->routeIs('frontend.home') || (request()->path() === $lang . '/' || request()->path() === $lang);
@endphp

<header
    x-data="{ mobileOpen: false, langOpen: false, scrolled: false }"
    @scroll.window="scrolled = window.pageYOffset > 50"
    @if($isHomepage)
        :class="scrolled ? 'bg-navy/95 backdrop-blur-xl shadow-xl border-b border-white/10' : 'bg-transparent border-transparent'"
        class="absolute top-0 left-0 right-0 z-50 transition-all duration-500"
    @else
        :class="scrolled ? 'shadow-xl' : 'shadow-md'"
        class="relative bg-gradient-to-r from-navy via-navy to-blue-900 sticky top-0 z-50 transition-all duration-300 border-b border-white/5"
    @endif
    role="banner"
>
    @if(!$isHomepage)
    {{-- Decorative background elements (only for non-homepage) --}}
    <div class="absolute inset-0 opacity-10 pointer-events-none">
        <div class="absolute top-0 right-0 w-64 h-64 bg-amber/20 rounded-full filter blur-3xl"></div>
        <div class="absolute bottom-0 left-0 w-48 h-48 bg-blue-400/10 rounded-full filter blur-2xl"></div>
    </div>

    {{-- Top accent bar (only for non-homepage) --}}
    <div class="absolute top-0 left-0 right-0 h-0.5 bg-gradient-to-r from-amber via-orange-400 to-amber"></div>
    @endif

    <div class="{{ $isHomepage ? '' : 'relative z-10' }} max-w-7xl mx-auto px-4 sm:px-6">
        <div class="flex items-center h-20 gap-4">

            {{-- Logo / Site name --}}
            <a href="{{ $homeUrl }}" class="flex items-center gap-3 shrink-0 group
                focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber/70 focus-visible:ring-offset-2 focus-visible:ring-offset-navy rounded-lg"
            >
                {{-- Icon box --}}
                <div class="shrink-0 w-10 h-10 rounded-xl bg-gradient-to-br from-amber to-orange-500 flex items-center justify-center shadow-lg shadow-amber/30 group-hover:shadow-amber/50 group-hover:scale-105 transition-all duration-300">
                    <x-heroicon-s-wrench-screwdriver class="w-5 h-5 text-navy" />
                </div>
                {{-- Text logo --}}
                <p class="font-display font-extrabold text-2xl tracking-tight leading-none">
                    <span class="text-amber">OEM</span><span class="text-white">Hub</span>
                </p>
            </a>

            {{-- Spacer left --}}
            <div class="flex-1"></div>

            {{-- Desktop nav links (centered) --}}
            <nav class="hidden lg:flex items-center gap-1" aria-label="Main navigation">
                <a href="/{{ $lang }}/" class="group px-4 py-2.5 text-sm font-semibold text-white/90 hover:text-white rounded-lg hover:bg-white/10 transition-all duration-200 whitespace-nowrap focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber/70 focus-visible:ring-offset-2 focus-visible:ring-offset-navy">
                    <span class="flex items-center gap-2">
                        <x-heroicon-o-magnifying-glass class="w-4 h-4" />
                        Parts Search
                    </span>
                </a>
                <a href="/{{ $lang }}/brands" class="group px-4 py-2.5 text-sm font-semibold text-white/90 hover:text-white rounded-lg hover:bg-white/10 transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber/70 focus-visible:ring-offset-2 focus-visible:ring-offset-navy">
                    <span class="flex items-center gap-2">
                        <x-heroicon-o-tag class="w-4 h-4" />
                        Brands
                    </span>
                </a>
                <a href="/{{ $lang }}/blog/" class="group px-4 py-2.5 text-sm font-semibold text-white/90 hover:text-white rounded-lg hover:bg-white/10 transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber/70 focus-visible:ring-offset-2 focus-visible:ring-offset-navy">
                    <span class="flex items-center gap-2">
                        <x-heroicon-o-document-text class="w-4 h-4" />
                        Blog
                    </span>
                </a>
                <a href="/{{ $lang }}/about" class="group px-4 py-2.5 text-sm font-semibold text-white/90 hover:text-white rounded-lg hover:bg-white/10 transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber/70 focus-visible:ring-offset-2 focus-visible:ring-offset-navy">
                    <span class="flex items-center gap-2">
                        <x-heroicon-o-information-circle class="w-4 h-4" />
                        About
                    </span>
                </a>
            </nav>

            {{-- Spacer right --}}
            <div class="flex-1"></div>

            {{-- Right actions --}}
            <div class="flex items-center gap-2 sm:gap-3 shrink-0">

                {{-- Language switcher --}}
                <x-language-switcher align="right" theme="dark" />

                {{-- Cart icon with mini-cart dropdown --}}
<div
    class="relative"
    x-data="{
        count: 0,
        items: [],
        subtotal: 0,
        hovered: false,
        loaded: false,
        loading: false,
        animateBadge: false,
        forceShowDropdown: false,
        init() {
            this.loadCartCount();
            window.addEventListener('cart-updated', (event) => {
                const prevCount = this.count;
                this.count = event.detail.itemCount || 0;
                this.loaded = false; // Reset loaded state to force fresh fetch
                
                // Trigger animation and dropdown show when item added
                if (this.count > prevCount) {
                    this.animateBadge = true;
                    this.loadPreview(); // Load new items immediately
                    this.forceShowDropdown = true;
                    setTimeout(() => {
                        this.animateBadge = false;
                        this.forceShowDropdown = false;
                    }, 5000); // Keep open a bit longer (5s) to be helpful
                } else {
                    this.loadPreview(); // Just load silently if removed or changed
                }
            });
        },
                        async loadCartCount() {
                            try {
                                const res  = await fetch('/{{ $lang }}/cart/summary');
                                const data = await res.json();
                                if (data.success) this.count = data.summary.item_count || 0;
                            } catch (e) {}
                        },
                        async loadPreview() {
                            if (this.loaded) return;
                            this.loading = true;
                            try {
                                const res  = await fetch('/{{ $lang }}/cart/preview');
                                const data = await res.json();
                                if (data.success) {
                                    this.items    = data.items;
                                    this.subtotal = data.summary.subtotal;
                                    this.count    = data.summary.item_count || 0;
                                    this.loaded   = true;
                                }
                            } catch (e) {}
                            this.loading = false;
                        },
                        async removeItem(itemId) {
                            try {
                                const res = await fetch(`/{{ $lang }}/cart/remove/${itemId}`, {
                                    method: 'DELETE',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        // Include CSRF somehow - usually present in layout meta
                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]') ? document.querySelector('meta[name=csrf-token]').content : ''
                                    }
                                });
                                const data = await res.json();
                                if (data.success) {
                                    // Trigger global cart update to sync pages & navbar icon
                                    window.dispatchEvent(new CustomEvent('cart-updated', { detail: { itemCount: data.cart_summary.item_count } }));
                                    // Soft refresh the dropdown state
                                    this.items = this.items.filter(i => i.id !== itemId);
                                    if(this.items.length === 0) {
                                        this.subtotal = 0;
                                    } else {
                                        this.subtotal = data.cart_summary.subtotal;
                                    }
                                }
                            } catch (e) {}
                        },
                        conditionClass(condition) {
                            const map = {
                                new:              'bg-green-100 text-green-700',
                                used_grade_a:     'bg-blue-100 text-blue-700',
                                used_grade_b:     'bg-amber-100 text-amber-700',
                                used_grade_c:     'bg-gray-100 text-gray-500',
                                remanufactured:   'bg-purple-100 text-purple-700',
                                aftermarket:      'bg-red-100 text-red-700',
                                new_old_stock:    'bg-teal-100 text-teal-700',
                            };
                            return map[condition] || 'bg-gray-100 text-gray-500';
                        }
                    }"
                    @mouseenter="hovered = true; loadPreview()"
                    @mouseleave="hovered = false"
                >
                    {{-- Cart icon button --}}
                    <a
                        href="{{ $cartUrl }}"
                        class="relative flex items-center justify-center w-12 h-12 text-white/90 hover:text-white rounded-xl hover:bg-white/10 transition-all duration-200 group
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber/70 focus-visible:ring-offset-2 focus-visible:ring-offset-navy"
                        :aria-label="'Shopping cart' + (count > 0 ? ', ' + count + ' items' : '')"
                    >
                        <x-heroicon-o-shopping-cart class="w-5 h-5 group-hover:scale-110 transition-transform duration-200" />
<span
                    x-show="count > 0"
                    x-text="count > 9 ? '9+' : count"
                    :class="animateBadge ? 'animate-cart-bounce bg-emerald-500 text-white shadow-emerald-500/50' : ''"
                    class="absolute -top-1 -right-1 min-w-[20px] h-[20px] bg-amber text-navy text-[10px] font-extrabold rounded-full flex items-center justify-center leading-none px-1 shadow-md shadow-amber/50 ring-2 ring-navy transition-colors duration-300"
                ></span>
                    </a>

{{-- ── Mini-cart dropdown (has items) ── --}}
            <div
                x-show="(hovered || forceShowDropdown) && count > 0"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                        x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                        x-cloak
                        class="absolute top-full right-0 mt-3 w-80 sm:w-96 max-w-[90vw] overflow-hidden rounded-2xl border border-white/60 bg-white/95 shadow-2xl shadow-navy/15 backdrop-blur-xl ring-1 ring-black/5 z-50"
                    >
                        {{-- Arrow caret --}}
                        <div class="absolute -top-1.5 right-5 w-3 h-3 rotate-45 bg-white border-l border-t border-white/60 rounded-tl-sm" aria-hidden="true"></div>

                        {{-- Header --}}
                        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                            <p class="text-sm font-bold text-navy">Cart</p>
                            <span
                                class="inline-flex items-center rounded-full bg-amber/15 px-2 py-0.5 text-[11px] font-bold text-amber-text"
                                x-text="count + ' item' + (count !== 1 ? 's' : '')"
                            ></span>
                        </div>

                        {{-- Items list --}}
                        <div class="max-h-64 overflow-y-auto divide-y divide-gray-50">

                            {{-- Skeleton loading --}}
                            <template x-if="loading">
                                <div class="divide-y divide-gray-50">
                                    <template x-for="i in 2" :key="i">
                                        <div class="px-4 py-3 animate-pulse flex gap-3">
                                            <div class="flex-1 space-y-2">
                                                <div class="h-3 w-24 rounded bg-gray-100"></div>
                                                <div class="h-3 w-36 rounded bg-gray-100"></div>
                                                <div class="flex justify-between">
                                                    <div class="h-3 w-8 rounded bg-gray-100"></div>
                                                    <div class="h-3 w-12 rounded bg-gray-100"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            {{-- Real items --}}
                            <template x-if="!loading">
                                <div class="divide-y divide-gray-50/50">
                                    <template x-for="item in items.slice(0, 4)" :key="item.id">
                                        <div class="group relative flex items-center gap-3 px-4 py-3 hover:bg-gradient-to-r hover:from-white hover:to-gray-50/80 transition-all duration-300">
                                            
                                            {{-- Mini Avatar with Texture --}}
                                            <div class="w-12 h-12 shrink-0 rounded-xl bg-gradient-to-br from-navy to-blue-900 flex items-center justify-center shadow-inner relative overflow-hidden group-hover:shadow-md transition-shadow">
                                                <div class="absolute inset-0 opacity-20 bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')]"></div>
                                                <template x-if="item.product?.condition === 'new' || !item.product?.condition">
                                                    <x-heroicon-o-sparkles class="w-5 h-5 text-white/50 group-hover:text-amber transition-colors relative z-10" />
                                                </template>
                                                <template x-if="item.product?.condition && item.product?.condition !== 'new'">
                                                    <x-heroicon-o-wrench-screwdriver class="w-5 h-5 text-white/50 group-hover:text-amber transition-colors transform group-hover:-rotate-12 relative z-10" />
                                                </template>
                                            </div>

                                            <div class="flex-1 min-w-0 flex flex-col justify-center">
                                                {{-- OEM + condition badge --}}
                                                <div class="flex items-center gap-1.5 flex-wrap mb-0.5">
                                                    <span class="font-mono text-[11px] font-black text-navy truncate block" x-text="item.oem_number"></span>
                                                    <span
                                                        class="shrink-0 rounded px-1.5 py-[1px] text-[8px] font-black uppercase tracking-widest leading-tight"
                                                        :class="conditionClass(item.product?.condition)"
                                                        x-text="(item.product?.condition || 'new').replace(/_/g,' ')"
                                                    ></span>
                                                </div>
                                                {{-- Part name --}}
                                                <p class="text-[10px] font-semibold text-muted truncate mb-1" x-text="item.name || 'Genuine OEM Part'"></p>
                                                {{-- Qty + price row --}}
                                                <div class="flex items-center gap-2">
                                                    <span class="text-[10px] font-bold text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded leading-none">Qty: <span x-text="item.quantity"></span></span>
                                                    <span class="text-xs font-black text-navy bg-amber/10 px-1.5 py-0.5 rounded text-amber-600 leading-none" x-text="'€' + item.line_total.toFixed(2)"></span>
                                                </div>
                                            </div>

                                            {{-- Remove Button Action --}}
                                            <button @click.stop="removeItem(item.id)" aria-label="Remove item" class="opacity-0 group-hover:opacity-100 flex items-center justify-center w-8 h-8 rounded-lg bg-red-50 text-red-400 hover:bg-red-500 hover:text-white transition-all transform hover:scale-105 shrink-0 shadow-sm disabled:opacity-50">
                                                <x-heroicon-o-trash class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </template>

                                    {{-- More items badge --}}
                                    <div x-show="items.length > 4" class="px-4 py-2.5 text-center bg-gray-50/50">
                                        <span class="text-[11px] font-semibold text-muted"
                                              x-text="'+ ' + (items.length - 4) + ' more item' + (items.length - 4 !== 1 ? 's' : '') + ' in cart'"></span>
                                    </div>
                                </div>
                            </template>
                        </div>

                        {{-- Footer --}}
                        <div class="px-4 pt-3 pb-4 border-t border-gray-100 bg-gray-50/50 space-y-3">
                            <div class="flex items-baseline justify-between mb-1">
                                <span class="text-[11px] font-black uppercase tracking-widest text-muted">Cart Subtotal</span>
                                <span class="text-lg font-black text-navy" x-text="'€' + subtotal.toFixed(2)"></span>
                            </div>
                            <div class="flex gap-2">
                                <a
                                    href="{{ $cartUrl }}"
                                    class="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-white border border-gray-200 py-2.5 text-xs font-bold text-navy shadow-sm transition-all duration-200 hover:border-gray-300 hover:bg-gray-50 active:scale-[0.98]"
                                >
                                    View Cart
                                </a>
                                <a
                                    href="{{ $cartUrl }}#checkout"
                                    class="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-gradient-to-r from-amber to-orange-500 py-2.5 text-xs font-black uppercase tracking-[0.05em] text-navy shadow-md shadow-amber/30 transition-all duration-200 hover:shadow-lg hover:shadow-amber/40 active:scale-[0.98] group"
                                >
                                    Checkout
                                    <x-heroicon-s-arrow-right class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform" />
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- ── Empty cart dropdown ── --}}
                    <div
                        x-show="hovered && count === 0 && loaded"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                        x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                        x-cloak
                        class="absolute top-full right-0 mt-3 w-64 max-w-[90vw] overflow-hidden rounded-2xl border border-white/60 bg-white/95 shadow-2xl shadow-navy/15 backdrop-blur-xl ring-1 ring-black/5 z-50"
                    >
                        <div class="absolute -top-1.5 right-5 w-3 h-3 rotate-45 bg-white border-l border-t border-white/60 rounded-tl-sm" aria-hidden="true"></div>
                        <div class="px-5 py-7 text-center">
                            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
                                <x-heroicon-o-shopping-cart class="w-5 h-5 text-gray-400" />
                            </div>
                            <p class="text-sm font-bold text-navy">Your cart is empty</p>
                            <p class="mt-1 text-xs text-muted">Search for OEM parts to get started</p>
                        </div>
                    </div>
                </div>

                {{-- Account / login --}}
                @auth
                <a
                    href="/{{ $lang }}/account/dashboard"
                    class="hidden sm:flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-white/10 hover:bg-white/20 rounded-xl transition-all duration-200 backdrop-blur-sm border border-white/10
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber/70 focus-visible:ring-offset-2 focus-visible:ring-offset-navy"
                >
                    <x-heroicon-o-user-circle class="w-5 h-5" />
                    <span>Account</span>
                </a>
                @else
                <button
                    @click="$dispatch('open-auth-modal')"
                    class="hidden sm:flex items-center gap-2 px-5 py-2.5 text-sm font-bold text-navy bg-gradient-to-r from-amber to-orange-500 hover:from-amber/90 hover:to-orange-400 rounded-xl transition-all duration-200 shadow-lg shadow-amber/30 hover:shadow-amber/50
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber/70 focus-visible:ring-offset-2 focus-visible:ring-offset-navy"
                >
                    <x-heroicon-o-user class="w-4 h-4" />
                    <span>Sign In</span>
                </button>
                @endauth

                {{-- Mobile menu toggle --}}
                <button
                    @click="mobileOpen = !mobileOpen"
                    :aria-expanded="mobileOpen"
                    aria-controls="mobile-menu"
                    aria-label="Toggle navigation menu"
                    class="lg:hidden flex items-center justify-center w-11 h-11 text-white/90 hover:text-white rounded-xl hover:bg-white/10 transition-all duration-200
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber/70 focus-visible:ring-offset-2 focus-visible:ring-offset-navy"
                >
                    <x-heroicon-o-bars-3 x-show="!mobileOpen" class="w-5 h-5" aria-hidden="true" />
                    <x-heroicon-o-x-mark x-show="mobileOpen" class="w-5 h-5" aria-hidden="true" x-cloak/>
                </button>
            </div>
        </div>

        {{-- Mobile nav --}}
        <nav
            id="mobile-menu"
            x-show="mobileOpen"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-4"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-4"
            aria-label="Mobile navigation"
            class="lg:hidden pb-6 space-y-3 @if($isHomepage) bg-navy/95 backdrop-blur-xl rounded-b-2xl px-2 @endif"
        >
            {{-- Mobile nav links --}}
            <div class="flex flex-col gap-2">
                <a href="/{{ $lang }}/" class="flex items-center gap-3 px-4 py-3.5 text-sm font-semibold text-white/90 hover:text-white hover:bg-white/10 rounded-xl min-h-[48px] transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber/70 focus-visible:ring-offset-2 focus-visible:ring-offset-navy">
                    <x-heroicon-o-magnifying-glass class="w-5 h-5" />
                    Parts Search
                </a>
                <a href="/{{ $lang }}/brands" class="flex items-center gap-3 px-4 py-3.5 text-sm font-semibold text-white/90 hover:text-white hover:bg-white/10 rounded-xl min-h-[48px] transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber/70 focus-visible:ring-offset-2 focus-visible:ring-offset-navy">
                    <x-heroicon-o-tag class="w-5 h-5" />
                    Brands
                </a>
                <a href="/{{ $lang }}/blog/" class="flex items-center gap-3 px-4 py-3.5 text-sm font-semibold text-white/90 hover:text-white hover:bg-white/10 rounded-xl min-h-[48px] transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber/70 focus-visible:ring-offset-2 focus-visible:ring-offset-navy">
                    <x-heroicon-o-document-text class="w-5 h-5" />
                    Blog
                </a>
                <a href="/{{ $lang }}/about" class="flex items-center gap-3 px-4 py-3.5 text-sm font-semibold text-white/90 hover:text-white hover:bg-white/10 rounded-xl min-h-[48px] transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber/70 focus-visible:ring-offset-2 focus-visible:ring-offset-navy">
                    <x-heroicon-o-information-circle class="w-5 h-5" />
                    About
                </a>
            </div>

            {{-- Divider --}}
            <div class="border-t border-white/10 pt-4 mt-2"></div>

            @guest
            <button
                @click="$dispatch('open-auth-modal'); mobileOpen = false"
                class="w-full flex items-center justify-center gap-2 px-4 py-3.5 text-sm font-bold text-navy bg-gradient-to-r from-amber to-orange-500 rounded-xl min-h-[48px] shadow-lg shadow-amber/30
                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber/70 focus-visible:ring-offset-2 focus-visible:ring-offset-navy"
            >
                <x-heroicon-o-user class="w-5 h-5" aria-hidden="true" />
                Sign In / Register
            </button>
            @endguest
            @auth
            <a href="/{{ $lang }}/account/dashboard" class="flex items-center gap-3 px-4 py-3.5 text-sm font-semibold text-white hover:bg-white/10 rounded-xl min-h-[48px] transition-all duration-200
                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber/70 focus-visible:ring-offset-2 focus-visible:ring-offset-navy">
                <x-heroicon-o-user-circle class="w-6 h-6" aria-hidden="true" />
                My Account
            </a>
            @endauth
        </nav>
    </div>
</header>
