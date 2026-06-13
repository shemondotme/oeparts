@extends('layouts.app')

@section('title', ui_copy('cart_title', 'cart.title'))

@section('meta_robots')<meta name="robots" content="noindex, nofollow">@endsection

@section('content')
@php
    $cartLocale = app()->getLocale();
    $cartUpdateRoute = url('/'.$cartLocale.'/cart/update');
    $cartRemoveRoute = url('/'.$cartLocale.'/cart/remove');
    $cartPreviewRoute = route('frontend.cart.preview', ['lang' => $cartLocale]);
    $cartCouponApplyRoute = route('frontend.cart.coupon.apply', ['lang' => $cartLocale]);
    $cartCouponRemoveRoute = route('frontend.cart.coupon.remove', ['lang' => $cartLocale]);
@endphp
<div x-data="cartData(@json($cart), @json($summary), '{{ $cartLocale }}', '{{ $cartUpdateRoute }}', '{{ $cartRemoveRoute }}', '{{ $cartPreviewRoute }}', '{{ $cartCouponApplyRoute }}', '{{ $cartCouponRemoveRoute }}')" class="relative min-h-screen bg-ivory text-ink pt-10 pb-28">
    <div class="fixed inset-0 bg-grid-ivory-fine bg-grid-md opacity-40 pointer-events-none" aria-hidden="true"></div>

    {{-- ── Clear Cart Confirm Modal ── --}}
    <div x-show="confirmOpen" x-cloak x-trap.noscroll="confirmOpen"
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         role="dialog" aria-modal="true" aria-labelledby="clear-cart-title"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
        <div class="absolute inset-0 bg-ink/70" @click="confirmOpen = false"></div>
        <div class="relative bg-paper border border-ink max-w-md w-full z-10"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">
            <div class="flex items-center justify-between px-6 py-3 border-b border-ink bg-ivory-alt">
                <span class="bp-spec text-ink">Confirm · Destructive</span>
                <button @click="confirmOpen = false" class="text-ink-muted hover:text-ink">
                    <x-heroicon-s-x-mark class="w-4 h-4" />
                </button>
            </div>
            <div class="p-6 md:p-8">
                <h3 id="clear-cart-title" class="font-display text-2xl font-extrabold text-ink tracking-tight mb-3">
                    Clear Cart<span class="text-amber">?</span>
                </h3>
                <p class="text-body mb-8">All items will be removed from your cart. This cannot be undone.</p>
                <div class="flex gap-3">
                    <button @click="confirmOpen = false" class="flex-1 bp-btn-outline justify-center">
                        Cancel
                    </button>
                    <button @click="confirmClearCart()"
                            class="flex-1 inline-flex items-center justify-center gap-2 px-5 py-3 border border-red-600 bg-red-600 text-ivory
                                   font-mono text-xs font-bold uppercase tracking-[0.22em] hover:bg-red-700 transition-colors">
                        <x-heroicon-s-trash class="w-4 h-4" />
                        Clear Cart
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="relative max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-10">

        {{-- ── Document header ── --}}
        <div class="flex flex-wrap items-center justify-between gap-4 pb-4 mb-6 border-b border-rule">
            <nav class="flex items-center gap-2 bp-spec-mono">
                <a href="{{ url('/'.app()->getLocale().'/') }}" class="hover:text-amber-ink transition-colors">Home</a>
                <span class="text-rule-strong">/</span>
                <span class="text-ink">{{ ui_copy('cart_title', 'cart.title') }}</span>
            </nav>
            <span class="bp-spec-mono">
                DOC · ORDER-WORKSHEET · CART
            </span>
        </div>

        {{-- ── 1. Page Heading + step strip ── --}}
        <div class="grid grid-cols-12 gap-x-4 sm:gap-x-6 lg:gap-x-8 items-end pb-8 mb-8 border-b border-ink">
            <div class="col-span-12 md:col-span-7">
                <div class="flex items-center gap-4 mb-6">
                    <span class="w-10 h-[3px] bg-amber inline-block"></span>
                    <span class="bp-spec text-amber-ink">01 · Review Order</span>
                </div>
                <h1 class="font-display font-extrabold text-ink leading-[0.95] tracking-[-0.03em]
                           text-4xl sm:text-5xl lg:text-6xl">
                    {{ ui_copy('cart_title', 'cart.title') }}<span class="text-amber">.</span>
                </h1>
                <template x-if="summary.item_count > 0">
                    <p class="mt-6 font-mono text-sm tabular-nums text-body">
                        <span class="text-ink font-bold" x-text="summary.item_count"></span>
                        <span x-text="summary.item_count === 1 ? 'item' : 'items'"></span>
                        <span class="text-ink-muted">· ready for checkout</span>
                    </p>
                </template>
            </div>

            {{-- Step breadcrumb (numbered) --}}
            <div x-show="cart.items.length > 0" class="col-span-12 md:col-span-5">
                <div class="border border-ink">
                    <div class="grid grid-cols-3 text-center">
                        <div class="px-3 py-3 bg-ink text-ivory">
                            <p class="font-mono text-[9px] tracking-[0.2em] uppercase text-amber mb-1">Step 01</p>
                            <p class="font-mono text-[11px] font-bold uppercase tracking-[0.18em]">{{ ui_copy('cart_step_cart', 'cart.step_cart') }}</p>
                        </div>
                        <div class="px-3 py-3 border-l border-ink bg-paper text-ink-muted">
                            <p class="font-mono text-[9px] tracking-[0.2em] uppercase mb-1">Step 02</p>
                            <p class="font-mono text-[11px] font-bold uppercase tracking-[0.18em]">{{ ui_copy('cart_step_shipping', 'cart.step_shipping') }}</p>
                        </div>
                        <div class="px-3 py-3 border-l border-ink bg-paper text-ink-muted">
                            <p class="font-mono text-[9px] tracking-[0.2em] uppercase mb-1">Step 03</p>
                            <p class="font-mono text-[11px] font-bold uppercase tracking-[0.18em]">{{ ui_copy('cart_step_payment', 'cart.step_payment') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── 2. Empty State ── --}}
        <div x-show="cart.items.length === 0" x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             class="bp-card p-10 md:p-16 text-center">
            <span class="bp-spec text-amber-ink block mb-4">Report · Cart-Empty</span>
            <div class="inline-flex w-16 h-16 border-2 border-ink items-center justify-center mb-8">
                <x-heroicon-o-shopping-bag class="w-7 h-7 text-ink" />
            </div>
            <h2 class="font-display text-4xl md:text-5xl font-extrabold text-ink tracking-tight mb-4 text-balance">
                {{ ui_copy('cart_empty_title', 'cart.empty_title') }}<span class="text-amber">.</span>
            </h2>
            <p class="text-body max-w-lg mx-auto mb-10">{{ ui_copy('cart_empty_subtitle', 'cart.empty_subtitle') }}</p>
            <div class="flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('frontend.search.console', ['lang' => app()->getLocale()]) }}" class="bp-btn-primary">
                    <x-heroicon-s-magnifying-glass class="w-5 h-5" />
                    {{ ui_copy('cart_empty_browse_btn', 'cart.empty_browse_btn') }}
                </a>
                <a href="{{ url('/'.app()->getLocale().'/contact') }}"
                   class="inline-flex items-center gap-1.5 font-mono text-[10px] font-bold uppercase tracking-[0.22em] text-ink-muted hover:text-ink transition-colors">
                    <x-heroicon-s-chat-bubble-left-ellipsis class="w-3 h-3" />
                    {{ __('Need help finding a part?') }}
                </a>
            </div>

            @if($popularOems->isNotEmpty())
            <div class="mt-10 pt-8 border-t border-rule">
                <p class="bp-spec text-ink-muted mb-5">Popular · Indexed</p>
                <div class="flex flex-wrap items-center justify-center gap-2">
                    @foreach($popularOems as $oem)
                    <a href="{{ url('/'.app()->getLocale().'/parts/'.$oem) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-rule-strong bg-paper
                              font-mono text-sm font-bold tabular-nums text-ink
                              hover:bg-ink hover:text-ivory hover:border-ink transition-colors">
                        {{ $oem }}
                        <x-heroicon-s-arrow-long-right class="w-3 h-3" />
                    </a>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- ── 3. Cart Content ── --}}
        <div x-show="cart.items.length > 0" x-cloak class="grid grid-cols-12 gap-x-4 sm:gap-x-6 lg:gap-x-10 items-start">

            {{-- Left: Items --}}
            <div class="col-span-12 lg:col-span-8">

                {{-- Error banner --}}
                <div x-show="errorMessage" x-cloak
                     class="mb-4 flex items-start gap-3 p-4 border border-red-600 bg-red-50">
                    <x-heroicon-s-exclamation-triangle class="w-5 h-5 text-red-600 shrink-0 mt-0.5" />
                    <span class="flex-1 font-mono text-[11px] uppercase tracking-wider text-red-700" x-text="errorMessage"></span>
                    <button @click="errorMessage = ''" class="text-red-600 hover:text-red-800">
                        <x-heroicon-s-x-mark class="w-4 h-4" />
                    </button>
                </div>

                {{-- Section header --}}
                <div class="flex items-center justify-between mb-4">
                    <span class="bp-spec text-amber-ink">01.a · Order items</span>
                    <button @click="confirmOpen = true"
                            class="inline-flex items-center gap-1.5 font-mono text-[10px] font-bold uppercase tracking-[0.22em] text-ink-muted hover:text-red-600 transition-colors">
                        <x-heroicon-s-trash class="w-3 h-3" />
                        {{ ui_copy('cart_clear_cart', 'cart.clear_cart') }}
                    </button>
                </div>

                {{-- Items ledger --}}
                <div class="border border-ink bg-paper">
                    {{-- Column header --}}
                    <div class="hidden sm:grid grid-cols-12 gap-3 px-5 py-3 border-b border-ink bg-ivory-alt
                                font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink">
                        <span class="col-span-5">Item · OEM</span>
                        <span class="col-span-2 text-center">Unit {{ settings('store.currency_symbol', '€') }}</span>
                        <span class="col-span-2 text-center">Qty</span>
                        <span class="col-span-2 text-right">Subtotal {{ settings('store.currency_symbol', '€') }}</span>
                        <span class="col-span-1 text-right">Act</span>
                    </div>

                    <template x-for="(item, index) in cart.items" :key="item.id">
                        <div class="border-b border-rule last:border-b-0 transition-all"
                             x-bind:class="item.removing ? 'opacity-0 -translate-x-8' : 'opacity-100'"
                             style="transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);">

                            {{-- ── MOBILE: Stacked Card ── --}}
                            <div class="block sm:hidden p-4 space-y-3">
                                {{-- Row 1: OEM + condition + remove --}}
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex items-center gap-2 min-w-0 flex-1">
                                        <span class="font-mono text-sm font-bold text-ink tabular-nums truncate"
                                              x-text="item.oem_number"></span>
                                        <span class="shrink-0 inline-flex items-center px-1.5 py-0.5 bp-spec-mono font-bold rounded-sm text-[9px]"
                                              :style="`background-color: ${item.condition_bg}; color: ${item.condition_text};`"
                                              x-text="item.condition_name"></span>
                                    </div>
                                    <button @click="removeItem(item.id)"
                                            class="shrink-0 w-7 h-7 flex items-center justify-center border border-rule-strong text-ink-muted hover:bg-red-600 hover:text-ivory hover:border-red-600 transition-colors">
                                        <x-heroicon-s-trash class="w-3 h-3" />
                                    </button>
                                </div>
                                {{-- Row 2: product name --}}
                                <p class="text-xs text-body line-clamp-2" x-text="item.name || 'Genuine OEM Part'"></p>
                                {{-- Row 3: stock + unit price --}}
                                <div class="flex items-center justify-between gap-2">
                                    <p class="font-mono text-[9px] tracking-[0.18em] uppercase"
                                       :class="item.in_stock ? 'text-amber-ink' : 'text-red-600'"
                                       x-text="item.in_stock ? '· In stock' : '· Out of stock'"></p>
                                    <span class="font-mono text-sm tabular-nums text-ink-muted">{{ settings('store.currency_symbol', '€') }}<span x-text="item.price.toFixed(2)"></span> <span class="text-[9px] uppercase tracking-[0.18em]">ea.</span></span>
                                </div>
                                {{-- Row 4: qty stepper + subtotal --}}
                                <div class="flex items-center justify-between gap-3 pt-2 border-t border-rule">
                                    <div class="inline-flex items-center border border-ink">
                                        <button @click="decrementItem(item.id)"
                                                :disabled="item.quantity <= 1"
                                                aria-label="Decrease quantity"
                                                class="w-8 h-8 flex items-center justify-center text-ink hover:bg-ink hover:text-ivory disabled:opacity-30 transition-colors">
                                            <x-heroicon-s-minus class="w-3 h-3" />
                                        </button>
                                        <input type="text" aria-label="Item quantity"
                                               x-model.number="item.quantity"
                                               @change="updateItemQuantity(item.id, item.quantity)"
                                               class="w-10 h-8 text-center font-mono text-xs font-bold text-ink bg-paper border-0 border-x border-ink focus:ring-0 focus:outline-none p-0">
                                        <button @click="incrementItem(item.id)"
                                                :disabled="item.quantity >= 99"
                                                aria-label="Increase quantity"
                                                class="w-8 h-8 flex items-center justify-center text-ink hover:bg-ink hover:text-ivory disabled:opacity-30 transition-colors">
                                            <x-heroicon-s-plus class="w-3 h-3" />
                                        </button>
                                    </div>
                                    <div class="text-right">
                                        <p class="bp-spec-mono text-[9px] mb-0.5">Subtotal</p>
                                        <p class="font-mono text-base font-bold text-ink tabular-nums leading-none">
                                            {{ settings('store.currency_symbol', '€') }}<span x-text="(item.price * item.quantity).toFixed(2)"></span>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {{-- ── DESKTOP: Grid row ── --}}
                            <div class="hidden sm:grid grid-cols-12 gap-3 px-5 py-4 items-center"
                                 x-bind:class="item.removing ? 'opacity-0 -translate-x-8' : 'opacity-100'">

                                {{-- OEM + name + condition --}}
                                <div class="col-span-5 flex items-start gap-3 min-w-0">
                                    <span class="font-mono text-[10px] tabular-nums text-ink-muted shrink-0 mt-1.5"
                                          x-text="String(index + 1).padStart(3, '0')"></span>
                                    <div class="w-10 h-10 border border-rule bg-ivory-alt flex items-center justify-center shrink-0">
                                        <template x-if="item.condition_slug === 'new'">
                                            <x-heroicon-o-sparkles class="w-4 h-4 text-ink" />
                                        </template>
                                        <template x-if="item.condition_slug !== 'new'">
                                            <x-heroicon-o-wrench-screwdriver class="w-4 h-4 text-ink" />
                                        </template>
                                    </div>
                                    <div class="min-w-0 flex-1" x-data="clipboard()">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-mono text-sm font-bold text-ink tabular-nums truncate cursor-pointer"
                                                  @click="copy(item.oem_number)" title="Copy OEM number"
                                                  x-text="item.oem_number"></span>
                                            <span class="inline-flex items-center px-1.5 py-0.5 bp-spec-mono font-bold rounded-sm"
                                                  :style="`background-color: ${item.condition_bg}; color: ${item.condition_text};`"
                                                  x-text="item.condition_name"></span>
                                        </div>
                                        <span x-show="copied" x-cloak x-transition
                                              class="text-[10px] font-mono font-bold text-emerald-600">Copied</span>
                                        <p class="text-xs text-body line-clamp-2 mt-0.5" x-text="item.name || 'Genuine OEM Part'"></p>
                                        <p class="mt-1 font-mono text-[9px] tracking-[0.18em] uppercase"
                                           :class="item.in_stock ? 'text-amber-ink' : 'text-red-600'"
                                           x-text="item.in_stock ? '· In stock' : '· Out of stock'"></p>
                                    </div>
                                </div>

                                {{-- Unit price --}}
                                <div class="col-span-2 text-center">
                                    <span class="font-mono text-sm tabular-nums text-ink">{{ settings('store.currency_symbol', '€') }}<span x-text="item.price.toFixed(2)"></span></span>
                                </div>

                                {{-- Qty stepper --}}
                                <div class="col-span-2 flex justify-center">
                                    <div class="inline-flex items-center border border-ink">
                                        <button @click="decrementItem(item.id)"
                                                :disabled="item.quantity <= 1"
                                                aria-label="Decrease quantity"
                                                class="w-8 h-8 flex items-center justify-center text-ink hover:bg-ink hover:text-ivory disabled:opacity-30 transition-colors">
                                            <x-heroicon-s-minus class="w-3 h-3" />
                                        </button>
                                        <input type="text" aria-label="Item quantity"
                                               x-model.number="item.quantity"
                                               @change="updateItemQuantity(item.id, item.quantity)"
                                               class="w-10 h-8 text-center font-mono text-xs font-bold text-ink bg-paper border-0 border-x border-ink focus:ring-0 focus:outline-none p-0">
                                        <button @click="incrementItem(item.id)"
                                                :disabled="item.quantity >= 99"
                                                aria-label="Increase quantity"
                                                class="w-8 h-8 flex items-center justify-center text-ink hover:bg-ink hover:text-ivory disabled:opacity-30 transition-colors">
                                            <x-heroicon-s-plus class="w-3 h-3" />
                                        </button>
                                    </div>
                                </div>

                                {{-- Subtotal --}}
                                <div class="col-span-2 text-right">
                                    <p class="font-mono text-base font-bold text-ink tabular-nums leading-none">
                                        {{ settings('store.currency_symbol', '€') }}<span x-text="(item.price * item.quantity).toFixed(2)"></span>
                                    </p>
                                </div>

                                {{-- Remove --}}
                                <div class="col-span-1 flex justify-end">
                                    <button @click="removeItem(item.id)"
                                            aria-label="Remove item"
                                            class="w-8 h-8 flex items-center justify-center border border-rule-strong text-ink-muted
                                                   hover:bg-red-600 hover:text-ivory hover:border-red-600 transition-colors">
                                        <x-heroicon-s-trash class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Shipping carriers info strip --}}
                <div class="mt-6 border border-ink bg-paper">
                    <div class="flex items-center justify-between px-5 py-3 border-b border-rule bg-ivory-alt">
                        <span class="bp-spec text-ink">01.b · EU shipping · carriers</span>
                        <span class="bp-spec-mono">27 countries</span>
                    </div>
                    <div class="grid grid-cols-3 divide-x divide-rule">
                        @foreach(['DHL', 'DPD', 'GLS'] as $carrier)
                        <div class="p-4 flex flex-col items-center gap-2 text-center">
                            <div class="w-8 h-8 border border-rule flex items-center justify-center">
                                <x-heroicon-o-truck class="w-4 h-4 text-ink" />
                            </div>
                            <span class="font-display text-base font-bold text-ink tracking-tight">{{ $carrier }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Right: Order Summary --}}
            <aside class="col-span-12 lg:col-span-4 lg:sticky lg:top-28 mt-8 lg:mt-0">
                <div class="border border-ink bg-paper">

                    {{-- Header --}}
                    <div class="flex items-center justify-between px-5 py-3 border-b border-ink bg-ivory-alt">
                        <span class="bp-spec text-amber-ink">02 · Order summary</span>
                        <span class="bp-spec-mono">{{ settings('store.currency', 'EUR') }}</span>
                    </div>

                    {{-- Corner register marks --}}
                    <div class="relative p-6">
                        <span class="absolute -top-px left-2 w-3 h-3 border-l-2 border-t-2 border-amber" aria-hidden="true"></span>
                        <span class="absolute -top-px right-2 w-3 h-3 border-r-2 border-t-2 border-amber" aria-hidden="true"></span>

                        {{-- Price change warnings --}}
                        <template x-if="summary.price_changes && summary.price_changes.length > 0">
                            <div class="border border-amber-400 bg-amber-50 p-4 mb-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <x-heroicon-s-exclamation-triangle class="w-4 h-4 text-amber-600 shrink-0" />
                                    <span class="font-mono text-[10px] font-bold uppercase tracking-[0.22em] text-amber-700">Price Updated</span>
                                </div>
                                <template x-for="change in summary.price_changes" :key="change.item.id">
                                    <p class="text-xs text-amber-800 mt-1">
                                        <span x-text="change.item.product?.oem_number || 'Item'"></span> — price changed from
                                        <span class="font-bold line-through" x-text="'{{ settings('store.currency_symbol', '€') }}' + change.old_price.toFixed(2)"></span>
                                        to
                                        <span class="font-bold" x-text="'{{ settings('store.currency_symbol', '€') }}' + change.current_price.toFixed(2)"></span>
                                        (<span x-text="'+' + change.change_percent.toFixed(1) + '%'"></span>)
                                    </p>
                                </template>
                            </div>
                        </template>

                        {{-- Line items --}}
                        <dl class="space-y-0 border-t border-rule">
                            <div class="flex items-baseline justify-between gap-3 py-3 border-b border-rule">
                                <dt class="bp-spec-mono">Subtotal</dt>
                                <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                                <dd class="font-mono text-sm font-bold tabular-nums text-ink">{{ settings('store.currency_symbol', '€') }}<span x-text="summary.subtotal_excl_vat.toFixed(2)"></span></dd>
                            </div>
                            <template x-if="summary.coupon_code">
                                <div class="flex items-baseline justify-between gap-3 py-3 border-b border-rule">
                                    <dt class="inline-flex items-center gap-1.5 font-mono text-[10px] tracking-[0.22em] uppercase text-amber-ink">
                                        <x-heroicon-s-ticket class="w-3 h-3" />
                                        <span x-text="summary.coupon_code"></span>
                                        <button @click="removeCoupon()" title="Remove" class="text-red-600 hover:text-red-800 ml-1">
                                            <x-heroicon-s-x-mark class="w-3 h-3" />
                                        </button>
                                    </dt>
                                    <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                                    <dd class="font-mono text-sm font-bold tabular-nums text-amber-ink">-{{ settings('store.currency_symbol', '€') }}<span x-text="summary.coupon_discount.toFixed(2)"></span></dd>
                                </div>
                            </template>
                            <div class="flex items-baseline justify-between gap-3 py-3 border-b border-rule">
                                <dt class="bp-spec-mono">
                                    VAT · <span x-text="summary.vat_rate"></span>%
                                </dt>
                                <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                                <dd class="font-mono text-sm font-bold tabular-nums text-ink">{{ settings('store.currency_symbol', '€') }}<span x-text="summary.vat_amount.toFixed(2)"></span></dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-3 py-3">
                                <dt class="bp-spec-mono">Shipping</dt>
                                <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                                <dd class="font-mono text-[10px] font-bold uppercase tracking-[0.2em] text-amber-ink">Calculated next</dd>
                            </div>
                        </dl>

                        {{-- Free shipping progress --}}
                        <template x-if="summary.free_shipping_threshold > 0">
                            <div class="mt-6 p-4 border border-rule bg-ivory-alt">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="bp-spec text-amber-ink">Free shipping</span>
                                    <template x-if="summary.shipping_needed > 0">
                                        <span class="font-mono text-xs font-bold tabular-nums text-ink">{{ settings('store.currency_symbol', '€') }}<span x-text="summary.shipping_needed.toFixed(2)"></span> left</span>
                                    </template>
                                    <template x-if="summary.shipping_needed <= 0">
                                        <span class="inline-flex items-center gap-1 font-mono text-[10px] font-bold uppercase tracking-[0.2em] text-amber-ink">
                                            <x-heroicon-s-check class="w-3 h-3" />
                                            Qualified
                                        </span>
                                    </template>
                                </div>
                                <div class="h-1.5 w-full bg-paper border border-rule-strong">
                                    <div class="h-full bg-amber transition-all duration-700 ease-out"
                                         :style="'width: ' + Math.min(100, (summary.subtotal / summary.free_shipping_threshold) * 100) + '%'"></div>
                                </div>
                            </div>
                        </template>

                        {{-- Promo code --}}
                        <template x-if="!summary.coupon_code">
                            <div class="mt-6">
                                <label for="promo_code" class="bp-spec mb-2 inline-block">Promo code</label>
                                <div class="flex border border-ink focus-within:border-amber transition-colors">
                                    <input type="text" id="promo_code" x-model="couponCode" placeholder="Enter code"
                                           class="flex-1 px-3 py-2.5 bg-paper font-mono text-sm uppercase text-ink
                                                  placeholder:font-sans placeholder:text-ink-muted placeholder:normal-case
                                                  border-0 focus:outline-none focus:ring-0">
                                    <button @click="applyCoupon()" :disabled="!couponCode"
                                            class="px-4 py-2.5 bg-ink text-ivory font-mono text-[10px] font-bold uppercase tracking-[0.22em]
                                                   hover:bg-amber hover:text-ink transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                                        Apply
                                    </button>
                                </div>
                                <template x-if="couponMessage">
                                    <p class="mt-2 font-mono text-[10px] uppercase tracking-wider"
                                       :class="couponError ? 'text-red-600' : 'text-amber-ink'"
                                       x-text="couponMessage"></p>
                                </template>
                            </div>
                        </template>

                        {{-- Grand total --}}
                        <div class="mt-6 pt-5 border-t-2 border-ink">
                            <div class="flex items-baseline justify-between gap-3 mb-2">
                                <span class="font-mono text-[10px] font-bold uppercase tracking-[0.22em] text-ink">Total · {{ settings('store.currency', 'EUR') }}</span>
                                <p class="font-mono text-4xl sm:text-5xl font-medium text-ink tabular-nums leading-none tracking-tight">
                                    {{ settings('store.currency_symbol', '€') }}<span x-text="summary.grand_total.toFixed(2)"></span>
                                </p>
                            </div>
                            <p class="text-right font-mono text-[9px] tracking-[0.2em] uppercase text-ink-muted">
                                Including all taxes
                            </p>
                        </div>

                        {{-- Checkout CTA --}}
                        <a href="{{ url('/'.app()->getLocale().'/checkout') }}"
                           class="mt-6 bp-btn-primary w-full justify-center">
                            Checkout now
                            <x-heroicon-s-arrow-long-right class="w-5 h-5" />
                        </a>

                        {{-- Trust badges --}}
                        <div class="mt-5 pt-4 border-t border-rule flex flex-wrap items-center justify-between gap-2">
                            <span class="inline-flex items-center gap-1.5 bp-spec-mono">
                                <x-heroicon-s-shield-check class="w-3 h-3 text-amber-ink" />
                                SSL · TLS 1.3
                            </span>
                            <span class="inline-flex items-center gap-1.5 bp-spec-mono">
                                <x-heroicon-s-credit-card class="w-3 h-3 text-amber-ink" />
                                Airwallex
                            </span>
                        </div>

                        <span class="absolute -bottom-px left-2 w-3 h-3 border-l-2 border-b-2 border-amber" aria-hidden="true"></span>
                        <span class="absolute -bottom-px right-2 w-3 h-3 border-r-2 border-b-2 border-amber" aria-hidden="true"></span>
                    </div>
                </div>

                {{-- Support card --}}
                <div class="mt-4 border border-rule bg-paper">
                    <div class="flex items-start gap-3 p-5">
                        <div class="w-8 h-8 border border-ink flex items-center justify-center shrink-0 bg-paper">
                            <x-heroicon-o-chat-bubble-left-ellipsis class="w-4 h-4 text-ink" />
                        </div>
                        <div class="flex-1">
                            <p class="bp-spec text-amber-ink mb-1">Need help?</p>
                            <p class="text-xs text-body mb-3">Mon-Fri 09:00-18:00. Our support team is ready to assist.</p>
                            <a href="{{ url('/'.app()->getLocale().'/contact') }}"
                               class="inline-flex items-center gap-1.5 font-mono text-[10px] font-bold uppercase tracking-[0.22em] text-ink hover:text-amber-ink transition-colors">
                                <x-heroicon-s-envelope class="w-3 h-3" />
                                Contact us
                                <x-heroicon-s-arrow-long-right class="w-3 h-3" />
                            </a>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    {{-- Mobile sticky total --}}
    <div x-show="cart.items.length > 0" x-cloak
         class="lg:hidden fixed bottom-0 inset-x-0 bg-paper border-t-2 border-ink px-4 py-3 z-40"
         x-transition:enter="transition ease-out duration-200 transform"
         x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0">
        <div class="flex items-center justify-between gap-4 max-w-lg mx-auto">
            <div>
                <p class="font-mono text-[9px] tracking-[0.22em] uppercase text-ink-muted mb-0.5">Total · {{ settings('store.currency', 'EUR') }}</p>
                <p class="font-mono text-2xl font-bold text-ink tabular-nums leading-none">
                    {{ settings('store.currency_symbol', '€') }}<span x-text="summary.grand_total.toFixed(2)"></span>
                </p>
            </div>
            <a href="{{ url('/'.app()->getLocale().'/checkout') }}" class="bp-btn-primary flex-1 justify-center">
                Checkout
                <x-heroicon-s-arrow-long-right class="w-4 h-4" />
            </a>
        </div>
    </div>
</div>

@endsection
