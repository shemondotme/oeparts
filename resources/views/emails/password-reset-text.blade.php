{{ trans('emails.password_reset.headline', [], $locale ?? 'en') ?: 'Reset your password' }}
=====================================

{{ trans('emails.password_reset.body', [], $locale ?? 'en') ?: 'We received a request to reset the password for your OeParts account.' }}

{{ trans('emails.password_reset.cta', [], $locale ?? 'en') ?: 'SET NEW PASSWORD' }}:
{{ $resetUrl }}

{{ trans('emails.password_reset.expiry_note', [], $locale ?? 'en') ?: 'This link expires in 60 minutes. If you did not request a password reset, you can safely ignore this email.' }}

--
OeParts Europe
{{ config('app.url') }}
