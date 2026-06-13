@extends('layouts.app')

@section('title', __('Payment failed') . ' — ' . settings('general.site_name', 'OeParts'))

@section('content')
<div class="relative min-h-screen bg-ivory text-ink">
    <div class="fixed inset-0 bg-grid-ivory-fine bg-grid-md opacity-40 pointer-events-none" aria-hidden="true"></div>

    <div class="relative max-w-3xl mx-auto px-4 sm:px-6 lg:px-10 py-16">
        <div class="border border-ink bg-paper" style="box-shadow: 6px 6px 0 rgba(20,22,29,1);">
            <div class="flex items-center justify-between px-5 py-3 border-b border-ink bg-red-600 text-ivory">
                <span class="font-mono text-[10px] tracking-[0.22em] uppercase font-bold flex items-center gap-2">
                    <x-heroicon-s-exclamation-triangle class="w-3.5 h-3.5" />
                    Payment · Failed
                </span>
                <span class="font-mono text-[10px] tracking-[0.22em] uppercase">
                    {{ $order->order_number }}
                </span>
            </div>

            <div class="p-8 sm:p-12 text-center">
                <div class="mx-auto w-14 h-14 border border-red-600 bg-red-50 flex items-center justify-center mb-6">
                    <x-heroicon-s-x-mark class="w-7 h-7 text-red-600" />
                </div>
                <h1 class="font-display text-3xl md:text-4xl font-extrabold text-ink leading-tight tracking-[-0.02em]">
                    Payment could not be completed<span class="text-amber">.</span>
                </h1>
                <p class="mt-4 text-body leading-relaxed">
                    {{ __('Your payment was declined or interrupted. No charges were made. You can try again or choose a different payment method.') }}
                </p>

                <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
                    <a href="{{ route('frontend.checkout.payment', ['lang' => $lang, 'order' => $order->order_number]) }}"
                       class="bp-btn-primary justify-center">
                        <x-heroicon-s-arrow-path class="w-5 h-5" />
                        {{ __('Try again') }}
                    </a>
                    <a href="{{ route('frontend.search.console', ['lang' => $lang]) }}"
                       class="bp-btn-outline justify-center">
                        {{ __('Back to browse') }}
                    </a>
                </div>

                <div class="mt-8 inline-flex items-center gap-3 px-4 py-3 border border-rule-strong bg-ivory-alt">
                    <span class="bp-spec-mono">Order</span>
                    <span class="font-mono text-sm font-bold text-ink tabular-nums">{{ $order->order_number }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
