@extends('emails.layout')

@section('content')
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">

        {{-- ═══ DOC HEADER: Recovery Notice ═══ --}}
        <tr>
            <td style="padding-bottom: 24px; border-bottom: 1px solid #D8CFB6;">
                <p class="spec-label" style="margin: 0 0 8px 0; color: #9A5A00;">
                    CART · PENDING
                </p>
                <h2 class="font-display" style="margin: 0; font-size: 24px; line-height: 32px; color: #0A1228;">
                    Complete your order<span class="text-amber">.</span>
                </h2>
                <p style="margin: 12px 0 0 0; font-size: 15px; line-height: 22px; color: #4E5A74;">
                    {{ trans('emails.abandoned_cart.greeting', ['name' => $customerName], $locale) }}
                    <br>
                    {{ trans('emails.abandoned_cart.body', [], $locale) }}
                </p>
            </td>
        </tr>

        {{-- ═══ URGENCY STRIP ═══ --}}
        <tr>
            <td style="padding: 16px 0;">
                <div style="background-color: #F59E0B; padding: 12px 16px; border-left: 4px solid #0A1228;">
                    <p style="margin: 0; font-family: 'Courier New', Courier, monospace; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.1em; color: #0A1228;">
                        {{ trans('emails.abandoned_cart.urgency_note', [], $locale) }}
                    </p>
                </div>
            </td>
        </tr>

        {{-- ═══ ITEM MANIFEST ═══ --}}
        <tr>
            <td style="padding-bottom: 24px;">
                <p class="spec-label" style="margin: 0 0 12px 0; color: #9A5A00;">
                    ITEMS IN CART
                </p>

                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border: 1px solid #D8CFB6; border-collapse: collapse;">
                    <thead>
                        <tr style="background-color: #EFE9D6; border-bottom: 1px solid #D8CFB6;">
                            <th align="left" style="padding: 12px; font-family: 'Courier New', Courier, monospace; font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: #4E5A74; font-weight: bold;">
                                PRODUCT / OEM
                            </th>
                            <th align="center" style="padding: 12px; font-family: 'Courier New', Courier, monospace; font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: #4E5A74; font-weight: bold;">
                                QTY
                            </th>
                            <th align="right" style="padding: 12px; font-family: 'Courier New', Courier, monospace; font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: #4E5A74; font-weight: bold;">
                                PRICE
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($items as $item)
                            <tr style="border-bottom: 1px solid #D8CFB6;">
                                <td style="padding: 12px; font-size: 14px; color: #0A1228; vertical-align: top;">
                                    <strong style="display: block; margin-bottom: 4px;">
                                        {{ $item['oem_number'] ?? $item['oem_number_snapshot'] ?? 'Part' }}
                                    </strong>
                                    <span class="font-mono" style="font-size: 12px; color: #4E5A74;">
                                        {{ $item['oem_number'] ?? $item['oem_number_snapshot'] ?? '' }}
                                    </span>
                                </td>
                                <td align="center" style="padding: 12px; font-size: 14px; color: #0A1228; vertical-align: top;">
                                    <span class="font-mono">{{ $item['quantity'] }}</span>
                                </td>
                                <td align="right" style="padding: 12px; font-size: 14px; color: #0A1228; vertical-align: top;">
                                    <span class="font-mono" style="font-weight: bold;">{{ number_format((float) ($item['total_price'] ?? $item['price_at_add'] * $item['quantity']), 2) }} €</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
        </tr>

        {{-- ═══ TOTAL SUMMARY ═══ --}}
        <tr>
            <td style="padding-bottom: 32px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                    <tr>
                        <td width="50%"></td>
                        <td width="50%">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="padding: 6px 0; border-top: 2px solid #0A1228;">
                                        <span class="spec-label" style="color: #0A1228;">CART TOTAL</span>
                                    </td>
                                    <td align="right" style="padding: 6px 0; border-top: 2px solid #0A1228;">
                                        <span class="font-mono" style="font-size: 18px; color: #0A1228; font-weight: bold;">
                                            {{ number_format((float) $total, 2) }} €
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- ═══ CTA BUTTON ═══ --}}
        <tr>
            <td align="center" style="padding: 24px 0; border-top: 1px solid #D8CFB6;">
                <p style="margin: 0 0 20px 0; font-size: 14px; line-height: 20px; color: #4E5A74;">
                    {{ trans('emails.abandoned_cart.cta', [], $locale) }}
                </p>
                <a href="{{ route('frontend.cart.index', ['lang' => $locale]) }}"
                   class="btn-primary"
                   style="display: inline-block; padding: 14px 28px; background-color: #0A1228; color: #F7F3E7 !important; text-decoration: none; font-family: 'Courier New', Courier, monospace; font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.18em; border: 1px solid #0A1228;">
                    RETURN TO CART →
                </a>
            </td>
        </tr>

        {{-- ═══ SUPPORT FOOTER NOTE ═══ --}}
        <tr>
            <td style="padding-top: 24px;">
                <p style="margin: 0; font-size: 13px; line-height: 18px; color: #4E5A74; text-align: center;">
                    {{ trans('emails.abandoned_cart.support_note', [], $locale) }}
                </p>
            </td>
        </tr>

    </table>
@endsection
