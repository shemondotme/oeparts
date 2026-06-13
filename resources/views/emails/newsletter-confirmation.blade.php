@extends('emails.layout')

@section('content')
    {{-- ══════════════════════════════════════════════════════════════════════
         NEWSLETTER CONFIRMATION — INDUSTRIAL BLUEPRINT OPT-IN
         Focus: Clarity, single action, verification.
         ══════════════════════════════════════════════════════════════════ --}}

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">

        {{-- ═══ DOC HEADER: Confirmation Request ═══ --}}
        <tr>
            <td style="padding-bottom: 24px; border-bottom: 1px solid #D8CFB6;">
                <p class="spec-label" style="margin: 0 0 8px 0; color: #9A5A00;">
                    SUBSCRIPTION · VERIFY
                </p>
                <h2 class="font-display" style="margin: 0; font-size: 24px; line-height: 32px; color: #0A1228;">
                    Confirm your subscription<span class="text-amber">.</span>
                </h2>
                <p style="margin: 12px 0 0 0; font-size: 15px; line-height: 22px; color: #4E5A74;">
                    {{ trans('emails.newsletter_confirmation.greeting', [], $locale) ?: settings('email.newsletter_greeting', 'You are one step away from joining the OeParts Journal.') }}
                </p>
            </td>
        </tr>

        {{-- ═══ INTRO PROSE ═══ --}}
        <tr>
            <td style="padding: 24px 0;">
                <p style="margin: 0 0 16px 0; font-size: 15px; line-height: 24px; color: #0A1228;">
                    {{ trans('emails.newsletter_confirmation.body', [], $locale) ?: settings('email.newsletter_body', 'We send technical updates, new arrival alerts, and industry insights. No spam, no fluff. Just genuine parts intelligence.') }}
                </p>
                <p style="margin: 0; font-size: 15px; line-height: 24px; color: #0A1228;">
                    Please click the button below to verify your email address and activate your subscription.
                </p>
            </td>
        </tr>

        {{-- ═══ CTA BUTTON ═══ --}}
        <tr>
            <td align="center" style="padding: 24px 0; border-top: 1px solid #D8CFB6; border-bottom: 1px solid #D8CFB6;">
                <a href="{{ $confirmUrl }}"
                   class="btn-primary"
                   style="display: inline-block; padding: 14px 28px; background-color: #0A1228; color: #F7F3E7 !important; text-decoration: none; font-family: 'Courier New', Courier, monospace; font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.18em; border: 1px solid #0A1228;">
                    CONFIRM SUBSCRIPTION →
                </a>
            </td>
        </tr>

        {{-- ═══ EXPIRY / SECURITY NOTE ═══ --}}
        <tr>
            <td style="padding-top: 24px;">
                <p style="margin: 0 0 8px 0; font-size: 13px; line-height: 18px; color: #4E5A74; text-align: center;">
                    {{ settings('email.newsletter_expiry', 'This link will expire in 24 hours.') }}
                </p>
                <p style="margin: 0; font-size: 13px; line-height: 18px; color: #4E5A74; text-align: center;">
                    If you did not request this subscription, you can safely ignore this email.
                </p>
            </td>
        </tr>

    </table>
@endsection
