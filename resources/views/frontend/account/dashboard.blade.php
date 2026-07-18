@extends('layouts.app')

@section('title', ui_copy('account_dashboard_title', 'account.dashboard_title') . ' — ' . settings('general.site_name', 'OeParts'))

@section('meta_robots')<meta name="robots" content="noindex, nofollow">@endsection

@php
    $lang = app()->getLocale();
    $totalOrders    = $user->orders()->count();
    // Excludes Cancelled/Refunded — a cancelled or refunded order isn't money
    // the customer actually spent, so counting it here overstates the total.
    $totalSpent     = (string) $user->orders()->whereNotIn('status', [
        \App\Enums\OrderStatus::Cancelled,
        \App\Enums\OrderStatus::Refunded,
    ])->sum('grand_total');
    $pendingOrders  = $user->orders()->whereIn('status', [
        \App\Enums\OrderStatus::Pending,
        \App\Enums\OrderStatus::Paid,
        \App\Enums\OrderStatus::Processing,
    ])->count();
    $savedAddresses = $user->addresses()->count();
    $memberSince    = format_date($user->created_at);
@endphp

@section('content')
<x-account.shell
    active="dashboard"
    eyebrow="{{ ui_copy('account_dashboard_eyebrow', 'account.dashboard_eyebrow') }}"
    title="{{ ui_copy('account_welcome_back', 'account.welcome_back', ['name' => $user->first_name ?: $user->name]) }}"
    :subtitle="ui_copy('account_dashboard_subtitle', 'account.dashboard_subtitle')"
    :breadcrumb="[['label' => ui_copy('account_nav_dashboard', 'account.nav_dashboard')]]"
>
    <x-slot name="actions">
        <a href="{{ route('frontend.search.console', ['lang' => $lang]) }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 bg-amber text-ink border border-amber
                  font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                  hover:bg-paper hover:text-ink transition-colors">
            <x-heroicon-s-magnifying-glass class="w-4 h-4" />
            {{ ui_copy('account_new_search', 'account.new_search') }}
        </a>
        <a href="{{ route('frontend.cart.index', ['lang' => $lang]) }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 border border-ivory/20 text-ivory
                  font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                  hover:border-amber hover:text-amber transition-colors">
            <x-heroicon-s-shopping-cart class="w-4 h-4" />
            {{ ui_copy('account_view_cart', 'account.view_cart') }}
        </a>
    </x-slot>

    {{-- ── KPI Cards ─────────────────────────────────────────────────── --}}
    <section class="mb-8">
        <div class="flex items-center justify-between mb-4">
            <span class="bp-spec text-amber-ink">{{ ui_copy('account_key_metrics_eyebrow', 'account.key_metrics_eyebrow') }}</span>
            <span class="bp-spec-mono">
                {{ ui_copy('account_member_since', 'account.member_since', ['date' => $memberSince]) }}
            </span>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Total Orders --}}
            <div class="border border-ink bg-paper p-5 relative overflow-hidden bp-shadow-sm">
                <span class="absolute top-0 left-0 right-0 h-[3px] bg-amber"></span>
                <p class="bp-spec-mono">{{ ui_copy('account_total_orders', 'account.total_orders') }}</p>
                <p class="mt-2 font-display text-4xl font-extrabold text-ink tabular-nums tracking-[-0.03em] leading-none">
                    {{ str_pad((string) $totalOrders, 2, '0', STR_PAD_LEFT) }}
                </p>
                <div class="mt-3 flex items-center gap-1.5">
                    <x-heroicon-s-shopping-bag class="w-3 h-3 text-amber-ink" />
                    <span class="font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted">{{ ui_copy('account_all_time', 'account.all_time') }}</span>
                </div>
            </div>

            {{-- Total Spent --}}
            <div class="border border-ink bg-paper p-5 relative overflow-hidden bp-shadow-sm">
                <span class="absolute top-0 left-0 right-0 h-[3px] bg-emerald-600"></span>
                <p class="bp-spec-mono">{{ ui_copy('account_total_spent', 'account.total_spent') }}</p>
                <p class="mt-2 font-display text-4xl font-extrabold text-ink tabular-nums tracking-[-0.03em] leading-none">
                    {{ format_price($totalSpent) }}
                </p>
                <div class="mt-3 flex items-center gap-1.5">
                    <x-heroicon-s-currency-euro class="w-3 h-3 text-emerald-700" />
                    <span class="font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted">{{ ui_copy('account_lifetime_currency', 'account.lifetime_currency', ['currency' => settings('general.currency', 'EUR')]) }}</span>
                </div>
            </div>

            {{-- In Flight --}}
            <div class="border border-ink bg-paper p-5 relative overflow-hidden bp-shadow-sm">
                <span class="absolute top-0 left-0 right-0 h-[3px] bg-ink"></span>
                <p class="bp-spec-mono">{{ ui_copy('account_in_flight', 'account.in_flight') }}</p>
                <p class="mt-2 font-display text-4xl font-extrabold text-ink tabular-nums tracking-[-0.03em] leading-none">
                    {{ str_pad((string) $pendingOrders, 2, '0', STR_PAD_LEFT) }}
                </p>
                <div class="mt-3 flex items-center gap-1.5">
                    <x-heroicon-s-truck class="w-3 h-3 text-ink" />
                    <span class="font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted">{{ ui_copy('account_being_processed', 'account.being_processed') }}</span>
                </div>
            </div>

            {{-- Saved Addresses --}}
            <div class="border border-ink bg-paper p-5 relative overflow-hidden bp-shadow-sm">
                <span class="absolute top-0 left-0 right-0 h-[3px] bg-blue-600"></span>
                <p class="bp-spec-mono">{{ ui_copy('account_addresses_on_file', 'account.addresses_on_file') }}</p>
                <p class="mt-2 font-display text-4xl font-extrabold text-ink tabular-nums tracking-[-0.03em] leading-none">
                    {{ str_pad((string) $savedAddresses, 2, '0', STR_PAD_LEFT) }}
                </p>
                <div class="mt-3 flex items-center gap-1.5">
                    <x-heroicon-s-map-pin class="w-3 h-3 text-blue-700" />
                    <span class="font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted">{{ ui_copy('account_ship_to_records', 'account.ship_to_records') }}</span>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Account Information ──────────────────────────────────────── --}}
    <section class="mb-8 border border-ink bg-paper bp-shadow">
        <header class="flex items-center justify-between px-5 py-3 border-b border-ink bg-ivory-alt">
            <span class="bp-spec text-amber-ink flex items-center gap-2">
                <x-heroicon-o-identification class="w-3.5 h-3.5" />
                {{ ui_copy('account_account_profile_eyebrow', 'account.account_profile_eyebrow') }}
            </span>
            <a href="{{ route('frontend.account.settings', ['lang' => $lang]) }}"
               class="inline-flex items-center gap-1.5 font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink
                      border-b border-amber hover:text-amber-ink transition-colors pb-0.5">
                {{ ui_copy('account_edit_profile', 'account.edit_profile') }}
                <x-heroicon-s-arrow-long-right class="w-3 h-3" />
            </a>
        </header>

        <dl class="grid sm:grid-cols-2 lg:grid-cols-4 divide-y sm:divide-y-0 sm:divide-x divide-rule">
            <div class="px-5 py-4">
                <dt class="bp-spec-mono">{{ ui_copy('account_full_name', 'account.full_name') }}</dt>
                <dd class="mt-1.5 font-display text-base font-bold text-ink tracking-[-0.01em]">
                    {{ $user->name }}
                </dd>
            </div>
            <div class="px-5 py-4">
                <dt class="bp-spec-mono">{{ ui_copy('account_email', 'account.email') }}</dt>
                <dd class="mt-1.5 font-mono text-sm font-bold text-ink truncate">
                    {{ $user->email }}
                </dd>
            </div>
            <div class="px-5 py-4">
                <dt class="bp-spec-mono">{{ ui_copy('account_phone', 'account.phone') }}</dt>
                <dd class="mt-1.5 font-mono text-sm font-bold text-ink">
                    {{ $user->phone ?: '—' }}
                </dd>
            </div>
            <div class="px-5 py-4">
                <dt class="bp-spec-mono">{{ ui_copy('account_member_since_label', 'account.member_since_label') }}</dt>
                <dd class="mt-1.5 font-mono text-sm font-bold text-ink">
                    {{ $memberSince }}
                </dd>
            </div>
        </dl>
    </section>

    {{-- ── Recent Orders ────────────────────────────────────────────── --}}
    <section class="mb-8">
        <header class="flex items-center justify-between mb-4">
            <span class="bp-spec text-amber-ink">{{ ui_copy('account_recent_orders_eyebrow', 'account.recent_orders_eyebrow') }}</span>
            <a href="{{ route('frontend.account.orders', ['lang' => $lang]) }}"
               class="inline-flex items-center gap-1.5 font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink
                      border-b border-amber hover:text-amber-ink transition-colors pb-0.5">
                {{ ui_copy('account_view_all_orders', 'account.view_all_orders') }}
                <x-heroicon-s-arrow-long-right class="w-3 h-3" />
            </a>
        </header>

        @if($recentOrders->isNotEmpty())
            <div class="border border-ink bg-paper overflow-hidden bp-shadow">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-ivory-alt border-b border-ink">
                            <tr>
                                <th class="px-5 py-3 text-left font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted">{{ ui_copy('account_th_hash', 'account.th_hash') }}</th>
                                <th class="px-5 py-3 text-left font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted">{{ ui_copy('account_th_order', 'account.th_order') }}</th>
                                <th class="px-5 py-3 text-left font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted">{{ ui_copy('account_th_date', 'account.th_date') }}</th>
                                <th class="px-5 py-3 text-right font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted">{{ ui_copy('account_th_total', 'account.th_total') }}</th>
                                <th class="px-5 py-3 text-left font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted">{{ ui_copy('account_th_status', 'account.th_status') }}</th>
                                <th class="px-5 py-3 text-right font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted">{{ ui_copy('account_th_action', 'account.th_action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-rule">
                            @foreach($recentOrders->take(5) as $idx => $order)
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
                                <tr class="hover:bg-ivory-alt transition-colors">
                                    <td class="px-5 py-4 font-mono text-[10px] tabular-nums tracking-[0.18em] uppercase text-ink-muted">
                                        {{ str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT) }}
                                    </td>
                                    <td class="px-5 py-4">
                                        <a href="{{ route('frontend.account.order.detail', ['lang' => $lang, 'order' => $order]) }}"
                                           class="font-mono text-sm font-bold text-ink tabular-nums hover:text-amber-ink transition-colors">
                                            {{ $order->order_number }}
                                        </a>
                                        <p class="mt-0.5 font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted">
                                            {{ $order->items_count }} {{ ui_trans_choice('account_item_word', 'account.item_word', $order->items_count) }}
                                        </p>
                                    </td>
                                    <td class="px-5 py-4 font-mono text-xs text-ink-muted tabular-nums">
                                        {{ $order->created_at->format('Y-m-d') }}
                                    </td>
                                    <td class="px-5 py-4 text-right font-mono text-sm font-bold text-ink tabular-nums">
                                        {{ format_price($order->grand_total) }}
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex items-center gap-2">
                                            <span class="inline-block w-1.5 h-3 {{ $statusBar }}"></span>
                                            <span class="font-mono text-[10px] font-bold tracking-[0.18em] uppercase text-ink">
                                                {{ ui_copy('account_order_status_'.$order->status->value, 'account.order_status_'.$order->status->value) }}
                                            </span>
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <a href="{{ route('frontend.account.order.detail', ['lang' => $lang, 'order' => $order]) }}"
                                           class="inline-flex items-center gap-1.5 font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink
                                                  border-b border-amber hover:text-amber-ink transition-colors pb-0.5">
                                            {{ ui_copy('account_view', 'account.view') }}
                                            <x-heroicon-s-arrow-long-right class="w-3 h-3" />
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            {{-- Empty state --}}
            <div class="border border-ink bg-paper p-10 text-center bp-shadow">
                <div class="inline-flex items-center justify-center w-14 h-14 border border-ink bg-ivory-alt mb-5">
                    <x-heroicon-o-shopping-bag class="w-6 h-6 text-ink-muted" />
                </div>
                <h3 class="font-display text-2xl font-extrabold text-ink tracking-[-0.02em]">
                    {{ ui_copy('account_no_orders_yet', 'account.no_orders_yet') }}<span class="text-amber">.</span>
                </h3>
                <p class="mt-2 text-sm text-ink-muted max-w-md mx-auto">
                    {{ ui_copy('account_no_orders_note', 'account.no_orders_note') }}
                </p>
                <a href="{{ route('frontend.search.console', ['lang' => $lang]) }}" class="bp-btn-primary mt-6">
                    <x-heroicon-s-magnifying-glass class="w-5 h-5" />
                    {{ ui_copy('account_search_oem_parts', 'account.search_oem_parts') }}
                </a>
            </div>
        @endif
    </section>

    {{-- ── Quick actions ────────────────────────────────────────────── --}}
    <section>
        <header class="flex items-center justify-between mb-4">
            <span class="bp-spec text-amber-ink">{{ ui_copy('account_quick_actions_eyebrow', 'account.quick_actions_eyebrow') }}</span>
        </header>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @php
                $quickActions = [
                    [
                        'label' => ui_copy('account_manage_addresses', 'account.manage_addresses'),
                        'desc'  => ui_copy('account_manage_addresses_desc', 'account.manage_addresses_desc'),
                        'icon'  => 'heroicon-o-map-pin',
                        'href'  => route('frontend.account.addresses', ['lang' => $lang]),
                    ],
                    [
                        'label' => ui_copy('account_track_refunds', 'account.track_refunds'),
                        'desc'  => ui_copy('account_track_refunds_desc', 'account.track_refunds_desc'),
                        'icon'  => 'heroicon-o-arrow-path',
                        'href'  => route('frontend.account.refunds', ['lang' => $lang]),
                    ],
                    [
                        'label' => ui_copy('account_account_settings', 'account.account_settings'),
                        'desc'  => ui_copy('account_account_settings_desc', 'account.account_settings_desc'),
                        'icon'  => 'heroicon-o-cog-6-tooth',
                        'href'  => route('frontend.account.settings', ['lang' => $lang]),
                    ],
                ];
            @endphp
            @foreach($quickActions as $qa)
                <a href="{{ $qa['href'] }}"
                   class="group border border-ink bg-paper p-5 flex items-start gap-4 transition-all
                          hover:bg-ink hover:text-ivory bp-shadow-sm">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-2">
                            <p class="font-display text-base font-bold text-ink group-hover:text-ivory tracking-[-0.01em]">
                                {{ $qa['label'] }}
                            </p>
                            <x-dynamic-component :component="$qa['icon']"
                                class="w-5 h-5 text-ink-muted group-hover:text-amber transition-colors shrink-0" />
                        </div>
                        <p class="mt-1 text-xs text-ink-muted group-hover:text-ivory/70 leading-relaxed">
                            {{ $qa['desc'] }}
                        </p>
                    </div>
                </a>
            @endforeach
        </div>
    </section>
</x-account.shell>
@endsection
