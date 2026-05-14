@extends('layouts.app')

@section('title', ($address ? __('Edit Address') : __('Add Address')) . ' — ' . settings('general.site_name', 'OEMHub'))

@section('meta_robots')<meta name="robots" content="noindex, nofollow">@endsection

@php
    $lang = app()->getLocale();
    $isEdit = (bool) $address;
@endphp

@section('content')
<x-account.shell
    active="addresses"
    :eyebrow="$isEdit ? '§ Edit · Address · Record' : '§ New · Address · Record'"
    :title="$isEdit ? __('Edit address') : __('Add new address')"
    :subtitle="$isEdit ? __('Update this saved ship-to record.') : __('Save a new ship-to record for faster checkout on future orders.')"
    :docId="$isEdit ? 'DOC · ADDRESS-EDIT · ' . $address->id : 'DOC · ADDRESS-NEW'"
    :breadcrumb="[
        ['label' => 'Addresses', 'href' => route('frontend.account.addresses', ['lang' => $lang])],
        ['label' => $isEdit ? 'Edit' : 'New'],
    ]"
>
    {{-- Validation errors --}}
    @if($errors->any())
        <div class="mb-6 border border-red-600 bg-red-50 p-5"
             style="box-shadow: 4px 4px 0 rgba(20,22,29,1);">
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 border border-red-600 bg-paper flex items-center justify-center shrink-0">
                    <x-heroicon-s-exclamation-triangle class="w-4 h-4 text-red-600" />
                </div>
                <div class="flex-1">
                    <p class="bp-spec text-red-700 mb-1">§ Validation · Error</p>
                    <ul class="text-sm text-red-800 space-y-0.5 list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <section class="border border-ink bg-paper" style="box-shadow: 6px 6px 0 rgba(20,22,29,1);">
        <header class="flex items-center justify-between px-5 py-3 border-b border-ink bg-ivory-alt">
            <span class="bp-spec text-amber-ink flex items-center gap-2">
                <x-heroicon-o-map-pin class="w-3.5 h-3.5" />
                § Address · Details
            </span>
            <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">
                * = required
            </span>
        </header>

        <form method="POST"
              action="{{ route('frontend.account.addresses.store', ['lang' => $lang]) }}"
              class="p-6 sm:p-8 space-y-6">
            @csrf
            @if($isEdit)
                <input type="hidden" name="id" value="{{ $address->id }}">
            @endif

            {{-- Recipient --}}
            <fieldset class="space-y-5">
                <legend class="bp-spec text-amber-ink mb-3">§ 01 · Recipient</legend>

                <div class="grid sm:grid-cols-2 gap-5">
                    <div>
                        <label for="first_name" class="bp-spec block mb-2 text-ink">
                            First name <span class="text-red-600">*</span>
                        </label>
                        <input type="text" id="first_name" name="first_name" required
                               value="{{ old('first_name', $address->first_name ?? '') }}"
                               placeholder="John"
                               class="bp-input w-full">
                    </div>
                    <div>
                        <label for="last_name" class="bp-spec block mb-2 text-ink">
                            Last name <span class="text-red-600">*</span>
                        </label>
                        <input type="text" id="last_name" name="last_name" required
                               value="{{ old('last_name', $address->last_name ?? '') }}"
                               placeholder="Smith"
                               class="bp-input w-full">
                    </div>
                </div>

                <div>
                    <label for="company" class="bp-spec block mb-2 text-ink">
                        Company
                        <span class="text-ink-muted/80 normal-case tracking-normal font-normal ml-1">(optional)</span>
                    </label>
                    <input type="text" id="company" name="company"
                           value="{{ old('company', $address->company ?? '') }}"
                           placeholder="ACME GmbH"
                           class="bp-input w-full">
                </div>

                <div>
                    <label for="phone" class="bp-spec block mb-2 text-ink">
                        Phone
                        <span class="text-ink-muted/80 normal-case tracking-normal font-normal ml-1">(optional)</span>
                    </label>
                    <input type="tel" id="phone" name="phone"
                           value="{{ old('phone', $address->phone ?? '') }}"
                           placeholder="+49 30 12345678"
                           class="bp-input w-full">
                </div>
            </fieldset>

            <div class="border-t border-dotted border-rule-strong"></div>

            {{-- Location --}}
            <fieldset class="space-y-5">
                <legend class="bp-spec text-amber-ink mb-3">§ 02 · Location</legend>

                <div>
                    <label for="address_line_1" class="bp-spec block mb-2 text-ink">
                        Address line 1 <span class="text-red-600">*</span>
                    </label>
                    <input type="text" id="address_line_1" name="address_line_1" required
                           value="{{ old('address_line_1', $address->address_line1 ?? $address->address_line_1 ?? '') }}"
                           placeholder="123 Main Street"
                           class="bp-input w-full">
                </div>

                <div>
                    <label for="address_line_2" class="bp-spec block mb-2 text-ink">
                        Address line 2
                        <span class="text-ink-muted/80 normal-case tracking-normal font-normal ml-1">(optional)</span>
                    </label>
                    <input type="text" id="address_line_2" name="address_line_2"
                           value="{{ old('address_line_2', $address->address_line2 ?? $address->address_line_2 ?? '') }}"
                           placeholder="Apt, suite, unit, etc."
                           class="bp-input w-full">
                </div>

                <div class="grid sm:grid-cols-2 gap-5">
                    <div>
                        <label for="city" class="bp-spec block mb-2 text-ink">
                            City <span class="text-red-600">*</span>
                        </label>
                        <input type="text" id="city" name="city" required
                               value="{{ old('city', $address->city ?? '') }}"
                               placeholder="Berlin"
                               class="bp-input w-full">
                    </div>
                    <div>
                        <label for="state" class="bp-spec block mb-2 text-ink">
                            State / province <span class="text-red-600">*</span>
                        </label>
                        <input type="text" id="state" name="state" required
                               value="{{ old('state', $address->state ?? '') }}"
                               placeholder="Berlin"
                               class="bp-input w-full">
                    </div>
                </div>

                <div class="grid sm:grid-cols-2 gap-5">
                    <div>
                        <label for="postal_code" class="bp-spec block mb-2 text-ink">
                            Postal code <span class="text-red-600">*</span>
                        </label>
                        <input type="text" id="postal_code" name="postal_code" required
                               value="{{ old('postal_code', $address->postal_code ?? '') }}"
                               placeholder="10115"
                               class="bp-input w-full font-mono tabular-nums">
                    </div>
                    <div>
                        <label for="country_code" class="bp-spec block mb-2 text-ink">
                            Country <span class="text-red-600">*</span>
                        </label>
                        <select id="country_code" name="country_code" required class="bp-input w-full">
                            <option value="">{{ __('Select a country') }}</option>
                            @foreach(\App\Services\ViesService::getEuCountries() as $code => $name)
                                <option value="{{ $code }}"
                                    {{ old('country_code', $address->country_code ?? '') === $code ? 'selected' : '' }}>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </fieldset>

            <div class="border-t border-dotted border-rule-strong"></div>

            {{-- Flags --}}
            <fieldset>
                <legend class="bp-spec text-amber-ink mb-3">§ 03 · Flags</legend>

                <label class="flex items-start gap-3 p-4 border border-rule-strong bg-ivory-alt cursor-pointer
                              hover:border-ink transition-colors">
                    <input type="checkbox" name="is_default" value="1"
                           {{ old('is_default', $address?->is_default) ? 'checked' : '' }}
                           class="w-4 h-4 mt-0.5 border-ink text-amber focus:ring-amber focus:ring-offset-0 shrink-0">
                    <div class="flex-1">
                        <p class="font-display text-sm font-bold text-ink tracking-[-0.01em]">
                            {{ __('Set as default address') }}
                        </p>
                        <p class="mt-1 font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted">
                            Pre-selected on future checkouts
                        </p>
                    </div>
                </label>
            </fieldset>

            {{-- Actions --}}
            <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3 pt-6 border-t border-ink">
                <a href="{{ route('frontend.account.addresses', ['lang' => $lang]) }}"
                   class="bp-btn-outline justify-center sm:justify-start">
                    <x-heroicon-s-arrow-long-left class="w-4 h-4" />
                    {{ __('Cancel') }}
                </a>
                <button type="submit" class="bp-btn-primary justify-center flex-1 sm:flex-none">
                    <x-heroicon-s-check class="w-5 h-5" />
                    <span>{{ $isEdit ? __('Update address') : __('Save address') }}</span>
                    <x-heroicon-s-arrow-long-right class="w-5 h-5" />
                </button>
            </div>
        </form>
    </section>
</x-account.shell>
@endsection
