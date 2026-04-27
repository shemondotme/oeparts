{{--
  ═══════════════════════════════════════════════════════════════════
  INDUSTRIAL BLUEPRINT — Auth Modal (Login / Register)
  ═══════════════════════════════════════════════════════════════════
  Opened by: $dispatch('open-auth-modal') or $dispatch('open-auth-modal', { tab: 'register' })
  Registration now includes inline email verification (like checkout flow)
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

    {{-- Modal panel wrapper — scrollable on overflow (register tab is tall) --}}
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

            {{-- Corner register marks --}}
            <span class="absolute -top-1 -left-1 w-3 h-3 border-l-2 border-t-2 border-amber" aria-hidden="true"></span>
            <span class="absolute -top-1 -right-1 w-3 h-3 border-r-2 border-t-2 border-amber" aria-hidden="true"></span>
            <span class="absolute -bottom-1 -left-1 w-3 h-3 border-l-2 border-b-2 border-amber" aria-hidden="true"></span>
            <span class="absolute -bottom-1 -right-1 w-3 h-3 border-r-2 border-b-2 border-amber" aria-hidden="true"></span>

            {{-- ═══ Document header (dark) ═══ --}}
            <div class="relative bg-ink text-ivory border-b border-ink overflow-hidden">
                <div class="absolute inset-0 bg-grid-navy bg-grid-md opacity-60 pointer-events-none" aria-hidden="true"></div>

                {{-- Amber tick strip top --}}
                <div class="relative h-[3px] flex" aria-hidden="true">
                    <span class="w-12 bg-amber"></span>
                    <span class="flex-1 bg-white/10"></span>
                </div>

                <div class="relative px-7 pt-6 pb-5">
                    {{-- Doc ID + close --}}
                    <div class="flex items-center justify-between mb-5">
                        <span class="font-mono text-[10px] font-bold tracking-[0.28em] uppercase text-amber">
                            § AUTH · <span x-text="tab === 'login' ? 'PROTOCOL-IN' : 'PROTOCOL-REG'"></span>
                        </span>
                        <button @click="close()"
                                class="w-8 h-8 flex items-center justify-center border border-white/20 text-ivory/70
                                       hover:bg-amber hover:text-ink hover:border-amber transition-colors"
                                aria-label="Close">
                            <x-heroicon-o-x-mark class="w-4 h-4" />
                        </button>
                    </div>

                    {{-- Headline --}}
                    <h2 id="auth-modal-title"
                        class="font-display font-extrabold text-ivory leading-[0.95] tracking-[-0.02em] text-3xl md:text-[34px]">
                        <span x-show="tab === 'login'">Welcome back<span class="text-amber">.</span></span>
                        <span x-show="tab === 'register'">Create your account<span class="text-amber">.</span></span>
                    </h2>
                </div>
            </div>

            {{-- ═══ Error message --}}
            <div x-show="error" x-cloak class="relative px-7 pt-4">
                <div class="flex items-start gap-3 px-4 py-3 border border-red-600 bg-red-50">
                    <x-heroicon-s-x-circle class="w-4 h-4 text-red-600 shrink-0 mt-0.5" />
                    <span class="font-mono text-[11px] tracking-[0.08em] text-red-700" x-text="error"></span>
                </div>
            </div>

            {{-- ═══ Tab bar (login / register) --}}
            <div class="relative flex border-b border-rule" role="tablist">
                <button
                    @click="tab = 'login'; error = ''"
                    :class="tab === 'login' ? 'bg-amber text-ink' : 'text-ivory/70 hover:text-ivory hover:bg-white/5'"
                    class="relative py-3 font-mono text-[11px] font-bold tracking-[0.22em] uppercase transition-colors flex-1"
                    role="tab"
                    :aria-selected="tab === 'login'"
                >
                    Sign in
                </button>
                <button
                    @click="tab = 'register'; error = ''"
                    :class="tab === 'register' ? 'bg-amber text-ink' : 'text-ivory/70 hover:text-ivory hover:bg-white/5'"
                    class="relative py-3 font-mono text-[11px] font-bold tracking-[0.22em] uppercase transition-colors flex-1 border-l border-rule"
                    role="tab"
                    :aria-selected="tab === 'register'"
                >
                    Register
                </button>
            </div>

            {{-- ═══ Content area --}}
            <div class="relative px-7 py-6 space-y-6">

                {{-- ═══ LOGIN FORM ═══ --}}
                <div x-show="tab === 'login'" x-cloak role="tabpanel" class="space-y-4">
                    <form
                        method="POST"
                        action="{{ $loginUrl }}"
                        @submit.prevent="
                            loading = true; error = '';
                            fetch('{{ $loginUrl }}', {
                                method: 'POST',
                                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                                credentials: 'same-origin',
                                body: JSON.stringify({ email: $refs.loginEmail.value, password: $refs.loginPassword.value })
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
                                    error = d.message || 'Invalid credentials';
                                    loading = false;
                                }
                            })
                            .catch(() => { error = 'Connection failed. Please try again.'; loading = false; });
                        "
                        class="space-y-4"
                    >
                        {{-- Email --}}
                        <div>
                            <label for="login-email" class="bp-spec block mb-2 text-ink">§ Email address</label>
                            <input type="email" id="login-email" name="email" x-ref="loginEmail" required placeholder="you@example.com" x-ref="firstInput"
                                   class="w-full px-4 py-3 border border-ink bg-paper font-mono text-sm text-ink placeholder:text-ink-muted/60 focus:outline-none focus:border-amber transition-colors">
                        </div>

                        {{-- Password --}}
                        <div>
                            <label for="login-password" class="bp-spec block mb-2 text-ink">§ Password</label>
                            <input type="password" id="login-password" name="password" x-ref="loginPassword" required placeholder="••••••••"
                                   class="w-full px-4 py-3 border border-ink bg-paper font-mono text-sm text-ink placeholder:text-ink-muted/60 focus:outline-none focus:border-amber transition-colors">
                        </div>

                        {{-- Submit --}}
                        <button type="submit" :disabled="loading" class="bp-btn-primary w-full justify-center py-3.5 text-sm disabled:opacity-50">
                            <svg x-show="loading" x-cloak class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                            </svg>
                            <x-heroicon-s-arrow-right-on-rectangle class="w-4 h-4" x-show="!loading" />
                            <span x-text="loading ? 'Signing in…' : 'Sign in'"></span>
                        </button>
                    </form>

                    {{-- Forgot password --}}
                    <div class="pt-2 text-center">
                        <a href="{{ url('/'.$lang.'/reset-password') }}" class="font-mono text-[10px] font-bold uppercase tracking-[0.2em] text-amber-ink hover:text-ink transition-colors">
                            Forgot password?
                        </a>
                    </div>
                </div>

                {{-- ═══ REGISTER FORM (WITH INLINE OTP) ═══ --}}
                <div x-show="tab === 'register'" x-cloak role="tabpanel">
                    <form
                        method="POST"
                        action="{{ $registerUrl }}"
                        x-data="{
                            accountCreated: false,
                            otpSent: false,
                            otpDigits: ['', '', '', '', '', ''],
                            otpVerifying: false,
                            otpVerified: false,
                            otpLoading: false,
                        }"
                        @submit.prevent="
                            if (otpVerified) {
                                this.closest('form').submit();
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
                                    accountCreated = true;
                                    sendOtp($refs.regEmail.value);
                                } else {
                                    error = d.message || Object.values(d.errors || {})[0]?.[0] || 'Registration failed';
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
                        {{-- ═══ STEP 1: Account Details ═══ --}}
                        <template x-if="!accountCreated">
                            <div class="space-y-4">
                                {{-- Name --}}
                                <div>
                                    <label for="reg-name" class="bp-spec block mb-2 text-ink">§ Full name</label>
                                    <input type="text" id="reg-name" name="name" x-ref="regName" x-ref="firstInput" required placeholder="John Smith"
                                           class="w-full px-4 py-3 border border-ink bg-paper font-mono text-sm text-ink placeholder:text-ink-muted/60 focus:outline-none focus:border-amber transition-colors">
                                </div>

                                {{-- Email --}}
                                <div>
                                    <label for="reg-email" class="bp-spec block mb-2 text-ink">§ Email address</label>
                                    <input type="email" id="reg-email" name="email" x-ref="regEmail" required placeholder="you@example.com"
                                           class="w-full px-4 py-3 border border-ink bg-paper font-mono text-sm text-ink placeholder:text-ink-muted/60 focus:outline-none focus:border-amber transition-colors">
                                </div>

                                {{-- Password --}}
                                <div>
                                    <label for="reg-password" class="bp-spec block mb-2 text-ink">§ Password · min {{ $pwMin }} chars</label>
                                    <input type="password" id="reg-password" name="password" x-ref="regPassword" required :placeholder="'Min {{ $pwMin }} characters'"
                                           class="w-full px-4 py-3 border border-ink bg-paper font-mono text-sm text-ink placeholder:text-ink-muted/60 focus:outline-none focus:border-amber transition-colors">
                                </div>

                                {{-- Confirm password --}}
                                <div>
                                    <label for="reg-confirm" class="bp-spec block mb-2 text-ink">§ Confirm password</label>
                                    <input type="password" id="reg-confirm" name="password_confirmation" x-ref="regConfirm" required placeholder="••••••••"
                                           class="w-full px-4 py-3 border border-ink bg-paper font-mono text-sm text-ink placeholder:text-ink-muted/60 focus:outline-none focus:border-amber transition-colors">
                                </div>

                                {{-- Honeypot --}}
                                <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">

                                {{-- Terms checkbox --}}
                                <div class="flex items-start gap-3">
                                    <input type="checkbox" id="reg-terms" name="agree_terms" value="1" required
                                           class="mt-1.5 w-4 h-4 border border-ink accent-amber">
                                    <label for="reg-terms" class="text-sm font-sans text-ink flex-1">
                                        I agree to the <a href="{{ url('/'.app()->getLocale().'/terms-of-service') }}" class="text-amber-ink hover:text-ink border-b border-amber-ink/30 hover:border-ink transition-colors" target="_blank">Terms of Service</a> and <a href="{{ url('/'.app()->getLocale().'/privacy-policy') }}" class="text-amber-ink hover:text-ink border-b border-amber-ink/30 hover:border-ink transition-colors" target="_blank">Privacy Policy</a>
                                    </label>
                                </div>

                                {{-- Info --}}
                                <div class="bg-blue-50 border border-blue-200 rounded px-3 py-2">
                                    <p class="font-mono text-[10px] tracking-[0.06em] text-blue-700 flex items-center gap-2">
                                        <x-heroicon-s-envelope class="w-3.5 h-3.5 shrink-0" />
                                        <span>Next: Verify your email with a 6-digit code</span>
                                    </p>
                                </div>

                                {{-- Submit --}}
                                <button type="submit" :disabled="loading"
                                        class="bp-btn-primary w-full justify-center py-3.5 text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                                    <svg x-show="loading" x-cloak class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                                    </svg>
                                    <x-heroicon-s-user-plus class="w-4 h-4" x-show="!loading" />
                                    <span x-text="loading ? 'Creating…' : 'Create account'"></span>
                                </button>
                            </div>
                        </template>

                        {{-- ═══ STEP 2: Email Verification ═══ --}}
                        <template x-if="accountCreated && !otpVerified">
                            <div class="space-y-4">
                                <div class="border border-amber bg-amber/10 p-5">
                                    <div class="flex items-start gap-3 mb-4">
                                        <div class="w-9 h-9 border border-amber bg-paper flex items-center justify-center shrink-0">
                                            <x-heroicon-s-shield-check class="w-4 h-4 text-amber-ink" />
                                        </div>
                                        <div class="flex-1">
                                            <p class="bp-spec text-amber-ink mb-1">§ Verify email</p>
                                            <p class="text-xs text-body mb-3">
                                                6-digit code sent to
                                                <span x-text="$refs.regEmail.value || '…'" class="font-mono font-bold text-ink"></span>
                                            </p>
                                            <button type="button"
                                                    @click="sendOtp($refs.regEmail.value)"
                                                    :disabled="otpLoading || otpSent"
                                                    class="bp-btn-primary text-xs disabled:opacity-40">
                                                <span x-show="!otpLoading" class="flex items-center gap-2">
                                                    <x-heroicon-o-paper-airplane class="w-3 h-3" />
                                                    <span x-text="otpSent ? 'Code sent ✓' : 'Send code'"></span>
                                                </span>
                                                <span x-show="otpLoading" class="flex items-center gap-2">
                                                    <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                                    </svg>
                                                    Sending…
                                                </span>
                                            </button>
                                        </div>
                                    </div>

                                    {{-- OTP inputs --}}
                                    <template x-if="otpSent">
                                        <div class="border-t border-amber bg-paper/70 pt-4">
                                            <p class="bp-spec text-ink text-xs mb-3">§ 6-digit code</p>
                                            <div class="flex gap-1 mb-3" id="reg-otp-inputs">
                                                <template x-for="i in 6" :key="i">
                                                    <input type="text" inputmode="numeric" maxlength="1"
                                                           :value="otpDigits[i - 1] || ''"
                                                           @input="handleOtpInput($event, i - 1)"
                                                           @paste="handleOtpPaste($event)"
                                                           @keydown.backspace="handleOtpBackspace($event, i - 1)"
                                                           class="flex-1 h-10 text-center text-lg font-mono font-bold border border-ink bg-paper focus:outline-none focus:border-amber focus:ring-2 focus:ring-amber/30">
                                                </template>
                                            </div>

                                            {{-- Verify button --}}
                                            <button type="button"
                                                    @click="verifyOtp()"
                                                    :disabled="otpDigits.join('').length < 6 || otpVerifying"
                                                    class="bp-btn-primary w-full justify-center text-xs disabled:opacity-40">
                                                <span x-show="!otpVerifying" class="flex items-center gap-2">
                                                    <x-heroicon-s-shield-check class="w-3 h-3" />
                                                    Verify code
                                                </span>
                                                <span x-show="otpVerifying" class="flex items-center gap-2">
                                                    <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                                    </svg>
                                                    Verifying…
                                                </span>
                                            </button>

                                            <div class="mt-2 text-center text-xs text-ink-muted">
                                                <span x-text="otpDigits.join('').length"></span> / 6
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        {{-- ═══ STEP 3: Verified ═══ --}}
                        <template x-if="otpVerified">
                            <div class="space-y-4">
                                <div class="flex items-center gap-3 p-4 border border-ink bg-ivory-alt">
                                    <div class="w-8 h-8 border border-ink bg-paper flex items-center justify-center shrink-0">
                                        <x-heroicon-s-check class="w-4 h-4 text-amber-ink" />
                                    </div>
                                    <div>
                                        <p class="bp-spec text-amber-ink text-sm">§ Email verified</p>
                                        <p class="text-xs text-body mt-0.5">Your account is ready!</p>
                                    </div>
                                </div>

                                <button type="submit" class="bp-btn-primary w-full justify-center py-3.5">
                                    <x-heroicon-s-check-circle class="w-4 h-4" />
                                    <span>Complete Registration</span>
                                    <x-heroicon-s-arrow-long-right class="w-4 h-4" />
                                </button>
                            </div>
                        </template>
                    </form>
                </div>

            </div>
        </div>
        </div>
    </div>
</div>

<script>
// Registration OTP helpers
function sendOtp(email) {
    if (!email) return;
    this.otpLoading = true;
    fetch('{{ route('frontend.auth.resend-otp', ['lang' => app()->getLocale()]) }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
        body: JSON.stringify({ email: email, purpose: 'email_verify' })
    }).then(r => {
        if (r.ok) {
            this.otpSent = true;
        }
    }).finally(() => {
        this.otpLoading = false;
    });
}

function handleOtpInput(e, index) {
    const val = e.target.value.replace(/\D/g, '').slice(0, 1);
    e.target.value = val;
    const next = [...this.otpDigits];
    next[index] = val;
    this.otpDigits = next;
    if (val && index < 5) {
        const inputs = document.querySelectorAll('#reg-otp-inputs input');
        inputs[index + 1]?.focus();
    }
}

function handleOtpBackspace(e, index) {
    if (!e.target.value && index > 0) {
        const inputs = document.querySelectorAll('#reg-otp-inputs input');
        inputs[index - 1]?.focus();
    }
}

function handleOtpPaste(e) {
    const raw = (e.clipboardData || window.clipboardData).getData('text') || '';
    const digits = raw.replace(/\D/g, '').slice(0, 6).split('');
    if (digits.length === 0) return;
    e.preventDefault();
    const filled = [...this.otpDigits];
    for (let i = 0; i < 6; i++) filled[i] = digits[i] || '';
    this.otpDigits = filled;
    this.$nextTick(() => {
        const inputs = document.querySelectorAll('#reg-otp-inputs input');
        inputs.forEach((el, i) => { el.value = filled[i] || ''; });
        (inputs[Math.min(digits.length, 5)] || inputs[5])?.focus();
    });
}

function verifyOtp() {
    this.otpVerifying = true;
    fetch('{{ route('frontend.auth.verify-otp', ['lang' => app()->getLocale()]) }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
        body: JSON.stringify({ email: this.$refs.regEmail.value, otp: this.otpDigits.join(''), purpose: 'email_verify' })
    }).then(r => r.ok ? this.otpVerified = true : null)
      .catch(() => {})
      .finally(() => { this.otpVerifying = false; });
}
</script>
