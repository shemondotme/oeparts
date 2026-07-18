@extends('layouts.app')

@section('title', ui_copy('checkout_payment_title', 'checkout.payment_title') . ' — ' . settings('general.site_name', 'OeParts'))

@section('meta_robots')<meta name="robots" content="noindex, nofollow">@endsection

@php
    $lang = app()->getLocale();

    // Apple Pay / Google Pay are instruments inside the single Airwallex
    // "card" payment_method (there's no separate PaymentMethod enum case for
    // them) — admin toggles under Checkout Settings → Payment Methods decide
    // whether the Drop-in element is allowed to offer each wallet. Brand
    // names are proper nouns and stay untranslated across all locales.
    $appleEnabled = (bool) settings('checkout.enable_apple_pay', true);
    $googleEnabled = (bool) settings('checkout.enable_google_pay', true);
    $enabledWallets = array_filter([
        $appleEnabled ? 'Apple Pay' : null,
        $googleEnabled ? 'Google Pay' : null,
    ]);

    $cardMethodLabel = ui_copy('checkout_credit_debit_card', 'checkout.credit_debit_card');
    $cardBrandsCaption = ui_copy('checkout_card_brands_caption', 'checkout.card_brands_caption');
    if (!empty($enabledWallets)) {
        $cardMethodLabel .= ' / ' . implode(' / ', $enabledWallets);
        $cardBrandsCaption .= ' · ' . implode(' · ', $enabledWallets);
    }
@endphp

@push('styles')
    {{-- Card/Apple Pay/Google Pay payments dial out to Airwallex; open the
         connection early without paying for the SDK download unless that
         method is actually chosen. static.airwallex.com serves the
         Components SDK bundle, checkout.airwallex.com frames the card/3-DS
         iframe inside the mounted Drop-in element. --}}
    <link rel="preconnect" href="https://static.airwallex.com" crossorigin>
    <link rel="preconnect" href="https://checkout.airwallex.com" crossorigin>
@endpush

@section('content')
<div class="relative min-h-screen bg-ivory text-ink">
    <div class="fixed inset-0 bg-grid-ivory-fine bg-grid-md opacity-40 pointer-events-none" aria-hidden="true"></div>

    {{-- ── Dark Doc Header ── --}}
    <div class="relative bg-ink text-ivory border-b border-rule-dark overflow-hidden">
        <div class="absolute inset-0 bg-grid-navy bg-grid-lg opacity-60 pointer-events-none" aria-hidden="true"></div>
        <div class="relative max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-10 pt-10 pb-6">

            {{-- Breadcrumb --}}
            <div class="flex flex-wrap items-center justify-between gap-4 pb-4 mb-6 border-b border-white/15">
                <nav class="flex items-center gap-2 font-mono text-[10px] tracking-[0.22em] uppercase text-ivory/60">
                    <a href="{{ url('/'.$lang.'/') }}" class="hover:text-amber transition-colors">{{ ui_copy('checkout_breadcrumb_home', 'checkout.breadcrumb_home') }}</a>
                    <span class="text-ivory/30">/</span>
                    <span class="text-ivory/80">{{ ui_copy('checkout_breadcrumb_checkout', 'checkout.breadcrumb_checkout') }}</span>
                    <span class="text-ivory/30">/</span>
                    <span class="text-ivory">{{ ui_copy('checkout_step_payment', 'checkout.step_payment') }}</span>
                </nav>
                <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-ivory/60">
                    {{ ui_copy('checkout_doc_payment_sheet', 'checkout.doc_payment_sheet', ['number' => $order->order_number]) }}
                </span>
            </div>

            <div class="flex items-end justify-between gap-4 flex-wrap">
                <div>
                    <div class="flex items-center gap-4 mb-4">
                        <span class="w-10 h-[3px] bg-amber inline-block"></span>
                        <span class="font-mono text-[10px] tracking-[0.28em] uppercase text-amber">{{ ui_copy('checkout_payment_title', 'checkout.payment_title') }}</span>
                    </div>
                    <h1 class="font-display font-extrabold text-ivory leading-[0.95] tracking-[-0.03em] text-4xl md:text-5xl lg:text-6xl">
                        {{ ui_copy('checkout_complete_payment_heading', 'checkout.complete_payment_heading') }}<span class="text-amber">.</span>
                    </h1>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Flash error ── --}}
    @if(session('error'))
    <div class="relative max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-10 pt-6">
        <div class="border border-red-600 bg-red-50 px-4 py-3 flex items-start gap-3" role="alert" aria-live="assertive">
            <x-heroicon-s-exclamation-triangle class="w-5 h-5 text-red-600 shrink-0 mt-0.5" aria-hidden="true" />
            <p class="text-sm text-red-800">{{ session('error') }}</p>
        </div>
    </div>
    @endif

    {{-- ── Main content ── --}}
    <div class="relative max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-10 py-10">

        <div class="grid grid-cols-12 gap-x-4 sm:gap-x-6 lg:gap-x-10 items-start">

            {{-- Left: Payment form --}}
            <div class="col-span-12 lg:col-span-8">

                <div class="border border-ink bg-paper">
                    <div class="flex items-center justify-between px-5 py-3 border-b border-ink bg-ivory-alt">
                        <span class="bp-spec text-amber-ink">{{ ui_copy('checkout_payment_method_heading', 'checkout.payment_method_heading') }}</span>
                        <span class="bp-spec-mono">
                            {{ ui_copy('checkout_order_label', 'checkout.order_label', ['number' => $order->order_number]) }}
                        </span>
                    </div>

                    <div class="p-6 sm:p-8 space-y-6">

                        <form method="POST"
                              action="{{ route('frontend.checkout.payment.process', ['lang' => $lang, 'order' => $order->order_number]) }}"
                              id="payment-form"
                              enctype="multipart/form-data"
                              class="space-y-6"
                              x-data="{
                                  submitting: false,
                                  errorMessage: '',
                                  showError(msg) {
                                      this.errorMessage = msg;
                                      this.$el.querySelector('#payment-error-inline')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                  },
                                  clearError() {
                                      this.errorMessage = '';
                                  },
                                  onSubmit() {
                                      this.submitting = true;
                                      this.errorMessage = '';
                                  }
                              }"
                              @submit="onSubmit">
                            @csrf
                            <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
                            <div class="border border-ink bg-paper">
                                <label class="flex items-start gap-4 p-5 cursor-pointer border-b border-rule transition-colors hover:bg-ivory-alt">
                                    <div class="flex items-center gap-3 shrink-0 mt-0.5">
                                        <input type="radio" id="method-card" name="payment_method" value="card"
                                               class="w-4 h-4 border-ink text-amber-ink focus:ring-amber-ink focus:ring-offset-0"
                                               {{ old('payment_method', $selectedMethod) === 'card' ? 'checked' : '' }} required>
                                    </div>
                                    <div class="w-10 h-10 border border-rule-strong bg-ivory-alt flex items-center justify-center shrink-0">
                                        <x-heroicon-o-credit-card class="w-5 h-5 text-ink" />
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-display text-base font-bold text-ink tracking-[-0.01em]">{{ $cardMethodLabel }}</p>
                                        <p class="mt-1 font-mono text-[11px] tracking-[0.18em] uppercase text-ink-muted">
                                            {{ $cardBrandsCaption }}
                                        </p>
                                    </div>
                                </label>

                                <label class="flex items-start gap-4 p-5 cursor-pointer transition-colors hover:bg-ivory-alt">
                                    <div class="flex items-center gap-3 shrink-0 mt-0.5">
                                        <input type="radio" id="method-bank" name="payment_method" value="bank_transfer"
                                               class="w-4 h-4 border-ink text-amber-ink focus:ring-amber-ink focus:ring-offset-0"
                                               {{ old('payment_method', $selectedMethod) === 'bank_transfer' ? 'checked' : '' }}>
                                    </div>
                                    <div class="w-10 h-10 border border-rule-strong bg-ivory-alt flex items-center justify-center shrink-0">
                                        <x-heroicon-o-building-library class="w-5 h-5 text-ink" />
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-display text-base font-bold text-ink tracking-[-0.01em]">{{ ui_copy('checkout_bank_transfer', 'checkout.bank_transfer') }}</p>
                                        <p class="mt-1 font-mono text-[11px] tracking-[0.18em] uppercase text-ink-muted">
                                            {{ ui_copy('checkout_bank_transfer_sepa_note', 'checkout.bank_transfer_sepa_note') }}
                                        </p>
                                    </div>
                                </label>
                            </div>

                            {{-- Inline payment error (Alpine) --}}
                            <div id="payment-error-inline" x-show="errorMessage" x-cloak
                                 class="border border-red-600 bg-red-50 px-4 py-3 flex items-start gap-3"
                                 role="alert" aria-live="assertive">
                                <x-heroicon-s-exclamation-triangle class="w-5 h-5 text-red-600 shrink-0 mt-0.5" />
                                <div class="flex-1 min-w-0">
                                    <p class="bp-spec-mono text-red-600 mb-0.5">{{ ui_copy('checkout_payment_error_heading', 'checkout.payment_error_heading') }}</p>
                                    <p class="text-sm text-red-800" x-text="errorMessage"></p>
                                </div>
                                <button type="button" @click="clearError()"
                                        class="shrink-0 text-red-600 hover:text-red-800">
                                    <x-heroicon-s-x-mark class="w-4 h-4" />
                                </button>
                            </div>

                            {{-- Card section --}}
                            <div id="card-section" class="hidden space-y-4">
                                <div class="border border-rule-strong bg-ivory-alt p-5 flex items-start gap-3">
                                    <div class="w-9 h-9 border border-ink bg-paper flex items-center justify-center shrink-0">
                                        <x-heroicon-s-lock-closed class="w-4 h-4 text-amber-ink" />
                                    </div>
                                    <div>
                                        <p class="bp-spec text-amber-ink mb-1">{{ ui_copy('checkout_secure_payment', 'checkout.secure_payment') }}</p>
                                        <p class="text-sm text-body leading-relaxed">
                                            {{ ui_copy('checkout_secure_payment_note', 'checkout.secure_payment_note') }}
                                        </p>
                                    </div>
                                </div>

                                <div id="airwallex-dropin" class="border border-ink bg-paper p-5 min-h-[200px]"></div>

                                <input type="hidden" name="payment_intent_id" id="payment-intent-id">
                            </div>

                            {{-- Bank transfer section --}}
                            <div id="bank-section" class="hidden space-y-4">
                                <div class="border border-amber bg-amber/10 p-5 flex items-start gap-3">
                                    <div class="w-9 h-9 border border-amber bg-paper flex items-center justify-center shrink-0">
                                        <x-heroicon-s-exclamation-circle class="w-4 h-4 text-amber-ink" />
                                    </div>
                                    <div>
                                        <p class="bp-spec text-amber-ink mb-1">{{ ui_copy('checkout_important_instructions', 'checkout.important_instructions') }}</p>
                                        <p class="text-sm text-body leading-relaxed">
                                            {{ ui_copy('checkout_important_instructions_note', 'checkout.important_instructions_note') }}
                                        </p>
                                    </div>
                                </div>

                                <section class="border border-ink bg-paper">
                                    <header class="flex items-center justify-between px-4 py-3 border-b border-ink bg-ivory-alt">
                                        <span class="bp-spec text-amber-ink flex items-center gap-2">
                                            <x-heroicon-o-building-library class="w-3.5 h-3.5" />
                                            {{ ui_copy('checkout_bank_transfer_details', 'checkout.bank_transfer_details') }}
                                        </span>
                                    </header>
                                    <div class="p-5">
                                        @if(!empty($bankDetails))
                                            <dl class="divide-y divide-rule">
                                                @foreach($bankDetails as $key => $value)
                                                @if(is_scalar($value) || $value === null)
                                                <div class="flex items-baseline justify-between gap-3 py-3">
                                                    <dt class="bp-spec-mono">{{ __(ucfirst(str_replace('_', ' ', $key))) }}</dt>
                                                    <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                                                    <dd class="flex items-center gap-2">
                                                        <span class="font-mono text-sm font-bold text-ink tabular-nums">{{ $value }}</span>
                                                        <button type="button"
                                                                class="inline-flex items-center gap-1 px-2 py-1 border border-ink bg-paper hover:bg-ink hover:text-ivory
                                                                       font-mono text-[10px] font-bold uppercase tracking-[0.18em] text-ink transition-colors copy-btn"
                                                                data-clipboard-text="{{ $value }}"
                                                                aria-live="polite">
                                                            <x-heroicon-o-document-duplicate class="w-3 h-3" />
                                                            {{ ui_copy('checkout_copy_btn', 'checkout.copy_btn') }}
                                                        </button>
                                                    </dd>
                                                </div>
                                                @endif
                                                @endforeach
                                            </dl>
                                        @else
                                            <p class="font-mono text-xs tracking-[0.18em] uppercase text-ink-muted">{{ ui_copy('checkout_bank_details_pending', 'checkout.bank_details_pending') }}</p>
                                        @endif
                                    </div>
                                </section>

                                <div>
                                    <label for="payment_proof" class="bp-spec block mb-2 text-ink">
                                        {{ ui_copy('checkout_upload_payment_proof', 'checkout.upload_payment_proof') }}
                                        <span class="text-ink-muted/80 normal-case tracking-normal font-normal ml-1">{{ ui_copy('checkout_optional', 'checkout.optional') }}</span>
                                    </label>
                                    <input type="file" id="payment_proof" name="payment_proof"
                                           class="block w-full text-sm text-body font-mono
                                                  file:mr-4 file:py-2.5 file:px-4 file:border-0 file:border-r file:border-ink
                                                  file:bg-ink file:text-ivory file:font-mono file:text-[10px] file:font-bold file:uppercase file:tracking-[0.22em]
                                                  file:cursor-pointer hover:file:bg-amber hover:file:text-ink transition-colors
                                                  border border-ink bg-paper">
                                    <p class="mt-2 font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted">
                                        {{ ui_copy('checkout_upload_payment_proof_note', 'checkout.upload_payment_proof_note') }}
                                    </p>
                                </div>
                            </div>

                            {{-- Submit --}}
                            <button type="submit" id="submit-btn"
                                    :disabled="submitting"
                                    class="bp-btn-primary w-full justify-center py-4 text-base"
                                    :class="submitting && 'opacity-60 pointer-events-none'">
                                <span x-show="submitting" x-cloak>
                                    <x-heroicon-s-arrow-path class="w-5 h-5 animate-spin" />
                                </span>
                                <span x-show="!submitting" x-cloak>
                                    <x-heroicon-s-lock-closed class="w-5 h-5" />
                                </span>
                                <span x-text="submitting ? '{{ addslashes(ui_copy('checkout_processing', 'checkout.processing')) }}' : '{{ addslashes(ui_copy('checkout_complete_payment_btn', 'checkout.complete_payment_btn')) }}'"></span>
                            </button>
                        </form>

                    </div>
                </div>
            </div>

            {{-- Right: Order summary --}}
            <aside class="col-span-12 lg:col-span-4 lg:sticky lg:top-10 lg:h-fit mt-8 lg:mt-0">
                <div class="border border-ink bg-paper">
                    <div class="flex items-center justify-between px-5 py-3 border-b border-ink bg-ivory-alt">
                        <span class="bp-spec text-amber-ink">{{ ui_copy('checkout_order_summary', 'checkout.order_summary') }}</span>
                        <span class="bp-spec-mono">{{ settings('general.currency', 'EUR') }}</span>
                    </div>

                    <div class="p-5 space-y-2">
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="bp-spec-mono">{{ ui_copy('checkout_order_number_label', 'checkout.order_number_label') }}</dt>
                            <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                            <dd class="font-mono text-sm font-bold text-ink tabular-nums">{{ $order->order_number }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="bp-spec-mono">{{ ui_copy('checkout_items_label', 'checkout.items_label') }}</dt>
                            <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                            <dd class="font-mono text-sm font-bold text-ink tabular-nums">{{ $order->items->count() }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="bp-spec-mono">{{ ui_copy('checkout_subtotal_label', 'checkout.subtotal_label') }}</dt>
                            <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                            <dd class="font-mono text-sm font-bold text-ink tabular-nums">{{ format_price($order->subtotal) }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="bp-spec-mono">{{ ui_copy('checkout_shipping_label', 'checkout.shipping_label') }}</dt>
                            <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                            <dd class="font-mono text-sm font-bold text-ink tabular-nums">{{ format_price($order->shipping_cost) }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="bp-spec-mono">{{ ui_copy('checkout_vat_short', 'checkout.vat_short') }}</dt>
                            <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                            <dd class="font-mono text-sm font-bold text-ink tabular-nums">{{ format_price($order->vat_amount) }}</dd>
                        </div>
                    </div>

                    <div class="px-5 py-4 border-t-2 border-ink flex items-end justify-between gap-3">
                        <div>
                            <p class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink">{{ ui_copy('checkout_grand_total_label', 'checkout.grand_total_label') }}</p>
                            <p class="font-mono text-[9px] tracking-[0.2em] uppercase text-ink-muted mt-1">{{ settings('general.currency', 'EUR') }} · {{ ui_copy('checkout_incl_vat_short', 'checkout.incl_vat_short') }}</p>
                        </div>
                        <p class="font-mono text-3xl font-medium text-ink tabular-nums leading-none tracking-tight">
                            {{ format_price($order->grand_total) }}
                        </p>
                    </div>
                </div>

                {{-- Trust strip --}}
                <div class="mt-4 border border-rule bg-ivory-alt p-4">
                    <div class="flex items-center justify-center gap-2 px-2">
                        <x-heroicon-s-credit-card class="w-4 h-4 text-amber-ink shrink-0" />
                        <span class="font-mono text-[9px] tracking-[0.2em] uppercase text-ink">Airwallex{{ !empty($enabledWallets) ? ' · ' . implode(' · ', $enabledWallets) : '' }}</span>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const cardRadio = document.getElementById('method-card');
            const bankRadio = document.getElementById('method-bank');
            const cardSection = document.getElementById('card-section');
            const bankSection = document.getElementById('bank-section');
            const submitBtn = document.getElementById('submit-btn');
            const submitLabel = submitBtn ? submitBtn.querySelector('span:not([x-show])') : null;

            function getAlpineForm() {
                const form = document.getElementById('payment-form');
                return form ? Alpine.$data(form) : null;
            }

            function showPaymentError(message) {
                const alpine = getAlpineForm();
                if (alpine) {
                    alpine.showError(message);
                } else {
                    alert(message);
                }
            }
            window.showPaymentError = showPaymentError;

            // The Airwallex Components SDK (~sizeable bundle) is only fetched
            // once the customer actually picks card/wallet payment — was an
            // unconditional <script src> on every visit, including
            // bank-transfer customers who never use it. A preconnect hint
            // above still opens the connection early so choosing this method
            // doesn't pay the DNS/TLS cost.
            var airwallexScriptPromise = null;
            function loadAirwallexScript() {
                if (!airwallexScriptPromise) {
                    airwallexScriptPromise = new Promise(function (resolve, reject) {
                        if (typeof window.AirwallexComponentsSDK !== 'undefined') { resolve(); return; }
                        var script = document.createElement('script');
                        script.src = 'https://static.airwallex.com/components/sdk/v1/index.js';
                        script.onload = resolve;
                        script.onerror = reject;
                        document.head.appendChild(script);
                    });
                }
                return airwallexScriptPromise;
            }

            function toggleSections() {
                if (cardRadio.checked) {
                    cardSection.classList.remove('hidden');
                    bankSection.classList.add('hidden');
                    // The Drop-in element renders its own Pay/Apple Pay/Google
                    // Pay button inside #airwallex-dropin — our own submit
                    // button would be a confusing second "pay" control.
                    submitBtn.classList.add('hidden');
                    loadAirwallexScript().then(initAirwallex).catch(function () {
                        showPaymentError('{{ addslashes(ui_copy('checkout_payment_failed_js', 'checkout.payment_failed_js')) }}');
                    });
                } else if (bankRadio.checked) {
                    cardSection.classList.add('hidden');
                    bankSection.classList.remove('hidden');
                    submitBtn.classList.remove('hidden');
                }
            }

            cardRadio.addEventListener('change', toggleSections);
            bankRadio.addEventListener('change', toggleSections);
            toggleSections();

            document.querySelectorAll('.copy-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const text = this.getAttribute('data-clipboard-text');
                    navigator.clipboard.writeText(text).then(() => {
                        const original = this.innerHTML;
                        this.textContent = '{{ addslashes(ui_copy('checkout_copied_btn', 'checkout.copied_btn')) }}';
                        this.classList.add('bg-amber', 'border-amber', 'text-ink');
                        setTimeout(() => {
                            this.innerHTML = original;
                            this.classList.remove('bg-amber', 'border-amber', 'text-ink');
                        }, 2000);
                    });
                });
            });

            let airwallexInitialized = false;
            function initAirwallex() {
                if (airwallexInitialized) return;
                if (typeof window.AirwallexComponentsSDK === 'undefined') return;

                fetch('{{ route("frontend.checkout.payment.intent", ["lang" => $lang, "order" => $order->order_number]) }}')
                    .then(r => r.json())
                    .then(async (data) => {
                        if (!data.success) {
                            showPaymentError('{{ addslashes(ui_copy('checkout_payment_failed_js', 'checkout.payment_failed_js')) }}');
                            return;
                        }
                        document.getElementById('payment-intent-id').value = data.payment_intent_id;

                        await window.AirwallexComponentsSDK.init({
                            env: data.env,
                            enabledElements: ['payments'],
                        });

                        // dropIn covers card, Apple Pay and Google Pay in a
                        // single embedded element — it auto-detects which of
                        // the offered wallets the current browser/device
                        // actually supports and only shows those. Which
                        // wallets are OFFERED at all is admin-controlled
                        // (Checkout Settings → Payment Methods); 'methods'
                        // restricts the element to exactly that list, and the
                        // per-wallet *RequestOptions are only sent when that
                        // wallet is enabled (both require countryCode — the
                        // merchant's processing country, not the customer's).
                        const dropInMethods = ['card'];
                        const dropInOptions = {
                            intent_id: data.payment_intent_id,
                            client_secret: data.client_secret,
                            currency: data.currency,
                        };
                        if (data.enable_apple_pay) {
                            dropInMethods.push('applepay');
                            dropInOptions.applePayRequestOptions = { countryCode: data.country_code };
                        }
                        if (data.enable_google_pay) {
                            dropInMethods.push('googlepay');
                            dropInOptions.googlePayRequestOptions = { countryCode: data.country_code };
                        }
                        dropInOptions.methods = dropInMethods;

                        const dropIn = await window.AirwallexComponentsSDK.createElement('dropIn', dropInOptions);

                        dropIn.mount('airwallex-dropin');

                        const returnUrl = '{{ route("frontend.checkout.payment.return", ["lang" => $lang, "order" => $order->order_number]) }}';

                        // The Drop-in element owns the entire confirm flow
                        // internally (including any 3-DS / wallet sheet) —
                        // 'success' only fires once the payment has actually
                        // gone through, so we just hand off to the
                        // return/waiting page (webhook is still the source of
                        // truth for the order's paid status).
                        dropIn.on('success', () => {
                            window.location.href = returnUrl;
                        });

                        dropIn.on('error', (event) => {
                            console.error('Payment error:', event.detail);
                            showPaymentError('{{ addslashes(ui_copy('checkout_payment_failed_js', 'checkout.payment_failed_js')) }}');
                        });

                        airwallexInitialized = true;
                    })
                    .catch(err => {
                        console.error('intent error', err);
                        showPaymentError('{{ addslashes(ui_copy('checkout_payment_failed_js', 'checkout.payment_failed_js')) }}');
                    });
            }
        });
    </script>
@endpush
