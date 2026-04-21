@extends('layouts.admin')

@section('title', 'Customer Reports — OEMHub')

@section('content')
<div class="px-6 py-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Customer Reports</h1>
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
        <form method="GET" action="{{ route('admin.reports.customers') }}" class="flex items-center gap-4">
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

    <!-- Customer Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total Customers</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $totalCustomers ?? 0 }}</p>
                </div>
                <div class="p-3 bg-blue-50 rounded-lg">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5 0H21" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">New Customers</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $newCount ?? 0 }}</p>
                </div>
                <div class="p-3 bg-green-50 rounded-lg">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Repeat Customers</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $repeatCount ?? 0 }}</p>
                </div>
                <div class="p-3 bg-purple-50 rounded-lg">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Repeat Rate</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">
                        @if($totalCustomers > 0)
                            {{ number_format(($repeatCount / $totalCustomers) * 100, 1) }}%
                        @else
                            0%
                        @endif
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

    <!-- Customer Growth Chart Placeholder -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Customer Growth</h3>
        <div class="h-64 flex items-center justify-center border border-dashed border-gray-300 rounded-lg">
            <p class="text-gray-500">Customer growth chart would be displayed here</p>
        </div>
        @if($customerGrowth->isNotEmpty())
            <div class="mt-4 text-sm text-gray-600">
                <p>Showing {{ $customerGrowth->count() }} days of data from {{ $customerGrowth->first()->date }} to {{ $customerGrowth->last()->date }}</p>
                <p>Total new customers: {{ $customerGrowth->sum('new_customers') }}</p>
            </div>
        @endif
    </div>

    <!-- Customer Demographics -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Customer Status Distribution</h3>
            <div class="space-y-3">
                @forelse($customerStatus as $status)
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700">
                            {{ $status->is_active ? 'Active' : 'Inactive' }} Users
                        </span>
                        <span class="text-sm font-bold text-gray-900">{{ $status->count }} customers</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No status data available</p>
                @endforelse
            </div>
        </div>

        <!-- Top Customers by Lifetime Value -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Customers by Lifetime Value</h3>
            <div class="space-y-3">
                @forelse($customerLTV->take(5) as $customer)
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $customer->email }}</p>
                            <p class="text-xs text-gray-500">{{ $customer->total_orders }} orders</p>
                        </div>
                        <span class="text-sm font-bold text-gray-900">${{ number_format($customer->total_spent, 2) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No customer LTV data available</p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Full Customer LTV Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Customer Lifetime Value Details</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Orders</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Spent</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Order</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($customerLTV as $customer)
                        <tr>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $customer->email }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $customer->total_orders }}</td>
                            <td class="px-4 py-3 text-sm font-bold text-gray-900">${{ number_format($customer->total_spent, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $customer->last_order_date ? \Carbon\Carbon::parse($customer->last_order_date)->format('M d, Y') : 'N/A' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500">No customer data available</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection