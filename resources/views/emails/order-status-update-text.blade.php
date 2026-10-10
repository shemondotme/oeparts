{{ trans('emails.order_status.title', [], $locale) }}

@php
    $bodyKey = 'emails.order_status_update.body_'.$newStatus->value;
    if ($newStatus === \App\Enums\OrderStatus::Cancelled && $order->payment_status === \App\Enums\PaymentStatus::Paid) {
        $bodyKey = 'emails.order_status_update.body_cancelled_paid';
    }
@endphp
{{ trans()->has($bodyKey, $locale) ? trans($bodyKey, ['order_number' => $order->order_number], $locale) : trans('emails.order_status.body', ['order_number' => $order->order_number], $locale) }}

{{ trans('emails.order_status.order_number', [], $locale) }}: {{ $order->order_number }}
{{ trans('emails.order_status.previous_status', [], $locale) }}: {{ $oldStatus->value }}
{{ trans('emails.order_status.new_status', [], $locale) }}: {{ $newStatus->value }}
@if($newStatus === \App\Enums\OrderStatus::Shipped && filled($order->tracking_number))
{{ trans('emails.order_shipped.tracking_number', [], $locale) }}: {{ $order->tracking_number }}
@if(filled($order->tracking_url))
{{ trans('emails.order_shipped.track_package', [], $locale) }}: {{ $order->tracking_url }}
@endif
@endif

{{ trans('emails.order_status.view_order', [], $locale) }}: {{ route('frontend.account.order.detail', ['lang' => $locale, 'order' => $order->id]) }}

---
{{ trans('emails.layout.footer_line1', ['year' => now()->year], $locale) }}
{{ config('app.url') }}
