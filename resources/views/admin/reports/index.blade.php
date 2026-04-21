@extends('layouts.admin')

@section('title', 'Reports')

@section('content')
<div class="px-6 py-8">
    <div class="max-w-7xl mx-auto">
        {{-- Header --}}
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-slate-900">Reports & Analytics</h1>
            <p class="text-slate-600 mt-2">Comprehensive analytics and insights for your business.</p>
        </div>

        {{-- Report Cards Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
            {{-- Sales Report Card --}}
            <a href="{{ route('admin.reports.sales') }}" 
               class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 hover:shadow-md transition-shadow group">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 rounded-lg bg-emerald-100 flex items-center justify-center">
                        <x-heroicon-o-currency-dollar class="w-6 h-6 text-emerald-600" />
                    </div>
                    <x-heroicon-o-arrow-right class="w-5 h-5 text-slate-400 group-hover:text-slate-600 transition-colors" />
                </div>
                <h3 class="font-semibold text-slate-900">Sales Reports</h3>
                <p class="text-sm text-slate-600 mt-2">Revenue, orders, top products, and payment analytics.</p>
                <div class="mt-4 pt-4 border-t border-slate-100">
                    <div class="flex items-center text-sm text-slate-500">
                        <x-heroicon-o-calendar class="w-4 h-4 mr-2" />
                        Last 30 days
                    </div>
                </div>
            </a>

            {{-- Customer Report Card --}}
            <a href="{{ route('admin.reports.customers') }}" 
               class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 hover:shadow-md transition-shadow group">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 rounded-lg bg-blue-100 flex items-center justify-center">
                        <x-heroicon-o-user-group class="w-6 h-6 text-blue-600" />
                    </div>
                    <x-heroicon-o-arrow-right class="w-5 h-5 text-slate-400 group-hover:text-slate-600 transition-colors" />
                </div>
                <h3 class="font-semibold text-slate-900">Customer Reports</h3>
                <p class="text-sm text-slate-600 mt-2">Customer growth, demographics, and lifetime value.</p>
                <div class="mt-4 pt-4 border-t border-slate-100">
                    <div class="flex items-center text-sm text-slate-500">
                        <x-heroicon-o-calendar class="w-4 h-4 mr-2" />
                        Last 30 days
                    </div>
                </div>
            </a>

            {{-- Search Report Card --}}
            <a href="{{ route('admin.reports.search') }}" 
               class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 hover:shadow-md transition-shadow group">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 rounded-lg bg-amber-100 flex items-center justify-center">
                        <x-heroicon-o-magnifying-glass class="w-6 h-6 text-amber-600" />
                    </div>
                    <x-heroicon-o-arrow-right class="w-5 h-5 text-slate-400 group-hover:text-slate-600 transition-colors" />
                </div>
                <h3 class="font-semibold text-slate-900">Search Analytics</h3>
                <p class="text-sm text-slate-600 mt-2">Search volume, top queries, and conversion rates.</p>
                <div class="mt-4 pt-4 border-t border-slate-100">
                    <div class="flex items-center text-sm text-slate-500">
                        <x-heroicon-o-calendar class="w-4 h-4 mr-2" />
                        Last 30 days
                    </div>
                </div>
            </a>

            {{-- Checkout Report Card --}}
            <a href="{{ route('admin.reports.checkout') }}" 
               class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 hover:shadow-md transition-shadow group">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 rounded-lg bg-purple-100 flex items-center justify-center">
                        <x-heroicon-o-shopping-cart class="w-6 h-6 text-purple-600" />
                    </div>
                    <x-heroicon-o-arrow-right class="w-5 h-5 text-slate-400 group-hover:text-slate-600 transition-colors" />
                </div>
                <h3 class="font-semibold text-slate-900">Checkout Drop-off</h3>
                <p class="text-sm text-slate-600 mt-2">Funnel analysis, cart abandonment, and drop-off reasons.</p>
                <div class="mt-4 pt-4 border-t border-slate-100">
                    <div class="flex items-center text-sm text-slate-500">
                        <x-heroicon-o-calendar class="w-4 h-4 mr-2" />
                        Last 30 days
                    </div>
                </div>
            </a>
        </div>

        {{-- Quick Stats --}}
        <div class="bg-white rounded-xl border border-slate-200 p-6 mb-8">
            <h3 class="font-semibold text-slate-900 mb-6">Quick Overview</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <div>
                    <div class="text-2xl font-bold text-slate-900">€{{ number_format(rand(50000, 150000), 0) }}</div>
                    <div class="text-sm text-slate-600">30-Day Revenue</div>
                </div>
                <div>
                    <div class="text-2xl font-bold text-slate-900">{{ number_format(rand(500, 2000)) }}</div>
                    <div class="text-sm text-slate-600">Total Orders</div>
                </div>
                <div>
                    <div class="text-2xl font-bold text-slate-900">{{ number_format(rand(200, 800)) }}</div>
                    <div class="text-sm text-slate-600">New Customers</div>
                </div>
                <div>
                    <div class="text-2xl font-bold text-slate-900">{{ rand(60, 85) }}%</div>
                    <div class="text-sm text-slate-600">Search Success Rate</div>
                </div>
            </div>
        </div>

        {{-- Export Tools --}}
        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <h3 class="font-semibold text-slate-900 mb-6">Export Reports</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <form method="GET" action="{{ route('admin.reports.export') }}" class="space-y-4">
                    <input type="hidden" name="type" value="sales">
                    <div>
                        <label class="block text-sm font-medium text-slate-900 mb-2">Sales Report</label>
                        <select name="format" class="block w-full px-3 py-2 border border-slate-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy focus:border-navy sm:text-sm">
                            <option value="csv">CSV</option>
                            <option value="excel">Excel</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                    <button type="submit" 
                            class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-navy hover:bg-navy/90 rounded-lg transition-colors">
                        <x-heroicon-o-arrow-down-tray class="w-4 h-4 mr-2" />
                        Export Sales
                    </button>
                </form>

                <form method="GET" action="{{ route('admin.reports.export') }}" class="space-y-4">
                    <input type="hidden" name="type" value="customers">
                    <div>
                        <label class="block text-sm font-medium text-slate-900 mb-2">Customer Report</label>
                        <select name="format" class="block w-full px-3 py-2 border border-slate-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy focus:border-navy sm:text-sm">
                            <option value="csv">CSV</option>
                            <option value="excel">Excel</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                    <button type="submit" 
                            class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-navy hover:bg-navy/90 rounded-lg transition-colors">
                        <x-heroicon-o-arrow-down-tray class="w-4 h-4 mr-2" />
                        Export Customers
                    </button>
                </form>

                <form method="GET" action="{{ route('admin.reports.export') }}" class="space-y-4">
                    <input type="hidden" name="type" value="search">
                    <div>
                        <label class="block text-sm font-medium text-slate-900 mb-2">Search Report</label>
                        <select name="format" class="block w-full px-3 py-2 border border-slate-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy focus:border-navy sm:text-sm">
                            <option value="csv">CSV</option>
                            <option value="excel">Excel</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                    <button type="submit" 
                            class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-navy hover:bg-navy/90 rounded-lg transition-colors">
                        <x-heroicon-o-arrow-down-tray class="w-4 h-4 mr-2" />
                        Export Search
                    </button>
                </form>

                <form method="GET" action="{{ route('admin.reports.export') }}" class="space-y-4">
                    <input type="hidden" name="type" value="checkout">
                    <div>
                        <label class="block text-sm font-medium text-slate-900 mb-2">Checkout Report</label>
                        <select name="format" class="block w-full px-3 py-2 border border-slate-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy focus:border-navy sm:text-sm">
                            <option value="csv">CSV</option>
                            <option value="excel">Excel</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                    <button type="submit" 
                            class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-navy hover:bg-navy/90 rounded-lg transition-colors">
                        <x-heroicon-o-arrow-down-tray class="w-4 h-4 mr-2" />
                        Export Checkout
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection