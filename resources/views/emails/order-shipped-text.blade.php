{{ trans('emails.order_shipped.title', [], $locale) }}

{{ trans('emails.order_shipped.body', ['order_number' => $order->order_number], $locale) }}

@if($order->tracking_number)
{{ trans('emails.order_shipped.carrier', [], $locale) }}: {{ $order->carrier_name }}
{{ trans('emails.order_shipped.tracking_number', [], $locale) }}: {{ $order->tracking_number }}
@if($order->tracking_url)
{{ trans('emails.order_shipped.track_package', [], $locale) }}: {{ $order->tracking_url }}
@endif
@endif

{{ trans('emails.order_shipped.view_order', [], $locale) }}: {{ route('frontend.account.order.detail', ['lang' => $locale, 'order' => $order->id]) }}

---
{{ trans('emails.layout.footer_line1', [], $locale) }}
{{ config('app.url') }}
