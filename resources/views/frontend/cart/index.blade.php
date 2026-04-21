@extends('layouts.app')

@section('title', __('cart.title'))

@push('styles')
{{-- Premium Cart Enhancements --}}
<style>
    /* Decorative blobs for depth */
    .bg-blob {
        position: absolute;
        border-radius: 50%;
        filter: blur(60px);
        z-index: -1;
        pointer-events: none;
    }
    .blob-amber { background: radial-gradient(circle, rgba(245, 158, 11, 0.08) 0%, transparent 70%); }
    .blob-blue { background: radial-gradient(circle, rgba(59, 130, 246, 0.06) 0%, transparent 70%); }

    /* Item row transitions */
    .cart-item-card {
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .cart-item-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 15px 35px -8px rgba(11, 58, 104, 0.08);
        border-color: rgba(245, 158, 11, 0.3);
    }

    /* Progress bar animation */
    @keyframes progressGlow {
        0%, 100% { opacity: 0.6; }
        50% { opacity: 1; text-shadow: 0 0 10px rgba(245, 158, 11, 0.5); }
    }
    .free-shipping-glow {
        animation: progressGlow 2s infinite;
    }

    /* Stepper buttons */
    .stepper-btn {
        transition: all 0.2s ease;
    }
    .stepper-btn:hover:not(:disabled) {
        background-color: rgba(11, 58, 104, 0.1);
        color: #0B3A68;
    }
    .stepper-btn:active:not(:disabled) {
        transform: scale(0.95);
    }

    /* Respect reduced motion */
    @media (prefers-reduced-motion: reduce) {
        .cart-item-card, .stepper-btn, .free-shipping-glow {
            animation: none !important;
            transition: none !important;
        }
    }
</style>
@endpush

@section('content')
<div
    x-data="cartData()"
    class="min-h-screen bg-[#F8FAFC] relative overflow-hidden pt-20 pb-24"
>
    {{-- Decorative backgrounds --}}
    <div class="bg-blob blob-amber w-[40rem] h-[40rem] -top-32 -right-32"></div>
    <div class="bg-blob blob-blue w-[40rem] h-[40rem] bottom-10 -left-64"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 md:py-8 relative z-10">

        {{-- ── 1. Page Heading ── --}}
        <div class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-6"
             x-data="{ shown: false }"
             x-init="setTimeout(() => shown = true, 50)"
             :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'"
             style="transition: all 0.6s cubic-bezier(0.16, 1, 0.3, 1);">
            <div>
                <nav class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.2em] text-muted mb-4 opacity-70">
                    <a href="/{{ app()->getLocale() }}/" class="hover:text-amber hover:opacity-100 transition-colors">{{ __('Home') }}</a>
                    <span class="text-gray-300">/</span>
                    <span class="text-navy">{{ __('cart.title') }}</span>
                </nav>
                <h1 class="font-display text-4xl sm:text-5xl lg:text-6xl font-black text-navy tracking-tight leading-none flex items-center gap-5">
                    {{ __('cart.title') }}
                    <template x-if="summary.item_count > 0">
                        <span class="flex h-12 w-12 items-center justify-center rounded-[18px] bg-gradient-to-br from-amber to-orange-500 text-white text-xl font-black shadow-lg shadow-amber/30">
                            <span x-text="summary.item_count"></span>
                        </span>
                    </template>
                </h1>
            </div>
            
            {{-- Step Indicator --}}
            <div x-show="cart.items.length > 0" class="flex items-center gap-1 bg-white/60 backdrop-blur-xl rounded-[20px] p-2 border border-white max-w-fit shadow-sm">
                <div class="flex items-center gap-2 px-4 py-2 rounded-[14px] bg-navy text-white shadow-md">
                    <span class="text-xs font-black">01</span>
                    <span class="text-[10px] font-black uppercase tracking-[0.1em]">{{ __('cart.step_cart') }}</span>
                </div>
                <div class="w-6 h-px bg-gray-300 mx-1"></div>
                <div class="flex items-center gap-2 px-4 py-2 text-muted opacity-50">
                    <span class="text-xs font-black">02</span>
                    <span class="text-[10px] font-black uppercase tracking-[0.1em]">{{ __('cart.step_shipping') }}</span>
                </div>
                <div class="w-6 h-px bg-gray-300 mx-1"></div>
                <div class="flex items-center gap-2 px-4 py-2 text-muted opacity-50">
                    <span class="text-xs font-black">03</span>
                    <span class="text-[10px] font-black uppercase tracking-[0.1em]">{{ __('cart.step_payment') }}</span>
                </div>
            </div>
        </div>

        {{-- ── 2. Main Content Grid ── --}}
        
        {{-- EMPTY STATE --}}
        <div
            x-show="cart.items.length === 0"
            x-cloak
            x-transition:enter="transition ease-out duration-500"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            class="relative text-center py-20 lg:py-32 bg-white/50 backdrop-blur-xl border border-white rounded-[40px] shadow-sm"
        >
            <div class="mx-auto mb-10 relative w-fit">
                <div class="absolute inset-0 bg-gradient-to-br from-amber to-orange-400 blur-[40px] opacity-20 transform scale-150 rounded-full animate-pulse"></div>
                <div class="relative w-32 h-32 mx-auto bg-white rounded-[32px] shadow-xl flex items-center justify-center border border-white rotate-6 hover:rotate-12 transition-transform duration-500">
                     <x-heroicon-o-shopping-bag class="w-16 h-16 text-amber" />
                </div>
            </div>
            <h2 class="font-display text-4xl font-black text-navy mb-4 tracking-tight">{{ __('cart.empty_title') }}</h2>
            <p class="text-lg text-body font-medium mb-12 max-w-lg mx-auto">{{ __('cart.empty_subtitle') }}</p>
            
            <a href="/{{ app()->getLocale() }}/" class="inline-flex items-center justify-center gap-3 px-8 py-4 rounded-xl font-black text-white bg-gradient-to-r from-navy to-blue-900 shadow-xl shadow-navy/20 hover:shadow-2xl hover:-translate-y-1 transition-all group">
                <x-heroicon-o-magnifying-glass class="w-5 h-5 transition-transform group-hover:scale-110" />
                {{ __('cart.empty_browse_btn') }}
            </a>
            
            <div class="mt-16 pt-12 border-t border-gray-200/50">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-muted mb-6">{{ __('cart.empty_popular_label') }}</p>
                <div class="flex flex-wrap items-center justify-center gap-3">
                    @php $popularOEMs = ['1K0407271F', '3C0615301AA', '5Q0615301M', '8K0615301D']; @endphp
                    @foreach($popularOEMs as $oem)
                        <a href="/{{ app()->getLocale() }}/parts/{{ $oem }}" 
                           class="px-5 py-2.5 rounded-[14px] bg-white border border-gray-200 text-sm font-mono font-bold text-navy shadow-sm transition-all hover:border-amber hover:text-amber hover:shadow-md hover:-translate-y-0.5">
                            {{ $oem }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- CART CONTENT --}}
        <div x-show="cart.items.length > 0" x-cloak class="grid lg:grid-cols-12 gap-8 lg:gap-10 items-start">
            
            {{-- Left: Item List (8 Cols) --}}
            <div class="lg:col-span-8 flex flex-col gap-6">
                
                {{-- Global Error/Alert --}}
                <div x-show="errorMessage" x-cloak class="p-4 rounded-[20px] bg-red-50 border border-red-100 flex items-center gap-4 shadow-sm">
                    <div class="w-10 h-10 shrink-0 bg-red-100 rounded-full flex items-center justify-center">
                        <x-heroicon-s-exclamation-triangle class="w-5 h-5 text-red-500" />
                    </div>
                    <span class="text-red-800 font-bold text-sm flex-1" x-text="errorMessage"></span>
                    <button @click="errorMessage = ''" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-red-100 text-red-400 hover:text-red-700 transition-colors"><x-heroicon-o-x-mark class="w-4 h-4"/></button>
                </div>

                <div class="flex items-center justify-between px-2 mb-1">
                    <h3 class="text-xs font-black uppercase tracking-[0.15em] text-navy">{{ __('Order Items') }}</h3>
                    <button @click="clearCart()" class="text-[11px] font-black uppercase tracking-[0.1em] text-red-400 hover:text-red-600 flex items-center gap-1.5 transition-colors group">
                        <x-heroicon-o-trash class="w-3.5 h-3.5 transition-transform group-hover:rotate-12 group-hover:scale-110" />
                        {{ __('cart.clear_cart') }}
                    </button>
                </div>

                {{-- Shopping Cards Container --}}
                <div class="flex flex-col gap-4">
                    <template x-for="(item, index) in cart.items" :key="item.id">
                        <div 
                            class="cart-item-card relative overflow-hidden rounded-[28px] bg-white/90 backdrop-blur-md border border-white p-4 sm:p-5 shadow-sm"
                            x-bind:class="item.removing ? 'opacity-0 scale-95 translate-x-12' : 'opacity-100'"
                            style="transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);"
                        >
                            <div class="flex flex-col sm:flex-row gap-5 items-center sm:items-stretch">
                                {{-- Stylized Part Avatar Button --}}
                                <div class="w-20 h-20 sm:w-24 sm:h-24 shrink-0 rounded-[20px] bg-gradient-to-br from-navy to-blue-900 flex items-center justify-center shadow-inner relative group overflow-hidden">
                                    <div class="absolute inset-0 opacity-20 bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')]"></div>
                                    <template x-if="item.condition === 'new'">
                                        <div class="relative z-10">
                                            <x-heroicon-o-sparkles class="w-8 h-8 sm:w-10 sm:h-10 text-white/40 group-hover:text-amber transition-colors duration-300 transform group-hover:scale-110" />
                                            <div class="absolute -top-1 -right-1 w-3 h-3 rounded-full bg-emerald-400 shadow-[0_0_10px_rgba(52,211,153,0.8)] ring-2 ring-navy"></div>
                                        </div>
                                    </template>
                                    <template x-if="item.condition !== 'new'">
                                        <div class="relative z-10">
                                            <x-heroicon-o-wrench-screwdriver class="w-8 h-8 sm:w-10 sm:h-10 text-white/40 group-hover:text-amber transition-colors duration-300 transform group-hover:-rotate-12" />
                                            <div class="absolute -top-1 -right-1 w-3 h-3 rounded-full bg-amber flex shadow-[0_0_10px_rgba(245,158,11,0.8)] ring-2 ring-navy"></div>
                                        </div>
                                    </template>
                                </div>

                                {{-- Part Info --}}
                                <div class="flex-1 min-w-0 flex flex-col justify-center text-center sm:text-left w-full">
                                    <div class="flex flex-col sm:flex-row sm:items-center gap-2 mb-1.5 justify-center sm:justify-start">
                                        <h4 class="font-mono text-lg sm:text-xl font-black text-navy tracking-tight leading-none" x-text="item.oem_number"></h4>
                                        <div class="inline-flex items-center">
                                            <span class="inline-block px-2.5 py-1 rounded-[8px] text-[9px] font-black uppercase tracking-[0.1em]"
                                                  :class="{
                                                      'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200': item.condition === 'new',
                                                      'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200': item.condition.includes('used'),
                                                      'bg-purple-100 text-purple-700 ring-1 ring-inset ring-purple-200': item.condition === 'remanufactured',
                                                      'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-200': item.condition === 'aftermarket',
                                                      'bg-gray-100 text-gray-600 ring-1 ring-inset ring-gray-200': !['new','remanufactured','aftermarket'].includes(item.condition) && !item.condition.includes('used')
                                                  }"
                                                  x-text="item.condition.replace(/_/g, ' ')"></span>
                                        </div>
                                    </div>
                                    <p class="text-sm font-bold text-body mb-2 line-clamp-2" x-text="item.name || 'Genuine OEM Part'"></p>
                                    
                                    <div class="flex items-center justify-center sm:justify-start gap-3 mt-auto">
                                         <div class="px-2.5 py-1 bg-gray-50 rounded text-xs font-bold text-navy opacity-70">
                                             €<span x-text="item.price.toFixed(2)"></span> / unit
                                         </div>
                                         <div class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest text-emerald-600">
                                             <x-heroicon-s-check-circle class="w-3.5 h-3.5" />
                                             In Stock
                                         </div>
                                    </div>
                                </div>

                                {{-- Actions (Desktop Right aligned, Mobile bottom) --}}
                                <div class="flex sm:flex-col items-center sm:items-end justify-between sm:justify-center shrink-0 w-full sm:w-auto pt-4 sm:pt-0 border-t sm:border-t-0 border-gray-100">
                                    <div class="text-left sm:text-right mb-0 sm:mb-4">
                                        <p class="font-display text-2xl font-black text-navy tracking-tighter leading-none">€<span x-text="(item.price * item.quantity).toFixed(2)"></span></p>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        {{-- Modern Tactile Stepper --}}
                                        <div class="flex items-center bg-gray-50 rounded-[14px] p-1 border border-gray-200 shadow-inner">
                                            <button @click="decrementItem(item.id)" :disabled="item.quantity <= 1" class="stepper-btn w-8 h-8 rounded-[10px] flex items-center justify-center text-navy/50 disabled:opacity-30 border border-transparent hover:border-gray-300 hover:bg-white hover:shadow-sm">
                                                <x-heroicon-o-minus class="w-4 h-4" />
                                            </button>
                                            <input type="text" x-model.number="item.quantity" @change="updateItemQuantity(item.id, item.quantity)"
                                                   class="w-10 bg-transparent text-center text-sm font-black text-navy border-0 focus:ring-0 p-0">
                                            <button @click="incrementItem(item.id)" :disabled="item.quantity >= 99" class="stepper-btn w-8 h-8 rounded-[10px] flex items-center justify-center text-navy/50 disabled:opacity-30 border border-transparent hover:border-gray-300 hover:bg-white hover:shadow-sm">
                                                <x-heroicon-o-plus class="w-4 h-4" />
                                            </button>
                                        </div>

                                        {{-- Remove Action --}}
                                        <button @click="removeItem(item.id)" class="group w-10 h-10 rounded-[14px] bg-red-50 text-red-400 hover:bg-red-500 hover:text-white transition-all flex items-center justify-center shadow-sm">
                                            <x-heroicon-o-trash class="w-4 h-4 group-hover:scale-110 transition-transform" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Shipping info banner --}}
                <div class="mt-4 p-5 sm:p-6 rounded-[28px] bg-white/60 backdrop-blur-xl border border-white shadow-sm flex flex-col gap-6">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-[16px] bg-amber-50 flex items-center justify-center border border-amber-100 shadow-inner">
                            <x-heroicon-o-globe-europe-africa class="w-6 h-6 text-amber-600" />
                        </div>
                        <div>
                            <h4 class="text-navy font-black text-base">{{ __('Secure EU Shipping') }}</h4>
                            <p class="text-muted font-medium text-xs mt-0.5">{{ __('Fast & tracked delivery across all member states via our trusted partners.') }}</p>
                        </div>
                    </div>
                    
                    <div class="flex flex-wrap gap-3">
                        <!-- DHL -->
                        <div class="flex items-center gap-3 px-4 py-2.5 rounded-[16px] border border-red-100 bg-red-50/30 hover:bg-red-50/60 shadow-sm transition-colors">
                            <div class="w-8 h-8 rounded-xl bg-red-50 flex items-center justify-center text-red-500 shadow-inner"><x-heroicon-o-truck class="w-4 h-4"/></div>
                            <div class="flex flex-col"><span class="text-sm font-black text-red-600 leading-none mb-0.5">DHL</span><span class="text-[9px] font-bold text-slate-500 uppercase">Carrier</span></div>
                        </div>
                        <!-- DPD -->
                        <div class="flex items-center gap-3 px-4 py-2.5 rounded-[16px] border border-blue-100 bg-blue-50/30 hover:bg-blue-50/60 shadow-sm transition-colors">
                            <div class="w-8 h-8 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600 shadow-inner"><x-heroicon-o-truck class="w-4 h-4"/></div>
                            <div class="flex flex-col"><span class="text-sm font-black text-blue-600 leading-none mb-0.5">DPD</span><span class="text-[9px] font-bold text-slate-500 uppercase">Carrier</span></div>
                        </div>
                        <!-- GLS -->
                        <div class="flex items-center gap-3 px-4 py-2.5 rounded-[16px] border border-emerald-100 bg-emerald-50/30 hover:bg-emerald-50/60 shadow-sm transition-colors">
                            <div class="w-8 h-8 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600 shadow-inner"><x-heroicon-o-truck class="w-4 h-4"/></div>
                            <div class="flex flex-col"><span class="text-sm font-black text-emerald-600 leading-none mb-0.5">GLS</span><span class="text-[9px] font-bold text-slate-500 uppercase">Carrier</span></div>
                        </div>
                        <!-- FedEx -->
                        <div class="flex items-center gap-3 px-4 py-2.5 rounded-[16px] border border-purple-100 bg-purple-50/30 hover:bg-purple-50/60 shadow-sm transition-colors">
                            <div class="w-8 h-8 rounded-xl bg-purple-50 flex items-center justify-center text-purple-600 shadow-inner"><x-heroicon-o-truck class="w-4 h-4"/></div>
                            <div class="flex flex-col"><span class="text-sm font-black text-purple-600 leading-none mb-0.5">FedEx</span><span class="text-[9px] font-bold text-slate-500 uppercase">Carrier</span></div>
                        </div>
                        <!-- UPS -->
                        <div class="flex items-center gap-3 px-4 py-2.5 rounded-[16px] border border-gray-200 bg-white/50 hover:bg-white shadow-sm transition-colors">
                            <div class="w-8 h-8 rounded-xl bg-gray-50 border border-gray-100 flex items-center justify-center text-gray-700 shadow-inner"><x-heroicon-o-truck class="w-4 h-4"/></div>
                            <div class="flex flex-col"><span class="text-sm font-black text-gray-800 leading-none mb-0.5">UPS</span><span class="text-[9px] font-bold text-slate-500 uppercase">Carrier</span></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right: Order Summary (4 Cols) - STICKY CONTAINER --}}
            <div class="lg:col-span-4 space-y-6 lg:sticky lg:top-28 lg:h-fit">
                
                {{-- Summary Card --}}
                <div class="bg-white/80 backdrop-blur-xl rounded-[36px] p-6 sm:p-8 border border-white shadow-2xl shadow-navy/5 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-amber/10 rounded-full blur-3xl -mr-16 -mt-16 pointer-events-none"></div>

                    <h3 class="font-display text-2xl font-black text-navy mb-8 flex items-center gap-3">
                        <x-heroicon-o-document-text class="w-6 h-6 text-amber" />
                        {{ __('Order Summary') }}
                    </h3>

                    <div class="space-y-4 mb-8">
                        <div class="flex justify-between items-center text-sm">
                            <span class="font-bold text-muted">{{ __('Subtotal') }}</span>
                            <span class="font-black text-navy">€<span x-text="summary.subtotal_excl_vat.toFixed(2)"></span></span>
                        </div>
                        <template x-if="summary.coupon_code">
                            <div class="flex justify-between items-center text-sm">
                                <span class="font-bold text-amber-600 flex items-center gap-1.5">
                                    <x-heroicon-s-ticket class="w-4 h-4"/>
                                    <span x-text="summary.coupon_code"></span>
                                    <button @click="removeCoupon()" title="Remove" class="text-red-400 hover:text-red-600 transition-colors ml-1">
                                        <x-heroicon-o-x-circle class="w-4 h-4"/>
                                    </button>
                                </span>
                                <span class="font-black text-amber-600">-€<span x-text="summary.coupon_discount.toFixed(2)"></span></span>
                            </div>
                        </template>
                        <div class="flex justify-between items-center text-sm">
                            <span class="font-bold text-muted">VAT (<span x-text="summary.vat_rate"></span>%)</span>
                            <span class="font-black text-navy">€<span x-text="summary.vat_amount.toFixed(2)"></span></span>
                        </div>
                        <div class="flex justify-between items-center text-sm">
                            <span class="font-bold text-muted">{{ __('Shipping') }}</span>
                            <span class="text-[10px] uppercase tracking-widest font-black text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-[6px] ring-1 ring-inset ring-emerald-100">{{ __('Calculated next') }}</span>
                        </div>
                    </div>

                    {{-- Free Shipping Tracker --}}
                    <template x-if="summary.free_shipping_threshold > 0">
                        <div class="mb-8 p-5 rounded-[20px] bg-[#FFF8EB] border border-amber/20 relative overflow-hidden">
                            <div class="flex justify-between items-end mb-3 relative z-10">
                                <span class="text-[10px] font-black uppercase tracking-[0.15em] text-amber">{{ __('Free Shipping') }}</span>
                                <span class="text-xs font-black text-navy">
                                    <template x-if="summary.shipping_needed > 0">
                                        <span>€<span x-text="summary.shipping_needed.toFixed(2)"></span> left</span>
                                    </template>
                                    <template x-if="summary.shipping_needed <= 0">
                                        <span class="text-emerald-600 flex items-center gap-1"><x-heroicon-s-check-circle class="w-4 h-4"/> Qualified</span>
                                    </template>
                                </span>
                            </div>
                            <div class="h-2 w-full bg-amber/10 rounded-full overflow-hidden relative z-10">
                                <div class="h-full bg-gradient-to-r from-amber to-orange-400 transition-all duration-700 ease-out relative"
                                     :style="'width: ' + Math.min(100, (summary.subtotal / summary.free_shipping_threshold) * 100) + '%'">
                                     <div class="absolute inset-0 bg-white/30 animate-shimmer"></div>
                                </div>
                            </div>
                        </div>
                    </template>

                    {{-- Promo Code Field --}}
                    <template x-if="!summary.coupon_code">
                        <div class="mb-6">
                            <label for="promo_code" class="block text-[10px] font-black uppercase tracking-[0.15em] text-navy mb-2">{{ __('Promo Code') }}</label>
                            <div class="relative flex items-center group">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                    <x-heroicon-o-ticket class="w-4 h-4 text-muted group-focus-within:text-amber transition-colors" />
                                </div>
                                <input type="text" id="promo_code" x-model="couponCode" placeholder="{{ __('Enter discount code') }}"
                                       class="block w-full pl-10 pr-24 py-3 bg-gray-50 border border-gray-200 rounded-[14px] text-sm font-mono uppercase text-navy placeholder:text-gray-400 focus:ring-2 focus:ring-amber/30 focus:border-amber transition-all">
                                <button @click="applyCoupon()" :disabled="!couponCode"
                                        class="absolute right-1.5 inset-y-1.5 px-4 rounded-[10px] bg-navy text-white text-[10px] font-black uppercase tracking-widest hover:bg-blue-900 focus:ring-2 focus:ring-offset-1 focus:ring-navy transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                                    {{ __('Apply') }}
                                </button>
                            </div>
                            <template x-if="couponMessage">
                                <p class="mt-2 text-xs font-bold" :class="couponError ? 'text-red-500' : 'text-emerald-600'" x-text="couponMessage"></p>
                            </template>
                        </div>
                    </template>

                    {{-- Total Banner --}}
                    <div class="pt-6 border-t border-gray-100 mb-8">
                        <div class="flex justify-between items-center gap-4">
                             <div class="flex flex-col justify-center mt-1">
                                 <span class="text-[11px] font-black uppercase tracking-[0.2em] text-muted mb-0.5">{{ __('Total Amount') }}</span>
                                 <span class="text-[9px] font-bold uppercase text-emerald-600 tracking-wider">{{ __('Including all taxes') }}</span>
                             </div>
                             <p class="font-display text-4xl sm:text-5xl font-black text-navy tracking-tighter leading-none">€<span x-text="summary.grand_total.toFixed(2)"></span></p>
                        </div>
                    </div>

                    {{-- Action Button --}}
                    <a href="/{{ app()->getLocale() }}/checkout" 
                       class="flex items-center justify-between w-full h-16 px-8 rounded-[20px] bg-gradient-to-r from-amber via-orange-400 to-amber bg-[length:200%_auto] text-navy font-black text-sm uppercase tracking-[0.1em] shadow-xl shadow-amber/30 hover:shadow-2xl hover:shadow-amber/40 hover:-translate-y-1 hover:bg-[position:right_center] transition-all duration-300 group">
                        <span>{{ __('Checkout Now') }}</span>
                        <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center group-hover:scale-110 transition-transform">
                             <x-heroicon-o-arrow-right class="w-4 h-4 text-navy transition-transform group-hover:translate-x-0.5" />
                        </div>
                    </a>

                    <div class="mt-8 pt-4 border-t border-gray-100">
                        <div class="flex items-center justify-center gap-6">
                            <div class="flex items-center gap-1.5 opacity-50 grayscale hover:grayscale-0 hover:opacity-100 transition-all">
                                 <x-heroicon-s-shield-check class="w-4 h-4 text-emerald-500" />
                                 <span class="text-[10px] font-bold text-navy uppercase tracking-widest">Secure</span>
                            </div>
                            <div class="flex items-center gap-1.5 opacity-50 grayscale hover:grayscale-0 hover:opacity-100 transition-all">
                                 <x-heroicon-s-credit-card class="w-4 h-4 text-navy" />
                                 <span class="text-[10px] font-bold text-navy uppercase tracking-widest">Stripe</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Helpful info Card --}}
                <div class="bg-white/60 backdrop-blur-xl rounded-[28px] p-6 border border-white shadow-lg shadow-navy/5 relative overflow-hidden group hover:bg-white/80 transition-colors">
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center shrink-0">
                            <x-heroicon-o-chat-bubble-left-ellipsis class="w-5 h-5 text-blue-500" />
                        </div>
                        <div>
                            <h5 class="text-xs font-black text-navy uppercase tracking-[0.1em] mb-1.5">{{ __('Need help?') }}</h5>
                            <p class="text-xs text-muted mb-4 font-medium leading-relaxed">{{ __('Our customer support is available Mon-Fri 09:00 - 18:00 to help with your order.') }}</p>
                            <a href="/{{ app()->getLocale() }}/contact" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-navy to-blue-900 text-xs font-bold text-white hover:shadow-lg hover:shadow-navy/20 transition-all group">
                                <x-heroicon-o-envelope class="w-4 h-4" />
                                <span>{{ __('Contact Us') }}</span>
                                <x-heroicon-s-arrow-right class="w-3 h-3 group-hover:translate-x-1 transition-transform ml-1" />
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Mobile Sticky Total Bar --}}
    <div 
        x-show="cart.items.length > 0" x-cloak
        class="lg:hidden fixed bottom-0 inset-x-0 bg-white/90 backdrop-blur-3xl border-t border-white/50 px-6 py-5 z-40 shadow-[0_-20px_40px_rgba(11,58,104,0.08)]"
        x-transition:enter="transition ease-out duration-300 transform"
        x-transition:enter-start="translate-y-full"
        x-transition:enter-end="translate-y-0"
    >
        <div class="flex items-center justify-between gap-6 max-w-lg mx-auto">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.15em] text-muted mb-0.5">{{ __('Total Amount') }}</p>
                <p class="text-2xl font-black text-navy tracking-tight leading-none">€<span x-text="summary.grand_total.toFixed(2)"></span></p>
            </div>
            <a href="/{{ app()->getLocale() }}/checkout" class="flex-1 flex items-center justify-center gap-2 h-14 rounded-2xl bg-gradient-to-r from-amber to-orange-500 text-navy text-sm font-black uppercase tracking-[0.1em] shadow-lg shadow-amber/30 hover:shadow-xl active:scale-95 transition-all">
                {{ __('Checkout') }}
                <x-heroicon-s-arrow-right class="w-4 h-4" />
            </a>
        </div>
    </div>
</div>

<script>
function cartData() {
    const serverCart    = @json($cart);
    const serverSummary = @json($summary);

    function mapItem(item) {
        const lang = '{{ app()->getLocale() }}';
        let itemName = item.product.name;
        if (typeof itemName === 'object' && itemName !== null) {
            itemName = itemName[lang] || itemName['en'] || Object.values(itemName)[0];
        }

        return {
            id:           item.id,
            quantity:     item.quantity,
            price:        parseFloat(item.price_at_add),
            oldPrice:     item.old_price ? parseFloat(item.old_price) : null,
            priceChanged: !!(item.old_price && Math.abs(parseFloat(item.old_price) - parseFloat(item.price_at_add)) > 0.01),
            priceBlocked: item.block_checkout || false,
            oem_number:   item.product.oem_number,
            name:         itemName,
            condition:    item.product.condition || 'new',
            removing:     false,
        };
    }

    return {
        cart:         { items: serverCart.items.map(mapItem) },
        summary:      { ...serverSummary },
        loading:      false,
        errorMessage: '',
        couponCode:   '',
        couponMessage:'',
        couponError:  false,

        async applyCoupon() {
            if (!this.couponCode) return;
            this.couponError = false;
            this.couponMessage = 'Applying...';
            try {
                const res = await fetch(`/{{ app()->getLocale() }}/cart/coupon/apply`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ coupon_code: this.couponCode })
                });
                const data = await res.json();
                if (data.success) {
                    this.couponMessage = '';
                    this.couponCode = '';
                    this.summary = { ...data.cart_summary };
                } else {
                    this.couponError = true;
                    this.couponMessage = data.message || 'Invalid promo code';
                }
            } catch (e) {
                this.couponError = true;
                this.couponMessage = 'Connection error';
            }
            if (this.couponMessage) setTimeout(() => this.couponMessage = '', 4000);
        },

        async removeCoupon() {
            try {
                const res = await fetch(`/{{ app()->getLocale() }}/cart/coupon/remove`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    }
                });
                const data = await res.json();
                if (data.success) {
                    this.summary = { ...data.cart_summary };
                }
            } catch (e) {
                this.showError('Error removing coupon');
            }
        },

        async incrementItem(itemId) {
            const item = this.cart.items.find(i => i.id === itemId);
            if (item && item.quantity < 99) await this.updateItem(itemId, item.quantity + 1);
        },

        async decrementItem(itemId) {
            const item = this.cart.items.find(i => i.id === itemId);
            if (item && item.quantity > 1) await this.updateItem(itemId, item.quantity - 1);
        },

        async updateItemQuantity(itemId, quantity) {
            const qty = Math.max(1, Math.min(99, parseInt(quantity) || 1));
            await this.updateItem(itemId, qty);
        },

        async updateItem(itemId, quantity) {
            try {
                const res = await fetch(`/{{ app()->getLocale() }}/cart/update/${itemId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ quantity })
                });
                if (res.ok) {
                    await this.loadCart();
                } else {
                    this.showError('Error updating cart');
                }
            } catch (e) {
                this.showError('Connection error');
            }
        },

        async removeItem(itemId) {
            const item = this.cart.items.find(i => i.id === itemId);
            if (!item) return;
            item.removing = true;
            await new Promise(r => setTimeout(r, 400));
            try {
                const res = await fetch(`/{{ app()->getLocale() }}/cart/remove/${itemId}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    }
                });
                if (res.ok) {
                    await this.loadCart();
                } else {
                    item.removing = false;
                    this.showError('Error removing item');
                }
            } catch (e) {
                item.removing = false;
                this.showError('Connection error');
            }
        },

        async clearCart() {
            if (!confirm('Are you sure you want to empty your cart?')) return;
            const ids = [...this.cart.items.map(i => i.id)];
            for (const id of ids) {
                await fetch(`/{{ app()->getLocale() }}/cart/remove/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    }
                });
            }
            await this.loadCart();
        },

        async loadCart() {
            this.loading = true;
            try {
                const res  = await fetch('/{{ app()->getLocale() }}/cart/preview');
                const data = await res.json();
                if (data.success) {
                    this.cart.items = data.items.map(mapItem);
                    this.summary    = { ...data.summary };
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Cart updated', type: 'info' } }));
                }
            } catch (e) {
                this.showError('Error loading cart');
            } finally {
                this.loading = false;
            }
        },

        showError(msg) {
            this.errorMessage = msg;
            setTimeout(() => { this.errorMessage = ''; }, 5000);
        },
        
        hasBlockedItems() {
            return this.cart.items.some(i => i.priceBlocked);
        }
    };
}
</script>
@endsection
