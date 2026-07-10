@extends('emails.layout')

@php
    $sourced = $newStatus === \App\Enums\PartInquiryStatus::Sourced;
@endphp

@section('content')
    {{-- ══════════════════════════════════════════════════════════════════════
         PART INQUIRY STATUS — INDUSTRIAL BLUEPRINT UPDATE
         Focus: Clear verdict (sourced / unavailable), reference tracking.
         ══════════════════════════════════════════════════════════════════ --}}

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">

        {{-- ═══ DOC HEADER: Verdict ═══ --}}
        <tr>
            <td style="padding-bottom: 24px; border-bottom: 1px solid #D8CFB6;">
                <p class="spec-label" style="margin: 0 0 8px 0; color: {{ $sourced ? '#166534' : '#9A5A00' }};">
                    {{ trans($sourced ? 'emails.part_inquiry_status.sourced_label' : 'emails.part_inquiry_status.unavailable_label', [], $locale) }}
                </p>
                <h2 class="font-display" style="margin: 0; font-size: 24px; line-height: 32px; color: #0A1228;">
                    {{ trans($sourced ? 'emails.part_inquiry_status.sourced_title' : 'emails.part_inquiry_status.unavailable_title', [], $locale) }}<span class="text-amber">.</span>
                </h2>
                <p style="margin: 12px 0 0 0; font-size: 15px; line-height: 22px; color: #4E5A74;">
                    {{ trans($sourced ? 'emails.part_inquiry_status.sourced_body' : 'emails.part_inquiry_status.unavailable_body', [], $locale) }}
                </p>
            </td>
        </tr>

        {{-- ═══ INQUIRY REFERENCE CARD ═══ --}}
        <tr>
            <td style="padding: 24px 0;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border: 1px solid #D8CFB6; background-color: #F7F3E7;">
                    <tr>
                        <td style="padding: 20px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="padding-bottom: 12px; border-bottom: 1px dashed #D8CFB6;">
                                        <span class="spec-label" style="color: #4E5A74;">{{ trans('emails.part_inquiry_status.inquiry_id', [], $locale) }}</span>
                                    </td>
                                    <td align="right" style="padding-bottom: 12px; border-bottom: 1px dashed #D8CFB6;">
                                        <span class="font-mono" style="font-size: 14px; color: #0A1228; font-weight: bold;">
                                            {{ $inquiry->id }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 0; border-bottom: 1px dashed #D8CFB6;">
                                        <span class="spec-label" style="color: #4E5A74;">{{ trans('emails.part_inquiry_status.requested_part', [], $locale) }}</span>
                                    </td>
                                    <td align="right" style="padding: 12px 0; border-bottom: 1px dashed #D8CFB6;">
                                        <span class="font-mono" style="font-size: 16px; color: #0A1228; font-weight: bold; letter-spacing: 1px;">
                                            {{ $inquiry->oem_number }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-top: 12px;">
                                        <span class="spec-label" style="color: #4E5A74;">{{ trans('emails.part_inquiry_status.status', [], $locale) }}</span>
                                    </td>
                                    <td align="right" style="padding-top: 12px;">
                                        <span class="font-mono" style="font-size: 14px; font-weight: bold; color: {{ $sourced ? '#166534' : '#9A5A00' }};">
                                            {{ strtoupper($newStatus->value) }}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

    </table>
@endsection
