@extends('emails.layout')

@section('content')
    {{-- ══════════════════════════════════════════════════════════════════════
         CONTACT REPLY — INDUSTRIAL BLUEPRINT SUPPORT RESPONSE
         Focus: Clean prose, professional tone, clear subject.
         ══════════════════════════════════════════════════════════════════ --}}

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">

        {{-- ═══ DOC HEADER: Support Response ═══ --}}
        <tr>
            <td style="padding-bottom: 24px; border-bottom: 1px solid #D8CFB6;">
                <p class="spec-label" style="margin: 0 0 8px 0; color: #9A5A00;">
                    SUPPORT · RESPONSE
                </p>
                <h2 class="font-display" style="margin: 0; font-size: 24px; line-height: 32px; color: #0A1228;">
                    Regarding your inquiry<span class="text-amber">.</span>
                </h2>
                <p style="margin: 12px 0 0 0; font-size: 15px; line-height: 22px; color: #4E5A74;">
                    {{ trans('emails.contact_reply.greeting', ['name' => $contact->name ?? 'Customer'], $locale) }}
                    <br>
                    {{ trans('emails.contact_reply.intro', [], $locale) ?: settings('email.contact_reply_intro', 'Thank you for contacting OeParts support. Please find our response below.') }}
                </p>
            </td>
        </tr>

        {{-- ═══ ORIGINAL MESSAGE REFERENCE ═══ --}}
        @if(isset($contact->subject) || isset($contact->message))
        <tr>
            <td style="padding: 24px 0;">
                <p class="spec-label" style="margin: 0 0 8px 0; color: #4E5A74;">
                    YOUR ORIGINAL MESSAGE
                </p>
                <div style="background-color: #F7F3E7; border-left: 4px solid #D8CFB6; padding: 16px; font-size: 14px; line-height: 22px; color: #4E5A74; font-style: italic;">
                    @if(isset($contact->subject))
                        <strong style="display: block; margin-bottom: 8px; color: #0A1228; font-style: normal;">Subject: {{ $contact->subject }}</strong>
                    @endif
                    {!! nl2br(e($contact->message ?? '')) !!}
                </div>
            </td>
        </tr>
        @endif

        {{-- ═══ ADMIN RESPONSE ═══ --}}
        <tr>
            <td style="padding-bottom: 24px;">
                <p class="spec-label" style="margin: 0 0 8px 0; color: #9A5A00;">
                    OUR RESPONSE
                </p>
                <div style="background-color: #FFFFFF; border: 1px solid #D8CFB6; padding: 20px; font-size: 15px; line-height: 24px; color: #0A1228;">
                    {!! nl2br(e($replyMessage ?? '')) !!}
                </div>
            </td>
        </tr>

        {{-- ═══ CTA / NEXT STEPS ═══ --}}
        <tr>
            <td align="center" style="padding: 24px 0; border-top: 1px solid #D8CFB6;">
                <p style="margin: 0 0 20px 0; font-size: 14px; line-height: 20px; color: #4E5A74;">
                    {{ settings('email.contact_reply_cta', 'If you have further questions, simply reply to this email.') }}
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
