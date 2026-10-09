<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $t('name_'.$documentType->value) }} {{ $invoice->invoice_number }}</title>
    @include('pdf.partials.invoice-styles')
</head>
<body>
    @php
        $hasPartNumbers = collect($items)->contains(fn ($i) => filled($i['part_number']));
        $hasLeadTimes = collect($items)->contains(fn ($i) => filled($i['lead_time']));
        $hasLineDiscount = collect($items)->contains(fn ($i) => bccomp($i['discount_percent'], '0', 2) > 0);
        $breakdown = $invoice->breakdownRows();
        $hasMixedRates = count($breakdown) > 1;
        $treatment = $invoice->effectiveTreatment();
        $standardVat = $treatment === \App\Enums\InvoiceVatTreatment::Standard;
        $isCredit = $documentType->isCredit();
        // Credit notes show their amounts as negatives; a zero stays unsigned.
        $fmt = fn ($n, $flip = true) => ($isCredit && $flip && bccomp((string) $n, '0', 2) !== 0 ? '-' : '').format_price($n, $invoice->currency, $locale);
        $trim = fn ($n) => rtrim(rtrim((string) $n, '0'), '.');
        $dueLabelKey = match ($documentType) {
            \App\Enums\InvoiceDocumentType::Quote => 'valid_until',
            \App\Enums\InvoiceDocumentType::Proforma => 'pay_before',
            default => 'due',
        };
    @endphp
    <div class="header">
        <div class="company-info">
            @php [$wordmarkHeavy, $wordmarkLight] = brand_wordmark_parts($settings['company_name']); @endphp
            <h1><span class="wordmark-heavy">{{ $wordmarkHeavy }}</span><span class="wordmark-light">{{ $wordmarkLight }}</span><span class="wordmark-dot">.</span></h1>
            <div>{{ $settings['company_address'] }}</div>
            <div class="mono">{{ $t('vat_id') }}: {{ $settings['company_vat'] }}</div>
            @if(!empty($settings['company_registration']))
                <div class="mono">{{ $t('reg_no') }}: {{ $settings['company_registration'] }}</div>
            @endif
            <div>{{ $t('email') }}: {{ $settings['company_email'] }}</div>
            <div>{{ $t('phone') }}: {{ $settings['company_phone'] }}</div>
        </div>
        <div class="invoice-info">
            <p class="doc-eyebrow">OEPARTS · {{ $t('title_'.$documentType->value) }}</p>
            <h2>{{ $t('title_'.$documentType->value) }}</h2>
            <div class="meta-row"><span class="label">{{ $t('number') }}</span><span class="value">{{ $invoice->invoice_number }}</span></div>
            <div class="meta-row"><span class="label">{{ $t('date') }}</span><span class="value">{{ $invoice->issue_date->format('d/m/Y') }}</span></div>
            @if($invoice->supply_date)
                <div class="meta-row"><span class="label">{{ $t('supply_date') }}</span><span class="value">{{ $invoice->supply_date->format('d/m/Y') }}</span></div>
            @endif
            @if($documentType !== \App\Enums\InvoiceDocumentType::CreditNote)
                <div class="meta-row"><span class="label">{{ $t($dueLabelKey) }}</span><span class="value">{{ $invoice->due_date->format('d/m/Y') }}</span></div>
            @endif
            @if($invoice->parent)
                <div class="meta-row"><span class="label">{{ $t($isCredit ? 'credit_for' : 'ref') }}</span><span class="value">{{ $invoice->parent->invoice_number }}</span></div>
            @endif
            @if($invoice->po_number)
                <div class="meta-row"><span class="label">{{ $t('your_ref') }}</span><span class="value">{{ $invoice->po_number }}</span></div>
            @endif
            @if($invoice->delivery_terms)
                <div class="meta-row"><span class="label">{{ $t('delivery') }}</span><span class="value">{{ $invoice->delivery_terms }}</span></div>
            @endif
        </div>
    </div>

    <div class="section">
        <div class="section-title">{{ $t($documentType === \App\Enums\InvoiceDocumentType::Quote ? 'prepared_for' : 'bill_to') }}</div>
        <div><strong>{{ $invoice->client_name }}</strong></div>
        @if($invoice->client_company)
            <div>{{ $invoice->client_company }}</div>
        @endif
        @if($invoice->client_vat_number)
            <div class="mono">{{ $t('vat_id') }}: {{ $invoice->client_vat_number }}</div>
        @endif
        <div>{{ $invoice->client_address_line1 }}</div>
        @if($invoice->client_address_line2)
            <div>{{ $invoice->client_address_line2 }}</div>
        @endif
        <div>{{ trim($invoice->client_postal_code.' '.$invoice->client_city) }}</div>
        @if($invoice->client_state)
            <div>{{ $invoice->client_state }}</div>
        @endif
        <div>{{ $invoice->client_country_code }}</div>
        @if($invoice->client_email)
            <div>{{ $t('email') }}: {{ $invoice->client_email }}</div>
        @endif
        @if($invoice->client_phone)
            <div>{{ $t('phone') }}: {{ $invoice->client_phone }}</div>
        @endif
    </div>

    <div class="section">
        <div class="section-title">{{ $t('items') }}</div>
        <table>
            <thead>
                <tr>
                    @if($hasPartNumbers)<th>{{ $t('part_no') }}</th>@endif
                    <th>{{ $t('description') }}</th>
                    @if($hasLeadTimes)<th>{{ $t('availability') }}</th>@endif
                    <th class="text-right">{{ $t('quantity') }}</th>
                    <th class="text-right">{{ $t('unit_price') }}</th>
                    @if($hasLineDiscount)<th class="text-right">{{ $t('discount_short') }}</th>@endif
                    @if($hasMixedRates)<th class="text-right">{{ $t('vat_short') }}</th>@endif
                    <th class="text-right">{{ $t('total_col') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                <tr style="{{ $loop->even ? 'background-color: #FBF9F2;' : '' }}">
                    @if($hasPartNumbers)<td class="mono">{{ $item['part_number'] }}</td>@endif
                    <td>{!! nl2br(e($item['description'])) !!}</td>
                    @if($hasLeadTimes)<td>{{ $item['lead_time'] }}</td>@endif
                    <td class="text-right mono">{{ $trim($item['quantity']) }}@if(filled($item['unit'])) {{ $item['unit'] }}@endif</td>
                    <td class="text-right mono">{{ $fmt($item['unit_price'], false) }}</td>
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
            <span>{{ $t('subtotal') }}:</span>
            <span class="value">{{ $fmt($invoice->subtotal) }}</span>
        </div>
        @if(bccomp((string) $invoice->discount_amount, '0', 2) > 0)
        <div class="totals-row">
            <span>{{ $t('discount') }}{{ $invoice->discount_type === 'percent' ? ' ('.$trim($invoice->discount_percent).'%)' : '' }}:</span>
            <span class="value">{{ $isCredit ? '+' : '-' }}{{ $fmt($invoice->discount_amount, false) }}</span>
        </div>
        @endif
        @if(! $standardVat)
        <div class="totals-row">
            <span>{{ $t('vat') }}:</span>
            <span class="value">{{ $fmt('0.00') }}</span>
        </div>
        @elseif($hasMixedRates)
            @foreach($breakdown as $row)
            <div class="totals-row">
                <span>{{ $t('vat_of', ['rate' => $trim($row['rate']), 'base' => $fmt($row['base'], false)]) }}:</span>
                <span class="value">{{ $fmt($row['vat']) }}</span>
            </div>
            @endforeach
        @elseif(!empty($breakdown) && bccomp($breakdown[0]['rate'], '0', 2) > 0)
        <div class="totals-row">
            <span>{{ $t('vat_rate', ['rate' => $trim($breakdown[0]['rate'])]) }}:</span>
            <span class="value">{{ $fmt($invoice->vat_amount) }}</span>
        </div>
        @endif
        <div class="totals-row total">
            <span>{{ $t('total') }}:</span>
            <span class="value">{{ $fmt($invoice->total) }}</span>
        </div>
    </div>

    @if($invoice->vatNotice())
    <div class="notice-box">
        <strong>{{ $treatment === \App\Enums\InvoiceVatTreatment::ReverseCharge ? $t('reverse_charge') : $t('vat') }}</strong> — {{ $invoice->vatNotice() }}
        @if($invoice->client_vat_number && in_array($treatment, [\App\Enums\InvoiceVatTreatment::ReverseCharge, \App\Enums\InvoiceVatTreatment::IntraEu], true))
            {{ $t('buyer_vat_id') }}: <span class="mono">{{ $invoice->client_vat_number }}</span>.
        @endif
    </div>
    @endif

    @if($documentType->disclaimer())
    <div class="notice-box">{{ $t('disclaimer_'.$documentType->value) }}</div>
    @endif

    @if($documentType->requestsPayment())
        @include('pdf.partials.payment-section', ['docLocale' => $locale])
    @endif

    @if($invoice->notes)
    <div class="notice-box">
        {!! nl2br(e($invoice->notes)) !!}
    </div>
    @endif

    @if($invoice->terms_text)
    <div class="section" style="margin-top: 14px;">
        <div class="section-title">{{ $t('terms') }}</div>
        <div style="font-size: 10px; color: #4B5563;">{!! nl2br(e($invoice->terms_text)) !!}</div>
    </div>
    @endif

    <div class="footer">
        <div>{{ settings_trans('invoice.thank_you_text', 'Thank you for your business!') }}</div>
        <div>{{ $t('questions', ['email' => $settings['company_email']]) }}</div>
        <div class="mono">{{ $t('generated_on') }} {{ now()->format('d/m/Y H:i') }}</div>
    </div>
</body>
</html>
