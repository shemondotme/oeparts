@extends('layouts.app')

@section('title', 'Order Confirmed - ' . settings('general.site_name', 'OEMHub'))

@section('content')
@php
    // Get order from session or use fallback for demo
    $order = session('checkout.order');
    if (!$order) {
        $orderData = [
            'order_number' => 'ORD-202604-000123',
            'email' => 'customer@example.com',
            'created_at' => now(),
            'grand_total' => 134.30,
            'payment_status' => 'pending',
        ];
    } else {
        $orderData = [
            'order_number' => $order->order_number,
            'email' => $order->email,
            'created_at' => $order->created_at,
            'grand_total' => $order->grand_total,
            'payment_status' => $order->payment_status,
        ];
    }
@endphp

<div class="min-h-screen bg-gradient-to-br from-emerald-50 via-white to-blue-50 py-12 px-4">
    <div class="max-w-3xl mx-auto">

        {{-- ── Success Icon ─────────────────────────────────────────── --}}
        <div class="relative inline-flex items-center justify-center w-28 h-28 mx-auto mb-8">
            <div class="absolute inset-0 bg-emerald-200 rounded-full animate-ping opacity-20"></div>
            <div class="absolute inset-2 bg-emerald-100 rounded-full animate-pulse opacity-50"></div>
            <div class="relative w-28 h-28 rounded-full bg-gradient-to-br from-emerald-500 to-emerald-600
                        flex items-center justify-center shadow-2xl shadow-emerald-500/40">
                <x-heroicon-s-check-circle class="w-16 h-16 text-white" />
            </div>
        </div>

        {{-- ── Thank You Message ────────────────────────────────────── --}}
        <div class="text-center mb-10">
            <h1 class="font-display text-4xl md:text-5xl font-extrabold text-navy mb-3">
                Thank You!
            </h1>
            <p class="text-lg text-muted max-w-md mx-auto">
                Your order has been placed successfully. We're preparing it for shipment.
            </p>
        </div>

        {{-- ── Order Details Card ───────────────────────────────────── --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-xl p-8 mb-8">
            <div class="mb-6 pb-6 border-b border-gray-100">
                <p class="text-sm text-muted mb-2 flex items-center gap-2">
                    <x-heroicon-o-document-text class="w-4 h-4 text-amber" />
                    Order Number
                </p>
                <p class="font-mono font-extrabold text-3xl text-navy">{{ $orderData['order_number'] }}</p>
            </div>

            <div class="grid md:grid-cols-3 gap-6 mb-6 pb-6 border-b border-gray-100">
                <div>
                    <p class="text-xs text-muted font-semibold uppercase tracking-wide mb-2 flex items-center gap-1.5">
                        <x-heroicon-o-envelope class="w-3.5 h-3.5 text-amber" />
                        Email
                    </p>
                    <p class="text-navy font-mono font-bold text-sm truncate">{{ $orderData['email'] }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted font-semibold uppercase tracking-wide mb-2 flex items-center gap-1.5">
                        <x-heroicon-o-calendar class="w-3.5 h-3.5 text-amber" />
                        Date
                    </p>
                    <p class="text-navy font-mono font-bold text-sm">{{ $orderData['created_at']->format('M j, Y') }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted font-semibold uppercase tracking-wide mb-2 flex items-center gap-1.5">
                        <x-heroicon-o-credit-card class="w-3.5 h-3.5 text-amber" />
                        Payment
                    </p>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold
                                 {{ $orderData['payment_status'] === 'paid' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber/15 text-amber-text' }}">
                        @if($orderData['payment_status'] === 'paid')
                            <x-heroicon-s-check-circle class="w-3 h-3" />
                        @else
                            <x-heroicon-o-clock class="w-3 h-3" />
                        @endif
                        {{ ucfirst($orderData['payment_status']) }}
                    </span>
                </div>
            </div>

            <div class="p-6 bg-gradient-to-br from-navy/5 to-blue-50 rounded-xl border border-navy/10">
                <p class="text-sm text-muted mb-1">Order Total</p>
                <p class="text-4xl font-extrabold text-amber">€{{ number_format($orderData['grand_total'], 2) }}</p>
            </div>
        </div>

        {{-- ── What's Next? ─────────────────────────────────────────── --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8 mb-8">
            <h3 class="font-display text-lg font-bold text-navy mb-6 flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-amber/10 flex items-center justify-center">
                    <x-heroicon-o-light-bulb class="w-4 h-4 text-amber" />
                </div>
                What's Next?
            </h3>

            <div class="space-y-4">
                <div class="flex items-start gap-4">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-amber to-orange-500 flex items-center justify-center shrink-0 shadow-md">
                        <span class="text-white font-bold text-sm">1</span>
                    </div>
                    <div class="flex-1">
                        <p class="font-bold text-navy">Confirmation Email</p>
                        <p class="text-sm text-muted mt-0.5">Check your inbox for detailed order confirmation and receipt</p>
                    </div>
                </div>

                <div class="flex items-start gap-4">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-amber to-orange-500 flex items-center justify-center shrink-0 shadow-md">
                        <span class="text-white font-bold text-sm">2</span>
                    </div>
                    <div class="flex-1">
                        <p class="font-bold text-navy">Processing</p>
                        <p class="text-sm text-muted mt-0.5">We'll prepare and pack your order within 24 hours</p>
                    </div>
                </div>

                <div class="flex items-start gap-4">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-amber to-orange-500 flex items-center justify-center shrink-0 shadow-md">
                        <span class="text-white font-bold text-sm">3</span>
                    </div>
                    <div class="flex-1">
                        <p class="font-bold text-navy">Tracking Updates</p>
                        <p class="text-sm text-muted mt-0.5">Receive real-time tracking updates via email once shipped</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Action Buttons ───────────────────────────────────────── --}}
        <div class="grid sm:grid-cols-2 gap-4 mb-8">
            <a href="{{ route('frontend.account.orders', ['lang' => app()->getLocale()]) }}"
               class="inline-flex items-center justify-center gap-2 px-6 py-4 bg-gradient-to-r from-amber to-orange-500 text-navy font-bold rounded-xl
                      shadow-lg shadow-amber/30 hover:shadow-amber/50 hover:from-amber/90 hover:to-orange-400
                      transition-all duration-200">
                <x-heroicon-o-list-bullet class="w-5 h-5" />
                View All Orders
            </a>
            <a href="{{ route('frontend.home', ['lang' => app()->getLocale()]) }}"
               class="inline-flex items-center justify-center gap-2 px-6 py-4 border-2 border-navy text-navy font-bold rounded-xl
                      hover:bg-navy/5 hover:border-navy/80 transition-all duration-200">
                <x-heroicon-o-arrow-left class="w-5 h-5" />
                Continue Shopping
            </a>
        </div>

        {{-- ── Continue Shopping CTA ────────────────────────────────── --}}
        <div class="relative overflow-hidden bg-gradient-to-br from-navy via-navy to-blue-900 rounded-2xl p-8 text-white mb-8">
            <div class="absolute top-0 right-0 w-48 h-48 bg-amber/10 rounded-full filter blur-3xl pointer-events-none"></div>
            <div class="absolute bottom-0 left-0 w-32 h-32 bg-blue-400/10 rounded-full filter blur-2xl pointer-events-none"></div>

            <div class="relative z-10 flex flex-col sm:flex-row items-center gap-6">
                <div class="flex-1 text-center sm:text-left">
                    <h3 class="font-display font-bold text-xl mb-2">Need More Parts?</h3>
                    <p class="text-white/70 text-sm">Continue shopping and discover more genuine OEM parts for your vehicle</p>
                </div>
                <a href="{{ route('frontend.home', ['lang' => app()->getLocale()]) }}"
                   class="shrink-0 inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-amber to-orange-500 text-navy font-bold rounded-xl
                          shadow-lg shadow-amber/30 hover:shadow-amber/50 hover:scale-105
                          transition-all duration-200">
                    <x-heroicon-o-magnifying-glass class="w-5 h-5" />
                    Search OEM Parts
                </a>
            </div>
        </div>

        {{-- ── Support Link ─────────────────────────────────────────── --}}
        <div class="text-center pt-6 border-t border-gray-200">
            <p class="text-sm text-muted mb-3">Need help? We're here for you</p>
            <a href="{{ route('frontend.contact.show', ['lang' => app()->getLocale()]) }}"
               class="inline-flex items-center gap-2 text-amber-text hover:text-amber font-bold transition-colors">
                <x-heroicon-o-chat-bubble-left-right class="w-4 h-4" />
                Contact Support
            </a>
        </div>

    </div>
</div>
@endsection
