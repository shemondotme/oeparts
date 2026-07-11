@extends('frontend.checkout.layout')

@section('checkout_content')
<div class="space-y-6">
    <header class="pb-4 border-b border-rule">
        <h2 class="font-display text-2xl md:text-3xl font-extrabold text-ink leading-tight tracking-[-0.02em]">
            {{ ui_copy('checkout_contact_info_heading', 'checkout.contact_info_heading') }}<span class="text-amber">.</span>
        </h2>
        <p class="mt-2 font-mono text-[11px] tracking-[0.18em] uppercase text-ink-muted">
            {{ ui_copy('checkout_contact_info_subtitle', 'checkout.contact_info_subtitle') }}
        </p>
    </header>

    <div>
        <label for="checkout-email" class="bp-spec block mb-2 text-ink">
            {{ ui_copy('checkout_email_address_label', 'checkout.email_address_label') }} <span class="text-red-600 normal-case tracking-normal">*</span>
        </label>
        <div class="relative border border-ink bg-paper focus-within:border-amber transition-colors @error('email') border-red-600 @enderror">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none text-ink-muted">
                <x-heroicon-o-envelope class="w-4 h-4" />
            </span>
            <input type="email" id="checkout-email" name="email"
                   value="{{ old('email', $checkoutData['contact_email'] ?? auth()->user()?->email ?? '') }}"
                   placeholder="your@email.com" inputmode="email" autocomplete="email" required
                   class="w-full pl-10 pr-4 py-3 bg-transparent font-mono text-sm text-ink placeholder:text-ink-muted/60 placeholder:font-sans placeholder:text-xs placeholder:tracking-normal focus:outline-none">
        </div>
        @error('email')
            <p class="mt-2 flex items-center gap-1.5 font-mono text-[10px] tracking-[0.18em] uppercase text-red-600">
                <x-heroicon-s-exclamation-circle class="w-3 h-3" /> {{ $message }}
            </p>
        @enderror
    </div>

    <div>
        <label for="checkout-phone" class="bp-spec block mb-2 text-ink">
            {{ ui_copy('checkout_phone_label', 'checkout.phone_label') }} <span class="text-ink-muted/80 normal-case tracking-normal font-normal ml-1">{{ ui_copy('checkout_optional', 'checkout.optional') }}</span>
        </label>
        <div class="relative border border-ink bg-paper focus-within:border-amber transition-colors @error('phone') border-red-600 @enderror">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none text-ink-muted">
                <x-heroicon-o-phone class="w-4 h-4" />
            </span>
            <input type="tel" id="checkout-phone" name="phone"
                   value="{{ old('phone', $checkoutData['contact_phone'] ?? '') }}"
                   placeholder="+49 123 456 7890" inputmode="tel" autocomplete="tel"
                   class="w-full pl-10 pr-4 py-3 bg-transparent font-mono text-sm text-ink placeholder:text-ink-muted/60 placeholder:font-sans placeholder:text-xs placeholder:tracking-normal focus:outline-none">
        </div>
        @error('phone')
            <p class="mt-2 flex items-center gap-1.5 font-mono text-[10px] tracking-[0.18em] uppercase text-red-600">
                <x-heroicon-s-exclamation-circle class="w-3 h-3" /> {{ $message }}
            </p>
        @enderror
    </div>
</div>
@endsection
