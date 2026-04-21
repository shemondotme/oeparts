@extends('emails.layout')

@section('content')
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td style="padding: 40px 40px 20px; text-align: center;">
                <h1 style="margin: 0; font-size: 24px; line-height: 32px; color: #333333; font-weight: 600;">
                    {{ trans('emails.order_confirmation.title', [], $locale) }}
                </h1>
                <p style="margin: 20px 0 0; font-size: 16px; line-height: 24px; color: #666666;">
                    {{ trans('emails.order_confirmation.greeting', ['name' => $order->shipping_name], $locale) }}
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding: 20px 40px;">
                <p style="margin: 0 0 20px; font-size: 16px; line-height: 24px; color: #333333;">
                    {{ trans('emails.order_confirmation.body', ['order_number' => $order->order_number], $locale) }}
                </p>
                <p style="margin: 0 0 20px; font-size: 16px; line-height: 24px; color: #333333;">
                    {{ trans('emails.order_confirmation.estimated_delivery', [
                        'min' => $order->shipping_estimated_days_min,
                        'max' => $order->shipping_estimated_days_max
                    ], $locale) }}
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding: 0 40px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                    <tr>
                        <td style="padding: 24px; background-color: #f9fafb; border-radius: 8px;">
                            <h2 style="margin: 0 0 16px; font-size: 18px; line-height: 24px; color: #333333; font-weight: 600;">
                                {{ trans('emails.order_confirmation.order_summary', [], $locale) }}
                            </h2>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">
                                        <span style="font-size: 14px; line-height: 20px; color: #666666;">
                                            {{ trans('emails.order_confirmation.order_number', [], $locale) }}
                                        </span>
                                        <span style="float: right; font-size: 14px; line-height: 20px; color: #333333; font-weight: 500;">
                                            {{ $order->order_number }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">
                                        <span style="font-size: 14px; line-height: 20px; color: #666666;">
                                            {{ trans('emails.order_confirmation.order_date', [], $locale) }}
                                        </span>
                                        <span style="float: right; font-size: 14px; line-height: 20px; color: #333333; font-weight: 500;">
                                            {{ $order->created_at->format('d.m.Y') }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">
                                        <span style="font-size: 14px; line-height: 20px; color: #666666;">
                                            {{ trans('emails.order_confirmation.shipping_method', [], $locale) }}
                                        </span>
                                        <span style="float: right; font-size: 14px; line-height: 20px; color: #333333; font-weight: 500;">
                                            {{ $order->shipping_method_name_snapshot }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">
                                        <span style="font-size: 14px; line-height: 20px; color: #666666;">
                                            {{ trans('emails.order_confirmation.shipping_address', [], $locale) }}
                                        </span>
                                        <span style="float: right; font-size: 14px; line-height: 20px; color: #333333; font-weight: 500; text-align: right;">
                                            {{ $order->shipping_address_line1 }}<br>
                                            {{ $order->shipping_city }}, {{ $order->shipping_postal_code }}<br>
                                            {{ $order->shipping_country_code }}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding: 20px 40px;">
                <h2 style="margin: 0 0 16px; font-size: 18px; line-height: 24px; color: #333333; font-weight: 600;">
                    {{ trans('emails.order_confirmation.order_items', [], $locale) }}
                </h2>
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="padding: 12px 0; border-bottom: 2px solid #e5e7eb; text-align: left; font-size: 14px; line-height: 20px; color: #666666; font-weight: 500;">
                                {{ trans('emails.order_confirmation.product', [], $locale) }}
                            </th>
                            <th style="padding: 12px 0; border-bottom: 2px solid #e5e7eb; text-align: right; font-size: 14px; line-height: 20px; color: #666666; font-weight: 500;">
                                {{ trans('emails.order_confirmation.quantity', [], $locale) }}
                            </th>
                            <th style="padding: 12px 0; border-bottom: 2px solid #e5e7eb; text-align: right; font-size: 14px; line-height: 20px; color: #666666; font-weight: 500;">
                                {{ trans('emails.order_confirmation.price', [], $locale) }}
                            </th>
                            <th style="padding: 12px 0; border-bottom: 2px solid #e5e7eb; text-align: right; font-size: 14px; line-height: 20px; color: #666666; font-weight: 500;">
                                {{ trans('emails.order_confirmation.total', [], $locale) }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->items as $item)
                            <tr>
                                <td style="padding: 12px 0; border-bottom: 1px solid #e5e7eb; font-size: 14px; line-height: 20px; color: #333333;">
                                    <strong>{{ $item->product ? trans_field($item->product->name) : $item->oem_number_snapshot }}</strong><br>
                                    <span style="color: #666666;">{{ $item->oem_number_snapshot }}</span>
                                </td>
                                <td style="padding: 12px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-size: 14px; line-height: 20px; color: #333333;">
                                    {{ $item->quantity }}
                                </td>
                                <td style="padding: 12px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-size: 14px; line-height: 20px; color: #333333;">
                                    {{ number_format($item->unit_price, 2) }} €
                                </td>
                                <td style="padding: 12px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-size: 14px; line-height: 20px; color: #333333;">
                                    {{ number_format($item->total_price, 2) }} €
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding: 20px 40px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border-collapse: collapse;">
                    <tr>
                        <td style="padding: 8px 0; text-align: right;">
                            <span style="font-size: 14px; line-height: 20px; color: #666666;">
                                {{ trans('emails.order_confirmation.subtotal', [], $locale) }}
                            </span>
                            <span style="margin-left: 16px; font-size: 14px; line-height: 20px; color: #333333; font-weight: 500;">
                                {{ number_format($order->subtotal, 2) }} €
                            </span>
                        </td>
                    </tr>
                    @if($order->discount_amount > 0)
                        <tr>
                            <td style="padding: 8px 0; text-align: right;">
                                <span style="font-size: 14px; line-height: 20px; color: #666666;">
                                    {{ trans('emails.order_confirmation.discount', [], $locale) }}
                                </span>
                                <span style="margin-left: 16px; font-size: 14px; line-height: 20px; color: #333333; font-weight: 500;">
                                    -{{ number_format($order->discount_amount, 2) }} €
                                </span>
                            </td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding: 8px 0; text-align: right;">
                            <span style="font-size: 14px; line-height: 20px; color: #666666;">
                                {{ trans('emails.order_confirmation.shipping', [], $locale) }}
                            </span>
                            <span style="margin-left: 16px; font-size: 14px; line-height: 20px; color: #333333; font-weight: 500;">
                                {{ number_format($order->shipping_cost, 2) }} €
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; text-align: right;">
                            <span style="font-size: 14px; line-height: 20px; color: #666666;">
                                {{ trans('emails.order_confirmation.vat', [], $locale) }}
                            </span>
                            <span style="margin-left: 16px; font-size: 14px; line-height: 20px; color: #333333; font-weight: 500;">
                                {{ number_format($order->vat_amount, 2) }} €
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 12px 0; border-top: 2px solid #e5e7eb; text-align: right;">
                            <span style="font-size: 16px; line-height: 24px; color: #333333; font-weight: 600;">
                                {{ trans('emails.order_confirmation.grand_total', [], $locale) }}
                            </span>
                            <span style="margin-left: 16px; font-size: 16px; line-height: 24px; color: #333333; font-weight: 600;">
                                {{ number_format($order->grand_total, 2) }} €
                            </span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding: 20px 40px; text-align: center;">
                <p style="margin: 0 0 20px; font-size: 14px; line-height: 20px; color: #666666;">
                    {{ trans('emails.order_confirmation.footer', [], $locale) }}
                </p>
                <a href="{{ route('frontend.account.order.detail', ['lang' => $locale, 'order' => $order->id]) }}" style="display: inline-block; padding: 12px 24px; background-color: #0B3A68; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 16px; line-height: 24px; font-weight: 500;">
                    {{ trans('emails.order_confirmation.view_order', [], $locale) }}
                </a>
            </td>
        </tr>
    </table>
@endsection