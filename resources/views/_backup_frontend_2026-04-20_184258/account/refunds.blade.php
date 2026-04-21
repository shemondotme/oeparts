@extends('layouts.app')

@section('title', __('My Refund Requests'))

@section('content')
<div class="min-h-screen bg-bg-page">

{{-- ── Breadcrumb ───────────────────────────────────────────────────────── --}}
<div class="bg-gray-50 border-b border-gray-100 py-3 px-4">
    <div class="max-w-5xl mx-auto">
        <ol class="flex flex-wrap items-center gap-1.5 text-xs text-muted">
            <li><a href="/{{ app()->getLocale() }}/" class="hover:text-amber-text transition-colors font-medium">Home</a></li>
            <li class="text-gray-300"><x-heroicon-o-chevron-right class="w-3 h-3 inline" /></li>
            <li><a href="/{{ app()->getLocale() }}/account/dashboard" class="hover:text-amber-text transition-colors font-medium">Account</a></li>
            <li class="text-gray-300"><x-heroicon-o-chevron-right class="w-3 h-3 inline" /></li>
            <li class="text-navy font-semibold">Refund Requests</li>
        </ol>
    </div>
</div>

<div class="max-w-5xl mx-auto px-4 py-8">

    {{-- ── Page Header ────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-8">
        <div>
            <h1 class="font-display text-2xl md:text-3xl font-extrabold text-navy leading-tight">
                Refund Requests
            </h1>
            <p class="text-muted mt-2">Track the status of your refund requests</p>
        </div>
        <a href="{{ route('frontend.account.orders', ['lang' => app()->getLocale()]) }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 border-2 border-gray-200 rounded-xl text-navy font-bold text-sm
                  hover:border-amber hover:text-amber hover:bg-amber/5 transition-all duration-200">
            <x-heroicon-o-arrow-left class="w-4 h-4" />
            Back to Orders
        </a>
    </div>

    @if($refunds->isEmpty())
        {{-- ── Empty State ─────────────────────────────────────────────── --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-12 text-center">
            <div class="relative inline-flex items-center justify-center w-20 h-20 mb-6">
                <div class="absolute inset-0 bg-gray-100 rounded-full"></div>
                <div class="relative w-20 h-20 rounded-full bg-gradient-to-br from-gray-50 to-gray-100 flex items-center justify-center">
                    <x-heroicon-o-document-text class="w-10 h-10 text-gray-400" />
                </div>
            </div>

            <h3 class="font-display text-xl font-bold text-navy mb-2">No refund requests</h3>
            <p class="text-muted mb-6 max-w-md mx-auto">
                You have not submitted any refund requests yet. If you need help with an order, visit your orders page.
            </p>
            <a href="{{ route('frontend.account.orders', ['lang' => app()->getLocale()]) }}"
               class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-amber to-orange-500 text-navy font-bold rounded-xl
                      shadow-lg shadow-amber/30 hover:shadow-amber/50 hover:from-amber/90 hover:to-orange-400 transition-all duration-200">
                <x-heroicon-o-shopping-cart class="w-4 h-4" />
                View My Orders
            </a>
        </div>
    @else
        {{-- ── Refunds Table ───────────────────────────────────────────── --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="px-6 py-4 text-left text-sm font-bold text-navy">Order</th>
                            <th class="px-6 py-4 text-left text-sm font-bold text-navy">Submitted</th>
                            <th class="px-6 py-4 text-left text-sm font-bold text-navy">Amount</th>
                            <th class="px-6 py-4 text-left text-sm font-bold text-navy">Status</th>
                            <th class="px-6 py-4 text-left text-sm font-bold text-navy">Admin Note</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($refunds as $refund)
                        <tr class="odd:bg-gray-50 hover:bg-gray-100 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <a href="{{ route('frontend.account.order.detail', ['lang' => app()->getLocale(), 'order' => $refund->order]) }}"
                                   class="font-mono font-bold text-amber-text hover:text-amber transition-colors">
                                    #{{ $refund->order->order_number }}
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-muted">
                                {{ $refund->created_at->format('M j, Y') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap font-bold text-navy">
                                €{{ number_format($refund->amount_requested, 2) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $statusConfig = [
                                        'pending'   => ['bg' => 'bg-amber/15', 'text' => 'text-amber-text', 'label' => 'Pending'],
                                        'approved'  => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'label' => 'Approved'],
                                        'processed' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'label' => 'Processed'],
                                        'rejected'  => ['bg' => 'bg-red-100', 'text' => 'text-red-700', 'label' => 'Rejected'],
                                    ];
                                    $config = $statusConfig[$refund->status->value] ?? ['bg' => 'bg-gray-100', 'text' => 'text-gray-700', 'label' => ucfirst($refund->status->value)];
                                @endphp
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold {{ $config['bg'] }} {{ $config['text'] }}">
                                    @if($refund->status->value === 'pending')
                                        <x-heroicon-o-clock class="w-3 h-3" />
                                    @elseif($refund->status->value === 'approved')
                                        <x-heroicon-s-check-circle class="w-3 h-3" />
                                    @elseif($refund->status->value === 'processed')
                                        <x-heroicon-s-check-badge class="w-3 h-3" />
                                    @elseif($refund->status->value === 'rejected')
                                        <x-heroicon-o-x-mark class="w-3 h-3" />
                                    @endif
                                    {{ $config['label'] }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-muted">
                                {{ $refund->admin_note ?? '—' }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Pagination --}}
        <div class="mt-6">
            {{ $refunds->links() }}
        </div>
    @endif
</div>
</div>
@endsection
