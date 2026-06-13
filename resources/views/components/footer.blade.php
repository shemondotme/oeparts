@php
    $lang     = app()->getLocale();
    $siteName = settings('general.site_name', 'OeParts');
    $tagline  = settings('general.site_tagline', 'The central hub for genuine OEM auto parts in Europe.');
    $phone    = settings('contact.phone', '');
    $email    = settings('contact.email', '');
    $hours    = settings('contact.business_hours', 'MON–FRI · 09:00–18:00 CET');
    $facebook = settings('contact.facebook_url', '');
    $linkedin = settings('contact.linkedin_url', '');
    $year     = date('Y');

    $langSwitchUrl = function($code) {
        $route = request()->route();
        if (!$route || !$route->getName()) {
            return url('/'.$code.'/');
        }
        $params = $route->parameters();
        $params['lang'] = $code;
        try {
            return route($route->getName(), $params);
        } catch (\Exception $e) {
            $path = request()->path();
            $newPath = preg_replace('#^(en|de|lt|fr|es)(/|$)#', $code . '$2', $path);
            return url('/'.$newPath);
        }
    };
@endphp

{{-- ══════════════════════════════════════════════════════════════════════
     INDUSTRIAL BLUEPRINT FOOTER
     Document colophon — technical, authoritative, spec-sheet closing page
     ══════════════════════════════════════════════════════════════════ --}}
<footer x-data class="relative bg-ink text-ivory overflow-hidden" role="contentinfo">

    {{-- Blueprint grid texture --}}
    <div class="absolute inset-0 bg-grid-navy bg-grid-md opacity-60 pointer-events-none" aria-hidden="true"></div>

    <div class="relative z-10 max-w-[1440px] mx-auto px-4 sm:px-6">

        {{-- ═══ Colophon header strip ═══ --}}
        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-10 py-12 border-b border-white/15">

            {{-- Brand monogram + tagline --}}
            <div class="flex items-start gap-6 max-w-2xl">
                {{-- Monogram mark --}}
                <div class="relative shrink-0 hidden sm:block">
                    <svg viewBox="0 0 60 60" class="w-16 h-16" aria-hidden="true">
                        <path d="M30 3 L53 16 L53 44 L30 57 L7 44 L7 16 Z" fill="#F59E0B"/>
                        <path d="M30 13 L44.5 21.5 L44.5 38.5 L30 47 L15.5 38.5 L15.5 21.5 Z" fill="#0B1A29"/>
                        <path d="M30 18 L30 42 M18 30 L42 30" stroke="#F4EFE1" stroke-width="2.5" stroke-linecap="square"/>
                        <circle cx="30" cy="30" r="3.2" fill="#F59E0B"/>
                    </svg>
                    <span class="absolute -top-0.5 -right-0.5 w-2 h-2 bg-ivory"></span>
                </div>

                <div class="min-w-0">
                    <p class="bp-spec-light mb-3">99 · COLOPHON · OEPARTS/EU</p>
                    <h2 class="font-display text-4xl sm:text-5xl font-extrabold tracking-[-0.03em] leading-[0.95]">
                        {{ $siteName }}<span class="text-amber">.</span>
                    </h2>
                    <p class="mt-4 text-[14px] text-white/70 leading-relaxed">
                        {{ $tagline }}
                    </p>
                    <p class="mt-4 inline-flex items-center gap-2 font-mono text-[10px] tracking-[0.24em] uppercase text-ivory/50">
                        <span class="w-1.5 h-1.5 bg-emerald-500 inline-block"></span>
                        Operational · {{ preg_replace('/\s+·\s+/', ' ', $hours) }}
                    </p>
                </div>
            </div>

            {{-- Inline stats — spec ledger --}}
            <dl class="grid grid-cols-3 gap-0 border-l border-white/15 divide-x divide-white/15 shrink-0">
                <div class="px-5 py-2">
                    <dt class="bp-spec-light">Parts</dt>
                    <dd class="mt-1 font-mono text-2xl sm:text-3xl font-bold text-amber tabular-nums leading-none tracking-tight">{{ settings('footer.stat_parts', '1M+') }}</dd>
                </div>
                <div class="px-5 py-2">
                    <dt class="bp-spec-light">Countries</dt>
                    <dd class="mt-1 font-mono text-2xl sm:text-3xl font-bold text-amber tabular-nums leading-none tracking-tight">{{ settings('footer.stat_countries', '27') }}</dd>
                </div>
                <div class="px-5 py-2">
                    <dt class="bp-spec-light">Languages</dt>
                    <dd class="mt-1 font-mono text-2xl sm:text-3xl font-bold text-amber tabular-nums leading-none tracking-tight">{{ settings('footer.stat_languages', '05') }}</dd>
                </div>
            </dl>
        </div>

        {{-- ═══ Main 4-column grid ═══ --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-0 border-b border-white/15">

            {{-- Column 1 — Catalogue --}}
            <div class="sm:border-r border-white/15 py-10 sm:pr-8 lg:pr-10">
                <div class="flex items-baseline gap-3 mb-5">
                    <span class="font-mono text-[10px] font-bold tracking-[0.22em] text-amber">01</span>
                    <h3 class="bp-spec-light">{{ __('Catalogue') }}</h3>
                </div>
                <ul class="space-y-3">
                    @foreach([
                        [route('frontend.search.console', ['lang' => $lang]), __('Search by OEM')],
                        [url('/'.$lang.'/brands'),  __('Browse Brands')],
                        [url('/'.$lang.'/blog'),    __('Journal')],
                        [url('/'.$lang.'/contact'), __('Contact')],
                    ] as [$href, $label])
                        <li>
                            <a href="{{ $href }}"
                               class="group inline-flex items-center gap-2.5 text-sm text-ivory/80 hover:text-amber transition-colors">
                                <span class="font-mono text-[10px] text-white/40 group-hover:text-amber transition-colors">→</span>
                                <span class="border-b border-transparent group-hover:border-amber pb-[1px]">{{ $label }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Column 2 — Account --}}
            <div class="lg:border-r border-white/15 py-10 sm:pl-8 lg:pr-10 lg:pl-10 border-t sm:border-t-0">
                <div class="flex items-baseline gap-3 mb-5">
                    <span class="font-mono text-[10px] font-bold tracking-[0.22em] text-amber">02</span>
                    <h3 class="bp-spec-light">{{ __('Account') }}</h3>
                </div>
                <ul class="space-y-3">
                    @auth
                        @foreach([
                            [url('/'.$lang.'/account/dashboard'), __('Dashboard')],
                            [url('/'.$lang.'/account/orders'),    __('Orders')],
                            [url('/'.$lang.'/account/addresses'), __('Addresses')],
                            [url('/'.$lang.'/account/refunds'),   __('Refunds')],
                        ] as [$href, $label])
                            <li>
                                <a href="{{ $href }}" class="group inline-flex items-center gap-2.5 text-sm text-ivory/80 hover:text-amber transition-colors">
                                    <span class="font-mono text-[10px] text-white/40 group-hover:text-amber transition-colors">→</span>
                                    <span class="border-b border-transparent group-hover:border-amber pb-[1px]">{{ $label }}</span>
                                </a>
                            </li>
                        @endforeach
                    @else
                        <li>
                            <a href="{{ url('/'.$lang.'/?auth=signin') }}#signin"
                               @click.prevent="$dispatch('open-auth-modal')"
                               class="group inline-flex items-center gap-2.5 text-sm text-ivory/80 hover:text-amber transition-colors">
                                <span class="font-mono text-[10px] text-white/40 group-hover:text-amber transition-colors">→</span>
                                <span class="border-b border-transparent group-hover:border-amber pb-[1px]">{{ __('Sign in') }}</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ url('/'.$lang.'/?auth=register') }}#register"
                               @click.prevent="$dispatch('open-auth-modal', { tab: 'register' })"
                               class="group inline-flex items-center gap-2.5 text-sm text-ivory/80 hover:text-amber transition-colors">
                                <span class="font-mono text-[10px] text-white/40 group-hover:text-amber transition-colors">→</span>
                                <span class="border-b border-transparent group-hover:border-amber pb-[1px]">{{ __('Register') }}</span>
                            </a>
                        </li>
                    @endauth
                    <li>
                        <a href="{{ url('/'.$lang.'/cart') }}" class="group inline-flex items-center gap-2.5 text-sm text-ivory/80 hover:text-amber transition-colors">
                            <span class="font-mono text-[10px] text-white/40 group-hover:text-amber transition-colors">→</span>
                            <span class="border-b border-transparent group-hover:border-amber pb-[1px]">{{ __('Basket') }}</span>
                        </a>
                    </li>
                </ul>
            </div>

            {{-- Column 3 — Contact spec-sheet --}}
            <div class="sm:border-r border-white/15 py-10 sm:pr-8 lg:pr-10 lg:pl-10 border-t lg:border-t-0">
                <div class="flex items-baseline gap-3 mb-5">
                    <span class="font-mono text-[10px] font-bold tracking-[0.22em] text-amber">03</span>
                    <h3 class="bp-spec-light">{{ __('Contact') }}</h3>
                </div>

                <dl class="space-y-4 text-sm">
                    <div>
                        <dt class="bp-spec-light text-[9px]">{{ __('Phone') }}</dt>
                        <dd class="mt-1">
                            <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}"
                               class="font-mono text-ivory hover:text-amber transition-colors tabular-nums">
                                {{ $phone ?: settings('contact.phone', '+370 600 00000') }}
                            </a>
                        </dd>
                    </div>
                    <div>
                        <dt class="bp-spec-light text-[9px]">{{ __('Email') }}</dt>
                        <dd class="mt-1">
                            <a href="mailto:{{ $email }}"
                               class="text-ivory hover:text-amber transition-colors">
                                {{ $email ?: settings('contact.email', 'info@oeparts.lt') }}
                            </a>
                        </dd>
                    </div>
                    <div>
                        <dt class="bp-spec-light text-[9px]">{{ __('Hours') }}</dt>
                        <dd class="mt-1 font-mono text-ivory/80 text-[13px]">{{ $hours }}</dd>
                    </div>
                </dl>

                @if($facebook || $linkedin)
                <div class="mt-6 flex gap-2">
                    @if($facebook)
                    <a href="{{ $facebook }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center justify-center w-9 h-9 border border-white/30 hover:bg-amber hover:border-amber hover:text-ink transition-colors"
                       aria-label="Facebook">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z"/></svg>
                    </a>
                    @endif
                    @if($linkedin)
                    <a href="{{ $linkedin }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center justify-center w-9 h-9 border border-white/30 hover:bg-amber hover:border-amber hover:text-ink transition-colors"
                       aria-label="LinkedIn">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M4.98 3.5c0 1.381-1.11 2.5-2.48 2.5s-2.48-1.119-2.48-2.5c0-1.38 1.11-2.5 2.48-2.5s2.48 1.12 2.48 2.5zm.02 4.5h-5v16h5v-16zm7.982 0h-4.968v16h4.969v-8.399c0-4.67 6.029-5.052 6.029 0v8.399h4.988v-10.131c0-7.88-8.922-7.593-11.018-3.714v-2.155z"/></svg>
                    </a>
                    @endif
                </div>
                @endif
            </div>

            {{-- Column 4 — Languages + payments --}}
            <div class="py-10 sm:pl-8 lg:pl-10 border-t sm:border-t-0">
                <div class="flex items-baseline gap-3 mb-5">
                    <span class="font-mono text-[10px] font-bold tracking-[0.22em] text-amber">04</span>
                    <h3 class="bp-spec-light">{{ __('Languages') }}</h3>
                </div>

                <div class="grid grid-cols-5 gap-1 mb-8">
                    @foreach(['en' => 'EN', 'de' => 'DE', 'lt' => 'LT', 'fr' => 'FR', 'es' => 'ES'] as $code => $label)
                        <a href="{{ $langSwitchUrl($code) }}"
                           class="inline-flex items-center justify-center h-10 font-mono text-[11px] font-bold tracking-[0.14em]
                                  border transition-colors
                                  {{ $code === $lang
                                      ? 'bg-amber text-ink border-amber'
                                      : 'bg-transparent text-ivory/70 border-white/20 hover:border-amber hover:text-amber' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>

                <p class="bp-spec-light mb-3">{{ __('Payments') }}</p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach(settings('footer.payment_methods', ['VISA', 'MASTERCARD', 'APPLE PAY', 'GOOGLE PAY', 'SEPA', 'BANK TRANSFER']) as $method)
                        <span class="inline-flex items-center h-8 px-3 border border-white/25 font-mono text-[10px] font-bold tracking-[0.16em] text-ivory/80 whitespace-nowrap">
                            {{ $method }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ═══ Trust row — technical badges ═══ --}}
        <div class="grid grid-cols-2 md:grid-cols-4 border-b border-white/15 divide-x divide-white/15">
            <div class="flex items-start gap-3 p-5">
                <div class="w-8 h-8 border border-amber/60 flex items-center justify-center shrink-0 mt-0.5">
                    <x-heroicon-s-lock-closed class="w-4 h-4 text-amber" aria-hidden="true" />
                </div>
                <div class="min-w-0">
                    <p class="font-sans text-[12px] font-bold uppercase tracking-[0.14em] text-ivory leading-tight">{{ settings('footer.security_badge_text', 'SSL Encrypted') }}</p>
                    <p class="font-mono text-[10px] text-ivory/50 tracking-[0.18em] uppercase mt-1">{{ settings('footer.security_badge_subtext', 'TLS 1.3') }}</p>
                </div>
            </div>
            <div class="flex items-start gap-3 p-5">
                <div class="w-8 h-8 border border-amber/60 flex items-center justify-center shrink-0 mt-0.5">
                    <x-heroicon-o-truck class="w-4 h-4 text-amber" aria-hidden="true" />
                </div>
                <div class="min-w-0">
                    <p class="font-sans text-[12px] font-bold uppercase tracking-[0.14em] text-ivory leading-tight">{{ settings('footer.shipping_badge_text', 'EU-Wide Despatch') }}</p>
                    <p class="font-mono text-[10px] text-ivory/50 tracking-[0.18em] uppercase mt-1">{{ settings('footer.shipping_badge_subtext', 'DHL · DPD · GLS') }}</p>
                </div>
            </div>
            <div class="flex items-start gap-3 p-5">
                <div class="w-8 h-8 border border-amber/60 flex items-center justify-center shrink-0 mt-0.5">
                    <x-heroicon-o-arrow-path class="w-4 h-4 text-amber" aria-hidden="true" />
                </div>
                <div class="min-w-0">
                    <p class="font-sans text-[12px] font-bold uppercase tracking-[0.14em] text-ivory leading-tight">{{ settings('footer.returns_badge_text', 'Return Window') }}</p>
                    <p class="font-mono text-[10px] text-ivory/50 tracking-[0.18em] uppercase mt-1">{{ settings('footer.returns_badge_subtext', '14 Days') }}</p>
                </div>
            </div>
            <div class="flex items-start gap-3 p-5">
                <div class="w-8 h-8 border border-amber/60 flex items-center justify-center shrink-0 mt-0.5">
                    <x-heroicon-s-shield-check class="w-4 h-4 text-amber" aria-hidden="true" />
                </div>
                <div class="min-w-0">
                    <p class="font-sans text-[12px] font-bold uppercase tracking-[0.14em] text-ivory leading-tight">{{ settings('footer.oem_badge_text', 'Genuine OEM') }}</p>
                    <p class="font-mono text-[10px] text-ivory/50 tracking-[0.18em] uppercase mt-1">{{ settings('footer.oem_badge_subtext', 'Verified Source') }}</p>
                </div>
            </div>
        </div>

        {{-- ═══ Colophon footer ═══ --}}
        <div class="py-5 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="font-mono text-[10px] font-bold tracking-[0.26em] uppercase text-amber">©</span>
                <span class="font-mono text-[11px] text-ivory/50 tracking-[0.12em] uppercase tabular-nums">
                    {{ $year }} · {{ strtoupper($siteName) }} · ALL RIGHTS RESERVED
                </span>
            </div>
            <nav class="flex flex-wrap gap-x-5 gap-y-2 font-mono text-[11px] uppercase tracking-[0.18em]" aria-label="Legal">
                <a href="{{ url('/'.$lang.'/privacy-policy') }}" class="text-ivory/60 hover:text-amber border-b border-transparent hover:border-amber pb-0.5 transition-colors">Privacy</a>
                <a href="{{ url('/'.$lang.'/terms-of-service') }}" class="text-ivory/60 hover:text-amber border-b border-transparent hover:border-amber pb-0.5 transition-colors">Terms</a>
                <a href="#cookies" @click.prevent="$dispatch('open-cookie-consent')" class="text-ivory/60 hover:text-amber border-b border-transparent hover:border-amber pb-0.5 transition-colors">Cookies</a>
                <a href="{{ route('frontend.sitemap', ['lang' => $lang]) }}" class="text-ivory/60 hover:text-amber border-b border-transparent hover:border-amber pb-0.5 transition-colors">Sitemap</a>
            </nav>
        </div>
    </div>
</footer>
