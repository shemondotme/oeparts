@extends('frontend.checkout.layout')

@section('checkout_content')
<div x-data="{ paymentMethod: '{{ old('payment_method', $checkoutData['payment_method'] ?? 'card') }}' }">

    <div class="flex items-center gap-3 mb-8 pb-6 border-b border-gray-100">
        <div class="w-10 h-10 rounded-xl bg-navy/8 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-navy/50" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
            </svg>
        </div>
        <div>
            <h2 class="font-display text-2xl font-black text-navy leading-tight">Payment Method</h2>
            <p class="text-sm text-muted font-medium mt-0.5">Choose how you would like to pay. Secure payment details are collected after the order is created.</p>
        </div>
    </div>

    <div class="space-y-3 mb-6">
        <label class="flex items-start gap-4 p-4 sm:p-5 rounded-xl border-2 cursor-pointer transition-all duration-150"
               :class="paymentMethod === 'card' ? 'border-amber bg-amber/4 shadow-sm' : 'border-gray-200 bg-white hover:border-gray-300'">
            <input type="radio" name="payment_method" value="card"
                   x-model="paymentMethod" required
                   class="w-4 h-4 mt-0.5 text-amber border-gray-300 focus:ring-amber focus:ring-offset-0 shrink-0">
            <div class="w-10 h-10 rounded-xl bg-navy/5 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-navy/50" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                </svg>
            </div>
            <div class="flex-1">
                <p class="font-bold text-navy text-sm mb-0.5">Credit / Debit Card</p>
                <p class="text-xs text-muted font-medium">Pay securely with Airwallex on the next page.</p>
            </div>
        </label>

        <label class="flex items-start gap-4 p-4 sm:p-5 rounded-xl border-2 cursor-pointer transition-all duration-150"
               :class="paymentMethod === 'bank_transfer' ? 'border-amber bg-amber/4 shadow-sm' : 'border-gray-200 bg-white hover:border-gray-300'">
            <input type="radio" name="payment_method" value="bank_transfer"
                   x-model="paymentMethod" required
                   class="w-4 h-4 mt-0.5 text-amber border-gray-300 focus:ring-amber focus:ring-offset-0 shrink-0">
            <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/>
                </svg>
            </div>
            <div class="flex-1">
                <p class="font-bold text-navy text-sm mb-0.5">Bank Transfer (SEPA)</p>
                <p class="text-xs text-muted font-medium">We’ll show bank details immediately after order confirmation.</p>
            </div>
        </label>
    </div>

    <template x-if="paymentMethod === 'card'">
        <div class="rounded-xl border border-blue-100 bg-blue-50/60 p-5">
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-lg bg-blue-100 flex items-center justify-center shrink-0 mt-0.5">
                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <div>
                    <p class="font-bold text-blue-900 text-sm mb-2">Secure card checkout</p>
                    <p class="text-sm text-blue-700 font-medium leading-relaxed">
                        Card details are collected securely by Airwallex after the order is created. This checkout step stores only your payment preference.
                    </p>
                </div>
            </div>
        </div>
    </template>

    <template x-if="paymentMethod === 'bank_transfer'">
        <div class="rounded-xl border border-blue-100 bg-blue-50/60 p-5">
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-lg bg-blue-100 flex items-center justify-center shrink-0 mt-0.5">
                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="font-bold text-blue-900 text-sm mb-2">Bank transfer instructions</p>
                    <p class="text-sm text-blue-700 font-medium leading-relaxed">
                        We’ll create the order first, then show your bank details and payment reference on the next page.
                    </p>
                </div>
            </div>
        </div>
    </template>

    <div class="rounded-xl border border-gray-200 bg-gray-50/40 p-5">
        <label for="customer_note" class="block text-xs font-bold text-navy/70 mb-2 uppercase tracking-wider">Order Note (optional)</label>
        <textarea id="customer_note"
                  name="customer_note"
                  rows="3"
                  placeholder="Add any delivery or order notes"
                  class="w-full px-4 py-3 text-sm rounded-xl border border-gray-300 text-navy font-medium bg-white placeholder:text-gray-400 focus:outline-none focus:border-amber focus:ring-2 focus:ring-amber/20 transition-colors">{{ old('customer_note', $checkoutData['customer_note'] ?? '') }}</textarea>
    </div>

    <div class="mt-5 flex flex-wrap items-center justify-center gap-4 py-4 px-5 rounded-xl bg-gray-50 border border-gray-200">
        <div class="flex items-center gap-1.5">
            <svg class="w-4 h-4 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <span class="text-xs font-bold text-gray-600">256-bit SSL</span>
        </div>
        <div class="w-px h-4 bg-gray-300"></div>
        <div class="flex items-center gap-1.5">
            <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            <span class="text-xs font-bold text-gray-600">Encrypted Payment</span>
        </div>
        <div class="w-px h-4 bg-gray-300"></div>
        <div class="flex items-center gap-1.5">
            <svg class="w-4 h-4 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <span class="text-xs font-bold text-gray-600">Airwallex Protected</span>
        </div>
    </div>

</div>
@endsection
