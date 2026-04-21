@extends('layouts.admin')

@section('title', 'Refund Request #' . $refund->id)

@section('content')
<div class="px-6 py-8">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.refunds.index') }}" class="text-gray-500 hover:text-gray-700">
                    <x-heroicon-o-arrow-left class="w-5 h-5" />
                </a>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Refund Request #{{ $refund->id }}</h1>
                    <p class="text-gray-600 mt-1">
                        Submitted on {{ $refund->created_at->format('F j, Y \a\t H:i') }}
                        for Order #{{ $refund->order->order_number }}
                    </p>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                @if($refund->status->value === 'pending') bg-yellow-100 text-yellow-800
                @elseif($refund->status->value === 'approved') bg-green-100 text-green-800
                @elseif($refund->status->value === 'rejected') bg-red-100 text-red-800
                @elseif($refund->status->value === 'processed') bg-blue-100 text-blue-800
                @endif">
                {{ ucfirst($refund->status->value) }}
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {{-- Left Column: Refund Details --}}
        <div class="lg:col-span-2 space-y-8">
            {{-- Refund Details --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Refund Details</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-gray-600">Order Number</p>
                        <p class="font-medium text-gray-900 mt-1">
                            <a href="{{ route('admin.orders.show', $refund->order) }}" class="text-navy hover:underline">
                                {{ $refund->order->order_number }}
                            </a>
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Order Total</p>
                        <p class="font-medium text-gray-900 mt-1">{{ format_money($refund->order->grand_total) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Amount Requested</p>
                        <p class="font-medium text-gray-900 mt-1">{{ format_money($refund->amount_requested) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Customer</p>
                        <p class="font-medium text-gray-900 mt-1">{{ $refund->user->email ?? 'N/A' }}</p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-sm text-gray-600">Reason</p>
                        <p class="font-medium text-gray-900 mt-1">{{ $refund->reason }}</p>
                    </div>
                    @if($refund->return_images)
                        <div class="md:col-span-2">
                            <p class="text-sm text-gray-600">Return Images</p>
                            <div class="flex flex-wrap gap-3 mt-2">
                                @foreach($refund->return_images as $image)
                                    <a href="{{ $image }}" target="_blank" class="block w-24 h-24">
                                        <img src="{{ $image }}" alt="Return image" class="w-full h-full object-cover rounded-lg border border-gray-200">
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Order Items --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Order Items</h2>
                <div class="space-y-4">
                    @foreach($refund->order->items as $item)
                        <div class="flex items-start justify-between border-b border-gray-100 pb-4 last:border-0 last:pb-0">
                            <div class="flex items-start gap-4">
                                <div class="w-16 h-16 bg-gray-100 rounded-lg flex items-center justify-center">
                                    <x-heroicon-o-cube class="w-8 h-8 text-gray-400" />
                                </div>
                                <div>
                                    <h3 class="font-medium text-gray-900">
                                        {{ $item->product->manufacturer->name ?? 'Unknown' }} - {{ $item->product->oem_number }}
                                    </h3>
                                    <p class="text-sm text-gray-600 mt-1">
                                        Condition: {{ $item->product->condition->value }}
                                    </p>
                                    <p class="text-sm text-gray-600 mt-1">Qty: {{ $item->quantity }} × {{ format_money($item->unit_price) }}</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="font-medium text-gray-900">{{ format_money($item->total_price) }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Admin Notes --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Admin Notes</h2>
                @if($refund->admin_note)
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-gray-700 whitespace-pre-line">{{ $refund->admin_note }}</p>
                        @if($refund->processed_at)
                            <p class="text-sm text-gray-500 mt-2">Processed on {{ $refund->processed_at->format('F j, Y \a\t H:i') }}</p>
                        @endif
                    </div>
                @else
                    <p class="text-gray-500 text-center py-4">No admin notes yet.</p>
                @endif
            </div>
        </div>

        {{-- Right Column: Actions --}}
        <div class="space-y-8">
            {{-- Update Status --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Update Status</h2>
                <form method="POST" action="{{ route('admin.refunds.update-status', $refund) }}">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">New Status</label>
                            <select id="status" name="status" required
                                    class="w-full rounded-lg border-gray-300 text-sm">
                                @foreach($statuses as $status)
                                    <option value="{{ $status->value }}" {{ $refund->status->value === $status->value ? 'selected' : '' }}>
                                        {{ ucfirst($status->value) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="admin_note" class="block text-sm font-medium text-gray-700 mb-1">Admin Note</label>
                            <textarea id="admin_note" name="admin_note" rows="4" required
                                      class="w-full rounded-lg border-gray-300 text-sm"
                                      placeholder="Explain your decision...">{{ old('admin_note', $refund->admin_note) }}</textarea>
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

            {{-- Process Refund --}}
            @if($refund->status->value === 'approved')
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Process Refund</h2>
                    <p class="text-sm text-gray-600 mb-4">Mark this refund as processed and update the order status to "Refunded".</p>
                    <form method="POST" action="{{ route('admin.refunds.process', $refund) }}">
                        @csrf
                        <div class="space-y-4">
                            <div>
                                <label for="refund_amount" class="block text-sm font-medium text-gray-700 mb-1">Refund Amount (€)</label>
                                <input type="text" id="refund_amount" name="amount" inputmode="decimal" required
                                       value="{{ old('amount', $refund->amount_requested > 0 ? number_format($refund->amount_requested, 2, '.', '') : number_format($refund->order->grand_total, 2, '.', '')) }}"
                                       class="w-full rounded-lg border-gray-300 text-sm" />
                            </div>
                            <div>
                                <label for="processed_note" class="block text-sm font-medium text-gray-700 mb-1">Processing Note</label>
                                <textarea id="processed_note" name="processed_note" rows="3" required
                                          class="w-full rounded-lg border-gray-300 text-sm"
                                          placeholder="Add details about the refund processing..."></textarea>
                            </div>

                            <div class="flex items-center gap-2">
                                <input type="checkbox" id="process_notify" name="notify_customer" value="1" class="rounded border-gray-300">
                                <label for="process_notify" class="text-sm text-gray-700">Notify customer</label>
                            </div>

                            <button type="submit"
                                    class="w-full py-2.5 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700">
                                Mark as Processed
                            </button>
                        </div>
                    </form>
                </div>
            @endif

            {{-- Order Summary --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Order Summary</h2>
                <div class="space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Order Status</span>
                        <span class="font-medium text-gray-900">{{ $refund->order->status->label() }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Payment Method</span>
                        <span class="font-medium text-gray-900">{{ ucfirst($refund->order->payment_method->value) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Payment Status</span>
                        <span class="font-medium text-gray-900">{{ ucfirst($refund->order->payment_status->value) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Order Date</span>
                        <span class="font-medium text-gray-900">{{ $refund->order->created_at->format('M d, Y') }}</span>
                    </div>
                </div>

                <div class="mt-6 pt-6 border-t border-gray-200">
                    <a href="{{ route('admin.orders.show', $refund->order) }}"
                       class="w-full py-2.5 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 flex items-center justify-center gap-2">
                        <x-heroicon-o-arrow-right-circle class="w-4 h-4" />
                        View Full Order
                    </a>
                </div>
            </div>

            {{-- Customer Info --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Customer Information</h2>
                <div class="space-y-3">
                    <div>
                        <p class="text-sm text-gray-600">Email</p>
                        <p class="font-medium text-gray-900">{{ $refund->user->email ?? 'N/A' }}</p>
                    </div>
                    @if($refund->user)
                        <div>
                            <p class="text-sm text-gray-600">Account Created</p>
                            <p class="font-medium text-gray-900">{{ $refund->user->created_at->format('M d, Y') }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Total Orders</p>
                            <p class="font-medium text-gray-900">{{ $refund->user->orders()->count() }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection