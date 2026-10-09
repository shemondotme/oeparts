<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $order->order_number }}</title>
    @include('pdf.partials.invoice-styles')
</head>
<body>
    @php
        // Order invoices are intentionally English-only (see docs/PREMIUM_GRADE_MASTER_WORKFLOW.md
        // email/invoice chunk); the labels come from the same file the custom documents use.
        $t = fn (string $key, array $replace = []) => __('invoice_doc.'.$key, $replace, 'en');

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

        $destination = strtoupper((string) ($order->shipping_country_code ?? ''));
        $isZeroRatedExport = ! $order->vat_exempt
            && bccomp((string) ($order->vat_amount ?? '0'), '0', 2) === 0
            && $destination !== ''
            && ! app(\App\Services\ViesService::class)->isEuCountry($destination);

        $addressLines = fn ($a) => [
            trim(($a->first_name ?? '').' '.($a->last_name ?? '')),
            $a->company ?? null,
            $a->address_line_1 ?? null,
            $a->address_line_2 ?? null,
            trim(implode(' ', array_filter([$a->postal_code ?? null, $a->city ?? null]))),
            $a->state ?? null,
            $a->country_code ?? null,
            filled($a->phone ?? null) ? $t('phone').': '.$a->phone : null,
        ];
        $billLines = $addressLines($billingAddress);
        $shipLines = $addressLines($shippingAddress);
        $sameAddress = $billLines === $shipLines;
        if ($order->is_b2b && $order->vat_number) {
            array_splice($billLines, 2, 0, [$t('vat_id').': '.$order->vat_number]);
        }

        $parties = [[
            'label' => $t('seller'),
            'lines' => [
                $settings['company_name'],
                $settings['company_address'],
                filled($settings['company_vat']) ? $t('vat_id').': '.$settings['company_vat'] : null,
                filled($settings['company_registration'] ?? null) ? $t('reg_no').': '.$settings['company_registration'] : null,
                filled($settings['company_email']) ? $t('email').': '.$settings['company_email'] : null,
                filled($settings['company_phone']) ? $t('phone').': '.$settings['company_phone'] : null,
            ],
        ]];
        if ($sameAddress) {
            $parties[] = ['label' => $t('bill_ship_to'), 'lines' => $billLines];
        } else {
            $parties[] = ['label' => $t('bill_to'), 'lines' => $billLines];
            $parties[] = ['label' => $t('ship_to'), 'lines' => $shipLines];
        }

        $meta = [
            $t('number') => $order->invoice_number ?? $order->order_number,
            $t('date') => $order->created_at->format('d/m/Y'),
            $t('due') => $order->created_at->copy()->addDays((int) settings('invoice.payment_terms_days', 30))->format('d/m/Y'),
            'Order' => $order->order_number,
        ];
    @endphp

    @include('pdf.partials.doc-header', ['docTitle' => $t('title_invoice'), 'meta' => $meta])

    <div class="content">
        @include('pdf.partials.parties', ['parties' => $parties])

        <table class="items">
            <thead>
                <tr>
                    <th>{{ $t('description') }}</th>
                    <th>OEM #</th>
                    <th>Condition</th>
                    <th class="text-right">{{ $t('quantity') }}</th>
                    <th class="text-right">{{ $t('unit_price') }}</th>
                    <th class="text-right">{{ $t('total_col') }}</th>
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
                    <td class="mono">{{ $item->oem_number_snapshot ?? '—' }}</td>
                    <td><span class="condition-tag">{{ $item->condition_snapshot ?? '—' }}</span></td>
                    <td class="text-right mono">{{ $item->quantity }}</td>
                    <td class="text-right mono">{{ format_price($item->unit_price) }}</td>
                    <td class="text-right mono">{{ format_price($item->total_price) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <table class="summary">
            <tr>
                <td style="width: 56%; padding-right: 24px;">
                    @if($order->vat_exempt)
                    <div class="notice-box">
                        <strong>{{ $t('reverse_charge') }}</strong> — VAT to be accounted for by the recipient under Article 194/196 of Council Directive 2006/112/EC.
                        @if($order->vat_number)
                            {{ $t('buyer_vat_id') }}: <span class="mono">{{ $order->vat_number }}</span>.
                        @endif
                    </div>
                    @elseif($isZeroRatedExport)
                    <div class="notice-box">
                        <strong>Zero-rated export</strong> — supply to a destination outside the EU, exempt from VAT under Article 146 of Council Directive 2006/112/EC.
                    </div>
                    @endif
                    <div class="notice-box">
                        <strong>Oversized parts — shipping notice.</strong> The shipping cost above is a fixed rate for standard-size parcels. If this order includes an oversized or heavy part, the carrier may apply an additional freight surcharge, which will be invoiced separately after dispatch.
                    </div>
                </td>
                <td style="width: 44%;">
                    <table class="totals">
                        <tr>
                            <td>{{ $t('subtotal') }}</td>
                            <td class="value">{{ format_price($order->subtotal) }}</td>
                        </tr>
                        @if($order->shipping_cost > 0)
                        <tr>
                            <td>Shipping</td>
                            <td class="value">{{ format_price($order->shipping_cost) }}</td>
                        </tr>
                        @endif
                        @if($order->urgent_processing && bccomp((string) $order->urgent_processing_fee, '0', 2) > 0)
                        <tr>
                            <td>Rush Processing</td>
                            <td class="value">{{ format_price($order->urgent_processing_fee) }}</td>
                        </tr>
                        @endif
                        @if(bccomp((string) $order->handling_fee, '0', 2) > 0)
                        <tr>
                            <td>Handling Fee</td>
                            <td class="value">{{ format_price($order->handling_fee) }}</td>
                        </tr>
                        @endif
                        @if($order->discount_amount > 0)
                        <tr>
                            <td>{{ $t('discount') }}</td>
                            <td class="value">-{{ format_price($order->discount_amount) }}</td>
                        </tr>
                        @endif
                        @if($order->vat_exempt || $isZeroRatedExport)
                        <tr>
                            <td>{{ $t('vat') }} 0%</td>
                            <td class="value">{{ format_price('0.00') }}</td>
                        </tr>
                        @elseif(isset($order->vat_amount) && bccomp((string) $order->vat_amount, '0', 2) > 0)
                        <tr>
                            <td>{{ $t('vat_rate', ['rate' => rtrim(rtrim($vatRatePercent, '0'), '.')]) }}</td>
                            <td class="value">{{ format_price($order->vat_amount) }}</td>
                        </tr>
                        @endif
                        <tr class="grand">
                            <td>{{ $t('total') }}</td>
                            <td class="value">{{ format_price($order->grand_total) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        @if($order->payment_status !== \App\Enums\PaymentStatus::Paid)
            @include('pdf.partials.bank-details', ['bank' => $bank ?? null, 'paymentReference' => $order->invoice_number ?? $order->order_number])
        @endif

        <div class="footer">
            <div>{{ settings_trans('invoice.thank_you_text', 'Thank you for your business!') }}</div>
            @if(filled($settings['company_email']))<div>{{ $t('questions', ['email' => $settings['company_email']]) }}</div>@endif
            <div class="mono">{{ $t('generated_on') }} {{ now()->format('d/m/Y H:i') }}</div>
        </div>
    </div>
</body>
</html>
