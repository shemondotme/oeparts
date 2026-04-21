@extends('layouts.app')

@section('title', __('My Account') . ' — ' . settings('general.site_name', 'OEMHub'))

@php
    $lang = app()->getLocale();
    $totalOrders    = $user->orders()->count();
    $totalSpent     = (float) ($user->orders()->sum('grand_total') ?? 0);
    $pendingOrders  = $user->orders()->whereIn('status', [
        \App\Enums\OrderStatus::Pending,
        \App\Enums\OrderStatus::Paid,
        \App\Enums\OrderStatus::Processing,
    ])->count();
    $savedAddresses = $user->addresses()->count();
    $memberSince    = $user->created_at->format('M j, Y');
@endphp

@section('content')
<x-account.shell
    active="dashboard"
    eyebrow="§ 01 · Customer · Console"
    title="Welcome back, {!! e($user->first_name ?: $user->name) !!}"
    :subtitle="__('Your B2B operating sheet — orders in flight, addresses on file, and everything tied to your procurement pipeline.')"
    docId="DOC · CUSTOMER-SHEET · REV. {{ now()->format('Y.m.d') }}"
    :breadcrumb="[['label' => 'Dashboard']]"
>
    <x-slot name="actions">
        <a href="{{ route('frontend.search.console', ['lang' => $lang]) }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 bg-amber text-ink border border-amber
                  font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                  hover:bg-paper hover:text-ink transition-colors">
            <x-heroicon-s-magnifying-glass class="w-4 h-4" />
            New search
        </a>
        <a href="{{ route('frontend.cart.index', ['lang' => $lang]) }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 border border-ivory/20 text-ivory
                  font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                  hover:border-amber hover:text-amber transition-colors">
            <x-heroicon-s-shopping-cart class="w-4 h-4" />
            View cart
        </a>
    </x-slot>

    {{-- ── KPI Cards ─────────────────────────────────────────────────── --}}
    <section class="mb-8">
        <div class="flex items-center justify-between mb-4">
            <span class="bp-spec text-amber-ink">§ 01 · Key · Metrics</span>
            <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">
                Member since {{ $memberSince }}
            </span>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Total Orders --}}
            <div class="border border-ink bg-paper p-5 relative overflow-hidden"
                 style="box-shadow: 4px 4px 0 rgba(20,22,29,1);">
                <span class="absolute top-0 left-0 right-0 h-[3px] bg-amber"></span>
                <p class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">Total orders</p>
                <p class="mt-2 font-display text-4xl font-extrabold text-ink tabular-nums tracking-[-0.03em] leading-none">
                    {{ str_pad((string) $totalOrders, 2, '0', STR_PAD_LEFT) }}
                </p>
                <div class="mt-3 flex items-center gap-1.5">
                    <x-heroicon-s-shopping-bag class="w-3 h-3 text-amber-ink" />
                    <span class="font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted">All time</span>
                </div>
            </div>

            {{-- Total Spent --}}
            <div class="border border-ink bg-paper p-5 relative overflow-hidden"
                 style="box-shadow: 4px 4px 0 rgba(20,22,29,1);">
                <span class="absolute top-0 left-0 right-0 h-[3px] bg-emerald-600"></span>
                <p class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">Total spent</p>
                <p class="mt-2 font-display text-4xl font-extrabold text-ink tabular-nums tracking-[-0.03em] leading-none">
                    €{{ number_format($totalSpent, 0) }}
                </p>
                <div class="mt-3 flex items-center gap-1.5">
                    <x-heroicon-s-currency-euro class="w-3 h-3 text-emerald-700" />
                    <span class="font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted">Lifetime · EUR</span>
                </div>
            </div>

            {{-- In Flight --}}
            <div class="border border-ink bg-paper p-5 relative overflow-hidden"
                 style="box-shadow: 4px 4px 0 rgba(20,22,29,1);">
                <span class="absolute top-0 left-0 right-0 h-[3px] bg-ink"></span>
                <p class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">In flight</p>
                <p class="mt-2 font-display text-4xl font-extrabold text-ink tabular-nums tracking-[-0.03em] leading-none">
                    {{ str_pad((string) $pendingOrders, 2, '0', STR_PAD_LEFT) }}
                </p>
                <div class="mt-3 flex items-center gap-1.5">
                    <x-heroicon-s-truck class="w-3 h-3 text-ink" />
                    <span class="font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted">Being processed</span>
                </div>
            </div>

            {{-- Saved Addresses --}}
            <div class="border border-ink bg-paper p-5 relative overflow-hidden"
                 style="box-shadow: 4px 4px 0 rgba(20,22,29,1);">
                <span class="absolute top-0 left-0 right-0 h-[3px] bg-blue-600"></span>
                <p class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">Addresses on file</p>
                <p class="mt-2 font-display text-4xl font-extrabold text-ink tabular-nums tracking-[-0.03em] leading-none">
                    {{ str_pad((string) $savedAddresses, 2, '0', STR_PAD_LEFT) }}
                </p>
                <div class="mt-3 flex items-center gap-1.5">
                    <x-heroicon-s-map-pin class="w-3 h-3 text-blue-700" />
                    <span class="font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted">Ship-to records</span>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Account Information ──────────────────────────────────────── --}}
    <section class="mb-8 border border-ink bg-paper" style="box-shadow: 6px 6px 0 rgba(20,22,29,1);">
        <header class="flex items-center justify-between px-5 py-3 border-b border-ink bg-ivory-alt">
            <span class="bp-spec text-amber-ink flex items-center gap-2">
                <x-heroicon-o-identification class="w-3.5 h-3.5" />
                § 02 · Account · Profile
            </span>
            <a href="{{ route('frontend.account.settings', ['lang' => $lang]) }}"
               class="inline-flex items-center gap-1.5 font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink
                      border-b border-amber hover:text-amber-ink transition-colors pb-0.5">
                Edit profile
                <x-heroicon-s-arrow-long-right class="w-3 h-3" />
            </a>
        </header>

        <dl class="grid sm:grid-cols-2 lg:grid-cols-4 divide-y sm:divide-y-0 sm:divide-x divide-rule">
            <div class="px-5 py-4">
                <dt class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">Full name</dt>
                <dd class="mt-1.5 font-display text-base font-bold text-ink tracking-[-0.01em]">
                    {{ $user->name }}
                </dd>
            </div>
            <div class="px-5 py-4">
                <dt class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">Email</dt>
                <dd class="mt-1.5 font-mono text-sm font-bold text-ink truncate">
                    {{ $user->email }}
                </dd>
            </div>
            <div class="px-5 py-4">
                <dt class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">Phone</dt>
                <dd class="mt-1.5 font-mono text-sm font-bold text-ink">
                    {{ $user->phone ?: '—' }}
                </dd>
            </div>
            <div class="px-5 py-4">
                <dt class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">Member since</dt>
                <dd class="mt-1.5 font-mono text-sm font-bold text-ink">
                    {{ $memberSince }}
                </dd>
            </div>
        </dl>
    </section>

    {{-- ── Recent Orders ────────────────────────────────────────────── --}}
    <section class="mb-8">
        <header class="flex items-center justify-between mb-4">
            <span class="bp-spec text-amber-ink">§ 03 · Recent · Orders</span>
            <a href="{{ route('frontend.account.orders', ['lang' => $lang]) }}"
               class="inline-flex items-center gap-1.5 font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink
                      border-b border-amber hover:text-amber-ink transition-colors pb-0.5">
                View all orders
                <x-heroicon-s-arrow-long-right class="w-3 h-3" />
            </a>
        </header>

        @if($recentOrders->isNotEmpty())
            <div class="border border-ink bg-paper overflow-hidden" style="box-shadow: 6px 6px 0 rgba(20,22,29,1);">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-ink-tint border-b border-ink">
                            <tr>
                                <th class="px-5 py-3 text-left font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted">#</th>
                                <th class="px-5 py-3 text-left font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted">Order</th>
                                <th class="px-5 py-3 text-left font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted">Date</th>
                                <th class="px-5 py-3 text-right font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted">Total</th>
                                <th class="px-5 py-3 text-left font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted">Status</th>
                                <th class="px-5 py-3 text-right font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-rule">
                            @foreach($recentOrders->take(5) as $idx => $order)
                                @php
                                    $statusBar = match($order->status) {
                                        \App\Enums\OrderStatus::Delivered        => 'bg-emerald-600',
                                        \App\Enums\OrderStatus::Shipped          => 'bg-blue-600',
                                        \App\Enums\OrderStatus::Processing       => 'bg-amber',
                                        \App\Enums\OrderStatus::Paid             => 'bg-ink',
                                        \App\Enums\OrderStatus::Pending          => 'bg-ink-muted',
                                        \App\Enums\OrderStatus::Cancelled        => 'bg-red-600',
                                        \App\Enums\OrderStatus::RefundRequested  => 'bg-orange-600',
                                        \App\Enums\OrderStatus::Refunded         => 'bg-amber-ink',
                                        default                                  => 'bg-ink-muted',
                                    };
                                @endphp
                                <tr class="hover:bg-ivory-alt transition-colors">
                                    <td class="px-5 py-4 font-mono text-[10px] tabular-nums tracking-[0.18em] uppercase text-ink-muted">
                                        {{ str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT) }}
                                    </td>
                                    <td class="px-5 py-4">
                                        <a href="{{ route('frontend.account.order.detail', ['lang' => $lang, 'order' => $order]) }}"
                                           class="font-mono text-sm font-bold text-ink tabular-nums hover:text-amber-ink transition-colors">
                                            {{ $order->order_number }}
                                        </a>
                                        <p class="mt-0.5 font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted">
                                            {{ $order->items->count() }} {{ \Illuminate\Support\Str::plural('item', $order->items->count()) }}
                                        </p>
                                    </td>
                                    <td class="px-5 py-4 font-mono text-xs text-ink-muted tabular-nums">
                                        {{ $order->created_at->format('Y-m-d') }}
                                    </td>
                                    <td class="px-5 py-4 text-right font-mono text-sm font-bold text-ink tabular-nums">
                                        €{{ number_format((float) $order->grand_total, 2) }}
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex items-center gap-2">
                                            <span class="inline-block w-1.5 h-3 {{ $statusBar }}"></span>
                                            <span class="font-mono text-[10px] font-bold tracking-[0.18em] uppercase text-ink">
                                                {{ $order->status->label() }}
                                            </span>
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <a href="{{ route('frontend.account.order.detail', ['lang' => $lang, 'order' => $order]) }}"
                                           class="inline-flex items-center gap-1.5 font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink
                                                  border-b border-amber hover:text-amber-ink transition-colors pb-0.5">
                                            View
                                            <x-heroicon-s-arrow-long-right class="w-3 h-3" />
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            {{-- Empty state --}}
            <div class="border border-ink bg-paper p-10 text-center" style="box-shadow: 6px 6px 0 rgba(20,22,29,1);">
                <div class="inline-flex items-center justify-center w-14 h-14 border border-ink bg-ivory-alt mb-5">
                    <x-heroicon-o-shopping-bag class="w-6 h-6 text-ink-muted" />
                </div>
                <h3 class="font-display text-2xl font-extrabold text-ink tracking-[-0.02em]">
                    No orders yet<span class="text-amber">.</span>
                </h3>
                <p class="mt-2 text-sm text-ink-muted max-w-md mx-auto">
                    Start an OEM search and place your first B2B procurement order.
                </p>
                <a href="{{ route('frontend.search.console', ['lang' => $lang]) }}" class="bp-btn-primary mt-6">
                    <x-heroicon-s-magnifying-glass class="w-5 h-5" />
                    Search OEM parts
                </a>
            </div>
        @endif
    </section>

    {{-- ── Quick actions ────────────────────────────────────────────── --}}
    <section>
        <header class="flex items-center justify-between mb-4">
            <span class="bp-spec text-amber-ink">§ 04 · Quick · Actions</span>
        </header>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @php
                $quickActions = [
                    [
                        'num'   => '01',
                        'label' => __('Manage addresses'),
                        'desc'  => __('Add or update your ship-to records.'),
                        'icon'  => 'heroicon-o-map-pin',
                        'href'  => route('frontend.account.addresses', ['lang' => $lang]),
                    ],
                    [
                        'num'   => '02',
                        'label' => __('Track refunds'),
                        'desc'  => __('Monitor the status of pending refund requests.'),
                        'icon'  => 'heroicon-o-arrow-path',
                        'href'  => route('frontend.account.refunds', ['lang' => $lang]),
                    ],
                    [
                        'num'   => '03',
                        'label' => __('Account settings'),
                        'desc'  => __('Update profile, password, and preferences.'),
                        'icon'  => 'heroicon-o-cog-6-tooth',
                        'href'  => route('frontend.account.settings', ['lang' => $lang]),
                    ],
                ];
            @endphp
            @foreach($quickActions as $qa)
                <a href="{{ $qa['href'] }}"
                   class="group border border-ink bg-paper p-5 flex items-start gap-4 transition-all
                          hover:bg-ink hover:text-ivory"
                   style="box-shadow: 4px 4px 0 rgba(20,22,29,1);">
                    <span class="font-mono text-[10px] tabular-nums tracking-[0.22em] uppercase text-ink-muted group-hover:text-amber mt-1">
                        {{ $qa['num'] }}
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-2">
                            <p class="font-display text-base font-bold text-ink group-hover:text-ivory tracking-[-0.01em]">
                                {{ $qa['label'] }}
                            </p>
                            <x-dynamic-component :component="$qa['icon']"
                                class="w-5 h-5 text-ink-muted group-hover:text-amber transition-colors shrink-0" />
                        </div>
                        <p class="mt-1 text-xs text-ink-muted group-hover:text-ivory/70 leading-relaxed">
                            {{ $qa['desc'] }}
                        </p>
                    </div>
                </a>
            @endforeach
        </div>
    </section>
</x-account.shell>
@endsection
