@extends('emails.layout')

@section('content')
    {{-- ══════════════════════════════════════════════════════════════════════
         PART INQUIRY RECEIVED — INDUSTRIAL BLUEPRINT ACKNOWLEDGMENT
         Focus: Professional, consultative, reference tracking.
         ══════════════════════════════════════════════════════════════════ --}}

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">

        {{-- ═══ DOC HEADER: Inquiry Acknowledgment ═══ --}}
        <tr>
            <td style="padding-bottom: 24px; border-bottom: 1px solid #D8CFB6;">
                <p class="spec-label" style="margin: 0 0 8px 0; color: #9A5A00;">
                    SUPPORT · INQUIRY RECEIVED
                </p>
                <h2 class="font-display" style="margin: 0; font-size: 24px; line-height: 32px; color: #0A1228;">
                    We have received your request<span class="text-amber">.</span>
                </h2>
                <p style="margin: 12px 0 0 0; font-size: 15px; line-height: 22px; color: #4E5A74;">
                    {{ trans('emails.part_inquiry.greeting', ['name' => $inquiry->name], $locale) }}
                    <br>
                    {{ trans('emails.part_inquiry.body_intro', [], $locale) ?: 'Our technical team is reviewing your part inquiry. We will respond with availability, pricing, and cross-reference options within 24 hours.' }}
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
                                {{-- Reference ID --}}
                                <tr>
                                    <td style="padding-bottom: 12px;">
                                        <span class="spec-label" style="color: #4E5A74;">INQUIRY ID</span>
                                    </td>
                                    <td align="right" style="padding-bottom: 12px;">
                                        <span class="font-mono" style="font-size: 14px; color: #0A1228; font-weight: bold;">
                                            {{ $inquiry->id }}
                                        </span>
                                    </td>
                                </tr>
                                {{-- Date Submitted --}}
                                <tr>
                                    <td style="padding-bottom: 12px; border-bottom: 1px dashed #D8CFB6;">
                                        <span class="spec-label" style="color: #4E5A74;">DATE SUBMITTED</span>
                                    </td>
                                    <td align="right" style="padding-bottom: 12px; border-bottom: 1px dashed #D8CFB6;">
                                        <span class="font-mono" style="font-size: 14px; color: #0A1228;">
                                            {{ $inquiry->created_at->format('d M Y, H:i') }} CET
                                        </span>
                                    </td>
                                </tr>
                                {{-- Requested OEM/Part --}}
                                <tr>
                                    <td colspan="2" style="padding-top: 12px;">
                                        <span class="spec-label" style="color: #4E5A74; display: block; margin-bottom: 4px;">REQUESTED PART</span>
                                        <p style="margin: 0; font-size: 14px; line-height: 20px; color: #0A1228;">
                                            <strong>{{ $inquiry->oem_number ?? 'N/A' }}</strong>
                                            @if($inquiry->part_name)
                                                <br><span style="color: #4E5A74;">{{ $inquiry->part_name }}</span>
                                            @endif
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- ═══ NEXT STEPS / EXPECTATION ═══ --}}
        <tr>
            <td style="padding-bottom: 24px;">
                <p class="spec-label" style="margin: 0 0 8px 0; color: #9A5A00;">
                    WHAT HAPPENS NEXT
                </p>
                <ul style="margin: 0; padding-left: 20px; font-size: 14px; line-height: 22px; color: #0A1228;">
                    <li style="margin-bottom: 8px;">Our specialists verify the part number against manufacturer catalogs.</li>
                    <li style="margin-bottom: 8px;">We check stock levels across our EU distributor network.</li>
                    <li style="margin-bottom: 8px;">You receive a detailed quote with shipping options via email.</li>
                </ul>
            </td>
        </tr>

        {{-- ═══ CTA BUTTON ═══ --}}
        <tr>
            <td align="center" style="padding: 24px 0; border-top: 1px solid #D8CFB6;">
                <p style="margin: 0 0 20px 0; font-size: 14px; line-height: 20px; color: #4E5A74;">
                    Need to add more details? Reply directly to this email.
                </p>
                <a href="{{ route('frontend.contact.show', ['lang' => $locale]) }}"
                   class="btn-primary"
                   style="display: inline-block; padding: 14px 28px; background-color: #0A1228; color: #F7F3E7 !important; text-decoration: none; font-family: 'Courier New', Courier, monospace; font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.18em; border: 1px solid #0A1228;">
                    CONTACT SUPPORT →
                </a>
            </td>
        </tr>

    </table>
@endsection
