@extends('layouts.admin')

@section('title', 'Orders')
@section('page_title', 'Order Management')

@section('header_actions')
    <a href="{{ route('admin.orders.export', request()->query()) }}" class="bp-btn-outline">
        <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
        Export CSV
    </a>
@endsection

@section('content')
<div class="space-y-6">

    {{-- KPI Strip --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="bp-card p-5">
            <p class="bp-spec text-ink-muted">§ Total</p>
            <p class="mt-2 font-mono text-3xl font-bold tabular-nums text-ink">{{ number_format($orders->total()) }}</p>
            <p class="mt-1 text-sm text-ink-muted">All orders</p>
        </div>
        <div class="bp-card p-5">
            <p class="bp-spec text-amber-ink">§ Pending</p>
            <p class="mt-2 font-mono text-3xl font-bold tabular-nums text-amber-ink">
                {{ \App\Models\Order::where('status', \App\Enums\OrderStatus::Pending)->count() }}
            </p>
            <p class="mt-1 text-sm text-ink-muted">Awaiting payment</p>
        </div>
        <div class="bp-card p-5">
            <p class="bp-spec text-ink-muted">§ Processing</p>
            <p class="mt-2 font-mono text-3xl font-bold tabular-nums text-ink">
                {{ \App\Models\Order::where('status', \App\Enums\OrderStatus::Processing)->count() }}
            </p>
            <p class="mt-1 text-sm text-ink-muted">Being prepared</p>
        </div>
        <div class="bp-card p-5">
            <p class="bp-spec text-ink-muted">§ Shipped</p>
            <p class="mt-2 font-mono text-3xl font-bold tabular-nums text-ink">
                {{ \App\Models\Order::where('status', \App\Enums\OrderStatus::Shipped)->count() }}
            </p>
            <p class="mt-1 text-sm text-ink-muted">In transit</p>
        </div>
    </div>

    {{-- Filters --}}
    <section class="bp-card">
        <header class="bp-card-header">
            <p class="bp-spec text-ink-muted">§ Filter · Orders</p>
        </header>
        <form method="GET" action="{{ route('admin.orders.index') }}"
              class="p-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <label for="status" class="block bp-spec mb-2">§ Status</label>
                <select id="status" name="status" class="bp-select">
                    <option value="all" {{ request('status') == 'all' || !request('status') ? 'selected' : '' }}>All Statuses</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->value }}" {{ request('status') == $status->value ? 'selected' : '' }}>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="order_number" class="block bp-spec mb-2">§ Order Number</label>
                <input type="text" id="order_number" name="order_number"
                       value="{{ request('order_number') }}"
                       placeholder="ORD-2025-03-00001"
                       class="bp-input-mono">
            </div>

            <div>
                <label for="customer_email" class="block bp-spec mb-2">§ Customer Email</label>
                <input type="email" id="customer_email" name="customer_email"
                       value="{{ request('customer_email') }}"
                       placeholder="customer@example.com"
                       class="bp-input">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="date_from" class="block bp-spec mb-2">§ From</label>
                    <input type="date" id="date_from" name="date_from"
                           value="{{ request('date_from') }}" class="bp-input">
                </div>
                <div>
                    <label for="date_to" class="block bp-spec mb-2">§ To</label>
                    <input type="date" id="date_to" name="date_to"
                           value="{{ request('date_to') }}" class="bp-input">
                </div>
            </div>

            <div class="md:col-span-2 xl:col-span-4 flex items-center justify-between gap-4 pt-2 border-t border-rule">
                <label class="flex items-center gap-2 text-sm text-ink-muted cursor-pointer">
                    <input type="checkbox" name="urgent" value="1"
                           {{ request()->boolean('urgent') ? 'checked' : '' }}
                           class="rounded-none border-rule" onchange="this.form.submit()">
                    <x-heroicon-o-bolt class="w-3.5 h-3.5 text-amber-ink" />
                    Urgent orders only
                </label>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.orders.index') }}" class="bp-btn-ghost">Reset</a>
                    <button type="submit" class="bp-btn-primary">Apply</button>
                </div>
            </div>
        </form>
    </section>

    {{-- Orders Table --}}
    <section class="bp-card overflow-hidden">
        <header class="bp-card-header flex items-center justify-between gap-4">
            <div>
                <p class="bp-spec text-amber-ink">§ Commerce · Orders</p>
                <h2 class="mt-1 font-display text-xl font-bold text-ink tracking-[-0.02em]">
                    Order Registry<span class="text-amber">.</span>
                </h2>
            </div>
            <p class="font-mono text-xs text-ink-muted tabular-nums">
                {{ number_format($orders->total()) }} records
            </p>
        </header>

        <form method="POST" action="{{ route('admin.orders.bulk-status') }}"
              x-data="{ checked: [] }">
            @csrf

            {{-- Bulk action bar --}}
            <div x-show="checked.length > 0" x-cloak
                 class="flex items-center gap-3 px-5 py-3 bg-ink/5 border-b border-rule">
                <span class="font-mono text-xs text-ink"
                      x-text="checked.length + ' order' + (checked.length > 1 ? 's' : '') + ' selected'"></span>
                <div class="flex items-center gap-2 ml-auto">
                    <select name="status" class="bp-select text-xs py-1.5">
                        @foreach(\App\Enums\OrderStatus::cases() as $s)
                            <option value="{{ $s->value }}">Set → {{ $s->label() }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="bp-btn-primary text-[10px] py-1.5 px-3">Apply</button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="bp-table">
                    <thead>
                        <tr>
                            <th class="w-10">
                                <input type="checkbox"
                                       class="rounded-none border-rule"
                                       x-on:change="checked = $event.target.checked
                                           ? {{ json_encode($orders->pluck('id')->toArray()) }} : []"
                                       :checked="checked.length === {{ $orders->count() }} && {{ $orders->count() }} > 0">
                            </th>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Total</th>
                            <th class="text-right pr-5">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                            <tr>
                                <td>
                                    <input type="checkbox" name="order_ids[]"
                                           value="{{ $order->id }}"
                                           class="rounded-none border-rule"
                                           x-model="checked">
                                </td>
                                <td>
                                    <p class="font-mono text-sm font-bold text-ink">
                                        {{ $order->order_number }}
                                        @if($order->urgent_processing)
                                            <span class="ml-1 inline-flex items-center gap-0.5 border border-amber-ink/40 bg-amber/10 px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider text-amber-ink">
                                                <x-heroicon-s-bolt class="w-2.5 h-2.5" />
                                                URGENT
                                            </span>
                                        @endif
                                    </p>
                                    <p class="mt-0.5 font-mono text-xs text-ink-muted">
                                        {{ $order->items->count() }} {{ Str::plural('item', $order->items->count()) }}
                                    </p>
                                </td>
                                <td>
                                    <p class="text-sm text-ink">
                                        {{ $order->user_id ? $order->user->email : $order->guest_email }}
                                    </p>
                                    <p class="mt-0.5 font-mono text-xs text-ink-muted">
                                        {{ $order->user_id ? 'Registered' : 'Guest' }}
                                        @if($order->is_b2b) · <span class="text-amber-ink">B2B</span> @endif
                                    </p>
                                </td>
                                <td>
                                    <p class="font-mono text-xs tabular-nums text-ink">{{ $order->created_at->format('Y-m-d') }}</p>
                                    <p class="font-mono text-xs text-ink-muted">{{ $order->created_at->format('H:i') }}</p>
                                </td>
                                <td>
                                    @php
                                        $sc = match($order->status->value) {
                                            'pending'          => 'border-amber-ink/40 bg-amber/10 text-amber-ink',
                                            'paid'             => 'border-blue-600/30 bg-blue-50 text-blue-700',
                                            'processing'       => 'border-indigo-600/30 bg-indigo-50 text-indigo-700',
                                            'shipped'          => 'border-purple-600/30 bg-purple-50 text-purple-700',
                                            'delivered'        => 'border-green-600/30 bg-green-50 text-green-700',
                                            'cancelled'        => 'border-red-600/30 bg-red-50 text-red-700',
                                            'refund_requested' => 'border-orange-600/30 bg-orange-50 text-orange-700',
                                            'refunded'         => 'border-rule bg-ivory-alt text-ink-muted',
                                            default            => 'border-rule bg-ivory-alt text-ink-muted',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider {{ $sc }}">
                                        {{ $order->status->label() }}
                                    </span>
                                </td>
                                <td>
                                    <p class="font-mono text-sm font-bold tabular-nums text-ink">{{ format_money($order->grand_total) }}</p>
                                    @if($order->vat_exempt)
                                        <p class="font-mono text-[10px] text-green-700 uppercase tracking-wider">ex. VAT</p>
                                    @endif
                                </td>
                                <td class="text-right pr-5">
                                    <a href="{{ route('admin.orders.show', $order) }}" class="bp-btn-ghost gap-1 text-[10px]">
                                        <x-heroicon-o-eye class="w-3.5 h-3.5" />
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-16 text-center">
                                    <x-heroicon-o-document-magnifying-glass class="w-10 h-10 mx-auto text-ink/20 mb-3" />
                                    <p class="font-display font-bold text-ink">No orders found</p>
                                    <p class="mt-1 text-sm text-ink-muted">Try adjusting your filters.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($orders->hasPages())
                <div class="px-5 py-4 border-t border-rule">
                    {{ $orders->withQueryString()->links() }}
                </div>
            @endif
        </form>
    </section>

</div>
@endsection
