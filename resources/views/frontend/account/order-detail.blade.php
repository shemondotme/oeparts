@extends('layouts.app')

@section('title', ui_copy('account_order_title', 'account.order_title', ['number' => $order->order_number]) . ' — ' . settings('general.site_name', 'OeParts'))

@section('meta_robots')<meta name="robots" content="noindex, nofollow">@endsection

@php
    $lang = app()->getLocale();

    // Normalise order-status progression (order of fulfilment, not all cases).
    $timeline = [
        ['key' => 'pending',    'label' => ui_copy('account_timeline_placed', 'account.timeline_placed'),        'icon' => 'heroicon-s-pencil-square',    'done' => true],
        ['key' => 'paid',       'label' => ui_copy('account_timeline_paid', 'account.timeline_paid'),            'icon' => 'heroicon-s-credit-card',      'done' => false],
        ['key' => 'processing', 'label' => ui_copy('account_timeline_processing', 'account.timeline_processing'), 'icon' => 'heroicon-s-cog-6-tooth',      'done' => false],
        ['key' => 'shipped',    'label' => ui_copy('account_timeline_shipped', 'account.timeline_shipped'),       'icon' => 'heroicon-s-truck',            'done' => false],
        ['key' => 'delivered',  'label' => ui_copy('account_timeline_delivered', 'account.timeline_delivered'),   'icon' => 'heroicon-s-check-circle',     'done' => false],
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

    // 'cancelled' and the two refund states are all terminal — none of them
    // continue through the normal fulfilment timeline, so the progress bar
    // (which would otherwise show them "stuck" at Processing) is replaced
    // with a dedicated terminal panel for each.
    $terminalState = match ($statusValue) {
        'cancelled' => 'cancelled',
        'refund_requested', 'refunded' => 'refunded',
        default => null,
    };
    $isTerminalNegative = $terminalState !== null;
    $progressIndex = count($doneKeys) - 1;
    $progressPct = max(0, min(100, ($progressIndex / (count($timeline) - 1)) * 100));

    // Bank-transfer details for pending-payment orders.
    $showBankTransferNote = $order->payment_method?->value === 'bank_transfer'
        && $order->payment_status?->value === 'pending';

    // Discount shown if > 0.
    $hasDiscount = bccomp((string) $order->discount_amount, '0', 2) > 0;
@endphp

@section('content')
<x-account.shell
    active="orders"
    eyebrow="{{ ui_copy('account_order_detail_eyebrow', 'account.order_detail_eyebrow') }}"
    title="{{ ui_copy('account_order_title', 'account.order_title', ['number' => $order->order_number]) }}"
    :subtitle="ui_copy('account_placed_on', 'account.placed_on', ['date' => format_datetime($order->created_at)])"
    :docId="'DOC · ' . $order->order_number"
    :breadcrumb="[
        ['label' => ui_copy('account_nav_orders', 'account.nav_orders'), 'href' => route('frontend.account.orders', ['lang' => $lang])],
        ['label' => $order->order_number],
    ]"
>
    <x-slot name="actions">
        @if(in_array($statusValue, ['paid', 'processing', 'shipped', 'delivered', 'refund_requested', 'refunded']))
            <a href="{{ route('frontend.account.order.invoice', ['lang' => $lang, 'order' => $order]) }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 border border-ivory/20 text-ivory
                      font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                      hover:border-amber hover:text-amber transition-colors">
                <x-heroicon-s-document-arrow-down class="w-4 h-4" />
                {{ ui_copy('account_invoice', 'account.invoice') }}
            </a>
        @endif

        @if(in_array($statusValue, ['pending', 'paid', 'processing']))
            <form method="POST"
                  action="{{ route('frontend.account.order.cancel', ['lang' => $lang, 'order' => $order]) }}"
                  onsubmit="return confirm('{{ addslashes(ui_copy('account_cancel_order_confirm', 'account.cancel_order_confirm')) }}');">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2.5 border border-red-400 bg-transparent text-red-200
                               font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                               hover:bg-red-600 hover:border-red-600 hover:text-ivory transition-colors">
                    <x-heroicon-s-x-mark class="w-4 h-4" />
                    {{ ui_copy('account_cancel_order', 'account.cancel_order') }}
                </button>
            </form>
        @endif

        @if(in_array($statusValue, ['delivered']))
            <a href="{{ route('frontend.account.order.refund.form', ['lang' => $lang, 'order' => $order]) }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 border border-ivory/20 text-ivory
                      font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                      hover:border-amber hover:text-amber transition-colors">
                <x-heroicon-s-arrow-path class="w-4 h-4" />
                {{ ui_copy('account_request_refund', 'account.request_refund') }}
            </a>
        @endif
    </x-slot>

    {{-- Bank-transfer reminder --}}
    @if($showBankTransferNote)
        <div class="mb-6 border border-amber bg-amber/10 p-5 flex items-start gap-3 bp-shadow-sm">
            <div class="w-9 h-9 border border-amber bg-paper flex items-center justify-center shrink-0">
                <x-heroicon-s-exclamation-circle class="w-4 h-4 text-amber-ink" />
            </div>
            <div>
                <p class="bp-spec text-amber-ink mb-1">{{ ui_copy('account_bank_transfer_awaiting_eyebrow', 'account.bank_transfer_awaiting_eyebrow') }}</p>
                <p class="text-sm text-body leading-relaxed">
                    {{ ui_copy('account_bank_transfer_awaiting_note', 'account.bank_transfer_awaiting_note', ['ref' => $order->order_number]) }}
                </p>
            </div>
        </div>
    @endif

    {{-- ── Status timeline ──────────────────────────────────────────── --}}
    <section class="mb-6 border border-ink bg-paper bp-shadow">
        <header class="flex items-center justify-between px-5 py-3 border-b border-ink bg-ivory-alt">
            <span class="bp-spec text-amber-ink flex items-center gap-2">
                <x-heroicon-o-signal class="w-3.5 h-3.5" />
                {{ ui_copy('account_fulfilment_status_eyebrow', 'account.fulfilment_status_eyebrow') }}
            </span>
            <span class="font-mono text-[10px] tracking-[0.22em] uppercase
                         {{ $terminalState === 'cancelled' ? 'text-red-700' : ($terminalState === 'refunded' ? 'text-amber-ink' : 'text-emerald-700') }} flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 {{ $terminalState === 'cancelled' ? 'bg-red-600' : ($terminalState === 'refunded' ? 'bg-amber-ink' : 'bg-emerald-600') }} rounded-full"></span>
                {{ ui_copy('account_order_status_'.$order->status->value, 'account.order_status_'.$order->status->value) }}
            </span>
        </header>

        <div class="p-6 sm:p-8">
            @if($terminalState === 'cancelled')
                <div class="flex items-center gap-4">
                    <div class="w-11 h-11 border border-red-600 bg-red-50 flex items-center justify-center shrink-0">
                        <x-heroicon-s-x-mark class="w-5 h-5 text-red-600" />
                    </div>
                    <div>
                        <p class="font-display text-lg font-bold text-ink">{{ ui_copy('account_order_cancelled', 'account.order_cancelled') }}</p>
                        <p class="font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted mt-1">
                            {{ ui_copy('account_last_updated', 'account.last_updated', ['datetime' => $order->updated_at->format('Y-m-d · H:i')]) }}
                        </p>
                    </div>
                </div>
            @elseif($terminalState === 'refunded')
                <div class="flex items-center gap-4">
                    <div class="w-11 h-11 border border-amber bg-amber/10 flex items-center justify-center shrink-0">
                        <x-heroicon-s-arrow-path class="w-5 h-5 text-amber-ink" />
                    </div>
                    <div>
                        <p class="font-display text-lg font-bold text-ink">
                            {{ $statusValue === 'refunded' ? ui_copy('account_order_refunded', 'account.order_refunded') : ui_copy('account_order_refund_requested', 'account.order_refund_requested') }}
                        </p>
                        <p class="font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted mt-1">
                            {{ ui_copy('account_last_updated', 'account.last_updated', ['datetime' => $order->updated_at->format('Y-m-d · H:i')]) }}
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
                                                ? 'bg-amber border-amber text-ink bp-shadow-sm'
                                                : 'bg-paper border-rule-strong text-ink-muted' }}">
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
                                <p class="bp-spec text-emerald-700 mb-0.5">{{ ui_copy('account_tracking_number_label', 'account.tracking_number_label') }}</p>
                                <p class="font-mono text-sm font-bold text-ink tabular-nums">{{ $order->tracking_number }}</p>
                            </div>
                        </div>
                        @if($order->carrier)
                            <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-emerald-800">
                                {{ ui_copy('account_via_carrier', 'account.via_carrier', ['carrier' => $order->carrier]) }}
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
            <section class="border border-ink bg-paper bp-shadow">
                <header class="flex items-center justify-between px-5 py-3 border-b border-ink bg-ivory-alt">
                    <span class="bp-spec text-amber-ink flex items-center gap-2">
                        <x-heroicon-o-cube class="w-3.5 h-3.5" />
                        {{ ui_copy('account_line_items_eyebrow', 'account.line_items_eyebrow') }}
                    </span>
                    <span class="bp-spec-mono">
                        {{ $order->items->count() }} {{ ui_trans_choice('account_row_word', 'account.row_word', $order->items->count()) }}
                    </span>
                </header>

                <ul class="divide-y divide-rule">
                    @foreach($order->items as $i => $item)
                        @php
                            $partFallback = ui_copy('account_part_fallback', 'account.part_fallback');
                            $productName = $item->product
                                ? (is_array($item->product->name) ? (trans_field($item->product->name) ?: $partFallback) : $item->product->name)
                                : ($item->oem_number_snapshot ?: $partFallback);
                            $isNew = $item->condition_snapshot === 'new';
                            $lineTotal = $item->total_price;
                            // condition_snapshot is a historical slug (e.g. "used_a"), not a
                            // live Condition relation — resolve it the same way condition_label()
                            // does, without a per-row DB lookup.
                            $conditionKey = 'search.condition_label_' . str_replace('-', '_', (string) $item->condition_snapshot);
                            $conditionLabel = \Illuminate\Support\Facades\Lang::has($conditionKey)
                                ? __($conditionKey)
                                : ucfirst(str_replace('_', ' ', (string) $item->condition_snapshot));
                        @endphp
                        <li class="px-5 py-4 flex items-start gap-4">
                            <span class="font-mono text-[10px] tabular-nums tracking-[0.22em] uppercase text-ink-muted w-8 pt-0.5">
                                {{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}
                            </span>
                            <div class="w-11 h-11 border border-rule-strong bg-ivory-alt flex items-center justify-center shrink-0">
                                <x-heroicon-o-cube class="w-5 h-5 text-ink-muted" />
                            </div>
                            <div class="flex-1 min-w-0" x-data="clipboard()">
                                <p class="font-display text-sm font-bold text-ink tracking-[-0.01em] truncate">
                                    {{ $productName }}
                                </p>
                                <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1">
                                    <button type="button" class="appearance-none bg-transparent border-0 p-0 m-0 font-mono text-[10px] tracking-[0.18em] uppercase text-amber-ink cursor-pointer focus:outline-none focus:ring-2 focus:ring-inset focus:ring-amber-ink rounded-sm"
                                          @click="copy('{{ $item->oem_number_snapshot }}')" title="{{ ui_copy('account_copy_oem_title', 'account.copy_oem_title') }}">
                                        OEM · {{ $item->oem_number_snapshot }}
                                    </button>
                                    <span x-show="copied" x-cloak x-transition role="status" aria-live="polite" class="text-[10px] font-mono font-bold text-emerald-600 ml-2">{{ ui_copy('account_copied', 'account.copied') }}</span>
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
                                                {{ $conditionLabel }}
                                            </span>
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                    <p class="font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted">
                                        {{ $item->quantity }} × {{ format_price($item->unit_price) }}
                                    </p>
                                    <p class="mt-1 font-mono text-base font-medium text-ink tabular-nums">
                                        {{ format_price($lineTotal) }}
                                    </p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- Totals ledger --}}
            <section class="border border-ink bg-paper bp-shadow">
                <header class="px-5 py-3 border-b border-ink bg-ivory-alt">
                    <span class="bp-spec text-amber-ink flex items-center gap-2">
                        <x-heroicon-o-calculator class="w-3.5 h-3.5" />
                        {{ ui_copy('account_totals_ledger_eyebrow', 'account.totals_ledger_eyebrow') }}
                    </span>
                </header>
                <dl class="px-5 py-4 space-y-2">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="bp-spec-mono">{{ ui_copy('account_subtotal', 'account.subtotal') }}</dt>
                        <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                        <dd class="font-mono text-sm text-ink tabular-nums">{{ format_price($order->subtotal) }}</dd>
                    </div>
                    @if($hasDiscount)
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="bp-spec-mono">{{ ui_copy('account_discount', 'account.discount') }}</dt>
                        <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                        <dd class="font-mono text-sm text-emerald-700 tabular-nums">−{{ format_price($order->discount_amount) }}</dd>
                    </div>
                    @endif
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="bp-spec-mono">{{ ui_copy('account_shipping', 'account.shipping') }}</dt>
                        <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                        <dd class="font-mono text-sm text-ink tabular-nums">{{ format_price($order->shipping_cost) }}</dd>
                    </div>
                    @if($order->urgent_processing && bccomp((string) $order->urgent_processing_fee, '0', 2) > 0)
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="bp-spec-mono inline-flex items-center gap-1.5 text-amber-ink">
                            <x-heroicon-s-bolt class="w-3 h-3" />
                            {{ settings_trans('checkout.urgent_processing_label', 'Rush processing') }}
                        </dt>
                        <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                        <dd class="font-mono text-sm text-ink tabular-nums">{{ format_price($order->urgent_processing_fee) }}</dd>
                    </div>
                    @endif
                    @if(bccomp((string) $order->handling_fee, '0', 2) > 0)
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="bp-spec-mono">{{ ui_copy('checkout_handling_fee_label', 'checkout.handling_fee_label') }}</dt>
                        <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                        <dd class="font-mono text-sm text-ink tabular-nums">{{ format_price($order->handling_fee) }}</dd>
                    </div>
                    @endif
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="bp-spec-mono">{{ ui_copy('account_vat', 'account.vat') }}</dt>
                        <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                        <dd class="font-mono text-sm text-ink tabular-nums">{{ format_price($order->vat_amount) }}</dd>
                    </div>
                </dl>
                <div class="px-5 py-4 border-t-2 border-ink flex items-end justify-between gap-3">
                    <div>
                        <p class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink">{{ ui_copy('account_grand_total', 'account.grand_total') }}</p>
                        <p class="font-mono text-[9px] tracking-[0.2em] uppercase text-ink-muted mt-1">{{ settings('general.currency', 'EUR') }} · {{ ui_copy('account_incl_vat_short', 'account.incl_vat_short') }}</p>
                    </div>
                    <p class="font-mono text-3xl font-medium text-ink tabular-nums leading-none tracking-tight">
                        {{ format_price($order->grand_total) }}
                    </p>
                </div>
            </section>

            @if($order->customer_note)
                <section class="border border-ink bg-paper bp-shadow">
                    <header class="px-5 py-3 border-b border-ink bg-ivory-alt">
                        <span class="bp-spec text-amber-ink flex items-center gap-2">
                            <x-heroicon-o-pencil-square class="w-3.5 h-3.5" />
                            {{ ui_copy('account_customer_note_eyebrow', 'account.customer_note_eyebrow') }}
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
            <div class="border border-ink bg-paper bp-shadow-sm">
                <div class="px-4 py-3 border-b border-ink bg-ivory-alt">
                    <span class="bp-spec text-amber-ink flex items-center gap-2">
                        <x-heroicon-o-map-pin class="w-3.5 h-3.5" />
                        {{ ui_copy('account_ship_to_address_eyebrow', 'account.ship_to_address_eyebrow') }}
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
                        {{ $order->shipping_country_code ? localized_country_name($order->shipping_country_code) : '' }}
                    </p>
                </address>
            </div>

            {{-- Shipping method --}}
            @if($order->shipping_method_name_snapshot)
                <div class="border border-ink bg-paper bp-shadow-sm">
                    <div class="px-4 py-3 border-b border-ink bg-ivory-alt">
                        <span class="bp-spec text-amber-ink flex items-center gap-2">
                            <x-heroicon-o-truck class="w-3.5 h-3.5" />
                            {{ ui_copy('account_shipping_method_eyebrow', 'account.shipping_method_eyebrow') }}
                        </span>
                    </div>
                    <div class="p-4">
                        <p class="font-display text-sm font-bold text-ink tracking-[-0.01em]">
                            {{ $order->shipping_method_name_snapshot }}
                        </p>
                        @if($order->shipping_estimated_days_min || $order->shipping_estimated_days_max)
                            <p class="mt-1 font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted">
                                {{ ui_copy('account_est_delivery_days', 'account.est_delivery_days', ['min' => $order->shipping_estimated_days_min, 'max' => $order->shipping_estimated_days_max]) }}
                            </p>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Payment --}}
            <div class="border border-ink bg-paper bp-shadow-sm">
                <div class="px-4 py-3 border-b border-ink bg-ivory-alt">
                    <span class="bp-spec text-amber-ink flex items-center gap-2">
                        <x-heroicon-o-credit-card class="w-3.5 h-3.5" />
                        {{ ui_copy('account_payment_record_eyebrow', 'account.payment_record_eyebrow') }}
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
                                {{ $order->payment_method?->value === 'card' ? ui_copy('account_credit_debit_card', 'account.credit_debit_card') : ui_copy('account_bank_transfer', 'account.bank_transfer') }}
                            </p>
                            @if($order->payment_reference)
                                <p class="mt-1 font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted">
                                    {{ ui_copy('account_ref_label', 'account.ref_label', ['reference' => $order->payment_reference]) }}
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-t border-rule flex items-center justify-between">
                        <span class="bp-spec-mono">{{ ui_copy('account_status_label', 'account.status_label') }}</span>
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
                                {{ ui_copy('account_payment_status_'.$ps, 'account.payment_status_'.$ps) }}
                            </span>
                        </span>
                    </div>
                </div>
            </div>

            @if($order->is_b2b && ($order->company_name || $order->vat_number))
                <div class="border border-ink bg-paper bp-shadow-sm">
                    <div class="px-4 py-3 border-b border-ink bg-ivory-alt">
                        <span class="bp-spec text-amber-ink flex items-center gap-2">
                            <x-heroicon-o-briefcase class="w-3.5 h-3.5" />
                            {{ ui_copy('account_b2b_record_eyebrow', 'account.b2b_record_eyebrow') }}
                        </span>
                    </div>
                    <dl class="p-4 space-y-2">
                        @if($order->company_name)
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="bp-spec-mono">{{ ui_copy('account_company_label', 'account.company_label') }}</dt>
                                <dd class="font-mono text-xs font-bold text-ink truncate">{{ $order->company_name }}</dd>
                            </div>
                        @endif
                        @if($order->vat_number)
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="bp-spec-mono">{{ ui_copy('account_vat_label', 'account.vat_label') }}</dt>
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
