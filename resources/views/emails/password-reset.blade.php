@extends('emails.layout')

@section('content')
    {{-- ══════════════════════════════════════════════════════════════════════
         PASSWORD RESET — INDUSTRIAL BLUEPRINT SECURITY NOTICE
         Clean, urgent, reassuring. Blueprint monochrome with amber CTA.
         ══════════════════════════════════════════════════════════════════ --}}

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">

        {{-- ═══ DOC HEADER ═══ --}}
        <tr>
            <td style="padding-bottom: 24px; border-bottom: 1px solid #D8CFB6;">
                <p class="spec-label" style="margin: 0 0 8px 0; color: #9A5A00;">
                    SECURITY · PASSWORD RESET
                </p>
                <h2 class="font-display" style="margin: 0; font-size: 24px; line-height: 32px; color: #0A1228;">
                    {{ trans('emails.password_reset.headline', [], $locale) ?: 'Reset your password' }}<span class="text-amber">.</span>
                </h2>
            </td>
        </tr>

        {{-- ═══ BODY TEXT ═══ --}}
        <tr>
            <td style="padding: 24px 0 16px 0;">
                <p style="margin: 0 0 16px 0; font-size: 15px; line-height: 24px; color: #0A1228;">
                    {{ trans('emails.password_reset.body', [], $locale) ?: settings('email.password_reset_body', 'We received a request to reset the password for your OeParts account. Click the button below to set a new password.') }}
                </p>
                <p style="margin: 0 0 8px 0; font-size: 14px; line-height: 20px; color: #4E5A74;">
                    {{ trans('emails.password_reset.expiry_note', ['minutes' => config('auth.passwords.users.expire', 60)], $locale) }}
                </p>
                <p style="margin: 0; font-size: 14px; line-height: 20px; color: #4E5A74;">
                    {{ trans('emails.password_reset.fallback_note', [], $locale) }}
                </p>
            </td>
        </tr>

        {{-- ═══ CTA BUTTON ═══ --}}
        <tr>
            <td align="center" style="padding: 24px 0;">
                <a href="{{ $resetUrl }}"
                   class="btn-primary"
                   style="display: inline-block; padding: 16px 36px; background-color: #F59E0B; color: #0A1228 !important; text-decoration: none; font-family: 'Courier New', Courier, monospace; font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.18em; border: 1px solid #F59E0B;">
                    {{ trans('emails.password_reset.cta', [], $locale) ?: 'SET NEW PASSWORD →' }}
                </a>
            </td>
        </tr>

        {{-- ═══ SECURITY SPEC LEDGER ═══ --}}
        <tr>
            <td style="padding: 16px 0;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border: 1px solid #D8CFB6; background-color: #F7F3E7;">
                    <tr>
                        <td style="padding: 16px;">
                            <p class="spec-label" style="margin: 0 0 12px 0; color: #9A5A00;">SECURITY NOTICE</p>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="padding-bottom: 8px; border-bottom: 1px dashed #D8CFB6;">
                                        <span class="spec-label" style="color: #4E5A74;">LINK EXPIRES</span>
                                    </td>
                                    <td align="right" style="padding-bottom: 8px; border-bottom: 1px dashed #D8CFB6;">
                                        <span class="font-mono" style="font-size: 13px; color: #0A1228; font-weight: bold;">{{ config('auth.passwords.users.expire', 60) }} MIN</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-top: 8px;">
                                        <span class="spec-label" style="color: #4E5A74;">SINGLE USE</span>
                                    </td>
                                    <td align="right" style="padding-top: 8px;">
                                        <span class="font-mono" style="font-size: 13px; color: #0A1228; font-weight: bold;">YES</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- ═══ FALLBACK URL ═══ --}}
        <tr>
            <td style="padding-top: 16px; border-top: 1px solid #D8CFB6;">
                <p style="margin: 0 0 8px 0; font-size: 13px; line-height: 18px; color: #4E5A74;">
                    If the button above doesn't work, copy and paste this URL into your browser:
                </p>
                <p style="margin: 0; font-family: 'Courier New', Courier, monospace; font-size: 12px; line-height: 18px; color: #0A1228; word-break: break-all;">
                    {{ $resetUrl }}
                </p>
            </td>
        </tr>

    </table>
@endsection
