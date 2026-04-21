@extends('layouts.admin')

@section('title', 'Refund Management')

@section('content')
<div class="px-6 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Refund Management</h1>
            <p class="text-gray-600 mt-1">Review and process customer refund requests</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.refunds.export', request()->query()) }}"
               class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                Export CSV
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
        <form method="GET" action="{{ route('admin.refunds.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select id="status" name="status" class="w-full rounded-lg border-gray-300 text-sm">
                    <option value="all" {{ request('status') == 'all' || !request('status') ? 'selected' : '' }}>All Statuses</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->value }}" {{ request('status') == $status->value ? 'selected' : '' }}>
                            {{ ucfirst($status->value) }}
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
                <a href="{{ route('admin.refunds.index') }}"
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

    {{-- Refunds Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Refund ID
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Order
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Customer
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Amount
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Date
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($refunds as $refund)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                #{{ $refund->id }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900">{{ $refund->order->order_number }}</div>
                                <div class="text-sm text-gray-500">{{ format_money($refund->order->grand_total) }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    {{ $refund->user->email ?? 'N/A' }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ format_money($refund->amount_requested) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($refund->status->value === 'pending') bg-yellow-100 text-yellow-800
                                    @elseif($refund->status->value === 'approved') bg-green-100 text-green-800
                                    @elseif($refund->status->value === 'rejected') bg-red-100 text-red-800
                                    @elseif($refund->status->value === 'processed') bg-blue-100 text-blue-800
                                    @endif">
                                    {{ ucfirst($refund->status->value) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $refund->created_at->format('M d, Y') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="{{ route('admin.refunds.show', $refund) }}"
                                   class="text-navy hover:text-navy/80 inline-flex items-center gap-1">
                                    <x-heroicon-o-eye class="w-4 h-4" />
                                    Review
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                <x-heroicon-o-receipt-refund class="w-12 h-12 mx-auto text-gray-300 mb-3" />
                                <p class="text-lg font-medium text-gray-900">No refund requests found</p>
                                <p class="text-gray-600 mt-1">Try adjusting your filters or check back later.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($refunds->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $refunds->withQueryString()->links() }}
            </div>
        @endif
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mt-6">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total Requests</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ $refunds->total() }}</p>
                </div>
                <div class="p-3 bg-blue-50 rounded-lg">
                    <x-heroicon-o-receipt-refund class="w-6 h-6 text-blue-600" />
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Pending</p>
                    <p class="text-2xl font-bold text-yellow-600 mt-1">
                        {{ \App\Models\RefundRequest::where('status', \App\Enums\RefundStatus::Pending)->count() }}
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
                    <p class="text-sm font-medium text-gray-600">Approved</p>
                    <p class="text-2xl font-bold text-green-600 mt-1">
                        {{ \App\Models\RefundRequest::where('status', \App\Enums\RefundStatus::Approved)->count() }}
                    </p>
                </div>
                <div class="p-3 bg-green-50 rounded-lg">
                    <x-heroicon-o-check-circle class="w-6 h-6 text-green-600" />
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Processed</p>
                    <p class="text-2xl font-bold text-blue-600 mt-1">
                        {{ \App\Models\RefundRequest::where('status', \App\Enums\RefundStatus::Processed)->count() }}
                    </p>
                </div>
                <div class="p-3 bg-blue-50 rounded-lg">
                    <x-heroicon-o-banknotes class="w-6 h-6 text-blue-600" />
                </div>
            </div>
        </div>
    </div>
</div>
@endsection