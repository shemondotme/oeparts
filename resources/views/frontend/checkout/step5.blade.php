@extends('frontend.checkout.layout')

@section('checkout_content')
<div x-data="{ paymentMethod: '{{ old('payment_method', $checkoutData['payment_method'] ?? 'card') }}' }" class="space-y-6">

    {{-- Sub-header --}}
    <header class="pb-4 border-b border-rule">
        <h2 class="font-display text-2xl md:text-3xl font-extrabold text-ink leading-tight tracking-[-0.02em]">
            {{ ui_copy('checkout_payment_method_heading', 'checkout.payment_method_heading') }}<span class="text-amber-ink">.</span>
        </h2>
        <p class="mt-2 font-mono text-[11px] tracking-[0.18em] uppercase text-ink-muted">
            {{ ui_copy('checkout_payment_method_subtitle', 'checkout.payment_method_subtitle') }}
        </p>
    </header>

    {{-- Method radio options --}}
    <div class="border border-ink bg-paper">

        {{-- Credit / Debit Card --}}
        <label class="flex items-start gap-4 p-5 cursor-pointer border-b border-rule transition-colors"
               :class="paymentMethod === 'card' ? 'bg-amber/10' : 'bg-paper hover:bg-ivory-alt'">
            <div class="flex items-center gap-3 shrink-0 mt-0.5">
                <span class="font-mono text-[10px] tabular-nums tracking-[0.18em] uppercase text-ink-muted w-6">01</span>
                <input type="radio" name="payment_method" value="card"
                       x-model="paymentMethod" required
                       class="w-4 h-4 border-ink text-amber-ink focus:ring-amber-ink focus:ring-offset-0">
            </div>
            <div class="w-10 h-10 border border-rule-strong bg-ivory-alt flex items-center justify-center shrink-0"
                 :class="paymentMethod === 'card' ? 'border-ink bg-paper' : ''">
                <x-heroicon-o-credit-card class="w-5 h-5 text-ink" />
            </div>
            <div class="flex-1">
                <p class="font-display text-base font-bold text-ink tracking-[-0.01em]">{{ ui_copy('checkout_card_option_title', 'checkout.card_option_title') }}</p>
                <p class="mt-1 font-mono text-[11px] tracking-[0.18em] uppercase text-ink-muted">
                    {{ ui_copy('checkout_card_option_note', 'checkout.card_option_note') }}
                </p>
            </div>
        </label>

        {{-- Bank Transfer --}}
        <label class="flex items-start gap-4 p-5 cursor-pointer transition-colors"
               :class="paymentMethod === 'bank_transfer' ? 'bg-amber/10' : 'bg-paper hover:bg-ivory-alt'">
            <div class="flex items-center gap-3 shrink-0 mt-0.5">
                <span class="font-mono text-[10px] tabular-nums tracking-[0.18em] uppercase text-ink-muted w-6">02</span>
                <input type="radio" name="payment_method" value="bank_transfer"
                       x-model="paymentMethod" required
                       class="w-4 h-4 border-ink text-amber-ink focus:ring-amber-ink focus:ring-offset-0">
            </div>
            <div class="w-10 h-10 border border-rule-strong bg-ivory-alt flex items-center justify-center shrink-0"
                 :class="paymentMethod === 'bank_transfer' ? 'border-ink bg-paper' : ''">
                <x-heroicon-o-building-library class="w-5 h-5 text-ink" />
            </div>
            <div class="flex-1">
                <p class="font-display text-base font-bold text-ink tracking-[-0.01em]">{{ ui_copy('checkout_bank_option_title', 'checkout.bank_option_title') }}</p>
                <p class="mt-1 font-mono text-[11px] tracking-[0.18em] uppercase text-ink-muted">
                    {{ ui_copy('checkout_bank_option_note', 'checkout.bank_option_note') }}
                </p>
            </div>
        </label>
    </div>

    {{-- Contextual info for Card --}}
    <template x-if="paymentMethod === 'card'">
        <div class="border border-rule-strong bg-ivory-alt p-5 flex items-start gap-3">
            <div class="w-9 h-9 border border-ink bg-paper flex items-center justify-center shrink-0">
                <x-heroicon-s-lock-closed class="w-4 h-4 text-amber-ink" />
            </div>
            <div class="flex-1">
                <p class="bp-spec text-amber-ink mb-1">{{ ui_copy('checkout_secure_card_checkout', 'checkout.secure_card_checkout') }}</p>
                <p class="text-sm text-body leading-relaxed">
                    {{ ui_copy('checkout_secure_card_checkout_note', 'checkout.secure_card_checkout_note') }}
                </p>
            </div>
        </div>
    </template>

    {{-- Contextual info for Bank transfer --}}
    <template x-if="paymentMethod === 'bank_transfer'">
        <div class="border border-rule-strong bg-ivory-alt p-5 flex items-start gap-3">
            <div class="w-9 h-9 border border-ink bg-paper flex items-center justify-center shrink-0">
                <x-heroicon-s-information-circle class="w-4 h-4 text-amber-ink" />
            </div>
            <div class="flex-1">
                <p class="bp-spec text-amber-ink mb-1">{{ ui_copy('checkout_bank_transfer_instructions', 'checkout.bank_transfer_instructions') }}</p>
                <p class="text-sm text-body leading-relaxed">
                    {{ ui_copy('checkout_bank_transfer_instructions_note', 'checkout.bank_transfer_instructions_note') }}
                </p>
            </div>
        </div>
    </template>

    {{-- Customer note --}}
    <div>
        <label for="customer_note" class="bp-spec block mb-2 text-ink">
            {{ ui_copy('checkout_order_note_label', 'checkout.order_note_label') }}
            <span class="text-ink-muted/80 normal-case tracking-normal font-normal ml-1">{{ ui_copy('checkout_optional', 'checkout.optional') }}</span>
        </label>
        <div class="border border-ink bg-paper focus-within:border-amber transition-colors">
            <textarea id="customer_note"
                      name="customer_note"
                      rows="3"
                      placeholder="{{ ui_copy('checkout_order_note_placeholder', 'checkout.order_note_placeholder') }}"
                       class="w-full px-4 py-3 bg-transparent font-mono text-sm text-ink placeholder:text-ink-muted/60 placeholder:font-sans placeholder:text-xs placeholder:tracking-normal focus:outline-none resize-y">{{ old('customer_note', $checkoutData['customer_note'] ?? '') }}</textarea>
        </div>
    </div>

    {{-- Trust strip --}}
    <div class="border border-rule bg-ivory-alt p-4 grid grid-cols-3 divide-x divide-rule">
        <div class="flex items-center justify-center gap-2 px-2">
            <x-heroicon-s-shield-check class="w-4 h-4 text-amber-ink shrink-0" />
            <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink">256-bit SSL</span>
        </div>
        <div class="flex items-center justify-center gap-2 px-2">
            <x-heroicon-s-lock-closed class="w-4 h-4 text-amber-ink shrink-0" />
            <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink">Encrypted</span>
        </div>
        <div class="flex items-center justify-center gap-2 px-2">
            <x-heroicon-s-credit-card class="w-4 h-4 text-amber-ink shrink-0" />
            <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink">Airwallex</span>
        </div>
    </div>
</div>
@endsection
