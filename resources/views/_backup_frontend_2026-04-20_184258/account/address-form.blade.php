@extends('layouts.app')

@section('title', $address ? __('Edit Address') . ' - ' . settings('general.site_name', 'OEMHub') : __('Add Address') . ' - ' . settings('general.site_name', 'OEMHub'))

@section('content')
<div class="min-h-screen bg-gray-50">
    {{-- Header --}}
    <div class="bg-gradient-to-r from-navy to-blue-900 text-white py-8 px-4">
        <div class="max-w-3xl mx-auto">
            <h1 class="font-display text-3xl md:text-4xl font-bold">
                {{ $address ? __('Edit Address') : __('Add New Address') }}
            </h1>
            <p class="text-white/70 mt-2">
                {{ $address ? __('Update your saved address') : __('Save a new address for faster checkout') }}
            </p>
        </div>
    </div>

    {{-- Main Content --}}
    <div class="max-w-3xl mx-auto px-4 py-8">

        {{-- Error Messages --}}
        @if ($errors->any())
            <div class="mb-6 p-5 rounded-xl bg-red-50 border-2 border-red-200 text-red-700 flex items-start gap-3">
                <x-heroicon-s-exclamation-circle class="w-5 h-5 mt-0.5 shrink-0" />
                <div>
                    <p class="font-bold">{{ __('Validation Error') }}</p>
                    <ul class="text-sm mt-2 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        {{-- Form Card --}}
        <div class="bg-white rounded-xl border border-gray-100 p-8">

        <form method="POST" action="{{ route('frontend.account.save-address', ['lang' => app()->getLocale()]) }}" class="space-y-6">
            @csrf
            @if($address)
                <input type="hidden" name="id" value="{{ $address->id }}">
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- First Name --}}
                <div>
                    <label for="first_name" class="flex items-center gap-2 text-sm font-bold text-navy mb-3">
                        {{ __('First Name') }} *
                    </label>
                    <input
                        type="text"
                        id="first_name"
                        name="first_name"
                        value="{{ old('first_name', $address->first_name ?? '') }}"
                        required
                        class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:outline-none focus:border-amber focus:ring-4 focus:ring-amber/20 transition-all"
                        placeholder="John"
                    />
                </div>

                {{-- Last Name --}}
                <div>
                    <label for="last_name" class="block text-sm font-medium text-slate-700 mb-1">
                        {{ __('Last Name') }} *
                    </label>
                    <input
                        type="text"
                        id="last_name"
                        name="last_name"
                        value="{{ old('last_name', $address->last_name ?? '') }}"
                        required
                        class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:outline-none focus:border-amber focus:ring-4 focus:ring-amber/20 transition-all"
                        placeholder="Smith"
                    />
                </div>

                {{-- Company --}}
                <div class="md:col-span-2">
                    <label for="company" class="block text-sm font-medium text-slate-700 mb-1">
                        {{ __('Company') }}
                    </label>
                    <input
                        type="text"
                        id="company"
                        name="company"
                        value="{{ old('company', $address->company ?? '') }}"
                        class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:outline-none focus:border-amber focus:ring-4 focus:ring-amber/20 transition-all"
                        placeholder="ACME Inc."
                    />
                </div>

                {{-- Address Line 1 --}}
                <div class="md:col-span-2">
                    <label for="address_line_1" class="block text-sm font-medium text-slate-700 mb-1">
                        {{ __('Address Line 1') }} *
                    </label>
                    <input
                        type="text"
                        id="address_line_1"
                        name="address_line_1"
                        value="{{ old('address_line_1', $address->address_line_1 ?? '') }}"
                        required
                        class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:outline-none focus:border-amber focus:ring-4 focus:ring-amber/20 transition-all"
                        placeholder="123 Main Street"
                    />
                </div>

                {{-- Address Line 2 --}}
                <div class="md:col-span-2">
                    <label for="address_line_2" class="block text-sm font-medium text-slate-700 mb-1">
                        {{ __('Address Line 2') }}
                    </label>
                    <input
                        type="text"
                        id="address_line_2"
                        name="address_line_2"
                        value="{{ old('address_line_2', $address->address_line_2 ?? '') }}"
                        class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:outline-none focus:border-amber focus:ring-4 focus:ring-amber/20 transition-all"
                        placeholder="Apartment, suite, etc."
                    />
                </div>

                {{-- City --}}
                <div>
                    <label for="city" class="block text-sm font-medium text-slate-700 mb-1">
                        {{ __('City') }} *
                    </label>
                    <input
                        type="text"
                        id="city"
                        name="city"
                        value="{{ old('city', $address->city ?? '') }}"
                        required
                        class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:outline-none focus:border-amber focus:ring-4 focus:ring-amber/20 transition-all"
                        placeholder="Berlin"
                    />
                </div>

                {{-- State / Province --}}
                <div>
                    <label for="state" class="block text-sm font-medium text-slate-700 mb-1">
                        {{ __('State / Province') }} *
                    </label>
                    <input
                        type="text"
                        id="state"
                        name="state"
                        value="{{ old('state', $address->state ?? '') }}"
                        required
                        class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:outline-none focus:border-amber focus:ring-4 focus:ring-amber/20 transition-all"
                        placeholder="Berlin"
                    />
                </div>

                {{-- Postal Code --}}
                <div>
                    <label for="postal_code" class="block text-sm font-medium text-slate-700 mb-1">
                        {{ __('Postal Code') }} *
                    </label>
                    <input
                        type="text"
                        id="postal_code"
                        name="postal_code"
                        value="{{ old('postal_code', $address->postal_code ?? '') }}"
                        required
                        class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:outline-none focus:border-amber focus:ring-4 focus:ring-amber/20 transition-all"
                        placeholder="10115"
                    />
                </div>

                {{-- Country --}}
                <div>
                    <label for="country_code" class="block text-sm font-medium text-slate-700 mb-1">
                        {{ __('Country') }} *
                    </label>
                    <select
                        id="country_code"
                        name="country_code"
                        required
                        class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:outline-none focus:border-amber focus:ring-4 focus:ring-amber/20 transition-all"
                    >
                        <option value="">{{ __('Select a country') }}</option>
                        @foreach(\App\Services\ViesService::getEuCountries() as $code => $name)
                            <option value="{{ $code }}" {{ old('country_code', $address->country_code ?? '') == $code ? 'selected' : '' }}>
                                {{ $name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Phone --}}
                <div class="md:col-span-2">
                    <label for="phone" class="block text-sm font-medium text-slate-700 mb-1">
                        {{ __('Phone Number') }}
                    </label>
                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        value="{{ old('phone', $address->phone ?? '') }}"
                        class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:outline-none focus:border-amber focus:ring-4 focus:ring-amber/20 transition-all"
                        placeholder="+49 30 12345678"
                    />
                </div>

                {{-- Default Address Checkbox --}}
                <div class="p-4 rounded-xl bg-gray-50 border border-gray-200">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox"
                               name="is_default"
                               value="1"
                               {{ old('is_default', $address?->is_default ? 'checked' : '') }}
                               class="w-4 h-4 rounded border-gray-300 text-amber cursor-pointer">
                        <span class="text-sm font-semibold text-navy">{{ __('Set as default address') }}</span>
                    </label>
                </div>

            {{-- Submit Buttons --}}
            <div class="flex flex-col sm:flex-row gap-4 pt-6 border-t border-gray-100">
                <a href="/{{ app()->getLocale() }}/account/addresses"
                   class="inline-flex items-center justify-center gap-2 px-6 py-3
                          bg-white border-2 border-gray-200 text-navy font-bold rounded-xl
                          hover:border-amber hover:text-amber transition-all duration-200">
                    <x-heroicon-o-arrow-left class="w-4 h-4" />
                    {{ __('Cancel') }}
                </a>

                <button type="submit"
                        class="flex-1 inline-flex items-center justify-center gap-2 px-6 py-3
                               bg-gradient-to-r from-amber to-orange-500 text-navy font-bold rounded-xl
                               shadow-lg shadow-amber/30 hover:shadow-amber/50 hover:from-amber/90 hover:to-orange-400
                               transition-all duration-200">
                    <x-heroicon-o-check-circle class="w-4 h-4" />
                    {{ $address ? __('Update Address') : __('Save Address') }}
                </button>
            </div>
            </form>

        </div>
    </div>
</div>
@endsection