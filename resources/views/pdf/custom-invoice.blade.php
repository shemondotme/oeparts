<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    @include('pdf.partials.invoice-styles')
</head>
<body>
    @php
        $hasPartNumbers = collect($items)->contains(fn ($i) => filled($i['part_number']));
        $hasLineDiscount = collect($items)->contains(fn ($i) => bccomp($i['discount_percent'], '0', 2) > 0);
        $breakdown = $invoice->breakdownRows();
        $hasMixedRates = count($breakdown) > 1;
        $treatment = $invoice->effectiveTreatment();
        $standardVat = $treatment === \App\Enums\InvoiceVatTreatment::Standard;
        $fmt = fn ($n) => format_price($n, $invoice->currency, 'en');
        $trim = fn ($n) => rtrim(rtrim((string) $n, '0'), '.');
    @endphp
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
            @if($invoice->supply_date)
                <div class="meta-row"><span class="label">Supply date</span><span class="value">{{ $invoice->supply_date->format('d/m/Y') }}</span></div>
            @endif
            <div class="meta-row"><span class="label">Due</span><span class="value">{{ $invoice->due_date->format('d/m/Y') }}</span></div>
            @if($invoice->po_number)
                <div class="meta-row"><span class="label">Your ref. / PO</span><span class="value">{{ $invoice->po_number }}</span></div>
            @endif
            @if($invoice->delivery_terms)
                <div class="meta-row"><span class="label">Delivery</span><span class="value">{{ $invoice->delivery_terms }}</span></div>
            @endif
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
                    @if($hasPartNumbers)<th>Part no.</th>@endif
                    <th>Description</th>
                    <th class="text-right">Quantity</th>
                    <th class="text-right">Unit Price</th>
                    @if($hasLineDiscount)<th class="text-right">Disc.</th>@endif
                    @if($hasMixedRates)<th class="text-right">VAT</th>@endif
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                <tr style="{{ $loop->even ? 'background-color: #FBF9F2;' : '' }}">
                    @if($hasPartNumbers)<td class="mono">{{ $item['part_number'] }}</td>@endif
                    <td>{!! nl2br(e($item['description'])) !!}</td>
                    <td class="text-right mono">{{ $trim($item['quantity']) }}@if(filled($item['unit'])) {{ $item['unit'] }}@endif</td>
                    <td class="text-right mono">{{ $fmt($item['unit_price']) }}</td>
                    @if($hasLineDiscount)<td class="text-right mono">{{ bccomp($item['discount_percent'], '0', 2) > 0 ? '-'.$trim($item['discount_percent']).'%' : '' }}</td>@endif
                    @if($hasMixedRates)<td class="text-right mono">{{ $trim($item['vat_rate']) }}%</td>@endif
                    <td class="text-right mono">{{ $fmt($item['line_total']) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="totals">
        <div class="totals-row">
            <span>Subtotal:</span>
            <span class="value">{{ $fmt($invoice->subtotal) }}</span>
        </div>
        @if(bccomp((string) $invoice->discount_amount, '0', 2) > 0)
        <div class="totals-row">
            <span>Discount{{ $invoice->discount_type === 'percent' ? ' ('.$trim($invoice->discount_percent).'%)' : '' }}:</span>
            <span class="value">-{{ $fmt($invoice->discount_amount) }}</span>
        </div>
        @endif
        @if(! $standardVat)
        <div class="totals-row">
            <span>VAT:</span>
            <span class="value">{{ $fmt('0.00') }}</span>
        </div>
        @elseif($hasMixedRates)
            @foreach($breakdown as $row)
            <div class="totals-row">
                <span>VAT ({{ $trim($row['rate']) }}% of {{ $fmt($row['base']) }}):</span>
                <span class="value">{{ $fmt($row['vat']) }}</span>
            </div>
            @endforeach
        @elseif(!empty($breakdown) && bccomp($breakdown[0]['rate'], '0', 2) > 0)
        <div class="totals-row">
            <span>VAT ({{ $trim($breakdown[0]['rate']) }}%):</span>
            <span class="value">{{ $fmt($invoice->vat_amount) }}</span>
        </div>
        @endif
        <div class="totals-row total">
            <span>Total:</span>
            <span class="value">{{ $fmt($invoice->total) }}</span>
        </div>
    </div>

    @if($invoice->vatNotice())
    <div class="notice-box">
        <strong>{{ $treatment === \App\Enums\InvoiceVatTreatment::ReverseCharge ? 'Reverse charge' : 'VAT' }}</strong> — {{ $invoice->vatNotice() }}
        @if($invoice->client_vat_number && in_array($treatment, [\App\Enums\InvoiceVatTreatment::ReverseCharge, \App\Enums\InvoiceVatTreatment::IntraEu], true))
            Buyer VAT ID: <span class="mono">{{ $invoice->client_vat_number }}</span>.
        @endif
    </div>
    @endif

    @include('pdf.partials.payment-section')

    @if($invoice->notes)
    <div class="notice-box">
        {!! nl2br(e($invoice->notes)) !!}
    </div>
    @endif

    @if($invoice->terms_text)
    <div class="section" style="margin-top: 14px;">
        <div class="section-title">Terms &amp; Conditions</div>
        <div style="font-size: 10px; color: #4B5563;">{!! nl2br(e($invoice->terms_text)) !!}</div>
    </div>
    @endif

    <div class="footer">
        <div>{{ settings_trans('invoice.thank_you_text', 'Thank you for your business!') }}</div>
        <div>If you have any questions about this invoice, please contact {{ $settings['company_email'] }}</div>
        <div class="mono">Invoice generated on {{ now()->format('d/m/Y H:i') }}</div>
    </div>
</body>
</html>
