@extends('layouts.admin')

@section('title', 'Checkout Drop-off — OEMHub')

@section('content')
<div class="px-6 py-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Checkout Drop-off Analysis</h1>
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
        <form method="GET" action="{{ route('admin.reports.checkout') }}" class="flex items-center gap-4">
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

    <!-- Checkout Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total Carts</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $totalCarts ?? 0 }}</p>
                </div>
                <div class="p-3 bg-blue-50 rounded-lg">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Completed Orders</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $totalOrders ?? 0 }}</p>
                </div>
                <div class="p-3 bg-green-50 rounded-lg">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Abandoned Carts</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ ($totalCarts - $totalOrders) ?? 0 }}</p>
                </div>
                <div class="p-3 bg-red-50 rounded-lg">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Abandonment Rate</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">
                        {{ number_format($overallAbandonmentRate ?? 0, 1) }}%
                    </p>
                </div>
                <div class="p-3 bg-amber-50 rounded-lg">
                    <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Checkout Data Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Daily Checkout Performance</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Carts</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completed Orders</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Abandonment Rate</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($checkoutData as $data)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $data->date }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $data->total_carts }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $data->completed_orders }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $data->abandonment_rate > 50 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                                    {{ number_format($data->abandonment_rate, 1) }}%
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No checkout data available for this period</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Order Status Distribution -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Order Status Distribution</h3>
        <div class="space-y-4">
            @php
                // Calculate total count for percentage calculation
                $totalStatusCount = 0;
                if (is_array($checkoutSteps) || $checkoutSteps instanceof \Illuminate\Support\Collection) {
                    foreach ($checkoutSteps as $step) {
                        $totalStatusCount += $step->count;
                    }
                }
            @endphp
            @forelse($checkoutSteps as $step)
                @php
                    $percentage = $totalStatusCount > 0 ? ($step->count / $totalStatusCount) * 100 : 0;
                    // Handle OrderStatus enum - use label() method if available, otherwise convert to string
                    $statusLabel = is_object($step->status) && method_exists($step->status, 'label')
                        ? $step->status->label()
                        : (string) $step->status;
                @endphp
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-sm font-medium text-gray-700">{{ $statusLabel }}</span>
                        <span class="text-sm font-bold text-gray-900">{{ $step->count }} orders ({{ number_format($percentage, 1) }}%)</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                        <div class="bg-blue-600 h-2.5 rounded-full" style="width: {{ $percentage }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500">No order status data available</p>
            @endforelse
        </div>
    </div>

    <!-- Checkout Performance Summary -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Checkout Performance Summary</h3>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">Total Carts Created</span>
                    <span class="text-sm font-bold text-gray-900">{{ $totalCarts ?? 0 }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">Orders Completed</span>
                    <span class="text-sm font-bold text-gray-900">{{ $totalOrders ?? 0 }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">Overall Abandonment Rate</span>
                    <span class="text-sm font-bold text-gray-900">{{ number_format($overallAbandonmentRate ?? 0, 1) }}%</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">Conversion Rate</span>
                    <span class="text-sm font-bold text-gray-900">
                        @php
                            $conversionRate = $totalCarts > 0 ? (($totalOrders / $totalCarts) * 100) : 0;
                        @endphp
                        {{ number_format($conversionRate, 1) }}%
                    </span>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Key Metrics</h3>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">Date Range</span>
                    <span class="text-sm font-bold text-gray-900">{{ $startDate->format('M d, Y') }} – {{ $endDate->format('M d, Y') }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">Days Analyzed</span>
                    <span class="text-sm font-bold text-gray-900">{{ count($checkoutData) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">Average Daily Carts</span>
                    <span class="text-sm font-bold text-gray-900">
                        @php
                            $avgDailyCarts = count($checkoutData) > 0 ? $totalCarts / count($checkoutData) : 0;
                        @endphp
                        {{ number_format($avgDailyCarts, 1) }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">Average Daily Orders</span>
                    <span class="text-sm font-bold text-gray-900">
                        @php
                            $avgDailyOrders = count($checkoutData) > 0 ? $totalOrders / count($checkoutData) : 0;
                        @endphp
                        {{ number_format($avgDailyOrders, 1) }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection