@extends('layouts.admin')

@section('title', 'Order #' . $order->order_number)

@section('content')
<div class="px-6 py-8">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.orders.index') }}" class="text-gray-500 hover:text-gray-700">
                    <x-heroicon-o-arrow-left class="w-5 h-5" />
                </a>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Order #{{ $order->order_number }}</h1>
                    <p class="text-gray-600 mt-1">
                        Placed on {{ $order->created_at->format('F j, Y \a\t H:i') }}
                        @if($order->user)
                            by {{ $order->user->email }}
                        @else
                            by guest {{ $order->guest_email }}
                        @endif
                    </p>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.orders.packing-slip', $order) }}" target="_blank"
               class="inline-flex items-center gap-1 border border-gray-300 rounded px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                <x-heroicon-o-printer class="w-4 h-4" />
                Packing Slip
            </a>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                @if($order->status->value === 'pending') bg-yellow-100 text-yellow-800
                @elseif($order->status->value === 'paid') bg-blue-100 text-blue-800
                @elseif($order->status->value === 'processing') bg-indigo-100 text-indigo-800
                @elseif($order->status->value === 'shipped') bg-purple-100 text-purple-800
                @elseif($order->status->value === 'delivered') bg-green-100 text-green-800
                @elseif($order->status->value === 'cancelled') bg-red-100 text-red-800
                @elseif($order->status->value === 'refund_requested') bg-orange-100 text-orange-800
                @elseif($order->status->value === 'refunded') bg-gray-100 text-gray-800
                @endif">
                {{ $order->status->label() }}
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {{-- Left Column: Order Details & Items --}}
        <div class="lg:col-span-2 space-y-8">
            {{-- Order Items --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Order Items</h2>
                <div class="space-y-4">
                    @foreach($order->items as $item)
                        <div class="flex items-start justify-between border-b border-gray-100 pb-4 last:border-0 last:pb-0">
                            <div class="flex items-start gap-4">
                                <div class="w-16 h-16 bg-gray-100 rounded-lg flex items-center justify-center shrink-0">
                                    <x-heroicon-o-cube class="w-8 h-8 text-gray-400" />
                                </div>
                                <div>
                                    <h3 class="font-medium text-gray-900">
                                        {{ $item->product_name_snapshot ?? ($item->product?->name ? trans_field($item->product->name) : '—') }}
                                    </h3>
                                    <p class="text-xs font-mono text-gray-500 mt-0.5">{{ $item->oem_number_snapshot }}</p>
                                    <p class="text-sm text-gray-600 mt-1">
                                        {{ $item->manufacturer_snapshot }}
                                        @if($item->condition_snapshot)
                                            · <span class="capitalize">{{ $item->condition_snapshot }}</span>
                                        @endif
                                    </p>
                                    <p class="text-sm text-gray-600 mt-1">{{ $item->quantity }} × {{ format_money($item->unit_price) }}</p>
                                </div>
                            </div>
                            <div class="text-right shrink-0 ml-4">
                                <p class="font-semibold text-gray-900">{{ format_money($item->total_price) }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">VAT incl.</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Order Totals --}}
                <div class="mt-8 pt-6 border-t border-gray-200">
                    <div class="space-y-3 max-w-xs ml-auto">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Subtotal</span>
                            <span class="text-gray-900">{{ format_money($order->subtotal) }}</span>
                        </div>
                        @if($order->discount_amount > 0)
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Discount</span>
                                <span class="text-green-600">-{{ format_money($order->discount_amount) }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Shipping</span>
                            <span class="text-gray-900">{{ format_money($order->shipping_cost) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">VAT</span>
                            <span class="text-gray-900">{{ format_money($order->vat_amount) }}</span>
                        </div>
                        <div class="flex justify-between text-lg font-semibold pt-3 border-t border-gray-200">
                            <span class="text-gray-900">Total</span>
                            <span class="text-gray-900">{{ format_money($order->grand_total) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Status History --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Status History</h2>
                <div class="space-y-4">
                    @forelse($order->statusHistory as $history)
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center shrink-0">
                                <x-heroicon-o-arrow-path class="w-4 h-4 text-gray-500" />
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <p class="font-medium text-gray-900">
                                        Changed from {{ $history->old_status->label() }} to {{ $history->new_status->label() }}
                                    </p>
                                    <span class="text-sm text-gray-500">{{ $history->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="text-sm text-gray-600 mt-1">
                                    By {{ $history->admin?->name ?? 'System' }}
                                </p>
                                @if($history->note)
                                    <p class="text-sm text-gray-700 mt-2 bg-gray-50 p-3 rounded-lg">{{ $history->note }}</p>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-gray-500 text-center py-4">No status history recorded.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Right Column: Actions & Info --}}
        <div class="space-y-8">
            {{-- Update Status --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Update Status</h2>
                <form method="POST" action="{{ route('admin.orders.update-status', $order) }}">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">New Status</label>
                            <select id="status" name="status" required
                                    class="w-full rounded-lg border-gray-300 text-sm">
                                @foreach($statuses as $status)
                                    <option value="{{ $status->value }}" {{ $order->status->value === $status->value ? 'selected' : '' }}>
                                        {{ $status->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="note" class="block text-sm font-medium text-gray-700 mb-1">Note (Optional)</label>
                            <textarea id="note" name="note" rows="3"
                                      class="w-full rounded-lg border-gray-300 text-sm"
                                      placeholder="Add a note about this status change..."></textarea>
                        </div>

                        <div class="flex items-center gap-2">
                            <input type="checkbox" id="notify_customer" name="notify_customer" value="1" class="rounded border-gray-300">
                            <label for="notify_customer" class="text-sm text-gray-700">Notify customer via email</label>
                        </div>

                        <button type="submit"
                                class="w-full py-2.5 bg-navy text-white rounded-lg text-sm font-medium hover:bg-navy/90">
                            Update Status
                        </button>
                    </div>
                </form>
            </div>

            {{-- Tracking Information --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Tracking Information</h2>
                @if($order->tracking_number)
                    <div class="space-y-3 mb-6">
                        <div>
                            <p class="text-sm text-gray-600">Tracking Number</p>
                            <p class="font-medium text-gray-900">{{ $order->tracking_number }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Carrier</p>
                            <p class="font-medium text-gray-900">{{ $order->carrier ?? 'Not specified' }}</p>
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.orders.update-tracking', $order) }}">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label for="tracking_number" class="block text-sm font-medium text-gray-700 mb-1">Tracking Number</label>
                            <input type="text" id="tracking_number" name="tracking_number"
                                   value="{{ old('tracking_number', $order->tracking_number) }}"
                                   class="w-full rounded-lg border-gray-300 text-sm">
                        </div>

                        <div>
                            <label for="carrier" class="block text-sm font-medium text-gray-700 mb-1">Carrier</label>
                            <select id="carrier" name="carrier" class="w-full rounded-lg border-gray-300 text-sm">
                                <option value="">Select carrier</option>
                                @foreach($carriers as $carrier)
                                    <option value="{{ $carrier->name }}" {{ $order->carrier === $carrier->name ? 'selected' : '' }}>
                                        {{ $carrier->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="tracking_note" class="block text-sm font-medium text-gray-700 mb-1">Note (Optional)</label>
                            <textarea id="tracking_note" name="note" rows="2"
                                      class="w-full rounded-lg border-gray-300 text-sm"
                                      placeholder="Add a note about this tracking update..."></textarea>
                        </div>

                        <div class="flex items-center gap-2">
                            <input type="checkbox" id="tracking_notify" name="notify_customer" value="1" class="rounded border-gray-300">
                            <label for="tracking_notify" class="text-sm text-gray-700">Notify customer</label>
                        </div>

                        <button type="submit"
                                class="w-full py-2.5 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-900">
                            {{ $order->tracking_number ? 'Update Tracking' : 'Add Tracking' }}
                        </button>
                    </div>
                </form>
            </div>

            {{-- Customer & Shipping --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Customer & Shipping</h2>
                <div class="space-y-4">
                    <div>
                        <p class="text-sm text-gray-600">Customer</p>
                        <p class="font-medium text-gray-900">
                            @if($order->user)
                                {{ $order->user->email }} (Registered)
                            @else
                                {{ $order->guest_email }} (Guest)
                            @endif
                        </p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-600">Shipping Address</p>
                        <p class="font-medium text-gray-900 mt-1">{{ $order->shipping_name }}</p>
                        <p class="text-gray-600">{{ $order->shipping_address_line1 }}</p>
                        <p class="text-gray-600">{{ $order->shipping_city }}, {{ $order->shipping_postal_code }}</p>
                        <p class="text-gray-600">{{ $order->shipping_country_code }}</p>
                    </div>

                    @if($order->is_b2b)
                        <div class="pt-4 border-t border-gray-100">
                            <p class="text-sm text-gray-600">B2B Details</p>
                            <p class="font-medium text-gray-900">{{ $order->company_name }}</p>
                            <p class="text-gray-600">VAT: {{ $order->vat_number }}</p>
                            @if($order->vat_exempt)
                                <span class="inline-block mt-1 px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded">VAT Exempt</span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            {{-- Add Note --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Add Note</h2>
                <form method="POST" action="{{ route('admin.orders.add-note', $order) }}">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label for="note" class="block text-sm font-medium text-gray-700 mb-1">Note</label>
                            <textarea id="note" name="note" rows="3" required
                                      class="w-full rounded-lg border-gray-300 text-sm"
                                      placeholder="Add a note about this order..."></textarea>
                        </div>

                        <div class="flex items-center gap-2">
                            <input type="checkbox" id="is_internal" name="is_internal" value="1" class="rounded border-gray-300">
                            <label for="is_internal" class="text-sm text-gray-700">Internal note (not visible to customer)</label>
                        </div>

                        <button type="submit"
                                class="w-full py-2.5 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-900">
                            Add Note
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection