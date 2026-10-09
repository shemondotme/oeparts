@extends('emails.layout')

@section('content')
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td style="padding-bottom: 24px; border-bottom: 1px solid #D8CFB6;">
                <p class="spec-label" style="margin: 0 0 8px 0; color: #9A5A00;">
                    {{ mb_strtoupper(trans('emails.order_invoice.title', [], $locale)) }}
                </p>
                <h2 class="font-display" style="margin: 0; font-size: 24px; line-height: 32px; color: #0A1228;">
                    {{ $order->invoice_number ?: $order->order_number }}<span class="text-amber">.</span>
                </h2>
                <p style="margin: 12px 0 0 0; font-size: 15px; line-height: 22px; color: #4E5A74;">
                    {{ trans('emails.order_invoice.greeting', ['name' => $order->shipping_name], $locale) }}
                    <br>
                    {!! email_text(trans('emails.order_invoice.body', ['order_number' => $order->order_number], $locale)) !!}
                </p>
            </td>
        </tr>

        <tr>
            <td style="padding: 24px 0;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border: 1px solid #D8CFB6; background-color: #F7F3E7;">
                    <tr>
                        <td style="padding: 20px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                @if(filled($order->invoice_number))
                                <tr>
                                    <td style="padding-bottom: 12px;"><span class="spec-label" style="color: #4E5A74;">{{ mb_strtoupper(trans('emails.order_invoice.invoice_number', [], $locale)) }}</span></td>
                                    <td align="right" style="padding-bottom: 12px;"><span class="font-mono" style="font-size: 14px; color: #0A1228; font-weight: bold;">{{ $order->invoice_number }}</span></td>
                                </tr>
                                @endif
                                <tr>
                                    <td style="padding-bottom: 12px;"><span class="spec-label" style="color: #4E5A74;">{{ mb_strtoupper(trans('emails.order_invoice.order_number', [], $locale)) }}</span></td>
                                    <td align="right" style="padding-bottom: 12px;"><span class="font-mono" style="font-size: 14px; color: #0A1228;">{{ $order->order_number }}</span></td>
                                </tr>
                                <tr>
                                    <td style="padding-top: 12px; border-top: 1px dashed #D8CFB6;"><span class="spec-label" style="color: #4E5A74;">{{ mb_strtoupper(trans('emails.order_invoice.order_total', [], $locale)) }}</span></td>
                                    <td align="right" style="padding-top: 12px; border-top: 1px dashed #D8CFB6;"><span class="font-mono" style="font-size: 18px; color: #0A1228; font-weight: bold;">{{ number_format($order->grand_total, 2) }} €</span></td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr>
            <td style="font-size: 14px; line-height: 21px; color: #4E5A74;">
                {{ trans('emails.order_invoice.questions', ['email' => settings('company.email', 'info@oeparts.lt')], $locale) }}
            </td>
        </tr>
    </table>
@endsection
