{{ trans('emails.order_confirmation.title', [], $locale) }}

{{ trans('emails.order_confirmation.greeting', ['name' => $order->shipping_name], $locale) }}

{{ trans('emails.order_confirmation.body', ['order_number' => $order->order_number], $locale) }}

{{ trans('emails.order_confirmation.estimated_delivery', [
    'min' => $order->shipping_estimated_days_min,
    'max' => $order->shipping_estimated_days_max
], $locale) }}

{{ trans('emails.order_confirmation.order_summary', [], $locale) }}
----------------------------------------
{{ trans('emails.order_confirmation.order_number', [], $locale) }}: {{ $order->order_number }}
{{ trans('emails.order_confirmation.order_date', [], $locale) }}: {{ $order->created_at->format('d.m.Y') }}
{{ trans('emails.order_confirmation.shipping_method', [], $locale) }}: {{ $order->shipping_method_name_snapshot }}
{{ trans('emails.order_confirmation.shipping_address', [], $locale) }}:
{{ $order->shipping_address_line1 }}
{{ $order->shipping_city }}, {{ $order->shipping_postal_code }}
{{ $order->shipping_country_code }}

{{ trans('emails.order_confirmation.order_items', [], $locale) }}
----------------------------------------
@foreach($order->items as $item)
- {{ $item->product ? trans_field($item->product->name) : $item->oem_number_snapshot }} ({{ $item->oem_number_snapshot }})
  {{ trans('emails.order_confirmation.quantity', [], $locale) }}: {{ $item->quantity }}
  {{ trans('emails.order_confirmation.price', [], $locale) }}: {{ number_format($item->unit_price, 2) }} €
  {{ trans('emails.order_confirmation.total', [], $locale) }}: {{ number_format($item->total_price, 2) }} €

@endforeach
{{ trans('emails.order_confirmation.subtotal', [], $locale) }}: {{ number_format($order->subtotal, 2) }} €
@if($order->discount_amount > 0)
{{ trans('emails.order_confirmation.discount', [], $locale) }}: -{{ number_format($order->discount_amount, 2) }} €
@endif
{{ trans('emails.order_confirmation.shipping', [], $locale) }}: {{ number_format($order->shipping_cost, 2) }} €
@if($order->urgent_processing && bccomp((string) $order->urgent_processing_fee, '0', 2) > 0)
{{ settings_trans('checkout.urgent_processing_label', 'Rush processing') }}: {{ number_format((float) $order->urgent_processing_fee, 2) }} €
@endif
@if(bccomp((string) $order->handling_fee, '0', 2) > 0)
{{ trans('emails.order_confirmation.handling_fee', [], $locale) }}: {{ number_format((float) $order->handling_fee, 2) }} €
@endif
{{ trans('emails.order_confirmation.vat', [], $locale) }}: {{ number_format($order->vat_amount, 2) }} €
{{ trans('emails.order_confirmation.grand_total', [], $locale) }}: {{ number_format($order->grand_total, 2) }} €

{{ trans('emails.order_confirmation.oversized_notice_heading', [], $locale) }}
{{ trans('emails.order_confirmation.oversized_notice_body', [], $locale) }}

{{ trans('emails.order_confirmation.footer', [], $locale) }}

{{ trans('emails.order_confirmation.view_order', [], $locale) }}: {{ route('frontend.account.order.detail', ['lang' => $locale, 'order' => $order->id]) }}

---
{{ trans('emails.layout.footer_line1', ['year' => now()->year], $locale) }}
{{ trans('emails.layout.footer_line2', [], $locale) }}
{{ config('app.url') }}