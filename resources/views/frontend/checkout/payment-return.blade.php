@extends('layouts.app')

@section('title', ui_copy('checkout_payment_processing_title', 'checkout.payment_processing_title') . ' — ' . settings('general.site_name', 'OeParts'))

@section('meta_robots')<meta name="robots" content="noindex, nofollow">@endsection

@section('content')
<div class="relative min-h-screen bg-ivory text-ink">
    <div class="fixed inset-0 bg-grid-ivory-fine bg-grid-md opacity-40 pointer-events-none" aria-hidden="true"></div>

    <div class="relative max-w-3xl mx-auto px-4 sm:px-6 lg:px-10 py-16">
        <div class="border border-ink bg-paper" style="box-shadow: 6px 6px 0 rgba(20,22,29,1);">
            <div class="flex items-center justify-between px-5 py-3 border-b border-ink bg-ivory-alt">
                <span class="bp-spec text-amber-ink flex items-center gap-2">
                    <x-heroicon-o-arrow-path class="w-3.5 h-3.5 animate-spin" />
                    {{ ui_copy('checkout_payment_processing_eyebrow', 'checkout.payment_processing_eyebrow') }}
                </span>
                <span class="bp-spec-mono">
                    {{ $order->order_number }}
                </span>
            </div>

            <div class="p-8 sm:p-12 text-center">
                <div class="mx-auto w-14 h-14 border border-ink bg-paper flex items-center justify-center mb-6">
                    <x-heroicon-o-arrow-path class="w-6 h-6 text-ink animate-spin" />
                </div>
                <h1 class="font-display text-3xl md:text-4xl font-extrabold text-ink leading-tight tracking-[-0.02em]">
                    {{ ui_copy('checkout_verifying_payment_heading', 'checkout.verifying_payment_heading') }}<span class="text-amber">.</span>
                </h1>
                <p class="mt-4 text-body leading-relaxed">
                    {{ ui_copy('checkout_verifying_payment_note', 'checkout.verifying_payment_note') }}
                </p>
                <div class="mt-8 inline-flex items-center gap-3 px-4 py-3 border border-rule-strong bg-ivory-alt">
                    <span class="bp-spec-mono">{{ ui_copy('checkout_order_word', 'checkout.order_word') }}</span>
                    <span class="font-mono text-sm font-bold text-ink tabular-nums">{{ $order->order_number }}</span>
                </div>
                <button type="button" onclick="window.location.reload()"
                        class="mt-8 bp-btn-outline">
                    {{ ui_copy('checkout_check_status_now', 'checkout.check_status_now') }}
                </button>
                <p class="sr-only" role="status" aria-live="polite">
                    {{ ui_copy('checkout_verifying_payment_note', 'checkout.verifying_payment_note') }}
                </p>
            </div>
        </div>
    </div>
</div>
{{-- Background poll (no full-page reload) — checks whether the server-side
     webhook has confirmed payment yet, and navigates ONLY once, when it has.
     Was an unconditional `<meta http-equiv="refresh" content="5">` reloading
     the whole page every 5s with no way to pause/extend it (WCAG 2.2.1). The
     "Check status now" button above and this noscript fallback both cover
     JS-disabled visitors. --}}
<noscript><meta http-equiv="refresh" content="5"></noscript>
<script nonce="{{ csp_nonce() }}">
    (function () {
        var poll = setInterval(function () {
            fetch(window.location.href, { credentials: 'same-origin' })
                .then(function (response) {
                    if (response.redirected) {
                        clearInterval(poll);
                        window.location.href = response.url;
                    }
                })
                .catch(function () { /* transient network error — try again next tick */ });
        }, 5000);
    })();
</script>
@endsection
