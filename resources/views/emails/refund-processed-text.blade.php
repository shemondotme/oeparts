{{ trans('emails.refund_processed.title', [], $locale) }}

{{ trans('emails.refund_processed.body', ['order_number' => $refund->order->order_number], $locale) }}

{{ trans('emails.refund_processed.order_number', [], $locale) }}: {{ $refund->order->order_number }}
{{ trans('emails.refund_processed.refund_amount', [], $locale) }}: {{ number_format((float) $refund->amount_requested, 2) }} €
{{ trans('emails.refund_processed.payment_method', [], $locale) }}: {{ trans('emails.payment_methods.'.($refund->order->payment_method?->value ?? 'bank_transfer'), [], $locale) }}

{{ trans('emails.refund_processed.processing_time', ['days' => '5–10'], $locale) }}

{{ trans('emails.refund_processed.view_orders', [], $locale) }}: {{ route('frontend.account.orders', ['lang' => $locale]) }}

---
{{ trans('emails.layout.footer_line1', ['year' => now()->year], $locale) }}
{{ config('app.url') }}
