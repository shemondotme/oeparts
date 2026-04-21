@extends('frontend.checkout.layout')

@section('checkout_content')
<div x-data="checkoutStep1()" x-init="init()" class="space-y-6">
    <input type="hidden" name="otp_verified" :value="otpVerified ? '1' : '0'">

    {{-- Sub-header (since layout already has §01 Contact) --}}
    <header class="pb-4 border-b border-rule">
        <h2 class="font-display text-2xl md:text-3xl font-extrabold text-ink leading-tight tracking-[-0.02em]">
            Contact information<span class="text-amber">.</span>
        </h2>
        <p class="mt-2 font-mono text-[11px] tracking-[0.18em] uppercase text-ink-muted">
            Channel · Notifications · Receipt
        </p>
    </header>

    {{-- Email --}}
    <div>
        <label for="checkout-email" class="bp-spec block mb-2 text-ink">
            § Email address <span class="text-red-600 normal-case tracking-normal">*</span>
        </label>
        <div class="relative border border-ink bg-paper focus-within:border-amber transition-colors @error('email') border-red-600 @enderror">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none text-ink-muted">
                <x-heroicon-o-envelope class="w-4 h-4" />
            </span>
            <input type="email"
                   id="checkout-email"
                   name="email"
                   x-model="form.email"
                   placeholder="your@email.com"
                   inputmode="email"
                   autocomplete="email"
                   required
                   class="w-full pl-10 pr-4 py-3 bg-transparent font-mono text-sm text-ink placeholder:text-ink-muted/60 placeholder:font-sans placeholder:text-xs placeholder:tracking-normal focus:outline-none">
        </div>
        @error('email')
            <p class="mt-2 flex items-center gap-1.5 font-mono text-[10px] tracking-[0.18em] uppercase text-red-600">
                <x-heroicon-s-exclamation-circle class="w-3 h-3" />
                {{ $message }}
            </p>
        @enderror
    </div>

    {{-- Phone --}}
    <div>
        <label for="checkout-phone" class="bp-spec block mb-2 text-ink">
            § Phone
            <span class="text-ink-muted/80 normal-case tracking-normal font-normal ml-1">(optional)</span>
        </label>
        <div class="relative border border-ink bg-paper focus-within:border-amber transition-colors @error('phone') border-red-600 @enderror">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none text-ink-muted">
                <x-heroicon-o-phone class="w-4 h-4" />
            </span>
            <input type="tel"
                   id="checkout-phone"
                   name="phone"
                   x-model="form.phone"
                   placeholder="+49 123 456 7890"
                   inputmode="tel"
                   autocomplete="tel"
                   class="w-full pl-10 pr-4 py-3 bg-transparent font-mono text-sm text-ink placeholder:text-ink-muted/60 placeholder:font-sans placeholder:text-xs placeholder:tracking-normal focus:outline-none">
        </div>
        @error('phone')
            <p class="mt-2 flex items-center gap-1.5 font-mono text-[10px] tracking-[0.18em] uppercase text-red-600">
                <x-heroicon-s-exclamation-circle class="w-3 h-3" />
                {{ $message }}
            </p>
        @enderror
    </div>

    {{-- B2B toggle --}}
    <label class="flex items-start gap-3 p-4 border border-rule-strong bg-ivory-alt cursor-pointer hover:border-ink transition-colors">
        <input type="checkbox"
               name="is_b2b"
               value="1"
               x-model="form.is_b2b"
               class="mt-0.5 w-4 h-4 border-ink text-amber focus:ring-amber focus:ring-offset-0 shrink-0">
        <div class="select-none flex-1">
            <p class="font-mono text-xs font-bold uppercase tracking-[0.18em] text-ink">This is a business order · B2B</p>
            <p class="mt-1 text-xs text-body">You will be asked for your VAT number in the next step.</p>
        </div>
    </label>

    @php $skipOtp = (bool) config('app.checkout_skip_otp'); @endphp

    {{-- Testing notice when OTP is bypassed --}}
    @if($skipOtp)
    <div class="border border-amber-ink bg-amber/10 px-5 py-4 flex items-start gap-3">
        <div class="w-8 h-8 border border-amber-ink bg-paper flex items-center justify-center shrink-0">
            <x-heroicon-s-beaker class="w-4 h-4 text-amber-ink" />
        </div>
        <div class="flex-1">
            <p class="bp-spec text-amber-ink">§ TEST · OTP · BYPASSED</p>
            <p class="text-xs text-body mt-1">Email verification is temporarily disabled (<code class="font-mono text-[11px]">CHECKOUT_SKIP_OTP=true</code>). Re-enable in <code class="font-mono text-[11px]">.env</code> before going live.</p>
        </div>
    </div>
    @endif

    {{-- OTP section (guests only) — hidden when bypass is on --}}
    <template x-if="!{{ $skipOtp ? 'true' : 'false' }} && isGuest && !otpVerified">
        <div class="border border-amber bg-amber/10">
            <div class="flex items-start gap-3 p-5">
                <div class="w-9 h-9 border border-amber bg-paper flex items-center justify-center shrink-0">
                    <x-heroicon-s-shield-check class="w-4 h-4 text-amber-ink" />
                </div>
                <div class="flex-1">
                    <p class="bp-spec text-amber-ink mb-1">§ Verify email</p>
                    <p class="text-xs text-body mb-4">
                        We will send a 6-digit code to
                        <span x-text="form.email || '…'" class="font-mono font-bold text-ink"></span>
                    </p>
                    <button type="button"
                            @click="sendOtp()"
                            :disabled="otpLoading || !form.email"
                            class="bp-btn-primary disabled:opacity-40 disabled:cursor-not-allowed">
                        <span x-show="!otpLoading" class="flex items-center gap-2">
                            <x-heroicon-o-paper-airplane class="w-3.5 h-3.5" />
                            <span x-text="otpSent ? 'Resend code' : 'Send code'"></span>
                        </span>
                        <span x-show="otpLoading" class="flex items-center gap-2">
                            <svg class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            Sending…
                        </span>
                    </button>
                </div>
            </div>

            {{-- OTP digit inputs --}}
            <div x-show="otpSent" x-cloak class="border-t border-amber bg-paper/70 px-5 py-5">
                <p class="bp-spec text-ink mb-3">§ Enter 6-digit code</p>
                <div class="flex gap-2 mb-4" id="otp-inputs">
                    <template x-for="index in 6" :key="index">
                        <input type="text"
                               :aria-label="'OTP digit ' + index"
                               inputmode="numeric"
                               maxlength="1"
                               autocomplete="one-time-code"
                               :value="otpDigits[index - 1] || ''"
                               @input="handleOtpInput($event, index - 1)"
                               @paste="handleOtpPaste($event)"
                               @keydown.backspace="handleOtpBackspace($event, index - 1)"
                               class="w-11 sm:w-12 h-12 text-center text-xl font-mono font-bold tabular-nums text-ink
                                      border border-ink bg-paper
                                      focus:outline-none focus:border-amber focus:ring-2 focus:ring-amber/30 transition-colors">
                    </template>
                </div>

                {{-- VERIFY BUTTON — always rendered when otpSent is true --}}
                <button type="button"
                        @click="verifyOtp()"
                        :disabled="otpDigits.join('').length < 6 || otpVerifying"
                        class="bp-btn-primary w-full justify-center disabled:opacity-40 disabled:cursor-not-allowed">
                    <span x-show="!otpVerifying" class="flex items-center gap-2">
                        <x-heroicon-s-shield-check class="w-4 h-4" />
                        Verify code
                    </span>
                    <span x-show="otpVerifying" class="flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        Verifying…
                    </span>
                </button>

                {{-- Helper row --}}
                <div class="mt-3 flex items-center justify-between gap-3">
                    <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">
                        <span x-text="otpDigits.join('').length"></span> / 6 digits
                    </span>
                    <button type="button"
                            @click="otpSent = false; otpDigits = ['','','','','','']"
                            class="font-mono text-[10px] font-bold uppercase tracking-[0.22em] text-ink-muted hover:text-ink transition-colors py-1.5">
                        Try a different email
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{-- Verified badge --}}
    <template x-if="otpVerified || !isGuest">
        <div class="flex items-center gap-3 p-4 border border-ink bg-ivory-alt">
            <div class="w-8 h-8 border border-ink bg-paper flex items-center justify-center shrink-0">
                <x-heroicon-s-check class="w-4 h-4 text-amber-ink" />
            </div>
            <div>
                <p class="bp-spec text-amber-ink">§ Email verified</p>
                <p class="text-xs text-body mt-0.5">Address confirmed · Ready to continue</p>
            </div>
        </div>
    </template>
</div>

<script>
function checkoutStep1() {
    return {
        form: {
            email: @json(old('email', $checkoutData['contact_email'] ?? auth()->user()?->email ?? '')),
            phone: @json(old('phone', $checkoutData['contact_phone'] ?? '')),
            is_b2b: {{ old('is_b2b', $checkoutData['is_b2b'] ?? false) ? 'true' : 'false' }}
        },
        isGuest:      {{ auth()->guest() ? 'true' : 'false' }},
        otpSent:      {{ auth()->guest() && !empty($checkoutData['contact_email'] ?? null) && !($checkoutData['otp_verified'] ?? false) ? 'true' : 'false' }},
        otpDigits:    ['', '', '', '', '', ''],
        otpLoading:   false,
        otpVerifying: false,
        otpVerified:  {{ ($checkoutData['otp_verified'] ?? false) || config('app.checkout_skip_otp') ? 'true' : 'false' }},

        init() {},

        async sendOtp() {
            if (!this.form.email) return;
            this.otpLoading = true;
            try {
                const res = await fetch('{{ route('frontend.auth.resend-otp', ['lang' => app()->getLocale()]) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ email: this.form.email, purpose: 'guest_checkout' })
                });
                if (res.ok) {
                    this.otpSent = true;
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Code sent to ' + this.form.email, type: 'success', title: 'Code Sent' } }));
                } else {
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Could not send code. Check your email address.', type: 'error' } }));
                }
            } finally {
                this.otpLoading = false;
            }
        },

        handleOtpInput(e, index) {
            const val = e.target.value.replace(/\D/g, '').slice(0, 1);
            e.target.value = val;
            const next = [...this.otpDigits];
            next[index] = val;
            this.otpDigits = next;
            if (val && index < 5) {
                const inputs = document.querySelectorAll('#otp-inputs input');
                inputs[index + 1]?.focus();
            }
        },

        handleOtpBackspace(e, index) {
            if (!e.target.value && index > 0) {
                const inputs = document.querySelectorAll('#otp-inputs input');
                inputs[index - 1]?.focus();
            }
        },

        handleOtpPaste(e) {
            const raw = (e.clipboardData || window.clipboardData).getData('text') || '';
            const digits = raw.replace(/\D/g, '').slice(0, 6).split('');
            if (digits.length === 0) return;
            e.preventDefault();
            const filled = [...this.otpDigits];
            for (let i = 0; i < 6; i++) filled[i] = digits[i] || '';
            this.otpDigits = filled;
            this.$nextTick(() => {
                const inputs = document.querySelectorAll('#otp-inputs input');
                inputs.forEach((el, i) => { el.value = filled[i] || ''; });
                (inputs[Math.min(digits.length, 5)] || inputs[5])?.focus();
            });
        },

        async verifyOtp() {
            this.otpVerifying = true;
            try {
                const res = await fetch('{{ route('frontend.auth.verify-otp', ['lang' => app()->getLocale()]) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ email: this.form.email, otp: this.otpDigits.join(''), purpose: 'guest_checkout' })
                });
                if (res.ok) {
                    this.otpVerified = true;
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Email verified successfully.', type: 'success' } }));
                } else {
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Invalid code. Please try again.', type: 'error', title: 'Incorrect Code' } }));
                }
            } finally {
                this.otpVerifying = false;
            }
        }
    };
}
</script>
@endsection
