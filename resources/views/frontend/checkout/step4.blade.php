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
    $urgentProcessing = $sidebarSummary['urgent_processing'] ?? false;
    $urgentProcessingFee = $sidebarSummary['urgent_processing_fee'] ?? '0.00';
    $handlingFee = $sidebarSummary['handling_fee'] ?? '0.00';
@endphp

<div class="space-y-6">

    {{-- Sub-header --}}
    <header class="pb-4 border-b border-rule">
        <h2 class="font-display text-2xl md:text-3xl font-extrabold text-ink leading-tight tracking-[-0.02em]">
            {{ ui_copy('checkout_review_order_heading', 'checkout.review_order_heading') }}<span class="text-amber-ink">.</span>
        </h2>
        <p class="mt-2 font-mono text-[11px] tracking-[0.18em] uppercase text-ink-muted">
            {{ ui_copy('checkout_review_order_subtitle', 'checkout.review_order_subtitle') }}
        </p>
    </header>

    {{-- Order items manifest --}}
    <section class="border border-ink bg-paper">
        <header class="flex items-center justify-between px-4 py-3 border-b border-ink bg-ivory-alt">
            <span class="bp-spec text-amber-ink flex items-center gap-2">
                <x-heroicon-o-cube class="w-3.5 h-3.5" />
                {{ ui_copy('checkout_manifest_order_items', 'checkout.manifest_order_items') }}
            </span>
            @if($cart)
                <span class="bp-spec-mono">
                    {{ str_pad($cart->items->count(), 2, '0', STR_PAD_LEFT) }} {{ ui_trans_choice('checkout_items_word', 'checkout.items_word', $cart->items->count()) }}
                </span>
            @endif
        </header>
        @if($cart && $cart->items->isNotEmpty())
            <ul class="divide-y divide-rule">
                @foreach($cart->items as $item)
                <li class="grid grid-cols-12 items-center gap-3 px-4 py-3.5">
                    <div class="col-span-1">
                        <div class="w-9 h-9 border border-rule bg-ivory-alt flex items-center justify-center">
                            <x-heroicon-o-cube class="w-4 h-4 text-ink" />
                        </div>
                    </div>
                    <div class="col-span-7 min-w-0" x-data="clipboard()">
                        <button type="button" class="appearance-none bg-transparent border-0 p-0 m-0 font-mono text-sm font-bold tabular-nums text-ink truncate cursor-pointer focus:outline-none focus:ring-2 focus:ring-inset focus:ring-amber-ink rounded-sm"
                           @click="copy('{{ $item->product->oem_number }}')" title="{{ ui_copy('checkout_copy_oem_title', 'checkout.copy_oem_title') }}">
                            {{ $item->product->oem_number }}
                        </button>
                        <p class="font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted mt-0.5">
                            {{ ui_copy('checkout_qty_short', 'checkout.qty_short', ['qty' => str_pad($item->quantity, 2, '0', STR_PAD_LEFT)]) }} × {{ format_price($item->price_at_add) }}
                        </p>
                        <span x-show="copied" x-cloak x-transition role="status" aria-live="polite" class="text-[10px] font-mono font-bold text-emerald-600">{{ ui_copy('checkout_copied', 'checkout.copied') }}</span>
                    </div>
                    <span class="col-span-4 text-right font-mono text-base font-bold tabular-nums text-ink">
                        {{ format_price(bcmul((string) $item->price_at_add, (string) $item->quantity, 2)) }}
                    </span>
                </li>
                @endforeach
            </ul>
        @else
            <p class="p-5 font-mono text-xs tracking-[0.18em] uppercase text-ink-muted">{{ ui_copy('checkout_no_items_in_cart', 'checkout.no_items_in_cart') }}</p>
        @endif
    </section>

    {{-- Two-column details --}}
    <div class="grid sm:grid-cols-2 gap-4">

        {{-- Shipping address --}}
        <section class="border border-ink bg-paper">
            <header class="flex items-center justify-between px-4 py-3 border-b border-ink bg-ivory-alt">
                <span class="bp-spec text-amber-ink flex items-center gap-2">
                    <x-heroicon-o-map-pin class="w-3.5 h-3.5" />
                    {{ ui_copy('checkout_delivering_to', 'checkout.delivering_to') }}
                </span>
            </header>
            <div class="px-4 py-4">
                @if(!empty($addr))
                <address class="not-italic font-mono text-sm text-ink leading-relaxed">
                    <strong class="font-bold">{{ trim(($addr['first_name'] ?? '') . ' ' . ($addr['last_name'] ?? '')) }}</strong><br>
                    {{ $addr['street'] ?? '' }}<br>
                    <span class="tabular-nums">{{ $addr['postal_code'] ?? '' }}</span> {{ $addr['city'] ?? '' }}<br>
                    <span class="inline-flex items-center gap-1.5 mt-1 font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-amber-ink">
                        <x-heroicon-s-globe-europe-africa class="w-3 h-3" />
                        {{ !empty($addr['country_code']) ? localized_country_name($addr['country_code']) : '' }}
                    </span>
                </address>
                @else
                <p class="font-mono text-xs tracking-[0.18em] uppercase text-ink-muted">{{ ui_copy('checkout_no_shipping_address', 'checkout.no_shipping_address') }}</p>
                @endif
            </div>
        </section>

        {{-- Shipping method --}}
        <section class="border border-ink bg-paper">
            <header class="flex items-center justify-between px-4 py-3 border-b border-ink bg-ivory-alt">
                <span class="bp-spec text-amber-ink flex items-center gap-2">
                    <x-heroicon-o-truck class="w-3.5 h-3.5" />
                    {{ ui_copy('checkout_carrier_label', 'checkout.carrier_label') }}
                </span>
            </header>
            <div class="flex items-center justify-between px-4 py-4">
                <p class="font-display text-sm font-bold text-ink">
                    {{ $shippingMethod ? $shippingMethod->name : ui_copy('checkout_standard_delivery_fallback', 'checkout.standard_delivery_fallback') }}
                </p>
                <p class="font-mono text-base font-bold tabular-nums {{ $shippingCost === '0.00' ? 'text-amber-ink' : 'text-ink' }}">
                    @if($shippingCost === '0.00')
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 border border-amber bg-paper text-[10px] uppercase tracking-[0.22em]">{{ ui_copy('checkout_free_badge', 'checkout.free_badge') }}</span>
                    @else
                        {{ format_price($shippingCost) }}
                    @endif
                </p>
            </div>
        </section>
    </div>

    {{-- Oversized parts shipping notice — fixed-rate shipping doesn't cover
         oversized/heavy freight surcharges the carrier may bill after
         dispatch; highlighted here (and re-stated in the agreement checkbox
         below) so the customer sees and accepts this before paying. --}}
    <section class="border border-amber bg-amber/5">
        <div class="flex items-start gap-3 px-4 py-3.5">
            <x-heroicon-s-exclamation-triangle class="w-4 h-4 text-amber-ink shrink-0 mt-0.5" />
            <div>
                <p class="font-mono text-[11px] font-bold tracking-[0.18em] uppercase text-amber-ink">
                    {{ ui_copy('checkout_oversized_notice_heading', 'checkout.oversized_notice_heading') }}
                </p>
                <p class="mt-1.5 text-sm text-body leading-relaxed">
                    {{ ui_copy('checkout_oversized_notice_body', 'checkout.oversized_notice_body') }}
                </p>
            </div>
        </div>
    </section>

    {{-- Rush processing (if selected) --}}
    @if($urgentProcessing)
    <section class="border border-amber bg-amber/5">
        <div class="flex items-center justify-between px-4 py-3.5">
            <span class="inline-flex items-center gap-2 font-mono text-[11px] font-bold tracking-[0.18em] uppercase text-amber-ink">
                <x-heroicon-s-bolt class="w-4 h-4" />
                {{ settings_trans('checkout.urgent_processing_label', 'Rush processing') }}
            </span>
            <span class="font-mono text-base font-bold tabular-nums text-ink">{{ format_price($urgentProcessingFee) }}</span>
        </div>
    </section>
    @endif

    {{-- Pricing breakdown --}}
    <section class="border border-ink bg-paper">
        <header class="flex items-center px-4 py-3 border-b border-ink bg-ivory-alt">
            <span class="bp-spec text-amber-ink flex items-center gap-2">
                <x-heroicon-o-calculator class="w-3.5 h-3.5" />
                {{ ui_copy('checkout_price_breakdown_heading', 'checkout.price_breakdown_heading') }}
            </span>
        </header>
        <dl class="px-4 py-4 divide-y divide-rule">
            <div class="flex items-baseline justify-between gap-3 py-2.5">
                <dt class="bp-spec-mono">{{ ui_copy('checkout_subtotal_excl_vat', 'checkout.subtotal_excl_vat') }}</dt>
                <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                <dd class="font-mono text-sm font-bold tabular-nums text-ink">{{ format_price($subtotal) }}</dd>
            </div>
            <div class="flex items-baseline justify-between gap-3 py-2.5">
                <dt class="bp-spec-mono">{{ ui_copy('checkout_shipping_label', 'checkout.shipping_label') }}</dt>
                <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                <dd class="font-mono text-sm font-bold tabular-nums {{ $shippingCost === '0.00' ? 'text-amber-ink' : 'text-ink' }}">
                    {{ $shippingCost === '0.00' ? ui_copy('checkout_shipping_free', 'checkout.shipping_free') : format_price($shippingCost) }}
                </dd>
            </div>
            @if($urgentProcessing)
            <div class="flex items-baseline justify-between gap-3 py-2.5">
                <dt class="bp-spec-mono">{{ settings_trans('checkout.urgent_processing_label', 'Rush processing') }}</dt>
                <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                <dd class="font-mono text-sm font-bold tabular-nums text-ink">{{ format_price($urgentProcessingFee) }}</dd>
            </div>
            @endif
            @if(bccomp($handlingFee, '0', 2) > 0)
            <div class="flex items-baseline justify-between gap-3 py-2.5">
                <dt class="bp-spec-mono">{{ ui_copy('checkout_handling_fee_label', 'checkout.handling_fee_label') }}</dt>
                <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                <dd class="font-mono text-sm font-bold tabular-nums text-ink">{{ format_price($handlingFee) }}</dd>
            </div>
            @endif
            <div class="flex items-baseline justify-between gap-3 py-2.5">
                <dt class="bp-spec-mono">{{ ui_copy('checkout_vat_short', 'checkout.vat_short') }} · {{ $vatRate }}%</dt>
                <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                <dd class="font-mono text-sm font-bold tabular-nums text-ink">{{ format_price($vatAmount) }}</dd>
            </div>
        </dl>
        {{-- Grand total --}}
        <div class="px-4 py-4 border-t-2 border-ink flex items-end justify-between gap-3">
            <div>
                <p class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink">{{ ui_copy('checkout_grand_total_currency_label', 'checkout.grand_total_currency_label', ['currency' => settings('general.currency', 'EUR')]) }}</p>
                <p class="font-mono text-[9px] tracking-[0.2em] uppercase text-ink-muted mt-1">{{ ui_copy('checkout_including_all_taxes', 'checkout.including_all_taxes') }}</p>
            </div>
            <p class="font-mono text-3xl sm:text-4xl font-medium text-ink tabular-nums leading-none tracking-tight">
                {{ format_price($total) }}
            </p>
        </div>
    </section>

    {{-- Agreement --}}
    <label class="flex items-start gap-3 p-5 border border-ink bg-ivory-alt cursor-pointer hover:border-ink transition-colors">
        <input type="checkbox"
               name="agree_terms"
               value="1"
               required
               class="mt-0.5 w-4 h-4 border-ink text-amber-ink focus:ring-amber-ink focus:ring-offset-0 shrink-0">
        <span class="text-sm text-body leading-relaxed">
            {{ ui_copy('checkout_agree_terms_prefix', 'checkout.agree_terms_prefix') }}
            <a href="{{ route('frontend.page', ['lang' => app()->getLocale(), 'slug' => 'terms-of-service']) }}" target="_blank" rel="noopener noreferrer"
               class="font-bold text-amber-ink underline underline-offset-2 hover:no-underline transition-colors">{{ ui_copy('checkout_terms_and_conditions', 'checkout.terms_and_conditions') }}</a>,
            <a href="{{ route('frontend.page', ['lang' => app()->getLocale(), 'slug' => 'privacy-policy']) }}" target="_blank" rel="noopener noreferrer"
               class="font-bold text-amber-ink underline underline-offset-2 hover:no-underline transition-colors">{{ ui_copy('checkout_privacy_policy', 'checkout.privacy_policy') }}</a>
            {{ ui_copy('checkout_and', 'checkout.and') }}
            <a href="{{ route('frontend.page', ['lang' => app()->getLocale(), 'slug' => 'returns-policy']) }}" target="_blank" rel="noopener noreferrer"
               class="font-bold text-amber-ink underline underline-offset-2 hover:no-underline transition-colors">{{ ui_copy('checkout_returns_policy', 'checkout.returns_policy') }}</a>.
            {{ ui_copy('checkout_oversized_ack_sentence', 'checkout.oversized_ack_sentence') }}
        </span>
    </label>
    @error('agree_terms')
        <p class="flex items-center gap-1.5 font-mono text-[10px] tracking-[0.18em] uppercase text-red-600 ml-7">
            <x-heroicon-s-exclamation-circle class="w-3 h-3" />
            {{ $message }}
        </p>
    @enderror
</div>
@endsection
