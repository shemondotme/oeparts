{{ trans('emails.contact_reply.title', [], $locale) }}

{{ trans('emails.contact_reply.original_message', [], $locale) }}:
{{ $contactMessage->message }}

---

{{ $replyBody }}

---
{{ trans('emails.layout.footer_line1', [], $locale) }}
{{ config('app.url') }}
