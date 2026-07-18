{{ trans('emails.otp.title', [], $locale) }}

{{ trans('emails.otp.body', [], $locale) }}

{{ $code }}

{{ trans('emails.otp.expiry', ['minutes' => settings('auth.otp_expiry_minutes', 10)], $locale) }}

{{ trans('emails.otp.ignore', [], $locale) }}

---
{{ trans('emails.layout.footer_line1', ['year' => now()->year], $locale) }}
{{ config('app.url') }}
