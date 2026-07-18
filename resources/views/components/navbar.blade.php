@php
    $lang     = app()->getLocale();
    $cartUrl  = url("/{$lang}/cart");
    $homeUrl  = url("/{$lang}/");
    $siteName = settings('general.site_name', 'OeParts');

    $isHomepage = request()->routeIs('frontend.home')
        || (request()->path() === $lang . '/' || request()->path() === $lang);

    $navLinks = [
        [
            'href'  => route('frontend.search.console', ['lang' => $lang]),
            'label' => ui_copy('nav_label_parts', 'navbar.label_parts'),
            'active' => request()->routeIs('frontend.search.*'),
        ],
        [
            'href'  => url("/{$lang}/brands"),
            'label' => ui_copy('nav_label_brands', 'navbar.label_brands'),
            'active' => request()->routeIs('frontend.manufacturer.*') || request()->routeIs('frontend.car-model.*'),
        ],
        [
            'href'  => url("/{$lang}/blog/"),
            'label' => ui_copy('nav_label_journal', 'navbar.label_journal'),
            'active' => request()->routeIs('frontend.blog.*'),
        ],
        [
            'href'  => url("/{$lang}/about"),
            'label' => ui_copy('nav_label_about', 'navbar.label_about'),
            'active' => request()->routeIs('frontend.page') && request()->route('slug') === 'about',
        ],
    ];
@endphp

{{-- ══════════════════════════════════════════════════════════════════════
     INDUSTRIAL BLUEPRINT NAVBAR
     Flat, hairline, numbered navigation — reads like a document header
     ══════════════════════════════════════════════════════════════════ --}}
<header
    x-data="{ mobileOpen: false, scrolled: false }"
    x-init="$nextTick(() => {
        // Exact fractional height (no round/floor/ceil): rounding either way was
        // the actual bug — floor()/ceil() intentionally mismatch the real
        // boundary by a fraction of a px, which either leaks a sliver of
        // scrolled content through the gap (round up) or lets this header
        // paint over and hide the next sticky element's own top border
        // (round down). Elements stacked directly on this variable should
        // land exactly where this one visually ends — no more, no less.
        const setNavHeight = () => document.documentElement.style.setProperty('--navbar-h', $el.getBoundingClientRect().height + 'px');
        setNavHeight();
        new ResizeObserver(setNavHeight).observe($el);
    })"
    @scroll.window="scrolled = window.pageYOffset > 8"
    @click.away="mobileOpen = false"
    :class="scrolled ? 'bg-ivory/95 backdrop-blur-md' : 'bg-ivory'"
    class="sticky top-0 z-50 border-b border-rule transition-colors duration-200"
    role="banner"
>
    <div class="max-w-[1440px] mx-auto px-4 sm:px-6">
        <div class="flex items-stretch h-[69px] gap-0">

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
                    <x-brand-wordmark tone="light" size="sm" />
                    <p class="mt-1.5 font-mono text-[9px] tracking-[0.24em] uppercase text-ink-muted">
                        {!! str_replace(' ', '&#160;', e(ui_copy('nav_logo_subline', 'navbar.logo_subline'))) !!}
                    </p>
                </div>
            </a>

            {{-- ═══ Desktop navigation ═══ --}}
            <nav class="hidden lg:flex items-stretch flex-1 border-r border-rule" aria-label="Main navigation">
                @foreach($navLinks as $link)
                    <a href="{{ $link['href'] }}"
                       @if($link['active']) aria-current="page" @endif
                       class="group relative flex items-center gap-2.5 px-3.5 xl:px-5
                              text-ink hover:bg-ink/[0.04]
                              transition-colors duration-150
                              focus-visible:outline-none focus-visible:bg-ink/10">
                        {{-- whitespace-nowrap: at the lg: floor (1024px) longer
                             translations (confirmed with German "Über uns")
                             wrapped to two lines inside this nav item, which
                             looked broken next to the other single-line
                             items. Reduced side padding at this breakpoint
                             (back to the original px-5 from xl: up, where
                             there's slack again) recovers just enough width
                             to keep every current locale's label on one
                             line at 1024px — verified via Playwright across
                             en/de/lt/fr/es. --}}
                        <span class="font-sans text-[13px] font-bold uppercase tracking-[0.14em] whitespace-nowrap">
                            {{ $link['label'] }}
                        </span>
                        {{-- Amber tick — persistent when active, grows on hover otherwise --}}
                        <span class="absolute bottom-0 left-5 h-[3px] bg-amber transition-all duration-200
                                      {{ $link['active'] ? 'w-[calc(100%-2.5rem)]' : 'w-0 group-hover:w-[calc(100%-2.5rem)]' }}"></span>
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
                    @focusin="hovered = true; loadPreview()"
                    @focusout="if (!$el.contains($event.relatedTarget)) hovered = false"
                >
                    <a
                        href="{{ $cartUrl }}"
                        class="group relative flex items-center gap-2.5 px-5 min-w-[72px]
                               text-ink hover:bg-ink hover:text-ivory
                               transition-colors duration-150
                               focus-visible:outline-none focus-visible:bg-ink focus-visible:text-ivory"
                        :aria-label="'Shopping cart' + (count > 0 ? ', ' + count + ' items' : '')"
                    >
                        <x-heroicon-o-shopping-cart class="w-5 h-5" aria-hidden="true" />
                        <span class="font-sans text-[11px] font-bold tracking-[0.14em] uppercase hidden sm:inline">
                            {{ ui_copy('navbar_cart_label', 'navbar.cart_label') }}
                        </span>
                        <span
                            x-show="count > 0"
                            x-text="count > 99 ? '99+' : count"
                            :class="animateBadge ? 'bg-amber text-ink' : 'bg-ink text-ivory group-hover:bg-amber group-hover:text-ink'"
                            class="absolute top-2 right-2 min-w-[18px] h-[18px] px-1
                                   font-mono text-[10px] font-bold leading-none
                                   flex items-center justify-center
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
                                {{ ui_copy('navbar_cart_title', 'navbar.cart_title') }} <span class="text-amber" x-text="('0' + count).slice(-2)"></span>
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
                                                <p class="text-[11px] text-ink-muted truncate"                                                    x-text="item.name || '{{ ui_copy('cart_genuine_part', 'cart.genuine_part') }}'"></p>
                                                <p class="text-[10px] font-mono text-ink-muted uppercase tracking-[0.18em]">
                                                    QTY <span x-text="item.quantity"></span>
                                                </p>
                                            </div>
                                            <div class="text-right shrink-0 space-y-1">
                                                <p class="font-mono text-[13px] font-bold text-ink tabular-nums"
                                                   x-text="'{{ settings('general.currency_symbol', '€') }}' + Number(item.line_total).toFixed(2)"></p>
                                                <button @click.stop="removeItem(item.id)"
                                                        aria-label="Remove"
                                                        class="text-[9px] font-mono font-bold uppercase tracking-[0.2em] text-ink-muted hover:text-red-700 border-b border-transparent hover:border-red-700">
                                                    {{ ui_copy('navbar_remove_label', 'navbar.remove_label') }}
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
                                <span class="bp-spec">{{ ui_copy('navbar_subtotal_label', 'navbar.subtotal_label') }}</span>
                                {{-- Plain <span>s here (no dt/dd — this isn't a definition
                                     list), so the dotted-line divider is a real standalone
                                     element again, unlike the .bp-leader dt::after fix
                                     used everywhere else this pattern wraps a real <dl>. --}}
                                <span class="bp-leader-dots"></span>
                                <span class="font-mono text-lg font-bold text-ink tabular-nums"
                                      x-text="'{{ settings('general.currency_symbol', '€') }}' + Number(subtotal).toFixed(2)"></span>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <a href="{{ $cartUrl }}" class="bp-btn-outline text-[11px] py-2.5">
                                    {{ ui_copy('navbar_view_cart_label', 'navbar.view_cart_label') }}
                                </a>
                                <a href="{{ url("/{$lang}/checkout") }}" class="bp-btn-amber text-[11px] py-2.5">
                                    {{ ui_copy('navbar_checkout_label', 'navbar.checkout_label') }}
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
                                {{ ui_copy('navbar_cart_title', 'navbar.cart_title') }} 00
                            </p>
                        </div>
                        <div class="px-5 py-6 text-center">
                             <p class="font-display text-lg font-bold text-ink leading-tight">{{ ui_copy('cart_empty_message', 'cart.empty_message') }}</p>
                            <p class="mt-1.5 text-xs text-ink-muted">{{ ui_copy('cart_empty_description', 'cart.empty_description') }}</p>
                            <a href="{{ route('frontend.search.console', ['lang' => $lang]) }}"
                               class="mt-4 inline-flex items-center justify-center gap-2 w-full
                                      px-4 py-2.5 bg-ink text-ivory
                                      font-mono text-[10px] font-bold tracking-[0.22em] uppercase
                                      hover:bg-amber hover:text-ink transition-colors">
                                <x-heroicon-s-magnifying-glass class="w-4 h-4" />
                                {{ ui_copy('search_start_button_label', 'search.start_button_label') }}
                            </a>
                        </div>
                    </div>
                </div>

                {{-- ── Account / sign-in ── --}}
                @auth
                <div
                    class="relative hidden sm:flex items-stretch border-l border-rule"
                    x-data="{ hovered: false }"
                    @mouseenter="hovered = true"
                    @mouseleave="hovered = false"
                    @focusin="hovered = true"
                    @focusout="if (!$el.contains($event.relatedTarget)) hovered = false"
                >
                    <a
                        href="{{ url('/'.$lang.'/account/dashboard') }}"
                        class="flex items-center gap-2 px-5
                               text-ink hover:bg-ink hover:text-ivory
                               transition-colors duration-150
                               focus-visible:outline-none focus-visible:bg-ink focus-visible:text-ivory"
                    >
                        <x-heroicon-o-user-circle class="w-5 h-5" aria-hidden="true" />
                        <span class="font-sans text-[11px] font-bold tracking-[0.14em] uppercase">{{ ui_copy('navbar_account_label', 'navbar.account_label') }}</span>
                    </a>

                    {{-- Account dropdown --}}
                    <div
                        x-show="hovered"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100 translate-y-0"
                        x-transition:leave-end="opacity-0 translate-y-1"
                        x-cloak
                        class="absolute top-full right-0 w-64 bg-paper border border-ink z-50"
                    >
                        <div class="px-5 py-3 bg-ink text-ivory">
                            <p class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase">
                                {{ ui_copy('navbar_account_title', 'navbar.account_title') }}
                            </p>
                        </div>
                        <ul class="divide-y divide-rule">
                            @foreach([
                                [url('/'.$lang.'/account/dashboard'), ui_copy('account_nav_dashboard', 'account.nav_dashboard')],
                                [url('/'.$lang.'/account/orders'),    ui_copy('account_nav_orders', 'account.nav_orders')],
                                [url('/'.$lang.'/account/addresses'), ui_copy('account_nav_addresses', 'account.nav_addresses')],
                                [url('/'.$lang.'/account/refunds'),   ui_copy('account_nav_refunds', 'account.nav_refunds')],
                            ] as [$acctHref, $acctLabel])
                                <li>
                                    <a href="{{ $acctHref }}"
                                       class="flex items-center justify-between px-5 py-3 text-sm text-ink hover:bg-ivory transition-colors">
                                        {{ $acctLabel }}
                                        <span class="font-mono text-[10px] text-ink-muted">→</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        <form method="POST" action="{{ route('frontend.auth.logout', ['lang' => $lang]) }}" class="border-t border-ink">
                            @csrf
                            <button type="submit"
                                    class="w-full flex items-center justify-between px-5 py-3 font-mono text-[11px] font-bold uppercase tracking-[0.16em] text-ink-muted hover:text-red-700 hover:bg-ivory transition-colors">
                                {{ ui_copy('navbar_sign_out_label', 'navbar.sign_out_label') }}
                                <x-heroicon-o-arrow-right-on-rectangle class="w-4 h-4" />
                            </button>
                        </form>
                    </div>
                </div>
                @else
                <button
                    @click="$dispatch('open-auth-modal')"
                    class="hidden sm:flex items-center gap-2 px-5 border-l border-rule
                           bg-ink text-ivory hover:bg-amber hover:text-ink
                           transition-colors duration-150
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber focus-visible:ring-offset-0"
                >
                    <x-heroicon-o-arrow-right-on-rectangle class="w-4 h-4" aria-hidden="true" />
                    <span class="font-sans text-[11px] font-bold tracking-[0.16em] uppercase">{{ ui_copy('navbar_sign_in_label', 'navbar.sign_in_label') }}</span>
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
                   @if($link['active']) aria-current="page" @endif
                   class="flex items-center gap-4 py-4 group">
                    <span class="font-sans text-sm font-bold uppercase tracking-[0.16em] {{ $link['active'] ? 'text-amber-ink' : 'text-ink' }}">
                        {{ $link['label'] }}
                    </span>
                    @if($link['active'])
                        <span class="ml-auto w-1.5 h-1.5 bg-amber shrink-0"></span>
                    @else
                        <x-heroicon-s-arrow-long-right class="w-4 h-4 text-ink-muted ml-auto group-hover:text-ink transition-colors" />
                    @endif
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
                <span class="bp-spec-mono">{{ count($mobileLanguages) }} options</span>
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
                {{ ui_copy('navbar_sign_in_register_label', 'navbar.sign_in_register_label') }}
            </button>
            @endguest
            @auth
            <a href="{{ url('/'.$lang.'/account/dashboard') }}" class="w-full bp-btn-outline">
                <x-heroicon-o-user-circle class="w-5 h-5" />
                {{ ui_copy('navbar_my_account_label', 'navbar.my_account_label') }}
            </a>
            @endauth
        </div>
    </nav>
</header>
