@extends('layouts.admin')

@section('title', 'Search Analytics — OEMHub')

@section('content')
<div class="px-6 py-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Search Analytics</h1>
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
        <form method="GET" action="{{ route('admin.reports.search') }}" class="flex items-center gap-4">
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

    <!-- Search Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total Searches</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $totalSearches ?? 0 }}</p>
                </div>
                <div class="p-3 bg-blue-50 rounded-lg">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Successful Searches</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $successfulSearches ?? 0 }}</p>
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
                    <p class="text-sm font-medium text-gray-600">Failed Searches</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $failedSearches ?? 0 }}</p>
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
                    <p class="text-sm font-medium text-gray-600">Success Rate</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ number_format($successRate ?? 0, 1) }}%</p>
                </div>
                <div class="p-3 bg-amber-50 rounded-lg">
                    <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Volume Chart Placeholder -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Search Volume Trend</h3>
        <div class="h-64 flex items-center justify-center border border-dashed border-gray-300 rounded-lg">
            <p class="text-gray-500">Search volume chart would be displayed here</p>
        </div>
        @if($searchVolume->isNotEmpty())
            <div class="mt-4 text-sm text-gray-600">
                <p>Showing {{ $searchVolume->count() }} days of data from {{ $searchVolume->first()->date }} to {{ $searchVolume->last()->date }}</p>
                <p>Average daily searches: {{ number_format($searchVolume->avg('searches') ?? 0, 1) }}</p>
            </div>
        @endif
    </div>

    <!-- Search Conversion -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Search to Order Conversion</h3>
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">Total Searches</span>
                    <span class="text-sm font-bold text-gray-900">{{ $searchToOrder->total_searches ?? 0 }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">Resulting Orders</span>
                    <span class="text-sm font-bold text-gray-900">{{ $searchToOrder->resulting_orders ?? 0 }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">Conversion Rate</span>
                    <span class="text-sm font-bold text-gray-900">{{ number_format($searchToOrder->conversion_rate ?? 0, 2) }}%</span>
                </div>
            </div>
            <div class="mt-4 pt-4 border-t border-gray-200">
                <p class="text-xs text-gray-500">Orders placed within 24 hours of a search</p>
            </div>
        </div>

        <!-- Top Searches -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Top 10 Search Queries</h3>
            <div class="space-y-3">
                @forelse($topSearches->take(10) as $search)
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $search->search_query }}</p>
                            <p class="text-xs text-gray-500">Avg results: {{ number_format($search->avg_results ?? 0, 1) }}</p>
                        </div>
                        <span class="text-sm font-bold text-gray-900">{{ $search->search_count }} searches</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No search data available</p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Full Top Searches Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">All Search Queries</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Search Query</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Search Count</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Results</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Success Rate</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($topSearches as $search)
                        <tr>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $search->search_query }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $search->search_count }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ number_format($search->avg_results ?? 0, 1) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                @if($search->avg_results > 0)
                                    <span class="text-green-600 font-medium">Successful</span>
                                @else
                                    <span class="text-red-600 font-medium">No results</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500">No search data available</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection