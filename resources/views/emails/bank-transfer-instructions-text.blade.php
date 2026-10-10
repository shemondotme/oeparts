{{ trans('emails.bank_transfer.headline', [], $locale) }}

{{ trans('emails.bank_transfer.greeting', ['name' => $order->shipping_name], $locale) }}

{{ trans('emails.bank_transfer.body', ['order_number' => $order->order_number], $locale) }}

{{ trans('emails.bank_transfer.eyebrow', [], $locale) }}
----------------------------------------
@foreach([
    'amount' => number_format((float) $bank['amount'], 2).' '.$bank['currency'],
    'account_holder' => $bank['account_holder'],
    'iban' => $bank['iban'],
    'bic' => $bank['bic'],
    'bank_name' => $bank['bank_name'],
    'reference' => $bank['reference'],
] as $key => $value)
@if(filled($value))
{{ trans('emails.bank_transfer.'.$key, [], $locale) }}: {{ $value }}
@endif
@endforeach
{{ trans('emails.bank_transfer.pay_by', [], $locale) }}: {{ $deadline->format('d M Y, H:i') }}

{{ trans('emails.bank_transfer.reference_note', [], $locale) }}
{{ trans('emails.bank_transfer.expiry_note', ['hours' => $bank['expiry_hours']], $locale) }}

{{ trans('emails.order_confirmation.order_items', [], $locale) }}
----------------------------------------
@foreach($order->items as $item)
- {{ $item->product ? trans_field($item->product->name) : $item->oem_number_snapshot }} ({{ $item->oem_number_snapshot }}) × {{ $item->quantity }}: {{ number_format($item->total_price, 2) }} €
@endforeach
{{ trans('emails.order_confirmation.grand_total', [], $locale) }}: {{ number_format($order->grand_total, 2) }} €

{{ trans('emails.bank_transfer.footer', [], $locale) }}

{{ trans('emails.order_confirmation.view_order', [], $locale) }}: {{ route('frontend.account.order.detail', ['lang' => $locale, 'order' => $order->id]) }}

---
{{ trans('emails.layout.footer_line1', ['year' => now()->year], $locale) }}
{{ trans('emails.layout.footer_line2', [], $locale) }}
{{ config('app.url') }}
