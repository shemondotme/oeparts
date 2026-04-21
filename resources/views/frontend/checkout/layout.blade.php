@extends('layouts.app')

@section('title', __('Checkout') . ' - ' . settings('general.site_name', 'OEMHub'))

@section('content')
<div class="min-h-screen bg-gray-50">
    {{-- Header --}}
    <div class="bg-gradient-to-r from-navy to-blue-900 text-white py-8 px-4">
        <div class="max-w-5xl mx-auto">
            <h1 class="font-display text-3xl md:text-4xl font-bold">Secure Checkout</h1>
            <p class="text-white/70 mt-2">Complete your order in 5 simple steps</p>
        </div>
    </div>

    {{-- Progress Indicator --}}
    <div class="bg-white border-b border-gray-100 py-8 px-4 sticky top-0 z-30">
        <div class="max-w-5xl mx-auto">
            @php
                $steps = [
                    1 => 'Contact Info',
                    2 => 'Shipping Address',
                    3 => 'Shipping Method',
                    4 => 'Review Order',
                    5 => 'Payment',
                ];
                $currentStep = $step ?? 1;
            @endphp

            <div class="flex justify-between items-start mb-4">
                @foreach($steps as $number => $label)
                    <div class="flex flex-col items-center flex-1">
                        {{-- Step Circle --}}
                        <div class="relative w-full mb-3">
                            {{-- Connector line before (except first) --}}
                            @if($number > 1)
                                <div class="absolute left-0 top-5 -translate-x-1/2 w-full h-1 -ml-1/2"
                                     :class="'{{ $number <= $currentStep ? 'bg-gradient-to-r from-amber to-orange-500' : 'bg-gray-200' }}'"></div>
                            @endif

                            {{-- Circle --}}
                            <div class="relative w-10 h-10 rounded-full border-2 flex items-center justify-center font-bold text-sm
                                        {{ $number == $currentStep ? 'border-amber bg-amber/10 text-amber font-extrabold' : ($number < $currentStep ? 'border-amber bg-amber text-white' : 'border-gray-300 bg-white text-gray-400') }}"
                                 style="margin: 0 auto;">
                                @if($number < $currentStep)
                                    <x-heroicon-s-check class="w-5 h-5" />
                                @else
                                    <span>{{ $number }}</span>
                                @endif
                            </div>

                            {{-- Connector line after (except last) --}}
                            @if($number < count($steps))
                                <div class="absolute right-0 top-5 translate-x-1/2 w-full h-1 mr-1/2"
                                     :class="'{{ $number < $currentStep ? 'bg-gradient-to-r from-amber to-orange-500' : 'bg-gray-200' }}'"></div>
                            @endif
                        </div>

                        {{-- Label --}}
                        <span class="text-xs font-semibold text-center whitespace-nowrap
                                    {{ $number == $currentStep ? 'text-amber' : ($number < $currentStep ? 'text-navy' : 'text-muted') }}">
                            {{ $label }}
                        </span>
                    </div>
                @endforeach
            </div>

            {{-- Step Indicator Text --}}
            <p class="text-center text-sm font-semibold text-navy">
                Step {{ $currentStep }} of {{ count($steps) }}
            </p>
        </div>
    </div>

    {{-- Main Content --}}
    <div class="max-w-5xl mx-auto px-4 py-8">

        {{-- Flash Messages --}}
        @if(session('error'))
            <div class="mb-6 p-5 rounded-xl bg-red-50 border-2 border-red-200 text-red-700 flex items-start gap-3">
                <x-heroicon-s-exclamation-circle class="w-5 h-5 mt-0.5 shrink-0" />
                <div>
                    <p class="font-bold">Error</p>
                    <p class="text-sm mt-1">{{ session('error') }}</p>
                </div>
            </div>
        @endif

        @if(session('success'))
            <div class="mb-6 p-5 rounded-xl bg-emerald-50 border-2 border-emerald-200 text-emerald-700 flex items-start gap-3">
                <x-heroicon-s-check-circle class="w-5 h-5 mt-0.5 shrink-0" />
                <div>
                    <p class="font-bold">Success</p>
                    <p class="text-sm mt-1">{{ session('success') }}</p>
                </div>
            </div>
        @endif

        {{-- Form Layout --}}
        <div class="grid lg:grid-cols-3 gap-8">

            {{-- Left: Form (2 columns) --}}
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl border border-gray-100 p-8">
                    @yield('checkout_content')
                </div>

                {{-- Navigation Buttons --}}
                <div class="mt-6 flex flex-col sm:flex-row justify-between gap-4">
                    @if($currentStep > 1)
                        <a href="{{ route('frontend.checkout.show', ['lang' => app()->getLocale()]) }}"
                           class="inline-flex items-center justify-center gap-2 px-6 py-3
                                  bg-white border-2 border-gray-200 text-navy font-bold rounded-xl
                                  hover:border-amber hover:text-amber transition-all duration-200">
                            <x-heroicon-o-arrow-left class="w-4 h-4" />
                            Back
                        </a>
                    @else
                        <a href="{{ route('frontend.cart.index', ['lang' => app()->getLocale()]) }}"
                           class="inline-flex items-center justify-center gap-2 px-6 py-3
                                  bg-white border-2 border-gray-200 text-navy font-bold rounded-xl
                                  hover:border-amber hover:text-amber transition-all duration-200">
                            <x-heroicon-o-arrow-left class="w-4 h-4" />
                            Return to Cart
                        </a>
                    @endif

                    <form method="POST" action="{{ route('frontend.checkout.store', ['lang' => app()->getLocale()]) }}" class="flex-1 sm:flex-none">
                        @csrf
                        <button type="submit"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-3
                                       bg-gradient-to-r from-amber to-orange-500 text-navy font-bold rounded-xl
                                       shadow-lg shadow-amber/30 hover:shadow-amber/50 hover:from-amber/90 hover:to-orange-400
                                       transition-all duration-200">
                            @if($currentStep == 5)
                                <x-heroicon-o-credit-card class="w-4 h-4" />
                                Place Order
                            @else
                                {{ __('Continue') }}
                                <x-heroicon-o-arrow-right class="w-4 h-4" />
                            @endif
                        </button>
                    </form>
                </div>
            </div>

            {{-- Right: Order Summary --}}
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl border border-gray-100 p-6 sticky top-32 h-fit">
                    <h3 class="font-display text-lg font-bold text-navy mb-5 flex items-center gap-2">
                        <x-heroicon-s-shopping-cart class="w-5 h-5 text-amber" />
                        Order Summary
                    </h3>

                    @php
                        $cartService = app(\App\Services\CartService::class);
                        $user = auth()->user();
                        $guestToken = request()->cookie('guest_token');
                        $cart = $cartService->getOrCreateCart($user, $guestToken);
                        $summary = $cart ? $cartService->getSummary($cart) : null;
                    @endphp

                    @if($summary && $cart->items->isNotEmpty())
                        {{-- Items --}}
                        <div class="space-y-2 mb-5 pb-5 border-b border-gray-100 max-h-48 overflow-y-auto">
                            @foreach($cart->items as $item)
                                <div class="flex items-start justify-between gap-2 text-sm">
                                    <div class="min-w-0">
                                        <p class="font-mono font-bold text-navy truncate">{{ $item->product->oem_number }}</p>
                                        <p class="text-xs text-muted">Qty: {{ $item->quantity }}</p>
                                    </div>
                                    <span class="font-bold text-navy shrink-0">€{{ number_format($item->price_at_add * $item->quantity, 2) }}</span>
                                </div>
                            @endforeach
                        </div>

                        {{-- Totals --}}
                        <div class="space-y-3">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-muted">Subtotal</span>
                                <span class="font-bold text-navy">€{{ number_format($summary['subtotal'], 2) }}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-muted">Shipping</span>
                                <span class="text-amber font-semibold">
                                    @if($currentStep >= 3)
                                        Calculated
                                    @else
                                        TBD
                                    @endif
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-muted">VAT</span>
                                <span class="text-amber font-semibold">
                                    @if($currentStep >= 4)
                                        Calculated
                                    @else
                                        TBD
                                    @endif
                                </span>
                            </div>

                            <div class="pt-3 border-t-2 border-amber/20">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-navy">Estimated Total</span>
                                    <span class="text-2xl font-bold text-amber">€{{ number_format($summary['subtotal'], 2) }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- Security Info --}}
                        <div class="mt-6 pt-6 border-t border-gray-100">
                            <p class="text-xs text-muted text-center flex items-center justify-center gap-1.5">
                                <x-heroicon-s-lock-closed class="w-3.5 h-3.5 text-amber" />
                                256-bit SSL Secure
                            </p>
                        </div>
                    @else
                        <div class="text-center py-8">
                            <x-heroicon-o-shopping-cart class="w-12 h-12 text-gray-300 mx-auto mb-3" />
                            <p class="text-muted text-sm">Your cart is empty</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
