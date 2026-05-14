@extends('layouts.admin')

@section('title', 'Order #' . $order->order_number)
@section('page_title', 'Order Detail')

@section('header_actions')
    <a href="{{ route('admin.orders.index') }}" class="bp-btn-ghost">
        <x-heroicon-o-arrow-left class="w-4 h-4" />
        Back
    </a>
    <a href="{{ route('admin.orders.packing-slip', $order) }}" target="_blank" class="bp-btn-outline">
        <x-heroicon-o-printer class="w-4 h-4" />
        Packing Slip
    </a>
    @if($order->invoice_number)
        <a href="{{ route('admin.orders.invoice', $order) }}" target="_blank" class="bp-btn-outline">
            <x-heroicon-o-document-text class="w-4 h-4" />
            Invoice
        </a>
    @endif
@endsection

@section('content')
<div class="space-y-6">

    {{-- Order Header Strip --}}
    <section class="bp-card overflow-hidden">
        <header class="bp-card-header flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="bp-spec text-amber-ink">§ Commerce · Order Detail</p>
                <h2 class="mt-1 font-display text-xl font-bold text-ink tracking-[-0.02em]">
                    {{ $order->order_number }}<span class="text-amber">.</span>
                </h2>
                <p class="mt-1 text-sm text-ink-muted">
                    Placed {{ $order->created_at->format('F j, Y \a\t H:i') }}
                    · {{ $order->user ? $order->user->email : $order->guest_email }}
                </p>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                @if($order->urgent_processing)
                    <span class="inline-flex items-center gap-1 border border-amber-ink/40 bg-amber/10 px-2.5 py-1 font-mono text-[10px] font-bold uppercase tracking-wider text-amber-ink">
                        <x-heroicon-s-bolt class="w-3 h-3" />
                        URGENT
                    </span>
                @endif
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
                <span class="inline-flex items-center border px-3 py-1 font-mono text-[11px] font-bold uppercase tracking-wider {{ $sc }}">
                    {{ $order->status->label() }}
                </span>
            </div>
        </header>
    </section>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- ══ LEFT: Items + Totals + History ══ --}}
        <div class="space-y-6 lg:col-span-2">

            {{-- Order Items --}}
            <section class="bp-card overflow-hidden">
                <header class="bp-card-header">
                    <p class="bp-spec text-ink-muted">§ Line Items</p>
                </header>
                <div class="divide-y divide-rule">
                    @foreach($order->items as $item)
                        <div class="flex items-start justify-between gap-4 p-5">
                            <div class="flex items-start gap-4 min-w-0">
                                <div class="w-12 h-12 border border-rule bg-ivory-alt flex items-center justify-center shrink-0">
                                    <x-heroicon-o-cube class="w-6 h-6 text-ink/30" />
                                </div>
                                <div class="min-w-0">
                                    <p class="font-medium text-ink truncate">
                                        {{ $item->product_name_snapshot ?? ($item->product?->name ? trans_field($item->product->name) : '—') }}
                                    </p>
                                    <p class="mt-0.5 font-mono text-xs text-amber-ink tracking-wider">{{ $item->oem_number_snapshot }}</p>
                                    <p class="mt-1 text-sm text-ink-muted">
                                        {{ $item->manufacturer_snapshot }}
                                        @if($item->condition_snapshot)
                                            · <span class="capitalize">{{ str_replace('_', ' ', $item->condition_snapshot) }}</span>
                                        @endif
                                    </p>
                                    <p class="mt-1 font-mono text-xs text-ink-muted tabular-nums">
                                        {{ $item->quantity }} × {{ format_money($item->unit_price) }}
                                    </p>
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="font-mono font-bold tabular-nums text-ink">{{ format_money($item->total_price) }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Totals --}}
                <div class="border-t border-rule-strong bg-ivory-alt p-5">
                    <div class="ml-auto max-w-xs space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-ink-muted">Subtotal</span>
                            <span class="font-mono tabular-nums text-ink">{{ format_money($order->subtotal) }}</span>
                        </div>
                        @if($order->discount_amount > 0)
                            <div class="flex justify-between text-sm">
                                <span class="text-ink-muted">Discount</span>
                                <span class="font-mono tabular-nums text-green-700">−{{ format_money($order->discount_amount) }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between text-sm">
                            <span class="text-ink-muted">Shipping</span>
                            <span class="font-mono tabular-nums text-ink">{{ format_money($order->shipping_cost) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-ink-muted">VAT</span>
                            <span class="font-mono tabular-nums text-ink">
                                {{ $order->vat_exempt ? '€0.00 (Exempt)' : format_money($order->vat_amount) }}
                            </span>
                        </div>
                        @if($order->urgent_processing_fee > 0)
                            <div class="flex justify-between text-sm">
                                <span class="text-ink-muted">Urgent fee</span>
                                <span class="font-mono tabular-nums text-amber-ink">+{{ format_money($order->urgent_processing_fee) }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between border-t border-rule pt-3 font-display font-bold text-ink">
                            <span>Grand Total</span>
                            <span class="font-mono text-xl tabular-nums">{{ format_money($order->grand_total) }}</span>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Status History --}}
            <section class="bp-card overflow-hidden">
                <header class="bp-card-header">
                    <p class="bp-spec text-ink-muted">§ Status History</p>
                </header>
                <div class="p-5">
                    @forelse($order->statusHistory as $history)
                        <div class="flex items-start gap-4 py-3 border-b border-rule last:border-0">
                            <div class="w-8 h-8 border border-rule bg-ivory-alt flex items-center justify-center shrink-0">
                                <x-heroicon-o-arrow-path class="w-3.5 h-3.5 text-ink-muted" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-medium text-ink">
                                        {{ $history->old_status->label() }}
                                        <x-heroicon-o-arrow-right class="w-3 h-3 inline text-ink-muted" />
                                        {{ $history->new_status->label() }}
                                    </p>
                                    <span class="font-mono text-xs text-ink-muted shrink-0">
                                        {{ $history->created_at->diffForHumans() }}
                                    </span>
                                </div>
                                <p class="mt-0.5 text-sm text-ink-muted">
                                    by {{ $history->admin?->name ?? 'System' }}
                                </p>
                                @if($history->note)
                                    <p class="mt-2 border-l-2 border-amber pl-3 text-sm text-ink-muted">{{ $history->note }}</p>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-ink-muted">No status changes recorded.</p>
                    @endforelse
                </div>
            </section>

            {{-- Internal Notes --}}
            @if($order->notes->count())
                <section class="bp-card overflow-hidden">
                    <header class="bp-card-header">
                        <p class="bp-spec text-ink-muted">§ Internal Notes</p>
                    </header>
                    <div class="divide-y divide-rule">
                        @foreach($order->notes as $note)
                            <div class="p-5">
                                <div class="flex items-center justify-between mb-2">
                                    <p class="text-sm font-medium text-ink">{{ $note->admin?->name ?? 'Admin' }}</p>
                                    <span class="font-mono text-xs text-ink-muted">{{ $note->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="text-sm text-ink-muted">{{ $note->note }}</p>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        {{-- ══ RIGHT: Actions ══ --}}
        <div class="space-y-6">

            {{-- Update Status --}}
            <section class="bp-card overflow-hidden">
                <header class="bp-card-header">
                    <p class="bp-spec text-ink-muted">§ Update Status</p>
                </header>
                <form method="POST" action="{{ route('admin.orders.update-status', $order) }}" class="p-5 space-y-4">
                    @csrf
                    <div>
                        <label for="status" class="block bp-spec mb-2">§ New Status</label>
                        <select id="status" name="status" required class="bp-select">
                            @foreach($statuses as $status)
                                <option value="{{ $status->value }}"
                                        {{ $order->status->value === $status->value ? 'selected' : '' }}>
                                    {{ $status->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="status_note" class="block bp-spec mb-2">§ Note (optional)</label>
                        <textarea id="status_note" name="note" rows="3"
                                  class="bp-input resize-none"
                                  placeholder="Reason for status change..."></textarea>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-ink-muted cursor-pointer">
                        <input type="checkbox" name="notify_customer" value="1"
                               class="rounded-none border-rule">
                        Notify customer via email
                    </label>
                    <button type="submit" class="bp-btn-primary w-full justify-center">
                        Update Status
                    </button>
                </form>
            </section>

            {{-- Tracking --}}
            <section class="bp-card overflow-hidden">
                <header class="bp-card-header">
                    <p class="bp-spec text-ink-muted">§ Tracking Information</p>
                </header>
                <div class="p-5 space-y-4">
                    @if($order->tracking_number)
                        <div class="border border-rule bg-ivory-alt p-3 space-y-2">
                            <div>
                                <p class="bp-spec text-ink-muted">§ Tracking №</p>
                                <p class="mt-1 font-mono text-sm font-bold text-ink">{{ $order->tracking_number }}</p>
                            </div>
                            @if($order->carrier)
                                <div>
                                    <p class="bp-spec text-ink-muted">§ Carrier</p>
                                    <p class="mt-1 text-sm text-ink">{{ $order->carrier }}</p>
                                </div>
                            @endif
                        </div>
                    @endif
                    <form method="POST" action="{{ route('admin.orders.update-tracking', $order) }}" class="space-y-4">
                        @csrf
                        <div>
                            <label for="tracking_number" class="block bp-spec mb-2">§ Tracking Number</label>
                            <input type="text" id="tracking_number" name="tracking_number"
                                   value="{{ old('tracking_number', $order->tracking_number) }}"
                                   class="bp-input-mono">
                        </div>
                        <div>
                            <label for="carrier" class="block bp-spec mb-2">§ Carrier</label>
                            <select id="carrier" name="carrier" class="bp-select">
                                <option value="">Select carrier</option>
                                @foreach($carriers as $carrier)
                                    <option value="{{ $carrier->name }}" {{ $order->carrier === $carrier->name ? 'selected' : '' }}>
                                        {{ $carrier->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <label class="flex items-center gap-2 text-sm text-ink-muted cursor-pointer">
                            <input type="checkbox" name="notify_customer" value="1"
                                   class="rounded-none border-rule">
                            Notify customer
                        </label>
                        <button type="submit" class="bp-btn-outline w-full justify-center">
                            {{ $order->tracking_number ? 'Update Tracking' : 'Add Tracking' }}
                        </button>
                    </form>
                </div>
            </section>

            {{-- Customer & Address --}}
            <section class="bp-card overflow-hidden">
                <header class="bp-card-header">
                    <p class="bp-spec text-ink-muted">§ Customer · Shipping</p>
                </header>
                <div class="p-5 space-y-4">
                    <div>
                        <p class="bp-spec text-ink-muted mb-1">§ Customer</p>
                        @if($order->user)
                            <a href="{{ route('admin.customers.show', $order->user) }}"
                               class="text-sm font-medium text-ink hover:text-amber-ink underline decoration-dotted">
                                {{ $order->user->email }}
                            </a>
                            <p class="mt-0.5 font-mono text-xs text-ink-muted">Registered account</p>
                        @else
                            <p class="text-sm text-ink">{{ $order->guest_email }}</p>
                            <p class="mt-0.5 font-mono text-xs text-ink-muted">Guest checkout</p>
                        @endif
                    </div>
                    <div class="border-t border-rule pt-4">
                        <p class="bp-spec text-ink-muted mb-1">§ Ship To</p>
                        <p class="text-sm font-medium text-ink">{{ $order->shipping_name }}</p>
                        <p class="text-sm text-ink-muted">{{ $order->shipping_address_line1 }}</p>
                        <p class="text-sm text-ink-muted">{{ $order->shipping_city }}, {{ $order->shipping_postal_code }}</p>
                        <p class="font-mono text-sm text-ink-muted">{{ $order->shipping_country_code }}</p>
                    </div>
                    @if($order->is_b2b)
                        <div class="border-t border-rule pt-4">
                            <p class="bp-spec text-amber-ink mb-1">§ B2B</p>
                            <p class="text-sm font-medium text-ink">{{ $order->company_name }}</p>
                            <p class="font-mono text-sm text-ink-muted">VAT: {{ $order->vat_number }}</p>
                            @if($order->vat_exempt)
                                <span class="mt-2 inline-flex items-center border border-green-600/30 bg-green-50 px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider text-green-700">
                                    ✓ VAT Exempt
                                </span>
                            @endif
                        </div>
                    @endif
                    @if($order->customer_note)
                        <div class="border-t border-rule pt-4">
                            <p class="bp-spec text-ink-muted mb-1">§ Customer Note</p>
                            <p class="text-sm text-ink-muted italic">{{ $order->customer_note }}</p>
                        </div>
                    @endif
                </div>
            </section>

            {{-- Add Note --}}
            <section class="bp-card overflow-hidden">
                <header class="bp-card-header">
                    <p class="bp-spec text-ink-muted">§ Add Note</p>
                </header>
                <form method="POST" action="{{ route('admin.orders.add-note', $order) }}" class="p-5 space-y-4">
                    @csrf
                    <div>
                        <label for="note" class="block bp-spec mb-2">§ Note</label>
                        <textarea id="note" name="note" rows="3" required
                                  class="bp-input resize-none"
                                  placeholder="Internal note about this order..."></textarea>
                    </div>
                    <button type="submit" class="bp-btn-outline w-full justify-center">
                        <x-heroicon-o-chat-bubble-left class="w-4 h-4" />
                        Add Note
                    </button>
                </form>
            </section>

        </div>
    </div>
</div>
@endsection
