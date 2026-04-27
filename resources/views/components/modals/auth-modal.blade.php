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
    x-data="{
        show: false,
        tab: 'login',
        loading: false,
        error: '',
        showPw: false,
        showPw2: false,
        regEmailVerified: false,
        regOtpSent: false,
        regOtpLoading: false,
        regOtpVerifying: false,
        regOtpDigits: ['', '', '', '', '', ''],
        open(tab = 'login') {
            this.tab = tab;
            this.show = true;
            this.error = '';
            this.$nextTick(() => this.$refs.firstInput?.focus());
        },
        close() {
            this.show = false;
            this.error = '';
            this.loading = false;
            this.regEmailVerified = false;
            this.regOtpSent = false;
            this.regOtpDigits = ['', '', '', '', '', ''];
        },
        async regSendOtp() {
            const email = this.$refs.regEmail.value;
            if (!email) return;
            this.regOtpLoading = true;
            try {
                const res = await fetch('{{ route('frontend.auth.resend-otp', ['lang' => app()->getLocale()]) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ email: email, purpose: 'email_verify' })
                });
                if (res.ok) this.regOtpSent = true;
            } finally {
                this.regOtpLoading = false;
            }
        },
        regHandleOtpInput(e, index) {
            const val = e.target.value.replace(/\D/g, '').slice(0, 1);
            e.target.value = val;
            const next = [...this.regOtpDigits];
            next[index] = val;
            this.regOtpDigits = next;
            if (val && index < 5) {
                const inputs = document.querySelectorAll('#reg-otp-inputs input');
                inputs[index + 1]?.focus();
            }
        },
        regHandleOtpBackspace(e, index) {
            if (!e.target.value && index > 0) {
                const inputs = document.querySelectorAll('#reg-otp-inputs input');
                inputs[index - 1]?.focus();
            }
        },
        regHandleOtpPaste(e) {
            const raw = (e.clipboardData || window.clipboardData).getData('text') || '';
            const digits = raw.replace(/\D/g, '').slice(0, 6).split('');
            if (digits.length === 0) return;
            e.preventDefault();
            const filled = [...this.regOtpDigits];
            for (let i = 0; i < 6; i++) filled[i] = digits[i] || '';
            this.regOtpDigits = filled;
            this.$nextTick(() => {
                const inputs = document.querySelectorAll('#reg-otp-inputs input');
                inputs.forEach((el, i) => { el.value = filled[i] || ''; });
                (inputs[Math.min(digits.length, 5)] || inputs[5])?.focus();
            });
        },
        async regVerifyOtp() {
            this.regOtpVerifying = true;
            try {
                const res = await fetch('{{ route('frontend.auth.verify-otp', ['lang' => app()->getLocale()]) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ email: this.$refs.regEmail.value, otp: this.regOtpDigits.join(''), purpose: 'email_verify' })
                });
                if (res.ok) {
                    this.regEmailVerified = true;
                    this.regOtpSent = false;
                    this.regOtpDigits = ['', '', '', '', '', ''];
                }
            } finally {
                this.regOtpVerifying = false;
            }
        },
    }"
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
             role="dialog" aria-modal="true" aria-labelledby="auth-modal-title">

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
                            § AUTH · <span x-text="tab === 'login' ? 'PROTOCOL-IN' : 'PROTOCOL-REG'"></span>
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
                    </h2>
                    <p class="mt-2 font-mono text-[11px] tracking-[0.22em] uppercase text-ivory/60">
                        <span x-show="tab === 'login'">Sign in to continue · Secure session</span>
                        <span x-show="tab === 'register'" x-cloak>Free account · Verified email</span>
                    </p>

                    {{-- Tabs --}}
                    <div class="mt-6 grid grid-cols-2 border border-white/20 bg-ink/50" role="tablist">
                        <button
                            @click="tab = 'login'; error = ''"
                            :class="tab === 'login' ? 'bg-amber text-ink' : 'text-ivory/70 hover:text-ivory hover:bg-white/5'"
                            class="relative py-3 font-mono text-[11px] font-bold tracking-[0.22em] uppercase transition-colors"
                            role="tab"
                            :aria-selected="tab === 'login'"
                        >
                            Sign in
                        </button>
                        <button
                            @click="tab = 'register'; error = ''"
                            :class="tab === 'register' ? 'bg-amber text-ink' : 'text-ivory/70 hover:text-ivory hover:bg-white/5'"
                            class="relative py-3 font-mono text-[11px] font-bold tracking-[0.22em] uppercase transition-colors border-l border-white/20"
                            role="tab"
                            :aria-selected="tab === 'register'"
                        >
                            Register
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
                <div x-show="tab === 'login'" role="tabpanel" class="space-y-5">
                    <form
                        method="POST"
                        action="{{ $loginUrl }}"
                        @submit.prevent="
                            loading = true; error = '';
                            fetch('{{ $loginUrl }}', {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                                },
                                credentials: 'same-origin',
                                body: JSON.stringify({ email: $refs.loginEmail.value, password: $refs.loginPassword.value })
                            })
                            .then(async r => {
                                const text = await r.text();
                                try { return JSON.parse(text); }
                                catch { return { success: false, message: r.status + ' ' + r.statusText }; }
                            })
                            .then(d => {
                                if(d.success) { window.location.reload(); }
                                else { error = d.message || 'Invalid credentials'; loading = false; }
                            })
                            .catch(() => { error = 'Something went wrong. Please try again.'; loading = false; });
                        "
                        class="space-y-5"
                    >
                        {{-- Email --}}
                        <div>
                            <label for="login-email" class="bp-spec block mb-2 text-ink">§ Email address</label>
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
                                    x-ref="firstInput"
                                    required
                                    class="w-full pl-10 pr-4 py-3 bg-transparent font-mono text-sm text-ink placeholder:text-ink-muted/60 placeholder:font-sans placeholder:text-xs focus:outline-none"
                                    placeholder="you@example.com"
                                >
                            </div>
                        </div>

                        {{-- Password --}}
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label for="login-password" class="bp-spec text-ink">§ Password</label>
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
                <div x-show="tab === 'register'" x-cloak role="tabpanel" class="space-y-4">
                    <form
                        method="POST"
                        action="{{ $registerUrl }}"
                        @submit.prevent="
                            if (!regEmailVerified) {
                                error = 'Please verify your email first';
                                return;
                            }
                            loading = true; error = '';
                            fetch('{{ $registerUrl }}', {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                                },
                                credentials: 'same-origin',
                                body: JSON.stringify({ name: $refs.regName.value, email: $refs.regEmail.value, password: $refs.regPassword.value, password_confirmation: $refs.regConfirm.value })
                            })
                            .then(async r => {
                                const text = await r.text();
                                try { return JSON.parse(text); }
                                catch { return { success: false, message: r.status + ' ' + r.statusText }; }
                            })
                            .then(d => {
                                if(d.success) {
                                    window.location.href = '{{ url('/en/account/dashboard') }}';
                                } else {
                                    error = d.message || 'Registration failed';
                                    loading = false;
                                }
                            })
                            .catch(e => {
                                error = 'Something went wrong. Please try again.';
                                loading = false;
                            });
                        "
                        class="space-y-4"
                    >
                        {{-- Name --}}
                        <div>
                            <label for="reg-name" class="bp-spec block mb-2 text-ink">§ Full name</label>
                            <div class="relative border border-ink bg-paper focus-within:border-amber transition-colors">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted pointer-events-none">
                                    <x-heroicon-o-user class="w-4 h-4" />
                                </span>
                                <input type="text" id="reg-name" name="name" x-ref="regName" x-ref="firstInput" required placeholder="John Smith"
                                       class="w-full pl-10 pr-4 py-3 bg-transparent font-mono text-sm text-ink placeholder:text-ink-muted/60 placeholder:font-sans placeholder:text-xs focus:outline-none">
                            </div>
                        </div>

                        {{-- Email with Send Code Button (Modern Inline) --}}
                        <div>
                            <label for="reg-email" class="bp-spec block mb-2 text-ink">§ Email address</label>
                            <div class="flex gap-2">
                                <div class="relative flex-1 border border-ink bg-paper focus-within:border-amber transition-colors">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted pointer-events-none">
                                        <x-heroicon-o-envelope class="w-4 h-4" />
                                    </span>
                                    <input type="email" id="reg-email" name="email" x-ref="regEmail" required placeholder="you@example.com"
                                           :disabled="regEmailVerified"
                                           class="w-full pl-10 pr-4 py-3 bg-transparent font-mono text-sm text-ink placeholder:text-ink-muted/60 placeholder:font-sans placeholder:text-xs focus:outline-none disabled:opacity-60">
                                </div>

                                {{-- Send Code Button --}}
                                <template x-if="!regEmailVerified">
                                    <button type="button"
                                            @click="regSendOtp()"
                                            :disabled="regOtpLoading || !$refs.regEmail.value"
                                            class="px-4 py-3 border border-amber bg-amber/10 text-amber-ink font-mono text-xs font-bold uppercase tracking-[0.1em] hover:bg-amber/20 disabled:opacity-40 transition-colors whitespace-nowrap">
                                        <span x-show="!regOtpLoading" class="flex items-center gap-1.5">
                                            <x-heroicon-o-paper-airplane class="w-3 h-3" />
                                            Send code
                                        </span>
                                        <span x-show="regOtpLoading" class="flex items-center gap-1.5">
                                            <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                            </svg>
                                            Sending
                                        </span>
                                    </button>
                                </template>

                                {{-- Verified Badge --}}
                                <template x-if="regEmailVerified">
                                    <div class="flex items-center gap-2 px-4 py-3 border border-green-400 bg-green-50">
                                        <x-heroicon-s-check class="w-4 h-4 text-green-600" />
                                        <span class="font-mono text-xs font-bold text-green-700 uppercase tracking-[0.08em]">Verified</span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- OTP Input Fields (Appears when code sent) --}}
                        <template x-if="regOtpSent && !regEmailVerified">
                            <div class="bg-amber/5 border border-amber/30 rounded-lg p-4 space-y-3">
                                <p class="font-mono text-[11px] tracking-[0.1em] uppercase text-ink">6-digit code</p>
                                <div class="flex gap-1.5" id="reg-otp-inputs">
                                    <template x-for="i in 6" :key="i">
                                        <input type="text" inputmode="numeric" maxlength="1" autocomplete="one-time-code"
                                               :value="regOtpDigits[i - 1] || ''"
                                               @input="regHandleOtpInput($event, i - 1)"
                                               @paste="regHandleOtpPaste($event)"
                                               @keydown.backspace="regHandleOtpBackspace($event, i - 1)"
                                               class="flex-1 h-11 text-center text-lg font-mono font-bold tabular-nums border border-ink bg-paper focus:outline-none focus:border-amber focus:ring-2 focus:ring-amber/20 transition-colors rounded">
                                    </template>
                                </div>

                                {{-- Verify Button --}}
                                <button type="button"
                                        @click="regVerifyOtp()"
                                        :disabled="regOtpDigits.join('').length < 6 || regOtpVerifying"
                                        class="w-full py-2.5 bg-amber text-ink font-mono text-xs font-bold uppercase tracking-[0.1em] hover:bg-amber/90 disabled:opacity-40 transition-colors rounded">
                                    <span x-show="!regOtpVerifying" class="flex items-center justify-center gap-1.5">
                                        <x-heroicon-s-shield-check class="w-3 h-3" />
                                        Verify code
                                    </span>
                                    <span x-show="regOtpVerifying" class="flex items-center justify-center gap-1.5">
                                        <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                        </svg>
                                        Verifying
                                    </span>
                                </button>
                            </div>
                        </template>

                        {{-- Password --}}
                        <div>
                            <label for="reg-password" class="bp-spec block mb-2 text-ink">§ Password · min {{ $pwMin }} chars</label>
                            <div class="relative border border-ink bg-paper focus-within:border-amber transition-colors">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted pointer-events-none">
                                    <x-heroicon-o-lock-closed class="w-4 h-4" />
                                </span>
                                <input type="password" id="reg-password" name="password" x-ref="regPassword" required :placeholder="'Min {{ $pwMin }} characters'"
                                       class="w-full pl-10 pr-4 py-3 bg-transparent font-mono text-sm text-ink placeholder:text-ink-muted/60 placeholder:font-sans placeholder:text-xs focus:outline-none">
                            </div>
                        </div>

                        {{-- Confirm Password --}}
                        <div>
                            <label for="reg-confirm" class="bp-spec block mb-2 text-ink">§ Confirm password</label>
                            <div class="relative border border-ink bg-paper focus-within:border-amber transition-colors">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted pointer-events-none">
                                    <x-heroicon-o-lock-closed class="w-4 h-4" />
                                </span>
                                <input type="password" id="reg-confirm" name="password_confirmation" x-ref="regConfirm" required placeholder="••••••••"
                                       class="w-full pl-10 pr-4 py-3 bg-transparent font-mono text-sm text-ink placeholder:text-ink-muted/60 placeholder:font-sans placeholder:text-xs focus:outline-none">
                            </div>
                        </div>

                        {{-- Honeypot --}}
                        <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">

                        {{-- Terms --}}
                        <div class="flex items-start gap-3">
                            <input type="checkbox" id="reg-terms" name="agree_terms" value="1" required
                                   class="mt-1.5 w-4 h-4 border border-ink accent-amber">
                            <label for="reg-terms" class="text-sm font-sans text-ink flex-1">
                                I agree to the <a href="{{ url('/'.app()->getLocale().'/terms-of-service') }}" class="text-amber-ink hover:text-ink border-b border-amber-ink/30 hover:border-ink transition-colors" target="_blank">Terms of Service</a> and <a href="{{ url('/'.app()->getLocale().'/privacy-policy') }}" class="text-amber-ink hover:text-ink border-b border-amber-ink/30 hover:border-ink transition-colors" target="_blank">Privacy Policy</a>
                            </label>
                        </div>

                        {{-- Submit --}}
                        <button type="submit" :disabled="loading || !regEmailVerified"
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

            </div>
        </div>
        </div>
    </div>
</div>
