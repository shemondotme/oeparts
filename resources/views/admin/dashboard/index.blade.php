@extends('layouts.admin')

@section('title', 'Dashboard')

@php
/**
 * OrderStatus badge colours — keyed by the enum's string value.
 * Source of truth: App\Enums\OrderStatus
 */
$statusBadge = [
    'pending'          => 'bg-amber-100  text-amber-800',
    'paid'             => 'bg-blue-100   text-blue-800',
    'processing'       => 'bg-indigo-100 text-indigo-800',
    'shipped'          => 'bg-cyan-100   text-cyan-800',
    'delivered'        => 'bg-green-100  text-green-800',
    'cancelled'        => 'bg-red-100    text-red-800',
    'refund_requested' => 'bg-orange-100 text-orange-800',
    'refunded'         => 'bg-gray-100   text-gray-600',
];

/**
 * Natural column-spans per widget.
 * Controls visual width — user controls order and visibility only.
 */
$spanClass = [
    'health_strip'       => 'md:col-span-2 lg:col-span-4',
    'sales_chart'        => 'md:col-span-2 lg:col-span-2',
    'search_popularity'  => 'md:col-span-2 lg:col-span-2',
    'recent_orders'      => 'md:col-span-2 lg:col-span-2',
    'activity_log'       => 'md:col-span-2 lg:col-span-2',
];

/**
 * Left-border accent + icon bg per KPI card.
 */
$kpiAccent = [
    'total_orders'    => ['border' => 'border-l-blue-500',   'icon_bg' => 'bg-blue-50',   'icon_color' => 'text-blue-500'],
    'total_revenue'   => ['border' => 'border-l-green-500',  'icon_bg' => 'bg-green-50',  'icon_color' => 'text-green-500'],
    'total_customers' => ['border' => 'border-l-purple-500', 'icon_bg' => 'bg-purple-50', 'icon_color' => 'text-purple-500'],
    'total_products'  => ['border' => 'border-l-amber-500',  'icon_bg' => 'bg-amber-50',  'icon_color' => 'text-amber-500'],
];

$genericBorder = [
    'failed_jobs'          => 'border-l-red-400',
    'cron_status'          => 'border-l-slate-400',
    'newsletter_stats'     => 'border-l-blue-400',
    'ip_blocklist'         => 'border-l-red-400',
    'translation_progress' => 'border-l-violet-400',
    'admin_activity'       => 'border-l-indigo-400',
    'cart_abandonment'     => 'border-l-orange-400',
    'product_condition'    => 'border-l-teal-400',
    'customer_growth'      => 'border-l-green-400',
    'search_zero_results'  => 'border-l-amber-400',
    'checkout_dropoff'     => 'border-l-orange-500',
    'vat_compliance'       => 'border-l-green-500',
];
@endphp

@section('content')
<div class="px-6 py-8">

    {{-- ── Header ──────────────────────────────────────────────────────────────── --}}
    <div class="mb-6 flex flex-wrap justify-between items-start gap-4">
        <div>
            <h1 class="text-2xl font-display font-bold text-gray-900">Dashboard</h1>
            <p class="mt-0.5 text-sm text-gray-500">
                Welcome back, {{ auth('admin')->user()->name }}.
                Here's your store at a glance.
            </p>
        </div>
        <button id="btnEnterEdit"
                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg
                       text-sm font-medium text-gray-700 bg-white hover:bg-gray-50
                       focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-400
                       transition-colors">
            <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0
                         002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0
                         001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0
                         00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0
                         00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0
                         00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0
                         00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0
                         001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            Customize
        </button>
    </div>

    {{-- ── Edit-mode banner ─────────────────────────────────────────────────────── --}}
    <div id="editBanner" class="hidden mb-6 p-4 bg-amber-50 border border-amber-200 rounded-xl">
        <div class="flex flex-wrap items-center gap-3">
            <svg class="w-5 h-5 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="flex-1 text-sm text-amber-800">
                <strong>Customize Mode</strong> —
                Drag the <strong>≡ handle</strong> to reorder.
                Click the <strong>eye</strong> to hide or show a widget.
            </p>
            <button id="btnSave"
                    class="px-3 py-1.5 text-xs font-semibold text-white bg-navy rounded-lg
                           hover:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-offset-1
                           focus:ring-navy transition-colors">
                Save Changes
            </button>
            <button id="btnCancel"
                    class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border
                           border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none
                           focus:ring-2 focus:ring-offset-1 focus:ring-gray-300 transition-colors">
                Cancel
            </button>
        </div>
    </div>

    {{-- ── Widget grid — all 26 widgets, rendered in saved preference order ─────── --}}
    <div id="widgetsGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

        @foreach($preferences as $pref)
            @php
                $key      = $pref['id'];
                $widget   = $widgets[$key] ?? null;
                if (!$widget) continue;
                $isHidden = !($pref['visible'] ?? true);
                $span     = $spanClass[$key] ?? '';
            @endphp

            {{-- Widget card ---------------------------------------------------- --}}
            <div class="widget group {{ $span }} bg-white rounded-xl border-l-4 border border-gray-200
                        relative transition-all hover:shadow-md {{ $isHidden ? 'hidden' : '' }}
                        {{ isset($kpiAccent[$key]) ? $kpiAccent[$key]['border'] : ($genericBorder[$key] ?? 'border-l-gray-200') }}"
                 data-widget-id="{{ $key }}">

                {{-- Drag handle (top-left, hidden until edit mode) --}}
                <div class="btn-drag hidden absolute -top-2.5 -left-2.5 z-20 w-6 h-6 flex items-center
                            justify-center rounded-full bg-gray-800 text-white shadow
                            cursor-grab active:cursor-grabbing hover:bg-gray-900 select-none"
                     title="Drag to reorder">
                    <svg class="w-3 h-3 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                              d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </div>

                {{-- Visibility toggle (top-right, hidden until edit mode) --}}
                <button class="btn-visibility hidden absolute -top-2.5 -right-2.5 z-20 w-6 h-6
                               flex items-center justify-center rounded-full bg-gray-800 text-white
                               shadow hover:bg-gray-900 focus:outline-none select-none"
                        title="Toggle visibility">
                    <svg class="w-3 h-3 icon-eye-open {{ $isHidden ? 'hidden' : '' }}"
                         fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12
                                 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0
                                 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638
                                 0-8.573-3.007-9.963-7.178z"/>
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <svg class="w-3 h-3 icon-eye-slash {{ $isHidden ? '' : 'hidden' }}"
                         fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5
                                 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0
                                 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0
                                 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894
                                 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0
                                 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
                    </svg>
                </button>

                {{-- ═══════════════ Widget content ═══════════════ --}}

                @if($key === 'health_strip')
                {{-- ① Health Strip ──────────────────────────────────────────────── --}}
                <div class="p-4">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">
                        {{ $widget['title'] }}
                    </p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">
                        @foreach($widget['checks'] as $check)
                        @php
                            $dotColor = match($check['status']) {
                                'healthy' => 'bg-green-500',
                                'warning' => 'bg-amber-400',
                                default   => 'bg-red-500',
                            };
                            $textColor = match($check['status']) {
                                'healthy' => 'text-green-700',
                                'warning' => 'text-amber-700',
                                default   => 'text-red-700',
                            };
                            $bgColor = match($check['status']) {
                                'healthy' => 'bg-green-50  border-green-100',
                                'warning' => 'bg-amber-50  border-amber-100',
                                default   => 'bg-red-50    border-red-100',
                            };
                        @endphp
                        <div class="flex items-center gap-2 px-3 py-2 rounded-lg border {{ $bgColor }}">
                            <span class="w-2 h-2 rounded-full flex-shrink-0 {{ $dotColor }}"></span>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-gray-700 truncate">{{ $check['label'] }}</p>
                                <p class="text-xs {{ $textColor }}">{{ ucfirst($check['status']) }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                @elseif(isset($kpiAccent[$key]))
                {{-- ② KPI Cards (orders / revenue / customers / products) ─────────── --}}
                @php $accent = $kpiAccent[$key]; @endphp
                <div class="p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider truncate">
                                {{ $widget['title'] }}
                            </p>
                            <p class="mt-1.5 text-2xl font-bold text-gray-900 tabular-nums leading-none break-all">
                                {{ $widget['value'] }}
                            </p>
                            @if(isset($widget['change']))
                            <p class="mt-1.5 text-xs font-medium
                                      {{ $widget['change'] >= 0 ? 'text-green-600' : 'text-red-500' }}">
                                {{ $widget['change'] >= 0 ? '▲' : '▼' }}
                                {{ abs($widget['change']) }}% vs prev. 30d
                            </p>
                            @endif
                        </div>
                        <div class="p-2.5 rounded-xl {{ $accent['icon_bg'] }} flex-shrink-0">
                            <x-heroicon-o-{{ $widget['icon'] }} class="w-5 h-5 {{ $accent['icon_color'] }}" />
                        </div>
                    </div>
                </div>

                @elseif($key === 'sales_chart')
                {{-- ③ Sales Chart ────────────────────────────────────────────────── --}}
                <div class="p-5">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-sm font-semibold text-gray-800">{{ $widget['title'] }}</p>
                        <span class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">Last 30 days</span>
                    </div>
                    <div class="h-44 flex flex-col items-center justify-center gap-2
                                border-2 border-dashed border-gray-200 rounded-xl">
                        <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0
                                     01-1-1V4z"/>
                        </svg>
                        <p class="text-xs text-gray-400 font-medium">Chart — Sprint 15</p>
                    </div>
                </div>

                @elseif($key === 'search_popularity')
                {{-- ④ Search Popularity ──────────────────────────────────────────── --}}
                <div class="p-5">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-sm font-semibold text-gray-800">{{ $widget['title'] }}</p>
                        <span class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">Last 7 days</span>
                    </div>
                    @php $maxCount = $widget['data']->max('count') ?: 1; @endphp
                    @forelse($widget['data'] as $i => $item)
                    <div class="flex items-center gap-2 py-1.5 {{ !$loop->last ? 'border-b border-gray-50' : '' }}">
                        <span class="text-xs font-bold text-gray-300 w-4 text-right flex-shrink-0">
                            {{ $loop->index + 1 }}
                        </span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-1 mb-0.5">
                                <span class="text-xs font-mono text-gray-700 truncate"
                                      title="{{ $item->query }}">{{ $item->query }}</span>
                                <span class="text-xs font-bold text-gray-900 flex-shrink-0 tabular-nums">
                                    {{ number_format($item->count) }}
                                </span>
                            </div>
                            <div class="h-1 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-navy rounded-full"
                                     style="width: {{ round(($item->count / $maxCount) * 100) }}%"></div>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="flex flex-col items-center justify-center py-6 gap-2">
                        <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <p class="text-xs text-gray-400">No searches logged yet</p>
                    </div>
                    @endforelse
                </div>

                @elseif($key === 'system_alerts')
                {{-- ⑤ System Alerts ───────────────────────────────────────────────── --}}
                <div class="p-5">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-sm font-semibold text-gray-800">{{ $widget['title'] }}</p>
                        @if($widget['count'] > 0)
                        <span class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold
                                     text-white bg-red-500 rounded-full">
                            {{ $widget['count'] }}
                        </span>
                        @endif
                    </div>
                    @if($widget['count'] === 0)
                    <div class="flex items-center gap-2 p-3 bg-green-50 border border-green-100 rounded-lg">
                        <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor"
                             viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-xs font-medium text-green-700">All systems normal</p>
                    </div>
                    @else
                    <div class="space-y-2">
                        @foreach($widget['alerts'] as $alert)
                        @php
                            $alertStyle = match($alert['type']) {
                                'danger'  => 'bg-red-50    border-red-100    text-red-800',
                                'warning' => 'bg-amber-50  border-amber-100  text-amber-800',
                                default   => 'bg-blue-50   border-blue-100   text-blue-800',
                            };
                            $alertIcon = match($alert['type']) {
                                'danger'  => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
                                'warning' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.998-.833-2.732 0L4.342 16.5c-.77.833.192 2.5 1.732 2.5z',
                                default   => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                            };
                        @endphp
                        <div class="flex items-start gap-2 p-2.5 rounded-lg border {{ $alertStyle }}">
                            <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor"
                                 viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      stroke-width="2" d="{{ $alertIcon }}"/>
                            </svg>
                            <p class="text-xs leading-relaxed">{{ $alert['message'] }}</p>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>

                @elseif($key === 'recent_orders')
                {{-- ⑥ Recent Orders ───────────────────────────────────────────────── --}}
                <div class="p-5">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-sm font-semibold text-gray-800">{{ $widget['title'] }}</p>
                        <a href="{{ route('admin.orders.index') }}"
                           class="text-xs font-medium text-navy hover:underline">View all →</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b border-gray-100">
                                    <th class="pb-2 pr-3 text-left text-xs font-semibold text-gray-400
                                               uppercase tracking-wider">Order</th>
                                    <th class="pb-2 pr-3 text-left text-xs font-semibold text-gray-400
                                               uppercase tracking-wider">Customer</th>
                                    <th class="pb-2 pr-3 text-right text-xs font-semibold text-gray-400
                                               uppercase tracking-wider">Total</th>
                                    <th class="pb-2 text-left text-xs font-semibold text-gray-400
                                               uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($widget['items'] as $order)
                                @php $sv = $order->status->value; @endphp
                                <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50
                                           transition-colors">
                                    <td class="py-2.5 pr-3 font-mono text-xs font-semibold text-gray-900
                                               whitespace-nowrap">
                                        {{ $order->order_number }}
                                    </td>
                                    <td class="py-2.5 pr-3 text-xs text-gray-600 max-w-[100px] truncate"
                                        title="{{ $order->user->name ?? 'Guest' }}">
                                        {{ $order->user->name ?? 'Guest' }}
                                    </td>
                                    <td class="py-2.5 pr-3 text-xs font-semibold text-gray-900
                                               text-right tabular-nums whitespace-nowrap">
                                        €{{ number_format((float) $order->grand_total, 2) }}
                                    </td>
                                    <td class="py-2.5">
                                        <span class="inline-block px-1.5 py-0.5 rounded text-xs font-medium
                                                     whitespace-nowrap
                                                     {{ $statusBadge[$sv] ?? 'bg-gray-100 text-gray-600' }}">
                                            {{ $order->status->label() }}
                                        </span>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="py-8 text-center">
                                        <svg class="w-6 h-6 text-gray-300 mx-auto mb-1" fill="none"
                                             stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1
                                                  13H4L5 9z"/>
                                        </svg>
                                        <p class="text-xs text-gray-400">No orders yet</p>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @elseif($key === 'activity_log')
                {{-- ⑦ Activity Log ────────────────────────────────────────────────── --}}
                <div class="p-5">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-sm font-semibold text-gray-800">{{ $widget['title'] }}</p>
                        <span class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">
                            Last 10 actions
                        </span>
                    </div>
                    <div class="space-y-0 max-h-56 overflow-y-auto pr-1">
                        @forelse($widget['items'] as $log)
                        <div class="flex items-start gap-3 py-2
                                    {{ !$loop->last ? 'border-b border-gray-50' : '' }}">
                            <div class="w-6 h-6 rounded-full bg-navy/10 flex items-center justify-center
                                        flex-shrink-0 mt-0.5">
                                <span class="text-xs font-bold text-navy">
                                    {{ strtoupper(substr($log->admin->name ?? 'S', 0, 1)) }}
                                </span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs text-gray-700 leading-relaxed">
                                    <span class="font-semibold">{{ $log->admin->name ?? 'System' }}</span>
                                    <span class="text-gray-500 ml-1">{{ $log->action }}</span>
                                </p>
                                <p class="text-xs text-gray-400 mt-0.5 tabular-nums">
                                    {{ $log->created_at->diffForHumans() }}
                                </p>
                            </div>
                        </div>
                        @empty
                        <div class="flex flex-col items-center justify-center py-6 gap-2">
                            <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor"
                                 viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0
                                         00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0
                                         012-2h2a2 2 0 012 2"/>
                            </svg>
                            <p class="text-xs text-gray-400">No activity yet</p>
                        </div>
                        @endforelse
                    </div>
                </div>

                @elseif($key === 'top_searches')
                {{-- ⑧ Top Searches (all-time) ─────────────────────────────────────── --}}
                <div class="p-5">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-sm font-semibold text-gray-800">{{ $widget['title'] }}</p>
                        <span class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">All time</span>
                    </div>
                    @php $maxS = $widget['items']->max('count') ?: 1; @endphp
                    @forelse($widget['items'] as $search)
                    <div class="flex items-center gap-2 py-1.5 {{ !$loop->last ? 'border-b border-gray-50' : '' }}">
                        <span class="text-xs font-bold text-gray-300 w-4 text-right flex-shrink-0">
                            {{ $loop->index + 1 }}
                        </span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-1 mb-0.5">
                                <span class="text-xs font-mono text-gray-700 truncate"
                                      title="{{ $search->query }}">{{ $search->query }}</span>
                                <span class="text-xs font-bold text-gray-900 flex-shrink-0 tabular-nums">
                                    {{ number_format($search->count) }}
                                </span>
                            </div>
                            <div class="h-1 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-amber rounded-full"
                                     style="width: {{ round(($search->count / $maxS) * 100) }}%"></div>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="flex flex-col items-center justify-center py-6 gap-2">
                        <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <p class="text-xs text-gray-400">No searches logged</p>
                    </div>
                    @endforelse
                </div>

                @elseif($key === 'order_status')
                {{-- ⑨ Order Status Breakdown ──────────────────────────────────────── --}}
                <div class="p-5">
                    <p class="text-sm font-semibold text-gray-800 mb-3">{{ $widget['title'] }}</p>
                    @forelse($widget['data'] as $row)
                    @php
                        $sv2 = is_object($row->status) ? $row->status->value : $row->status;
                    @endphp
                    <div class="flex items-center justify-between py-1.5
                                {{ !$loop->last ? 'border-b border-gray-50' : '' }}">
                        <span class="inline-block px-2 py-0.5 rounded text-xs font-medium
                                     {{ $statusBadge[$sv2] ?? 'bg-gray-100 text-gray-600' }}">
                            {{ \App\Enums\OrderStatus::from($sv2)->label() }}
                        </span>
                        <span class="text-sm font-bold text-gray-900 tabular-nums">{{ $row->count }}</span>
                    </div>
                    @empty
                    <p class="text-xs text-gray-400 py-4 text-center">No orders yet</p>
                    @endforelse
                </div>

                @elseif($key === 'recent_inquiries')
                {{-- ⑩ Recent Inquiries ────────────────────────────────────────────── --}}
                <div class="p-5">
                    <p class="text-sm font-semibold text-gray-800 mb-3">{{ $widget['title'] }}</p>
                    @forelse($widget['items'] as $inquiry)
                    <div class="flex items-center justify-between py-2
                                {{ !$loop->last ? 'border-b border-gray-50' : '' }}">
                        <span class="font-mono text-xs font-semibold text-gray-900 truncate max-w-[60%]"
                              title="{{ $inquiry->oem_number ?? '' }}">
                            {{ $inquiry->oem_number ?? 'N/A' }}
                        </span>
                        <span class="text-xs text-gray-400 tabular-nums flex-shrink-0 ml-2">
                            {{ $inquiry->created_at->diffForHumans() }}
                        </span>
                    </div>
                    @empty
                    <p class="text-xs text-gray-400 py-4 text-center">No inquiries yet</p>
                    @endforelse
                </div>

                @elseif($key === 'recent_contacts')
                {{-- ⑪ Recent Contacts ─────────────────────────────────────────────── --}}
                <div class="p-5">
                    <p class="text-sm font-semibold text-gray-800 mb-3">{{ $widget['title'] }}</p>
                    @forelse($widget['items'] as $contact)
                    <div class="flex items-center justify-between py-2
                                {{ !$loop->last ? 'border-b border-gray-50' : '' }}">
                        <span class="text-xs font-medium text-gray-800 truncate max-w-[65%]"
                              title="{{ $contact->name ?? '' }}">
                            {{ $contact->name ?? 'Unknown' }}
                        </span>
                        <span class="text-xs text-gray-400 flex-shrink-0 ml-2">
                            {{ $contact->created_at->diffForHumans() }}
                        </span>
                    </div>
                    @empty
                    <p class="text-xs text-gray-400 py-4 text-center">No contacts yet</p>
                    @endforelse
                </div>

                @else
                {{-- ⑫ Generic stat card ───────────────────────────────────────────── --}}
                {{-- Covers: failed_jobs, cron_status, newsletter_stats, ip_blocklist,  --}}
                {{-- translation_progress, admin_activity, cart_abandonment,            --}}
                {{-- product_condition, customer_growth, search_zero_results,           --}}
                {{-- checkout_dropoff, vat_compliance                                   --}}
                @php
                    $genericIcon = [
                        'failed_jobs'          => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.998-.833-2.732 0L4.342 16.5c-.77.833.192 2.5 1.732 2.5z',
                        'cron_status'          => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
                        'newsletter_stats'     => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
                        'ip_blocklist'         => 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636',
                        'translation_progress' => 'M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129',
                        'admin_activity'       => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
                        'cart_abandonment'     => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
                        'product_condition'    => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
                        'customer_growth'      => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',
                        'search_zero_results'  => 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7',
                        'checkout_dropoff'     => 'M13 17h8m0 0V9m0 8l-8-8-4 4-6-6',
                        'vat_compliance'       => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
                    ][$key] ?? 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z';

                    $statusColor = match($widget['status'] ?? '') {
                        'success' => ['num' => 'text-green-600', 'badge' => 'bg-green-100 text-green-700', 'label' => 'Healthy', 'icon_bg' => 'bg-green-50',  'icon_color' => 'text-green-500'],
                        'warning' => ['num' => 'text-amber-600', 'badge' => 'bg-amber-100 text-amber-700', 'label' => 'Warning', 'icon_bg' => 'bg-amber-50',  'icon_color' => 'text-amber-500'],
                        'danger'  => ['num' => 'text-red-600',   'badge' => 'bg-red-100   text-red-700',   'label' => 'Issue',   'icon_bg' => 'bg-red-50',    'icon_color' => 'text-red-500'],
                        default   => ['num' => 'text-gray-900',  'badge' => 'bg-gray-100  text-gray-600',  'label' => '',        'icon_bg' => 'bg-gray-100',  'icon_color' => 'text-gray-400'],
                    };

                    // Failed jobs: red if > 0
                    $numColor = $statusColor['num'];
                    if ($key === 'failed_jobs' && ($widget['value'] ?? 0) > 0) {
                        $numColor = 'text-red-600';
                    }
                    if ($key === 'search_zero_results' && ($widget['value'] ?? 0) > 0) {
                        $numColor = 'text-amber-600';
                    }
                @endphp
                <div class="p-5">
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider leading-tight">
                            {{ $widget['title'] }}
                        </p>
                        <div class="w-7 h-7 rounded-lg {{ $statusColor['icon_bg'] }} flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 {{ $statusColor['icon_color'] }}" fill="none" stroke="currentColor"
                                 viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      stroke-width="1.75" d="{{ $genericIcon }}"/>
                            </svg>
                        </div>
                    </div>
                    <p class="text-2xl font-bold tabular-nums leading-none break-all {{ $numColor }}">
                        {{ $widget['value'] ?? '—' }}
                    </p>
                    @if(isset($widget['subtitle']))
                    <p class="text-xs text-gray-400 mt-1">{{ $widget['subtitle'] }}</p>
                    @endif
                    @if(isset($widget['status']) && $statusColor['label'])
                    <span class="inline-flex items-center mt-2 px-2 py-0.5 rounded-full
                                 text-xs font-medium {{ $statusColor['badge'] }}">
                        {{ $statusColor['label'] }}
                    </span>
                    @endif
                </div>
                @endif

            </div>{{-- /.widget --}}
        @endforeach

    </div>{{-- /#widgetsGrid --}}

</div>{{-- /page --}}
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    const grid       = document.getElementById('widgetsGrid');
    const editBanner = document.getElementById('editBanner');
    const btnEnter   = document.getElementById('btnEnterEdit');
    const btnSave    = document.getElementById('btnSave');
    const btnCancel  = document.getElementById('btnCancel');
    const csrf       = document.querySelector('meta[name="csrf-token"]').content;
    const prefsUrl   = '{{ route("admin.dashboard.preferences.update") }}';
    const basePrefs  = @json($preferences);

    let editMode  = false;
    let sortable  = null;

    // ── Enter edit mode ──────────────────────────────────────────────────────────
    btnEnter.addEventListener('click', function () {
        editMode = true;
        editBanner.classList.remove('hidden');
        this.disabled = true;

        grid.querySelectorAll('.widget').forEach(function (w) {
            // Un-hide hidden widgets — show them dimmed so the admin can re-enable them
            if (w.classList.contains('hidden')) {
                w.classList.remove('hidden');
                w.classList.add('opacity-40');
                w.dataset.markedHidden = '1';
                setEyeIcon(w, false);
            }

            // Reveal edit controls
            var btnDrag = w.querySelector('.btn-drag');
            var btnVis  = w.querySelector('.btn-visibility');
            if (btnDrag) btnDrag.classList.remove('hidden');
            if (btnVis)  btnVis.classList.remove('hidden');
        });

        // Initialise SortableJS — replaces all manual drag event handling
        sortable = Sortable.create(grid, {
            handle:    '.btn-drag',       // drag only via the ≡ handle
            animation: 150,               // smooth slide animation (ms)
            ghostClass: 'opacity-30',     // dragged item ghost style
            chosenClass: 'ring-2 ring-inset ring-amber-400',
            dragClass:   'shadow-xl',
            filter:    '.btn-visibility', // clicking eye never triggers drag
        });
    });

    // ── Save ─────────────────────────────────────────────────────────────────────
    btnSave.addEventListener('click', async function () {
        btnSave.disabled    = true;
        btnSave.textContent = 'Saving…';

        try {
            var resp = await fetch(prefsUrl, {
                method:  'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ preferences: collectPreferences() }),
            });
            var json = await resp.json();
            if (!json.success) throw new Error(json.message || 'Server error');
            location.reload();  // reload reflects saved order + visibility
        } catch (err) {
            console.error('Dashboard save error:', err);
            showToast('Save failed: ' + err.message, 'error');
            btnSave.disabled    = false;
            btnSave.textContent = 'Save Changes';
        }
    });

    // ── Cancel ───────────────────────────────────────────────────────────────────
    btnCancel.addEventListener('click', function () {
        location.reload();
    });

    // ── Visibility toggle (event delegation on grid) ─────────────────────────────
    grid.addEventListener('click', function (e) {
        if (!editMode) return;
        var btn = e.target.closest('.btn-visibility');
        if (!btn) return;

        var w = btn.closest('.widget');
        if (w.dataset.markedHidden) {
            delete w.dataset.markedHidden;
            w.classList.remove('opacity-40');
            setEyeIcon(w, true);
        } else {
            w.dataset.markedHidden = '1';
            w.classList.add('opacity-40');
            setEyeIcon(w, false);
        }
    });

    function setEyeIcon(w, visible) {
        var open  = w.querySelector('.icon-eye-open');
        var slash = w.querySelector('.icon-eye-slash');
        if (open)  open.classList.toggle('hidden', !visible);
        if (slash) slash.classList.toggle('hidden',  visible);
    }

    // ── Collect preferences from current DOM state ────────────────────────────────
    function collectPreferences() {
        var map = {};
        basePrefs.forEach(function (p) { map[p.id] = Object.assign({}, p); });

        var ordered = [];
        // SortableJS updates the DOM order in real time, so walking the DOM
        // gives the correct final order after any drag operations.
        grid.querySelectorAll(':scope > .widget').forEach(function (el) {
            var id   = el.dataset.widgetId;
            var pref = map[id];
            if (!pref) return;
            pref.visible = !el.dataset.markedHidden;
            ordered.push(pref);
            delete map[id];
        });

        // Safety: append any prefs that were somehow not in the DOM
        Object.values(map).forEach(function (p) { ordered.push(p); });

        return ordered;
    }

    // ── Toast ────────────────────────────────────────────────────────────────────
    function showToast(msg, type) {
        var t = document.createElement('div');
        t.className = 'fixed top-4 right-4 z-50 px-4 py-3 rounded-xl shadow-lg text-sm font-medium '
            + (type === 'success'
                ? 'bg-green-100 text-green-800 border border-green-200'
                : 'bg-red-100   text-red-800   border border-red-200');
        t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(function () { t.remove(); }, 4000);
    }

})();
</script>
@endpush
