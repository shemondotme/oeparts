@extends('frontend.checkout.layout')

@section('checkout_content')
<div x-data="checkoutStep1()" x-init="init()">
    <h2 class="font-display text-lg font-bold text-navy mb-8">Contact Information</h2>

    <div class="space-y-6">
        {{-- Email Field --}}
        <div>
            <label class="flex items-center gap-2 text-sm font-bold text-navy mb-3">
                <x-heroicon-o-envelope class="w-4 h-4 text-amber" />
                Email Address
            </label>
            <input type="email"
                   x-model="form.email"
                   placeholder="your@email.com"
                   inputmode="email"
                   class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 text-navy
                          focus:outline-none focus:border-amber focus:ring-4 focus:ring-amber/20
                          transition-all @error('email') border-red-400 @enderror"
                   required>
            @error('email')
                <p class="mt-2 text-sm text-red-500 flex items-center gap-1">
                    <x-heroicon-s-exclamation-circle class="w-4 h-4" />
                    {{ $message }}
                </p>
            @enderror
        </div>

        {{-- Phone Field --}}
        <div>
            <label class="flex items-center gap-2 text-sm font-bold text-navy mb-3">
                <x-heroicon-o-phone class="w-4 h-4 text-amber" />
                Phone Number <span class="text-muted text-xs font-normal">(optional)</span>
            </label>
            <input type="tel"
                   x-model="form.phone"
                   placeholder="+49 123 456 7890"
                   inputmode="tel"
                   class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 text-navy
                          focus:outline-none focus:border-amber focus:ring-4 focus:ring-amber/20
                          transition-all @error('phone') border-red-400 @enderror">
            @error('phone')
                <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        {{-- B2B Toggle --}}
        <div class="p-4 rounded-xl bg-gray-50 border border-gray-200">
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox"
                       x-model="form.is_b2b"
                       class="w-4 h-4 rounded border-gray-300 text-amber cursor-pointer">
                <span class="text-sm font-semibold text-navy">This is a business order</span>
            </label>
            <p class="text-xs text-muted mt-2 ml-7">You'll be asked for VAT details in the next step</p>
        </div>

        {{-- OTP Section (for guests) --}}
        <template x-if="isGuest && !otpVerified">
            <div class="bg-amber/10 border-2 border-amber/20 rounded-xl p-5">
                <div class="flex items-start gap-3">
                    <x-heroicon-o-shield-check class="w-5 h-5 text-amber shrink-0 mt-0.5" />
                    <div>
                        <h3 class="font-bold text-navy mb-2">Verify Your Email</h3>
                        <p class="text-sm text-amber mb-4">
                            We'll send a one-time code to <span x-text="form.email" class="font-mono font-bold"></span>
                        </p>

                        <button type="button"
                                @click="sendOtp()"
                                :disabled="otpLoading"
                                class="px-4 py-2 bg-amber text-navy font-bold text-sm rounded-lg
                                       hover:bg-amber/90 disabled:opacity-50 transition-all">
                            <template x-if="!otpLoading">
                                Send Code
                            </template>
                            <template x-if="otpLoading">
                                <span class="flex items-center gap-1.5">
                                    <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    Sending...
                                </span>
                            </template>
                        </button>
                    </div>
                </div>

                {{-- OTP Input (shown after sending) --}}
                <template x-if="otpSent">
                    <div class="mt-5 pt-5 border-t border-amber/20">
                        <p class="text-sm font-bold text-navy mb-3">Enter the 6-digit code</p>
                        <div class="grid grid-cols-6 gap-2 mb-4">
                            <template x-for="(digit, index) in 6" :key="index">
                                <input type="text"
                                       inputmode="numeric"
                                       maxlength="1"
                                       :value="otpDigits[index] || ''"
                                       @input="handleOtpInput($event, index)"
                                       @keydown.backspace="handleOtpBackspace($event, index)"
                                       class="w-full h-14 text-center text-2xl font-bold border-2 border-gray-200
                                              rounded-lg focus:outline-none focus:border-amber focus:ring-2 focus:ring-amber/10
                                              transition-all">
                            </template>
                        </div>

                        <button type="button"
                                @click="verifyOtp()"
                                :disabled="otpDigits.join('').length < 6 || otpVerifying"
                                class="w-full px-4 py-2.5 bg-navy text-white font-bold rounded-lg
                                       hover:bg-navy/90 disabled:opacity-50 transition-all">
                            <template x-if="!otpVerifying">
                                Verify Code
                            </template>
                            <template x-if="otpVerifying">
                                <span class="flex items-center justify-center gap-1.5">
                                    <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    Verifying...
                                </span>
                            </template>
                        </button>

                        <button type="button"
                                @click="otpSent = false; otpDigits = []"
                                class="w-full mt-2 text-sm text-muted hover:text-navy font-semibold transition-colors">
                            Try Different Email
                        </button>
                    </div>
                </template>
            </div>
        </template>

        {{-- Success Message --}}
        <template x-if="otpVerified || !isGuest">
            <div class="p-4 rounded-xl bg-emerald-50 border-2 border-emerald-200 flex items-start gap-3">
                <x-heroicon-s-check-circle class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" />
                <div>
                    <p class="font-bold text-emerald-700">Email Verified</p>
                    <p class="text-sm text-emerald-600 mt-1">Your email address is confirmed. Ready to proceed!</p>
                </div>
            </div>
        </template>
    </div>
</div>

<script>
function checkoutStep1() {
    return {
        form: {
            email: '{{ auth()->user()?->email ?? old('email', '') }}',
            phone: '{{ old('phone', '') }}',
            is_b2b: {{ old('is_b2b') ? 'true' : 'false' }}
        },
        isGuest: {{ auth()->guest() ? 'true' : 'false' }},
        otpSent: false,
        otpDigits: ['', '', '', '', '', ''],
        otpLoading: false,
        otpVerifying: false,
        otpVerified: {{ session('otp_verified') ? 'true' : 'false' }},

        init() {
            // Initialize
        },

        async sendOtp() {
            if (!this.form.email) return;
            this.otpLoading = true;
            try {
                const res = await fetch('/{{ app()->getLocale() }}/auth/send-otp', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                    },
                    body: JSON.stringify({ email: this.form.email })
                });
                if (res.ok) {
                    this.otpSent = true;
                }
            } finally {
                this.otpLoading = false;
            }
        },

        handleOtpInput(e, index) {
            const value = e.target.value.toUpperCase();
            if (!/^\d?$/.test(value)) {
                e.target.value = '';
                return;
            }
            this.otpDigits[index] = value;
            if (value && index < 5) {
                e.target.nextElementSibling?.focus();
            }
        },

        handleOtpBackspace(e, index) {
            if (index > 0 && !e.target.value) {
                e.target.previousElementSibling?.focus();
            }
        },

        async verifyOtp() {
            this.otpVerifying = true;
            try {
                const res = await fetch('/{{ app()->getLocale() }}/auth/verify-otp', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                    },
                    body: JSON.stringify({
                        email: this.form.email,
                        otp: this.otpDigits.join('')
                    })
                });
                if (res.ok) {
                    this.otpVerified = true;
                }
            } finally {
                this.otpVerifying = false;
            }
        }
    };
}
</script>
@endsection
