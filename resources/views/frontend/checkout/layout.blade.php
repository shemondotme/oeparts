@extends('layouts.app')

@section('title', ui_copy('checkout_title', 'checkout.title') . ' — ' . settings('general.site_name', 'OeParts'))

@section('meta_robots')<meta name="robots" content="noindex, nofollow">@endsection

@section('content')
@php
    $steps = [
        1 => ui_copy('checkout_step_contact', 'checkout.step_contact'),
        2 => ui_copy('checkout_step_address', 'checkout.step_address'),
        3 => ui_copy('checkout_step_shipping', 'checkout.step_shipping'),
        4 => ui_copy('checkout_step_review', 'checkout.step_review'),
        5 => ui_copy('checkout_step_payment', 'checkout.step_payment'),
    ];
    $currentStep = $step ?? 1;
@endphp

<div class="relative min-h-screen bg-ivory text-ink">
    <div class="fixed inset-0 bg-grid-ivory-fine bg-grid-md opacity-40 pointer-events-none" aria-hidden="true"></div>

    {{-- ── Dark Doc Header ── --}}
    <div class="relative bg-ink text-ivory border-b border-rule-dark overflow-hidden">
        <div class="absolute inset-0 bg-grid-navy bg-grid-lg opacity-60 pointer-events-none" aria-hidden="true"></div>
        <div class="relative max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-10 pt-10 pb-6">

            {{-- Breadcrumb --}}
            <div class="flex flex-wrap items-center justify-between gap-4 pb-4 mb-6 border-b border-white/15">
                <nav class="flex items-center gap-2 font-mono text-[10px] tracking-[0.22em] uppercase text-ivory/60">
                    <a href="{{ url('/'.app()->getLocale().'/') }}" class="hover:text-amber transition-colors">{{ ui_copy('checkout_breadcrumb_home', 'checkout.breadcrumb_home') }}</a>
                    <span class="text-ivory/30">/</span>
                    <a href="{{ route('frontend.cart.index', ['lang' => app()->getLocale()]) }}" class="hover:text-amber transition-colors">{{ ui_copy('checkout_breadcrumb_cart', 'checkout.breadcrumb_cart') }}</a>
                    <span class="text-ivory/30">/</span>
                    <span class="text-ivory">{{ ui_copy('checkout_breadcrumb_checkout', 'checkout.breadcrumb_checkout') }}</span>
                </nav>
                <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-ivory/60">
                    DOC · CHECKOUT-SHEET · {{ ui_copy('checkout_step_of_five', 'checkout.step_of_five', ['n' => str_pad($currentStep, 2, '0', STR_PAD_LEFT)]) }}
                </span>
            </div>

            <div class="flex items-end justify-between gap-4 flex-wrap">
                <div>
                    <div class="flex items-center gap-4 mb-4">
                        <span class="w-10 h-[3px] bg-amber inline-block"></span>
                        <span class="font-mono text-[10px] tracking-[0.28em] uppercase text-amber">{{ str_pad($currentStep, 2, '0', STR_PAD_LEFT) }} · {{ ui_copy('checkout_secure_checkout_eyebrow', 'checkout.secure_checkout_eyebrow') }}</span>
                    </div>
                    <h1 class="font-display font-extrabold text-ivory leading-[0.95] tracking-[-0.03em] text-4xl md:text-5xl lg:text-6xl">
                        {{ ui_copy('checkout_heading', 'checkout.heading') }}<span class="text-amber">.</span>
                    </h1>
                    <p class="mt-4 inline-flex items-center gap-2 font-mono text-[10px] tracking-[0.22em] uppercase text-ivory/70">
                        <x-heroicon-s-lock-closed class="w-3 h-3 text-amber" />
                        {{ ui_copy('checkout_tls_ssl_badge', 'checkout.tls_ssl_badge') }}
                    </p>
                </div>
                <a href="{{ route('frontend.cart.index', ['lang' => app()->getLocale()]) }}"
                   class="hidden md:inline-flex items-center gap-2 px-4 py-2.5 border border-ivory/20 text-ivory
                          font-mono text-[11px] font-bold uppercase tracking-[0.22em]
                          hover:border-amber hover:text-amber transition-colors">
                    <x-heroicon-s-arrow-long-left class="w-4 h-4" />
                    {{ ui_copy('checkout_back_to_cart', 'checkout.back_to_cart') }}
                </a>
            </div>
        </div>
    </div>

    {{-- ── Step Progress Band (sticky) ── --}}
    <div class="bg-paper border-b border-ink sticky top-0 z-30">
        <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-10 py-4">
            <div class="flex items-center justify-between">
                @foreach($steps as $num => $label)
                    @php
                        $numPad = str_pad($num, 2, '0', STR_PAD_LEFT);
                        $isDone = $num < $currentStep;
                        $isCurrent = $num === $currentStep;
                    @endphp
                    <div class="flex items-center {{ !$loop->last ? 'flex-1' : '' }}">

                        <div class="flex flex-col items-center shrink-0">
                            <div class="w-9 h-9 flex items-center justify-center font-mono text-sm font-bold tabular-nums transition-colors
                                        @if($isDone) bg-amber border border-amber text-ink
                                        @elseif($isCurrent) bg-ink border border-ink text-ivory
                                        @else bg-paper border border-rule-strong text-ink-muted
                                        @endif">
                                @if($isDone)
                                    <x-heroicon-s-check class="w-4 h-4" />
                                @else
                                    {{ $numPad }}
                                @endif
                            </div>
                            <span class="mt-2 font-mono text-[9px] font-bold uppercase tracking-[0.2em] hidden sm:block
                                         @if($isCurrent) text-ink
                                         @elseif($isDone) text-amber-ink
                                         @else text-ink-muted
                                         @endif">
                                {{ $label }}
                            </span>
                        </div>

                        @if(!$loop->last)
                            <div class="flex-1 h-px mx-2 sm:mx-3 {{ $isDone ? 'bg-amber' : 'bg-rule-strong' }}"></div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ── Session timeout banner ── --}}
    @if(isset($secondsRemaining) && $secondsRemaining > 0)
    <div x-data="{
             remaining: {{ (int) $secondsRemaining }},
             warned: false,
             init() {
                 setInterval(() => {
                     this.remaining = Math.max(0, this.remaining - 1);
                     if (this.remaining <= 120 && !this.warned) this.warned = true;
                 }, 1000);
             },
         }"
         x-show="remaining > 0"
         x-cloak
         role="timer"
         :class="remaining < 120 ? 'bg-red-50 border-red-400 text-red-800' : (remaining < 300 ? 'bg-amber/10 border-amber text-amber-ink' : 'bg-paper border-ink text-ink')"
         class="border-b transition-colors duration-500">
        {{-- Screen readers get a single threshold announcement (2 min left),
             not a per-second stream — the visible countdown below is
             aria-hidden so it doesn't also spam the same live region. --}}
        <div class="sr-only" role="status" aria-live="assertive" x-text="warned ? '{{ addslashes(ui_copy('checkout_session_expiring_soon_announcement', 'checkout.session_expiring_soon_announcement')) }}' : ''"></div>
        <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-10 py-2.5 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2 min-w-0">
                <x-heroicon-s-clock class="w-4 h-4 shrink-0" aria-hidden="true" />
                <span class="font-mono text-[10px] tracking-[0.18em] uppercase truncate" aria-hidden="true">
                    {{ ui_copy('checkout_session_expires_in', 'checkout.session_expires_in') }}
                    <span class="font-bold tabular-nums" x-text="Math.floor(remaining / 60) + ':' + String(remaining % 60).padStart(2, '0')"></span>
                    @if($currentStep < 5)
                        {{ ui_copy('checkout_session_expires_complete_before', 'checkout.session_expires_complete_before', ['step' => $steps[$currentStep] ?? ui_copy('checkout_current_step_fallback', 'checkout.current_step_fallback')]) }}
                    @endif
                </span>
            </div>
            <button @click="remaining = 0"
                    class="shrink-0 font-mono text-[9px] uppercase tracking-[0.22em] hover:underline">
                {{ ui_copy('checkout_dismiss', 'checkout.dismiss') }}
            </button>
        </div>
    </div>
    @endif

    {{-- ── Main content ── --}}
    <div class="relative max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-10 py-10">

        <form method="POST"
              action="{{ route('frontend.checkout.store', ['lang' => app()->getLocale()]) }}"
              id="checkout-form"
              x-data="{ submitting: false }"
              @submit="submitting = true">
            @csrf
            <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">

            <div class="grid grid-cols-12 gap-x-4 sm:gap-x-6 lg:gap-x-10 items-start">

                {{-- Left: Step Form --}}
                <div class="col-span-12 lg:col-span-8">

                    {{-- Step card --}}
                    <div class="border border-ink bg-paper">
                        {{-- Step header bar --}}
                        <div class="flex items-center justify-between px-5 py-3 border-b border-ink bg-ivory-alt">
                            <span class="bp-spec text-amber-ink">{{ str_pad($currentStep, 2, '0', STR_PAD_LEFT) }} · {{ $steps[$currentStep] ?? '' }}</span>
                            <span class="bp-spec-mono">
                                {{ ui_copy('checkout_step_n_of_5', 'checkout.step_n_of_5', ['n' => $currentStep]) }}
                            </span>
                        </div>
                        <div class="p-6 sm:p-8">
                            @yield('checkout_content')
                        </div>
                    </div>

                    {{-- Navigation --}}
                    <div class="mt-6 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
                        @if($currentStep > 1)
                            <a href="{{ route('frontend.checkout', ['lang' => app()->getLocale()]) }}?_back=1"
                               class="bp-btn-outline justify-center sm:justify-start">
                                <x-heroicon-s-arrow-long-left class="w-5 h-5" />
                                {{ ui_copy('checkout_back', 'checkout.back') }}
                            </a>
                        @else
                            <a href="{{ route('frontend.cart.index', ['lang' => app()->getLocale()]) }}"
                               class="bp-btn-outline justify-center sm:justify-start">
                                <x-heroicon-s-arrow-long-left class="w-5 h-5" />
                                {{ ui_copy('checkout_return_to_cart', 'checkout.return_to_cart') }}
                            </a>
                        @endif

                        <button type="submit" form="checkout-form"
                                :disabled="submitting"
                                class="bp-btn-primary justify-center sm:min-w-[240px]"
                                :class="submitting && 'opacity-60 pointer-events-none'">
                            <span x-show="submitting" x-cloak>
                                <x-heroicon-s-arrow-path class="w-5 h-5 animate-spin" />
                            </span>
                            <span x-show="!submitting" x-cloak>
                                @if($currentStep === 5)
                                    <x-heroicon-s-lock-closed class="w-5 h-5" />
                                @else
                                    <x-heroicon-s-arrow-long-right class="w-5 h-5" />
                                @endif
                            </span>
                            <span x-text="submitting ? '{{ addslashes(ui_copy('checkout_processing', 'checkout.processing')) }}' : '{{ addslashes($currentStep === 5 ? ui_copy('checkout_place_order', 'checkout.place_order') : ui_copy('checkout_continue', 'checkout.continue')) }}'"></span>
                            @if($currentStep !== 5)
                                <x-heroicon-s-arrow-long-right class="w-5 h-5" x-show="!submitting" x-cloak />
                            @endif
                        </button>
                    </div>
                </div>

                {{-- Right: Order Summary --}}
                <aside class="col-span-12 lg:col-span-4 lg:sticky lg:top-24 lg:h-fit mt-8 lg:mt-0">

                    @php
                        $summaryCart = $checkoutCart ?? null;
                        $summaryData = $checkoutSummary ?? null;
                        $summarySidebar = $sidebarSummary ?? [];
                    @endphp

                    <div class="border border-ink bg-paper">
                        <div class="flex items-center justify-between px-5 py-3 border-b border-ink bg-ivory-alt">
                            <span class="bp-spec text-amber-ink">{{ ui_copy('checkout_order_summary', 'checkout.order_summary') }}</span>
                            <span class="bp-spec-mono">{{ settings('store.currency', 'EUR') }}</span>
                        </div>

                        <div class="relative p-5">
                            <span class="absolute -top-px left-2 w-3 h-3 border-l-2 border-t-2 border-amber" aria-hidden="true"></span>
                            <span class="absolute -top-px right-2 w-3 h-3 border-r-2 border-t-2 border-amber" aria-hidden="true"></span>

                            @if($summaryData && $summaryCart && $summaryCart->items->isNotEmpty())

                                {{-- Items --}}
                                <ul class="divide-y divide-rule border-y border-rule mb-5 max-h-52 overflow-y-auto">
                                    @foreach($summaryCart->items as $item)
                                    <li class="flex items-center justify-between gap-3 py-3">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="w-9 h-9 border border-rule flex items-center justify-center shrink-0 bg-ivory-alt">
                                                <x-heroicon-o-cube class="w-4 h-4 text-ink" />
                                            </div>
                                            <div class="min-w-0" x-data="clipboard()">
                                                <button type="button" class="appearance-none bg-transparent border-0 p-0 m-0 font-mono text-sm font-bold tabular-nums text-ink truncate cursor-pointer focus:outline-none focus:ring-2 focus:ring-inset focus:ring-amber-ink rounded-sm"
                                                   @click="copy('{{ $item->product->oem_number }}')" title="{{ ui_copy('checkout_copy_oem_title', 'checkout.copy_oem_title') }}">
                                                    {{ $item->product->oem_number }}
                                                </button>
                                                <p class="font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted mt-0.5">
                                                    {{ ui_copy('checkout_qty_short', 'checkout.qty_short', ['qty' => $item->quantity]) }}
                                                </p>
                                                <span x-show="copied" x-cloak x-transition role="status" aria-live="polite" class="text-[10px] font-mono font-bold text-emerald-600">{{ ui_copy('checkout_copied', 'checkout.copied') }}</span>
                                            </div>
                                        </div>
                                        <span class="font-mono text-sm font-bold tabular-nums text-ink shrink-0">
                                            {{ format_price(bcmul((string) $item->price_at_add, (string) $item->quantity, 2)) }}
                                        </span>
                                    </li>
                                    @endforeach
                                </ul>

                                {{-- Totals --}}
                                <dl class="space-y-0">
                                    @if(!empty($summaryData['coupon_code']))
                                    <div class="flex items-baseline justify-between gap-3 py-2 border-b border-rule">
                                        <dt class="inline-flex items-center gap-1.5 font-mono text-[10px] tracking-[0.22em] uppercase text-amber-ink">
                                            <x-heroicon-s-ticket class="w-3 h-3" />
                                            {{ $summaryData['coupon_code'] }}
                                        </dt>
                                        <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                                        <dd class="font-mono text-sm font-bold tabular-nums text-amber-ink">
                                            -{{ settings('store.currency_symbol', '€') }}{{ number_format($summaryData['coupon_discount'] ?? 0, 2) }}
                                        </dd>
                                    </div>
                                    @endif
                                    <div class="flex items-baseline justify-between gap-3 py-2 border-b border-rule">
                                        <dt class="bp-spec-mono">{{ ui_copy('checkout_subtotal_label', 'checkout.subtotal_label') }}</dt>
                                        <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                                        <dd class="font-mono text-sm font-bold tabular-nums text-ink">
                                            {{ format_price($summarySidebar['subtotal'] ?? 0) }}
                                        </dd>
                                    </div>
                                    <div class="flex items-baseline justify-between gap-3 py-2 border-b border-rule">
                                        <dt class="bp-spec-mono">{{ ui_copy('checkout_shipping_label', 'checkout.shipping_label') }}</dt>
                                        <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                                        <dd class="font-mono text-sm font-bold tabular-nums text-ink">
                                            @if(($summarySidebar['shipping_cost'] ?? null) !== null)
                                                @if(($summarySidebar['shipping_cost'] ?? null) === '0.00')
                                                    <span class="text-amber-ink uppercase tracking-[0.22em] text-[10px]">{{ ui_copy('checkout_shipping_free', 'checkout.shipping_free') }}</span>
                                                @else
                                                    {{ format_price($summarySidebar['shipping_cost'] ?? 0) }}
                                                @endif
                                            @else
                                                <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-amber-ink">{{ ui_copy('checkout_shipping_next_step', 'checkout.shipping_next_step') }}</span>
                                            @endif
                                        </dd>
                                    </div>
                                    <div class="flex items-baseline justify-between gap-3 py-2">
                                        <dt class="bp-spec-mono">
                                            {{ ui_copy('checkout_vat_short', 'checkout.vat_short') }} · {{ $summarySidebar['vat_rate'] ?? ($summaryData['vat_rate'] ?? 21) }}%
                                        </dt>
                                        <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                                        <dd class="font-mono text-sm font-bold tabular-nums text-ink">
                                            @if($currentStep >= 2)
                                                {{ format_price($summarySidebar['vat_amount'] ?? 0) }}
                                            @else
                                                <span class="bp-spec-mono">{{ ui_copy('checkout_vat_tbd', 'checkout.vat_tbd') }}</span>
                                            @endif
                                        </dd>
                                    </div>
                                </dl>

                                {{-- Grand total --}}
                                <div class="mt-4 pt-4 border-t-2 border-ink">
                                    <div class="flex items-end justify-between gap-3">
                                        <div>
                                            <p class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink">{{ ui_copy('checkout_total_currency_label', 'checkout.total_currency_label', ['currency' => settings('store.currency', 'EUR')]) }}</p>
                                            <p class="font-mono text-[9px] tracking-[0.2em] uppercase text-ink-muted mt-1">{{ ui_copy('checkout_including_all_taxes', 'checkout.including_all_taxes') }}</p>
                                        </div>
                                        <p class="font-mono text-3xl sm:text-4xl font-medium text-ink tabular-nums leading-none tracking-tight">
                                            {{ format_price($summarySidebar['grand_total'] ?? 0) }}
                                        </p>
                                    </div>
                                </div>

                                {{-- Trust badges --}}
                                <div class="mt-5 pt-4 border-t border-rule flex items-center justify-between gap-3">
                                    <span class="inline-flex items-center gap-1.5 bp-spec-mono">
                                        <x-heroicon-s-shield-check class="w-3 h-3 text-amber-ink" />
                                        {{ ui_copy('checkout_ssl_tls_badge', 'checkout.ssl_tls_badge') }}
                                    </span>
                                    <span class="inline-flex items-center gap-1.5 bp-spec-mono">
                                        <x-heroicon-s-credit-card class="w-3 h-3 text-amber-ink" />
                                        Airwallex
                                    </span>
                                </div>
                            @else
                                <div class="text-center py-10">
                                    <x-heroicon-o-shopping-bag class="w-10 h-10 text-rule-strong mx-auto mb-3" />
                                    <p class="font-mono text-[11px] tracking-[0.22em] uppercase text-ink-muted">{{ ui_copy('checkout_cart_empty', 'checkout.cart_empty') }}</p>
                                </div>
                            @endif

                            <span class="absolute -bottom-px left-2 w-3 h-3 border-l-2 border-b-2 border-amber" aria-hidden="true"></span>
                            <span class="absolute -bottom-px right-2 w-3 h-3 border-r-2 border-b-2 border-amber" aria-hidden="true"></span>
                        </div>
                    </div>

                    {{-- Support card --}}
                    <div class="mt-4 border border-rule bg-paper p-4 flex items-start gap-3">
                        <div class="w-8 h-8 border border-ink flex items-center justify-center shrink-0 bg-paper">
                            <x-heroicon-o-chat-bubble-left-ellipsis class="w-4 h-4 text-ink" />
                        </div>
                        <div class="flex-1">
                            <p class="bp-spec text-amber-ink mb-1">{{ ui_copy('checkout_need_help_heading', 'checkout.need_help_heading') }}</p>
                            <p class="text-xs text-body mb-2">{{ ui_copy('checkout_support_hours_note', 'checkout.support_hours_note') }}</p>
                            <a href="{{ url('/'.app()->getLocale().'/contact') }}"
                               class="inline-flex items-center gap-1.5 font-mono text-[10px] font-bold uppercase tracking-[0.22em] text-ink hover:text-amber-ink transition-colors">
                                {{ ui_copy('checkout_contact_support', 'checkout.contact_support') }}
                                <x-heroicon-s-arrow-long-right class="w-3 h-3" />
                            </a>
                        </div>
                    </div>
                </aside>
            </div>
        </form>
    </div>
</div>

{{-- Flash → Toast events --}}
@if(session('error'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    window.dispatchEvent(new CustomEvent('toast', {
        detail: { message: @json(session('error')), type: 'error', title: @json(ui_copy('checkout_toast_error_title', 'checkout.toast_error_title')), duration: 6000 }
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
        detail: { message: @json(session('warning')), type: 'warning', title: @json(ui_copy('checkout_toast_warning_title', 'checkout.toast_warning_title')), duration: 5000 }
    }));
});
</script>
@endif
@endsection
