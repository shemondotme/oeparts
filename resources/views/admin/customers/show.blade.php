@extends('layouts.admin')

@section('title', 'Customer — ' . $customer->name)
@section('page_title', 'Customer Details')

@section('header_actions')
    @if(!$customer->trashed())
    <form action="{{ route('admin.customers.toggle-active', $customer) }}" method="POST" class="inline">
        @csrf
        @method('PATCH')
        <button type="submit"
                class="{{ $customer->is_active ? 'bp-btn-ghost text-amber-700' : 'bp-btn-ghost text-green-700' }} gap-1">
            @if($customer->is_active)
                <x-heroicon-o-lock-closed class="w-4 h-4" />
                Deactivate
            @else
                <x-heroicon-o-lock-open class="w-4 h-4" />
                Activate
            @endif
        </button>
    </form>
    @endif
    <a href="{{ route('admin.customers.index') }}" class="bp-btn-ghost gap-1">
        <x-heroicon-o-arrow-left class="w-4 h-4" />
        Back
    </a>
@endsection

@section('content')
<div class="space-y-6">

    {{-- Header strip --}}
    <div class="bp-card p-5 flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="bp-spec text-ink-muted">§ Customer</p>
            <h2 class="font-display text-2xl font-bold text-ink tracking-[-0.02em]">
                {{ $customer->name }}<span class="text-amber">.</span>
            </h2>
            <p class="font-mono text-xs text-ink-muted mt-0.5">{{ $customer->email }}</p>
        </div>
        <div class="flex items-center gap-2">
            @if($customer->trashed())
                <span class="inline-flex items-center border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider border-red-600/30 bg-red-50 text-red-700">Deleted</span>
            @elseif($customer->is_active)
                <span class="inline-flex items-center border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider border-green-600/30 bg-green-50 text-green-700">Active</span>
            @else
                <span class="inline-flex items-center border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider border-rule bg-ivory-alt text-ink-muted">Inactive</span>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Left: Profile + Orders --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Profile information --}}
            <section class="bp-card overflow-hidden">
                <header class="bp-card-header">
                    <p class="bp-spec text-ink-muted">§ Profile · Information</p>
                </header>
                <div class="divide-y divide-rule">
                    @foreach([
                        ['§ Full Name',       $customer->name,       false],
                        ['§ Email',           $customer->email,      true],
                        ['§ Phone',           $customer->phone ?? '—', true],
                        ['§ Registered',      $customer->created_at->format('Y-m-d H:i'), true],
                        ['§ Email Verified',  $customer->email_verified_at ? $customer->email_verified_at->format('Y-m-d') : 'Not verified', true],
                    ] as [$label, $value, $mono])
                    <div class="flex items-start gap-4 px-5 py-3">
                        <span class="w-36 flex-shrink-0 bp-spec text-ink-muted">{{ $label }}</span>
                        <span class="{{ $mono ? 'font-mono text-xs tabular-nums' : 'text-sm font-bold' }} text-ink">{{ $value }}</span>
                    </div>
                    @endforeach
                    <div class="flex items-start gap-4 px-5 py-3">
                        <span class="w-36 flex-shrink-0 bp-spec text-ink-muted">§ Status</span>
                        @if($customer->trashed())
                            <span class="inline-flex items-center border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider border-red-600/30 bg-red-50 text-red-700">Deleted</span>
                        @elseif($customer->is_active)
                            <span class="inline-flex items-center border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider border-green-600/30 bg-green-50 text-green-700">Active</span>
                        @else
                            <span class="inline-flex items-center border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider border-rule bg-ivory-alt text-ink-muted">Inactive</span>
                        @endif
                    </div>
                </div>
            </section>

            {{-- Recent Orders --}}
            <section class="bp-card overflow-hidden">
                <header class="bp-card-header flex items-center justify-between gap-4">
                    <p class="bp-spec text-ink-muted">§ Recent · Orders</p>
                    <a href="{{ route('admin.orders.index', ['customer_id' => $customer->id]) }}"
                       class="font-mono text-xs text-amber-ink hover:underline">
                        View all {{ $customer->orders->count() }}
                    </a>
                </header>
                @if($customer->orders->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="bp-table">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($customer->orders->take(10) as $order)
                                @php
                                    $statusBadge = match($order->status->value ?? '') {
                                        'pending'    => 'border-amber/30 bg-amber-50 text-amber-700',
                                        'confirmed'  => 'border-blue-600/30 bg-blue-50 text-blue-700',
                                        'processing' => 'border-purple-600/30 bg-purple-50 text-purple-700',
                                        'shipped'    => 'border-indigo-600/30 bg-indigo-50 text-indigo-700',
                                        'delivered'  => 'border-green-600/30 bg-green-50 text-green-700',
                                        'cancelled'  => 'border-red-600/30 bg-red-50 text-red-700',
                                        'refunded'   => 'border-rule bg-ivory-alt text-ink-muted',
                                        default      => 'border-rule bg-ivory-alt text-ink-muted',
                                    };
                                @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.orders.show', $order) }}"
                                           class="font-mono text-sm text-amber-ink hover:underline tracking-wider">
                                            {{ $order->order_number }}
                                        </a>
                                    </td>
                                    <td>
                                        <p class="font-mono text-sm tabular-nums font-bold text-ink">{{ format_money($order->grand_total) }}</p>
                                    </td>
                                    <td>
                                        <span class="inline-flex items-center border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider {{ $statusBadge }}">
                                            {{ $order->status->value ?? '—' }}
                                        </span>
                                    </td>
                                    <td>
                                        <p class="font-mono text-xs tabular-nums text-ink">{{ $order->created_at->format('Y-m-d') }}</p>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="px-5 py-10 text-center">
                        <x-heroicon-o-shopping-cart class="w-8 h-8 mx-auto text-ink/20 mb-2" />
                        <p class="text-sm text-ink-muted">No orders yet.</p>
                    </div>
                @endif
            </section>

        </div>

        {{-- Right: Stats + Actions + Meta --}}
        <div class="space-y-6">

            <div class="grid grid-cols-2 gap-3 lg:grid-cols-1 lg:gap-4">
                <div class="bp-card p-4 text-center">
                    <p class="font-mono text-2xl font-bold tabular-nums text-ink">{{ $customer->orders->count() }}</p>
                    <p class="text-xs text-ink-muted mt-1">Total Orders</p>
                </div>
                <div class="bp-card p-4 text-center">
                    <p class="font-mono text-2xl font-bold tabular-nums text-ink">
                        {{ format_money($customer->orders->sum('grand_total')) }}
                    </p>
                    <p class="text-xs text-ink-muted mt-1">Total Spent</p>
                </div>
            </div>

            <section class="bp-card overflow-hidden">
                <header class="bp-card-header">
                    <p class="bp-spec text-ink-muted">§ Quick · Actions</p>
                </header>
                <div class="p-5 space-y-2">
                    <a href="{{ route('admin.orders.index', ['customer_id' => $customer->id]) }}"
                       class="bp-btn-outline w-full justify-center gap-1">
                        <x-heroicon-o-shopping-bag class="w-4 h-4" />
                        View All Orders
                    </a>
                    @if(!$customer->trashed())
                    <form action="{{ route('admin.customers.toggle-active', $customer) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                                class="bp-btn-ghost w-full justify-center gap-1 {{ $customer->is_active ? 'text-amber-700' : 'text-green-700' }}">
                            @if($customer->is_active)
                                <x-heroicon-o-lock-closed class="w-4 h-4" />
                                Deactivate Account
                            @else
                                <x-heroicon-o-lock-open class="w-4 h-4" />
                                Activate Account
                            @endif
                        </button>
                    </form>
                    @endif
                </div>
            </section>

            <section class="bp-card overflow-hidden">
                <header class="bp-card-header">
                    <p class="bp-spec text-ink-muted">§ Metadata</p>
                </header>
                <div class="p-5 space-y-3">
                    <div>
                        <p class="bp-spec text-ink-muted mb-1">§ Registered</p>
                        <p class="font-mono text-xs tabular-nums text-ink">{{ $customer->created_at->format('Y-m-d H:i') }}</p>
                    </div>
                    <div class="border-t border-rule pt-3">
                        <p class="bp-spec text-ink-muted mb-1">§ Last Updated</p>
                        <p class="font-mono text-xs tabular-nums text-ink">{{ $customer->updated_at->format('Y-m-d H:i') }}</p>
                    </div>
                    @if($customer->deleted_at)
                    <div class="border-t border-rule pt-3">
                        <p class="bp-spec text-red-600 mb-1">§ Deleted</p>
                        <p class="font-mono text-xs tabular-nums text-red-600">{{ $customer->deleted_at->format('Y-m-d H:i') }}</p>
                    </div>
                    @endif
                </div>
            </section>

        </div>
    </div>

</div>
@endsection
