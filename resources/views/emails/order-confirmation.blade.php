@extends('emails.layout')

@section('content')
    {{-- ══════════════════════════════════════════════════════════════════════
         ORDER CONFIRMATION — INDUSTRIAL BLUEPRINT RECEIPT
         Dense, tabular, spec-sheet aesthetic.
         ══════════════════════════════════════════════════════════════════ --}}

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">

        {{-- ═══ DOC HEADER: Order Summary ═══ --}}
        <tr>
            <td style="padding-bottom: 24px; border-bottom: 1px solid #D8CFB6;">
                <p class="spec-label" style="margin: 0 0 8px 0; color: #9A5A00;">
                    ORDER · CONFIRMED
                </p>
                <h2 class="font-display" style="margin: 0; font-size: 24px; line-height: 32px; color: #0A1228;">
                    {{ trans('emails.order_confirmation.headline', [], $locale) }}<span class="text-amber">.</span>
                </h2>
                <p style="margin: 12px 0 0 0; font-size: 15px; line-height: 22px; color: #4E5A74;">
                    {{ trans('emails.order_confirmation.greeting', ['name' => $order->shipping_name], $locale) }}
                    <br>
                    {{ trans('emails.order_confirmation.body', ['order_number' => $order->order_number], $locale) }}
                </p>
            </td>
        </tr>

        {{-- ═══ SPEC LEDGER: Key Details ═══ --}}
        <tr>
            <td style="padding: 24px 0;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border: 1px solid #D8CFB6; background-color: #F7F3E7;">
                    <tr>
                        <td style="padding: 16px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                {{-- Order Number --}}
                                <tr>
                                    <td style="padding-bottom: 8px;">
                                        <span class="spec-label" style="color: #4E5A74;">ORDER NO.</span>
                                    </td>
                                    <td align="right" style="padding-bottom: 8px;">
                                        <span class="font-mono" style="font-size: 14px; color: #0A1228; font-weight: bold;">
                                            {{ $order->order_number }}
                                        </span>
                                    </td>
                                </tr>
                                {{-- Date --}}
                                <tr>
                                    <td style="padding-bottom: 8px;">
                                        <span class="spec-label" style="color: #4E5A74;">DATE</span>
                                    </td>
                                    <td align="right" style="padding-bottom: 8px;">
                                        <span class="font-mono" style="font-size: 14px; color: #0A1228;">
                                            {{ $order->created_at->format('d M Y') }}
                                        </span>
                                    </td>
                                </tr>
                                {{-- Shipping Method --}}
                                <tr>
                                    <td style="padding-bottom: 8px;">
                                        <span class="spec-label" style="color: #4E5A74;">SHIPPING</span>
                                    </td>
                                    <td align="right" style="padding-bottom: 8px;">
                                        <span class="font-mono" style="font-size: 14px; color: #0A1228;">
                                            {{ $order->shipping_method_name_snapshot }}
                                        </span>
                                    </td>
                                </tr>
                                {{-- Estimated Delivery --}}
                                <tr>
                                    <td style="padding-top: 8px; border-top: 1px dashed #D8CFB6;">
                                        <span class="spec-label" style="color: #4E5A74;">EST. DELIVERY</span>
                                    </td>
                                    <td align="right" style="padding-top: 8px; border-top: 1px dashed #D8CFB6;">
                                        <span class="font-mono" style="font-size: 14px; color: #0A1228; font-weight: bold;">
                                            {{ $order->shipping_estimated_days_min }}–{{ $order->shipping_estimated_days_max }} Days
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- ═══ SHIPPING ADDRESS ═══ --}}
        <tr>
            <td style="padding-bottom: 24px;">
                <p class="spec-label" style="margin: 0 0 8px 0; color: #9A5A00;">
                    DELIVERING TO
                </p>
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border: 1px solid #D8CFB6; background-color: #FFFFFF;">
                    <tr>
                        <td style="padding: 16px;">
                            <p style="margin: 0; font-size: 14px; line-height: 20px; color: #0A1228;">
                                <strong>{{ $order->shipping_name }}</strong><br>
                                {{ $order->shipping_address_line1 }}<br>
                                @if($order->shipping_address_line2)
                                    {{ $order->shipping_address_line2 }}<br>
                                @endif
                                {{ $order->shipping_postal_code }} {{ $order->shipping_city }}<br>
                                {{ $order->shipping_country_code }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- ═══ ORDER ITEMS MANIFEST ═══ --}}
        <tr>
            <td style="padding-bottom: 24px;">
                <p class="spec-label" style="margin: 0 0 12px 0; color: #9A5A00;">
                    ITEM MANIFEST
                </p>

                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border: 1px solid #D8CFB6; border-collapse: collapse;">
                    {{-- Table Header --}}
                    <thead>
                        <tr style="background-color: #EFE9D6; border-bottom: 1px solid #D8CFB6;">
                            <th align="left" style="padding: 12px; font-family: 'Courier New', Courier, monospace; font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: #4E5A74; font-weight: bold;">
                                OEM / PRODUCT
                            </th>
                            <th align="center" style="padding: 12px; font-family: 'Courier New', Courier, monospace; font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: #4E5A74; font-weight: bold;">
                                QTY
                            </th>
                            <th align="right" style="padding: 12px; font-family: 'Courier New', Courier, monospace; font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: #4E5A74; font-weight: bold;">
                                PRICE
                            </th>
                            <th align="right" style="padding: 12px; font-family: 'Courier New', Courier, monospace; font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: #4E5A74; font-weight: bold;">
                                TOTAL
                            </th>
                        </tr>
                    </thead>

                    {{-- Table Body --}}
                    <tbody>
                        @foreach($order->items as $item)
                            <tr style="border-bottom: 1px solid #D8CFB6;">
                                <td style="padding: 12px; font-size: 14px; color: #0A1228; vertical-align: top;">
                                    <strong style="display: block; margin-bottom: 4px;">
                                        {{ $item->product ? trans_field($item->product->name) : $item->oem_number_snapshot }}
                                    </strong>
                                    <span class="font-mono" style="font-size: 12px; color: #4E5A74;">
                                        {{ $item->oem_number_snapshot }}
                                    </span>
                                </td>
                                <td align="center" style="padding: 12px; font-size: 14px; color: #0A1228; vertical-align: top;">
                                    <span class="font-mono">{{ $item->quantity }}</span>
                                </td>
                                <td align="right" style="padding: 12px; font-size: 14px; color: #0A1228; vertical-align: top;">
                                    <span class="font-mono">{{ number_format($item->unit_price, 2) }} €</span>
                                </td>
                                <td align="right" style="padding: 12px; font-size: 14px; color: #0A1228; vertical-align: top;">
                                    <span class="font-mono" style="font-weight: bold;">{{ number_format($item->total_price, 2) }} €</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
        </tr>

        {{-- ═══ FINANCIAL SUMMARY ═══ --}}
        <tr>
            <td style="padding-bottom: 32px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                    <tr>
                        <td width="50%"></td>
                        <td width="50%">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                {{-- Subtotal --}}
                                <tr>
                                    <td style="padding: 6px 0; border-bottom: 1px dotted #D8CFB6;">
                                        <span style="font-size: 14px; color: #4E5A74;">Subtotal</span>
                                    </td>
                                    <td align="right" style="padding: 6px 0; border-bottom: 1px dotted #D8CFB6;">
                                        <span class="font-mono" style="font-size: 14px; color: #0A1228;">{{ number_format($order->subtotal, 2) }} €</span>
                                    </td>
                                </tr>

                                {{-- Discount (if any) --}}
                                @if($order->discount_amount > 0)
                                    <tr>
                                        <td style="padding: 6px 0; border-bottom: 1px dotted #D8CFB6;">
                                            <span style="font-size: 14px; color: #4E5A74;">Discount</span>
                                        </td>
                                        <td align="right" style="padding: 6px 0; border-bottom: 1px dotted #D8CFB6;">
                                            <span class="font-mono" style="font-size: 14px; color: #DC2626;">-{{ number_format($order->discount_amount, 2) }} €</span>
                                        </td>
                                    </tr>
                                @endif

                                {{-- Shipping --}}
                                <tr>
                                    <td style="padding: 6px 0; border-bottom: 1px dotted #D8CFB6;">
                                        <span style="font-size: 14px; color: #4E5A74;">Shipping</span>
                                    </td>
                                    <td align="right" style="padding: 6px 0; border-bottom: 1px dotted #D8CFB6;">
                                        <span class="font-mono" style="font-size: 14px; color: #0A1228;">{{ number_format($order->shipping_cost, 2) }} €</span>
                                    </td>
                                </tr>

                                @if($order->urgent_processing && bccomp((string) $order->urgent_processing_fee, '0', 2) > 0)
                                {{-- Rush processing --}}
                                <tr>
                                    <td style="padding: 6px 0; border-bottom: 1px dotted #D8CFB6;">
                                        <span style="font-size: 14px; color: #9A5A00;">{{ settings_trans('checkout.urgent_processing_label', 'Rush processing') }}</span>
                                    </td>
                                    <td align="right" style="padding: 6px 0; border-bottom: 1px dotted #D8CFB6;">
                                        <span class="font-mono" style="font-size: 14px; color: #0A1228;">{{ number_format((float) $order->urgent_processing_fee, 2) }} €</span>
                                    </td>
                                </tr>
                                @endif

                                @if(bccomp((string) $order->handling_fee, '0', 2) > 0)
                                <tr>
                                    <td style="padding: 6px 0; border-bottom: 1px dotted #D8CFB6;">
                                        <span style="font-size: 14px; color: #4E5A74;">{{ ui_copy('checkout_handling_fee_label', 'checkout.handling_fee_label') }}</span>
                                    </td>
                                    <td align="right" style="padding: 6px 0; border-bottom: 1px dotted #D8CFB6;">
                                        <span class="font-mono" style="font-size: 14px; color: #0A1228;">{{ number_format((float) $order->handling_fee, 2) }} €</span>
                                    </td>
                                </tr>
                                @endif

                                {{-- VAT --}}
                                <tr>
                                    <td style="padding: 6px 0; border-bottom: 1px dotted #D8CFB6;">
                                        <span style="font-size: 14px; color: #4E5A74;">VAT</span>
                                    </td>
                                    <td align="right" style="padding: 6px 0; border-bottom: 1px dotted #D8CFB6;">
                                        <span class="font-mono" style="font-size: 14px; color: #0A1228;">{{ number_format($order->vat_amount, 2) }} €</span>
                                    </td>
                                </tr>

                                {{-- Grand Total --}}
                                <tr>
                                    <td style="padding: 12px 0;">
                                        <span class="spec-label" style="color: #0A1228;">GRAND TOTAL</span>
                                    </td>
                                    <td align="right" style="padding: 12px 0;">
                                        <span class="font-mono" style="font-size: 18px; color: #0A1228; font-weight: bold;">
                                            {{ number_format($order->grand_total, 2) }} €
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- ═══ OVERSIZED PARTS NOTICE ═══ --}}
        <tr>
            <td style="padding: 0 40px 24px 40px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border: 1px solid #D8CFB6; background-color: #F7F3E7;">
                    <tr>
                        <td style="padding: 16px;">
                            <p class="spec-label" style="margin: 0 0 8px 0; color: #9A5A00;">
                                {{ trans('emails.order_confirmation.oversized_notice_heading', [], $locale) }}
                            </p>
                            <p style="margin: 0; font-size: 13px; line-height: 19px; color: #4E5A74;">
                                {{ trans('emails.order_confirmation.oversized_notice_body', [], $locale) }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- ═══ CTA BUTTON ═══ --}}
        <tr>
            <td align="center" style="padding: 24px 0; border-top: 1px solid #D8CFB6;">
                <p style="margin: 0 0 20px 0; font-size: 14px; line-height: 20px; color: #4E5A74;">
                    {{ trans('emails.order_confirmation.footer', [], $locale) }}
                </p>
                <a href="{{ route('frontend.account.order.detail', ['lang' => $locale, 'order' => $order->id]) }}"
                   class="btn-primary"
                   style="display: inline-block; padding: 14px 28px; background-color: #0A1228; color: #F7F3E7 !important; text-decoration: none; font-family: 'Courier New', Courier, monospace; font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.18em; border: 1px solid #0A1228;">
                    VIEW ORDER DETAILS →
                </a>
            </td>
        </tr>

    </table>
@endsection
