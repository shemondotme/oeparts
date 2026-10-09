{{ trans('emails.order_invoice.title', [], $locale) }} {{ $order->invoice_number ?: $order->order_number }}

{{ trans('emails.order_invoice.greeting', ['name' => $order->shipping_name], $locale) }}

{{ strip_tags(str_replace('**', '', trans('emails.order_invoice.body', ['order_number' => $order->order_number], $locale))) }}

@if(filled($order->invoice_number))
{{ trans('emails.order_invoice.invoice_number', [], $locale) }}: {{ $order->invoice_number }}
@endif
{{ trans('emails.order_invoice.order_number', [], $locale) }}: {{ $order->order_number }}
{{ trans('emails.order_invoice.order_total', [], $locale) }}: {{ number_format($order->grand_total, 2) }} €

{{ trans('emails.order_invoice.questions', ['email' => settings('company.email', 'info@oeparts.lt')], $locale) }}

---
{{ config('app.url') }}
