@php
    $lang     = app()->getLocale();
    $cartUrl  = url("/{$lang}/cart");
    $homeUrl  = url("/{$lang}/");
    $siteName = settings('general.site_name', 'OeParts');

    $isHomepage = request()->routeIs('frontend.home')
        || (request()->path() === $lang . '/' || request()->path() === $lang);

    $navLinks = [
        ['href' => route('frontend.search.console', ['lang' => $lang]), 'label' => ui_copy('nav_label_parts', 'navbar.label_parts')],
        ['href' => url("/{$lang}/brands"),  'label' => ui_copy('nav_label_brands', 'navbar.label_brands')],
        ['href' => url("/{$lang}/blog/"),   'label' => ui_copy('nav_label_journal', 'navbar.label_journal')],
        ['href' => url("/{$lang}/about"),   'label' => ui_copy('nav_label_about', 'navbar.label_about')],
    ];
@endphp

{{-- ══════════════════════════════════════════════════════════════════════
     INDUSTRIAL BLUEPRINT NAVBAR
     Flat, hairline, numbered navigation — reads like a document header
     ══════════════════════════════════════════════════════════════════ --}}
<header
    x-data="{ mobileOpen: false, scrolled: false }"
    @scroll.window="scrolled = window.pageYOffset > 8"
    @click.away="mobileOpen = false"
    :class="scrolled ? 'bg-ivory/95 backdrop-blur-md' : 'bg-ivory'"
    class="sticky top-0 z-50 border-b border-rule transition-colors duration-200"
    role="banner"
>
    {{-- Spec-sheet strip: site meta rendered as technical document header --}}
    <div class="border-b border-rule/60 bg-ivory">
        <div class="max-w-[1440px] mx-auto px-4 sm:px-6 flex items-center justify-between h-8 text-[10px] font-mono uppercase tracking-[0.24em] text-ink-muted">
            <div class="flex items-center gap-4">
                <span class="hidden sm:inline">{{ ui_copy('nav_strip_doc', 'navbar.strip_doc') }}</span>
                <span class="hidden lg:inline text-rule-strong">│</span>
                <span class="hidden lg:inline">{{ ui_copy('nav_strip_genuine', 'navbar.strip_genuine') }}</span>
            </div>
            <div class="flex items-center gap-4">
                <span class="hidden sm:inline-flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 bg-emerald-600"></span>
                    {{ ui_copy('nav_strip_status', 'navbar.strip_status') }}
                </span>
                <span class="uppercase">{{ strtoupper($lang) }} · {{ settings('store.currency', 'EUR') }}</span>
            </div>
        </div>
    </div>

    <div class="max-w-[1440px] mx-auto px-4 sm:px-6">
        <div class="flex items-stretch h-[72px] gap-0">

            {{-- ═══ Logo block ═══ --}}
            <a href="{{ $homeUrl }}"
               class="group flex items-center gap-3.5 pr-6 border-r border-rule shrink-0
                      focus-visible:outline-none focus-visible:bg-ink/5"
               aria-label="{{ $siteName }} · Home">
                {{-- Hex bolt mark — authored SVG, inverts on hover --}}
                <div class="relative w-11 h-11 shrink-0 transition-transform duration-300 group-hover:rotate-[30deg]">
                    <svg viewBox="0 0 60 60" class="w-full h-full" aria-hidden="true">
                        <path d="M30 3 L53 16 L53 44 L30 57 L7 44 L7 16 Z"
                              class="fill-ink group-hover:fill-amber transition-colors duration-200"/>
                        <path d="M30 13 L44.5 21.5 L44.5 38.5 L30 47 L15.5 38.5 L15.5 21.5 Z"
                              class="fill-ivory group-hover:fill-ink transition-colors duration-200"/>
                        <path d="M30 18 L30 42 M18 30 L42 30"
                              class="stroke-ink group-hover:stroke-amber transition-colors duration-200"
                              stroke-width="2.5" stroke-linecap="square"/>
                        <circle cx="30" cy="30" r="3.2"
                                class="fill-amber group-hover:fill-ivory transition-colors duration-200"/>
                    </svg>
                    <span class="absolute -top-0.5 -right-0.5 w-1.5 h-1.5 bg-amber group-hover:bg-ink transition-colors"></span>
                </div>
                <div class="leading-none">
                    <p class="font-display font-extrabold text-[22px] tracking-[-0.02em] text-ink leading-none">
                        {{ $siteName }}
                    </p>
                    <p class="mt-1.5 font-mono text-[9px] tracking-[0.24em] uppercase text-ink-muted">
                        {!! str_replace(' ', '&#160;', e(ui_copy('nav_logo_subline', 'navbar.logo_subline'))) !!}
                    </p>
                </div>
            </a>

            {{-- ═══ Desktop navigation — numbered ═══ --}}
            <nav class="hidden lg:flex items-stretch flex-1" aria-label="Main navigation">
                @foreach($navLinks as $link)
                    <a href="{{ $link['href'] }}"
                       class="group relative flex items-center gap-2.5 px-5 border-r border-rule/60
                              text-ink hover:bg-ink/[0.04]
                              transition-colors duration-150
                              focus-visible:outline-none focus-visible:bg-ink/10">
                        <span class="font-sans text-[13px] font-bold uppercase tracking-[0.14em]">
                            {{ $link['label'] }}
                        </span>
                        {{-- Amber tick on hover --}}
                        <span class="absolute bottom-0 left-5 h-[3px] w-0 bg-amber transition-all duration-200 group-hover:w-[calc(100%-2.5rem)]"></span>
                    </a>
                @endforeach
            </nav>

            {{-- Spacer (only on non-desktop to push right actions) --}}
            <div class="flex-1 lg:hidden"></div>

            {{-- ═══ Right actions ═══ --}}
            <div class="flex items-stretch border-l border-rule">

                {{-- Language switcher (keep component) --}}
                <div class="hidden sm:flex items-center px-3 border-r border-rule/60">
                    <x-language-switcher align="right" theme="light" />
                </div>

                {{-- ── Cart ── --}}
                <div
                    class="relative flex items-stretch"
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
                                this.loaded = false;
                                if (this.count > prevCount) {
                                    this.animateBadge = true;
                                    this.loadPreview();
                                    this.forceShowDropdown = true;
                                    setTimeout(() => {
                                        this.animateBadge = false;
                                        this.forceShowDropdown = false;
                                    }, 5000);
                                } else {
                                    this.loadPreview();
                                }
                            });
                        },
                        async loadCartCount() {
                            try {
                                const res  = await fetch('{{ route('frontend.cart.summary', ['lang' => $lang]) }}');
                                const data = await res.json();
                                if (data.success) this.count = data.summary.item_count || 0;
                            } catch (e) {}
                        },
                        async loadPreview() {
                            if (this.loaded) return;
                            this.loading = true;
                            try {
                                const res  = await fetch('{{ route('frontend.cart.preview', ['lang' => $lang]) }}');
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
                                const res = await fetch(`{{ url('/'.$lang.'/cart/remove') }}/${itemId}`, {
                                    method: 'DELETE',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]') ? document.querySelector('meta[name=csrf-token]').content : ''
                                    }
                                });
                                const data = await res.json();
                                if (data.success) {
                                    window.dispatchEvent(new CustomEvent('cart-updated', { detail: { itemCount: data.cart_summary.item_count } }));
                                    this.items = this.items.filter(i => i.id !== itemId);
                                    this.subtotal = this.items.length ? data.cart_summary.subtotal : 0;
                                }
                            } catch (e) {}
                        }
                    }"
                    @mouseenter="hovered = true; loadPreview()"
                    @mouseleave="hovered = false"
                >
                    <a
                        href="{{ $cartUrl }}"
                        class="relative flex items-center gap-2.5 px-5 min-w-[72px]
                               text-ink hover:bg-ink hover:text-ivory
                               transition-colors duration-150
                               focus-visible:outline-none focus-visible:bg-ink focus-visible:text-ivory"
                        :aria-label="'Shopping cart' + (count > 0 ? ', ' + count + ' items' : '')"
                    >
                        <x-heroicon-o-shopping-cart class="w-5 h-5" aria-hidden="true" />
                        <span class="font-mono text-[10px] font-bold tracking-[0.18em] uppercase hidden sm:inline">
                            {{ settings('navbar.cart_label', 'CART') }}
                        </span>
                        <span
                            x-show="count > 0"
                            x-text="count > 99 ? '99+' : count"
                            :class="animateBadge ? 'bg-amber text-ink' : 'bg-ink text-ivory group-hover:bg-amber group-hover:text-ink'"
                            class="absolute top-2 right-2 min-w-[18px] h-[18px] px-1
                                   font-mono text-[10px] font-bold leading-none
                                   flex items-center justify-center
                                   bg-amber text-ink
                                   ring-1 ring-ink"
                        ></span>
                    </a>

                    {{-- ── Mini-cart dropdown (Blueprint style) ── --}}
                    <div
                        x-show="(hovered || forceShowDropdown) && count > 0"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100 translate-y-0"
                        x-transition:leave-end="opacity-0 translate-y-1"
                        x-cloak
                        class="absolute top-full right-0 w-[380px] max-w-[92vw] bg-paper border border-ink z-50"
                    >
                        {{-- Spec header --}}
                        <div class="flex items-center justify-between px-5 py-3 bg-ink text-ivory">
                            <p class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase">
                                {{ settings('navbar.cart_title', 'DOC · BASKET /') }} <span class="text-amber" x-text="('0' + count).slice(-2)"></span>
                            </p>
                            <p class="font-mono text-[10px] tracking-[0.18em] uppercase text-ivory/70">
                                <span x-text="count + ' LINE' + (count !== 1 ? 'S' : '')"></span>
                            </p>
                        </div>

                        {{-- Items --}}
                        <div class="max-h-72 overflow-y-auto divide-y divide-rule">
                            <template x-if="loading">
                                <div class="divide-y divide-rule">
                                    <template x-for="i in 2" :key="i">
                                        <div class="px-5 py-4 flex items-start gap-3 animate-pulse">
                                            <div class="h-3 w-16 bg-rule"></div>
                                            <div class="flex-1 space-y-2">
                                                <div class="h-3 w-32 bg-rule"></div>
                                                <div class="h-3 w-20 bg-rule"></div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <template x-if="!loading">
                                <div class="divide-y divide-rule">
                                    <template x-for="(item, idx) in items.slice(0, 4)" :key="item.id">
                                        <div class="group relative flex items-start gap-3 px-5 py-3.5 hover:bg-ivory transition-colors">
                                            {{-- Row number --}}
                                            <span class="font-mono text-[10px] font-bold text-ink-muted pt-0.5 w-6 shrink-0"
                                                  x-text="('0' + (idx + 1)).slice(-2)"></span>
                                            <div class="flex-1 min-w-0 space-y-1">
                                                <p class="font-mono text-[13px] font-bold text-ink tracking-wide truncate uppercase"
                                                   x-text="item.oem_number"></p>
                                                <p class="text-[11px] text-ink-muted truncate"                                                    x-text="item.name || '{{ settings('cart.fallback_name', 'Genuine OEM Part') }}'"></p>
                                                <p class="text-[10px] font-mono text-ink-muted uppercase tracking-[0.18em]">
                                                    QTY <span x-text="item.quantity"></span>
                                                </p>
                                            </div>
                                            <div class="text-right shrink-0 space-y-1">
                                                <p class="font-mono text-[13px] font-bold text-ink tabular-nums"
                                                   x-text="'{{ settings('store.currency_symbol', '€') }}' + item.line_total.toFixed(2)"></p>
                                                <button @click.stop="removeItem(item.id)"
                                                        aria-label="Remove"
                                                        class="text-[9px] font-mono font-bold uppercase tracking-[0.2em] text-ink-muted hover:text-red-700 border-b border-transparent hover:border-red-700">
                                                    {{ settings('navbar.remove_label', 'REMOVE') }}
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                    <div x-show="items.length > 4" class="px-5 py-2.5 bg-ivory text-center">
                                        <span class="font-mono text-[10px] uppercase tracking-[0.2em] text-ink-muted"
                                              x-text="'+ ' + (items.length - 4) + ' MORE'"></span>
                                    </div>
                                </div>
                            </template>
                        </div>

                        {{-- Footer: total + CTAs --}}
                        <div class="border-t border-ink px-5 py-4 space-y-3 bg-ivory">
                            <div class="bp-leader">
                                <span class="bp-spec">{{ settings('navbar.subtotal_label', 'SUBTOTAL') }}</span>
                                <span class="bp-leader-dots"></span>
                                <span class="font-mono text-lg font-bold text-ink tabular-nums"
                                      x-text="'{{ settings('store.currency_symbol', '€') }}' + subtotal.toFixed(2)"></span>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <a href="{{ $cartUrl }}" class="bp-btn-outline text-[11px] py-2.5">
                                    {{ settings('navbar.view_cart_label', 'VIEW CART') }}
                                </a>
                                <a href="{{ url("/{$lang}/checkout") }}" class="bp-btn-amber text-[11px] py-2.5">
                                    {{ settings('navbar.checkout_label', 'CHECKOUT') }}
                                    <x-heroicon-s-arrow-long-right class="w-4 h-4" />
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- Empty cart dropdown --}}
                    <div
                        x-show="hovered && count === 0 && loaded"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-cloak
                        class="absolute top-full right-0 w-72 bg-paper border border-ink z-50"
                    >
                        <div class="px-5 py-3 bg-ink text-ivory">
                            <p class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase">
                                {{ settings('navbar.cart_title', 'DOC · BASKET /') }} 00
                            </p>
                        </div>
                        <div class="px-5 py-6 text-center">
                             <p class="font-display text-lg font-bold text-ink leading-tight">{{ settings('cart.empty_message', 'Empty basket') }}</p>
                            <p class="mt-1.5 text-xs text-ink-muted">{{ settings('cart.empty_description', 'Search an OEM number to begin.') }}</p>
                            <a href="{{ route('frontend.search.console', ['lang' => $lang]) }}"
                               class="mt-4 inline-flex items-center justify-center gap-2 w-full
                                      px-4 py-2.5 bg-ink text-ivory
                                      font-mono text-[10px] font-bold tracking-[0.22em] uppercase
                                      hover:bg-amber hover:text-ink transition-colors">
                                <x-heroicon-s-magnifying-glass class="w-4 h-4" />
                                {{ settings('search.start_button_label', 'Start search') }}
                            </a>
                        </div>
                    </div>
                </div>

                {{-- ── Account / sign-in ── --}}
                @auth
                <a
                    href="{{ url('/'.$lang.'/account/dashboard') }}"
                    class="hidden sm:flex items-center gap-2 px-5 border-l border-rule
                           text-ink hover:bg-ink hover:text-ivory
                           transition-colors duration-150
                           focus-visible:outline-none focus-visible:bg-ink focus-visible:text-ivory"
                >
                    <x-heroicon-o-user-circle class="w-5 h-5" aria-hidden="true" />
                    <span class="font-mono text-[10px] font-bold tracking-[0.18em] uppercase">{{ settings('navbar.account_label', 'ACCOUNT') }}</span>
                </a>
                @else
                <button
                    @click="$dispatch('open-auth-modal')"
                    class="hidden sm:flex items-center gap-2 px-5 border-l border-rule
                           bg-ink text-ivory hover:bg-amber hover:text-ink
                           transition-colors duration-150
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber focus-visible:ring-offset-0"
                >
                    <x-heroicon-o-arrow-right-on-rectangle class="w-4 h-4" aria-hidden="true" />
                    <span class="font-mono text-[11px] font-bold tracking-[0.2em] uppercase">{{ settings('navbar.sign_in_label', 'SIGN IN') }}</span>
                </button>
                @endauth

                {{-- Mobile toggle --}}
                <button
                    @click="mobileOpen = !mobileOpen"
                    :aria-expanded="mobileOpen"
                    aria-controls="mobile-menu"
                    aria-label="Toggle navigation menu"
                    class="lg:hidden flex items-center justify-center w-[56px] border-l border-rule
                           text-ink hover:bg-ink hover:text-ivory transition-colors"
                >
                    <x-heroicon-o-bars-3 x-show="!mobileOpen" class="w-5 h-5" aria-hidden="true" />
                    <x-heroicon-o-x-mark x-show="mobileOpen" class="w-5 h-5" aria-hidden="true" x-cloak />
                </button>
            </div>
        </div>
    </div>

    {{-- ═══ Mobile nav panel ═══ --}}
    <nav
        id="mobile-menu"
        x-show="mobileOpen"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        aria-label="Mobile navigation"
        class="lg:hidden bg-ivory border-t border-rule"
    >
        <div class="px-4 sm:px-6 py-3 divide-y divide-rule/70">
            @foreach($navLinks as $link)
                <a href="{{ $link['href'] }}"
                   class="flex items-center gap-4 py-4 group">
                    <span class="font-sans text-sm font-bold uppercase tracking-[0.16em] text-ink">
                        {{ $link['label'] }}
                    </span>
                    <x-heroicon-s-arrow-long-right class="w-4 h-4 text-ink-muted ml-auto group-hover:text-ink transition-colors" />
                </a>
            @endforeach
        </div>

        {{-- ── Language picker (mobile) ── --}}
        @php
            $mobileLanguages = json_decode(settings('store.languages', json_encode([
                'en' => ['fi' => 'gb', 'native' => 'English'],
                'de' => ['fi' => 'de', 'native' => 'Deutsch'],
                'lt' => ['fi' => 'lt', 'native' => 'Lietuvių'],
                'fr' => ['fi' => 'fr', 'native' => 'Français'],
                'es' => ['fi' => 'es', 'native' => 'Español'],
            ])), true);
            $mobileLangUrl = function($newLocale) {
                $current = request()->route();
                if (!$current || !$current->getName()) { return "/{$newLocale}/"; }
                $params = $current->parameters();
                $params['lang'] = $newLocale;
                $query = request()->query();
                unset($query['lang']);
                try {
                    $url = route($current->getName(), $params);
                    return $url . (empty($query) ? '' : '?' . http_build_query($query));
                } catch (\Exception $e) {
                    $path = request()->path();
                    $newPath = preg_replace('#^(en|de|lt|fr|es)(/|$)#', $newLocale . '$2', $path);
                    return url('/' . $newPath);
                }
            };
        @endphp
        <div class="border-t border-rule px-4 sm:px-6 py-4">
            <div class="flex items-center justify-between mb-3">
                <span class="font-mono text-[10px] font-bold tracking-[0.24em] uppercase text-amber-ink">Locale</span>
                <span class="bp-spec-mono">5 options</span>
            </div>
            <div class="grid grid-cols-5 gap-[2px] border border-ink bg-ink">
                @foreach($mobileLanguages as $code => $data)
                    @php $isActive = $lang === $code; @endphp
                    <a href="{{ $mobileLangUrl($code) }}"
                       aria-label="{{ $data['native'] }}"
                       class="flex flex-col items-center justify-center gap-1.5 py-3 transition-colors
                              {{ $isActive ? 'bg-amber text-ink' : 'bg-paper text-ink hover:bg-ivory-alt' }}">
                        <img src="{{ asset('flags/' . $data['fi'] . '.svg') }}"
                             alt="{{ $data['native'] }}"
                             class="w-6 h-[16px] object-cover border {{ $isActive ? 'border-ink/30' : 'border-rule' }}">
                        <span class="font-mono text-[10px] font-bold tracking-[0.18em] uppercase tabular-nums">
                            {{ strtoupper($code) }}
                        </span>
                    </a>
                @endforeach
            </div>
        </div>

        <div class="border-t border-rule px-4 sm:px-6 py-4 space-y-3">
            @guest
            <button
                @click="$dispatch('open-auth-modal'); mobileOpen = false"
                class="w-full bp-btn-primary"
            >
                <x-heroicon-o-arrow-right-on-rectangle class="w-4 h-4" />
                {{ settings('navbar.sign_in_register_label', 'SIGN IN · REGISTER') }}
            </button>
            @endguest
            @auth
            <a href="{{ url('/'.$lang.'/account/dashboard') }}" class="w-full bp-btn-outline">
                <x-heroicon-o-user-circle class="w-5 h-5" />
                {{ settings('navbar.my_account_label', 'MY ACCOUNT') }}
            </a>
            @endauth
        </div>
    </nav>
</header>
