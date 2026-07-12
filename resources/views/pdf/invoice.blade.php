<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $order->order_number }}</title>
    <style>
        @page {
            margin: 50px;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 40px;
        }
        .company-info h1 {
            font-size: 24px;
            font-weight: bold;
            color: #1e293b;
            margin: 0 0 5px 0;
        }
        .invoice-info {
            text-align: right;
        }
        .invoice-info h2 {
            font-size: 20px;
            font-weight: bold;
            color: #1e293b;
            margin: 0 0 10px 0;
        }
        .section {
            margin-bottom: 30px;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #1e293b;
            border-bottom: 2px solid #1e293b;
            padding-bottom: 5px;
            margin-bottom: 15px;
        }
        .two-columns {
            display: flex;
            justify-content: space-between;
        }
        .column {
            width: 48%;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th {
            background-color: #f1f5f9;
            text-align: left;
            padding: 10px;
            border: 1px solid #cbd5e1;
            font-weight: bold;
        }
        td {
            padding: 10px;
            border: 1px solid #cbd5e1;
        }
        .text-right {
            text-align: right;
        }
        .totals {
            width: 300px;
            margin-left: auto;
            margin-top: 30px;
        }
        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
        }
        .totals-row.total {
            font-weight: bold;
            font-size: 14px;
            border-top: 2px solid #1e293b;
            padding-top: 10px;
            margin-top: 10px;
        }
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #cbd5e1;
            text-align: center;
            font-size: 10px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-info">
            <h1>{{ $settings['company_name'] }}</h1>
            <div>{{ $settings['company_address'] }}</div>
            <div>VAT: {{ $settings['company_vat'] }}</div>
            @if(!empty($settings['company_registration']))
                <div>Reg. No: {{ $settings['company_registration'] }}</div>
            @endif
            <div>Email: {{ $settings['company_email'] }}</div>
            <div>Phone: {{ $settings['company_phone'] }}</div>
        </div>
        <div class="invoice-info">
            <h2>INVOICE</h2>
            <div><strong>Invoice #:</strong> {{ $order->invoice_number ?? $order->order_number }}</div>
            <div><strong>Date:</strong> {{ $order->created_at->format('d/m/Y') }}</div>
            <div><strong>Due Date:</strong> {{ $order->created_at->addDays((int) settings('invoice.payment_terms_days', 30))->format('d/m/Y') }}</div>
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
                    <div>VAT: {{ $order->vat_number }}</div>
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
                <tr>
                    <td>
                        @if($item->product)
                            {{ trans_field($item->product->name) }}
                        @else
                            {{ trim(($item->manufacturer_snapshot ?? '') . ' ' . ($item->oem_number_snapshot ?? '')) }}
                        @endif
                    </td>
                    <td>{{ $item->oem_number_snapshot ?? '—' }}</td>
                    <td>{{ $item->condition_snapshot ?? '—' }}</td>
                    <td class="text-right">{{ $item->quantity }}</td>
                    <td class="text-right">{{ format_price($item->unit_price) }}</td>
                    <td class="text-right">{{ format_price($item->total_price) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @php
        $vatTaxableBase = bcadd(bcadd((string) $order->subtotal, (string) $order->shipping_cost, 2), (string) $order->urgent_processing_fee, 2);
        $vatRatePercent = bccomp($vatTaxableBase, '0', 2) > 0
            ? bcmul(bcdiv((string) $order->vat_amount, $vatTaxableBase, 4), '100', 2)
            : '0.00';
    @endphp
    <div class="totals">
        <div class="totals-row">
            <span>Subtotal:</span>
            <span>{{ format_price($order->subtotal) }}</span>
        </div>
        @if($order->shipping_cost > 0)
        <div class="totals-row">
            <span>Shipping:</span>
            <span>{{ format_price($order->shipping_cost) }}</span>
        </div>
        @endif
        @if($order->urgent_processing && bccomp((string) $order->urgent_processing_fee, '0', 2) > 0)
        <div class="totals-row">
            {{-- Invoice document is intentionally English-only throughout
                 (see PREMIUM_GRADE_MASTER_WORKFLOW.md email/invoice chunk) —
                 a fixed label here, not the customer-facing localized one,
                 keeps this line consistent with the rest of the document. --}}
            <span>Rush Processing:</span>
            <span>{{ format_price($order->urgent_processing_fee) }}</span>
        </div>
        @endif
        @if($order->discount_amount > 0)
        <div class="totals-row">
            <span>Discount:</span>
            <span>-{{ format_price($order->discount_amount) }}</span>
        </div>
        @endif
        @if($order->vat_exempt)
        <div class="totals-row">
            <span>VAT:</span>
            <span>{{ format_price('0.00') }}</span>
        </div>
        @elseif(isset($order->vat_amount) && bccomp((string) $order->vat_amount, '0', 2) > 0)
        <div class="totals-row">
            <span>VAT ({{ rtrim(rtrim($vatRatePercent, '0'), '.') }}%):</span>
            <span>{{ format_price($order->vat_amount) }}</span>
        </div>
        @endif
        <div class="totals-row total">
            <span>Total:</span>
            <span>{{ format_price($order->grand_total) }}</span>
        </div>
    </div>

    @if($order->vat_exempt)
    <div class="section" style="margin-top: 20px; padding: 12px 15px; border: 1px solid #cbd5e1; background-color: #f8fafc;">
        <strong>Reverse charge</strong> — VAT to be accounted for by the recipient under Article 194/196 of Council Directive 2006/112/EC.
        @if($order->vat_number)
            Buyer VAT ID: {{ $order->vat_number }}.
        @endif
    </div>
    @endif

    <div class="footer">
        <div>{{ settings('invoice.thank_you_text', 'Thank you for your business!') }}</div>
        <div>If you have any questions about this invoice, please contact {{ $settings['company_email'] }}</div>
        <div>Invoice generated on {{ now()->format('d/m/Y H:i') }}</div>
    </div>
</body>
</html>