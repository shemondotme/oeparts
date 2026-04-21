@extends('frontend.checkout.layout')

@section('checkout_content')
@php
    $cart = $checkoutCart;
    $summary = $checkoutSummary;
    $addr = $checkoutData['shipping_address'] ?? [];
    $shippingMethod = $selectedShippingMethod;
    $shippingCost = $selectedShippingCost ?? '0.00';
    $subtotal = $sidebarSummary['subtotal'] ?? '0.00';
    $vatRate = $sidebarSummary['vat_rate'] ?? ($summary['vat_rate'] ?? settings('tax.default_vat_rate', 21));
    $vatAmount = $sidebarSummary['vat_amount'] ?? '0.00';
    $total = $sidebarSummary['grand_total'] ?? '0.00';
@endphp

{{-- Section header --}}
<div class="flex items-center gap-3 mb-8 pb-6 border-b border-gray-100">
    <div class="w-10 h-10 rounded-xl bg-navy/8 flex items-center justify-center shrink-0">
        <svg class="w-5 h-5 text-navy/50" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
    </div>
    <div>
        <h2 class="font-display text-2xl font-black text-navy leading-tight">Review Your Order</h2>
        <p class="text-sm text-muted font-medium mt-0.5">Please confirm all details are correct before paying.</p>
    </div>
</div>

<div class="space-y-4">

    {{-- Order items --}}
    <div class="rounded-xl border border-gray-200 overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 bg-gray-50 border-b border-gray-200">
            <span class="text-xs font-black text-navy uppercase tracking-wider flex items-center gap-2">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"/>
                </svg>
                Order Items
            </span>
            @if($cart)
                <span class="text-xs font-bold text-muted">{{ $cart->items->count() }} {{ Str::plural('item', $cart->items->count()) }}</span>
            @endif
        </div>
        @if($cart && $cart->items->isNotEmpty())
            <div class="divide-y divide-gray-100">
                @foreach($cart->items as $item)
                <div class="flex items-center gap-3 px-4 py-3.5">
                    <div class="w-9 h-9 rounded-lg bg-navy flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-white/40" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-mono font-black text-navy text-sm">{{ $item->product->oem_number }}</p>
                        <p class="text-xs text-muted mt-0.5">{{ $item->quantity }} × €{{ number_format($item->price_at_add, 2) }}</p>
                    </div>
                    <span class="font-black text-navy text-sm shrink-0">€{{ number_format((float) bcmul((string) $item->price_at_add, (string) $item->quantity, 2), 2) }}</span>
                </div>
                @endforeach
            </div>
        @else
            <p class="text-muted text-sm p-4">No items in cart.</p>
        @endif
    </div>

    {{-- Shipping address --}}
    <div class="rounded-xl border border-gray-200 overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 bg-gray-50 border-b border-gray-200">
            <span class="text-xs font-black text-navy uppercase tracking-wider flex items-center gap-2">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Delivering To
            </span>
        </div>
        <div class="px-4 py-4">
            @if(!empty($addr))
            <address class="not-italic text-sm text-navy leading-relaxed">
                <strong class="font-black">{{ trim(($addr['first_name'] ?? '') . ' ' . ($addr['last_name'] ?? '')) }}</strong><br>
                @if(!empty($checkoutData['company_name'])) <span class="text-muted">{{ $checkoutData['company_name'] }}</span><br> @endif
                {{ $addr['street'] ?? '' }}<br>
                <span class="font-mono font-semibold">{{ $addr['postal_code'] ?? '' }}</span> {{ $addr['city'] ?? '' }}<br>
                <span class="font-bold text-xs uppercase tracking-wider text-muted">{{ $addr['country_code'] ?? '' }}</span>
            </address>
            @else
            <p class="text-muted text-sm">No shipping address provided.</p>
            @endif
        </div>
    </div>

    {{-- Shipping method --}}
    <div class="rounded-xl border border-gray-200 overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 bg-gray-50 border-b border-gray-200">
            <span class="text-xs font-black text-navy uppercase tracking-wider flex items-center gap-2">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                </svg>
                Shipping Method
            </span>
        </div>
        <div class="flex items-center justify-between px-4 py-4">
            <p class="text-sm font-bold text-navy">{{ $shippingMethod ? trans_field($shippingMethod->name) : 'Standard Delivery' }}</p>
            <p class="text-sm font-black {{ $shippingCost === '0.00' ? 'text-emerald-600' : 'text-navy' }}">
                {{ $shippingCost === '0.00' ? 'FREE' : '€' . number_format((float) $shippingCost, 2) }}
            </p>
        </div>
    </div>

    {{-- Pricing breakdown --}}
    <div class="rounded-xl border border-gray-200 overflow-hidden">
        <div class="flex items-center px-4 py-3 bg-gray-50 border-b border-gray-200">
            <span class="text-xs font-black text-navy uppercase tracking-wider flex items-center gap-2">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                Price Breakdown
            </span>
        </div>
        <div class="px-4 py-4 space-y-3">
            <div class="flex justify-between text-sm">
                <span class="text-muted font-medium">Subtotal (excl. VAT)</span>
                <span class="font-bold text-navy">€{{ number_format((float) $subtotal, 2) }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-muted font-medium">Shipping</span>
                <span class="font-bold {{ $shippingCost === '0.00' ? 'text-emerald-600' : 'text-navy' }}">
                    {{ $shippingCost === '0.00' ? 'FREE' : '€' . number_format((float) $shippingCost, 2) }}
                </span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-muted font-medium">VAT ({{ $vatRate }}%)</span>
                <span class="font-bold text-navy">€{{ number_format((float) $vatAmount, 2) }}</span>
            </div>
            <div class="flex items-center justify-between pt-3 mt-3 border-t-2 border-navy/10">
                <div>
                    <p class="font-black text-navy text-base">Grand Total</p>
                    <p class="text-[10px] text-muted font-bold uppercase tracking-wider mt-0.5">Including all taxes</p>
                </div>
                <p class="font-display text-3xl font-black text-navy">€{{ number_format((float) $total, 2) }}</p>
            </div>
        </div>
    </div>

    {{-- Agreement --}}
    <div class="rounded-xl border border-gray-200 bg-gray-50/40 p-5">
        <label class="flex items-start gap-3 cursor-pointer group">
            <input type="checkbox"
                   name="agree_terms"
                   value="1"
                   required
                   class="mt-0.5 w-4 h-4 rounded border-gray-300 text-amber focus:ring-amber focus:ring-offset-0 shrink-0">
            <span class="text-sm text-navy font-medium leading-relaxed">
                I agree to the
                <a href="{{ route('frontend.page', ['lang' => app()->getLocale(), 'slug' => 'terms']) }}" target="_blank"
                   class="font-bold text-amber-text underline underline-offset-2 hover:text-amber-text/80 transition-colors">Terms and Conditions</a>
                and
                <a href="{{ route('frontend.page', ['lang' => app()->getLocale(), 'slug' => 'privacy']) }}" target="_blank"
                   class="font-bold text-amber-text underline underline-offset-2 hover:text-amber-text/80 transition-colors">Privacy Policy</a>
            </span>
        </label>
        @error('agree_terms')
            <p class="mt-2 text-xs text-red-500 font-semibold ml-7">{{ $message }}</p>
        @enderror
    </div>

</div>
@endsection
