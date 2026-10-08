<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $order->order_number }}</title>
    @include('pdf.partials.invoice-styles')
</head>
<body>
    <div class="header">
        <div class="company-info">
            @php [$wordmarkHeavy, $wordmarkLight] = brand_wordmark_parts($settings['company_name']); @endphp
            <h1><span class="wordmark-heavy">{{ $wordmarkHeavy }}</span><span class="wordmark-light">{{ $wordmarkLight }}</span><span class="wordmark-dot">.</span></h1>
            <div>{{ $settings['company_address'] }}</div>
            <div class="mono">VAT: {{ $settings['company_vat'] }}</div>
            @if(!empty($settings['company_registration']))
                <div class="mono">Reg. No: {{ $settings['company_registration'] }}</div>
            @endif
            <div>Email: {{ $settings['company_email'] }}</div>
            <div>Phone: {{ $settings['company_phone'] }}</div>
        </div>
        <div class="invoice-info">
            <p class="doc-eyebrow">OEPARTS · INVOICE</p>
            <h2>INVOICE</h2>
            <div class="meta-row"><span class="label">No.</span><span class="value">{{ $order->invoice_number ?? $order->order_number }}</span></div>
            <div class="meta-row"><span class="label">Date</span><span class="value">{{ $order->created_at->format('d/m/Y') }}</span></div>
            <div class="meta-row"><span class="label">Due</span><span class="value">{{ $order->created_at->addDays((int) settings('invoice.payment_terms_days', 30))->format('d/m/Y') }}</span></div>
        </div>
    </div>

    <div class="section">
        <div class="two-columns">
            <div class="column">
                <div class="section-title">Bill To</div>
                <div><strong>{{ $billingAddress->first_name }} {{ $billingAddress->last_name }}</strong></div>
                @if($billingAddress->company)
                    <div>{{ $billingAddress->company }}</div>
                @endif
                @if($order->is_b2b && $order->vat_number)
                    <div class="mono">VAT: {{ $order->vat_number }}</div>
                @endif
                <div>{{ $billingAddress->address_line_1 }}</div>
                @if($billingAddress->address_line_2)
                    <div>{{ $billingAddress->address_line_2 }}</div>
                @endif
                <div>{{ $billingAddress->city }}, {{ $billingAddress->state }} {{ $billingAddress->postal_code }}</div>
                <div>{{ $billingAddress->country_code }}</div>
                @if($billingAddress->phone)
                    <div>Phone: {{ $billingAddress->phone }}</div>
                @endif
            </div>
            <div class="column">
                <div class="section-title">Ship To</div>
                <div><strong>{{ $shippingAddress->first_name }} {{ $shippingAddress->last_name }}</strong></div>
                @if($shippingAddress->company)
                    <div>{{ $shippingAddress->company }}</div>
                @endif
                <div>{{ $shippingAddress->address_line_1 }}</div>
                @if($shippingAddress->address_line_2)
                    <div>{{ $shippingAddress->address_line_2 }}</div>
                @endif
                <div>{{ $shippingAddress->city }}, {{ $shippingAddress->state }} {{ $shippingAddress->postal_code }}</div>
                <div>{{ $shippingAddress->country_code }}</div>
                @if($shippingAddress->phone)
                    <div>Phone: {{ $shippingAddress->phone }}</div>
                @endif
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Items</div>
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>OEM #</th>
                    <th>Condition</th>
                    <th class="text-right">Quantity</th>
                    <th class="text-right">Unit Price</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                <tr style="{{ $loop->even ? 'background-color: #FBF9F2;' : '' }}">
                    <td>
                        @if($item->product)
                            {{ trans_field($item->product->name) }}
                        @else
                            {{ trim(($item->manufacturer_snapshot ?? '') . ' ' . ($item->oem_number_snapshot ?? '')) }}
                        @endif
                    </td>
                    <td class="mono">{{ $item->oem_number_snapshot ?? '—' }}</td>
                    <td><span class="condition-tag">{{ $item->condition_snapshot ?? '—' }}</span></td>
                    <td class="text-right mono">{{ $item->quantity }}</td>
                    <td class="text-right mono">{{ format_price($item->unit_price) }}</td>
                    <td class="text-right mono">{{ format_price($item->total_price) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @php
        // Matches CheckoutService::createOrder()'s VAT base: the discount
        // is excluded from the taxable amount (EU VAT Directive Art. 79(b)),
        // so vat_amount is (subtotal - discount + shipping + fees) * rate —
        // this must subtract discount_amount too, or the back-computed
        // rate % shown below would come out understated for any
        // coupon-discounted order.
        $vatTaxableBase = bcadd(bcadd(bcadd(bcsub((string) $order->subtotal, (string) $order->discount_amount, 2), (string) $order->shipping_cost, 2), (string) $order->urgent_processing_fee, 2), (string) $order->handling_fee, 2);
        $vatRatePercent = bccomp($vatTaxableBase, '0', 2) > 0
            ? bcmul(bcdiv((string) $order->vat_amount, $vatTaxableBase, 4), '100', 2)
            : '0.00';
    @endphp
    <div class="totals">
        <div class="totals-row">
            <span>Subtotal:</span>
            <span class="value">{{ format_price($order->subtotal) }}</span>
        </div>
        @if($order->shipping_cost > 0)
        <div class="totals-row">
            <span>Shipping:</span>
            <span class="value">{{ format_price($order->shipping_cost) }}</span>
        </div>
        @endif
        @if($order->urgent_processing && bccomp((string) $order->urgent_processing_fee, '0', 2) > 0)
        <div class="totals-row">
            {{-- Invoice document is intentionally English-only throughout
                 (see docs/PREMIUM_GRADE_MASTER_WORKFLOW.md email/invoice chunk) —
                 a fixed label here, not the customer-facing localized one,
                 keeps this line consistent with the rest of the document. --}}
            <span>Rush Processing:</span>
            <span class="value">{{ format_price($order->urgent_processing_fee) }}</span>
        </div>
        @endif
        @if(bccomp((string) $order->handling_fee, '0', 2) > 0)
        <div class="totals-row">
            <span>Handling Fee:</span>
            <span class="value">{{ format_price($order->handling_fee) }}</span>
        </div>
        @endif
        @if($order->discount_amount > 0)
        <div class="totals-row">
            <span>Discount:</span>
            <span class="value">-{{ format_price($order->discount_amount) }}</span>
        </div>
        @endif
        @if($order->vat_exempt)
        <div class="totals-row">
            <span>VAT:</span>
            <span class="value">{{ format_price('0.00') }}</span>
        </div>
        @elseif(isset($order->vat_amount) && bccomp((string) $order->vat_amount, '0', 2) > 0)
        <div class="totals-row">
            <span>VAT ({{ rtrim(rtrim($vatRatePercent, '0'), '.') }}%):</span>
            <span class="value">{{ format_price($order->vat_amount) }}</span>
        </div>
        @endif
        <div class="totals-row total">
            <span>Total:</span>
            <span class="value">{{ format_price($order->grand_total) }}</span>
        </div>
    </div>

    @if($order->vat_exempt)
    <div class="notice-box">
        <strong>Reverse charge</strong> — VAT to be accounted for by the recipient under Article 194/196 of Council Directive 2006/112/EC.
        @if($order->vat_number)
            Buyer VAT ID: <span class="mono">{{ $order->vat_number }}</span>.
        @endif
    </div>
    @endif

    @if($order->payment_status !== \App\Enums\PaymentStatus::Paid)
        @include('pdf.partials.bank-details', ['bank' => $bank ?? null, 'paymentReference' => $order->invoice_number ?? $order->order_number])
    @endif

    <div class="notice-box">
        <strong>Oversized parts — shipping notice.</strong> The shipping cost above is a fixed rate for standard-size parcels. If this order includes an oversized or heavy part, the carrier may apply an additional freight surcharge, which will be invoiced separately after dispatch.
    </div>

    <div class="footer">
        <div>{{ settings_trans('invoice.thank_you_text', 'Thank you for your business!') }}</div>
        <div>If you have any questions about this invoice, please contact {{ $settings['company_email'] }}</div>
        <div class="mono">Invoice generated on {{ now()->format('d/m/Y H:i') }}</div>
    </div>
</body>
</html>
