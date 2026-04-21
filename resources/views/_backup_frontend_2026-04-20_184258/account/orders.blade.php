@extends('layouts.app')

@section('title', 'My Orders')

@section('content')
@php
    $lang = app()->getLocale();
@endphp

{{-- ── Breadcrumb ──────────────────────────────────────────────────────── --}}
<div class="bg-gray-50 border-b border-gray-100 py-3 px-4">
    <div class="max-w-6xl mx-auto">
        <ol class="flex flex-wrap items-center gap-1.5 text-xs text-muted">
            <li>
                <a href="/{{ $lang }}/" class="hover:text-amber-text transition-colors font-medium">Home</a>
            </li>
            <li class="text-gray-300"><x-heroicon-o-chevron-right class="w-3 h-3 inline" /></li>
            <li>
                <a href="{{ route('frontend.account.dashboard', ['lang' => $lang]) }}" class="hover:text-amber-text transition-colors font-medium">My Account</a>
            </li>
            <li class="text-gray-300"><x-heroicon-o-chevron-right class="w-3 h-3 inline" /></li>
            <li class="text-navy font-semibold">Orders</li>
        </ol>
    </div>
</div>

{{-- ── Page Header ─────────────────────────────────────────────────────── --}}
<div class="bg-gradient-to-r from-navy via-navy to-blue-900 text-white py-8 px-4">
    <div class="max-w-6xl mx-auto">
        <h1 class="font-display text-3xl md:text-4xl font-bold mb-2">My Orders</h1>
        <p class="text-white/70">Track and manage all your OEM part orders in one place.</p>
    </div>
</div>

{{-- ── Main Content ────────────────────────────────────────────────────── --}}
<div class="bg-bg-page min-h-screen">
    <div class="max-w-6xl mx-auto px-4 py-8">
        <div class="grid lg:grid-cols-4 gap-8">

            {{-- ── Sidebar ─────────────────────────────────────────────── --}}
            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 sticky top-8 h-fit">
                    <nav class="space-y-1.5">
                        <a href="{{ route('frontend.account.dashboard', ['lang' => $lang]) }}"
                           class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-navy hover:bg-gray-50 font-semibold transition-colors">
                            <x-heroicon-o-squares-2x2 class="w-5 h-5" />
                            Dashboard
                        </a>
                        <a href="{{ route('frontend.account.orders', ['lang' => $lang]) }}"
                           class="flex items-center gap-3 px-4 py-2.5 rounded-xl bg-amber/10 text-amber font-semibold transition-colors hover:bg-amber/20">
                            <x-heroicon-o-shopping-bag class="w-5 h-5" />
                            Orders
                        </a>
                        <a href="{{ route('frontend.account.refunds', ['lang' => $lang]) }}"
                           class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-navy hover:bg-gray-50 font-semibold transition-colors">
                            <x-heroicon-o-arrow-path class="w-5 h-5" />
                            Refunds
                        </a>
                        <a href="{{ route('frontend.account.addresses', ['lang' => $lang]) }}"
                           class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-navy hover:bg-gray-50 font-semibold transition-colors">
                            <x-heroicon-o-map-pin class="w-5 h-5" />
                            Addresses
                        </a>
                        <a href="{{ route('frontend.account.settings', ['lang' => $lang]) }}"
                           class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-navy hover:bg-gray-50 font-semibold transition-colors">
                            <x-heroicon-o-cog-6-tooth class="w-5 h-5" />
                            Settings
                        </a>
                    </nav>
                </div>
            </div>

            {{-- ── Main Column ─────────────────────────────────────────── --}}
            <div class="lg:col-span-3">

                @if($orders->isEmpty())
                {{-- ── Empty State ─────────────────────────────────────── --}}
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-12 text-center">
                    <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-6">
                        <x-heroicon-o-shopping-bag class="w-8 h-8 text-muted" />
                    </div>
                    <h3 class="font-display text-xl font-bold text-navy mb-2">No Orders Yet</h3>
                    <p class="text-sm text-muted max-w-md mx-auto mb-6">
                        You haven't placed any orders yet. Start searching for OEM parts and place your first order today.
                    </p>
                    <a href="/{{ $lang }}/"
                       class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-gradient-to-r from-amber to-orange-500 text-navy text-sm font-bold shadow-lg shadow-amber/30 hover:from-amber/90 hover:to-orange-400 transition-all duration-200">
                        <x-heroicon-o-magnifying-glass class="w-4 h-4" />
                        Search OEM Parts
                    </a>
                </div>
                @else
                {{-- ── Orders Table ────────────────────────────────────── --}}
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b border-gray-100">
                                <tr>
                                    <th class="px-6 py-4 text-left text-sm font-bold text-navy">Order #</th>
                                    <th class="px-6 py-4 text-left text-sm font-bold text-navy">Date</th>
                                    <th class="px-6 py-4 text-left text-sm font-bold text-navy">Items</th>
                                    <th class="px-6 py-4 text-left text-sm font-bold text-navy">Total</th>
                                    <th class="px-6 py-4 text-left text-sm font-bold text-navy">Status</th>
                                    <th class="px-6 py-4 text-left text-sm font-bold text-navy">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($orders as $order)
                                @php
                                    $statusConfig = match($order->status) {
                                        \App\Enums\OrderStatus::Delivered       => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'icon' => 'heroicon-o-check-circle'],
                                        \App\Enums\OrderStatus::Shipped        => ['bg' => 'bg-blue-100',    'text' => 'text-blue-700',    'icon' => 'heroicon-o-truck'],
                                        \App\Enums\OrderStatus::Processing     => ['bg' => 'bg-amber/15',    'text' => 'text-amber-text',  'icon' => 'heroicon-o-cog-6-tooth'],
                                        \App\Enums\OrderStatus::Paid           => ['bg' => 'bg-purple-100',  'text' => 'text-purple-700',  'icon' => 'heroicon-o-credit-card'],
                                        \App\Enums\OrderStatus::Pending        => ['bg' => 'bg-gray-100',    'text' => 'text-gray-600',    'icon' => 'heroicon-o-clock'],
                                        \App\Enums\OrderStatus::Cancelled      => ['bg' => 'bg-red-100',     'text' => 'text-red-700',     'icon' => 'heroicon-o-x-circle'],
                                        \App\Enums\OrderStatus::RefundRequested => ['bg' => 'bg-orange-100', 'text' => 'text-orange-700',  'icon' => 'heroicon-o-arrow-path'],
                                        \App\Enums\OrderStatus::Refunded       => ['bg' => 'bg-yellow-100',  'text' => 'text-yellow-700',  'icon' => 'heroicon-o-banknotes'],
                                        default                                => ['bg' => 'bg-gray-100',    'text' => 'text-gray-600',    'icon' => 'heroicon-o-question-mark-circle'],
                                    };
                                @endphp
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <a href="{{ route('frontend.account.order.detail', ['lang' => $lang, 'order' => $order]) }}"
                                           class="font-mono font-bold text-navy text-sm hover:text-amber transition-colors">
                                            {{ $order->order_number }}
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-muted">
                                        {{ $order->created_at->format('M d, Y') }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-navy">
                                        {{ $order->items->count() }}
                                    </td>
                                    <td class="px-6 py-4 font-bold text-navy font-mono text-sm">
                                        €{{ number_format($order->grand_total, 2) }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold {{ $statusConfig['bg'] }} {{ $statusConfig['text'] }}">
                                            <x-dynamic-component :component="$statusConfig['icon']" class="w-3.5 h-3.5" />
                                            {{ $order->status->label() }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <a href="{{ route('frontend.account.order.detail', ['lang' => $lang, 'order' => $order]) }}"
                                           class="inline-flex items-center gap-1 text-sm text-amber font-semibold hover:text-amber/80 transition-colors">
                                            View
                                            <x-heroicon-o-arrow-right class="w-3.5 h-3.5" />
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- ── Pagination ──────────────────────────────────── --}}
                    @if($orders->hasPages())
                    <div class="px-6 py-4 border-t border-gray-100">
                        {{ $orders->links() }}
                    </div>
                    @endif
                </div>
                @endif

            </div>
        </div>
    </div>
</div>
@endsection
