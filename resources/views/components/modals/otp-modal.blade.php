{{--
  ═══════════════════════════════════════════════════════════════════
  INDUSTRIAL BLUEPRINT — OTP Verification Modal
  ═══════════════════════════════════════════════════════════════════
  Opened by: $dispatch('open-otp-modal', { email, purpose })
  Emits on success: $dispatch('otp-verified', { email, purpose, token })
  Purposes: guest_checkout | contact_form | email_verify
--}}
@php
    $otpLen = settings('auth.otp_length', 6);
    $otpExpiry = settings('auth.otp_expiry_minutes', 10);
    $otpCooldown = settings('auth.otp_resend_cooldown', 60);
@endphp
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
            this.cooldown = {{ $otpCooldown }};
            clearInterval(this.cooldownTimer);
            this.cooldownTimer = setInterval(() => {
                if (this.cooldown > 0) { this.cooldown--; }
                else { clearInterval(this.cooldownTimer); }
            }, 1000);
        },
        async verify(code) {
            if (code.length !== {{ $otpLen }}) return;
            this.loading = true;
            this.error   = '';
            try {
                const r = await fetch('{{ route('frontend.auth.verify-otp', ['lang' => app()->getLocale()]) }}', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body:    JSON.stringify({ email: this.email, otp: code, purpose: this.purpose }),
                });
                const d = await r.json();
                if (d.success) {
                    this.success = 'Email verified successfully.';
                    this.$dispatch('otp-verified', { email: this.email, purpose: this.purpose, token: d.token });
                    setTimeout(() => this.close(), 900);
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
                const r = await fetch('{{ route('frontend.auth.resend-otp', ['lang' => app()->getLocale()]) }}', {
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
        @click="close()"
        class="fixed inset-0 z-50 bg-ink/75 backdrop-blur-[3px]"
    ></div>

    {{-- Panel --}}
    <div
        x-show="show"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-3"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        @click.self="close()"
    >
        <div class="relative bg-paper border border-ink w-full max-w-md shadow-[8px_8px_0_0_rgba(11,26,41,0.12)]"
             role="dialog" aria-modal="true" aria-labelledby="otp-modal-title">

            {{-- Corner marks --}}
            <span class="absolute -top-1 -left-1 w-3 h-3 border-l-2 border-t-2 border-amber" aria-hidden="true"></span>
            <span class="absolute -top-1 -right-1 w-3 h-3 border-r-2 border-t-2 border-amber" aria-hidden="true"></span>
            <span class="absolute -bottom-1 -left-1 w-3 h-3 border-l-2 border-b-2 border-amber" aria-hidden="true"></span>
            <span class="absolute -bottom-1 -right-1 w-3 h-3 border-r-2 border-b-2 border-amber" aria-hidden="true"></span>

            {{-- Dark header --}}
            <div class="relative bg-ink text-ivory overflow-hidden">
                <div class="absolute inset-0 bg-grid-navy bg-grid-md opacity-60 pointer-events-none" aria-hidden="true"></div>
                <div class="relative h-[3px] flex" aria-hidden="true">
                    <span class="w-12 bg-amber"></span>
                    <span class="flex-1 bg-white/10"></span>
                </div>
                <div class="relative px-7 pt-5 pb-5">
                    <div class="flex items-center justify-between mb-4">
                        <span class="font-mono text-[10px] font-bold tracking-[0.28em] uppercase text-amber">§ OTP · VERIFY</span>
                        <button @click="close()"
                                class="w-8 h-8 flex items-center justify-center border border-white/20 text-ivory/70
                                       hover:bg-amber hover:text-ink hover:border-amber transition-colors"
                                aria-label="Close">
                            <x-heroicon-o-x-mark class="w-4 h-4" />
                        </button>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 border-2 border-amber flex items-center justify-center shrink-0">
                            <x-heroicon-o-envelope class="w-6 h-6 text-amber" />
                        </div>
                        <div class="min-w-0">
                            <h2 id="otp-modal-title"
                                class="font-display font-extrabold text-ivory leading-[1.05] tracking-[-0.02em] text-2xl">
                                Check your email<span class="text-amber">.</span>
                            </h2>
                            <p class="mt-2 font-mono text-[11px] tracking-[0.14em] text-ivory/70 break-all">
                                Code sent to <span class="text-amber" x-text="email"></span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Status messages --}}
            <div class="px-7 pt-5" x-show="error || success" x-cloak>
                <div x-show="error"
                     class="flex items-start gap-3 px-4 py-3 border border-red-600 bg-red-50">
                    <x-heroicon-s-x-circle class="w-4 h-4 text-red-600 shrink-0 mt-0.5" />
                    <span class="font-mono text-[11px] tracking-[0.08em] text-red-700" x-text="error"></span>
                </div>
                <div x-show="success"
                     class="flex items-start gap-3 px-4 py-3 border border-emerald-600 bg-emerald-50">
                    <x-heroicon-s-check-circle class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" />
                    <span class="font-mono text-[11px] tracking-[0.08em] text-emerald-700" x-text="success"></span>
                </div>
            </div>

            {{-- OTP digit inputs --}}
            <div class="px-7 py-6"
                 x-data="otpInput({{ $otpLen }})"
                 @otp-complete.window="verify($event.detail.code)"
                 @otp-reset.window="reset()">
                <p class="bp-spec text-amber-ink mb-3">§ {{ $otpLen }}-digit access code</p>
                <div class="flex justify-between gap-1.5 sm:gap-2">
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
                            class="flex-1 min-w-0 h-14 text-center text-xl font-mono font-bold tabular-nums border border-ink bg-paper text-ink
                                   focus:outline-none focus:border-amber focus:bg-ivory-alt
                                   disabled:opacity-50 transition-colors"
                        >
                    </template>
                </div>

                <div x-show="loading" x-cloak class="mt-4 flex items-center justify-center gap-2">
                    <svg class="animate-spin w-4 h-4 text-ink" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                    </svg>
                    <span class="font-mono text-[11px] font-bold tracking-[0.22em] uppercase text-ink-muted">Verifying…</span>
                </div>

                <p class="mt-4 font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted text-center">
                    Code expires in {{ $otpExpiry }} min
                </p>
            </div>

            {{-- Resend ledger --}}
            <div class="mx-7 mb-5 border-t border-rule pt-4 flex items-center justify-between gap-4">
                <span class="font-mono text-[11px] tracking-[0.16em] uppercase text-ink-muted">
                    Didn't receive?
                </span>
                <button
                    @click="resend()"
                    :disabled="cooldown > 0 || resending"
                    class="inline-flex items-center gap-2 font-mono text-[11px] font-bold uppercase tracking-[0.2em]
                           text-amber-ink hover:text-ink transition-colors
                           disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:text-amber-ink">
                    <span :class="resending ? 'animate-spin' : ''" class="inline-flex">
                        <x-heroicon-s-arrow-path class="w-3.5 h-3.5" />
                    </span>
                    <span x-show="cooldown > 0">Resend in <span class="tabular-nums" x-text="cooldown"></span>s</span>
                    <span x-show="cooldown === 0" x-cloak x-text="resending ? 'Sending…' : 'Resend code'"></span>
                </button>
            </div>

            {{-- Cancel footer --}}
            <div class="border-t border-rule bg-ivory-alt px-7 py-3 text-center">
                <button @click="close()"
                        class="font-mono text-[10px] font-bold uppercase tracking-[0.22em] text-ink-muted hover:text-ink transition-colors">
                    Cancel verification
                </button>
            </div>
        </div>
    </div>
</div>
