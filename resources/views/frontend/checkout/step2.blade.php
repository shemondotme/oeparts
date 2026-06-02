@extends('frontend.checkout.layout')

@section('checkout_content')
@php
    $addr = $checkoutData['shipping_address'] ?? [];

    $europeanCountries = [
        'AT' => 'Austria',
        'BE' => 'Belgium',
        'BG' => 'Bulgaria',
        'HR' => 'Croatia',
        'CY' => 'Cyprus',
        'CZ' => 'Czech Republic',
        'DK' => 'Denmark',
        'EE' => 'Estonia',
        'FI' => 'Finland',
        'FR' => 'France',
        'DE' => 'Germany',
        'GR' => 'Greece',
        'HU' => 'Hungary',
        'IE' => 'Ireland',
        'IT' => 'Italy',
        'LV' => 'Latvia',
        'LT' => 'Lithuania',
        'LU' => 'Luxembourg',
        'MT' => 'Malta',
        'NL' => 'Netherlands',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'RO' => 'Romania',
        'SK' => 'Slovakia',
        'SI' => 'Slovenia',
        'ES' => 'Spain',
        'SE' => 'Sweden',
        'GB' => 'United Kingdom',
        'CH' => 'Switzerland',
        'NO' => 'Norway',
        'IS' => 'Iceland',
        'LI' => 'Liechtenstein',
        'MC' => 'Monaco',
        'AD' => 'Andorra',
        'SM' => 'San Marino',
        'VA' => 'Vatican City',
        'AL' => 'Albania',
        'BA' => 'Bosnia and Herzegovina',
        'ME' => 'Montenegro',
        'MK' => 'North Macedonia',
        'RS' => 'Serbia',
        'XK' => 'Kosovo',
        'MD' => 'Moldova',
        'UA' => 'Ukraine',
    ];
@endphp

<div class="space-y-6">

    {{-- Sub-header --}}
    <header class="pb-4 border-b border-rule">
        <h2 class="font-display text-2xl md:text-3xl font-extrabold text-ink leading-tight tracking-[-0.02em]">
            Shipping address<span class="text-amber">.</span>
        </h2>
        <p class="mt-2 font-mono text-[11px] tracking-[0.18em] uppercase text-ink-muted">
            Delivery · EU-Wide · Tracked
        </p>
    </header>

    {{-- First / Last name --}}
    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="bp-spec block mb-2 text-ink">
                § First name <span class="text-red-600 normal-case tracking-normal font-normal">*</span>
            </label>
            <div class="border border-ink bg-paper focus-within:border-amber transition-colors @error('first_name') border-red-600 @enderror">
                <input type="text" name="first_name"
                       value="{{ old('first_name', $addr['first_name'] ?? '') }}"
                       required autocomplete="given-name"
                       class="w-full px-4 py-3 bg-transparent font-mono text-sm text-ink placeholder:text-ink-muted/60 focus:outline-none">
            </div>
            @error('first_name')
                <p class="mt-2 font-mono text-[10px] tracking-[0.18em] uppercase text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="bp-spec block mb-2 text-ink">
                § Last name <span class="text-red-600 normal-case tracking-normal font-normal">*</span>
            </label>
            <div class="border border-ink bg-paper focus-within:border-amber transition-colors @error('last_name') border-red-600 @enderror">
                <input type="text" name="last_name"
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
        <label class="bp-spec block mb-2 text-ink">
            § Street address <span class="text-red-600 normal-case tracking-normal font-normal">*</span>
        </label>
        <div class="border border-ink bg-paper focus-within:border-amber transition-colors @error('street') border-red-600 @enderror">
            <input type="text" name="street"
                   value="{{ old('street', $addr['street'] ?? '') }}"
                   placeholder="e.g. Musterstraße 12"
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
            <label class="bp-spec block mb-2 text-ink">
                § City <span class="text-red-600 normal-case tracking-normal font-normal">*</span>
            </label>
            <div class="border border-ink bg-paper focus-within:border-amber transition-colors @error('city') border-red-600 @enderror">
                <input type="text" name="city"
                       value="{{ old('city', $addr['city'] ?? '') }}"
                       required autocomplete="address-level2"
                       class="w-full px-4 py-3 bg-transparent font-mono text-sm text-ink placeholder:text-ink-muted/60 focus:outline-none">
            </div>
            @error('city')
                <p class="mt-2 font-mono text-[10px] tracking-[0.18em] uppercase text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="bp-spec block mb-2 text-ink">
                § Postal code <span class="text-red-600 normal-case tracking-normal font-normal">*</span>
            </label>
            <div class="border border-ink bg-paper focus-within:border-amber transition-colors @error('postal_code') border-red-600 @enderror">
                <input type="text" name="postal_code"
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
        <label class="bp-spec block mb-2 text-ink">
            § Country <span class="text-red-600 normal-case tracking-normal font-normal">*</span>
        </label>
        <div class="relative border border-ink bg-paper focus-within:border-amber transition-colors @error('country_code') border-red-600 @enderror">
            <select name="country_code"
                    required autocomplete="country"
                    class="w-full px-4 py-3 pr-10 bg-transparent font-mono text-sm text-ink focus:outline-none appearance-none cursor-pointer">
                <option value="">— Select country —</option>
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
            <p class="bp-spec text-amber-ink mb-1">§ Pan-European delivery</p>
            <p class="text-xs text-body">
                Express, Standard and Economy shipping available across all listed European countries.
            </p>
        </div>
    </div>

</div>
@endsection
