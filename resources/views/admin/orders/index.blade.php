@extends('layouts.admin')

@section('title', 'Order Management')

@section('content')
<div class="px-6 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Order Management</h1>
            <p class="text-gray-600 mt-1">View, filter, and manage customer orders</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.orders.export', request()->query()) }}"
               class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                Export CSV
            </a>
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total Orders</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ $orders->total() }}</p>
                </div>
                <div class="p-3 bg-blue-50 rounded-lg">
                    <x-heroicon-o-shopping-bag class="w-6 h-6 text-blue-600" />
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Pending</p>
                    <p class="text-2xl font-bold text-yellow-600 mt-1">
                        {{ \App\Models\Order::where('status', \App\Enums\OrderStatus::Pending)->count() }}
                    </p>
                </div>
                <div class="p-3 bg-yellow-50 rounded-lg">
                    <x-heroicon-o-clock class="w-6 h-6 text-yellow-600" />
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Processing</p>
                    <p class="text-2xl font-bold text-indigo-600 mt-1">
                        {{ \App\Models\Order::where('status', \App\Enums\OrderStatus::Processing)->count() }}
                    </p>
                </div>
                <div class="p-3 bg-indigo-50 rounded-lg">
                    <x-heroicon-o-cog-6-tooth class="w-6 h-6 text-indigo-600" />
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Shipped</p>
                    <p class="text-2xl font-bold text-purple-600 mt-1">
                        {{ \App\Models\Order::where('status', \App\Enums\OrderStatus::Shipped)->count() }}
                    </p>
                </div>
                <div class="p-3 bg-purple-50 rounded-lg">
                    <x-heroicon-o-truck class="w-6 h-6 text-purple-600" />
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
        <form method="GET" action="{{ route('admin.orders.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select id="status" name="status" class="w-full rounded-lg border-gray-300 text-sm">
                    <option value="all" {{ request('status') == 'all' || !request('status') ? 'selected' : '' }}>All Statuses</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->value }}" {{ request('status') == $status->value ? 'selected' : '' }}>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="order_number" class="block text-sm font-medium text-gray-700 mb-1">Order Number</label>
                <input type="text" id="order_number" name="order_number" value="{{ request('order_number') }}"
                       placeholder="e.g., ORD-12345"
                       class="w-full rounded-lg border-gray-300 text-sm">
            </div>

            <div>
                <label for="customer_email" class="block text-sm font-medium text-gray-700 mb-1">Customer Email</label>
                <input type="email" id="customer_email" name="customer_email" value="{{ request('customer_email') }}"
                       placeholder="customer@example.com"
                       class="w-full rounded-lg border-gray-300 text-sm">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                    <input type="date" id="date_from" name="date_from" value="{{ request('date_from') }}"
                           class="w-full rounded-lg border-gray-300 text-sm">
                </div>
                <div>
                    <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                    <input type="date" id="date_to" name="date_to" value="{{ request('date_to') }}"
                           class="w-full rounded-lg border-gray-300 text-sm">
                </div>
            </div>

            <div class="md:col-span-4 flex items-center justify-end gap-3 pt-2">
                <label class="flex items-center gap-1 text-sm text-gray-600">
                    <input type="checkbox" name="urgent" value="1" {{ request()->boolean('urgent') ? 'checked' : '' }}
                           class="rounded border-gray-300" onchange="this.form.submit()">
                    Urgent only
                </label>
                <a href="{{ route('admin.orders.index') }}"
                   class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Reset
                </a>
                <button type="submit"
                        class="px-4 py-2 bg-navy text-white rounded-lg text-sm font-medium hover:bg-navy/90">
                    Apply Filters
                </button>
            </div>
        </form>
    </div>

    {{-- Orders Table --}}
    <form method="POST" action="{{ route('admin.orders.bulk-status') }}" x-data="{ checked: [] }">
        @csrf
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">

        {{-- Bulk action toolbar (appears when rows selected) --}}
        <div x-show="checked.length > 0" x-cloak
             class="flex items-center gap-3 px-6 py-3 bg-navy/5 border-b border-navy/10">
            <span class="text-sm font-medium text-navy" x-text="checked.length + ' order' + (checked.length > 1 ? 's' : '') + ' selected'"></span>
            <div class="flex items-center gap-2 ml-auto">
                <select name="status" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm bg-white focus:ring-navy focus:border-navy">
                    @foreach(\App\Enums\OrderStatus::cases() as $s)
                        <option value="{{ $s->value }}">Set to: {{ $s->label() }}</option>
                    @endforeach
                </select>
                <button type="submit"
                        class="px-3 py-1.5 bg-navy text-white rounded-lg text-sm font-medium hover:bg-navy/90">
                    Apply
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 w-10">
                            <input type="checkbox" class="rounded border-gray-300"
                                   x-on:change="checked = $event.target.checked ? {{ json_encode($orders->pluck('id')->toArray()) }} : []"
                                   :checked="checked.length === {{ $orders->count() }} && {{ $orders->count() }} > 0">
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Order
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Customer
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Date
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Total
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($orders as $order)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input type="checkbox" name="order_ids[]" value="{{ $order->id }}"
                                       class="rounded border-gray-300"
                                       x-model="checked">
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900">{{ $order->order_number }}
                                    @if($order->urgent_processing)
                                        <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">
                                            Urgent
                                        </span>
                                    @endif
                                </div>
                                    <div class="text-sm text-gray-500">{{ $order->items->count() }} {{ Str::plural('item', $order->items->count()) }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    {{ $order->user_id ? $order->user->email : $order->guest_email }}
                                </div>
                                <div class="text-sm text-gray-500">
                                    {{ $order->user_id ? 'Registered' : 'Guest' }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $order->created_at->format('M d, Y · H:i') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
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
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ format_money($order->grand_total) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="{{ route('admin.orders.show', $order) }}"
                                   class="text-navy hover:text-navy/80 inline-flex items-center gap-1">
                                    <x-heroicon-o-eye class="w-4 h-4" />
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                <x-heroicon-o-document-magnifying-glass class="w-12 h-12 mx-auto text-gray-300 mb-3" />
                                <p class="text-lg font-medium text-gray-900">No orders found</p>
                                <p class="text-gray-600 mt-1">Try adjusting your filters or check back later.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($orders->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $orders->withQueryString()->links() }}
            </div>
        @endif
    </div>
    </form>

</div>
@endsection