@php
    $lang     = app()->getLocale();
    $siteName = settings('general.site_name', 'OeParts');
    $tagline  = ui_copy('footer_site_tagline', 'footer.site_tagline');
    $phone    = settings('general.site_phone', '');
    $email    = settings('general.site_email', '');
    $hours    = settings_trans('contact.hours', 'MON–FRI · 09:00–18:00 CET');
    $showSocialInFooter = filter_var(settings('social_links.show_in_footer', true), FILTER_VALIDATE_BOOLEAN);
    $socialIconStyle    = settings('social_links.footer_icon_style', 'outlined');
    $socialBadgeClass   = $socialIconStyle === 'filled'
        ? 'bg-white/10 border border-white/30'
        : 'border border-white/30';
    $facebook  = settings('social_links.facebook_url', '');
    $instagram = settings('social_links.instagram_url', '');
    $twitter   = settings('social_links.twitter_url', '');
    $linkedin  = settings('social_links.linkedin_url', '');
    $youtube   = settings('social_links.youtube_url', '');
    $tiktok    = settings('social_links.tiktok_url', '');
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
            {{-- `group` sits on this OUTER block (not just the 64px icon link) so the
                 hover-triggered rotate/colour-swap responds anywhere across icon + gap
                 + wordmark — matching the navbar lockup (resources/views/components/
                 navbar.blade.php), where icon+wordmark+subline share one link and so
                 the whole box is one hover zone. Scoping `group` to only the icon left
                 a dead zone in the gap/wordmark where nothing happened on hover. --}}
            <div class="group flex items-start gap-6 max-w-2xl">
                {{-- Monogram mark — the ring/cross/dot HOVER colours are the exact same
                     tokens as the navbar mark's hover state (fill-amber / fill-ink /
                     stroke-amber / fill-ivory). The REST state differs (ivory ring
                     instead of the navbar's dark-ink ring), since the navbar's rest
                     colours assume a light background and this footer's is dark. The
                     corner badge is the ONE piece that can't just copy the navbar's
                     token verbatim: the navbar's hover badge is ink, invisible only
                     because that navbar sits on ivory — this footer's own background
                     IS ink, so the badge hovers to ivory instead (see its own comment
                     below) to stay visible rather than vanishing into the page. --}}
                <a href="{{ url('/'.$lang.'/') }}"
                   class="relative shrink-0 hidden sm:block focus-visible:outline-none"
                   aria-label="{{ $siteName }} · Home">
                    <div class="transition-transform duration-300 group-hover:rotate-[30deg]">
                        <svg viewBox="0 0 60 60" class="w-16 h-16" aria-hidden="true">
                            <path d="M30 3 L53 16 L53 44 L30 57 L7 44 L7 16 Z"
                                  class="fill-ivory group-hover:fill-amber transition-colors duration-200"/>
                            <path d="M30 13 L44.5 21.5 L44.5 38.5 L30 47 L15.5 38.5 L15.5 21.5 Z"
                                  class="fill-ink transition-colors duration-200"/>
                            <path d="M30 18 L30 42 M18 30 L42 30"
                                  class="stroke-ivory group-hover:stroke-amber transition-colors duration-200"
                                  stroke-width="2.5" stroke-linecap="square"/>
                            <circle cx="30" cy="30" r="3.2"
                                    class="fill-amber group-hover:fill-ivory transition-colors duration-200"/>
                        </svg>
                        {{-- Badge hover is ivory, NOT the navbar's ink — the navbar's hover
                             badge (ink) is only visible because that navbar sits on a light
                             background; this footer's own background IS ink, so copying that
                             value verbatim made the badge disappear into the page on hover. --}}
                        <span class="absolute -top-0.5 -right-0.5 w-2 h-2 bg-amber group-hover:bg-ivory transition-colors duration-200"></span>
                    </div>
                </a>

                <div class="min-w-0">
                    <a href="{{ url('/'.$lang.'/') }}" class="inline-block focus-visible:outline-none" aria-label="{{ $siteName }} · Home">
                        <x-brand-wordmark tone="dark" size="lg" as="h2" />
                    </a>
                    <p class="mt-4 text-[14px] text-white/70 leading-relaxed">
                        {{ $tagline }}
                    </p>
                    <p class="mt-4 font-mono text-[10px] tracking-[0.24em] uppercase text-ivory/50">
                        {{ preg_replace('/\s+·\s+/', ' ', $hours) }}
                    </p>
                </div>
            </div>

            {{-- Inline stats — spec ledger --}}
            <dl class="grid grid-cols-3 gap-0 border-l border-white/15 divide-x divide-white/15 shrink-0">
                <div class="px-5 py-2">
                    <dt class="bp-spec-light">{{ settings('footer.stat_parts_label') ?: __('footer.stat_parts_label') }}</dt>
                    <dd class="mt-1 font-mono text-2xl sm:text-3xl font-bold text-amber tabular-nums leading-none tracking-tight">{{ settings('footer.stat_parts', '1M+') }}</dd>
                </div>
                <div class="px-5 py-2">
                    <dt class="bp-spec-light">{{ settings('footer.stat_countries_label') ?: __('footer.stat_countries_label') }}</dt>
                    <dd class="mt-1 font-mono text-2xl sm:text-3xl font-bold text-amber tabular-nums leading-none tracking-tight">{{ settings('footer.stat_countries', '27') }}</dd>
                </div>
                <div class="px-5 py-2">
                    <dt class="bp-spec-light">{{ settings('footer.stat_languages_label') ?: __('footer.stat_languages_label') }}</dt>
                    <dd class="mt-1 font-mono text-2xl sm:text-3xl font-bold text-amber tabular-nums leading-none tracking-tight">{{ settings('footer.stat_languages', '05') }}</dd>
                </div>
            </dl>
        </div>

        {{-- ═══ Main 4-column grid ═══ --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-0 border-b border-white/15">

            {{-- Column 1 — Catalogue --}}
            <div class="sm:border-r border-white/15 py-10 sm:pr-8 lg:pr-10">
                <div class="flex items-baseline gap-3 mb-5">
                    <h3 class="bp-spec-light">{{ ui_copy('footer_catalogue', 'footer.catalogue') }}</h3>
                </div>
                <ul class="space-y-3">
                    @foreach([
                        [route('frontend.search.console', ['lang' => $lang]), ui_copy('footer_search_by_oem', 'footer.search_by_oem')],
                        [url('/'.$lang.'/brands'),  ui_copy('footer_browse_brands', 'footer.browse_brands')],
                        [url('/'.$lang.'/blog'),    ui_copy('footer_journal', 'footer.journal')],
                        [url('/'.$lang.'/contact'), ui_copy('nav_label_contact', 'navbar.label_contact')],
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
                    <h3 class="bp-spec-light">{{ ui_copy('footer_account_heading', 'footer.account_heading') }}</h3>
                </div>
                <ul class="space-y-3">
                    @auth
                        @foreach([
                            [url('/'.$lang.'/account/dashboard'), ui_copy('account_nav_dashboard', 'account.nav_dashboard')],
                            [url('/'.$lang.'/account/orders'),    ui_copy('account_nav_orders', 'account.nav_orders')],
                            [url('/'.$lang.'/account/addresses'), ui_copy('account_nav_addresses', 'account.nav_addresses')],
                            [url('/'.$lang.'/account/refunds'),   ui_copy('account_nav_refunds', 'account.nav_refunds')],
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
                                <span class="border-b border-transparent group-hover:border-amber pb-[1px]">{{ ui_copy('auth_sign_in', 'auth.sign_in') }}</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ url('/'.$lang.'/?auth=register') }}#register"
                               @click.prevent="$dispatch('open-auth-modal', { tab: 'register' })"
                               class="group inline-flex items-center gap-2.5 text-sm text-ivory/80 hover:text-amber transition-colors">
                                <span class="font-mono text-[10px] text-white/40 group-hover:text-amber transition-colors">→</span>
                                <span class="border-b border-transparent group-hover:border-amber pb-[1px]">{{ ui_copy('auth_register', 'auth.register') }}</span>
                            </a>
                        </li>
                    @endauth
                    <li>
                        <a href="{{ url('/'.$lang.'/cart') }}" class="group inline-flex items-center gap-2.5 text-sm text-ivory/80 hover:text-amber transition-colors">
                            <span class="font-mono text-[10px] text-white/40 group-hover:text-amber transition-colors">→</span>
                            <span class="border-b border-transparent group-hover:border-amber pb-[1px]">{{ ui_copy('footer_basket', 'footer.basket') }}</span>
                        </a>
                    </li>
                </ul>
            </div>

            {{-- Column 3 — Contact spec-sheet --}}
            <div class="sm:border-r border-white/15 py-10 sm:pr-8 lg:pr-10 lg:pl-10 border-t lg:border-t-0">
                <div class="flex items-baseline gap-3 mb-5">
                    <h3 class="bp-spec-light">{{ ui_copy('nav_label_contact', 'navbar.label_contact') }}</h3>
                </div>

                <dl class="space-y-4 text-sm">
                    <div>
                        <dt class="bp-spec-light text-[9px]">{{ ui_copy('footer_phone_label', 'footer.phone_label') }}</dt>
                        <dd class="mt-1">
                            <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}"
                               class="font-mono text-ivory hover:text-amber transition-colors tabular-nums">
                                {{ $phone ?: settings('general.site_phone', '+370 600 00000') }}
                            </a>
                        </dd>
                    </div>
                    <div>
                        <dt class="bp-spec-light text-[9px]">{{ ui_copy('footer_email_label', 'footer.email_label') }}</dt>
                        <dd class="mt-1">
                            <a href="mailto:{{ $email }}"
                               class="text-ivory hover:text-amber transition-colors">
                                {{ $email ?: settings('general.site_email', 'info@oeparts.lt') }}
                            </a>
                        </dd>
                    </div>
                    <div>
                        <dt class="bp-spec-light text-[9px]">{{ ui_copy('footer_hours_label', 'footer.hours_label') }}</dt>
                        <dd class="mt-1 font-mono text-ivory/80 text-[13px]">{{ $hours }}</dd>
                    </div>
                </dl>

                @if($showSocialInFooter && ($facebook || $instagram || $twitter || $linkedin || $youtube || $tiktok))
                <div class="mt-6 flex gap-2">
                    @if($facebook)
                    <a href="{{ $facebook }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center justify-center w-9 h-9 {{ $socialBadgeClass }} hover:bg-amber hover:border-amber hover:text-ink transition-colors"
                       aria-label="{{ ui_copy('footer_social_facebook', 'footer.social_facebook') }}">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z"/></svg>
                    </a>
                    @endif
                    @if($instagram)
                    <a href="{{ $instagram }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center justify-center w-9 h-9 {{ $socialBadgeClass }} hover:bg-amber hover:border-amber hover:text-ink transition-colors"
                       aria-label="{{ ui_copy('footer_social_instagram', 'footer.social_instagram') }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1" fill="currentColor" stroke="none"/></svg>
                    </a>
                    @endif
                    @if($twitter)
                    <a href="{{ $twitter }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center justify-center w-9 h-9 {{ $socialBadgeClass }} hover:bg-amber hover:border-amber hover:text-ink transition-colors"
                       aria-label="{{ ui_copy('footer_social_twitter', 'footer.social_twitter') }}">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                    </a>
                    @endif
                    @if($linkedin)
                    <a href="{{ $linkedin }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center justify-center w-9 h-9 {{ $socialBadgeClass }} hover:bg-amber hover:border-amber hover:text-ink transition-colors"
                       aria-label="{{ ui_copy('footer_social_linkedin', 'footer.social_linkedin') }}">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M4.98 3.5c0 1.381-1.11 2.5-2.48 2.5s-2.48-1.119-2.48-2.5c0-1.38 1.11-2.5 2.48-2.5s2.48 1.12 2.48 2.5zm.02 4.5h-5v16h5v-16zm7.982 0h-4.968v16h4.969v-8.399c0-4.67 6.029-5.052 6.029 0v8.399h4.988v-10.131c0-7.88-8.922-7.593-11.018-3.714v-2.155z"/></svg>
                    </a>
                    @endif
                    @if($youtube)
                    <a href="{{ $youtube }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center justify-center w-9 h-9 {{ $socialBadgeClass }} hover:bg-amber hover:border-amber hover:text-ink transition-colors"
                       aria-label="{{ ui_copy('footer_social_youtube', 'footer.social_youtube') }}">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.016 3.016 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                    </a>
                    @endif
                    @if($tiktok)
                    <a href="{{ $tiktok }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center justify-center w-9 h-9 {{ $socialBadgeClass }} hover:bg-amber hover:border-amber hover:text-ink transition-colors"
                       aria-label="{{ ui_copy('footer_social_tiktok', 'footer.social_tiktok') }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M14 4v10.5a3.5 3.5 0 1 1-3.5-3.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 4c0 3 2 5 5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                    @endif
                </div>
                @endif
            </div>

            {{-- Column 4 — Languages + payments --}}
            <div class="py-10 sm:pl-8 lg:pl-10 border-t sm:border-t-0">
                <div class="flex items-baseline gap-3 mb-5">
                    <h3 class="bp-spec-light">{{ ui_copy('footer_languages_heading', 'footer.languages_heading') }}</h3>
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

                <p class="bp-spec-light mb-3">{{ ui_copy('footer_payments_heading', 'footer.payments_heading') }}</p>
                <div class="flex flex-wrap gap-1.5">
                    @php
                        // settings() returns raw strings — an operator-saved
                        // list arrives JSON-encoded; the default is an array.
                        // Kept in sync with what checkout actually offers today
                        // (Airwallex Drop-in: card incl. Amex + Apple Pay +
                        // Google Pay, plus bank transfer/SEPA).
                        $footerPaymentsDefault = ['VISA', 'MASTERCARD', 'AMEX', 'APPLE PAY', 'GOOGLE PAY', 'BANK TRANSFER', 'SEPA'];
                        $footerPayments = settings('footer.payment_methods', $footerPaymentsDefault);
                        if (is_string($footerPayments)) {
                            $footerPayments = json_decode($footerPayments, true) ?: $footerPaymentsDefault;
                        }
                    @endphp
                    @foreach($footerPayments as $method)
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
                    <p class="font-sans text-[12px] font-bold uppercase tracking-[0.14em] text-ivory leading-tight">{{ ui_copy('footer_security_badge_text', 'footer.security_badge_text') }}</p>
                    <p class="font-mono text-[10px] text-ivory/50 tracking-[0.18em] uppercase mt-1">{{ ui_copy('footer_security_badge_subtext', 'footer.security_badge_subtext') }}</p>
                </div>
            </div>
            <div class="flex items-start gap-3 p-5">
                <div class="w-8 h-8 border border-amber/60 flex items-center justify-center shrink-0 mt-0.5">
                    <x-heroicon-o-truck class="w-4 h-4 text-amber" aria-hidden="true" />
                </div>
                <div class="min-w-0">
                    <p class="font-sans text-[12px] font-bold uppercase tracking-[0.14em] text-ivory leading-tight">{{ ui_copy('footer_shipping_badge_text', 'footer.shipping_badge_text') }}</p>
                    <p class="font-mono text-[10px] text-ivory/50 tracking-[0.18em] uppercase mt-1">{{ ui_copy('footer_shipping_badge_subtext', 'footer.shipping_badge_subtext') }}</p>
                </div>
            </div>
            <div class="flex items-start gap-3 p-5">
                <div class="w-8 h-8 border border-amber/60 flex items-center justify-center shrink-0 mt-0.5">
                    <x-heroicon-o-arrow-path class="w-4 h-4 text-amber" aria-hidden="true" />
                </div>
                <div class="min-w-0">
                    <p class="font-sans text-[12px] font-bold uppercase tracking-[0.14em] text-ivory leading-tight">{{ ui_copy('footer_returns_badge_text', 'footer.returns_badge_text') }}</p>
                    <p class="font-mono text-[10px] text-ivory/50 tracking-[0.18em] uppercase mt-1">{{ ui_copy('footer_returns_badge_subtext', 'footer.returns_badge_subtext') }}</p>
                </div>
            </div>
            <div class="flex items-start gap-3 p-5">
                <div class="w-8 h-8 border border-amber/60 flex items-center justify-center shrink-0 mt-0.5">
                    <x-heroicon-s-shield-check class="w-4 h-4 text-amber" aria-hidden="true" />
                </div>
                <div class="min-w-0">
                    <p class="font-sans text-[12px] font-bold uppercase tracking-[0.14em] text-ivory leading-tight">{{ ui_copy('footer_oem_badge_text', 'footer.oem_badge_text') }}</p>
                    <p class="font-mono text-[10px] text-ivory/50 tracking-[0.18em] uppercase mt-1">{{ ui_copy('footer_oem_badge_subtext', 'footer.oem_badge_subtext') }}</p>
                </div>
            </div>
        </div>

        {{-- ═══ Colophon footer ═══ --}}
        <div class="py-5 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="font-mono text-[10px] font-bold tracking-[0.26em] uppercase text-amber">©</span>
                <span class="font-mono text-[11px] text-ivory/50 tracking-[0.12em] uppercase tabular-nums">
                    {{ $year }} · {{ strtoupper($siteName) }} · {{ ui_copy('footer_all_rights_reserved', 'footer.all_rights_reserved') }}
                </span>
            </div>
            <nav class="flex flex-wrap gap-x-5 gap-y-2 font-mono text-[11px] uppercase tracking-[0.18em]" aria-label="Legal">
                <a href="{{ url('/'.$lang.'/privacy-policy') }}" class="text-ivory/60 hover:text-amber border-b border-transparent hover:border-amber pb-0.5 transition-colors">{{ ui_copy('footer_privacy', 'footer.privacy') }}</a>
                <a href="{{ url('/'.$lang.'/terms-of-service') }}" class="text-ivory/60 hover:text-amber border-b border-transparent hover:border-amber pb-0.5 transition-colors">{{ ui_copy('footer_terms', 'footer.terms') }}</a>
                <a href="{{ url('/'.$lang.'/returns-policy') }}" class="text-ivory/60 hover:text-amber border-b border-transparent hover:border-amber pb-0.5 transition-colors">{{ ui_copy('footer_returns', 'footer.returns') }}</a>
                <a href="{{ url('/'.$lang.'/shipping-information') }}" class="text-ivory/60 hover:text-amber border-b border-transparent hover:border-amber pb-0.5 transition-colors">{{ ui_copy('footer_shipping_info', 'footer.shipping_info') }}</a>
                <a href="#cookies" @click.prevent="$dispatch('open-cookie-consent')" class="text-ivory/60 hover:text-amber border-b border-transparent hover:border-amber pb-0.5 transition-colors">{{ ui_copy('footer_cookies', 'footer.cookies') }}</a>
                <a href="{{ url('/'.$lang.'/impressum') }}" class="text-ivory/60 hover:text-amber border-b border-transparent hover:border-amber pb-0.5 transition-colors">{{ ui_copy('footer_impressum', 'footer.impressum') }}</a>
                <a href="{{ route('frontend.sitemap', ['lang' => $lang]) }}" class="text-ivory/60 hover:text-amber border-b border-transparent hover:border-amber pb-0.5 transition-colors">{{ ui_copy('footer_sitemap', 'footer.sitemap') }}</a>
            </nav>
        </div>
    </div>
</footer>
