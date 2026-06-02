@extends('layouts.app')

@section('title', __('Payment processing') . ' — ' . settings('general.site_name', 'OeParts'))

@section('content')
<div class="relative min-h-screen bg-ivory text-ink">
    <div class="fixed inset-0 bg-grid-ivory-fine bg-grid-md opacity-40 pointer-events-none" aria-hidden="true"></div>

    <div class="relative max-w-3xl mx-auto px-4 sm:px-6 lg:px-10 py-16">
        <div class="border border-ink bg-paper" style="box-shadow: 6px 6px 0 rgba(20,22,29,1);">
            <div class="flex items-center justify-between px-5 py-3 border-b border-ink bg-ivory-alt">
                <span class="bp-spec text-amber-ink flex items-center gap-2">
                    <x-heroicon-o-arrow-path class="w-3.5 h-3.5 animate-spin" />
                    § Payment · Processing
                </span>
                <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">
                    {{ $order->order_number }}
                </span>
            </div>

            <div class="p-8 sm:p-12 text-center">
                <div class="mx-auto w-14 h-14 border border-ink bg-paper flex items-center justify-center mb-6">
                    <x-heroicon-o-arrow-path class="w-6 h-6 text-ink animate-spin" />
                </div>
                <h1 class="font-display text-3xl md:text-4xl font-extrabold text-ink leading-tight tracking-[-0.02em]">
                    Verifying your payment<span class="text-amber">.</span>
                </h1>
                <p class="mt-4 text-body leading-relaxed">
                    {{ __('We are waiting for confirmation from your bank. This usually takes a few seconds. This page will refresh automatically.') }}
                </p>
                <div class="mt-8 inline-flex items-center gap-3 px-4 py-3 border border-rule-strong bg-ivory-alt">
                    <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">Order</span>
                    <span class="font-mono text-sm font-bold text-ink tabular-nums">{{ $order->order_number }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
<meta http-equiv="refresh" content="5">
@endsection
