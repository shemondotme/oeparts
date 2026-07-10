@php
    $sourced = $newStatus === \App\Enums\PartInquiryStatus::Sourced;
@endphp
{{ trans($sourced ? 'emails.part_inquiry_status.sourced_title' : 'emails.part_inquiry_status.unavailable_title', [], $locale) }}

{{ trans($sourced ? 'emails.part_inquiry_status.sourced_body' : 'emails.part_inquiry_status.unavailable_body', [], $locale) }}

{{ trans('emails.part_inquiry_status.inquiry_id', [], $locale) }}: {{ $inquiry->id }}
{{ trans('emails.part_inquiry_status.requested_part', [], $locale) }}: {{ $inquiry->oem_number }}
{{ trans('emails.part_inquiry_status.status', [], $locale) }}: {{ strtoupper($newStatus->value) }}
