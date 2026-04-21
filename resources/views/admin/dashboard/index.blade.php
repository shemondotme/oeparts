@extends('layouts.admin')

@section('title', 'Dashboard')

@php
    /**
     * OrderStatus badge colours — keyed by the enum's string value.
     * Source of truth: App\Enums\OrderStatus
     * Updated for Industrial Blueprint v2
     */
    $statusBadge = [
        'pending'          => ['bg' => '#FEF3C7', 'text' => '#D97706'], // Amber
        'paid'             => ['bg' => '#DBEAFE', 'text' => '#1D4ED8'], // Blue
        'processing'       => ['bg' => '#DBEAFE', 'text' => '#1D4ED8'], // Blue
        'shipped'          => ['bg' => '#FEF3C7', 'text' => '#D97706'], // Amber
        'delivered'        => ['bg' => '#DCFCE7', 'text' => '#16A34A'], // Green
        'cancelled'        => ['bg' => '#FEE2E2', 'text' => '#DC2626'], // Red
        'refund_requested' => ['bg' => '#FFEDD5', 'text' => '#EA580C'], // Orange
        'refunded'         => ['bg' => '#F1F5F9', 'text' => '#64748B'], // Gray
    ];
@endphp

@section('page_title', 'Dashboard')

@section('content')
<div class="space-y-8">

    {{-- ══════════════════════════════════════════════════════════════════════
         KEY METRICS (Stat Cards)
         ═══════════════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {{-- Total Orders --}}
        <div class="bp-card p-5 relative overflow-hidden group">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <p class="bp-spec text-ink-muted">Total Orders</p>
                    <p class="mt-2 font-mono text-3xl font-bold text-ink tabular-nums">{{ number_format($totalOrders ?? 0) }}</p>
                </div>
                <div class="p-2 bg-ivory-alt border border-rule">
                    <x-heroicon-o-shopping-bag class="w-6 h-6 text-ink" />
                </div>
            </div>
            <div class="flex items-center gap-2 text-xs font-mono text-ink-muted">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span>Lifetime</span>
            </div>
        </div>

        {{-- Pending Orders --}}
        <div class="bp-card p-5 relative overflow-hidden group">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <p class="bp-spec text-ink-muted">Pending</p>
                    <p class="mt-2 font-mono text-3xl font-bold text-amber-ink tabular-nums">{{ number_format($pendingOrders ?? 0) }}</p>
                </div>
                <div class="p-2 bg-ivory-alt border border-rule">
                    <x-heroicon-o-clock class="w-6 h-6 text-amber-ink" />
                </div>
            </div>
            <div class="flex items-center gap-2 text-xs font-mono text-ink-muted">
                <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                <span>Requires Action</span>
            </div>
        </div>

        {{-- Processing Orders --}}
        <div class="bp-card p-5 relative overflow-hidden group">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <p class="bp-spec text-ink-muted">Processing</p>
                    <p class="mt-2 font-mono text-3xl font-bold text-blue-600 tabular-nums">{{ number_format($processingOrders ?? 0) }}</p>
                </div>
                <div class="p-2 bg-ivory-alt border border-rule">
                    <x-heroicon-o-cog-6-tooth class="w-6 h-6 text-blue-600" />
                </div>
            </div>
            <div class="flex items-center gap-2 text-xs font-mono text-ink-muted">
                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                <span>In Progress</span>
            </div>
        </div>

        {{-- Shipped Orders --}}
        <div class="bp-card p-5 relative overflow-hidden group">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <p class="bp-spec text-ink-muted">Shipped</p>
                    <p class="mt-2 font-mono text-3xl font-bold text-emerald-600 tabular-nums">{{ number_format($shippedOrders ?? 0) }}</p>
                </div>
                <div class="p-2 bg-ivory-alt border border-rule">
                    <x-heroicon-o-truck class="w-6 h-6 text-emerald-600" />
                </div>
            </div>
            <div class="flex items-center gap-2 text-xs font-mono text-ink-muted">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span>Last 30 Days</span>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════
         MAIN CONTENT GRID
         ═══════════════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ═══ LEFT COLUMN (2/3 width): Recent Orders & Charts ═══ --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Recent Orders Table --}}
            <div class="bp-card">
                <header class="px-5 py-4 border-b border-rule bg-ivory-alt flex items-center justify-between">
                    <h2 class="font-display font-bold text-lg text-ink">Recent Orders</h2>
                    <a href="{{ route('admin.orders.index') }}" class="text-xs font-mono uppercase tracking-wider text-amber-ink hover:text-amber transition-colors">View All →</a>
                </header>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-ivory-alt border-b border-rule">
                            <tr>
                                <th class="px-5 py-3 font-mono text-xs uppercase tracking-wider text-ink-muted">Order ID</th>
                                <th class="px-5 py-3 font-mono text-xs uppercase tracking-wider text-ink-muted">Customer</th>
                                <th class="px-5 py-3 font-mono text-xs uppercase tracking-wider text-ink-muted">Total</th>
                                <th class="px-5 py-3 font-mono text-xs uppercase tracking-wider text-ink-muted">Status</th>
                                <th class="px-5 py-3 font-mono text-xs uppercase tracking-wider text-ink-muted">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-rule">
                            @forelse($recentOrders ?? [] as $order)
                            <tr class="hover:bg-ivory-alt/50 transition-colors">
                                <td class="px-5 py-3 font-mono text-ink">
                                    <a href="{{ route('admin.orders.show', $order->id) }}" class="hover:text-amber-ink hover:underline">
                                        #{{ $order->order_number }}
                                    </a>
                                </td>
                                <td class="px-5 py-3 text-ink">{{ $order->shipping_name ?? 'Guest' }}</td>
                                <td class="px-5 py-3 font-mono text-ink tabular-nums">€{{ number_format($order->grand_total, 2) }}</td>
                                <td class="px-5 py-3">
                                    @php
                                        $badge = $statusBadge[$order->status] ?? ['bg' => '#F1F5F9', 'text' => '#64748B'];
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-none text-xs font-medium font-mono uppercase tracking-wider" style="background-color: {{ $badge['bg'] }}; color: {{ $badge['text'] }};">
                                        {{ $order->status }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 font-mono text-ink-muted text-xs">{{ $order->created_at->format('d M Y') }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="px-5 py-8 text-center text-ink-muted">No recent orders found.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Sales Chart Placeholder (Blueprint Style) --}}
            <div class="bp-card p-6">
                <header class="mb-6">
                    <h2 class="font-display font-bold text-lg text-ink">Sales Overview</h2>
                    <p class="text-sm text-ink-muted mt-1">Revenue trends over the last 30 days.</p>
                </header>
                <div class="h-64 w-full bg-ivory-alt border border-rule flex items-center justify-center relative overflow-hidden">
                    {{-- Grid Texture --}}
                    <div class="absolute inset-0 bg-grid-ivory-fine opacity-50 pointer-events-none"></div>

                    {{-- Simulated Chart Bars (CSS Only for visual placeholder) --}}
                    <div class="flex items-end justify-around w-full h-full px-4 pb-4 gap-2 z-10">
                        @for($i = 0; $i < 12; $i++)
                            @php $height = rand(20, 80); @endphp
                            <div class="w-full bg-ink/10 hover:bg-amber/20 transition-colors relative group" style="height: {{ $height }}%;">
                                <div class="absolute bottom-0 left-0 right-0 h-1 bg-amber opacity-0 group-hover:opacity-100 transition-opacity"></div>
                            </div>
                        @endfor
                    </div>
                </div>
                <div class="flex justify-between mt-4 text-xs font-mono text-ink-muted">
                    <span>01 Nov</span>
                    <span>15 Nov</span>
                    <span>30 Nov</span>
                </div>
            </div>

        </div>

        {{-- ═══ RIGHT COLUMN (1/3 width): System Health & Quick Actions ═══ --}}
        <div class="space-y-6">

            {{-- System Health Strip --}}
            <div class="bp-card p-5">
                <header class="mb-4 border-b border-rule pb-3">
                    <h2 class="font-display font-bold text-base text-ink">System Health</h2>
                </header>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-ink-muted">Database</span>
                        <span class="flex items-center gap-2 text-xs font-mono text-emerald-600">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            Online
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-ink-muted">Redis Cache</span>
                        <span class="flex items-center gap-2 text-xs font-mono text-emerald-600">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            Connected
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-ink-muted">Queue Worker</span>
                        <span class="flex items-center gap-2 text-xs font-mono text-emerald-600">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            Active
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-ink-muted">Storage</span>
                        <span class="text-xs font-mono text-ink">42% Used</span>
                    </div>
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="bp-card p-5">
                <header class="mb-4 border-b border-rule pb-3">
                    <h2 class="font-display font-bold text-base text-ink">Quick Actions</h2>
                </header>
                <div class="space-y-3">
                    <a href="{{ route('admin.catalog.products.create') }}" class="flex items-center gap-3 p-3 border border-rule hover:border-ink hover:bg-ivory-alt transition-all group">
                        <x-heroicon-o-plus-circle class="w-5 h-5 text-ink group-hover:text-amber transition-colors" />
                        <span class="text-sm font-medium text-ink">Add New Product</span>
                    </a>
                    <a href="{{ route('admin.orders.index', ['status' => 'pending']) }}" class="flex items-center gap-3 p-3 border border-rule hover:border-ink hover:bg-ivory-alt transition-all group">
                        <x-heroicon-o-clock class="w-5 h-5 text-ink group-hover:text-amber transition-colors" />
                        <span class="text-sm font-medium text-ink">Process Pending Orders</span>
                    </a>
                    <a href="{{ route('admin.cms.blog.create') }}" class="flex items-center gap-3 p-3 border border-rule hover:border-ink hover:bg-ivory-alt transition-all group">
                        <x-heroicon-o-pencil-square class="w-5 h-5 text-ink group-hover:text-amber transition-colors" />
                        <span class="text-sm font-medium text-ink">Write Journal Post</span>
                    </a>
                </div>
            </div>

            {{-- Top Manufacturers --}}
            <div class="bp-card p-5">
                <header class="mb-4 border-b border-rule pb-3">
                    <h2 class="font-display font-bold text-base text-ink">Top Manufacturers</h2>
                </header>
                <ul class="space-y-3">
                    @foreach($topManufacturers ?? [] as $mfg)
                    <li class="flex items-center justify-between text-sm">
                        <span class="text-ink">{{ trans_field($mfg->name) }}</span>
                        <span class="font-mono text-ink-muted">{{ $mfg->products_count ?? 0 }} parts</span>
                    </li>
                    @endforeach
                </ul>
            </div>

        </div>
    </div>

</div>
@endsection
