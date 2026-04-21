{{-- Cookie Consent Banner - Premium Side Card (2026) --}}
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
    x-show="visible"
    x-transition:enter="transition ease-out duration-500"
    x-transition:enter-start="translate-x-full opacity-0"
    x-transition:enter-end="translate-x-0 opacity-100"
    x-transition:leave="transition ease-in duration-300"
    x-transition:leave-start="translate-x-0 opacity-100"
    x-transition:leave-end="translate-x-full opacity-0"
    class="fixed bottom-8 right-8 z-50"
    style="display: none;"
    role="region"
    aria-label="Cookie consent"
>
    {{-- Main Card --}}
    <div class="w-[420px] max-w-[calc(100vw-4rem)] bg-navy rounded-3xl shadow-2xl shadow-navy/50 border border-white/10 overflow-hidden">
        
        {{-- Top Gradient Accent Line --}}
        <div class="h-1.5 bg-gradient-to-r from-amber via-orange-500 to-amber"></div>
        
        <div class="p-7">
            {{-- Badge --}}
            <div class="inline-flex items-center gap-2 bg-amber/20 text-amber rounded-full px-4 py-2 text-xs font-bold uppercase tracking-wider mb-4 border border-amber/30 shadow-sm shadow-amber/10">
                <x-heroicon-s-shield-check class="w-4 h-4" />
                Cookie Policy
            </div>
            
            {{-- Heading --}}
            <h3 class="text-xl font-display font-bold text-white mb-3 leading-tight">
                We value your privacy
            </h3>
            
            {{-- Description --}}
            <p class="text-sm text-white/70 leading-relaxed mb-6">
                We use cookies to enhance your browsing experience, serve personalized content, and analyze our traffic.
            </p>
            
            {{-- Action Buttons --}}
            <div class="flex items-center gap-3 mb-4">
                {{-- Decline Button --}}
                <button
                    @click="decline()"
                    type="button"
                    class="flex-1 px-5 py-3.5 text-sm font-semibold text-white
                           bg-white/5 backdrop-blur-sm
                           border border-white/20
                           rounded-xl
                           hover:bg-white/10 hover:border-white/30
                           transition-all duration-300 hover:scale-[1.02]"
                    aria-label="Decline all cookies"
                >
                    <span class="flex items-center justify-center gap-2">
                        <x-heroicon-o-x-mark class="w-4 h-4" />
                        Decline
                    </span>
                </button>
                
                {{-- Accept Button --}}
                <button
                    @click="accept()"
                    type="button"
                    class="flex-1 px-5 py-3.5 text-sm font-bold text-navy
                           bg-gradient-to-r from-amber to-orange-500
                           rounded-xl
                           shadow-lg shadow-amber/30
                           hover:shadow-xl hover:shadow-amber/40 hover:shadow-amber/20
                           transition-all duration-300 hover:scale-[1.02]"
                    aria-label="Accept all cookies"
                >
                    <span class="flex items-center justify-center gap-2">
                        <x-heroicon-s-check-circle class="w-4 h-4" />
                        Accept All
                    </span>
                </button>
            </div>
            
            {{-- Privacy Policy Link --}}
            <a 
                href="{{ route('frontend.page', ['lang' => app()->getLocale(), 'slug' => 'privacy-policy']) }}" 
                class="inline-flex items-center gap-1.5 text-sm text-amber/80 hover:text-amber transition-colors duration-300 group mb-5"
                target="_blank"
            >
                <x-heroicon-o-information-circle class="w-4 h-4" />
                <span class="underline decoration-amber/50 group-hover:decoration-amber">Learn more in our Privacy Policy</span>
            </a>
            
            {{-- Divider --}}
            <div class="h-px w-full bg-gradient-to-r from-white/10 via-white/10 to-transparent mb-4"></div>
            
            {{-- Trust Badges --}}
            <div class="flex items-center gap-5 text-xs text-white/60">
                <div class="flex items-center gap-2">
                    <x-heroicon-s-lock-closed class="w-4 h-4 text-amber" />
                    <span class="font-medium">GDPR Compliant</span>
                </div>
                <div class="flex items-center gap-2">
                    <x-heroicon-s-shield-check class="w-4 h-4 text-amber" />
                    <span class="font-medium">Secure</span>
                </div>
                <div class="flex items-center gap-2">
                    <x-heroicon-o-arrow-path class="w-4 h-4 text-amber" />
                    <span class="font-medium">Change anytime</span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Cookie preferences modal (optional advanced feature) --}}
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
            this.open = false;
            $dispatch('cookie-preferences-saved', this.preferences);
        }
    }"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-[60] overflow-y-auto"
    aria-labelledby="modal-title"
    role="dialog"
    aria-modal="true"
    style="display: none;"
>
    {{-- Backdrop --}}
    <div class="fixed inset-0 bg-navy/80 backdrop-blur-sm transition-opacity" @click="open = false"></div>

    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        {{-- Spacer --}}
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        {{-- Modal Panel --}}
        <div class="relative inline-block bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            {{-- Decorative top bar --}}
            <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-amber via-orange-500 to-amber"></div>

            <div class="bg-white px-8 pt-8 pb-6">
                {{-- Header --}}
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-12 h-12 rounded-2xl bg-amber/10 flex items-center justify-center">
                        <x-heroicon-o-shield-check class="w-6 h-6 text-amber" />
                    </div>
                    <div>
                        <h3 class="text-xl font-display font-bold text-navy" id="modal-title">
                            Cookie Preferences
                        </h3>
                        <p class="text-sm text-muted">Customize your cookie settings</p>
                    </div>
                </div>

                {{-- Cookie Options --}}
                <div class="space-y-4">
                    {{-- Necessary Cookies --}}
                    <div class="group p-4 rounded-2xl bg-gray-50 border border-gray-100 hover:border-amber/30 transition-all duration-300">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <x-heroicon-s-shield-check class="w-5 h-5 text-emerald-500" />
                                    <p class="font-bold text-navy">Necessary Cookies</p>
                                </div>
                                <p class="text-sm text-muted">Required for basic site functionality and security</p>
                            </div>
                            <div class="relative">
                                <input 
                                    type="checkbox" 
                                    checked 
                                    disabled 
                                    class="w-5 h-5 text-emerald-500 border-gray-300 rounded-lg focus:ring-emerald-500 disabled:opacity-50 cursor-not-allowed"
                                >
                                <span class="absolute -bottom-5 left-1/2 -translate-x-1/2 text-xs text-emerald-600 font-semibold whitespace-nowrap">Always On</span>
                            </div>
                        </div>
                    </div>

                    {{-- Analytics Cookies --}}
                    <div class="group p-4 rounded-2xl bg-gray-50 border border-gray-100 hover:border-amber/30 transition-all duration-300">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <x-heroicon-o-chart-bar class="w-5 h-5 text-blue-500" />
                                    <p class="font-bold text-navy">Analytics Cookies</p>
                                </div>
                                <p class="text-sm text-muted">Help us improve by collecting anonymous usage data</p>
                            </div>
                            <input 
                                type="checkbox" 
                                x-model="preferences.analytics" 
                                class="w-5 h-5 text-blue-500 border-gray-300 rounded-lg focus:ring-blue-500 transition-all"
                            >
                        </div>
                    </div>

                    {{-- Marketing Cookies --}}
                    <div class="group p-4 rounded-2xl bg-gray-50 border border-gray-100 hover:border-amber/30 transition-all duration-300">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <x-heroicon-o-sparkles class="w-5 h-5 text-purple-500" />
                                    <p class="font-bold text-navy">Marketing Cookies</p>
                                </div>
                                <p class="text-sm text-muted">Used to deliver relevant ads and marketing campaigns</p>
                            </div>
                            <input 
                                type="checkbox" 
                                x-model="preferences.marketing" 
                                class="w-5 h-5 text-purple-500 border-gray-300 rounded-lg focus:ring-purple-500 transition-all"
                            >
                        </div>
                    </div>
                </div>
            </div>

            {{-- Footer Actions --}}
            <div class="bg-gray-50 px-8 py-6 sm:flex sm:flex-row-reverse gap-3">
                <button 
                    type="button" 
                    @click="save()" 
                    class="w-full inline-flex justify-center items-center gap-2 rounded-2xl border border-transparent shadow-lg px-6 py-3.5 bg-gradient-to-r from-amber to-orange-500 text-base font-bold text-white hover:shadow-xl hover:shadow-amber/40 focus:outline-none transition-all duration-300 hover:scale-105 sm:w-auto"
                >
                    <x-heroicon-s-check-circle class="w-5 h-5" />
                    Save Preferences
                </button>
                <button 
                    type="button" 
                    @click="open = false" 
                    class="mt-3 w-full inline-flex justify-center items-center gap-2 rounded-2xl border-2 border-gray-200 shadow-sm px-6 py-3.5 bg-white text-base font-semibold text-gray-700 hover:bg-gray-100 hover:border-gray-300 focus:outline-none transition-all duration-300 sm:mt-0 sm:w-auto"
                >
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>
@endif
