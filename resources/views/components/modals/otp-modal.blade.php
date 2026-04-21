{{--
  OTP Verification Modal
  Opened by dispatching: $dispatch('open-otp-modal', { email, purpose })
  Emits on success: $dispatch('otp-verified', { email, purpose, token })

  Purposes: guest_checkout | contact_form | email_verify
--}}
<div
    x-data="{
        show: false,
        email: '',
        purpose: '',
        loading: false,
        resending: false,
        error: '',
        success: '',
        cooldown: 0,
        cooldownTimer: null,

        open(detail) {
            this.email   = detail.email || '';
            this.purpose = detail.purpose || 'email_verify';
            this.show    = true;
            this.error   = '';
            this.success = '';
            this.loading = false;
            this.startCooldown();
        },

        close() {
            this.show    = false;
            this.error   = '';
            this.success = '';
            this.loading = false;
            clearInterval(this.cooldownTimer);
            this.cooldown = 0;
        },

        startCooldown() {
            this.cooldown = {{ settings('auth.otp_resend_cooldown', 60) }};
            clearInterval(this.cooldownTimer);
            this.cooldownTimer = setInterval(() => {
                if (this.cooldown > 0) { this.cooldown--; }
                else { clearInterval(this.cooldownTimer); }
            }, 1000);
        },

        async verify(code) {
            if (code.length !== {{ settings('auth.otp_length', 6) }}) return;
            this.loading = true;
            this.error   = '';
            try {
                const r = await fetch('/api/otp/verify', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body:    JSON.stringify({ email: this.email, otp_code: code, purpose: this.purpose }),
                });
                const d = await r.json();
                if (d.success) {
                    this.success = 'Email verified!';
                    this.$dispatch('otp-verified', { email: this.email, purpose: this.purpose, token: d.token });
                    setTimeout(() => this.close(), 800);
                } else {
                    this.error = d.message || 'Invalid code. Please try again.';
                    this.$dispatch('otp-reset');
                    this.loading = false;
                }
            } catch {
                this.error   = 'Something went wrong. Please try again.';
                this.loading = false;
            }
        },

        async resend() {
            if (this.cooldown > 0 || this.resending) return;
            this.resending = true;
            this.error     = '';
            try {
                const r = await fetch('/api/otp/send', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body:    JSON.stringify({ email: this.email, purpose: this.purpose }),
                });
                const d = await r.json();
                if (d.success) {
                    this.$dispatch('otp-reset');
                    this.startCooldown();
                } else {
                    this.error = d.message || 'Could not resend code.';
                }
            } catch {
                this.error = 'Something went wrong. Please try again.';
            } finally {
                this.resending = false;
            }
        },
    }"
    @open-otp-modal.window="open($event.detail)"
    @keydown.escape.window="close()"
    x-cloak
>
    {{-- Backdrop --}}
    <div
        x-show="show"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="close()"
        class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm"
    ></div>

    {{-- Panel --}}
    <div
        x-show="show"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
    >
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm text-center" role="dialog" aria-modal="true">

            {{-- Header --}}
            <div class="px-6 pt-6 pb-2">
                <div class="w-14 h-14 rounded-full bg-navy/10 flex items-center justify-center mx-auto mb-4">
                    <x-heroicon-o-envelope class="w-7 h-7 text-navy" />
                </div>
                <h2 class="font-display font-bold text-xl text-navy">Check your email</h2>
                <p class="text-sm text-muted mt-2">
                    We sent a {{ settings('auth.otp_length', 6) }}-digit code to
                    <span class="font-medium text-body" x-text="email"></span>
                </p>
            </div>

            {{-- Error / success --}}
            <div class="px-6">
                <div x-show="error" class="mt-4 flex items-center gap-2 px-3 py-2 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 text-left">
                    <x-heroicon-o-x-circle class="w-4 h-4 shrink-0" />
                    <span x-text="error"></span>
                </div>
                <div x-show="success" class="mt-4 flex items-center gap-2 px-3 py-2 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700 text-left">
                    <x-heroicon-o-check-circle class="w-4 h-4 shrink-0" />
                    <span x-text="success"></span>
                </div>
            </div>

            {{-- OTP digit inputs --}}
            <div
                class="px-6 py-6"
                x-data="otpInput({{ settings('auth.otp_length', 6) }})"
                @otp-complete.window="verify($event.detail.code)"
                @otp-reset.window="reset()"
            >
                <div class="flex justify-center gap-2">
                    <template x-for="(digit, i) in digits" :key="i">
                        <input
                            type="tel"
                            inputmode="numeric"
                            maxlength="1"
                            x-model="digits[i]"
                            @input="onInput($event, i)"
                            @keydown.backspace="onBackspace($event, i)"
                            @paste.prevent="onPaste($event)"
                            :id="'otp-' + i"
                            :disabled="loading"
                            class="w-11 h-14 text-center text-xl font-mono font-bold border-2 border-slate-300 rounded-lg focus:outline-none focus:border-navy focus:ring-1 focus:ring-navy disabled:opacity-50 transition-colors"
                        >
                    </template>
                </div>

                {{-- Loading indicator --}}
                <div x-show="loading" class="mt-4 flex items-center justify-center gap-2 text-sm text-muted">
                    <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                    </svg>
                    Verifying…
                </div>
            </div>

            {{-- Resend --}}
            <div class="px-6 pb-6 text-sm text-muted">
                <p>
                    Didn't receive it?
                    <button
                        @click="resend()"
                        :disabled="cooldown > 0 || resending"
                        class="text-amber-text font-medium hover:underline disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span x-show="cooldown > 0">Resend in <span x-text="cooldown"></span>s</span>
                        <span x-show="cooldown === 0" x-cloak x-text="resending ? 'Sending…' : 'Resend code'"></span>
                    </button>
                </p>
                <p class="mt-2 text-xs">
                    Code expires in {{ settings('auth.otp_expiry_minutes', 10) }} minutes.
                </p>
            </div>

            <div class="border-t border-slate-100 px-6 py-4">
                <button @click="close()" class="text-sm text-muted hover:text-body transition-colors">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>
