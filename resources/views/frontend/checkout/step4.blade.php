@extends('frontend.checkout.layout')

@section('checkout_content')
@php
    // Get cart data dynamically
    $cartService = app(\App\Services\CartService::class);
    $user = auth()->user();
    $guestToken = request()->cookie('guest_token');
    $cart = $cartService->getOrCreateCart($user, $guestToken);
    $summary = $cart ? $cartService->getSummary($cart) : null;

    // Get shipping method price
    $shippingMethod = old('shipping_method', session('checkout.shipping_method'));
    $shippingPrices = [
        'dhl' => 12.99,
        'dpd' => 10.99,
        'gls' => 8.99,
        'free' => 0.00,
    ];
    $shippingCost = $shippingPrices[$shippingMethod] ?? 0.00;

    // Calculate VAT
    $vatRate = session('checkout.vat_rate', 21);
    $subtotal = $summary['subtotal'] ?? 0.00;
    $vatAmount = bcdiv(bcmul($subtotal, $vatRate, 4), '100', 2);
    $total = bcadd(bcadd($subtotal, $shippingCost, 2), $vatAmount, 2);
@endphp

<div class="space-y-6">
    {{-- ── Order Items ────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
        <h3 class="font-display text-base font-bold text-navy mb-4 flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-amber/10 flex items-center justify-center">
                <x-heroicon-o-cube class="w-4 h-4 text-amber" />
            </div>
            Order Items
        </h3>

        @if($cart && $cart->items->isNotEmpty())
        <div class="space-y-3 pb-4 border-b border-gray-100">
            @foreach($cart->items as $item)
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-start gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-lg bg-navy/5 flex items-center justify-center shrink-0">
                        <x-heroicon-o-cube class="w-5 h-5 text-navy/40" />
                    </div>
                    <div class="min-w-0">
                        <p class="font-mono font-bold text-navy text-sm truncate">{{ $item->product->oem_number }}</p>
                        <p class="text-xs text-muted mt-0.5">
                            {{ $item->quantity }} × €{{ number_format($item->price_at_add, 2) }}
                        </p>
                    </div>
                </div>
                <span class="font-bold text-navy shrink-0">€{{ number_format($item->price_at_add * $item->quantity, 2) }}</span>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-muted text-sm">No items in cart</p>
        @endif
    </div>

    {{-- ── Shipping Address ──────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
        <h3 class="font-display text-base font-bold text-navy mb-4 flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                <x-heroicon-o-map-pin class="w-4 h-4 text-blue-600" />
            </div>
            Shipping To
        </h3>
        @php
            $addr = session('checkout.address', []);
        @endphp
        @if(!empty($addr))
        <div class="p-4 bg-gray-50 rounded-xl border border-gray-100">
            <p class="text-sm text-navy leading-relaxed">
                <strong class="font-bold text-navy">{{ $addr['first_name'] ?? '' }} {{ $addr['last_name'] ?? '' }}</strong><br>
                {{ $addr['street'] ?? '' }}<br>
                <span class="font-mono font-semibold">{{ $addr['postal_code'] ?? '' }}</span> {{ $addr['city'] ?? '' }}<br>
                <span class="text-muted">{{ $addr['country_code'] ?? '' }}</span>
            </p>
        </div>
        @else
        <p class="text-muted text-sm">No shipping address provided</p>
        @endif
    </div>

    {{-- ── Shipping Method ───────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
        <h3 class="font-display text-base font-bold text-navy mb-4 flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center">
                <x-heroicon-o-truck class="w-4 h-4 text-emerald-600" />
            </div>
            Shipping Method
        </h3>
        @php
            $carrierNames = ['dhl' => 'DHL', 'dpd' => 'DPD', 'gls' => 'GLS', 'free' => 'Standard'];
        @endphp
        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl border border-gray-100">
            <p class="font-bold text-navy">{{ $carrierNames[$shippingMethod] ?? 'Standard' }}</p>
            <p class="font-bold text-lg {{ $shippingCost === 0.00 ? 'text-emerald-600' : 'text-amber' }}">
                {{ $shippingCost === 0.00 ? 'FREE' : '€' . number_format($shippingCost, 2) }}
            </p>
        </div>
    </div>

    {{-- ── Pricing Breakdown ─────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
        <h3 class="font-display text-base font-bold text-navy mb-4 flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-navy/10 flex items-center justify-center">
                <x-heroicon-o-calculator class="w-4 h-4 text-navy" />
            </div>
            Pricing Breakdown
        </h3>

        <div class="space-y-3 pb-4 border-b border-gray-100">
            <div class="flex justify-between text-sm">
                <span class="text-muted">Subtotal</span>
                <span class="font-bold text-navy">€{{ number_format($subtotal, 2) }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-muted">Shipping</span>
                <span class="font-bold {{ $shippingCost === 0.00 ? 'text-emerald-600' : 'text-navy' }}">
                    {{ $shippingCost === 0.00 ? 'FREE' : '€' . number_format($shippingCost, 2) }}
                </span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-muted">VAT ({{ $vatRate }}%)</span>
                <span class="font-bold text-navy">€{{ number_format($vatAmount, 2) }}</span>
            </div>
        </div>

        <div class="flex items-center justify-between pt-4">
            <div>
                <span class="font-bold text-navy text-lg">Total</span>
                <p class="text-xs text-muted mt-0.5">Including VAT</p>
            </div>
            <span class="text-3xl font-extrabold text-amber">€{{ number_format($total, 2) }}</span>
        </div>
    </div>

    {{-- ── Agreements ────────────────────────────────────────────────── --}}
    <div class="bg-gradient-to-br from-navy/5 to-blue-50 rounded-2xl p-6 border border-navy/10 space-y-4">
        <label class="flex items-start gap-3 cursor-pointer group">
            <input type="checkbox" name="agree_terms" required
                   class="w-5 h-5 mt-0.5 rounded border-gray-300 text-amber focus:ring-amber focus:ring-2">
            <span class="text-sm text-navy group-hover:text-navy transition-colors">
                I agree to the
                <a href="/{{ app()->getLocale() }}/page/terms" class="text-amber-text hover:text-amber font-semibold underline underline-offset-2">Terms and Conditions</a>
                and
                <a href="/{{ app()->getLocale() }}/page/privacy" class="text-amber-text hover:text-amber font-semibold underline underline-offset-2">Privacy Policy</a>
            </span>
        </label>
    </div>
</div>
@endsection
