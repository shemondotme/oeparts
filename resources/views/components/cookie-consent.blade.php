{{-- Cookie Consent Banner — Industrial Blueprint (2026) --}}
@props(['enabled' => true])

@if($enabled)
<div
    x-data="{
        visible: !localStorage.getItem('cookie_consent_accepted'),
        accept() {
            localStorage.setItem('cookie_consent_accepted', '1');
            this.visible = false;
            $dispatch('cookie-consent-accepted');
        },
        decline() {
            localStorage.setItem('cookie_consent_declined', '1');
            this.visible = false;
            $dispatch('cookie-consent-declined');
        }
    }"
    x-on:open-cookie-consent.window="visible = true"
    x-show="visible"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="translate-x-6 opacity-0"
    x-transition:enter-end="translate-x-0 opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="translate-x-0 opacity-100"
    x-transition:leave-end="translate-x-6 opacity-0"
    class="fixed bottom-6 right-6 z-50"
    style="display: none;"
    role="region"
    aria-label="Cookie consent"
>
    {{-- ── Banner panel ───────────────────────────────────────────── --}}
    <div class="relative w-[420px] max-w-[calc(100vw-3rem)] bg-ink text-ivory border border-ink overflow-hidden"
         style="box-shadow: 8px 8px 0 rgba(241,145,58,1);">

        {{-- Subtle grid background --}}
        <div class="absolute inset-0 bg-grid-navy bg-grid-md opacity-60 pointer-events-none" aria-hidden="true"></div>

        {{-- Top doc strip --}}
        <div class="relative flex items-center justify-between px-5 py-2.5 border-b border-white/15 bg-black/20">
            <div class="flex items-center gap-2.5">
                <span class="w-2.5 h-2.5 bg-amber"></span>
                <span class="font-mono text-[10px] font-bold tracking-[0.26em] uppercase text-amber">
                    Consent · 01
                </span>
            </div>
            <span class="font-mono text-[9px] tracking-[0.22em] uppercase text-ivory/50">
                GDPR · EU-{{ now()->format('Y') }}
            </span>
        </div>

        <div class="relative p-6">
            {{-- Title --}}
            <h3 class="font-display text-2xl font-extrabold text-ivory leading-[1.05] tracking-[-0.02em]">
                {{ __('Cookies on file') }}<span class="text-amber">.</span>
            </h3>

            {{-- Spec line --}}
            <div class="mt-3 flex items-center gap-2 font-mono text-[10px] tracking-[0.22em] uppercase text-ivory/50">
                <span>Purpose</span>
                <span class="flex-1 border-t border-dashed border-ivory/20"></span>
                <span class="text-amber">Operational · Analytics</span>
            </div>

            {{-- Description --}}
            <p class="mt-4 text-sm text-ivory/75 leading-relaxed">
                {{ __('We use cookies to keep the site secure, remember your session, and measure how pages perform. Necessary cookies are always on; everything else is opt-in.') }}
            </p>

            {{-- Actions --}}
            <div class="mt-6 grid grid-cols-2 gap-2.5">
                <button
                    @click="decline()"
                    type="button"
                    class="group flex items-center justify-center gap-2 px-4 py-3 border border-ivory/50
                           font-mono text-[11px] font-bold tracking-[0.22em] uppercase text-ivory
                           hover:border-amber hover:text-amber transition-colors"
                    aria-label="Decline all cookies"
                >
                    <x-heroicon-s-no-symbol class="w-3.5 h-3.5" />
                    {{ __('Decline') }}
                </button>

                <button
                    @click="accept()"
                    type="button"
                    class="group flex items-center justify-center gap-2 px-4 py-3 bg-amber border border-amber
                           font-mono text-[11px] font-bold tracking-[0.22em] uppercase text-ink
                           hover:bg-ivory hover:border-ivory transition-colors"
                    aria-label="Accept all cookies"
                >
                    <x-heroicon-s-check class="w-3.5 h-3.5" />
                    {{ __('Accept all') }}
                </button>
            </div>

            {{-- Secondary row --}}
            <div class="mt-4 flex items-center justify-between gap-3 pt-4 border-t border-white/10">
                <button
                    type="button"
                    @click="$dispatch('open-cookie-preferences')"
                    class="inline-flex items-center gap-1.5 font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ivory/70
                           border-b border-dashed border-ivory/30 hover:text-amber hover:border-amber transition-colors pb-0.5"
                >
                    <x-heroicon-o-adjustments-horizontal class="w-3.5 h-3.5" />
                    {{ __('Customize') }}
                </button>
                <a href="{{ route('frontend.page', ['lang' => app()->getLocale(), 'slug' => 'cookie-policy']) }}"
                   class="inline-flex items-center gap-1.5 font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-amber
                          hover:text-ivory transition-colors">
                    {{ __('Policy') }}
                    <x-heroicon-s-arrow-long-right class="w-3 h-3" />
                </a>
            </div>

            {{-- Trust strip --}}
            <div class="mt-5 grid grid-cols-3 gap-0 border border-white/15 divide-x divide-white/10">
                <div class="px-2.5 py-2 flex items-center gap-1.5">
                    <x-heroicon-s-lock-closed class="w-3 h-3 text-amber shrink-0" />
                    <span class="font-mono text-[9px] font-bold tracking-[0.18em] uppercase text-ivory/65 truncate">GDPR</span>
                </div>
                <div class="px-2.5 py-2 flex items-center gap-1.5">
                    <x-heroicon-s-shield-check class="w-3 h-3 text-amber shrink-0" />
                    <span class="font-mono text-[9px] font-bold tracking-[0.18em] uppercase text-ivory/65 truncate">Secure</span>
                </div>
                <div class="px-2.5 py-2 flex items-center gap-1.5">
                    <x-heroicon-s-arrow-path class="w-3 h-3 text-amber shrink-0" />
                    <span class="font-mono text-[9px] font-bold tracking-[0.18em] uppercase text-ivory/65 truncate">Revocable</span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════
     Cookie preferences modal — Industrial Blueprint
═══════════════════════════════════════════════════════════════════ --}}
<div
    x-data="{
        open: false,
        preferences: {
            necessary: true,
            analytics: false,
            marketing: false
        },
        save() {
            localStorage.setItem('cookie_preferences', JSON.stringify(this.preferences));
            localStorage.setItem('cookie_consent_accepted', '1');
            this.open = false;
            $dispatch('cookie-preferences-saved', this.preferences);
        }
    }"
    x-on:open-cookie-preferences.window="open = true"
    x-show="open"
    x-cloak
    @keydown.escape.window="open = false"
    class="fixed inset-0 z-[60] overflow-y-auto"
    aria-labelledby="cookie-pref-title"
    role="dialog"
    aria-modal="true"
    style="display: none;"
>
    {{-- Backdrop --}}
    <div class="fixed inset-0 bg-ink/80 backdrop-blur-sm transition-opacity" @click="open = false"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"></div>

    <div class="flex items-center justify-center min-h-screen px-4 py-8">
        {{-- Modal panel --}}
        <div class="relative w-full max-w-xl bg-paper text-ink border border-ink overflow-hidden"
             style="box-shadow: 10px 10px 0 rgba(20,22,29,1);"
             x-trap.noscroll.inert="open"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 translate-y-4">

            {{-- Doc header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-ink bg-ivory-alt">
                <div class="flex items-center gap-3">
                    <span class="w-2 h-8 bg-amber"></span>
                    <div>
                        <span class="bp-spec text-amber-ink">Consent · Detail</span>
                        <h3 id="cookie-pref-title" class="font-display text-lg font-extrabold text-ink tracking-[-0.02em] leading-tight">
                            {{ __('Cookie preferences') }}
                        </h3>
                    </div>
                </div>
                <button type="button" @click="open = false"
                        class="w-9 h-9 flex items-center justify-center border border-ink text-ink
                               hover:bg-ink hover:text-ivory transition-colors"
                        aria-label="Close">
                    <x-heroicon-s-x-mark class="w-4 h-4" />
                </button>
            </div>

            {{-- Body --}}
            <div class="p-6 space-y-3">

                <p class="text-sm text-body leading-relaxed mb-4">
                    {{ __('Choose which categories of cookies this browser may store. Necessary cookies cannot be disabled — they keep the cart, session and basic security working.') }}
                </p>

                {{-- Necessary --}}
                <div class="border border-ink bg-paper">
                    <div class="flex items-stretch">
                        <div class="w-1.5 bg-emerald-600"></div>
                        <div class="flex-1 flex items-center gap-4 p-4">
                            <div class="w-10 h-10 border border-ink bg-ivory-alt flex items-center justify-center shrink-0">
                                <x-heroicon-s-shield-check class="w-4 h-4 text-emerald-700" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-display text-sm font-bold text-ink leading-tight">
                                        {{ __('Necessary') }}
                                    </span>
                                    <span class="font-mono text-[9px] font-bold tracking-[0.2em] uppercase text-emerald-700 px-1.5 py-0.5 border border-emerald-600 bg-emerald-50">
                                        {{ __('Required') }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-ink-muted leading-relaxed">
                                    {{ __('Session, cart, CSRF, locale & security. Always on.') }}
                                </p>
                            </div>
                            <div class="shrink-0 font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-emerald-700">
                                {{ __('On') }}
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Analytics --}}
                <label class="block border border-ink bg-paper cursor-pointer
                              hover:bg-ivory-alt transition-colors"
                       :class="preferences.analytics ? 'bg-ivory-alt' : ''">
                    <div class="flex items-stretch">
                        <div class="w-1.5" :class="preferences.analytics ? 'bg-amber' : 'bg-ink-muted/30'"></div>
                        <div class="flex-1 flex items-center gap-4 p-4">
                            <div class="w-10 h-10 border border-ink bg-ivory-alt flex items-center justify-center shrink-0">
                                <x-heroicon-s-chart-bar class="w-4 h-4 text-ink" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <span class="font-display text-sm font-bold text-ink leading-tight">
                                    {{ __('Analytics') }}
                                </span>
                                <p class="mt-1 text-xs text-ink-muted leading-relaxed">
                                    {{ __('Anonymous page-view and performance metrics.') }}
                                </p>
                            </div>
                            {{-- Toggle --}}
                            <div class="relative shrink-0">
                                <input type="checkbox" x-model="preferences.analytics" class="sr-only peer" />
                                <div class="w-11 h-6 border border-ink bg-paper peer-checked:bg-amber peer-checked:border-ink transition-colors"></div>
                                <div class="absolute top-[2px] left-[2px] w-[18px] h-[18px] bg-ink peer-checked:translate-x-[18px] transition-transform"
                                     :class="preferences.analytics ? 'translate-x-[18px]' : 'translate-x-0'"></div>
                            </div>
                        </div>
                    </div>
                </label>

                {{-- Marketing --}}
                <label class="block border border-ink bg-paper cursor-pointer
                              hover:bg-ivory-alt transition-colors"
                       :class="preferences.marketing ? 'bg-ivory-alt' : ''">
                    <div class="flex items-stretch">
                        <div class="w-1.5" :class="preferences.marketing ? 'bg-amber' : 'bg-ink-muted/30'"></div>
                        <div class="flex-1 flex items-center gap-4 p-4">
                            <div class="w-10 h-10 border border-ink bg-ivory-alt flex items-center justify-center shrink-0">
                                <x-heroicon-s-megaphone class="w-4 h-4 text-ink" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <span class="font-display text-sm font-bold text-ink leading-tight">
                                    {{ __('Marketing') }}
                                </span>
                                <p class="mt-1 text-xs text-ink-muted leading-relaxed">
                                    {{ __('Retargeting and campaign attribution cookies.') }}
                                </p>
                            </div>
                            <div class="relative shrink-0">
                                <input type="checkbox" x-model="preferences.marketing" class="sr-only peer" />
                                <div class="w-11 h-6 border border-ink bg-paper peer-checked:bg-amber peer-checked:border-ink transition-colors"></div>
                                <div class="absolute top-[2px] left-[2px] w-[18px] h-[18px] bg-ink transition-transform"
                                     :class="preferences.marketing ? 'translate-x-[18px]' : 'translate-x-0'"></div>
                            </div>
                        </div>
                    </div>
                </label>
            </div>

            {{-- Footer --}}
            <div class="px-6 py-4 border-t border-ink bg-ivory-alt flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                <a href="{{ route('frontend.page', ['lang' => app()->getLocale(), 'slug' => 'cookie-policy']) }}"
                   class="inline-flex items-center gap-1.5 font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted
                          border-b border-dashed border-ink-muted hover:text-amber-ink hover:border-amber transition-colors pb-0.5 self-start">
                    <x-heroicon-o-document-text class="w-3.5 h-3.5" />
                    {{ __('Read full policy') }}
                </a>
                <div class="flex items-center gap-2.5">
                    <button type="button" @click="open = false"
                            class="px-4 py-2.5 border border-ink text-ink font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                                   hover:bg-ink hover:text-ivory transition-colors">
                        {{ __('Cancel') }}
                    </button>
                    <button type="button" @click="save()"
                            class="inline-flex items-center gap-2 px-4 py-2.5 bg-ink border border-ink text-ivory
                                   font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                                   hover:bg-amber hover:text-ink hover:border-amber transition-colors">
                        <x-heroicon-s-check class="w-3.5 h-3.5" />
                        {{ __('Save') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
