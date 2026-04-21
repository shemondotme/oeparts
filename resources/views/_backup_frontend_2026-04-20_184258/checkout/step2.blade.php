@extends('frontend.checkout.layout')

@section('checkout_content')

{{-- Section header --}}
<div class="flex items-center gap-3 mb-8 pb-6 border-b border-gray-100">
    <div class="w-10 h-10 rounded-xl bg-navy/8 flex items-center justify-center shrink-0">
        <svg class="w-5 h-5 text-navy/50" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
    </div>
    <div>
        <h2 class="font-display text-2xl font-black text-navy leading-tight">Shipping Address</h2>
        <p class="text-sm text-muted font-medium mt-0.5">Where should we deliver your order?</p>
    </div>
</div>

{{-- Note: No <form> tag — renders inside layout's #checkout-form --}}

@php
    $addr = $checkoutData['shipping_address'] ?? [];
    $isB2b = (bool) ($checkoutData['is_b2b'] ?? false);
@endphp

<div class="space-y-5">

    {{-- First / Last name --}}
    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-bold text-navy/70 mb-2 uppercase tracking-wider">
                First Name <span class="text-red-400 normal-case tracking-normal font-normal">*</span>
            </label>
            <input type="text" name="first_name"
                   value="{{ old('first_name', $addr['first_name'] ?? '') }}"
                   required autocomplete="given-name"
                   class="w-full px-4 py-3 text-sm rounded-xl border border-gray-300 text-navy font-medium bg-white
                          placeholder:text-gray-400 focus:outline-none focus:border-amber focus:ring-2 focus:ring-amber/20
                          transition-colors @error('first_name') border-red-400 bg-red-50 @enderror">
            @error('first_name')
                <p class="mt-1.5 text-xs text-red-500 font-semibold">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="block text-xs font-bold text-navy/70 mb-2 uppercase tracking-wider">
                Last Name <span class="text-red-400 normal-case tracking-normal font-normal">*</span>
            </label>
            <input type="text" name="last_name"
                   value="{{ old('last_name', $addr['last_name'] ?? '') }}"
                   required autocomplete="family-name"
                   class="w-full px-4 py-3 text-sm rounded-xl border border-gray-300 text-navy font-medium bg-white
                          placeholder:text-gray-400 focus:outline-none focus:border-amber focus:ring-2 focus:ring-amber/20
                          transition-colors @error('last_name') border-red-400 bg-red-50 @enderror">
            @error('last_name')
                <p class="mt-1.5 text-xs text-red-500 font-semibold">{{ $message }}</p>
            @enderror
        </div>
    </div>

    {{-- B2B: Company + VAT --}}
    @if($isB2b)
    <div class="grid sm:grid-cols-2 gap-4 p-5 rounded-xl border border-blue-100 bg-blue-50/40">
        <div class="sm:col-span-2">
            <p class="text-xs font-bold text-blue-700 uppercase tracking-wider mb-4 flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 110 2h-3a1 1 0 01-1-1v-2a1 1 0 00-1-1H9a1 1 0 00-1 1v2a1 1 0 01-1 1H4a1 1 0 110-2V4z" clip-rule="evenodd"/>
                </svg>
                Business (B2B) Details
            </p>
        </div>
        <div>
            <label class="block text-xs font-bold text-navy/70 mb-2 uppercase tracking-wider">
                Company Name <span class="text-red-400 normal-case tracking-normal font-normal">*</span>
            </label>
            <input type="text" name="company"
                   value="{{ old('company', $checkoutData['company_name'] ?? '') }}"
                   required autocomplete="organization"
                   class="w-full px-4 py-3 text-sm rounded-xl border border-gray-300 text-navy font-medium bg-white
                          placeholder:text-gray-400 focus:outline-none focus:border-amber focus:ring-2 focus:ring-amber/20
                          transition-colors @error('company') border-red-400 bg-red-50 @enderror">
            @error('company')
                <p class="mt-1.5 text-xs text-red-500 font-semibold">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="block text-xs font-bold text-navy/70 mb-2 uppercase tracking-wider">
                VAT Number <span class="text-red-400 normal-case tracking-normal font-normal">*</span>
            </label>
            <input type="text" name="vat_number"
                   value="{{ old('vat_number', $checkoutData['vat_number'] ?? '') }}"
                   placeholder="DE123456789"
                   required autocomplete="off"
                   class="w-full px-4 py-3 text-sm rounded-xl border border-gray-300 text-navy font-mono bg-white
                          placeholder:text-gray-400 placeholder:font-sans focus:outline-none focus:border-amber focus:ring-2 focus:ring-amber/20
                          transition-colors @error('vat_number') border-red-400 bg-red-50 @enderror">
            <p class="mt-1.5 text-[11px] text-muted">Format: country code + number (e.g. DE123456789)</p>
            @error('vat_number')
                <p class="mt-1 text-xs text-red-500 font-semibold">{{ $message }}</p>
            @enderror
        </div>
    </div>
    @endif

    {{-- Street --}}
    <div>
        <label class="block text-xs font-bold text-navy/70 mb-2 uppercase tracking-wider">
            Street Address <span class="text-red-400 normal-case tracking-normal font-normal">*</span>
        </label>
        <input type="text" name="street"
               value="{{ old('street', $addr['street'] ?? '') }}"
               placeholder="e.g. Musterstraße 12"
               required autocomplete="street-address"
               class="w-full px-4 py-3 text-sm rounded-xl border border-gray-300 text-navy font-medium bg-white
                      placeholder:text-gray-400 placeholder:font-normal focus:outline-none focus:border-amber focus:ring-2 focus:ring-amber/20
                      transition-colors @error('street') border-red-400 bg-red-50 @enderror">
        @error('street')
            <p class="mt-1.5 text-xs text-red-500 font-semibold">{{ $message }}</p>
        @enderror
    </div>

    {{-- City + Postal --}}
    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-bold text-navy/70 mb-2 uppercase tracking-wider">
                City <span class="text-red-400 normal-case tracking-normal font-normal">*</span>
            </label>
            <input type="text" name="city"
                   value="{{ old('city', $addr['city'] ?? '') }}"
                   required autocomplete="address-level2"
                   class="w-full px-4 py-3 text-sm rounded-xl border border-gray-300 text-navy font-medium bg-white
                          placeholder:text-gray-400 focus:outline-none focus:border-amber focus:ring-2 focus:ring-amber/20
                          transition-colors @error('city') border-red-400 bg-red-50 @enderror">
            @error('city')
                <p class="mt-1.5 text-xs text-red-500 font-semibold">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="block text-xs font-bold text-navy/70 mb-2 uppercase tracking-wider">
                ZIP / Postal Code <span class="text-red-400 normal-case tracking-normal font-normal">*</span>
            </label>
            <input type="text" name="postal_code"
                   value="{{ old('postal_code', $addr['postal_code'] ?? '') }}"
                   required inputmode="numeric" autocomplete="postal-code"
                   class="w-full px-4 py-3 text-sm rounded-xl border border-gray-300 text-navy font-mono bg-white
                          placeholder:text-gray-400 placeholder:font-sans focus:outline-none focus:border-amber focus:ring-2 focus:ring-amber/20
                          transition-colors @error('postal_code') border-red-400 bg-red-50 @enderror">
            @error('postal_code')
                <p class="mt-1.5 text-xs text-red-500 font-semibold">{{ $message }}</p>
            @enderror
        </div>
    </div>

    {{-- Country --}}
    <div>
        <label class="block text-xs font-bold text-navy/70 mb-2 uppercase tracking-wider">
            Country <span class="text-red-400 normal-case tracking-normal font-normal">*</span>
        </label>
        <div class="relative">
            <select name="country_code"
                    required autocomplete="country"
                    class="w-full px-4 py-3 text-sm rounded-xl border border-gray-300 text-navy font-medium bg-white
                           focus:outline-none focus:border-amber focus:ring-2 focus:ring-amber/20
                           transition-colors appearance-none @error('country_code') border-red-400 bg-red-50 @enderror">
                <option value="">Select country…</option>
                @foreach(['DE' => '🇩🇪 Germany', 'AT' => '🇦🇹 Austria', 'FR' => '🇫🇷 France', 'LT' => '🇱🇹 Lithuania', 'ES' => '🇪🇸 Spain'] as $code => $name)
                    <option value="{{ $code }}" {{ old('country_code', $addr['country_code'] ?? '') === $code ? 'selected' : '' }}>
                        {{ $name }}
                    </option>
                @endforeach
            </select>
            <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
        </div>
        @error('country_code')
            <p class="mt-1.5 text-xs text-red-500 font-semibold">{{ $message }}</p>
        @enderror
    </div>

    {{-- EU shipping note --}}
    <div class="flex items-start gap-3 p-3.5 rounded-xl bg-blue-50/70 border border-blue-100">
        <svg class="w-4 h-4 text-blue-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-xs text-blue-600 font-semibold leading-relaxed">
            We ship to all EU member states. Shipping cost is calculated in the next step.
        </p>
    </div>

</div>
@endsection
