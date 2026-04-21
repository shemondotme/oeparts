@extends('frontend.checkout.layout')

@section('checkout_content')
    <h2 class="text-2xl font-bold mb-6">{{ __('Payment') }}</h2>
    <p class="text-gray-600 mb-8">
        {{ __('Complete your payment to finalize your order.') }}
    </p>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {{-- Left column: Payment method selection --}}
        <div class="lg:col-span-2 space-y-8">
            {{-- Order summary --}}
            <div class="bg-gray-50 rounded-xl p-6">
                <h3 class="font-bold text-lg mb-4">{{ __('Order Summary') }}</h3>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-600">{{ __('Order Number') }}</span>
                        <span class="font-medium">{{ $order->order_number }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">{{ __('Grand Total') }}</span>
                        <span class="font-bold text-lg">{{ format_money($order->grand_total) }}</span>
                    </div>
                </div>
            </div>

            {{-- Payment method selection --}}
            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <h3 class="font-bold text-lg mb-6">{{ __('Select Payment Method') }}</h3>
                
                <form method="POST" action="{{ route('frontend.checkout.payment.process', ['lang' => $lang, 'order' => $order->order_number]) }}" id="payment-form">
                    @csrf
                    {{-- Honeypot --}}
                    <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
                    
                    {{-- Payment method radio buttons --}}
                    <div class="space-y-4 mb-8">
                        <div class="flex items-center p-4 border border-gray-300 rounded-lg hover:border-primary-500 transition cursor-pointer">
                            <input type="radio" id="method-card" name="payment_method" value="card" 
                                   class="h-5 w-5 text-primary-600 focus:ring-primary-500" 
                                   {{ old('payment_method', $selectedMethod) === 'card' ? 'checked' : '' }} required>
                            <label for="method-card" class="ml-4 flex-1 cursor-pointer">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <span class="font-medium text-gray-900">{{ __('Credit/Debit Card') }}</span>
                                        <p class="text-sm text-gray-600 mt-1">
                                            {{ __('Pay securely with Visa, Mastercard, or American Express via Airwallex.') }}
                                        </p>
                                    </div>
                                    <div class="flex space-x-2">
                                        <img src="https://cdn.jsdelivr.net/npm/simple-icons@v5/icons/visa.svg" alt="Visa" class="h-8 w-8">
                                        <img src="https://cdn.jsdelivr.net/npm/simple-icons@v5/icons/mastercard.svg" alt="Mastercard" class="h-8 w-8">
                                        <img src="https://cdn.jsdelivr.net/npm/simple-icons@v5/icons/americanexpress.svg" alt="Amex" class="h-8 w-8">
                                    </div>
                                </div>
                            </label>
                        </div>

                        <div class="flex items-center p-4 border border-gray-300 rounded-lg hover:border-primary-500 transition cursor-pointer">
                            <input type="radio" id="method-bank" name="payment_method" value="bank_transfer" 
                                   class="h-5 w-5 text-primary-600 focus:ring-primary-500"
                                   {{ old('payment_method', $selectedMethod) === 'bank_transfer' ? 'checked' : '' }}>
                            <label for="method-bank" class="ml-4 flex-1 cursor-pointer">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <span class="font-medium text-gray-900">{{ __('Bank Transfer') }}</span>
                                        <p class="text-sm text-gray-600 mt-1">
                                            {{ __('Make a direct bank transfer to our account. Your order will be processed once payment is confirmed.') }}
                                        </p>
                                    </div>
                                    <div class="text-primary-700">
                                        <svg class="h-8 w-8" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2H4zm12 2H4v4h12V6z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- Card payment section (shown when card selected) --}}
                    <div id="card-section" class="hidden">
                        <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <div class="flex items-center text-blue-800">
                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                <span class="font-medium">{{ __('Secure Payment') }}</span>
                            </div>
                            <p class="text-blue-700 text-sm mt-2">
                                {{ __('Your payment is processed securely by Airwallex. We never store your card details.') }}
                            </p>
                        </div>

                        {{-- Airwallex iframe container --}}
                        <div id="airwallex-dropin" class="mb-6"></div>

                        {{-- Hidden fields for Airwallex --}}
                        <input type="hidden" name="payment_intent_id" id="payment-intent-id">
                        <input type="hidden" name="payment_method_id" id="payment-method-id">
                        <input type="hidden" name="client_secret" id="client-secret">
                    </div>

                    {{-- Bank transfer section (shown when bank transfer selected) --}}
                    <div id="bank-section" class="hidden">
                        <div class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                            <div class="flex items-center text-amber-800">
                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                </svg>
                                <span class="font-medium">{{ __('Important Instructions') }}</span>
                            </div>
                            <p class="text-amber-700 text-sm mt-2">
                                {{ __('Please include your order number in the payment reference. Bank transfers may take 1-2 business days to process.') }}
                            </p>
                        </div>

                        {{-- Bank transfer details --}}
                        <div class="bg-gray-50 rounded-xl p-6 mb-6">
                            <h4 class="font-bold text-lg mb-4">{{ __('Bank Transfer Details') }}</h4>
                            @if(!empty($bankDetails))
                                <div class="space-y-4">
                                    @foreach($bankDetails as $key => $value)
                                        <div>
                                            <p class="text-sm text-gray-500">{{ __(ucfirst(str_replace('_', ' ', $key))) }}</p>
                                            <div class="flex items-center mt-1">
                                                <p class="font-mono text-lg font-medium">{{ $value }}</p>
                                                <button type="button" 
                                                        class="ml-3 px-3 py-1 text-sm bg-primary-100 text-primary-700 rounded hover:bg-primary-200 transition copy-btn"
                                                        data-clipboard-text="{{ $value }}">
                                                    {{ __('Copy') }}
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-gray-500">{{ __('Bank transfer details will appear after the method is confirmed.') }}</p>
                            @endif
                        </div>

                        {{-- Payment proof upload --}}
                        <div class="mb-6">
                            <label for="payment_proof" class="block text-sm font-medium text-gray-700 mb-2">
                                {{ __('Upload Payment Proof (Optional)') }}
                            </label>
                            <input type="file" id="payment_proof" name="payment_proof" 
                                   class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100">
                            <p class="mt-1 text-sm text-gray-500">
                                {{ __('Upload a screenshot or scan of your bank transfer confirmation.') }}
                            </p>
                        </div>
                    </div>

                    {{-- Submit button --}}
                    <div class="mt-8">
                        <button type="submit" id="submit-btn" 
                                class="w-full py-4 bg-green-600 text-white rounded-xl hover:bg-green-700 transition font-bold text-lg disabled:opacity-50 disabled:cursor-not-allowed">
                            {{ __('Complete Payment') }} &rarr;
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Right column: Order details --}}
        <div class="lg:col-span-1">
            <div class="bg-white border border-gray-200 rounded-xl p-6 sticky top-6">
                <h3 class="font-bold text-lg mb-6">{{ __('Order Details') }}</h3>
                
                {{-- Shipping info --}}
                <div class="mb-6">
                    <h4 class="font-medium text-gray-900 mb-2">{{ __('Shipping to') }}</h4>
                    <p class="text-gray-700">
                        {{ $order->shipping_name }}<br>
                        {{ $order->shipping_address_line1 }}<br>
                        {{ $order->shipping_city }}, {{ $order->shipping_postal_code }}<br>
                        {{ \App\Models\ShippingCountry::getName($order->shipping_country_code) ?? $order->shipping_country_code }}
                    </p>
                </div>

                {{-- Contact info --}}
                <div class="mb-6">
                    <h4 class="font-medium text-gray-900 mb-2">{{ __('Contact') }}</h4>
                    <p class="text-gray-700">{{ $order->guest_email }}</p>
                    @if($order->company_name)
                        <p class="text-gray-700 mt-1">{{ $order->company_name }}</p>
                    @endif
                </div>

                {{-- Items summary --}}
                <div class="border-t border-gray-200 pt-6">
                    <h4 class="font-medium text-gray-900 mb-3">{{ __('Items') }}</h4>
                    <div class="space-y-3">
                        @foreach($order->items as $item)
                            <div class="flex justify-between">
                                <div class="text-sm">
                                    <p class="font-medium">{{ $item->oem_number_snapshot }}</p>
                                    <p class="text-gray-600">{{ $item->quantity }} × {{ format_money($item->unit_price) }}</p>
                                </div>
                                <div class="text-sm font-medium">
                                    {{ format_money($item->total_price) }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Need help --}}
                <div class="mt-8 p-4 bg-gray-50 rounded-lg">
                    <p class="text-sm text-gray-600">
                        {{ __('Need help with payment?') }}
                        <a href="{{ route('frontend.page', ['lang' => $lang, 'slug' => 'contact']) }}" class="text-primary-600 hover:underline">
                            {{ __('Contact support') }}
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    {{-- Airwallex SDK --}}
    <script src="https://checkout.airwallex.com/assets/elements.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Payment method toggle
            const cardRadio = document.getElementById('method-card');
            const bankRadio = document.getElementById('method-bank');
            const cardSection = document.getElementById('card-section');
            const bankSection = document.getElementById('bank-section');
            const submitBtn = document.getElementById('submit-btn');

            function toggleSections() {
                if (cardRadio.checked) {
                    cardSection.classList.remove('hidden');
                    bankSection.classList.add('hidden');
                    submitBtn.disabled = false;
                    submitBtn.textContent = '{{ __("Pay Now") }} →';
                    initAirwallex();
                } else if (bankRadio.checked) {
                    cardSection.classList.add('hidden');
                    bankSection.classList.remove('hidden');
                    submitBtn.disabled = false;
                    submitBtn.textContent = '{{ __("Confirm Bank Transfer") }} →';
                }
            }

            cardRadio.addEventListener('change', toggleSections);
            bankRadio.addEventListener('change', toggleSections);
            
            // Initialize with current selection
            toggleSections();

            // Clipboard for bank details
            const copyButtons = document.querySelectorAll('.copy-btn');
            copyButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const text = this.getAttribute('data-clipboard-text');
                    navigator.clipboard.writeText(text).then(() => {
                        const originalText = this.textContent;
                        this.textContent = '{{ __("Copied!") }}';
                        this.classList.add('bg-green-100', 'text-green-700');
                        setTimeout(() => {
                            this.textContent = originalText;
                            this.classList.remove('bg-green-100', 'text-green-700');
                        }, 2000);
                    });
                });
            });

            // Airwallex initialization
            let airwallexInitialized = false;
            
            function initAirwallex() {
                if (airwallexInitialized) return;
                
                // Load Airwallex configuration from server
                fetch('{{ route("frontend.checkout.payment.intent", ["lang" => $lang, "order" => $order->order_number]) }}')
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            console.error('Failed to create payment intent:', data.error);
                            return;
                        }

                        // Set hidden fields
                        document.getElementById('payment-intent-id').value = data.payment_intent_id;
                        document.getElementById('client-secret').value = data.client_secret;

                        // Initialize Airwallex drop-in
                        Airwallex.init({
                            env: data.env, // 'demo' or 'prod'
                            origin: window.location.origin,
                            locale: '{{ app()->getLocale() }}'
                        });

                        const dropin = Airwallex.createElement('dropin', {
                            client_secret: data.client_secret,
                            currency: data.currency,
                            amount: data.amount,
                            onSuccess: (response) => {
                                console.log('Payment successful:', response);
                                document.getElementById('payment-method-id').value = response.id;
                                // Submit form automatically
                                document.getElementById('payment-form').submit();
                            },
                            onError: (error) => {
                                console.error('Payment error:', error);
                                alert('{{ __("Payment failed. Please try again.") }}');
                            }
                        });

                        dropin.mount('airwallex-dropin');
                        airwallexInitialized = true;
                    })
                    .catch(error => {
                        console.error('Error loading payment intent:', error);
                    });
            }

            // Form submission handling
            document.getElementById('payment-form').addEventListener('submit', function(e) {
                if (cardRadio.checked) {
                    // For card payments, Airwallex will handle submission via onSuccess
                    e.preventDefault();
                    // Trigger Airwallex payment
                    Airwallex.confirmPaymentIntent({
                        client_secret: document.getElementById('client-secret').value,
                        element: Airwallex.getElement('dropin'),
                        confirmParams: {
                            return_url: '{{ route("frontend.checkout.payment.return", ["lang" => $lang, "order" => $order->order_number]) }}'
                        }
                    });
                }
                // For bank transfers, form submits normally
            });
        });
    </script>
@endpush