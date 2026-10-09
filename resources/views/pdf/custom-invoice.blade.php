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

        $meta = [
            $t('number') => $invoice->invoice_number,
            $t('date') => $invoice->issue_date->format('d/m/Y'),
            $t('supply_date') => $invoice->supply_date?->format('d/m/Y'),
            $t($dueLabelKey) => $documentType !== \App\Enums\InvoiceDocumentType::CreditNote ? $invoice->due_date->format('d/m/Y') : null,
            $t($isCredit ? 'credit_for' : 'ref') => $invoice->parent?->invoice_number,
            $t('your_ref') => $invoice->po_number,
            $t('delivery') => $invoice->delivery_terms,
        ];

        $parties = [
            [
                'label' => $t('seller'),
                'lines' => [
                    $settings['company_name'],
                    $settings['company_address'],
                    filled($settings['company_vat']) ? $t('vat_id').': '.$settings['company_vat'] : null,
                    filled($settings['company_registration'] ?? null) ? $t('reg_no').': '.$settings['company_registration'] : null,
                    filled($settings['company_email']) ? $t('email').': '.$settings['company_email'] : null,
                    filled($settings['company_phone']) ? $t('phone').': '.$settings['company_phone'] : null,
                ],
            ],
            [
                'label' => $t($documentType === \App\Enums\InvoiceDocumentType::Quote ? 'prepared_for' : 'bill_to'),
                'lines' => [
                    $invoice->client_name,
                    $invoice->client_company,
                    filled($invoice->client_vat_number) ? $t('vat_id').': '.$invoice->client_vat_number : null,
                    $invoice->client_address_line1,
                    $invoice->client_address_line2,
                    trim($invoice->client_postal_code.' '.$invoice->client_city),
                    $invoice->client_state,
                    $invoice->client_country_code,
                    filled($invoice->client_email) ? $t('email').': '.$invoice->client_email : null,
                    filled($invoice->client_phone) ? $t('phone').': '.$invoice->client_phone : null,
                ],
            ],
        ];
    @endphp

    @include('pdf.partials.doc-header', ['docTitle' => $t('title_'.$documentType->value), 'meta' => $meta])

    <div class="content">
        @include('pdf.partials.parties', ['parties' => $parties])

        <table class="items">
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
                <tr>
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

        <table class="summary">
            <tr>
                <td style="width: 56%; padding-right: 24px;">
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
                    @if($invoice->notes)
                    <div class="notice-box">{!! nl2br(e($invoice->notes)) !!}</div>
                    @endif
                </td>
                <td style="width: 44%;">
                    <table class="totals">
                        <tr>
                            <td>{{ $t('subtotal') }}</td>
                            <td class="value">{{ $fmt($invoice->subtotal) }}</td>
                        </tr>
                        @if(bccomp((string) $invoice->discount_amount, '0', 2) > 0)
                        <tr>
                            <td>{{ $t('discount') }}{{ $invoice->discount_type === 'percent' ? ' ('.$trim($invoice->discount_percent).'%)' : '' }}</td>
                            <td class="value">{{ $isCredit ? '+' : '-' }}{{ $fmt($invoice->discount_amount, false) }}</td>
                        </tr>
                        @endif
                        @if(! $standardVat)
                        <tr>
                            <td>{{ $t('vat') }}</td>
                            <td class="value">{{ $fmt('0.00') }}</td>
                        </tr>
                        @elseif($hasMixedRates)
                            @foreach($breakdown as $row)
                            <tr>
                                <td>{{ $t('vat_of', ['rate' => $trim($row['rate']), 'base' => $fmt($row['base'], false)]) }}</td>
                                <td class="value">{{ $fmt($row['vat']) }}</td>
                            </tr>
                            @endforeach
                        @elseif(!empty($breakdown) && bccomp($breakdown[0]['rate'], '0', 2) > 0)
                        <tr>
                            <td>{{ $t('vat_rate', ['rate' => $trim($breakdown[0]['rate'])]) }}</td>
                            <td class="value">{{ $fmt($invoice->vat_amount) }}</td>
                        </tr>
                        @endif
                        <tr class="grand">
                            <td>{{ $t('total') }}</td>
                            <td class="value">{{ $fmt($invoice->total) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        @if($documentType->requestsPayment())
            @include('pdf.partials.payment-section', ['docLocale' => $locale])
        @endif

        @if($invoice->terms_text)
        <div class="section" style="margin-top: 16px;">
            <div class="section-title">{{ $t('terms') }}</div>
            <div style="font-size: 10px; color: #4B5563;">{!! nl2br(e($invoice->terms_text)) !!}</div>
        </div>
        @endif

        <div class="footer">
            <div>{{ settings_trans('invoice.thank_you_text', 'Thank you for your business!') }}</div>
            @if(filled($settings['company_email']))<div>{{ $t('questions', ['email' => $settings['company_email']]) }}</div>@endif
            <div class="mono">{{ $t('generated_on') }} {{ now()->format('d/m/Y H:i') }}</div>
        </div>
    </div>
</body>
</html>
