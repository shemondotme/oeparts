@extends('layouts.app')

@section('title', ui_copy('account_refunds_title', 'account.refunds_title') . ' — ' . settings('general.site_name', 'OeParts'))

@section('meta_robots')<meta name="robots" content="noindex, nofollow">@endsection

@php $lang = app()->getLocale(); @endphp

@section('content')
<x-account.shell
    active="refunds"
    eyebrow="{{ ui_copy('account_refunds_eyebrow', 'account.refunds_eyebrow') }}"
    title="{{ ui_copy('account_refund_requests', 'account.refund_requests') }}"
    :subtitle="ui_copy('account_refunds_subtitle', 'account.refunds_subtitle')"
    :breadcrumb="[['label' => ui_copy('account_nav_refunds', 'account.nav_refunds')]]"
>
    <x-slot name="actions">
        <a href="{{ route('frontend.account.orders', ['lang' => $lang]) }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 border border-ivory/20 text-ivory
                  font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                  hover:border-amber hover:text-amber transition-colors">
            <x-heroicon-s-arrow-long-left class="w-4 h-4" />
            {{ ui_copy('account_back_to_orders', 'account.back_to_orders') }}
        </a>
    </x-slot>

    @if($refunds->isEmpty())
        {{-- ── Empty state ─────────────────────────────────────────────── --}}
        <div class="border border-ink bg-paper p-16 text-center bp-shadow">
            <div class="inline-flex items-center justify-center w-16 h-16 border border-ink bg-ivory-alt mb-6">
                <x-heroicon-o-arrow-path class="w-7 h-7 text-ink-muted" />
            </div>
            <h3 class="font-display text-3xl font-extrabold text-ink tracking-[-0.02em]">
                {{ ui_copy('account_no_refund_requests', 'account.no_refund_requests') }}<span class="text-amber">.</span>
            </h3>
            <p class="mt-3 text-sm text-ink-muted max-w-md mx-auto leading-relaxed">
                {{ ui_copy('account_no_refund_requests_note', 'account.no_refund_requests_note') }}
            </p>
            <a href="{{ route('frontend.account.orders', ['lang' => $lang]) }}"
               class="mt-8 inline-flex items-center gap-2 px-5 py-3 bg-ink text-ivory border border-ink
                      font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                      hover:bg-amber hover:text-ink hover:border-amber transition-colors">
                <x-heroicon-s-shopping-bag class="w-4 h-4" />
                {{ ui_copy('account_view_my_orders', 'account.view_my_orders') }}
            </a>
        </div>
    @else
        @php
            $statusConfig = [
                'pending'   => ['bar' => 'bg-amber',         'text' => 'text-amber-ink', 'label' => ui_copy('account_refund_status_pending', 'account.refund_status_pending')],
                'approved'  => ['bar' => 'bg-blue-600',      'text' => 'text-blue-700',  'label' => ui_copy('account_refund_status_approved', 'account.refund_status_approved')],
                'processed' => ['bar' => 'bg-emerald-600',   'text' => 'text-emerald-700', 'label' => ui_copy('account_refund_status_processed', 'account.refund_status_processed')],
                'rejected'  => ['bar' => 'bg-red-600',       'text' => 'text-red-700',   'label' => ui_copy('account_refund_status_rejected', 'account.refund_status_rejected')],
            ];
        @endphp

        {{-- ── Summary strip ──────────────────────────────────────────── --}}
        <div class="mb-6 border border-ink bg-paper grid grid-cols-2 sm:grid-cols-4 divide-x divide-rule bp-shadow-sm">
            <div class="px-5 py-4">
                <p class="bp-spec-mono">{{ ui_copy('account_total', 'account.total') }}</p>
                <p class="mt-1 font-display text-2xl font-extrabold text-ink tabular-nums tracking-[-0.02em]">
                    {{ str_pad((string) $totals['all'], 3, '0', STR_PAD_LEFT) }}
                </p>
            </div>
            <div class="px-5 py-4">
                <p class="bp-spec-mono">{{ ui_copy('account_pending', 'account.pending') }}</p>
                <p class="mt-1 font-display text-2xl font-extrabold text-amber-ink tabular-nums tracking-[-0.02em]">
                    {{ str_pad((string) $totals['pending'], 2, '0', STR_PAD_LEFT) }}
                </p>
            </div>
            <div class="px-5 py-4">
                <p class="bp-spec-mono">{{ ui_copy('account_processed', 'account.processed') }}</p>
                <p class="mt-1 font-display text-2xl font-extrabold text-emerald-700 tabular-nums tracking-[-0.02em]">
                    {{ str_pad((string) $totals['processed'], 2, '0', STR_PAD_LEFT) }}
                </p>
            </div>
            <div class="px-5 py-4">
                <p class="bp-spec-mono">{{ ui_copy('account_value', 'account.value') }}</p>
                <p class="mt-1 font-display text-2xl font-extrabold text-ink tabular-nums tracking-[-0.02em]">
                    {{ format_price($totals['amount']) }}
                </p>
            </div>
        </div>

        {{-- ── Desktop table ──────────────────────────────────────────── --}}
        <div class="hidden md:block border border-ink bg-paper overflow-hidden bp-shadow">
            <div class="px-5 py-3 border-b border-ink bg-ivory-alt flex items-center justify-between">
                <span class="bp-spec text-amber-ink">{{ ui_copy('account_refunds_register', 'account.refunds_register') }}</span>
                <span class="bp-spec-mono">{{ settings('general.currency', 'EUR') }} · {{ ui_copy('account_amount_requested', 'account.amount_requested') }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-ivory-alt border-b border-ink">
                        <tr>
                            <th class="px-5 py-3 text-left font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted w-12">{{ ui_copy('account_th_hash', 'account.th_hash') }}</th>
                            <th class="px-5 py-3 text-left font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted">{{ ui_copy('account_th_order_short', 'account.th_order_short') }}</th>
                            <th class="px-5 py-3 text-left font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted">{{ ui_copy('account_th_submitted', 'account.th_submitted') }}</th>
                            <th class="px-5 py-3 text-right font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted">{{ ui_copy('account_th_amount', 'account.th_amount') }}</th>
                            <th class="px-5 py-3 text-left font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted">{{ ui_copy('account_th_status', 'account.th_status') }}</th>
                            <th class="px-5 py-3 text-left font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted">{{ ui_copy('account_th_admin_note', 'account.th_admin_note') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-rule">
                        @foreach($refunds as $idx => $refund)
                            @php
                                $cfg = $statusConfig[$refund->status->value] ?? ['bar' => 'bg-ink-muted', 'text' => 'text-ink', 'label' => ucfirst($refund->status->value)];
                                $rowNum = ($refunds->firstItem() ?? 1) + $idx;
                            @endphp
                            <tr class="hover:bg-ivory-alt transition-colors">
                                <td class="px-5 py-4 font-mono text-[10px] tabular-nums tracking-[0.18em] uppercase text-ink-muted">
                                    {{ str_pad((string) $rowNum, 3, '0', STR_PAD_LEFT) }}
                                </td>
                                <td class="px-5 py-4">
                                    <a href="{{ route('frontend.account.order.detail', ['lang' => $lang, 'order' => $refund->order]) }}"
                                       class="font-mono text-sm font-bold text-ink tabular-nums hover:text-amber-ink transition-colors">
                                        #{{ $refund->order->order_number }}
                                    </a>
                                </td>
                                <td class="px-5 py-4 font-mono text-xs text-ink-muted tabular-nums">
                                    {{ $refund->created_at->format('Y-m-d') }}
                                </td>
                                <td class="px-5 py-4 text-right font-mono text-sm font-bold text-ink tabular-nums">
                                    {{ format_price($refund->amount_requested) }}
                                </td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex items-center gap-2">
                                        <span class="inline-block w-1.5 h-3 {{ $cfg['bar'] }}"></span>
                                        <span class="font-mono text-[10px] font-bold tracking-[0.18em] uppercase text-ink">
                                            {{ $cfg['label'] }}
                                        </span>
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-sm text-body max-w-sm truncate">
                                    {{ $refund->admin_note ?: '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($refunds->hasPages())
                <div class="px-5 py-4 border-t border-ink bg-ivory-alt">
                    {{ $refunds->links('components.ui.pagination') }}
                </div>
            @endif
        </div>

        {{-- ── Mobile cards ──────────────────────────────────────────── --}}
        <div class="md:hidden space-y-4">
            @foreach($refunds as $refund)
                @php
                    $cfg = $statusConfig[$refund->status->value] ?? ['bar' => 'bg-ink-muted', 'text' => 'text-ink', 'label' => ucfirst($refund->status->value)];
                @endphp
                <a href="{{ route('frontend.account.order.detail', ['lang' => $lang, 'order' => $refund->order]) }}"
                   class="block border border-ink bg-paper p-5 hover:bg-ivory-alt transition-colors bp-shadow-sm">
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <div>
                            <p class="font-mono text-sm font-bold text-ink tabular-nums">#{{ $refund->order->order_number }}</p>
                            <p class="mt-1 font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted">
                                {{ $refund->created_at->format('Y-m-d') }}
                            </p>
                        </div>
                        <span class="inline-flex items-center gap-2 shrink-0">
                            <span class="inline-block w-1.5 h-3 {{ $cfg['bar'] }}"></span>
                            <span class="font-mono text-[10px] font-bold tracking-[0.18em] uppercase text-ink">
                                {{ $cfg['label'] }}
                            </span>
                        </span>
                    </div>
                    <div class="flex items-baseline justify-between gap-3 pt-3 border-t border-rule">
                        <span class="bp-spec-mono">{{ ui_copy('account_requested', 'account.requested') }}</span>
                        <span class="font-mono text-lg font-medium text-ink tabular-nums">
                            {{ format_price($refund->amount_requested) }}
                        </span>
                    </div>
                    @if($refund->admin_note)
                        <p class="mt-3 pt-3 border-t border-rule text-xs text-body leading-relaxed">
                            {{ Str::limit($refund->admin_note, 160) }}
                        </p>
                    @endif
                </a>
            @endforeach

            @if($refunds->hasPages())
                <div class="pt-2">{{ $refunds->links('components.ui.pagination') }}</div>
            @endif
        </div>
    @endif
</x-account.shell>
@endsection
