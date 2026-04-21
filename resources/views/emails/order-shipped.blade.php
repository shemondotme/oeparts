@extends('emails.layout')

@section('content')
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td style="padding: 40px 40px 20px; text-align: center;">
                <h1 style="margin: 0; font-size: 24px; line-height: 32px; color: #333333; font-weight: 600;">
                    {{ trans('emails.order_shipped.title', [], $locale) }}
                </h1>
                <p style="margin: 20px 0 0; font-size: 16px; line-height: 24px; color: #666666;">
                    {{ trans('emails.order_shipped.body', ['order_number' => $order->order_number], $locale) }}
                </p>
            </td>
        </tr>
        @if($order->tracking_number)
        <tr>
            <td style="padding: 20px 40px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                    <tr>
                        <td style="padding: 24px; background-color: #f9fafb; border-radius: 8px;">
                            <p style="margin: 0 0 8px; font-size: 14px; color: #666666;">
                                {{ trans('emails.order_shipped.carrier', [], $locale) }}:
                                <strong style="color: #333333;">{{ $order->carrier_name }}</strong>
                            </p>
                            <p style="margin: 0; font-size: 14px; color: #666666;">
                                {{ trans('emails.order_shipped.tracking_number', [], $locale) }}:
                                <strong style="color: #333333; font-family: 'Courier New', monospace;">{{ $order->tracking_number }}</strong>
                            </p>
                            @if($order->tracking_url)
                            <p style="margin: 12px 0 0;">
                                <a href="{{ $order->tracking_url }}" style="color: #0B3A68; text-decoration: underline; font-size: 14px;">
                                    {{ trans('emails.order_shipped.track_package', [], $locale) }}
                                </a>
                            </p>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        @endif
        <tr>
            <td style="padding: 20px 40px; text-align: center;">
                <a href="{{ route('frontend.account.order.detail', ['lang' => $locale, 'order' => $order->id]) }}" style="display: inline-block; padding: 12px 24px; background-color: #0B3A68; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 16px; line-height: 24px; font-weight: 500;">
                    {{ trans('emails.order_shipped.view_order', [], $locale) }}
                </a>
            </td>
        </tr>
    </table>
@endsection
