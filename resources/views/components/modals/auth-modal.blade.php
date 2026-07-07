{{--
  ═══════════════════════════════════════════════════════════════════
  INDUSTRIAL BLUEPRINT — Auth Modal (Login / Register with Inline OTP)
  ═══════════════════════════════════════════════════════════════════
  Modern inline email verification with OTP code input
--}}
@php
    $pwMin = settings('auth.customer_password_min', 8);
    $lang  = app()->getLocale();
    $loginUrl    = url("/{$lang}/login");
    $registerUrl = url("/{$lang}/register");
@endphp
<div
    x-data="authModal()"
    @open-auth-modal.window="open($event.detail?.tab ?? 'login')"
    @keydown.escape.window="close()"
    x-cloak
>
    {{-- Backdrop --}}
    <div
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="close()"
        class="fixed inset-0 z-50 bg-ink/75 backdrop-blur-[3px]"
    ></div>

    {{-- Modal panel wrapper --}}
    <div
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-4"
        class="fixed inset-0 z-50 overflow-y-auto overscroll-contain"
        @click.self="close()"
    >
        <div class="flex min-h-full items-center justify-center p-4" @click.self="close()">
        <div class="relative bg-paper border border-ink w-full max-w-md shadow-[8px_8px_0_0_rgba(11,26,41,0.12)]"
             role="dialog" aria-modal="true" aria-labelledby="auth-modal-title"
             x-trap.noscroll.inert="show">

            {{-- Corner marks --}}
            <span class="absolute -top-1 -left-1 w-3 h-3 border-l-2 border-t-2 border-amber" aria-hidden="true"></span>
            <span class="absolute -top-1 -right-1 w-3 h-3 border-r-2 border-t-2 border-amber" aria-hidden="true"></span>
            <span class="absolute -bottom-1 -left-1 w-3 h-3 border-l-2 border-b-2 border-amber" aria-hidden="true"></span>
            <span class="absolute -bottom-1 -right-1 w-3 h-3 border-r-2 border-b-2 border-amber" aria-hidden="true"></span>

            {{-- Header --}}
            <div class="relative bg-ink text-ivory border-b border-ink overflow-hidden">
                <div class="absolute inset-0 bg-grid-navy bg-grid-md opacity-60 pointer-events-none" aria-hidden="true"></div>
                <div class="relative h-[3px] flex" aria-hidden="true">
                    <span class="w-12 bg-amber"></span>
                    <span class="flex-1 bg-white/10"></span>
                </div>

                <div class="relative px-7 pt-6 pb-5">
                    <div class="flex items-center justify-between mb-5">
                        <span class="font-mono text-[10px] font-bold tracking-[0.28em] uppercase text-amber">
                            AUTH · <span x-text="tab === 'login' ? 'PROTOCOL-IN' : (tab === 'register' ? 'PROTOCOL-REG' : 'PROTOCOL-OTP')"></span>
                        </span>
                        <button @click="close()"
                                class="w-8 h-8 flex items-center justify-center border border-white/20 text-ivory/70 hover:bg-amber hover:text-ink hover:border-amber transition-colors"
                                aria-label="Close">
                            <x-heroicon-o-x-mark class="w-4 h-4" />
                        </button>
                    </div>

                    <h2 id="auth-modal-title"
                        class="font-display font-extrabold text-ivory leading-[0.95] tracking-[-0.02em] text-3xl md:text-[34px]">
                        <span x-show="tab === 'login'">Welcome back<span class="text-amber">.</span></span>
                        <span x-show="tab === 'register'">Create account<span class="text-amber">.</span></span>
                        <span x-show="tab === 'otp'" x-cloak>{{ __('Verify email') }}<span class="text-amber">.</span></span>
                    </h2>
                    <p class="mt-2 font-mono text-[11px] tracking-[0.22em] uppercase text-ivory/60">
                        <span x-show="tab === 'login'">Sign in to continue · Secure session</span>
                        <span x-show="tab === 'register'" x-cloak>Free account · Verified email</span>
                        <span x-show="tab === 'otp'" x-cloak>{{ __('One-time code · Secure verification') }}</span>
                    </p>

                    {{-- Tabs --}}
                    <div x-show="tab !== 'otp'" class="mt-6 grid grid-cols-2 border border-white/20 bg-ink/50" role="tablist">
                        <button
                            @click="tab = 'login'; error = ''"
                            :class="tab === 'login' ? 'bg-amber text-ink' : 'text-ivory/70 hover:text-ivory hover:bg-white/5'"
                            class="relative py-3 font-mono text-[11px] font-bold tracking-[0.22em] uppercase transition-colors"
                            role="tab"
                            id="auth-tab-login"
                            aria-controls="auth-panel-login"
                            :aria-selected="tab === 'login'"
                        >
                            {{ __('Sign in') }}
                        </button>
                        <button
                            @click="tab = 'register'; error = ''"
                            :class="tab === 'register' ? 'bg-amber text-ink' : 'text-ivory/70 hover:text-ivory hover:bg-white/5'"
                            class="relative py-3 font-mono text-[11px] font-bold tracking-[0.22em] uppercase transition-colors border-l border-white/20"
                            role="tab"
                            id="auth-tab-register"
                            aria-controls="auth-panel-register"
                            :aria-selected="tab === 'register'"
                        >
                            {{ __('Register') }}
                        </button>
                    </div>
                </div>
            </div>

            {{-- Error alert --}}
            <div x-show="error" x-transition class="px-7 pt-5" x-cloak>
                <div class="flex items-start gap-3 px-4 py-3 border border-red-600 bg-red-50">
                    <x-heroicon-s-exclamation-circle class="w-4 h-4 text-red-600 shrink-0 mt-0.5" />
                    <span class="font-mono text-[11px] tracking-[0.1em] text-red-700 leading-relaxed" x-text="error"></span>
                </div>
            </div>

            {{-- Content --}}
            <div class="relative px-7 py-6 space-y-6">

                {{-- LOGIN FORM --}}
                <div x-show="tab === 'login'" role="tabpanel" id="auth-panel-login" aria-labelledby="auth-tab-login" class="space-y-5">
                    <form
                        method="POST"
                        action="{{ $loginUrl }}"
                        @submit.prevent="submitLogin()"
                        class="space-y-5"
                    >
                        {{-- Email --}}
                        <div>
                            <label for="login-email" class="bp-spec block mb-2 text-ink">Email address</label>
                            <div class="relative border border-ink bg-paper focus-within:border-amber transition-colors">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted pointer-events-none">
                                    <x-heroicon-o-envelope class="w-4 h-4" />
                                </span>
                                <input
                                    id="login-email"
                                    name="email"
                                    type="email"
                                    inputmode="email"
                                    autocomplete="email"
                                    x-ref="loginEmail"
                                    required
                                    class="w-full pl-10 pr-4 py-3 bg-transparent font-mono text-sm text-ink placeholder:text-ink-muted/60 placeholder:font-sans placeholder:text-xs focus:outline-none"
                                    placeholder="you@example.com"
                                >
                            </div>
                        </div>

                        {{-- Password --}}
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label for="login-password" class="bp-spec text-ink">Password</label>
                                <a href="{{ url('/'.app()->getLocale().'/reset-password') }}"
                                   class="font-mono text-[10px] font-bold uppercase tracking-[0.2em] text-amber-ink hover:text-ink transition-colors">
                                    Forgot?
                                </a>
                            </div>
                            <div class="relative border border-ink bg-paper focus-within:border-amber transition-colors">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted pointer-events-none">
                                    <x-heroicon-o-lock-closed class="w-4 h-4" />
                                </span>
                                <input
                                    id="login-password"
                                    name="password"
                                    type="password"
                                    autocomplete="current-password"
                                    x-ref="loginPassword"
                                    required
                                    class="w-full pl-10 pr-4 py-3 bg-transparent font-mono text-sm text-ink placeholder:text-ink-muted/60 placeholder:font-sans placeholder:text-xs focus:outline-none"
                                    placeholder="••••••••"
                                >
                            </div>
                        </div>

                        {{-- Submit --}}
                        <button type="submit" :disabled="loading" class="bp-btn-primary w-full justify-center py-3 text-sm disabled:opacity-50">
                            <svg x-show="loading" x-cloak class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                            </svg>
                            <x-heroicon-s-lock-closed class="w-4 h-4" x-show="!loading" />
                            <span x-text="loading ? 'Signing in…' : 'Sign in'"></span>
                            <x-heroicon-s-arrow-long-right class="w-4 h-4" x-show="!loading" />
                        </button>
                    </form>

                    {{-- Divider --}}
                    <div class="mt-6 flex items-center gap-3">
                        <span class="flex-1 h-px bg-rule"></span>
                        <span class="font-mono text-[10px] font-bold tracking-[0.24em] uppercase text-ink-muted">New here?</span>
                        <span class="flex-1 h-px bg-rule"></span>
                    </div>
                    <button @click="tab = 'register'; error = ''"
                            class="bp-btn-outline w-full justify-center py-3 text-sm">
                        Create free account
                        <x-heroicon-s-user-plus class="w-4 h-4" />
                    </button>
                </div>

                {{-- REGISTER FORM (Modern Inline OTP) --}}
                <div x-show="tab === 'register'" x-cloak role="tabpanel" id="auth-panel-register" aria-labelledby="auth-tab-register" class="space-y-4">
                    <form
                        method="POST"
                        action="{{ $registerUrl }}"
                        @submit.prevent="submitRegister()"
                        class="space-y-4"
                    >
                        <div>
                            <label for="reg-name" class="bp-spec block mb-2 text-ink">Full name</label>
                            <div class="relative border border-ink bg-paper focus-within:border-amber transition-colors">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted pointer-events-none">
                                    <x-heroicon-o-user class="w-4 h-4" />
                                </span>
                                <input type="text" id="reg-name" name="name" x-ref="regName" required placeholder="John Smith"
                                       class="w-full pl-10 pr-4 py-3 bg-transparent font-mono text-sm text-ink placeholder:text-ink-muted/60 placeholder:font-sans placeholder:text-xs focus:outline-none">
                            </div>
                        </div>

                        <div>
                            <label for="reg-email" class="bp-spec block mb-2 text-ink">Email address</label>
                            <div class="relative border border-ink bg-paper focus-within:border-amber transition-colors">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted pointer-events-none">
                                    <x-heroicon-o-envelope class="w-4 h-4" />
                                </span>
                                <input type="email" id="reg-email" name="email" x-ref="regEmail" required
                                       inputmode="email" autocomplete="email" placeholder="you@example.com"
                                       class="w-full pl-10 pr-4 py-3 bg-transparent font-mono text-sm text-ink placeholder:text-ink-muted/60 placeholder:font-sans placeholder:text-xs focus:outline-none">
                            </div>
                        </div>

                        <div>
                            <label for="reg-password" class="bp-spec block mb-2 text-ink">Password · min {{ $pwMin }} chars</label>
                            <div class="relative border border-ink bg-paper focus-within:border-amber transition-colors">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted pointer-events-none">
                                    <x-heroicon-o-lock-closed class="w-4 h-4" />
                                </span>
                                <input type="password" id="reg-password" name="password" x-ref="regPassword" required
                                       autocomplete="new-password" :placeholder="'Min {{ $pwMin }} characters'"
                                       class="w-full pl-10 pr-4 py-3 bg-transparent font-mono text-sm text-ink placeholder:text-ink-muted/60 placeholder:font-sans placeholder:text-xs focus:outline-none">
                            </div>
                        </div>

                        <div>
                            <label for="reg-confirm" class="bp-spec block mb-2 text-ink">Confirm password</label>
                            <div class="relative border border-ink bg-paper focus-within:border-amber transition-colors">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted pointer-events-none">
                                    <x-heroicon-o-lock-closed class="w-4 h-4" />
                                </span>
                                <input type="password" id="reg-confirm" name="password_confirmation" x-ref="regConfirm" required
                                       autocomplete="new-password" placeholder="••••••••"
                                       class="w-full pl-10 pr-4 py-3 bg-transparent font-mono text-sm text-ink placeholder:text-ink-muted/60 placeholder:font-sans placeholder:text-xs focus:outline-none">
                            </div>
                        </div>

                        <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">

                        <div class="flex items-start gap-3">
                            <input type="checkbox" id="reg-terms" name="agree_terms" value="1" required
                                   class="mt-1.5 w-4 h-4 border border-ink accent-amber">
                            <label for="reg-terms" class="text-sm font-sans text-ink flex-1">
                                I agree to the <a href="{{ url('/'.app()->getLocale().'/terms-of-service') }}" class="text-amber-ink hover:text-ink border-b border-amber-ink/30 hover:border-ink transition-colors" target="_blank">Terms of Service</a> and <a href="{{ url('/'.app()->getLocale().'/privacy-policy') }}" class="text-amber-ink hover:text-ink border-b border-amber-ink/30 hover:border-ink transition-colors" target="_blank">Privacy Policy</a>
                            </label>
                        </div>

                        <button type="submit" :disabled="loading"
                                class="bp-btn-primary w-full justify-center py-3.5 text-sm disabled:opacity-50">
                            <svg x-show="loading" x-cloak class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                            </svg>
                            <x-heroicon-s-user-plus class="w-4 h-4" x-show="!loading" />
                            <span x-text="loading ? 'Creating…' : 'Create account'"></span>
                        </button>
                    </form>

                    {{-- Divider --}}
                    <div class="mt-6 flex items-center gap-3">
                        <span class="flex-1 h-px bg-rule"></span>
                        <span class="font-mono text-[10px] font-bold tracking-[0.24em] uppercase text-ink-muted">Already a member?</span>
                        <span class="flex-1 h-px bg-rule"></span>
                    </div>
                    <button @click="tab = 'login'; error = ''"
                            class="bp-btn-outline w-full justify-center py-3 text-sm">
                        Sign in instead
                        <x-heroicon-s-arrow-long-right class="w-4 h-4" />
                    </button>
                </div>

                {{-- OTP VERIFICATION (inline email verify) --}}
                <div x-show="tab === 'otp'" x-cloak role="tabpanel" class="space-y-5">
                    <div class="text-center">
                        <div class="inline-flex w-12 h-12 border border-ink bg-ivory-alt items-center justify-center mb-4">
                            <x-heroicon-o-envelope-open class="w-6 h-6 text-amber-ink" />
                        </div>
                        <p class="text-sm text-body">{{ __('Enter the code we emailed to') }}</p>
                        <p class="mt-0.5 font-mono text-sm font-bold text-ink break-all" x-text="otpEmail"></p>
                    </div>

                    <form @submit.prevent="verifyOtp()" class="space-y-5">
                        <div>
                            <label for="otp-code" class="bp-spec block mb-2 text-ink text-center">{{ __('Verification code') }}</label>
                            <input id="otp-code" x-ref="otpCode" x-model="otpCode"
                                   inputmode="numeric" autocomplete="one-time-code" :maxlength="otpLength" required
                                   @input="otpCode = otpCode.replace(/[^0-9]/g, '')"
                                   class="w-full px-4 py-3 border border-ink bg-paper text-center font-mono text-2xl font-bold tracking-[0.5em] text-ink focus:outline-none focus:border-amber"
                                   :placeholder="'•'.repeat(otpLength)">
                        </div>
                        <button type="submit" :disabled="loading || otpCode.length < otpLength"
                                class="bp-btn-primary w-full justify-center py-3 text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg x-show="loading" x-cloak class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                            </svg>
                            <x-heroicon-s-shield-check class="w-4 h-4" x-show="!loading" />
                            <span x-text="loading ? '{{ __('Verifying…') }}' : '{{ __('Verify & continue') }}'"></span>
                        </button>
                    </form>

                    <p x-show="resendMsg" x-cloak x-text="resendMsg"
                       class="text-center font-mono text-[11px] tracking-[0.08em] text-emerald-700"></p>

                    <div class="flex items-center justify-between pt-1">
                        <button type="button" @click="resendOtp()"
                                class="font-mono text-[10px] font-bold uppercase tracking-[0.2em] text-amber-ink hover:text-ink transition-colors">
                            {{ __('Resend code') }}
                        </button>
                        <button type="button" @click="tab = 'login'; error = ''; resendMsg = ''"
                                class="font-mono text-[10px] font-bold uppercase tracking-[0.2em] text-ink-muted hover:text-ink transition-colors">
                            {{ __('Back to sign in') }}
                        </button>
                    </div>
                </div>

            </div>
        </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function authModal() {
    const loginUrl     = @json($loginUrl);
    const registerUrl  = @json($registerUrl);
    const verifyUrl    = @json(url("/{$lang}/verify-otp"));
    const resendUrl    = @json(url("/{$lang}/resend-otp"));
    const dashboardUrl = @json(url("/{$lang}/account/dashboard"));
    const otpLength    = {{ (int) settings('auth.otp_length', 6) }};

    // Read the fresh XSRF-TOKEN cookie (Laravel refreshes it on every response),
    // not the static <meta> token. The auth flow makes several POSTs across
    // session rotation (login attempt + logout on requires_otp), which stales the
    // meta token and 419s the follow-up verify/login calls.
    function xsrfToken() {
        const m = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
        return m ? decodeURIComponent(m[1]) : (document.querySelector('meta[name=csrf-token]')?.content || '');
    }

    async function postJson(url, payload) {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });
        const text = await res.text();
        try { return JSON.parse(text); }
        catch { return { success: false, message: res.status + ' ' + res.statusText }; }
    }

    return {
        show: false,
        tab: 'login',
        loading: false,
        error: '',
        showPw: false,
        showPw2: false,
        // OTP (inline email verification) state
        otpEmail: '',
        otpPassword: '',
        otpCode: '',
        otpContext: 'login',
        resendMsg: '',
        otpLength: otpLength,

        open(tab = 'login') {
            this.tab = tab;
            this.show = true;
            this.error = '';
            this.$nextTick(() => {
                (tab === 'login' ? this.$refs.loginEmail : this.$refs.regName)?.focus();
            });
        },
        close() {
            this.show = false;
            this.error = '';
            this.loading = false;
        },

        async submitLogin() {
            if (this.loading) return;
            this.loading = true; this.error = '';
            const d = await postJson(loginUrl, {
                email: this.$refs.loginEmail.value,
                password: this.$refs.loginPassword.value,
            });
            if (d.success && d.data?.requires_otp) {
                this.startOtp(this.$refs.loginEmail.value, this.$refs.loginPassword.value, 'login');
            } else if (d.success) {
                window.location.reload();
            } else {
                this.error = d.message || 'Invalid credentials';
                this.loading = false;
            }
        },

        async submitRegister() {
            if (this.loading) return;
            this.loading = true; this.error = '';
            const d = await postJson(registerUrl, {
                name: this.$refs.regName.value,
                email: this.$refs.regEmail.value,
                password: this.$refs.regPassword.value,
                password_confirmation: this.$refs.regConfirm.value,
                // The controller requires an accepted terms flag; the checkbox was
                // previously omitted from the payload, so registration always 422'd.
                agree_terms: document.getElementById('reg-terms')?.checked ? '1' : '',
                website: '',
            });
            if (d.success && d.data?.requires_otp) {
                this.startOtp(this.$refs.regEmail.value, this.$refs.regPassword.value, 'register');
            } else if (d.success) {
                window.location.href = dashboardUrl;
            } else {
                this.error = d.message || 'Registration failed';
                this.loading = false;
            }
        },

        startOtp(email, password, context) {
            this.otpEmail = email;
            this.otpPassword = password;
            this.otpContext = context;
            this.otpCode = '';
            this.resendMsg = '';
            this.error = '';
            this.loading = false;
            this.tab = 'otp';
            this.$nextTick(() => this.$refs.otpCode?.focus());
        },

        async verifyOtp() {
            if (this.loading || this.otpCode.length < this.otpLength) return;
            this.loading = true; this.error = ''; this.resendMsg = '';
            const v = await postJson(verifyUrl, {
                email: this.otpEmail,
                otp: this.otpCode,
                purpose: 'email_verify',
            });
            if (!v.success) {
                this.error = v.message || 'Invalid or expired code.';
                this.loading = false;
                return;
            }
            // Email is now verified — complete sign-in with the stored credentials.
            const l = await postJson(loginUrl, { email: this.otpEmail, password: this.otpPassword });
            if (l.success && !l.data?.requires_otp) {
                if (this.otpContext === 'register') { window.location.href = dashboardUrl; }
                else { window.location.reload(); }
            } else {
                this.error = 'Email verified — please sign in.';
                this.tab = 'login';
                this.loading = false;
            }
        },

        async resendOtp() {
            this.error = ''; this.resendMsg = '';
            const d = await postJson(resendUrl, { email: this.otpEmail, purpose: 'email_verify' });
            this.resendMsg = d.success
                ? 'A new code has been sent to your email.'
                : (d.message || 'Could not resend the code.');
        },
    };
}
</script>
@endpush
