@extends('layouts.app')

@section('title', __('My Orders') . ' — ' . settings('general.site_name', 'OeParts'))

@section('meta_robots')<meta name="robots" content="noindex, nofollow">@endsection

@php $lang = app()->getLocale(); @endphp

@section('content')
<x-account.shell
    active="orders"
    eyebrow="§ 02 · Orders · Ledger"
    title="Orders"
    :subtitle="__('Track every procurement transaction — from submission through shipment and refund.')"
    docId="DOC · ORDER-INDEX · {{ now()->format('Y.m.d') }}"
    :breadcrumb="[['label' => 'Orders']]"
>
    <x-slot name="actions">
        <a href="{{ route('frontend.search.console', ['lang' => $lang]) }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 bg-amber text-ink border border-amber
                  font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                  hover:bg-paper hover:text-ink transition-colors">
            <x-heroicon-s-plus class="w-4 h-4" />
            New order
        </a>
    </x-slot>

    @if($orders->isEmpty())
        <div class="border border-ink bg-paper p-16 text-center" style="box-shadow: 6px 6px 0 rgba(20,22,29,1);">
            <div class="inline-flex items-center justify-center w-16 h-16 border border-ink bg-ivory-alt mb-6">
                <x-heroicon-o-shopping-bag class="w-7 h-7 text-ink-muted" />
            </div>
            <h3 class="font-display text-3xl font-extrabold text-ink tracking-[-0.02em]">
                No orders yet<span class="text-amber">.</span>
            </h3>
            <p class="mt-3 text-sm text-ink-muted max-w-md mx-auto leading-relaxed">
                You haven't placed any orders yet. Start searching for OEM parts and place your first B2B procurement order today.
            </p>
            <a href="{{ route('frontend.search.console', ['lang' => $lang]) }}" class="bp-btn-primary mt-8">
                <x-heroicon-s-magnifying-glass class="w-5 h-5" />
                Search OEM parts
            </a>
        </div>
    @else
        {{-- Summary strip --}}
        <div class="mb-6 border border-ink bg-paper grid grid-cols-3 divide-x divide-rule"
             style="box-shadow: 4px 4px 0 rgba(20,22,29,1);">
            <div class="px-5 py-4">
                <p class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">Total records</p>
                <p class="mt-1 font-display text-2xl font-extrabold text-ink tabular-nums tracking-[-0.02em]">
                    {{ str_pad((string) $orders->total(), 3, '0', STR_PAD_LEFT) }}
                </p>
            </div>
            <div class="px-5 py-4">
                <p class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">Page</p>
                <p class="mt-1 font-display text-2xl font-extrabold text-ink tabular-nums tracking-[-0.02em]">
                    {{ $orders->currentPage() }} / {{ max(1, $orders->lastPage()) }}
                </p>
            </div>
            <div class="px-5 py-4">
                <p class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">Showing</p>
                <p class="mt-1 font-display text-2xl font-extrabold text-ink tabular-nums tracking-[-0.02em]">
                    {{ $orders->count() }}
                </p>
            </div>
        </div>

        {{-- Orders table (desktop) --}}
        <div class="hidden md:block border border-ink bg-paper overflow-hidden"
             style="box-shadow: 6px 6px 0 rgba(20,22,29,1);">
            <div class="px-5 py-3 border-b border-ink bg-ivory-alt flex items-center justify-between">
                <span class="bp-spec text-amber-ink">§ Orders · List</span>
                <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">EUR · VAT included</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-ink-tint border-b border-ink">
                        <tr>
                            <th class="px-5 py-3 text-left font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted w-12">#</th>
                            <th class="px-5 py-3 text-left font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted">Order No.</th>
                            <th class="px-5 py-3 text-left font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted">Date</th>
                            <th class="px-5 py-3 text-center font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted">Items</th>
                            <th class="px-5 py-3 text-right font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted">Total</th>
                            <th class="px-5 py-3 text-left font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted">Status</th>
                            <th class="px-5 py-3 text-right font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-rule">
                        @foreach($orders as $idx => $order)
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
                                $rowNum = ($orders->firstItem() ?? 1) + $idx;
                            @endphp
                            <tr class="hover:bg-ivory-alt transition-colors">
                                <td class="px-5 py-4 font-mono text-[10px] tabular-nums tracking-[0.18em] uppercase text-ink-muted">
                                    {{ str_pad((string) $rowNum, 3, '0', STR_PAD_LEFT) }}
                                </td>
                                <td class="px-5 py-4">
                                    <a href="{{ route('frontend.account.order.detail', ['lang' => $lang, 'order' => $order]) }}"
                                       class="font-mono text-sm font-bold text-ink tabular-nums hover:text-amber-ink transition-colors">
                                        {{ $order->order_number }}
                                    </a>
                                </td>
                                <td class="px-5 py-4 font-mono text-xs text-ink-muted tabular-nums">
                                    {{ $order->created_at->format('Y-m-d') }}
                                </td>
                                <td class="px-5 py-4 text-center font-mono text-sm text-ink tabular-nums">
                                    {{ $order->items->count() }}
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

            @if($orders->hasPages())
                <div class="px-5 py-4 border-t border-ink bg-ivory-alt">
                    {{ $orders->links() }}
                </div>
            @endif
        </div>

        {{-- Orders cards (mobile) --}}
        <div class="md:hidden space-y-4">
            @foreach($orders as $order)
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
                <a href="{{ route('frontend.account.order.detail', ['lang' => $lang, 'order' => $order]) }}"
                   class="block border border-ink bg-paper p-5 hover:bg-ivory-alt transition-colors"
                   style="box-shadow: 4px 4px 0 rgba(20,22,29,1);">
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <div>
                            <p class="font-mono text-sm font-bold text-ink tabular-nums">{{ $order->order_number }}</p>
                            <p class="mt-1 font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted">
                                {{ $order->created_at->format('Y-m-d') }}
                            </p>
                        </div>
                        <span class="inline-flex items-center gap-2 shrink-0">
                            <span class="inline-block w-1.5 h-3 {{ $statusBar }}"></span>
                            <span class="font-mono text-[10px] font-bold tracking-[0.18em] uppercase text-ink">
                                {{ $order->status->label() }}
                            </span>
                        </span>
                    </div>
                    <div class="flex items-baseline justify-between gap-3 pt-3 border-t border-rule">
                        <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">
                            {{ $order->items->count() }} items
                        </span>
                        <span class="font-mono text-lg font-medium text-ink tabular-nums">
                            €{{ number_format((float) $order->grand_total, 2) }}
                        </span>
                    </div>
                </a>
            @endforeach

            @if($orders->hasPages())
                <div class="pt-2">{{ $orders->links() }}</div>
            @endif
        </div>
    @endif
</x-account.shell>
@endsection
