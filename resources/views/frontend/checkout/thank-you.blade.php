@extends('layouts.app')

@section('title', ui_copy('checkout_order_confirmed_title', 'checkout.order_confirmed_title') . ' - ' . settings('general.site_name', 'OeParts'))

@section('meta_robots')<meta name="robots" content="noindex, nofollow">@endsection

@section('content')
@php
    $orderData = [
        'order_number' => $order->order_number,
        'email' => $order->email,
        'created_at' => $order->created_at,
        'grand_total' => $order->grand_total,
        'payment_status' => $order->payment_status->value,
    ];
    $isPaid = $orderData['payment_status'] === 'paid';
@endphp

<div class="relative min-h-screen bg-ivory text-ink">
    <div class="fixed inset-0 bg-grid-ivory-fine bg-grid-md opacity-40 pointer-events-none" aria-hidden="true"></div>

    {{-- ── Dark doc header ── --}}
    <div class="relative bg-ink text-ivory border-b border-rule-dark overflow-hidden">
        <div class="absolute inset-0 bg-grid-navy bg-grid-lg opacity-60 pointer-events-none" aria-hidden="true"></div>
        <div class="relative max-w-[1200px] mx-auto px-4 sm:px-6 lg:px-10 pt-10 pb-8">

            <div class="flex flex-wrap items-center justify-between gap-4 pb-4 mb-6 border-b border-white/15">
                <nav class="flex items-center gap-2 font-mono text-[10px] tracking-[0.22em] uppercase text-ivory/60">
                    <a href="{{ url('/'.app()->getLocale().'/') }}" class="hover:text-amber transition-colors">{{ ui_copy('checkout_breadcrumb_home', 'checkout.breadcrumb_home') }}</a>
                    <span class="text-ivory/30">/</span>
                    <span class="text-ivory">{{ ui_copy('checkout_order_confirmed_breadcrumb', 'checkout.order_confirmed_breadcrumb') }}</span>
                </nav>
            </div>

            <div class="grid grid-cols-12 gap-6 items-center">
                <div class="col-span-12 md:col-span-8">
                    <div class="flex items-center gap-4 mb-4">
                        <span class="w-10 h-[3px] bg-amber inline-block"></span>
                        <span class="font-mono text-[10px] tracking-[0.28em] uppercase text-amber">{{ ui_copy('checkout_status_order_placed', 'checkout.status_order_placed') }}</span>
                    </div>
                    <h1 class="font-display font-extrabold text-ivory leading-[0.95] tracking-[-0.03em] text-4xl md:text-5xl lg:text-6xl">
                        {{ ui_copy('checkout_thank_you_heading', 'checkout.thank_you_heading') }}<span class="text-amber">.</span>
                    </h1>
                    <p class="mt-5 max-w-xl text-ivory/80 text-base md:text-lg leading-relaxed">
                        {{ ui_copy('checkout_thank_you_note', 'checkout.thank_you_note') }}
                    </p>
                </div>

                {{-- Confirmation stamp --}}
                <div class="col-span-12 md:col-span-4 flex md:justify-end">
                    <div class="relative border-2 border-amber bg-amber/10 px-6 py-5 inline-flex items-center gap-4">
                        <span class="absolute -top-1 -left-1 w-3 h-3 border-l-2 border-t-2 border-amber"></span>
                        <span class="absolute -top-1 -right-1 w-3 h-3 border-r-2 border-t-2 border-amber"></span>
                        <span class="absolute -bottom-1 -left-1 w-3 h-3 border-l-2 border-b-2 border-amber"></span>
                        <span class="absolute -bottom-1 -right-1 w-3 h-3 border-r-2 border-b-2 border-amber"></span>

                        <div class="w-10 h-10 border-2 border-amber bg-ink flex items-center justify-center shrink-0">
                            @if($isPaid)
                                <x-heroicon-s-check class="w-5 h-5 text-amber" />
                            @else
                                <x-heroicon-s-clock class="w-5 h-5 text-amber" />
                            @endif
                        </div>
                        <div>
                            <p class="font-mono text-[10px] font-bold tracking-[0.28em] uppercase text-amber">{{ ui_copy('checkout_filed_label', 'checkout.filed_label') }}</p>
                            <p class="font-display text-xl font-extrabold text-ivory tracking-[-0.02em]">
                                {{ $isPaid ? ui_copy('checkout_confirmed_label', 'checkout.confirmed_label') : ui_copy('checkout_payment_status_pending', 'checkout.payment_status_pending') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="relative max-w-[1200px] mx-auto px-4 sm:px-6 lg:px-10 py-10 space-y-6">

        {{-- Order details --}}
        <section class="border border-ink bg-paper relative">
            <header class="flex items-center justify-between px-5 py-3 border-b border-ink bg-ivory-alt">
                <span class="bp-spec text-amber-ink flex items-center gap-2">
                    <x-heroicon-o-document-text class="w-3.5 h-3.5" />
                    {{ ui_copy('checkout_order_details_heading', 'checkout.order_details_heading') }}
                </span>
                <span class="bp-spec-mono">{{ ui_copy('checkout_reference_label', 'checkout.reference_label') }}</span>
            </header>

            <div class="relative p-6 sm:p-8">
                <span class="absolute -top-px left-2 w-3 h-3 border-l-2 border-t-2 border-amber" aria-hidden="true"></span>
                <span class="absolute -top-px right-2 w-3 h-3 border-r-2 border-t-2 border-amber" aria-hidden="true"></span>

                {{-- Order number --}}
                <div class="pb-6 mb-6 border-b border-rule">
                    <p class="bp-spec text-ink-muted mb-2">{{ ui_copy('checkout_order_number_label', 'checkout.order_number_label') }}</p>
                    <p class="font-mono font-medium text-3xl md:text-4xl text-ink tabular-nums tracking-tight">
                        {{ $orderData['order_number'] }}
                    </p>
                </div>

                {{-- Meta grid --}}
                <div class="grid sm:grid-cols-3 gap-6 pb-6 mb-6 border-b border-rule">
                    <div>
                        <p class="bp-spec text-ink-muted mb-2 flex items-center gap-1.5">
                            <x-heroicon-s-envelope class="w-3 h-3 text-amber-ink" />
                            {{ ui_copy('checkout_email_label', 'checkout.email_label') }}
                        </p>
                        <p class="font-mono text-sm font-bold text-ink truncate">{{ $orderData['email'] }}</p>
                    </div>
                    <div>
                        <p class="bp-spec text-ink-muted mb-2 flex items-center gap-1.5">
                            <x-heroicon-s-calendar class="w-3 h-3 text-amber-ink" />
                            {{ ui_copy('checkout_date_label', 'checkout.date_label') }}
                        </p>
                        <p class="font-mono text-sm font-bold tabular-nums text-ink">{{ $orderData['created_at']->clone()->locale(app()->getLocale())->translatedFormat('M j, Y') }}</p>
                    </div>
                    <div>
                        <p class="bp-spec text-ink-muted mb-2 flex items-center gap-1.5">
                            <x-heroicon-s-credit-card class="w-3 h-3 text-amber-ink" />
                            {{ ui_copy('checkout_payment_label', 'checkout.payment_label') }}
                        </p>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 border font-mono text-[10px] font-bold uppercase tracking-[0.22em]
                                     {{ $isPaid ? 'border-amber bg-paper text-amber-ink' : 'border-ink bg-ivory-alt text-ink' }}">
                            @if($isPaid)
                                <x-heroicon-s-check class="w-3 h-3" />
                            @else
                                <x-heroicon-s-clock class="w-3 h-3" />
                            @endif
                            {{ ui_copy('checkout_payment_status_'.$orderData['payment_status'], 'checkout.payment_status_'.$orderData['payment_status']) }}
                        </span>
                    </div>
                </div>

                {{-- Total --}}
                <div class="flex items-end justify-between gap-3">
                    <div>
                        <p class="bp-spec text-ink mb-1">{{ ui_copy('checkout_order_total_currency_label', 'checkout.order_total_currency_label', ['currency' => settings('general.currency', 'EUR')]) }}</p>
                        <p class="font-mono text-[10px] tracking-[0.2em] uppercase text-ink-muted">{{ ui_copy('checkout_including_all_taxes', 'checkout.including_all_taxes') }}</p>
                    </div>
                    <p class="font-mono text-4xl md:text-5xl font-medium text-ink tabular-nums leading-none tracking-tight">
                        {{ format_price($orderData['grand_total']) }}
                    </p>
                </div>

                <span class="absolute -bottom-px left-2 w-3 h-3 border-l-2 border-b-2 border-amber" aria-hidden="true"></span>
                <span class="absolute -bottom-px right-2 w-3 h-3 border-r-2 border-b-2 border-amber" aria-hidden="true"></span>
            </div>
        </section>

        {{-- What's next ledger --}}
        <section class="border border-ink bg-paper">
            <header class="flex items-center justify-between px-5 py-3 border-b border-ink bg-ivory-alt">
                <span class="bp-spec text-amber-ink flex items-center gap-2">
                    <x-heroicon-o-map class="w-3.5 h-3.5" />
                    {{ ui_copy('checkout_whats_next_heading', 'checkout.whats_next_heading') }}
                </span>
                <span class="bp-spec-mono">{{ ui_copy('checkout_three_steps', 'checkout.three_steps') }}</span>
            </header>
            <ul class="divide-y divide-rule">
                @foreach([
                    ['num' => '01', 'title' => ui_copy('checkout_next_step_1_title', 'checkout.next_step_1_title'), 'desc' => ui_copy('checkout_next_step_1_desc', 'checkout.next_step_1_desc'), 'icon' => 'envelope'],
                    ['num' => '02', 'title' => ui_copy('checkout_next_step_2_title', 'checkout.next_step_2_title'), 'desc' => ui_copy('checkout_next_step_2_desc', 'checkout.next_step_2_desc'), 'icon' => 'cog'],
                    ['num' => '03', 'title' => ui_copy('checkout_next_step_3_title', 'checkout.next_step_3_title'), 'desc' => ui_copy('checkout_next_step_3_desc', 'checkout.next_step_3_desc'), 'icon' => 'truck'],
                ] as $step)
                <li class="flex items-start gap-5 p-5">
                    <span class="font-mono text-2xl font-medium text-ink-muted tabular-nums tracking-tight w-14 shrink-0">{{ $step['num'] }}</span>
                    <div class="w-10 h-10 border border-ink bg-ivory-alt flex items-center justify-center shrink-0">
                        @if($step['icon'] === 'envelope')
                            <x-heroicon-o-envelope class="w-5 h-5 text-ink" />
                        @elseif($step['icon'] === 'cog')
                            <x-heroicon-o-cog-6-tooth class="w-5 h-5 text-ink" />
                        @else
                            <x-heroicon-o-truck class="w-5 h-5 text-ink" />
                        @endif
                    </div>
                    <div class="flex-1 pt-1">
                        <p class="font-display text-base font-bold text-ink tracking-[-0.01em]">{{ $step['title'] }}</p>
                        <p class="mt-1 text-sm text-body leading-relaxed">{{ $step['desc'] }}</p>
                        <span class="inline-block mt-2 w-6 h-[2px] bg-amber"></span>
                    </div>
                </li>
                @endforeach
            </ul>
        </section>

        {{-- Action buttons --}}
        <div class="grid sm:grid-cols-2 gap-4">
            <a href="{{ route('frontend.account.orders', ['lang' => app()->getLocale()]) }}" class="bp-btn-primary justify-center py-4">
                <x-heroicon-o-list-bullet class="w-5 h-5" />
                {{ ui_copy('checkout_view_all_orders', 'checkout.view_all_orders') }}
            </a>
            <a href="{{ route('frontend.search.console', ['lang' => app()->getLocale()]) }}" class="bp-btn-outline justify-center py-4">
                <x-heroicon-o-magnifying-glass class="w-5 h-5" />
                {{ ui_copy('checkout_continue_shopping', 'checkout.continue_shopping') }}
            </a>
        </div>

        {{-- Need more parts CTA --}}
        <section class="relative bg-ink text-ivory border border-ink overflow-hidden">
            <div class="absolute inset-0 bg-grid-navy bg-grid-md opacity-60 pointer-events-none" aria-hidden="true"></div>
            <div class="absolute top-0 left-0 right-0 h-1 flex" aria-hidden="true">
                @for ($i = 0; $i < 40; $i++)
                    <span class="flex-1 h-full {{ $i % 2 === 0 ? 'bg-amber' : 'bg-transparent' }}"></span>
                @endfor
            </div>

            <div class="relative p-8 md:p-10 grid grid-cols-1 md:grid-cols-12 gap-6 items-center">
                <div class="md:col-span-8">
                    <p class="font-mono text-[10px] tracking-[0.28em] uppercase text-amber mb-3">{{ ui_copy('checkout_continue_shop_eyebrow', 'checkout.continue_shop_eyebrow') }}</p>
                    <h3 class="font-display text-2xl md:text-3xl font-extrabold text-ivory tracking-[-0.02em] leading-tight">
                        {{ ui_copy('checkout_need_more_parts_heading', 'checkout.need_more_parts_heading') }}<span class="text-amber">?</span>
                    </h3>
                    <p class="mt-3 text-ivory/80 text-sm md:text-base">
                        {{ ui_copy('checkout_need_more_parts_note', 'checkout.need_more_parts_note') }}
                    </p>
                </div>
                <div class="md:col-span-4 flex md:justify-end">
                    <a href="{{ route('frontend.search.console', ['lang' => app()->getLocale()]) }}"
                       class="bp-btn-amber">
                        <x-heroicon-o-magnifying-glass class="w-5 h-5" />
                        {{ ui_copy('checkout_search_oem_parts', 'checkout.search_oem_parts') }}
                    </a>
                </div>
            </div>
        </section>

        {{-- Support --}}
        <div class="text-center pt-6 border-t border-rule">
            <p class="bp-spec text-ink-muted mb-3">{{ ui_copy('checkout_need_help_short', 'checkout.need_help_short') }}</p>
            <a href="{{ route('frontend.contact.show', ['lang' => app()->getLocale()]) }}"
               class="inline-flex items-center gap-2 font-mono text-xs font-bold uppercase tracking-[0.22em] text-ink hover:text-amber-ink transition-colors">
                <x-heroicon-o-chat-bubble-left-right class="w-4 h-4" />
                {{ ui_copy('checkout_contact_support', 'checkout.contact_support') }}
                <x-heroicon-s-arrow-long-right class="w-4 h-4" />
            </a>
        </div>
    </div>
</div>
@endsection
