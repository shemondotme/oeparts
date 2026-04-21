{{ trans('emails.otp.title', [], $locale) }}

{{ trans('emails.otp.body', [], $locale) }}

{{ $code }}

{{ trans('emails.otp.expiry', [], $locale) }}

{{ trans('emails.otp.ignore', [], $locale) }}

---
{{ trans('emails.layout.footer_line1', [], $locale) }}
{{ config('app.url') }}
