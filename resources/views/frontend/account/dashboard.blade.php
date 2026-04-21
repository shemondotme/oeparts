@extends('layouts.app')

@section('title', 'My Account')

@section('content')
@php
    $lang = app()->getLocale();

    $totalOrders = $user->orders()->count();
    $totalSpent = $user->orders()->sum('grand_total') ?? 0;
    $savedAddresses = $user->addresses()->count();

    $addressCount = $user->addresses()->count();
@endphp

{{-- ── Breadcrumb ──────────────────────────────────────────────────────── --}}
<div class="bg-gray-50 border-b border-gray-100 py-3 px-4">
    <div class="max-w-6xl mx-auto">
        <ol class="flex flex-wrap items-center gap-1.5 text-xs text-muted">
            <li>
                <a href="/{{ $lang }}/" class="hover:text-amber-text transition-colors font-medium">Home</a>
            </li>
            <li class="text-gray-300"><x-heroicon-o-chevron-right class="w-3 h-3 inline" /></li>
            <li class="text-navy font-semibold">My Account</li>
        </ol>
    </div>
</div>

{{-- ── Page Header ─────────────────────────────────────────────────────── --}}
<div class="bg-gradient-to-r from-navy via-navy to-blue-900 text-white py-8 px-4">
    <div class="max-w-6xl mx-auto">
        <h1 class="font-display text-3xl md:text-4xl font-bold mb-2">My Account</h1>
        <p class="text-white/70">Welcome back, {{ $user->name }}!</p>
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
                           class="flex items-center gap-3 px-4 py-2.5 rounded-xl bg-amber/10 text-amber font-semibold transition-colors hover:bg-amber/20">
                            <x-heroicon-o-squares-2x2 class="w-5 h-5" />
                            Dashboard
                        </a>
                        <a href="{{ route('frontend.account.orders', ['lang' => $lang]) }}"
                           class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-navy hover:bg-gray-50 font-semibold transition-colors">
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
            <div class="lg:col-span-3 space-y-8">

                {{-- ── Stat Cards ──────────────────────────────────────── --}}
                <div class="grid sm:grid-cols-3 gap-6">
                    {{-- Total Orders --}}
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                        <div class="flex items-center gap-4">
                            <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center shrink-0">
                                <x-heroicon-o-shopping-bag class="w-4 h-4 text-blue-600" />
                            </div>
                            <div>
                                <p class="text-sm text-muted font-medium">Total Orders</p>
                                <p class="text-2xl font-bold text-navy font-mono">{{ $totalOrders }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- Total Spent --}}
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                        <div class="flex items-center gap-4">
                            <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center shrink-0">
                                <x-heroicon-o-currency-euro class="w-4 h-4 text-emerald-600" />
                            </div>
                            <div>
                                <p class="text-sm text-muted font-medium">Total Spent</p>
                                <p class="text-2xl font-bold text-navy font-mono">€{{ number_format($totalSpent, 2) }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- Saved Addresses --}}
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                        <div class="flex items-center gap-4">
                            <div class="w-8 h-8 rounded-lg bg-amber/10 flex items-center justify-center shrink-0">
                                <x-heroicon-o-map-pin class="w-4 h-4 text-amber-text" />
                            </div>
                            <div>
                                <p class="text-sm text-muted font-medium">Saved Addresses</p>
                                <p class="text-2xl font-bold text-navy font-mono">{{ $savedAddresses }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Account Information ─────────────────────────────── --}}
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="font-display text-xl font-bold text-navy">Account Information</h2>
                        <a href="{{ route('frontend.account.settings', ['lang' => $lang]) }}"
                           class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-amber to-orange-500 text-navy text-sm font-bold shadow-sm hover:from-amber/90 hover:to-orange-400 transition-all duration-200">
                            <x-heroicon-o-pencil class="w-4 h-4" />
                            Edit Profile
                        </a>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-8">
                        <div>
                            <p class="text-sm text-muted font-medium mb-1">Full Name</p>
                            <p class="text-base text-navy font-semibold">{{ $user->name }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted font-medium mb-1">Email</p>
                            <p class="text-base text-navy font-semibold">{{ $user->email }}</p>
                        </div>
                        @if($user->phone)
                        <div>
                            <p class="text-sm text-muted font-medium mb-1">Phone</p>
                            <p class="text-base text-navy font-semibold">{{ $user->phone }}</p>
                        </div>
                        @endif
                        <div>
                            <p class="text-sm text-muted font-medium mb-1">Member Since</p>
                            <p class="text-base text-navy font-semibold">{{ $user->created_at->format('M d, Y') }}</p>
                        </div>
                    </div>
                </div>

                {{-- ── Recent Orders ───────────────────────────────────── --}}
                @if($recentOrders->isNotEmpty())
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="font-display text-xl font-bold text-navy">Recent Orders</h2>
                        <a href="{{ route('frontend.account.orders', ['lang' => $lang]) }}"
                           class="text-sm text-amber font-semibold hover:text-amber/80 transition-colors flex items-center gap-1">
                            View All
                            <x-heroicon-o-arrow-right class="w-4 h-4" />
                        </a>
                    </div>

                    <div class="space-y-4">
                        @foreach($recentOrders->take(5) as $order)
                        @php
                            $statusColors = match($order->status) {
                                \App\Enums\OrderStatus::Delivered       => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700'],
                                \App\Enums\OrderStatus::Shipped        => ['bg' => 'bg-blue-100',    'text' => 'text-blue-700'],
                                \App\Enums\OrderStatus::Processing     => ['bg' => 'bg-amber/15',    'text' => 'text-amber-text'],
                                \App\Enums\OrderStatus::Paid           => ['bg' => 'bg-purple-100',  'text' => 'text-purple-700'],
                                \App\Enums\OrderStatus::Pending        => ['bg' => 'bg-gray-100',    'text' => 'text-gray-600'],
                                \App\Enums\OrderStatus::Cancelled      => ['bg' => 'bg-red-100',     'text' => 'text-red-700'],
                                \App\Enums\OrderStatus::RefundRequested => ['bg' => 'bg-orange-100', 'text' => 'text-orange-700'],
                                \App\Enums\OrderStatus::Refunded       => ['bg' => 'bg-yellow-100',  'text' => 'text-yellow-700'],
                                default                                => ['bg' => 'bg-gray-100',    'text' => 'text-gray-600'],
                            };
                        @endphp
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 rounded-xl border border-gray-100 hover:border-gray-200 transition-colors">
                            <div class="flex items-center gap-4">
                                <div class="w-8 h-8 rounded-lg bg-navy/10 flex items-center justify-center shrink-0">
                                    <x-heroicon-o-shopping-bag class="w-4 h-4 text-navy/60" />
                                </div>
                                <div>
                                    <p class="font-mono font-bold text-navy text-sm">{{ $order->order_number }}</p>
                                    <p class="text-xs text-muted">{{ $order->created_at->format('M d, Y') }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold {{ $statusColors['bg'] }} {{ $statusColors['text'] }}">
                                    {{ $order->status->label() }}
                                </span>
                                <p class="font-bold text-navy font-mono text-sm">€{{ number_format($order->grand_total, 2) }}</p>
                                <a href="{{ route('frontend.account.order.detail', ['lang' => $lang, 'order' => $order]) }}"
                                   class="text-sm text-amber font-semibold hover:text-amber/80 transition-colors shrink-0">
                                    View
                                </a>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @else
                {{-- No orders yet --}}
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8 text-center">
                    <div class="w-12 h-12 rounded-xl bg-gray-100 flex items-center justify-center mx-auto mb-4">
                        <x-heroicon-o-shopping-bag class="w-6 h-6 text-muted" />
                    </div>
                    <h3 class="font-display text-lg font-bold text-navy mb-2">No Orders Yet</h3>
                    <p class="text-sm text-muted mb-6">Start searching for OEM parts and place your first order.</p>
                    <a href="/{{ $lang }}/"
                       class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-gradient-to-r from-amber to-orange-500 text-navy text-sm font-bold shadow-lg shadow-amber/30 hover:from-amber/90 hover:to-orange-400 transition-all duration-200">
                        <x-heroicon-o-magnifying-glass class="w-4 h-4" />
                        Search OEM Parts
                    </a>
                </div>
                @endif

            </div>
        </div>
    </div>
</div>
@endsection
