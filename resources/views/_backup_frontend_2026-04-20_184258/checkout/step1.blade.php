@extends('frontend.checkout.layout')

@section('checkout_content')
<div x-data="checkoutStep1()" x-init="init()">
    <input type="hidden" name="otp_verified" :value="otpVerified ? '1' : '0'">

    {{-- Section header --}}
    <div class="flex items-center gap-3 mb-8 pb-6 border-b border-gray-100">
        <div class="w-10 h-10 rounded-xl bg-navy/8 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-navy/50" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
        </div>
        <div>
            <h2 class="font-display text-2xl font-black text-navy leading-tight">Contact Information</h2>
            <p class="text-sm text-muted font-medium mt-0.5">We'll use these details to keep you updated on your order.</p>
        </div>
    </div>

    <div class="space-y-5">

        {{-- Email field --}}
        <div>
            <label for="checkout-email" class="block text-xs font-bold text-navy/70 mb-2 uppercase tracking-wider">
                Email Address <span class="text-red-400 normal-case tracking-normal">*</span>
            </label>
            <div class="relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 pointer-events-none">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </span>
                <input type="email"
                       id="checkout-email"
                       name="email"
                       x-model="form.email"
                       placeholder="your@email.com"
                       inputmode="email"
                       autocomplete="email"
                       required
                       class="w-full pl-11 pr-4 py-3 text-sm rounded-xl border border-gray-300 text-navy font-medium bg-white
                              placeholder:text-gray-400 placeholder:font-normal
                              focus:outline-none focus:border-amber focus:ring-2 focus:ring-amber/20
                              transition-colors @error('email') border-red-400 bg-red-50 @enderror">
            </div>
            @error('email')
                <p class="mt-1.5 text-xs text-red-500 font-semibold flex items-center gap-1">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                    {{ $message }}
                </p>
            @enderror
        </div>

        {{-- Phone field --}}
        <div>
            <label for="checkout-phone" class="block text-xs font-bold text-navy/70 mb-2 uppercase tracking-wider">
                Phone
                <span class="normal-case tracking-normal text-gray-400 font-normal ml-1">(optional)</span>
            </label>
            <div class="relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 pointer-events-none">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                    </svg>
                </span>
                <input type="tel"
                       id="checkout-phone"
                       name="phone"
                       x-model="form.phone"
                       placeholder="+49 123 456 7890"
                       inputmode="tel"
                       autocomplete="tel"
                       class="w-full pl-11 pr-4 py-3 text-sm rounded-xl border border-gray-300 text-navy font-medium bg-white
                              placeholder:text-gray-400 placeholder:font-normal
                              focus:outline-none focus:border-amber focus:ring-2 focus:ring-amber/20
                              transition-colors @error('phone') border-red-400 bg-red-50 @enderror">
            </div>
            @error('phone')
                <p class="mt-1.5 text-xs text-red-500 font-semibold flex items-center gap-1">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                    {{ $message }}
                </p>
            @enderror
        </div>

        {{-- B2B Toggle --}}
        <div class="flex items-start gap-3 p-4 rounded-xl border border-gray-200 bg-gray-50/60 hover:border-navy/20 hover:bg-gray-50 transition-colors cursor-pointer"
             @click="$refs.b2bCheck.click()">
            <input type="checkbox"
                   name="is_b2b"
                   value="1"
                   x-model="form.is_b2b"
                   x-ref="b2bCheck"
                   @click.stop
                   class="mt-0.5 w-4 h-4 rounded border-gray-300 text-amber focus:ring-amber focus:ring-offset-0 shrink-0 cursor-pointer">
            <div class="select-none">
                <p class="text-sm font-bold text-navy">This is a business order (B2B)</p>
                <p class="text-xs text-muted font-medium mt-0.5">You'll be asked for your VAT number in the next step.</p>
            </div>
        </div>

        {{-- OTP section (guests only) --}}
        <template x-if="isGuest && !otpVerified">
            <div class="rounded-xl border border-amber/30 bg-amber/5 overflow-hidden">
                <div class="flex items-start gap-3 p-5">
                    <div class="w-9 h-9 rounded-lg bg-amber/15 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-4 h-4 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="font-bold text-navy text-sm mb-1">Verify Your Email</p>
                        <p class="text-xs text-amber-700 font-medium mb-4">
                            We'll send a 6-digit code to
                            <span x-text="form.email || '…'" class="font-mono font-bold"></span>
                        </p>
                        <button type="button"
                                @click="sendOtp()"
                                :disabled="otpLoading || !form.email"
                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg
                                       bg-navy text-white text-xs font-bold
                                       hover:bg-blue-900 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                            <template x-if="!otpLoading">
                                <span class="flex items-center gap-2">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                    </svg>
                                    Send Code
                                </span>
                            </template>
                            <template x-if="otpLoading">
                                <span class="flex items-center gap-2">
                                    <svg class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                    Sending…
                                </span>
                            </template>
                        </button>
                    </div>
                </div>

                {{-- OTP digit inputs --}}
                <template x-if="otpSent">
                    <div class="border-t border-amber/20 bg-white/60 px-5 py-5">
                        <p class="text-xs font-bold text-navy mb-3">Enter the 6-digit code sent to your email</p>
                        <div class="flex gap-2 mb-4" id="otp-inputs">
                            <template x-for="(digit, index) in 6" :key="index">
                                <input type="text"
                                       :aria-label="'OTP digit ' + (index + 1)"
                                       inputmode="numeric"
                                       maxlength="1"
                                       :value="otpDigits[index] || ''"
                                       @input="handleOtpInput($event, index)"
                                       @keydown.backspace="handleOtpBackspace($event, index)"
                                       class="w-12 h-12 text-center text-xl font-black text-navy
                                              border border-gray-300 rounded-xl bg-white
                                              focus:outline-none focus:border-amber focus:ring-2 focus:ring-amber/20
                                              transition-colors">
                            </template>
                        </div>
                        <button type="button"
                                @click="verifyOtp()"
                                :disabled="otpDigits.join('').length < 6 || otpVerifying"
                                class="w-full py-3 rounded-xl bg-navy text-white font-bold text-sm
                                       hover:bg-blue-900 disabled:opacity-40 disabled:cursor-not-allowed
                                       transition-colors flex items-center justify-center gap-2">
                            <template x-if="!otpVerifying"><span>Verify Code</span></template>
                            <template x-if="otpVerifying">
                                <span class="flex items-center gap-2">
                                    <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                    Verifying…
                                </span>
                            </template>
                        </button>
                        <button type="button"
                                @click="otpSent = false; otpDigits = ['','','','','','']"
                                class="w-full mt-2.5 text-xs font-semibold text-muted hover:text-navy transition-colors py-1.5">
                            Try a different email
                        </button>
                    </div>
                </template>
            </div>
        </template>

        {{-- Verified badge --}}
        <template x-if="otpVerified || !isGuest">
            <div class="flex items-center gap-3 p-4 rounded-xl bg-emerald-50 border border-emerald-200">
                <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-bold text-emerald-800">Email verified</p>
                    <p class="text-xs text-emerald-600 font-medium mt-0.5">Your address is confirmed. Ready to continue!</p>
                </div>
            </div>
        </template>

    </div>
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
        otpVerified:  {{ ($checkoutData['otp_verified'] ?? false) ? 'true' : 'false' }},

        init() {},

        async sendOtp() {
            if (!this.form.email) return;
            this.otpLoading = true;
            try {
                const res = await fetch('/{{ app()->getLocale() }}/resend-otp', {
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
            this.otpDigits[index] = val;
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

        async verifyOtp() {
            this.otpVerifying = true;
            try {
                const res = await fetch('/{{ app()->getLocale() }}/verify-otp', {
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
