@extends('layouts.app')

@section('title', __('Order :number', ['number' => $order->order_number]) . ' — ' . settings('general.site_name', 'OeParts'))

@section('meta_robots')<meta name="robots" content="noindex, nofollow">@endsection

@php
    $lang = app()->getLocale();

    // Normalise order-status progression (order of fulfilment, not all cases).
    $timeline = [
        ['key' => 'pending',    'label' => __('Placed'),     'icon' => 'heroicon-s-pencil-square',    'done' => true],
        ['key' => 'paid',       'label' => __('Paid'),       'icon' => 'heroicon-s-credit-card',      'done' => false],
        ['key' => 'processing', 'label' => __('Processing'), 'icon' => 'heroicon-s-cog-6-tooth',      'done' => false],
        ['key' => 'shipped',    'label' => __('Shipped'),    'icon' => 'heroicon-s-truck',            'done' => false],
        ['key' => 'delivered',  'label' => __('Delivered'),  'icon' => 'heroicon-s-check-circle',     'done' => false],
    ];

    $statusValue = $order->status?->value ?? 'pending';
    $completedMap = [
        'pending'     => ['pending'],
        'paid'        => ['pending', 'paid'],
        'processing'  => ['pending', 'paid', 'processing'],
        'shipped'     => ['pending', 'paid', 'processing', 'shipped'],
        'delivered'   => ['pending', 'paid', 'processing', 'shipped', 'delivered'],
        'cancelled'   => ['pending'],
        'refunded'    => ['pending', 'paid', 'processing'],
        'refund_requested' => ['pending', 'paid', 'processing'],
    ];
    $doneKeys = $completedMap[$statusValue] ?? ['pending'];

    $isTerminalNegative = in_array($statusValue, ['cancelled'], true);
    $progressIndex = count($doneKeys) - 1;
    $progressPct = max(0, min(100, ($progressIndex / (count($timeline) - 1)) * 100));

    // Bank-transfer details for pending-payment orders.
    $showBankTransferNote = $order->payment_method?->value === 'bank_transfer'
        && $order->payment_status?->value === 'pending';

    // Discount shown if > 0.
    $hasDiscount = ((float) $order->discount_amount) > 0;
@endphp

@section('content')
<x-account.shell
    active="orders"
    eyebrow="§ Order · Detail · Record"
    :title="'Order ' . $order->order_number"
    :subtitle="__('Placed on :date', ['date' => $order->created_at->format('F j, Y · H:i')])"
    :docId="'DOC · ' . $order->order_number"
    :breadcrumb="[
        ['label' => 'Orders', 'href' => route('frontend.account.orders', ['lang' => $lang])],
        ['label' => $order->order_number],
    ]"
>
    <x-slot name="actions">
        @if(in_array($statusValue, ['paid', 'processing', 'shipped', 'delivered']))
            <a href="{{ route('frontend.account.order.invoice', ['lang' => $lang, 'order' => $order]) }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 border border-ivory/20 text-ivory
                      font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                      hover:border-amber hover:text-amber transition-colors">
                <x-heroicon-s-document-arrow-down class="w-4 h-4" />
                Invoice
            </a>
        @endif

        @if(in_array($statusValue, ['pending', 'paid']))
            <form method="POST"
                  action="{{ route('frontend.account.order.cancel', ['lang' => $lang, 'order' => $order]) }}"
                  onsubmit="return confirm('{{ __('Are you sure you want to cancel this order?') }}');">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2.5 border border-red-400 bg-transparent text-red-200
                               font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                               hover:bg-red-600 hover:border-red-600 hover:text-ivory transition-colors">
                    <x-heroicon-s-x-mark class="w-4 h-4" />
                    Cancel order
                </button>
            </form>
        @endif

        @if(in_array($statusValue, ['delivered']))
            <a href="{{ route('frontend.account.order.refund.form', ['lang' => $lang, 'order' => $order]) }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 border border-ivory/20 text-ivory
                      font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                      hover:border-amber hover:text-amber transition-colors">
                <x-heroicon-s-arrow-path class="w-4 h-4" />
                Request refund
            </a>
        @endif
    </x-slot>

    {{-- Bank-transfer reminder --}}
    @if($showBankTransferNote)
        <div class="mb-6 border border-amber bg-amber/10 p-5 flex items-start gap-3"
             style="box-shadow: 4px 4px 0 rgba(20,22,29,1);">
            <div class="w-9 h-9 border border-amber bg-paper flex items-center justify-center shrink-0">
                <x-heroicon-s-exclamation-circle class="w-4 h-4 text-amber-ink" />
            </div>
            <div>
                <p class="bp-spec text-amber-ink mb-1">§ Awaiting · Bank · Transfer</p>
                <p class="text-sm text-body leading-relaxed">
                    {{ __('Your order is held until we confirm the bank transfer. Please complete the transfer using :ref as the reference. Processing starts within 1 business day after funds clear.', ['ref' => $order->order_number]) }}
                </p>
            </div>
        </div>
    @endif

    {{-- ── Status timeline ──────────────────────────────────────────── --}}
    <section class="mb-6 border border-ink bg-paper" style="box-shadow: 6px 6px 0 rgba(20,22,29,1);">
        <header class="flex items-center justify-between px-5 py-3 border-b border-ink bg-ivory-alt">
            <span class="bp-spec text-amber-ink flex items-center gap-2">
                <x-heroicon-o-signal class="w-3.5 h-3.5" />
                § 01 · Fulfilment · Status
            </span>
            <span class="font-mono text-[10px] tracking-[0.22em] uppercase
                         {{ $isTerminalNegative ? 'text-red-700' : 'text-emerald-700' }} flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 {{ $isTerminalNegative ? 'bg-red-600' : 'bg-emerald-600' }} rounded-full"></span>
                {{ $order->status->label() }}
            </span>
        </header>

        <div class="p-6 sm:p-8">
            @if($isTerminalNegative)
                <div class="flex items-center gap-4">
                    <div class="w-11 h-11 border border-red-600 bg-red-50 flex items-center justify-center shrink-0">
                        <x-heroicon-s-x-mark class="w-5 h-5 text-red-600" />
                    </div>
                    <div>
                        <p class="font-display text-lg font-bold text-ink">Order cancelled</p>
                        <p class="font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted mt-1">
                            Last updated · {{ $order->updated_at->format('Y-m-d · H:i') }}
                        </p>
                    </div>
                </div>
            @else
                {{-- Timeline steps --}}
                <div class="relative">
                    <div class="absolute top-5 left-5 right-5 h-[2px] bg-rule"></div>
                    <div class="absolute top-5 left-5 h-[2px] bg-amber transition-all duration-500"
                         style="width: calc({{ $progressPct }}% - {{ $progressPct }}px)"></div>

                    <div class="relative grid grid-cols-5 gap-2">
                        @foreach($timeline as $i => $step)
                            @php $isDone = in_array($step['key'], $doneKeys, true); @endphp
                            <div class="flex flex-col items-center text-center">
                                <div class="w-10 h-10 flex items-center justify-center border transition-all
                                            {{ $isDone
                                                ? 'bg-amber border-amber text-ink'
                                                : 'bg-paper border-rule-strong text-ink-muted' }}"
                                     style="{{ $isDone ? 'box-shadow: 2px 2px 0 rgba(20,22,29,1);' : '' }}">
                                    @if($isDone)
                                        <x-heroicon-s-check class="w-4 h-4" />
                                    @else
                                        <span class="font-mono text-[10px] font-bold tabular-nums">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                    @endif
                                </div>
                                <p class="mt-3 font-mono text-[10px] font-bold tracking-[0.18em] uppercase
                                          {{ $isDone ? 'text-ink' : 'text-ink-muted' }}">
                                    {{ $step['label'] }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if($order->tracking_number)
                    <div class="mt-8 border border-emerald-600 bg-emerald-50 px-4 py-3 flex items-center justify-between flex-wrap gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 border border-emerald-600 bg-paper flex items-center justify-center shrink-0">
                                <x-heroicon-s-truck class="w-4 h-4 text-emerald-700" />
                            </div>
                            <div>
                                <p class="bp-spec text-emerald-700 mb-0.5">§ Tracking · Number</p>
                                <p class="font-mono text-sm font-bold text-ink tabular-nums">{{ $order->tracking_number }}</p>
                            </div>
                        </div>
                        @if($order->carrier)
                            <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-emerald-800">
                                via {{ $order->carrier }}
                            </span>
                        @endif
                    </div>
                @endif
            @endif
        </div>
    </section>

    <div class="grid grid-cols-12 gap-x-4 sm:gap-x-6 gap-y-6 items-start">

        {{-- ── Left: Items + Totals ─────────────────────────────────── --}}
        <div class="col-span-12 lg:col-span-8 space-y-6">

            {{-- Order items --}}
            <section class="border border-ink bg-paper" style="box-shadow: 6px 6px 0 rgba(20,22,29,1);">
                <header class="flex items-center justify-between px-5 py-3 border-b border-ink bg-ivory-alt">
                    <span class="bp-spec text-amber-ink flex items-center gap-2">
                        <x-heroicon-o-cube class="w-3.5 h-3.5" />
                        § 02 · Line · Items
                    </span>
                    <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">
                        {{ $order->items->count() }} {{ \Illuminate\Support\Str::plural('row', $order->items->count()) }}
                    </span>
                </header>

                <ul class="divide-y divide-rule">
                    @foreach($order->items as $i => $item)
                        @php
                            $productName = $item->product
                                ? (is_array($item->product->name) ? (trans_field($item->product->name) ?: 'Part') : $item->product->name)
                                : ($item->oem_number_snapshot ?: 'Part');
                            $isNew = $item->condition_snapshot === 'new';
                            $lineTotal = $item->total_price ?? bcmul((string) $item->unit_price, (string) $item->quantity, 2);
                        @endphp
                        <li class="px-5 py-4 flex items-start gap-4">
                            <span class="font-mono text-[10px] tabular-nums tracking-[0.22em] uppercase text-ink-muted w-8 pt-0.5">
                                {{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}
                            </span>
                            <div class="w-11 h-11 border border-rule-strong bg-ivory-alt flex items-center justify-center shrink-0">
                                <x-heroicon-o-cube class="w-5 h-5 text-ink-muted" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-display text-sm font-bold text-ink tracking-[-0.01em] truncate">
                                    {{ $productName }}
                                </p>
                                <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1">
                                    <span class="font-mono text-[10px] tracking-[0.18em] uppercase text-amber-ink">
                                        OEM · {{ $item->oem_number_snapshot }}
                                    </span>
                                    @if($item->manufacturer_snapshot)
                                        <span class="font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted">
                                            {{ $item->manufacturer_snapshot }}
                                        </span>
                                    @endif
                                    @if($item->condition_snapshot)
                                        <span class="inline-flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 {{ $isNew ? 'bg-emerald-600' : 'bg-blue-600' }}"></span>
                                            <span class="font-mono text-[10px] tracking-[0.18em] uppercase
                                                         {{ $isNew ? 'text-emerald-700' : 'text-blue-700' }}">
                                                {{ ucfirst($item->condition_snapshot) }}
                                            </span>
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted">
                                    {{ $item->quantity }} × €{{ number_format((float) $item->unit_price, 2) }}
                                </p>
                                <p class="mt-1 font-mono text-base font-medium text-ink tabular-nums">
                                    €{{ number_format((float) $lineTotal, 2) }}
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- Totals ledger --}}
            <section class="border border-ink bg-paper" style="box-shadow: 6px 6px 0 rgba(20,22,29,1);">
                <header class="px-5 py-3 border-b border-ink bg-ivory-alt">
                    <span class="bp-spec text-amber-ink flex items-center gap-2">
                        <x-heroicon-o-calculator class="w-3.5 h-3.5" />
                        § 03 · Totals · Ledger
                    </span>
                </header>
                <dl class="px-5 py-4 space-y-2">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">Subtotal</dt>
                        <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                        <dd class="font-mono text-sm text-ink tabular-nums">€{{ number_format((float) $order->subtotal, 2) }}</dd>
                    </div>
                    @if($hasDiscount)
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">Discount</dt>
                        <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                        <dd class="font-mono text-sm text-emerald-700 tabular-nums">−€{{ number_format((float) $order->discount_amount, 2) }}</dd>
                    </div>
                    @endif
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">Shipping</dt>
                        <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                        <dd class="font-mono text-sm text-ink tabular-nums">€{{ number_format((float) $order->shipping_cost, 2) }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">VAT</dt>
                        <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                        <dd class="font-mono text-sm text-ink tabular-nums">€{{ number_format((float) $order->vat_amount, 2) }}</dd>
                    </div>
                </dl>
                <div class="px-5 py-4 border-t-2 border-ink flex items-end justify-between gap-3">
                    <div>
                        <p class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink">Grand total</p>
                        <p class="font-mono text-[9px] tracking-[0.2em] uppercase text-ink-muted mt-1">EUR · incl. VAT</p>
                    </div>
                    <p class="font-mono text-3xl font-medium text-ink tabular-nums leading-none tracking-tight">
                        €{{ number_format((float) $order->grand_total, 2) }}
                    </p>
                </div>
            </section>

            @if($order->customer_note)
                <section class="border border-ink bg-paper" style="box-shadow: 6px 6px 0 rgba(20,22,29,1);">
                    <header class="px-5 py-3 border-b border-ink bg-ivory-alt">
                        <span class="bp-spec text-amber-ink flex items-center gap-2">
                            <x-heroicon-o-pencil-square class="w-3.5 h-3.5" />
                            § 04 · Customer · Note
                        </span>
                    </header>
                    <div class="p-5 text-sm text-body leading-relaxed whitespace-pre-line">
                        {{ $order->customer_note }}
                    </div>
                </section>
            @endif
        </div>

        {{-- ── Right: Address + Payment ─────────────────────────────── --}}
        <aside class="col-span-12 lg:col-span-4 space-y-4">

            {{-- Shipping address --}}
            <div class="border border-ink bg-paper" style="box-shadow: 4px 4px 0 rgba(20,22,29,1);">
                <div class="px-4 py-3 border-b border-ink bg-ivory-alt">
                    <span class="bp-spec text-amber-ink flex items-center gap-2">
                        <x-heroicon-o-map-pin class="w-3.5 h-3.5" />
                        § Ship-to · Address
                    </span>
                </div>
                <address class="p-4 not-italic text-sm text-body leading-relaxed space-y-1">
                    <p class="font-display font-bold text-ink tracking-[-0.01em]">{{ $order->shipping_name ?: '—' }}</p>
                    @if($order->company_name)
                        <p class="font-mono text-xs text-ink-muted">{{ $order->company_name }}</p>
                    @endif
                    <p class="font-mono text-xs text-ink-muted">{{ $order->shipping_address_line1 }}</p>
                    <p class="font-mono text-xs text-ink-muted">
                        {{ $order->shipping_postal_code }} · {{ $order->shipping_city }}
                    </p>
                    <p class="font-mono text-xs text-ink-muted uppercase tracking-[0.2em] pt-1">
                        {{ $order->shipping_country_code }}
                    </p>
                </address>
            </div>

            {{-- Shipping method --}}
            @if($order->shipping_method_name_snapshot)
                <div class="border border-ink bg-paper" style="box-shadow: 4px 4px 0 rgba(20,22,29,1);">
                    <div class="px-4 py-3 border-b border-ink bg-ivory-alt">
                        <span class="bp-spec text-amber-ink flex items-center gap-2">
                            <x-heroicon-o-truck class="w-3.5 h-3.5" />
                            § Shipping · Method
                        </span>
                    </div>
                    <div class="p-4">
                        <p class="font-display text-sm font-bold text-ink tracking-[-0.01em]">
                            {{ $order->shipping_method_name_snapshot }}
                        </p>
                        @if($order->shipping_estimated_days_min || $order->shipping_estimated_days_max)
                            <p class="mt-1 font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted">
                                Est. delivery · {{ $order->shipping_estimated_days_min }}–{{ $order->shipping_estimated_days_max }} days
                            </p>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Payment --}}
            <div class="border border-ink bg-paper" style="box-shadow: 4px 4px 0 rgba(20,22,29,1);">
                <div class="px-4 py-3 border-b border-ink bg-ivory-alt">
                    <span class="bp-spec text-amber-ink flex items-center gap-2">
                        <x-heroicon-o-credit-card class="w-3.5 h-3.5" />
                        § Payment · Record
                    </span>
                </div>
                <div class="p-4">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 border border-rule-strong bg-ivory-alt flex items-center justify-center shrink-0">
                            @if($order->payment_method?->value === 'card')
                                <x-heroicon-o-credit-card class="w-5 h-5 text-ink" />
                            @else
                                <x-heroicon-o-building-library class="w-5 h-5 text-ink" />
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-display text-sm font-bold text-ink tracking-[-0.01em]">
                                {{ $order->payment_method?->value === 'card' ? __('Credit/Debit Card') : __('Bank Transfer') }}
                            </p>
                            @if($order->payment_reference)
                                <p class="mt-1 font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted">
                                    Ref · {{ $order->payment_reference }}
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-t border-rule flex items-center justify-between">
                        <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">Status</span>
                        @php
                            $ps = $order->payment_status?->value ?? 'pending';
                            $psBg = match($ps) {
                                'paid'     => 'bg-emerald-600',
                                'pending'  => 'bg-amber',
                                'failed'   => 'bg-red-600',
                                'refunded' => 'bg-amber-ink',
                                default    => 'bg-ink-muted',
                            };
                        @endphp
                        <span class="inline-flex items-center gap-2">
                            <span class="inline-block w-1.5 h-3 {{ $psBg }}"></span>
                            <span class="font-mono text-[10px] font-bold tracking-[0.18em] uppercase text-ink">
                                {{ ucfirst($ps) }}
                            </span>
                        </span>
                    </div>
                </div>
            </div>

            @if($order->is_b2b && ($order->company_name || $order->vat_number))
                <div class="border border-ink bg-paper" style="box-shadow: 4px 4px 0 rgba(20,22,29,1);">
                    <div class="px-4 py-3 border-b border-ink bg-ivory-alt">
                        <span class="bp-spec text-amber-ink flex items-center gap-2">
                            <x-heroicon-o-briefcase class="w-3.5 h-3.5" />
                            § B2B · Record
                        </span>
                    </div>
                    <dl class="p-4 space-y-2">
                        @if($order->company_name)
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">Company</dt>
                                <dd class="font-mono text-xs font-bold text-ink truncate">{{ $order->company_name }}</dd>
                            </div>
                        @endif
                        @if($order->vat_number)
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">VAT</dt>
                                <dd class="font-mono text-xs font-bold text-ink tabular-nums">{{ $order->vat_number }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            @endif
        </aside>
    </div>
</x-account.shell>
@endsection
