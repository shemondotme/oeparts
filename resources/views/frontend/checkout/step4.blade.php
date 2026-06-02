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

<div class="space-y-6">

    {{-- Sub-header --}}
    <header class="pb-4 border-b border-rule">
        <h2 class="font-display text-2xl md:text-3xl font-extrabold text-ink leading-tight tracking-[-0.02em]">
            Review order<span class="text-amber">.</span>
        </h2>
        <p class="mt-2 font-mono text-[11px] tracking-[0.18em] uppercase text-ink-muted">
            Manifest · Verify · Confirm
        </p>
    </header>

    {{-- Order items manifest --}}
    <section class="border border-ink bg-paper">
        <header class="flex items-center justify-between px-4 py-3 border-b border-ink bg-ivory-alt">
            <span class="bp-spec text-amber-ink flex items-center gap-2">
                <x-heroicon-o-cube class="w-3.5 h-3.5" />
                § Manifest · Order items
            </span>
            @if($cart)
                <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">
                    {{ str_pad($cart->items->count(), 2, '0', STR_PAD_LEFT) }} {{ Str::plural('item', $cart->items->count()) }}
                </span>
            @endif
        </header>
        @if($cart && $cart->items->isNotEmpty())
            <ul class="divide-y divide-rule">
                @foreach($cart->items as $item)
                <li class="grid grid-cols-12 items-center gap-3 px-4 py-3.5">
                    <span class="col-span-1 font-mono text-[10px] tabular-nums tracking-[0.18em] uppercase text-ink-muted">
                        {{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}
                    </span>
                    <div class="col-span-1">
                        <div class="w-9 h-9 border border-rule bg-ivory-alt flex items-center justify-center">
                            <x-heroicon-o-cube class="w-4 h-4 text-ink" />
                        </div>
                    </div>
                    <div class="col-span-6 min-w-0">
                        <p class="font-mono text-sm font-bold tabular-nums text-ink">{{ $item->product->oem_number }}</p>
                        <p class="font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted mt-0.5">
                            Qty {{ str_pad($item->quantity, 2, '0', STR_PAD_LEFT) }} × €{{ number_format($item->price_at_add, 2) }}
                        </p>
                    </div>
                    <span class="col-span-4 text-right font-mono text-base font-bold tabular-nums text-ink">
                        €{{ number_format((float) bcmul((string) $item->price_at_add, (string) $item->quantity, 2), 2) }}
                    </span>
                </li>
                @endforeach
            </ul>
        @else
            <p class="p-5 font-mono text-xs tracking-[0.18em] uppercase text-ink-muted">No items in cart</p>
        @endif
    </section>

    {{-- Two-column details --}}
    <div class="grid sm:grid-cols-2 gap-4">

        {{-- Shipping address --}}
        <section class="border border-ink bg-paper">
            <header class="flex items-center justify-between px-4 py-3 border-b border-ink bg-ivory-alt">
                <span class="bp-spec text-amber-ink flex items-center gap-2">
                    <x-heroicon-o-map-pin class="w-3.5 h-3.5" />
                    § Delivering to
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
                        {{ $addr['country_code'] ?? '' }}
                    </span>
                </address>
                @else
                <p class="font-mono text-xs tracking-[0.18em] uppercase text-ink-muted">No shipping address provided</p>
                @endif
            </div>
        </section>

        {{-- Shipping method --}}
        <section class="border border-ink bg-paper">
            <header class="flex items-center justify-between px-4 py-3 border-b border-ink bg-ivory-alt">
                <span class="bp-spec text-amber-ink flex items-center gap-2">
                    <x-heroicon-o-truck class="w-3.5 h-3.5" />
                    § Carrier
                </span>
            </header>
            <div class="flex items-center justify-between px-4 py-4">
                <p class="font-display text-sm font-bold text-ink">
                    {{ $shippingMethod ? $shippingMethod->name : 'Standard Delivery' }}
                </p>
                <p class="font-mono text-base font-bold tabular-nums {{ $shippingCost === '0.00' ? 'text-amber-ink' : 'text-ink' }}">
                    @if($shippingCost === '0.00')
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 border border-amber bg-paper text-[10px] uppercase tracking-[0.22em]">Free</span>
                    @else
                        €{{ number_format((float) $shippingCost, 2) }}
                    @endif
                </p>
            </div>
        </section>
    </div>

    {{-- Pricing breakdown --}}
    <section class="border border-ink bg-paper">
        <header class="flex items-center px-4 py-3 border-b border-ink bg-ivory-alt">
            <span class="bp-spec text-amber-ink flex items-center gap-2">
                <x-heroicon-o-calculator class="w-3.5 h-3.5" />
                § Price breakdown
            </span>
        </header>
        <dl class="px-4 py-4 divide-y divide-rule">
            <div class="flex items-baseline justify-between gap-3 py-2.5">
                <dt class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">Subtotal · excl. VAT</dt>
                <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                <dd class="font-mono text-sm font-bold tabular-nums text-ink">€{{ number_format((float) $subtotal, 2) }}</dd>
            </div>
            <div class="flex items-baseline justify-between gap-3 py-2.5">
                <dt class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">Shipping</dt>
                <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                <dd class="font-mono text-sm font-bold tabular-nums {{ $shippingCost === '0.00' ? 'text-amber-ink' : 'text-ink' }}">
                    {{ $shippingCost === '0.00' ? 'FREE' : '€' . number_format((float) $shippingCost, 2) }}
                </dd>
            </div>
            <div class="flex items-baseline justify-between gap-3 py-2.5">
                <dt class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">VAT · {{ $vatRate }}%</dt>
                <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                <dd class="font-mono text-sm font-bold tabular-nums text-ink">€{{ number_format((float) $vatAmount, 2) }}</dd>
            </div>
        </dl>
        {{-- Grand total --}}
        <div class="px-4 py-4 border-t-2 border-ink flex items-end justify-between gap-3">
            <div>
                <p class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink">Grand total · EUR</p>
                <p class="font-mono text-[9px] tracking-[0.2em] uppercase text-ink-muted mt-1">Including all taxes</p>
            </div>
            <p class="font-mono text-3xl sm:text-4xl font-medium text-ink tabular-nums leading-none tracking-tight">
                €{{ number_format((float) $total, 2) }}
            </p>
        </div>
    </section>

    {{-- Agreement --}}
    <label class="flex items-start gap-3 p-5 border border-rule-strong bg-ivory-alt cursor-pointer hover:border-ink transition-colors">
        <input type="checkbox"
               name="agree_terms"
               value="1"
               required
               class="mt-0.5 w-4 h-4 border-ink text-amber focus:ring-amber focus:ring-offset-0 shrink-0">
        <span class="text-sm text-body leading-relaxed">
            I agree to the
            <a href="{{ route('frontend.page', ['lang' => app()->getLocale(), 'slug' => 'terms']) }}" target="_blank" rel="noopener noreferrer"
               class="font-bold text-amber-ink underline underline-offset-2 hover:no-underline transition-colors">Terms and Conditions</a>
            and
            <a href="{{ route('frontend.page', ['lang' => app()->getLocale(), 'slug' => 'privacy']) }}" target="_blank" rel="noopener noreferrer"
               class="font-bold text-amber-ink underline underline-offset-2 hover:no-underline transition-colors">Privacy Policy</a>
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
