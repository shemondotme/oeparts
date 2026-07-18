{{ trans('emails.order_status.title', [], $locale) }}

{{ trans('emails.order_status.body', ['order_number' => $order->order_number], $locale) }}

{{ trans('emails.order_status.order_number', [], $locale) }}: {{ $order->order_number }}
{{ trans('emails.order_status.previous_status', [], $locale) }}: {{ $oldStatus->value }}
{{ trans('emails.order_status.new_status', [], $locale) }}: {{ $newStatus->value }}

{{ trans('emails.order_status.view_order', [], $locale) }}: {{ route('frontend.account.order.detail', ['lang' => $locale, 'order' => $order->id]) }}

---
{{ trans('emails.layout.footer_line1', ['year' => now()->year], $locale) }}
{{ config('app.url') }}
