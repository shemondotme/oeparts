@extends('layouts.app')

@section('title', 'Order Confirmed - ' . settings('general.site_name', 'OEMHub'))

@section('meta_robots')<meta name="robots" content="noindex, nofollow">@endsection

@section('content')
@php
    $order = session('checkout.order');
    if (!$order) {
        $orderData = [
            'order_number' => 'ORD-202604-000123',
            'email' => 'customer@example.com',
            'created_at' => now(),
            'grand_total' => 134.30,
            'payment_status' => 'pending',
        ];
    } else {
        $orderData = [
            'order_number' => $order->order_number,
            'email' => $order->email,
            'created_at' => $order->created_at,
            'grand_total' => $order->grand_total,
            'payment_status' => $order->payment_status,
        ];
    }
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
                    <a href="{{ url('/'.app()->getLocale().'/') }}" class="hover:text-amber transition-colors">Home</a>
                    <span class="text-ivory/30">/</span>
                    <span class="text-ivory">Order confirmed</span>
                </nav>
                <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-ivory/60">
                    DOC · CONFIRMATION · 001
                </span>
            </div>

            <div class="grid grid-cols-12 gap-6 items-center">
                <div class="col-span-12 md:col-span-8">
                    <div class="flex items-center gap-4 mb-4">
                        <span class="w-10 h-[3px] bg-amber inline-block"></span>
                        <span class="font-mono text-[10px] tracking-[0.28em] uppercase text-amber">§ Status · Order placed</span>
                    </div>
                    <h1 class="font-display font-extrabold text-ivory leading-[0.95] tracking-[-0.03em] text-4xl md:text-5xl lg:text-6xl">
                        Thank you<span class="text-amber">.</span>
                    </h1>
                    <p class="mt-5 max-w-xl text-ivory/80 text-base md:text-lg leading-relaxed">
                        Your order has been filed. We are preparing it for shipment and will email a full receipt shortly.
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
                            <x-heroicon-s-check class="w-5 h-5 text-amber" />
                        </div>
                        <div>
                            <p class="font-mono text-[10px] font-bold tracking-[0.28em] uppercase text-amber">§ Filed</p>
                            <p class="font-display text-xl font-extrabold text-ivory tracking-[-0.02em]">Confirmed</p>
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
                    § Order details
                </span>
                <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">Reference</span>
            </header>

            <div class="relative p-6 sm:p-8">
                <span class="absolute -top-px left-2 w-3 h-3 border-l-2 border-t-2 border-amber" aria-hidden="true"></span>
                <span class="absolute -top-px right-2 w-3 h-3 border-r-2 border-t-2 border-amber" aria-hidden="true"></span>

                {{-- Order number --}}
                <div class="pb-6 mb-6 border-b border-rule">
                    <p class="bp-spec text-ink-muted mb-2">§ Order number</p>
                    <p class="font-mono font-medium text-3xl md:text-4xl text-ink tabular-nums tracking-tight">
                        {{ $orderData['order_number'] }}
                    </p>
                </div>

                {{-- Meta grid --}}
                <div class="grid sm:grid-cols-3 gap-6 pb-6 mb-6 border-b border-rule">
                    <div>
                        <p class="bp-spec text-ink-muted mb-2 flex items-center gap-1.5">
                            <x-heroicon-s-envelope class="w-3 h-3 text-amber-ink" />
                            § Email
                        </p>
                        <p class="font-mono text-sm font-bold text-ink truncate">{{ $orderData['email'] }}</p>
                    </div>
                    <div>
                        <p class="bp-spec text-ink-muted mb-2 flex items-center gap-1.5">
                            <x-heroicon-s-calendar class="w-3 h-3 text-amber-ink" />
                            § Date
                        </p>
                        <p class="font-mono text-sm font-bold tabular-nums text-ink">{{ $orderData['created_at']->format('M j, Y') }}</p>
                    </div>
                    <div>
                        <p class="bp-spec text-ink-muted mb-2 flex items-center gap-1.5">
                            <x-heroicon-s-credit-card class="w-3 h-3 text-amber-ink" />
                            § Payment
                        </p>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 border font-mono text-[10px] font-bold uppercase tracking-[0.22em]
                                     {{ $isPaid ? 'border-amber bg-paper text-amber-ink' : 'border-ink bg-ivory-alt text-ink' }}">
                            @if($isPaid)
                                <x-heroicon-s-check class="w-3 h-3" />
                            @else
                                <x-heroicon-s-clock class="w-3 h-3" />
                            @endif
                            {{ ucfirst($orderData['payment_status']) }}
                        </span>
                    </div>
                </div>

                {{-- Total --}}
                <div class="flex items-end justify-between gap-3">
                    <div>
                        <p class="bp-spec text-ink mb-1">§ Order total · EUR</p>
                        <p class="font-mono text-[10px] tracking-[0.2em] uppercase text-ink-muted">Including all taxes</p>
                    </div>
                    <p class="font-mono text-4xl md:text-5xl font-medium text-ink tabular-nums leading-none tracking-tight">
                        €{{ number_format($orderData['grand_total'], 2) }}
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
                    § What happens next · Protocol
                </span>
                <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">03 steps</span>
            </header>
            <ul class="divide-y divide-rule">
                @foreach([
                    ['num' => '01', 'title' => 'Confirmation email', 'desc' => 'Check your inbox for detailed order confirmation and receipt.', 'icon' => 'envelope'],
                    ['num' => '02', 'title' => 'Processing', 'desc' => 'We prepare and pack your order within 24 hours.', 'icon' => 'cog'],
                    ['num' => '03', 'title' => 'Tracking updates', 'desc' => 'Receive real-time tracking updates by email once shipped.', 'icon' => 'truck'],
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
                View all orders
            </a>
            <a href="{{ route('frontend.search.console', ['lang' => app()->getLocale()]) }}" class="bp-btn-outline justify-center py-4">
                <x-heroicon-o-magnifying-glass class="w-5 h-5" />
                Continue shopping
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
                    <p class="font-mono text-[10px] tracking-[0.28em] uppercase text-amber mb-3">§ Continue · Shop</p>
                    <h3 class="font-display text-2xl md:text-3xl font-extrabold text-ivory tracking-[-0.02em] leading-tight">
                        Need more parts<span class="text-amber">?</span>
                    </h3>
                    <p class="mt-3 text-ivory/80 text-sm md:text-base">
                        Continue shopping and discover more genuine OEM parts for your vehicle.
                    </p>
                </div>
                <div class="md:col-span-4 flex md:justify-end">
                    <a href="{{ route('frontend.search.console', ['lang' => app()->getLocale()]) }}"
                       class="bp-btn-amber">
                        <x-heroicon-o-magnifying-glass class="w-5 h-5" />
                        Search OEM parts
                    </a>
                </div>
            </div>
        </section>

        {{-- Support --}}
        <div class="text-center pt-6 border-t border-rule">
            <p class="bp-spec text-ink-muted mb-3">§ Need help</p>
            <a href="{{ route('frontend.contact.show', ['lang' => app()->getLocale()]) }}"
               class="inline-flex items-center gap-2 font-mono text-xs font-bold uppercase tracking-[0.22em] text-ink hover:text-amber-ink transition-colors">
                <x-heroicon-o-chat-bubble-left-right class="w-4 h-4" />
                Contact support
                <x-heroicon-s-arrow-long-right class="w-4 h-4" />
            </a>
        </div>
    </div>
</div>
@endsection
