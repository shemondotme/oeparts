@extends('layouts.admin')

@section('title', 'Sales Report — OEMHub')

@section('content')
<div class="px-6 py-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Sales Report</h1>
            <p class="text-sm text-gray-600 mt-1">
                {{ $startDate->format('M d, Y') }} – {{ $endDate->format('M d, Y') }}
            </p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.reports.index') }}" class="btn btn-secondary">
                Back to Reports
            </a>
        </div>
    </div>

    <!-- Date Range Selector -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <form method="GET" action="{{ route('admin.reports.sales') }}" class="flex items-center gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                <select name="date_range" class="form-select" onchange="this.form.submit()">
                    @foreach($dateRanges as $value => $label)
                        <option value="{{ $value }}" {{ $dateRange == $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2">
                <input type="date" name="start_date" value="{{ request('start_date', $startDate->format('Y-m-d')) }}" class="form-input">
                <span class="text-gray-500">to</span>
                <input type="date" name="end_date" value="{{ request('end_date', $endDate->format('Y-m-d')) }}" class="form-input">
                <button type="submit" class="btn btn-primary">Apply</button>
            </div>
        </form>
    </div>

    <!-- Sales Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total Orders</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $salesSummary->total_orders ?? 0 }}</p>
                </div>
                <div class="p-3 bg-blue-50 rounded-lg">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total Revenue</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">${{ number_format($salesSummary->total_revenue ?? 0, 2) }}</p>
                </div>
                <div class="p-3 bg-green-50 rounded-lg">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Avg Order Value</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">${{ number_format($salesSummary->average_order_value ?? 0, 2) }}</p>
                </div>
                <div class="p-3 bg-purple-50 rounded-lg">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Completed Revenue</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">${{ number_format($salesSummary->completed_revenue ?? 0, 2) }}</p>
                </div>
                <div class="p-3 bg-amber-50 rounded-lg">
                    <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Revenue by Status -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Revenue by Order Status</h3>
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">Delivered</span>
                    <span class="text-sm font-bold text-gray-900">${{ number_format($salesSummary->completed_revenue ?? 0, 2) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">Pending</span>
                    <span class="text-sm font-bold text-gray-900">${{ number_format($salesSummary->pending_revenue ?? 0, 2) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">Cancelled</span>
                    <span class="text-sm font-bold text-gray-900">${{ number_format($salesSummary->cancelled_revenue ?? 0, 2) }}</span>
                </div>
            </div>
        </div>

        <!-- Payment Methods -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Payment Methods</h3>
            <div class="space-y-4">
                @forelse($paymentMethods as $method)
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700">
                            @if($method->payment_method instanceof \App\Enums\PaymentMethod)
                                {{ $method->payment_method->label() }}
                            @else
                                {{ ucfirst($method->payment_method) }}
                            @endif
                        </span>
                        <div class="flex items-center gap-4">
                            <span class="text-sm text-gray-600">{{ $method->count }} orders</span>
                            <span class="text-sm font-bold text-gray-900">${{ number_format($method->revenue, 2) }}</span>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No payment data available</p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Top Products -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Products by Revenue</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">OEM Number</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Units Sold</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Quantity</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($topProducts as $product)
                        <tr>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $product->oem_number }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ is_array($product->name) ? ($product->name['en'] ?? 'N/A') : $product->name }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $product->units_sold }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $product->total_quantity }}</td>
                            <td class="px-4 py-3 text-sm font-bold text-gray-900">${{ number_format($product->total_revenue, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">No product sales data available</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Daily Sales Chart Placeholder -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Daily Sales Trend</h3>
        <div class="h-64 flex items-center justify-center border border-dashed border-gray-300 rounded-lg">
            <p class="text-gray-500">Sales chart would be displayed here</p>
        </div>
        @if($dailySales->isNotEmpty())
            <div class="mt-4 text-sm text-gray-600">
                <p>Showing {{ $dailySales->count() }} days of data from {{ $dailySales->first()->date }} to {{ $dailySales->last()->date }}</p>
            </div>
        @endif
    </div>
</div>
@endsection