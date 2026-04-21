@extends('layouts.app')

@section('title', __('Secure Checkout') . ' — ' . settings('general.site_name', 'OEMHub'))

@section('content')
<div class="min-h-screen bg-[#F8FAFC]">

    {{-- ── Navy Hero Header ── --}}
    <div class="bg-gradient-to-br from-navy via-[#0d4a87] to-blue-900 pt-24 pb-6 px-4 relative overflow-hidden">
        {{-- Dot grid --}}
        <div class="absolute inset-0 pointer-events-none"
             style="background-image: radial-gradient(rgba(255,255,255,0.05) 1px, transparent 1px); background-size: 24px 24px;"></div>
        {{-- Glow orbs --}}
        <div class="absolute -top-20 -right-20 w-80 h-80 rounded-full pointer-events-none"
             style="background: radial-gradient(circle, rgba(245,158,11,0.12) 0%, transparent 70%);"></div>
        <div class="absolute -bottom-10 -left-10 w-60 h-60 rounded-full pointer-events-none"
             style="background: radial-gradient(circle, rgba(255,255,255,0.04) 0%, transparent 70%);"></div>

        <div class="max-w-6xl mx-auto relative z-10 flex items-end justify-between gap-4">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.3em] text-white/35 mb-3">
                    {{ settings('general.site_name', 'OEMHub') }} / Checkout
                </p>
                <h1 class="font-display text-4xl md:text-5xl font-black text-white tracking-tight leading-none">
                    Secure Checkout
                </h1>
                <p class="mt-3 text-white/50 text-sm font-medium flex items-center gap-2">
                    <svg class="w-3.5 h-3.5 text-amber/80 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                    </svg>
                    256-bit SSL encryption &middot; Protected by Airwallex
                </p>
            </div>
            <a href="{{ route('frontend.cart.index', ['lang' => app()->getLocale()]) }}"
               class="hidden md:inline-flex items-center gap-2 px-5 py-2.5 rounded-[12px]
                      bg-white/10 hover:bg-white/20 border border-white/15 text-white text-sm
                      font-bold transition-all shrink-0 mb-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
                </svg>
                Back to Cart
            </a>
        </div>
    </div>

    {{-- ── Step Progress Band ── --}}
    <div class="bg-white border-b border-gray-100 shadow-sm sticky top-0 z-30">
        <div class="max-w-6xl mx-auto px-4 py-5">
            @php
                $steps = [
                    1 => 'Contact',
                    2 => 'Address',
                    3 => 'Shipping',
                    4 => 'Review',
                    5 => 'Payment',
                ];
                $currentStep = $step ?? 1;
            @endphp

            <div class="flex items-center justify-between">
                @foreach($steps as $num => $label)
                    <div class="flex items-center {{ !$loop->last ? 'flex-1' : '' }}">

                        {{-- Step circle + label --}}
                        <div class="flex flex-col items-center shrink-0">
                            <div class="w-9 h-9 rounded-[11px] flex items-center justify-center text-sm font-black
                                @if($num < $currentStep)
                                    bg-emerald-500 text-white shadow-md shadow-emerald-500/30
                                @elseif($num === $currentStep)
                                    bg-navy text-white shadow-md shadow-navy/25
                                @else
                                    bg-gray-100 text-gray-400
                                @endif">
                                @if($num < $currentStep)
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                    </svg>
                                @else
                                    {{ $num }}
                                @endif
                            </div>
                            <span class="mt-1.5 text-[9px] font-black uppercase tracking-[0.08em] hidden sm:block
                                @if($num === $currentStep) text-navy
                                @elseif($num < $currentStep) text-emerald-600
                                @else text-gray-400
                                @endif">
                                {{ $label }}
                            </span>
                        </div>

                        {{-- Connector (not after last item) --}}
                        @if(!$loop->last)
                            <div class="flex-1 h-[3px] mx-2 sm:mx-3 rounded-full
                                {{ $num < $currentStep ? 'bg-emerald-400' : 'bg-gray-200' }}">
                            </div>
                        @endif

                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ── Decorative background blobs (below the sticky bar) ── --}}
    <div class="relative">
        <div class="absolute top-0 right-0 w-[50rem] h-[50rem] pointer-events-none -mr-48 -mt-24 opacity-60"
             style="background: radial-gradient(circle, rgba(245,158,11,0.06) 0%, transparent 65%); filter: blur(70px);"></div>
        <div class="absolute bottom-0 left-0 w-[40rem] h-[40rem] pointer-events-none -ml-32 opacity-50"
             style="background: radial-gradient(circle, rgba(59,130,246,0.05) 0%, transparent 65%); filter: blur(70px);"></div>

        {{-- ── Main Content ── --}}
        <div class="max-w-6xl mx-auto px-4 py-8 relative z-10">

            <form method="POST"
                  action="{{ route('frontend.checkout.store', ['lang' => app()->getLocale()]) }}"
                  id="checkout-form">
                @csrf
                {{-- Honeypot --}}
                <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">

                <div class="grid lg:grid-cols-3 gap-8 items-start">

                    {{-- ── Left: Step Form (2/3) ── --}}
                    <div class="lg:col-span-2">

                        {{-- Step card --}}
                        <div class="bg-white rounded-3xl border border-gray-100 shadow-xl shadow-gray-200/60 p-7 sm:p-10">
                            @yield('checkout_content')
                        </div>

                        {{-- Navigation --}}
                        <div class="mt-6 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
                            @if($currentStep > 1)
                                <a href="{{ route('frontend.checkout', ['lang' => app()->getLocale()]) }}?_back=1"
                                   class="inline-flex items-center justify-center gap-2 px-6 py-4 rounded-2xl
                                          bg-white border-2 border-gray-200 text-navy font-black text-sm
                                          hover:border-navy hover:shadow-md transition-all group">
                                    <svg class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
                                    </svg>
                                    Back
                                </a>
                            @else
                                <a href="{{ route('frontend.cart.index', ['lang' => app()->getLocale()]) }}"
                                   class="inline-flex items-center justify-center gap-2 px-6 py-4 rounded-2xl
                                          bg-white border-2 border-gray-200 text-navy font-black text-sm
                                          hover:border-navy hover:shadow-md transition-all group">
                                    <svg class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
                                    </svg>
                                    Return to Cart
                                </a>
                            @endif

                            <button type="submit" form="checkout-form"
                                    class="flex-1 sm:flex-none inline-flex items-center justify-center gap-3 px-10 py-4
                                           rounded-2xl font-black text-sm uppercase tracking-[0.1em]
                                           bg-gradient-to-r from-amber to-orange-500 text-navy
                                           shadow-lg shadow-amber/40 hover:shadow-xl hover:shadow-amber/50
                                           hover:-translate-y-0.5 transition-all duration-200 group">
                                @if($currentStep === 5)
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                    </svg>
                                    Place Order
                                @else
                                    Continue
                                    <svg class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                                    </svg>
                                @endif
                            </button>
                        </div>
                    </div>

                    {{-- ── Right: Order Summary (1/3, sticky below the progress band) ── --}}
                    <div class="lg:col-span-1 lg:sticky lg:top-20 lg:h-fit">

                        <div class="bg-white rounded-3xl border border-gray-100 shadow-xl shadow-gray-200/60 p-6 sm:p-7 overflow-hidden relative">
                            {{-- Amber glow top-right --}}
                            <div class="absolute -top-8 -right-8 w-32 h-32 rounded-full pointer-events-none"
                                 style="background: radial-gradient(circle, rgba(245,158,11,0.12) 0%, transparent 70%);"></div>

                            <h3 class="font-display text-xl font-black text-navy mb-6 flex items-center gap-3 relative z-10">
                                <div class="w-9 h-9 rounded-xl bg-amber/10 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4 text-amber" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                    </svg>
                                </div>
                                Order Summary
                            </h3>

                            @php
                                $summaryCart = $checkoutCart ?? null;
                                $summaryData = $checkoutSummary ?? null;
                                $summarySidebar = $sidebarSummary ?? [];
                            @endphp

                            @if($summaryData && $summaryCart && $summaryCart->items->isNotEmpty())

                                {{-- Items --}}
                                <div class="space-y-3 mb-5 pb-5 border-b border-gray-100 max-h-52 overflow-y-auto scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-gray-100">
                                    @foreach($summaryCart->items as $item)
                                        <div class="flex items-center justify-between gap-3">
                                            <div class="flex items-center gap-3 min-w-0">
                                                <div class="w-9 h-9 rounded-xl bg-navy/5 flex items-center justify-center shrink-0">
                                                    <svg class="w-4 h-4 text-navy/30" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"/>
                                                    </svg>
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="font-mono font-black text-navy text-sm truncate">{{ $item->product->oem_number }}</p>
                                                    <p class="text-[11px] text-muted font-medium mt-0.5">Qty: {{ $item->quantity }}</p>
                                                </div>
                                            </div>
                                            <span class="font-black text-navy text-sm shrink-0">
                                                €{{ number_format((float) bcmul((string) $item->price_at_add, (string) $item->quantity, 2), 2) }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>

                                {{-- Coupon row --}}
                                @if(!empty($summaryData['coupon_code']))
                                    <div class="flex items-center justify-between text-sm mb-4">
                                        <span class="flex items-center gap-1.5 text-amber-600 font-bold">
                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M2 6a2 2 0 012-2h12a2 2 0 012 2v2a1 1 0 010 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2v-6a1 1 0 010-2V6z"/>
                                            </svg>
                                            {{ $summaryData['coupon_code'] }}
                                        </span>
                                        <span class="font-black text-amber-600">-€{{ number_format($summaryData['coupon_discount'] ?? 0, 2) }}</span>
                                    </div>
                                @endif

                                {{-- Totals --}}
                                <div class="space-y-2.5">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted font-medium">Subtotal</span>
                                        <span class="font-black text-navy">€{{ number_format((float) ($summarySidebar['subtotal'] ?? 0), 2) }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted font-medium">Shipping</span>
                                        <span class="font-bold text-navy">
                                            @if(($summarySidebar['shipping_cost'] ?? null) !== null)
                                                @if(($summarySidebar['shipping_cost'] ?? null) === '0.00')
                                                    <span class="text-emerald-600 font-black">FREE</span>
                                                @else
                                                    €{{ number_format((float) ($summarySidebar['shipping_cost'] ?? 0), 2) }}
                                                @endif
                                            @else
                                                <span class="text-[11px] font-black text-amber uppercase tracking-wide">Next step</span>
                                            @endif
                                        </span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted font-medium">VAT ({{ $summarySidebar['vat_rate'] ?? ($summaryData['vat_rate'] ?? 21) }}%)</span>
                                        <span class="font-bold text-navy">
                                            @if($currentStep >= 2)
                                                €{{ number_format((float) ($summarySidebar['vat_amount'] ?? 0), 2) }}
                                            @else
                                                <span class="text-[11px] font-black text-gray-400 uppercase tracking-wide">TBD</span>
                                            @endif
                                        </span>
                                    </div>
                                </div>

                                {{-- Grand total --}}
                                <div class="mt-5 pt-5 border-t-2 border-amber/20">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-[10px] font-black uppercase tracking-[0.15em] text-muted">Total</p>
                                            <p class="text-[9px] font-bold text-emerald-600 uppercase tracking-wider mt-0.5">Incl. all taxes</p>
                                        </div>
                                        <p class="font-display text-3xl font-black text-navy tracking-tight">
                                            €{{ number_format((float) ($summarySidebar['grand_total'] ?? 0), 2) }}
                                        </p>
                                    </div>
                                </div>

                                {{-- Trust badges --}}
                                <div class="mt-5 pt-5 border-t border-gray-100 flex items-center justify-center gap-4">
                                    <div class="flex items-center gap-1.5 text-muted">
                                        <svg class="w-3.5 h-3.5 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        <span class="text-[10px] font-black uppercase tracking-widest">SSL Secure</span>
                                    </div>
                                    <div class="w-px h-4 bg-gray-200"></div>
                                    <div class="flex items-center gap-1.5 text-muted">
                                        <svg class="w-3.5 h-3.5 text-navy/50" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                        </svg>
                                        <span class="text-[10px] font-black uppercase tracking-widest">Airwallex</span>
                                    </div>
                                </div>

                            @else
                                <div class="text-center py-10">
                                    <svg class="w-12 h-12 text-gray-200 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                    </svg>
                                    <p class="text-muted text-sm font-medium">Your cart is empty</p>
                                </div>
                            @endif
                        </div>

                        {{-- Support card --}}
                        <div class="mt-4 bg-white rounded-2xl border border-gray-100 shadow-md p-5 flex items-start gap-4">
                            <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center shrink-0 mt-0.5">
                                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs font-black text-navy uppercase tracking-[0.1em] mb-1">Need help?</p>
                                <p class="text-xs text-muted font-medium leading-relaxed mb-2.5">Mon–Fri 09:00–18:00. Our team is ready.</p>
                                <a href="/{{ app()->getLocale() }}/contact"
                                   class="inline-flex items-center gap-1 text-xs font-black text-navy hover:text-amber transition-colors">
                                    Contact support
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                    </svg>
                                </a>
                            </div>
                        </div>

                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Flash → Toast events --}}
@if(session('error'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    window.dispatchEvent(new CustomEvent('toast', {
        detail: { message: @json(session('error')), type: 'error', title: 'Error', duration: 6000 }
    }));
});
</script>
@endif
@if(session('success'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    window.dispatchEvent(new CustomEvent('toast', {
        detail: { message: @json(session('success')), type: 'success', duration: 4000 }
    }));
});
</script>
@endif
@if(session('warning'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    window.dispatchEvent(new CustomEvent('toast', {
        detail: { message: @json(session('warning')), type: 'warning', title: 'Warning', duration: 5000 }
    }));
});
</script>
@endif
@endsection
