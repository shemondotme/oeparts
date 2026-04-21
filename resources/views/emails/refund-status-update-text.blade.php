{{ trans('emails.refund_status.title', [], $locale) }}

{{ trans('emails.refund_status.body', ['order_number' => $refund->order->order_number], $locale) }}

{{ trans('emails.refund_status.order_number', [], $locale) }}: {{ $refund->order->order_number }}
{{ trans('emails.refund_status.previous_status', [], $locale) }}: {{ $oldStatus->value }}
{{ trans('emails.refund_status.new_status', [], $locale) }}: {{ $newStatus->value }}

{{ trans('emails.refund_status.view_orders', [], $locale) }}: {{ route('frontend.account.orders', ['lang' => $locale]) }}

---
{{ trans('emails.layout.footer_line1', [], $locale) }}
{{ config('app.url') }}
