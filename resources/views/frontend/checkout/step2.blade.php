@extends('frontend.checkout.layout')

@section('checkout_content')
@php
    $addr = $checkoutData['shipping_address'] ?? [];

    $europeanCountryCodes = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR',
        'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK',
        'SI', 'ES', 'SE', 'GB', 'CH', 'NO', 'IS', 'LI', 'MC', 'AD', 'SM', 'VA',
        'AL', 'BA', 'ME', 'MK', 'RS', 'XK', 'MD', 'UA',
    ];

    // Localized country display names via PHP's intl extension (app/helpers.php
    // localized_country_name()) — avoids hand-translating 42 names x4 locales,
    // and correctly reflects whatever locale the storefront visitor is in.
    $europeanCountries = collect($europeanCountryCodes)
        ->mapWithKeys(fn ($code) => [$code => localized_country_name($code)])
        ->sort()
        ->all();
@endphp

<div class="space-y-6">

    {{-- Sub-header --}}
    <header class="pb-4 border-b border-rule">
        <h2 class="font-display text-2xl md:text-3xl font-extrabold text-ink leading-tight tracking-[-0.02em]">
            {{ ui_copy('checkout_shipping_address_heading', 'checkout.shipping_address_heading') }}<span class="text-amber-ink">.</span>
        </h2>
        <p class="mt-2 font-mono text-[11px] tracking-[0.18em] uppercase text-ink-muted">
            {{ ui_copy('checkout_shipping_address_subtitle', 'checkout.shipping_address_subtitle') }}
        </p>
    </header>

    {{-- First / Last name --}}
    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label for="checkout_first_name" class="bp-spec block mb-2 text-ink">
                {{ ui_copy('checkout_first_name_label', 'checkout.first_name_label') }} <span class="text-red-600 normal-case tracking-normal font-normal">*</span>
            </label>
            <div class="border border-ink bg-paper focus-within:border-amber-ink transition-colors @error('first_name') border-red-600 @enderror">
                <input type="text" id="checkout_first_name" name="first_name"
                       value="{{ old('first_name', $addr['first_name'] ?? '') }}"
                       required autocomplete="given-name"
                       class="w-full px-4 py-3 bg-transparent font-mono text-sm text-ink placeholder:text-ink-muted/60 focus:outline-none">
            </div>
            @error('first_name')
                <p class="mt-2 font-mono text-[10px] tracking-[0.18em] uppercase text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="checkout_last_name" class="bp-spec block mb-2 text-ink">
                {{ ui_copy('checkout_last_name_label', 'checkout.last_name_label') }} <span class="text-red-600 normal-case tracking-normal font-normal">*</span>
            </label>
            <div class="border border-ink bg-paper focus-within:border-amber-ink transition-colors @error('last_name') border-red-600 @enderror">
                <input type="text" id="checkout_last_name" name="last_name"
                       value="{{ old('last_name', $addr['last_name'] ?? '') }}"
                       required autocomplete="family-name"
                       class="w-full px-4 py-3 bg-transparent font-mono text-sm text-ink placeholder:text-ink-muted/60 focus:outline-none">
            </div>
            @error('last_name')
                <p class="mt-2 font-mono text-[10px] tracking-[0.18em] uppercase text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    {{-- Street --}}
    <div>
        <label for="checkout_street" class="bp-spec block mb-2 text-ink">
            {{ ui_copy('checkout_street_address_label', 'checkout.street_address_label') }} <span class="text-red-600 normal-case tracking-normal font-normal">*</span>
        </label>
        <div class="border border-ink bg-paper focus-within:border-amber-ink transition-colors @error('street') border-red-600 @enderror">
            <input type="text" id="checkout_street" name="street"
                   value="{{ old('street', $addr['street'] ?? '') }}"
                   placeholder="{{ ui_copy('checkout_street_placeholder', 'checkout.street_placeholder') }}"
                   required autocomplete="street-address"
                   class="w-full px-4 py-3 bg-transparent font-mono text-sm text-ink placeholder:text-ink-muted/60 placeholder:font-sans placeholder:text-xs focus:outline-none">
        </div>
        @error('street')
            <p class="mt-2 font-mono text-[10px] tracking-[0.18em] uppercase text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- City + Postal --}}
    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label for="checkout_city" class="bp-spec block mb-2 text-ink">
                {{ ui_copy('checkout_city_label', 'checkout.city_label') }} <span class="text-red-600 normal-case tracking-normal font-normal">*</span>
            </label>
            <div class="border border-ink bg-paper focus-within:border-amber-ink transition-colors @error('city') border-red-600 @enderror">
                <input type="text" id="checkout_city" name="city"
                       value="{{ old('city', $addr['city'] ?? '') }}"
                       required autocomplete="address-level2"
                       class="w-full px-4 py-3 bg-transparent font-mono text-sm text-ink placeholder:text-ink-muted/60 focus:outline-none">
            </div>
            @error('city')
                <p class="mt-2 font-mono text-[10px] tracking-[0.18em] uppercase text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="checkout_postal_code" class="bp-spec block mb-2 text-ink">
                {{ ui_copy('checkout_postal_code_label', 'checkout.postal_code_label') }} <span class="text-red-600 normal-case tracking-normal font-normal">*</span>
            </label>
            <div class="border border-ink bg-paper focus-within:border-amber-ink transition-colors @error('postal_code') border-red-600 @enderror">
                <input type="text" id="checkout_postal_code" name="postal_code"
                       value="{{ old('postal_code', $addr['postal_code'] ?? '') }}"
                       required inputmode="numeric" autocomplete="postal-code"
                       class="w-full px-4 py-3 bg-transparent font-mono text-sm text-ink tabular-nums placeholder:text-ink-muted/60 focus:outline-none">
            </div>
            @error('postal_code')
                <p class="mt-2 font-mono text-[10px] tracking-[0.18em] uppercase text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    {{-- Country --}}
    <div>
        <label for="checkout_country_code" class="bp-spec block mb-2 text-ink">
            {{ ui_copy('checkout_country_label', 'checkout.country_label') }} <span class="text-red-600 normal-case tracking-normal font-normal">*</span>
        </label>
        <div class="relative border border-ink bg-paper focus-within:border-amber-ink transition-colors @error('country_code') border-red-600 @enderror">
            <select id="checkout_country_code" name="country_code"
                    required autocomplete="country"
                    class="w-full px-4 py-3 pr-10 bg-transparent font-mono text-sm text-ink focus:outline-none appearance-none cursor-pointer">
                <option value="">{{ ui_copy('checkout_select_country_placeholder', 'checkout.select_country_placeholder') }}</option>
                @foreach($europeanCountries as $code => $name)
                    <option value="{{ $code }}" {{ old('country_code', $addr['country_code'] ?? '') === $code ? 'selected' : '' }}>
                        {{ $code }} · {{ $name }}
                    </option>
                @endforeach
            </select>
            <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                <x-heroicon-s-chevron-down class="w-4 h-4 text-ink-muted" />
            </div>
        </div>
        @error('country_code')
            <p class="mt-2 font-mono text-[10px] tracking-[0.18em] uppercase text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- EU shipping note --}}
    <div class="flex items-start gap-3 p-4 border border-rule-strong bg-ivory-alt">
        <div class="w-8 h-8 border border-ink bg-paper flex items-center justify-center shrink-0">
            <x-heroicon-o-globe-europe-africa class="w-4 h-4 text-ink" />
        </div>
        <div>
            <p class="bp-spec text-amber-ink mb-1">{{ ui_copy('checkout_pan_european_delivery', 'checkout.pan_european_delivery') }}</p>
            <p class="text-xs text-body">
                {{ ui_copy('checkout_pan_european_delivery_note', 'checkout.pan_european_delivery_note') }}
            </p>
        </div>
    </div>

</div>
@endsection
