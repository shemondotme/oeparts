@extends('layouts.admin')

@section('title', 'Reports')
@section('page_title', 'Reports')

@php
    $cards = [
        ['title' => 'Sales Reports', 'description' => 'Revenue, orders, top products, and payment analytics.', 'route' => route('admin.reports.sales'), 'icon' => 'heroicon-o-banknotes', 'type' => 'sales'],
        ['title' => 'Customer Reports', 'description' => 'Customer growth, activity state, and lifetime value.', 'route' => route('admin.reports.customers'), 'icon' => 'heroicon-o-users', 'type' => 'customers'],
        ['title' => 'Search Analytics', 'description' => 'Search volume, top queries, zero-result terms, and conversion.', 'route' => route('admin.reports.search'), 'icon' => 'heroicon-o-magnifying-glass', 'type' => 'search'],
        ['title' => 'Checkout Drop-off', 'description' => 'Cart activity and order conversion trends.', 'route' => route('admin.reports.checkout'), 'icon' => 'heroicon-o-shopping-cart', 'type' => 'checkout'],
    ];
@endphp

@section('content')
<div class="space-y-8">
    <x-admin.card title="Reports & Analytics" eyebrow="§ Analytics · Command Center">
        <p class="text-sm text-ink-muted">
            Real operational reports are available as pages and CSV exports. Unsupported fake Excel/PDF actions have been removed.
        </p>
    </x-admin.card>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4">
        @foreach($cards as $card)
            <a href="{{ $card['route'] }}" class="bp-card block overflow-hidden transition-transform hover:-translate-y-0.5">
                <header class="bp-card-header flex items-center justify-between">
                    <x-dynamic-component :component="$card['icon']" class="h-5 w-5 text-ink" />
                    <x-heroicon-o-arrow-right class="h-4 w-4 text-ink-muted" />
                </header>
                <div class="p-5">
                    <h3 class="font-display text-lg font-bold text-ink">{{ $card['title'] }}</h3>
                    <p class="mt-2 text-sm text-ink-muted">{{ $card['description'] }}</p>
                    <p class="mt-4 border-t border-rule pt-4 font-mono text-xs uppercase tracking-[0.16em] text-ink-muted">Last 30 days</p>
                </div>
            </a>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat-card title="30-Day Revenue" :value="format_money($quickStats['revenue'])" icon="heroicon-o-banknotes" />
        <x-admin.stat-card title="Orders" :value="number_format($quickStats['orders'])" icon="heroicon-o-shopping-bag" />
        <x-admin.stat-card title="New Customers" :value="number_format($quickStats['customers'])" icon="heroicon-o-users" />
        <x-admin.stat-card title="Search Success" :value="$quickStats['search_success_rate'] . '%'" icon="heroicon-o-magnifying-glass" />
    </div>

    <x-admin.card title="Export Reports" eyebrow="§ Export · CSV">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach($cards as $card)
                <form method="GET" action="{{ route('admin.reports.export') }}" class="border border-rule bg-ivory-alt p-4">
                    <input type="hidden" name="type" value="{{ $card['type'] }}">
                    <input type="hidden" name="format" value="csv">
                    <p class="font-display text-base font-bold text-ink">{{ $card['title'] }}</p>
                    <p class="mt-1 text-sm text-ink-muted">Download current CSV export.</p>
                    <button type="submit" class="bp-btn-primary mt-4 w-full">
                        <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                        Export CSV
                    </button>
                </form>
            @endforeach
        </div>
    </x-admin.card>
</div>
@endsection
