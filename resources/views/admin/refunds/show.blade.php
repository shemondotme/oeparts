@extends('layouts.admin')

@section('title', 'Refund #' . str_pad($refund->id, 5, '0', STR_PAD_LEFT))
@section('page_title', 'Refund Request')

@section('header_actions')
    <a href="{{ route('admin.refunds.index') }}" class="bp-btn-ghost gap-1">
        <x-heroicon-o-arrow-left class="w-4 h-4" />
        Back to Refunds
    </a>
@endsection

@section('content')
<div class="space-y-6">

    {{-- Refund Header Strip --}}
    <div class="bp-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <p class="bp-spec text-ink-muted">§ Refund Request</p>
                <h2 class="font-display text-2xl font-bold text-ink tracking-[-0.02em]">
                    #{{ str_pad($refund->id, 5, '0', STR_PAD_LEFT) }}<span class="text-amber">.</span>
                </h2>
                <p class="font-mono text-xs text-ink-muted">
                    Submitted {{ $refund->created_at->format('Y-m-d H:i') }}
                    · Order
                    <a href="{{ route('admin.orders.show', $refund->order) }}"
                       class="text-amber-ink hover:underline">{{ $refund->order->order_number }}</a>
                </p>
            </div>
            <div class="flex items-center gap-3">
                @php
                    $sc = match($refund->status->value) {
                        'pending'   => 'border-amber-ink/40 bg-amber/10 text-amber-ink',
                        'approved'  => 'border-green-600/30 bg-green-50 text-green-700',
                        'rejected'  => 'border-red-600/30 bg-red-50 text-red-700',
                        'processed' => 'border-blue-600/30 bg-blue-50 text-blue-700',
                        default     => 'border-rule bg-ivory-alt text-ink-muted',
                    };
                @endphp
                <span class="inline-flex items-center border px-3 py-1 font-mono text-xs font-bold uppercase tracking-wider {{ $sc }}">
                    {{ ucfirst($refund->status->value) }}
                </span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Left: Details + Items + Notes --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Refund Details --}}
            <section class="bp-card overflow-hidden">
                <header class="bp-card-header">
                    <p class="bp-spec text-ink-muted">§ Refund · Details</p>
                </header>
                <div class="p-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div>
                        <p class="bp-spec text-ink-muted mb-1">§ Order Number</p>
                        <a href="{{ route('admin.orders.show', $refund->order) }}"
                           class="font-mono text-sm font-bold text-amber-ink hover:underline">
                            {{ $refund->order->order_number }}
                        </a>
                    </div>
                    <div>
                        <p class="bp-spec text-ink-muted mb-1">§ Order Total</p>
                        <p class="font-mono text-sm font-bold tabular-nums text-ink">{{ format_money($refund->order->grand_total) }}</p>
                    </div>
                    <div>
                        <p class="bp-spec text-ink-muted mb-1">§ Amount Requested</p>
                        <p class="font-mono text-lg font-bold tabular-nums text-ink">{{ format_money($refund->amount_requested) }}</p>
                    </div>
                    <div>
                        <p class="bp-spec text-ink-muted mb-1">§ Customer</p>
                        <p class="text-sm text-ink">{{ $refund->user->email ?? 'N/A' }}</p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="bp-spec text-ink-muted mb-1">§ Reason</p>
                        <p class="text-sm text-ink leading-relaxed">{{ $refund->reason }}</p>
                    </div>
                    @if($refund->return_images)
                        <div class="md:col-span-2">
                            <p class="bp-spec text-ink-muted mb-2">§ Return Images</p>
                            <div class="flex flex-wrap gap-3">
                                @foreach($refund->return_images as $image)
                                    <a href="{{ $image }}" target="_blank"
                                       class="block w-20 h-20 border border-rule overflow-hidden hover:border-amber transition-colors">
                                        <img src="{{ $image }}" alt="Return image" class="w-full h-full object-cover">
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </section>

            {{-- Order Items --}}
            <section class="bp-card overflow-hidden">
                <header class="bp-card-header">
                    <p class="bp-spec text-ink-muted">§ Order · Items</p>
                </header>
                <div class="divide-y divide-rule">
                    @foreach($refund->order->items as $item)
                        <div class="p-5 flex items-start justify-between gap-4">
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 bg-ivory-alt border border-rule flex items-center justify-center shrink-0">
                                    <x-heroicon-o-cube class="w-5 h-5 text-ink-muted" />
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-ink">
                                        {{ $item->product->manufacturer->name ?? 'Unknown' }}
                                    </p>
                                    <p class="font-mono text-xs text-amber-ink tracking-wider mt-0.5">
                                        {{ $item->product->oem_number }}
                                    </p>
                                    <p class="font-mono text-xs text-ink-muted mt-1">
                                        {{ $item->product->condition->value }} · Qty {{ $item->quantity }} × {{ format_money($item->unit_price) }}
                                    </p>
                                </div>
                            </div>
                            <p class="font-mono text-sm font-bold tabular-nums text-ink shrink-0">
                                {{ format_money($item->total_price) }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Admin Notes --}}
            <section class="bp-card overflow-hidden">
                <header class="bp-card-header">
                    <p class="bp-spec text-ink-muted">§ Admin · Notes</p>
                </header>
                <div class="p-5">
                    @if($refund->admin_note)
                        <div class="border-l-2 border-amber pl-4 py-1">
                            <p class="text-sm text-ink whitespace-pre-line leading-relaxed">{{ $refund->admin_note }}</p>
                            @if($refund->processed_at)
                                <p class="font-mono text-xs text-ink-muted mt-2">
                                    Processed {{ $refund->processed_at->format('Y-m-d H:i') }}
                                </p>
                            @endif
                        </div>
                    @else
                        <p class="text-sm text-ink-muted text-center py-6">No admin notes recorded yet.</p>
                    @endif
                </div>
            </section>

        </div>

        {{-- Right: Actions + Summary + Customer --}}
        <div class="space-y-6">

            {{-- Update Status --}}
            <section class="bp-card overflow-hidden">
                <header class="bp-card-header">
                    <p class="bp-spec text-ink-muted">§ Update · Status</p>
                </header>
                <form method="POST" action="{{ route('admin.refunds.update-status', $refund) }}"
                      class="p-5 space-y-4">
                    @csrf
                    <div>
                        <label for="status" class="block bp-spec mb-2">§ New Status</label>
                        <select id="status" name="status" required class="bp-select">
                            @foreach($statuses as $status)
                                <option value="{{ $status->value }}" {{ $refund->status->value === $status->value ? 'selected' : '' }}>
                                    {{ ucfirst($status->value) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="admin_note" class="block bp-spec mb-2">§ Admin Note</label>
                        <textarea id="admin_note" name="admin_note" rows="4" required
                                  class="bp-input w-full resize-none"
                                  placeholder="Explain your decision...">{{ old('admin_note', $refund->admin_note) }}</textarea>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-ink-muted cursor-pointer">
                        <input type="checkbox" id="notify_customer" name="notify_customer" value="1"
                               class="rounded-none border-rule">
                        Notify customer via email
                    </label>

                    <button type="submit" class="bp-btn-primary w-full justify-center">
                        Update Status
                    </button>
                </form>
            </section>

            {{-- Process Refund (approved only) --}}
            @if($refund->status->value === 'approved')
                <section class="bp-card overflow-hidden border-green-600/30">
                    <header class="bp-card-header bg-green-50 border-b border-green-600/20">
                        <p class="bp-spec text-green-700">§ Process · Refund</p>
                    </header>
                    <div class="p-5">
                        <p class="text-sm text-ink-muted mb-4">
                            Mark this refund as processed and update the order status to Refunded.
                        </p>
                        <form method="POST" action="{{ route('admin.refunds.process', $refund) }}"
                              class="space-y-4">
                            @csrf
                            <div>
                                <label for="refund_amount" class="block bp-spec mb-2">§ Refund Amount (€)</label>
                                <input type="text" id="refund_amount" name="amount"
                                       inputmode="decimal" required
                                       value="{{ old('amount', $refund->amount_requested > 0 ? number_format($refund->amount_requested, 2, '.', '') : number_format($refund->order->grand_total, 2, '.', '')) }}"
                                       class="bp-input-mono w-full">
                            </div>
                            <div>
                                <label for="processed_note" class="block bp-spec mb-2">§ Processing Note</label>
                                <textarea id="processed_note" name="processed_note" rows="3" required
                                          class="bp-input w-full resize-none"
                                          placeholder="Add details about the refund processing..."></textarea>
                            </div>

                            <label class="flex items-center gap-2 text-sm text-ink-muted cursor-pointer">
                                <input type="checkbox" id="process_notify" name="notify_customer" value="1"
                                       class="rounded-none border-rule">
                                Notify customer
                            </label>

                            <button type="submit"
                                    class="w-full py-2.5 px-4 bg-green-700 text-white font-mono text-xs font-bold uppercase tracking-wider hover:bg-green-800 transition-colors flex items-center justify-center gap-2">
                                <x-heroicon-o-banknotes class="w-4 h-4" />
                                Mark as Processed
                            </button>
                        </form>
                    </div>
                </section>
            @endif

            {{-- Order Summary --}}
            <section class="bp-card overflow-hidden">
                <header class="bp-card-header">
                    <p class="bp-spec text-ink-muted">§ Order · Summary</p>
                </header>
                <div class="p-5 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-ink-muted">Order Status</span>
                        <span class="font-mono text-xs font-bold text-ink uppercase tracking-wider">{{ $refund->order->status->label() }}</span>
                    </div>
                    <div class="flex items-center justify-between border-t border-rule pt-3">
                        <span class="text-xs text-ink-muted">Payment Method</span>
                        <span class="font-mono text-xs text-ink uppercase">{{ ucfirst($refund->order->payment_method->value) }}</span>
                    </div>
                    <div class="flex items-center justify-between border-t border-rule pt-3">
                        <span class="text-xs text-ink-muted">Payment Status</span>
                        <span class="font-mono text-xs text-ink uppercase">{{ ucfirst($refund->order->payment_status->value) }}</span>
                    </div>
                    <div class="flex items-center justify-between border-t border-rule pt-3">
                        <span class="text-xs text-ink-muted">Order Date</span>
                        <span class="font-mono text-xs tabular-nums text-ink">{{ $refund->order->created_at->format('Y-m-d') }}</span>
                    </div>
                </div>
                <div class="px-5 pb-5">
                    <a href="{{ route('admin.orders.show', $refund->order) }}" class="bp-btn-outline w-full justify-center gap-1">
                        <x-heroicon-o-arrow-right-circle class="w-4 h-4" />
                        View Full Order
                    </a>
                </div>
            </section>

            {{-- Customer Info --}}
            <section class="bp-card overflow-hidden">
                <header class="bp-card-header">
                    <p class="bp-spec text-ink-muted">§ Customer · Info</p>
                </header>
                <div class="p-5 space-y-3">
                    <div>
                        <p class="bp-spec text-ink-muted mb-1">§ Email</p>
                        <p class="text-sm text-ink">{{ $refund->user->email ?? 'N/A' }}</p>
                    </div>
                    @if($refund->user)
                        <div class="border-t border-rule pt-3">
                            <p class="bp-spec text-ink-muted mb-1">§ Account Created</p>
                            <p class="font-mono text-xs tabular-nums text-ink">{{ $refund->user->created_at->format('Y-m-d') }}</p>
                        </div>
                        <div class="border-t border-rule pt-3">
                            <p class="bp-spec text-ink-muted mb-1">§ Total Orders</p>
                            <p class="font-mono text-2xl font-bold tabular-nums text-ink">{{ $refund->user->orders()->count() }}</p>
                        </div>
                    @endif
                </div>
            </section>

        </div>
    </div>

</div>
@endsection
