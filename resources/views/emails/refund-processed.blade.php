@extends('emails.layout')

@section('content')
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td style="padding: 40px 40px 20px; text-align: center;">
                <h1 style="margin: 0; font-size: 24px; line-height: 32px; color: #333333; font-weight: 600;">
                    {{ trans('emails.refund_processed.title', [], $locale) }}
                </h1>
                <p style="margin: 20px 0 0; font-size: 16px; line-height: 24px; color: #666666;">
                    {{ trans('emails.refund_processed.body', ['order_number' => $refund->order->order_number], $locale) }}
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding: 0 40px 20px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                    <tr>
                        <td style="padding: 24px; background-color: #f9fafb; border-radius: 8px;">
                            <p style="margin: 0 0 8px; font-size: 14px; color: #666666;">
                                {{ trans('emails.refund_processed.order_number', [], $locale) }}:
                                <strong style="color: #333333;">{{ $refund->order->order_number }}</strong>
                            </p>
                            <p style="margin: 0 0 8px; font-size: 14px; color: #666666;">
                                {{ trans('emails.refund_processed.refund_amount', [], $locale) }}:
                                <strong style="color: #333333;">{{ number_format($refund->amount, 2) }} €</strong>
                            </p>
                            <p style="margin: 0; font-size: 14px; color: #666666;">
                                {{ trans('emails.refund_processed.payment_method', [], $locale) }}:
                                <strong style="color: #333333;">{{ $refund->refund_method }}</strong>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding: 0 40px 20px;">
                <p style="margin: 0; font-size: 14px; line-height: 20px; color: #666666; text-align: center;">
                    {{ trans('emails.refund_processed.processing_time', [], $locale) }}
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding: 0 40px 20px; text-align: center;">
                <a href="{{ route('frontend.account.orders', ['lang' => $locale]) }}" style="display: inline-block; padding: 12px 24px; background-color: #0B3A68; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 16px; line-height: 24px; font-weight: 500;">
                    {{ trans('emails.refund_processed.view_orders', [], $locale) }}
                </a>
            </td>
        </tr>
    </table>
@endsection
