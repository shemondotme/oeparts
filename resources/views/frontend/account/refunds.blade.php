@extends('layouts.app')

@section('title', __('Refund Requests') . ' — ' . settings('general.site_name', 'OeParts'))

@section('meta_robots')<meta name="robots" content="noindex, nofollow">@endsection

@php $lang = app()->getLocale(); @endphp

@section('content')
<x-account.shell
    active="refunds"
    eyebrow="§ 03 · Refunds · Ledger"
    title="Refund requests"
    :subtitle="__('Track every refund submission — from initial request through admin decision and payout.')"
    docId="DOC · REFUND-INDEX · {{ now()->format('Y.m.d') }}"
    :breadcrumb="[['label' => 'Refunds']]"
>
    <x-slot name="actions">
        <a href="{{ route('frontend.account.orders', ['lang' => $lang]) }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 border border-ivory/20 text-ivory
                  font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                  hover:border-amber hover:text-amber transition-colors">
            <x-heroicon-s-arrow-long-left class="w-4 h-4" />
            {{ __('Back to orders') }}
        </a>
    </x-slot>

    @if($refunds->isEmpty())
        {{-- ── Empty state ─────────────────────────────────────────────── --}}
        <div class="border border-ink bg-paper p-16 text-center" style="box-shadow: 6px 6px 0 rgba(20,22,29,1);">
            <div class="inline-flex items-center justify-center w-16 h-16 border border-ink bg-ivory-alt mb-6">
                <x-heroicon-o-arrow-path class="w-7 h-7 text-ink-muted" />
            </div>
            <h3 class="font-display text-3xl font-extrabold text-ink tracking-[-0.02em]">
                {{ __('No refund requests') }}<span class="text-amber">.</span>
            </h3>
            <p class="mt-3 text-sm text-ink-muted max-w-md mx-auto leading-relaxed">
                {{ __('You have not submitted any refund requests yet. If you need help with a delivered order, open its detail page to start the process.') }}
            </p>
            <a href="{{ route('frontend.account.orders', ['lang' => $lang]) }}"
               class="mt-8 inline-flex items-center gap-2 px-5 py-3 bg-ink text-ivory border border-ink
                      font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                      hover:bg-amber hover:text-ink hover:border-amber transition-colors">
                <x-heroicon-s-shopping-bag class="w-4 h-4" />
                {{ __('View my orders') }}
            </a>
        </div>
    @else
        @php
            $statusConfig = [
                'pending'   => ['bar' => 'bg-amber',         'text' => 'text-amber-ink', 'label' => __('Pending')],
                'approved'  => ['bar' => 'bg-blue-600',      'text' => 'text-blue-700',  'label' => __('Approved')],
                'processed' => ['bar' => 'bg-emerald-600',   'text' => 'text-emerald-700', 'label' => __('Processed')],
                'rejected'  => ['bar' => 'bg-red-600',       'text' => 'text-red-700',   'label' => __('Rejected')],
            ];
            $totals = [
                'all'       => $refunds->total(),
                'pending'   => $refunds->where('status.value', 'pending')->count(),
                'processed' => $refunds->where('status.value', 'processed')->count(),
                'amount'    => (float) $refunds->sum('amount_requested'),
            ];
        @endphp

        {{-- ── Summary strip ──────────────────────────────────────────── --}}
        <div class="mb-6 border border-ink bg-paper grid grid-cols-2 sm:grid-cols-4 divide-x divide-rule"
             style="box-shadow: 4px 4px 0 rgba(20,22,29,1);">
            <div class="px-5 py-4">
                <p class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">{{ __('Total') }}</p>
                <p class="mt-1 font-display text-2xl font-extrabold text-ink tabular-nums tracking-[-0.02em]">
                    {{ str_pad((string) $totals['all'], 3, '0', STR_PAD_LEFT) }}
                </p>
            </div>
            <div class="px-5 py-4">
                <p class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">{{ __('Pending') }}</p>
                <p class="mt-1 font-display text-2xl font-extrabold text-amber-ink tabular-nums tracking-[-0.02em]">
                    {{ str_pad((string) $totals['pending'], 2, '0', STR_PAD_LEFT) }}
                </p>
            </div>
            <div class="px-5 py-4">
                <p class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">{{ __('Processed') }}</p>
                <p class="mt-1 font-display text-2xl font-extrabold text-emerald-700 tabular-nums tracking-[-0.02em]">
                    {{ str_pad((string) $totals['processed'], 2, '0', STR_PAD_LEFT) }}
                </p>
            </div>
            <div class="px-5 py-4">
                <p class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">{{ __('Value') }}</p>
                <p class="mt-1 font-display text-2xl font-extrabold text-ink tabular-nums tracking-[-0.02em]">
                    €{{ number_format($totals['amount'], 0) }}
                </p>
            </div>
        </div>

        {{-- ── Desktop table ──────────────────────────────────────────── --}}
        <div class="hidden md:block border border-ink bg-paper overflow-hidden"
             style="box-shadow: 6px 6px 0 rgba(20,22,29,1);">
            <div class="px-5 py-3 border-b border-ink bg-ivory-alt flex items-center justify-between">
                <span class="bp-spec text-amber-ink">§ {{ __('Refunds · Register') }}</span>
                <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">EUR · {{ __('amount requested') }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-ink-tint border-b border-ink">
                        <tr>
                            <th class="px-5 py-3 text-left font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted w-12">#</th>
                            <th class="px-5 py-3 text-left font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted">{{ __('Order') }}</th>
                            <th class="px-5 py-3 text-left font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted">{{ __('Submitted') }}</th>
                            <th class="px-5 py-3 text-right font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted">{{ __('Amount') }}</th>
                            <th class="px-5 py-3 text-left font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted">{{ __('Status') }}</th>
                            <th class="px-5 py-3 text-left font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted">{{ __('Admin note') }}</th>
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
                                    €{{ number_format((float) $refund->amount_requested, 2) }}
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
                    {{ $refunds->links() }}
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
                   class="block border border-ink bg-paper p-5 hover:bg-ivory-alt transition-colors"
                   style="box-shadow: 4px 4px 0 rgba(20,22,29,1);">
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
                        <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">{{ __('Requested') }}</span>
                        <span class="font-mono text-lg font-medium text-ink tabular-nums">
                            €{{ number_format((float) $refund->amount_requested, 2) }}
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
                <div class="pt-2">{{ $refunds->links() }}</div>
            @endif
        </div>
    @endif
</x-account.shell>
@endsection
