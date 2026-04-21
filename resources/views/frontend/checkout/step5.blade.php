@extends('frontend.checkout.layout')

@section('checkout_content')
<div x-data="{
        paymentMethod: old('payment_method', 'card'),
        cardNumber: '',
        expiryDate: '',
        cvc: ''
     }" class="space-y-6">

    {{-- ── Page Header ────────────────────────────────────────────────── --}}
    <div class="mb-6">
        <h2 class="font-display text-lg font-bold text-navy mb-2">Payment Method</h2>
        <p class="text-muted text-sm">Choose how you'd like to pay. All transactions are secure and encrypted.</p>
    </div>

    {{-- ── Payment Methods ───────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
        <div class="space-y-4">
            {{-- Card Payment --}}
            <label class="block p-5 rounded-xl border-2 border-gray-200 hover:border-amber/50 hover:shadow-md
                          cursor-pointer transition-all duration-200"
                   :class="paymentMethod === 'card' ? 'border-amber bg-amber/5 shadow-md' : ''">
                <div class="flex items-start gap-4">
                    <input type="radio"
                           name="payment_method"
                           value="card"
                           x-model="paymentMethod"
                           required
                           class="w-5 h-5 mt-0.5 text-amber focus:ring-amber">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="w-10 h-10 rounded-lg bg-navy/5 flex items-center justify-center">
                                <x-heroicon-o-credit-card class="w-5 h-5 text-navy/60" />
                            </div>
                            <div>
                                <span class="font-bold text-navy text-base">Credit / Debit Card</span>
                                <p class="text-xs text-muted mt-0.5">Visa, Mastercard, or American Express</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 mt-3">
                            <span class="px-2 py-1 bg-navy/10 rounded text-xs font-bold text-navy">VISA</span>
                            <span class="px-2 py-1 bg-navy/10 rounded text-xs font-bold text-navy">MC</span>
                            <span class="px-2 py-1 bg-navy/10 rounded text-xs font-bold text-navy">AMEX</span>
                        </div>
                    </div>
                </div>
            </label>

            {{-- Bank Transfer --}}
            <label class="block p-5 rounded-xl border-2 border-gray-200 hover:border-amber/50 hover:shadow-md
                          cursor-pointer transition-all duration-200"
                   :class="paymentMethod === 'bank' ? 'border-amber bg-amber/5 shadow-md' : ''">
                <div class="flex items-start gap-4">
                    <input type="radio"
                           name="payment_method"
                           value="bank"
                           x-model="paymentMethod"
                           required
                           class="w-5 h-5 mt-0.5 text-amber focus:ring-amber">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center">
                                <x-heroicon-o-building-library class="w-5 h-5 text-emerald-600" />
                            </div>
                            <div>
                                <span class="font-bold text-navy text-base">Bank Transfer</span>
                                <p class="text-xs text-muted mt-0.5">SEPA transfer - we'll provide details after checkout</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 mt-3">
                            <x-heroicon-o-information-circle class="w-4 h-4 text-blue-600" />
                            <span class="text-xs text-blue-700 font-semibold">Bank details shown after order confirmation</span>
                        </div>
                    </div>
                </div>
            </label>
        </div>
    </div>

    {{-- ── Card Details (conditionally shown) ────────────────────────── --}}
    <template x-if="paymentMethod === 'card'">
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
            <h3 class="font-display text-base font-bold text-navy mb-6 flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-amber/10 flex items-center justify-center">
                    <x-heroicon-o-lock-closed class="w-4 h-4 text-amber" />
                </div>
                Card Information
            </h3>

            <div class="space-y-5">
                {{-- Card Number --}}
                <div>
                    <label class="text-sm font-bold text-navy block mb-2">Card Number</label>
                    <input type="text"
                           x-model="cardNumber"
                           @input="cardNumber = cardNumber.replace(/[^0-9]/g, '').replace(/(.{4})/g, '$1 ').trim()"
                           placeholder="4532 1234 5678 9010"
                           maxlength="19"
                           inputmode="numeric"
                           class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 text-navy font-mono
                                  focus:outline-none focus:border-amber focus:ring-4 focus:ring-amber/20
                                  transition-all placeholder:font-sans placeholder:normal-case">
                </div>

                <div class="grid grid-cols-2 gap-5">
                    {{-- Expiry Date --}}
                    <div>
                        <label class="text-sm font-bold text-navy block mb-2">Expiry Date</label>
                        <input type="text"
                               x-model="expiryDate"
                               @input="if(expiryDate.length === 2 && !expiryDate.includes('/')) expiryDate += '/'"
                               placeholder="MM/YY"
                               maxlength="5"
                               inputmode="numeric"
                               class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 text-navy font-mono
                                      focus:outline-none focus:border-amber focus:ring-4 focus:ring-amber/20
                                      transition-all placeholder:font-sans placeholder:normal-case">
                    </div>

                    {{-- CVC --}}
                    <div>
                        <label class="text-sm font-bold text-navy block mb-2">CVC</label>
                        <input type="text"
                               x-model="cvc"
                               @input="cvc = cvc.replace(/[^0-9]/g, '')"
                               placeholder="123"
                               maxlength="4"
                               inputmode="numeric"
                               class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 text-navy font-mono
                                      focus:outline-none focus:border-amber focus:ring-4 focus:ring-amber/20
                                      transition-all placeholder:font-sans placeholder:normal-case">
                    </div>
                </div>
            </div>
        </div>
    </template>

    {{-- ── Bank Transfer Info (conditionally shown) --}}
    <template x-if="paymentMethod === 'bank'">
        <div class="bg-blue-50 rounded-2xl p-6 border-2 border-blue-100">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center shrink-0">
                    <x-heroicon-o-information-circle class="w-5 h-5 text-blue-600" />
                </div>
                <div class="flex-1">
                    <h3 class="font-bold text-blue-800 mb-2">Bank Transfer Instructions</h3>
                    <p class="text-sm text-blue-700 mb-3">
                        After placing your order, we'll provide you with our bank details. Please transfer the full amount within 3 business days.
                    </p>
                    <div class="p-4 bg-white rounded-xl border border-blue-200">
                        <p class="text-xs text-blue-700 font-semibold">Your order will be reserved for 72 hours after confirmation.</p>
                    </div>
                </div>
            </div>
        </div>
    </template>

    {{-- ── Security Badge ───────────────────────────────────────────── --}}
    <div class="flex items-center justify-center gap-3 p-4 bg-emerald-50 rounded-xl border border-emerald-100">
        <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center">
            <x-heroicon-s-shield-check class="w-5 h-5 text-emerald-600" />
        </div>
        <div class="text-sm">
            <p class="font-bold text-emerald-700">256-bit SSL Encryption</p>
            <p class="text-xs text-emerald-600">Your payment information is secure and encrypted</p>
        </div>
    </div>
</div>
@endsection
