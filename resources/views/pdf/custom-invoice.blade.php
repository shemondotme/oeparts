<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
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
            <div class="meta-row"><span class="label">No.</span><span class="value">{{ $invoice->invoice_number }}</span></div>
            <div class="meta-row"><span class="label">Date</span><span class="value">{{ $invoice->issue_date->format('d/m/Y') }}</span></div>
            <div class="meta-row"><span class="label">Due</span><span class="value">{{ $invoice->due_date->format('d/m/Y') }}</span></div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Bill To</div>
        <div><strong>{{ $invoice->client_name }}</strong></div>
        @if($invoice->client_company)
            <div>{{ $invoice->client_company }}</div>
        @endif
        @if($invoice->client_vat_number)
            <div class="mono">VAT: {{ $invoice->client_vat_number }}</div>
        @endif
        <div>{{ $invoice->client_address_line1 }}</div>
        @if($invoice->client_address_line2)
            <div>{{ $invoice->client_address_line2 }}</div>
        @endif
        <div>{{ trim($invoice->client_postal_code.' '.$invoice->client_city) }}</div>
        <div>{{ $invoice->client_country_code }}</div>
        @if($invoice->client_email)
            <div>Email: {{ $invoice->client_email }}</div>
        @endif
        @if($invoice->client_phone)
            <div>Phone: {{ $invoice->client_phone }}</div>
        @endif
    </div>

    <div class="section">
        <div class="section-title">Items</div>
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-right">Quantity</th>
                    <th class="text-right">Unit Price</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                <tr style="{{ $loop->even ? 'background-color: #FBF9F2;' : '' }}">
                    <td>{!! nl2br(e($item['description'])) !!}</td>
                    <td class="text-right mono">{{ rtrim(rtrim($item['quantity'], '0'), '.') }}</td>
                    <td class="text-right mono">{{ format_price($item['unit_price'], $invoice->currency, 'en') }}</td>
                    <td class="text-right mono">{{ format_price($item['line_total'], $invoice->currency, 'en') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="totals">
        <div class="totals-row">
            <span>Subtotal:</span>
            <span class="value">{{ format_price($invoice->subtotal, $invoice->currency, 'en') }}</span>
        </div>
        @if(bccomp((string) $invoice->discount_amount, '0', 2) > 0)
        <div class="totals-row">
            <span>Discount:</span>
            <span class="value">-{{ format_price($invoice->discount_amount, $invoice->currency, 'en') }}</span>
        </div>
        @endif
        @if($invoice->reverse_charge)
        <div class="totals-row">
            <span>VAT:</span>
            <span class="value">{{ format_price('0.00', $invoice->currency, 'en') }}</span>
        </div>
        @elseif(bccomp((string) $invoice->vat_rate, '0', 2) > 0)
        <div class="totals-row">
            <span>VAT ({{ rtrim(rtrim((string) $invoice->vat_rate, '0'), '.') }}%):</span>
            <span class="value">{{ format_price($invoice->vat_amount, $invoice->currency, 'en') }}</span>
        </div>
        @endif
        <div class="totals-row total">
            <span>Total:</span>
            <span class="value">{{ format_price($invoice->total, $invoice->currency, 'en') }}</span>
        </div>
    </div>

    @if($invoice->reverse_charge)
    <div class="notice-box">
        <strong>Reverse charge</strong> — VAT to be accounted for by the recipient under Article 194/196 of Council Directive 2006/112/EC.
        @if($invoice->client_vat_number)
            Buyer VAT ID: <span class="mono">{{ $invoice->client_vat_number }}</span>.
        @endif
    </div>
    @endif

    @if($invoice->status !== \App\Enums\CustomInvoiceStatus::Paid)
        @include('pdf.partials.bank-details', ['bank' => $bank ?? null, 'paymentReference' => $invoice->invoice_number])
    @endif

    @if($invoice->notes)
    <div class="notice-box">
        {!! nl2br(e($invoice->notes)) !!}
    </div>
    @endif

    <div class="footer">
        <div>{{ settings_trans('invoice.thank_you_text', 'Thank you for your business!') }}</div>
        <div>If you have any questions about this invoice, please contact {{ $settings['company_email'] }}</div>
        <div class="mono">Invoice generated on {{ now()->format('d/m/Y H:i') }}</div>
    </div>
</body>
</html>
