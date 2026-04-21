{{ trans('emails.abandoned_cart.title', [], $locale) }}

{{ trans('emails.abandoned_cart.body', [], $locale) }}

{{ trans('emails.abandoned_cart.cta', [], $locale) }}: {{ route('frontend.cart.index', ['lang' => $locale]) }}

---
{{ trans('emails.layout.footer_line1', [], $locale) }}
{{ config('app.url') }}
