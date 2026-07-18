{{ trans('emails.password_reset.headline', [], $locale ?? 'en') ?: 'Reset your password' }}
=====================================

{{ trans('emails.password_reset.body', [], $locale ?? 'en') ?: 'We received a request to reset the password for your OeParts account.' }}

{{ trans('emails.password_reset.cta', [], $locale ?? 'en') ?: 'SET NEW PASSWORD' }}:
{{ $resetUrl }}

{{ trans('emails.password_reset.expiry_note', ['minutes' => config('auth.passwords.users.expire', 60)], $locale ?? 'en') }}
{{ trans('emails.password_reset.fallback_note', [], $locale ?? 'en') }}

--
{{ trans('emails.layout.footer_line1', ['year' => now()->year], $locale ?? 'en') }}
{{ config('app.url') }}
