{{ trans('emails.welcome.title', [], $locale) }}

{{ trans('emails.welcome.body', ['name' => $user->name], $locale) }}

{{ trans('emails.welcome.cta', [], $locale) }}: {{ route('frontend.home', ['lang' => $locale]) }}

---
{{ trans('emails.layout.footer_line1', [], $locale) }}
{{ config('app.url') }}
