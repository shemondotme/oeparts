@extends('layouts.admin')

@section('title', 'Refunds')
@section('page_title', 'Refund Management')

@section('header_actions')
    <a href="{{ route('admin.refunds.export', request()->query()) }}" class="bp-btn-outline">
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
            <p class="mt-2 font-mono text-3xl font-bold tabular-nums text-ink">{{ number_format($refunds->total()) }}</p>
            <p class="mt-1 text-sm text-ink-muted">All requests</p>
        </div>
        <div class="bp-card p-5">
            <p class="bp-spec text-amber-ink">§ Pending</p>
            <p class="mt-2 font-mono text-3xl font-bold tabular-nums text-amber-ink">
                {{ \App\Models\RefundRequest::where('status', \App\Enums\RefundStatus::Pending)->count() }}
            </p>
            <p class="mt-1 text-sm text-ink-muted">Awaiting review</p>
        </div>
        <div class="bp-card p-5">
            <p class="bp-spec text-ink-muted">§ Approved</p>
            <p class="mt-2 font-mono text-3xl font-bold tabular-nums text-ink">
                {{ \App\Models\RefundRequest::where('status', \App\Enums\RefundStatus::Approved)->count() }}
            </p>
            <p class="mt-1 text-sm text-ink-muted">Awaiting processing</p>
        </div>
        <div class="bp-card p-5">
            <p class="bp-spec text-ink-muted">§ Processed</p>
            <p class="mt-2 font-mono text-3xl font-bold tabular-nums text-ink">
                {{ \App\Models\RefundRequest::where('status', \App\Enums\RefundStatus::Processed)->count() }}
            </p>
            <p class="mt-1 text-sm text-ink-muted">Completed</p>
        </div>
    </div>

    {{-- Filters --}}
    <section class="bp-card">
        <header class="bp-card-header">
            <p class="bp-spec text-ink-muted">§ Filter · Refunds</p>
        </header>
        <form method="GET" action="{{ route('admin.refunds.index') }}"
              class="p-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <label for="status" class="block bp-spec mb-2">§ Status</label>
                <select id="status" name="status" class="bp-select">
                    <option value="all" {{ request('status') == 'all' || !request('status') ? 'selected' : '' }}>All Statuses</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->value }}" {{ request('status') == $status->value ? 'selected' : '' }}>
                            {{ ucfirst($status->value) }}
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

            <div class="md:col-span-2 xl:col-span-4 flex items-center justify-end gap-4 pt-2 border-t border-rule">
                <a href="{{ route('admin.refunds.index') }}" class="bp-btn-ghost">Reset</a>
                <button type="submit" class="bp-btn-primary">Apply</button>
            </div>
        </form>
    </section>

    {{-- Refunds Table --}}
    <section class="bp-card overflow-hidden">
        <header class="bp-card-header flex items-center justify-between gap-4">
            <div>
                <p class="bp-spec text-amber-ink">§ Commerce · Refunds</p>
                <h2 class="mt-1 font-display text-xl font-bold text-ink tracking-[-0.02em]">
                    Refund Registry<span class="text-amber">.</span>
                </h2>
            </div>
            <p class="font-mono text-xs text-ink-muted tabular-nums">
                {{ number_format($refunds->total()) }} records
            </p>
        </header>

        <div class="overflow-x-auto">
            <table class="bp-table">
                <thead>
                    <tr>
                        <th>Refund</th>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th class="text-right pr-5">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($refunds as $refund)
                        <tr>
                            <td>
                                <p class="font-mono text-sm font-bold text-ink">#{{ str_pad($refund->id, 5, '0', STR_PAD_LEFT) }}</p>
                            </td>
                            <td>
                                <p class="font-mono text-sm font-bold text-ink">{{ $refund->order->order_number }}</p>
                                <p class="mt-0.5 font-mono text-xs text-ink-muted">{{ format_money($refund->order->grand_total) }}</p>
                            </td>
                            <td>
                                <p class="text-sm text-ink">{{ $refund->user->email ?? 'N/A' }}</p>
                            </td>
                            <td>
                                <p class="font-mono text-sm font-bold tabular-nums text-ink">{{ format_money($refund->amount_requested) }}</p>
                            </td>
                            <td>
                                @php
                                    $sc = match($refund->status->value) {
                                        'pending'   => 'border-amber-ink/40 bg-amber/10 text-amber-ink',
                                        'approved'  => 'border-green-600/30 bg-green-50 text-green-700',
                                        'rejected'  => 'border-red-600/30 bg-red-50 text-red-700',
                                        'processed' => 'border-blue-600/30 bg-blue-50 text-blue-700',
                                        default     => 'border-rule bg-ivory-alt text-ink-muted',
                                    };
                                @endphp
                                <span class="inline-flex items-center border px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider {{ $sc }}">
                                    {{ ucfirst($refund->status->value) }}
                                </span>
                            </td>
                            <td>
                                <p class="font-mono text-xs tabular-nums text-ink">{{ $refund->created_at->format('Y-m-d') }}</p>
                                <p class="font-mono text-xs text-ink-muted">{{ $refund->created_at->format('H:i') }}</p>
                            </td>
                            <td class="text-right pr-5">
                                <a href="{{ route('admin.refunds.show', $refund) }}" class="bp-btn-ghost gap-1 text-[10px]">
                                    <x-heroicon-o-eye class="w-3.5 h-3.5" />
                                    Review
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-16 text-center">
                                <x-heroicon-o-receipt-refund class="w-10 h-10 mx-auto text-ink/20 mb-3" />
                                <p class="font-display font-bold text-ink">No refund requests found</p>
                                <p class="mt-1 text-sm text-ink-muted">Try adjusting your filters.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($refunds->hasPages())
            <div class="px-5 py-4 border-t border-rule">
                {{ $refunds->withQueryString()->links() }}
            </div>
        @endif
    </section>

</div>
@endsection
