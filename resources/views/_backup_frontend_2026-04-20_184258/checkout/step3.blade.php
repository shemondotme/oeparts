@extends('frontend.checkout.layout')

@section('checkout_content')

{{-- Section header --}}
<div class="flex items-center gap-3 mb-8 pb-6 border-b border-gray-100">
    <div class="w-10 h-10 rounded-xl bg-navy/8 flex items-center justify-center shrink-0">
        <svg class="w-5 h-5 text-navy/50" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
        </svg>
    </div>
    <div>
        <h2 class="font-display text-2xl font-black text-navy leading-tight">Shipping Method</h2>
        <p class="text-sm text-muted font-medium mt-0.5">Choose your preferred carrier and delivery speed.</p>
    </div>
</div>

@php
    $selected = (string) old('shipping_method_id', $selectedId ?? '');
@endphp

<div class="space-y-3" x-data="{ selected: '{{ $selected }}' }">
    @forelse($methods as $method)
        @php
            $methodId = (string) $method->id;
            $isSelected = $selected === $methodId;
            $isFree = (float) $method->flat_rate <= 0;
            $days = $method->estimated_days_min && $method->estimated_days_max
                ? $method->estimated_days_min . '-' . $method->estimated_days_max . ' business days'
                : 'Fast EU delivery';
        @endphp
        <label class="flex items-center gap-4 p-4 sm:p-5 rounded-xl border-2 cursor-pointer transition-all duration-150 {{ $isSelected ? 'border-amber bg-amber/4 shadow-md' : 'border-gray-200 bg-white hover:border-gray-300' }}"
               :class="selected === '{{ $methodId }}' ? 'border-amber bg-amber/4 shadow-md' : 'border-gray-200 bg-white hover:border-gray-300'">
            <input type="radio"
                   name="shipping_method_id"
                   value="{{ $method->id }}"
                   x-model="selected"
                   {{ $isSelected ? 'checked' : '' }}
                   required
                   class="w-4 h-4 text-amber border-gray-300 focus:ring-amber focus:ring-offset-0 shrink-0">

            <div class="w-10 h-10 rounded-xl border border-gray-100 bg-gray-50 flex items-center justify-center shrink-0 relative">
                <svg class="w-5 h-5 text-navy/40" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                </svg>
                <span class="absolute -top-1 -right-1 w-3 h-3 rounded-full {{ $isFree ? 'bg-emerald-500' : 'bg-amber-500' }} ring-2 ring-white"></span>
            </div>

            <div class="flex-1 min-w-0">
                <p class="font-bold text-navy text-sm">{{ trans_field($method->name) }}</p>
                <p class="text-xs text-muted mt-0.5 flex items-center gap-1">
                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ $days }}
                </p>
            </div>

            <div class="shrink-0 text-right">
                @if($isFree)
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 text-xs font-black border border-emerald-200">
                        FREE
                    </span>
                @else
                    <span class="font-black text-navy text-base">€{{ number_format((float) $method->flat_rate, 2) }}</span>
                @endif
            </div>
        </label>
    @empty
        <div class="rounded-xl border border-amber/20 bg-amber/5 p-4 text-sm font-semibold text-amber-700">
            No shipping methods are currently available for your selected country.
        </div>
    @endforelse

    @error('shipping_method_id')
        <p class="text-xs text-red-500 font-semibold mt-1 flex items-center gap-1">
            <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            {{ $message }}
        </p>
    @enderror
</div>

{{-- Note --}}
<div class="mt-6 flex items-start gap-2.5 p-3.5 rounded-xl bg-amber/5 border border-amber/20">
    <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
    </svg>
    <p class="text-xs text-amber-700 font-semibold leading-relaxed">
        Free standard delivery is available on all orders. Faster options are calculated on the order subtotal.
    </p>
</div>

@endsection
