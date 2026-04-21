@extends('layouts.app')

@section('title', __('Order #:number', ['number' => $order->order_number]))

@section('content')
<div class="min-h-screen bg-bg-page">

{{-- ── Breadcrumb ───────────────────────────────────────────────────────── --}}
<div class="bg-gray-50 border-b border-gray-100 py-3 px-4">
    <div class="max-w-5xl mx-auto">
        <ol class="flex flex-wrap items-center gap-1.5 text-xs text-muted">
            <li><a href="/{{ app()->getLocale() }}/" class="hover:text-amber-text transition-colors font-medium">Home</a></li>
            <li class="text-gray-300"><x-heroicon-o-chevron-right class="w-3 h-3 inline" /></li>
            <li><a href="/{{ app()->getLocale() }}/account/dashboard" class="hover:text-amber-text transition-colors font-medium">Account</a></li>
            <li class="text-gray-300"><x-heroicon-o-chevron-right class="w-3 h-3 inline" /></li>
            <li><a href="/{{ app()->getLocale() }}/account/orders" class="hover:text-amber-text transition-colors font-medium">Orders</a></li>
            <li class="text-gray-300"><x-heroicon-o-chevron-right class="w-3 h-3 inline" /></li>
            <li class="text-navy font-semibold font-mono">{{ $order->order_number }}</li>
        </ol>
    </div>
</div>

<div class="max-w-5xl mx-auto px-4 py-8">

    {{-- ── Page Header ────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-8">
        <div>
            <h1 class="font-display text-2xl md:text-3xl font-extrabold text-navy leading-tight">
                Order <span class="font-mono text-amber-text bg-amber/10 px-2 py-0.5 rounded-lg">{{ $order->order_number }}</span>
            </h1>
            <p class="text-muted mt-2">
                Placed on <span class="font-semibold text-body">{{ $order->created_at->format('F j, Y \a\t H:i') }}</span>
            </p>
        </div>
        <div class="flex items-center gap-3">
            @if($order->status->canDownloadInvoice())
                <a href="{{ route('frontend.account.invoice.download', ['lang' => app()->getLocale(), 'order' => $order]) }}"
                   class="inline-flex items-center gap-2 px-4 py-2.5 border-2 border-gray-200 rounded-xl text-navy font-bold text-sm
                          hover:border-amber hover:text-amber hover:bg-amber/5 transition-all duration-200">
                    <x-heroicon-o-document-arrow-down class="w-4 h-4" />
                    Download Invoice
                </a>
            @endif
            @if($order->status->canBeCancelled())
                <form method="POST"
                      action="{{ route('frontend.account.order.cancel', ['lang' => app()->getLocale(), 'order' => $order]) }}"
                      x-data
                      x-on:submit.prevent="if(confirm('Are you sure you want to cancel this order?')) $el.submit()">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2.5 border-2 border-red-200 rounded-xl text-red-600 font-bold text-sm
                                   hover:border-red-300 hover:bg-red-50 transition-all duration-200">
                        <x-heroicon-o-x-mark class="w-4 h-4" />
                        Cancel Order
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- ── Order Status Timeline ──────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-8">
        <h2 class="font-display text-base font-bold text-navy mb-6 flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-navy/10 flex items-center justify-center">
                <x-heroicon-o-truck class="w-4 h-4 text-navy" />
            </div>
            Order Status
        </h2>

        <div class="flex items-center justify-between relative">
            {{-- Connector line --}}
            <div class="absolute top-5 left-0 right-0 h-1 bg-gray-100 -z-10"></div>
            <div class="absolute top-5 left-0 h-1 bg-gradient-to-r from-amber to-orange-500 -z-10 transition-all duration-500"
                 style="width: {{ ($order->status->value / 4) * 100 }}%"></div>

            @foreach(\App\Enums\OrderStatus::cases() as $index => $status)
                <div class="flex flex-col items-center text-center">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center mb-2 transition-all duration-300
                                {{ $order->status->value >= $status->value
                                    ? 'bg-gradient-to-br from-amber to-orange-500 text-white shadow-lg shadow-amber/30'
                                    : 'bg-gray-100 text-gray-400' }}">
                        @if($order->status->value >= $status->value)
                            <x-heroicon-s-check class="w-5 h-5" />
                        @else
                            <span class="text-sm font-bold">{{ $index + 1 }}</span>
                        @endif
                    </div>
                    <span class="text-xs font-semibold {{ $order->status->value >= $status->value ? 'text-navy' : 'text-muted' }}">
                        {{ $status->label() }}
                    </span>
                </div>
            @endforeach
        </div>

        @if($order->tracking_number)
        <div class="mt-6 p-4 bg-emerald-50 border border-emerald-100 rounded-xl">
            <p class="text-sm font-semibold text-emerald-700 flex items-center gap-2">
                <x-heroicon-s-check-circle class="w-4 h-4" />
                Tracking Number: <span class="font-mono font-bold">{{ $order->tracking_number }}</span>
            </p>
        </div>
        @endif
    </div>

    <div class="grid lg:grid-cols-3 gap-8">

        {{-- ── Left: Order Items (2 columns) ──────────────────────────── --}}
        <div class="lg:col-span-2 space-y-8">

            {{-- Order Items --}}
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                <h2 class="font-display text-base font-bold text-navy mb-6 flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-amber/10 flex items-center justify-center">
                        <x-heroicon-o-cube class="w-4 h-4 text-amber" />
                    </div>
                    Order Items
                </h2>

                <div class="space-y-6">
                    @foreach($order->items as $item)
                    <div class="flex items-start gap-4 pb-6 border-b border-gray-100 last:border-0 last:pb-0">
                        {{-- Icon --}}
                        <div class="w-12 h-12 rounded-xl bg-navy/5 flex items-center justify-center shrink-0">
                            <x-heroicon-o-cube class="w-6 h-6 text-navy/40" />
                        </div>

                        <div class="flex-1 min-w-0">
                            <h3 class="font-bold text-navy truncate">{{ $item->product->name ?? $item->product_name }}</h3>
                            <p class="text-sm font-mono font-semibold text-amber-text mt-1">OEM #: {{ $item->oem_number }}</p>
                            <div class="flex items-center gap-4 mt-2 text-sm text-muted">
                                <span>Qty: <strong class="text-navy">{{ $item->quantity }}</strong></span>
                                <span>·</span>
                                <span>Unit: <strong class="text-navy">{{ format_price($item->unit_price) }}</strong></span>
                            </div>
                        </div>

                        <div class="text-right shrink-0">
                            <p class="font-bold text-lg text-navy">{{ format_price($item->total) }}</p>
                            @if($item->condition)
                            <span class="inline-block mt-1 px-3 py-1 text-xs font-bold rounded-full
                                         {{ $item->condition === 'new' ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700' }}">
                                {{ ucfirst($item->condition) }}
                            </span>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- Order Totals --}}
                <div class="mt-8 pt-6 border-t border-gray-100">
                    <div class="max-w-md ml-auto space-y-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-muted">Subtotal</span>
                            <span class="font-bold text-navy">{{ format_price($order->subtotal) }}</span>
                        </div>
                        @if($order->shipping_cost > 0)
                        <div class="flex justify-between text-sm">
                            <span class="text-muted">Shipping</span>
                            <span class="font-bold text-navy">{{ format_price($order->shipping_cost) }}</span>
                        </div>
                        @endif
                        @if($order->discount_amount > 0)
                        <div class="flex justify-between text-sm">
                            <span class="text-muted">Discount</span>
                            <span class="font-bold text-emerald-600">-{{ format_price($order->discount_amount) }}</span>
                        </div>
                        @endif
                        @if($order->tax_amount > 0)
                        <div class="flex justify-between text-sm">
                            <span class="text-muted">Tax</span>
                            <span class="font-bold text-navy">{{ format_price($order->tax_amount) }}</span>
                        </div>
                        @endif
                        <div class="flex justify-between pt-3 border-t-2 border-amber/20">
                            <span class="font-bold text-navy text-lg">Total</span>
                            <span class="text-2xl font-extrabold text-amber">{{ format_price($order->total) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Right: Addresses & Payment (1 column) ──────────────────── --}}
        <div class="space-y-6">

            {{-- Shipping Address --}}
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                <h2 class="font-display text-base font-bold text-navy mb-4 flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                        <x-heroicon-o-map-pin class="w-4 h-4 text-blue-600" />
                    </div>
                    Shipping Address
                </h2>
                <address class="not-italic text-body leading-relaxed">
                    <p class="font-bold text-navy">{{ $order->shippingAddress->first_name }} {{ $order->shippingAddress->last_name }}</p>
                    @if($order->shippingAddress->company)
                    <p class="text-sm">{{ $order->shippingAddress->company }}</p>
                    @endif
                    <p class="text-sm">{{ $order->shippingAddress->address_line_1 }}</p>
                    @if($order->shippingAddress->address_line_2)
                    <p class="text-sm">{{ $order->shippingAddress->address_line_2 }}</p>
                    @endif
                    <p class="text-sm font-mono font-semibold text-navy mt-2">
                        {{ $order->shippingAddress->postal_code }} {{ $order->shippingAddress->city }}
                    </p>
                    <p class="text-sm text-muted">{{ $order->shippingAddress->country_code }}</p>
                    @if($order->shippingAddress->phone)
                    <p class="text-sm mt-2 flex items-center gap-1.5">
                        <x-heroicon-o-phone class="w-3.5 h-3.5 text-amber" />
                        {{ $order->shippingAddress->phone }}
                    </p>
                    @endif
                </address>
            </div>

            {{-- Billing Address --}}
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                <h2 class="font-display text-base font-bold text-navy mb-4 flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center">
                        <x-heroicon-o-document-text class="w-4 h-4 text-emerald-600" />
                    </div>
                    Billing Address
                </h2>
                <address class="not-italic text-body leading-relaxed">
                    <p class="font-bold text-navy">{{ $order->billingAddress->first_name }} {{ $order->billingAddress->last_name }}</p>
                    @if($order->billingAddress->company)
                    <p class="text-sm">{{ $order->billingAddress->company }}</p>
                    @endif
                    <p class="text-sm">{{ $order->billingAddress->address_line_1 }}</p>
                    @if($order->billingAddress->address_line_2)
                    <p class="text-sm">{{ $order->billingAddress->address_line_2 }}</p>
                    @endif
                    <p class="text-sm font-mono font-semibold text-navy mt-2">
                        {{ $order->billingAddress->postal_code }} {{ $order->billingAddress->city }}
                    </p>
                    <p class="text-sm text-muted">{{ $order->billingAddress->country_code }}</p>
                    @if($order->billingAddress->phone)
                    <p class="text-sm mt-2 flex items-center gap-1.5">
                        <x-heroicon-o-phone class="w-3.5 h-3.5 text-amber" />
                        {{ $order->billingAddress->phone }}
                    </p>
                    @endif
                </address>
            </div>

            {{-- Payment Method --}}
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                <h2 class="font-display text-base font-bold text-navy mb-4 flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-amber/10 flex items-center justify-center">
                        <x-heroicon-o-credit-card class="w-4 h-4 text-amber" />
                    </div>
                    Payment Method
                </h2>

                <div class="flex items-start gap-3">
                    @if($order->payment_method === 'card')
                    <div class="w-12 h-12 rounded-xl bg-navy/5 flex items-center justify-center shrink-0">
                        <x-heroicon-o-credit-card class="w-6 h-6 text-navy/50" />
                    </div>
                    <div>
                        <p class="font-bold text-navy">Credit / Debit Card</p>
                        <p class="text-sm text-muted mt-1">Ending in <span class="font-mono font-bold text-navy">{{ $order->payment_last4 ?? '****' }}</span></p>
                    </div>
                    @elseif($order->payment_method === 'bank_transfer')
                    <div class="w-12 h-12 rounded-xl bg-navy/5 flex items-center justify-center shrink-0">
                        <x-heroicon-o-building-library class="w-6 h-6 text-navy/50" />
                    </div>
                    <div>
                        <p class="font-bold text-navy">Bank Transfer</p>
                        <p class="text-sm text-muted mt-1">Reference: <span class="font-mono font-bold text-navy">{{ $order->payment_reference ?? 'N/A' }}</span></p>
                    </div>
                    @endif
                </div>

                <div class="mt-4 pt-4 border-t border-gray-100">
                    <p class="text-sm flex items-center gap-2">
                        Payment Status:
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold
                                     {{ $order->payment_status === 'paid' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber/15 text-amber-text' }}">
                            <x-heroicon-s-check-circle class="w-3 h-3" />
                            {{ ucfirst($order->payment_status) }}
                        </span>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
@endsection
