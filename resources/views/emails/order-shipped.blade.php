@extends('emails.layout')

@section('content')
    {{-- ══════════════════════════════════════════════════════════════════════
         ORDER SHIPPED — INDUSTRIAL BLUEPRINT SHIPPING MANIFEST
         Focus: Tracking number, carrier, estimated delivery.
         ══════════════════════════════════════════════════════════════════ --}}

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">

        {{-- ═══ DOC HEADER: Shipping Notification ═══ --}}
        <tr>
            <td style="padding-bottom: 24px; border-bottom: 1px solid #D8CFB6;">
                <p class="spec-label" style="margin: 0 0 8px 0; color: #9A5A00;">
                    LOGISTICS · DISPATCHED
                </p>
                <h2 class="font-display" style="margin: 0; font-size: 24px; line-height: 32px; color: #0A1228;">
                    Your order is on the way<span class="text-amber">.</span>
                </h2>
                <p style="margin: 12px 0 0 0; font-size: 15px; line-height: 22px; color: #4E5A74;">
                    {{ trans('emails.order_shipped.greeting', ['name' => $order->shipping_name], $locale) }}
                    <br>
                    {{ trans('emails.order_shipped.body', ['order_number' => $order->order_number], $locale) }}
                </p>
            </td>
        </tr>

        {{-- ═══ TRACKING INFO CARD ═══ --}}
        <tr>
            <td style="padding: 24px 0;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border: 1px solid #D8CFB6; background-color: #F7F3E7;">
                    <tr>
                        <td style="padding: 20px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                {{-- Carrier --}}
                                <tr>
                                    <td style="padding-bottom: 12px;">
                                        <span class="spec-label" style="color: #4E5A74;">CARRIER</span>
                                    </td>
                                    <td align="right" style="padding-bottom: 12px;">
                                        <span class="font-mono" style="font-size: 14px; color: #0A1228; font-weight: bold;">
                                            {{ $order->carrier_name ?? 'Standard Courier' }}
                                        </span>
                                    </td>
                                </tr>
                                {{-- Tracking Number --}}
                                <tr>
                                    <td colspan="2" style="padding-bottom: 12px; border-bottom: 1px dashed #D8CFB6;">
                                        <span class="spec-label" style="color: #4E5A74; display: block; margin-bottom: 4px;">TRACKING NUMBER</span>
                                        <span class="font-mono" style="font-size: 18px; color: #0A1228; font-weight: bold; letter-spacing: 1px;">
                                            {{ $order->tracking_number ?? 'N/A' }}
                                        </span>
                                    </td>
                                </tr>
                                {{-- Estimated Delivery --}}
                                <tr>
                                    <td style="padding-top: 12px;">
                                        <span class="spec-label" style="color: #4E5A74;">EST. DELIVERY</span>
                                    </td>
                                    <td align="right" style="padding-top: 12px;">
                                        <span class="font-mono" style="font-size: 14px; color: #0A1228; font-weight: bold;">
                                            @if($order->shipping_estimated_days_min && $order->shipping_estimated_days_max)
                                                {{ $order->shipping_estimated_days_min }}–{{ $order->shipping_estimated_days_max }} Days
                                            @else
                                                Pending
                                            @endif
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- ═══ ORDER SUMMARY (Compact) ═══ --}}
        <tr>
            <td style="padding-bottom: 24px;">
                <p class="spec-label" style="margin: 0 0 12px 0; color: #9A5A00;">
                    PACKAGE CONTENTS
                </p>
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border: 1px solid #D8CFB6; border-collapse: collapse;">
                    <thead>
                        <tr style="background-color: #EFE9D6; border-bottom: 1px solid #D8CFB6;">
                            <th align="left" style="padding: 10px; font-family: 'Courier New', Courier, monospace; font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: #4E5A74; font-weight: bold;">
                                ITEM
                            </th>
                            <th align="center" style="padding: 10px; font-family: 'Courier New', Courier, monospace; font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: #4E5A74; font-weight: bold;">
                                QTY
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->items as $item)
                            <tr style="border-bottom: 1px solid #D8CFB6;">
                                <td style="padding: 10px; font-size: 13px; color: #0A1228;">
                                    {{ $item->product ? trans_field($item->product->name) : $item->oem_number_snapshot }}
                                    <br>
                                    <span class="font-mono" style="font-size: 11px; color: #4E5A74;">{{ $item->oem_number_snapshot }}</span>
                                </td>
                                <td align="center" style="padding: 10px; font-size: 13px; color: #0A1228;">
                                    <span class="font-mono">{{ $item->quantity }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
        </tr>

        {{-- ═══ CTA BUTTON ═══ --}}
        <tr>
            <td align="center" style="padding: 24px 0; border-top: 1px solid #D8CFB6;">
                <p style="margin: 0 0 20px 0; font-size: 14px; line-height: 20px; color: #4E5A74;">
                    Track your package in real-time.
                </p>
                @if($order->tracking_url)
                    <a href="{{ $order->tracking_url }}"
                       class="btn-primary"
                       style="display: inline-block; padding: 14px 28px; background-color: #0A1228; color: #F7F3E7 !important; text-decoration: none; font-family: 'Courier New', Courier, monospace; font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.18em; border: 1px solid #0A1228;">
                        TRACK PACKAGE →
                    </a>
                @else
                    <a href="{{ route('frontend.account.order.detail', ['lang' => $locale, 'order' => $order->id]) }}"
                       class="btn-primary"
                       style="display: inline-block; padding: 14px 28px; background-color: #0A1228; color: #F7F3E7 !important; text-decoration: none; font-family: 'Courier New', Courier, monospace; font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.18em; border: 1px solid #0A1228;">
                        VIEW ORDER STATUS →
                    </a>
                @endif
            </td>
        </tr>

    </table>
@endsection
