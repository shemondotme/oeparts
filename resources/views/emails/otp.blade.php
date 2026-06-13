@extends('emails.layout')

@section('content')
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <!-- Header Section -->
        <tr>
            <td style="padding-bottom: 24px; border-bottom: 1px solid #D8CFB6;">
                <p class="spec-label" style="margin: 0 0 8px 0;">
                    SECURITY · VERIFICATION
                </p>
                <h2 class="font-display" style="margin: 0; font-size: 24px; line-height: 32px; color: #0A1228;">
                    Verify your identity<span class="text-amber">.</span>
                </h2>
            </td>
        </tr>

        <!-- Body Text -->
        <tr>
            <td style="padding: 24px 0;">
                <p style="margin: 0 0 16px 0; font-size: 16px; line-height: 24px; color: #0A1228;">
                    {{ trans('emails.otp.body', [], $locale) }}
                </p>
                <p style="margin: 0; font-size: 14px; line-height: 20px; color: #4E5A74;">
                    {{ settings('email.otp_warning', 'This code is valid for a short period. Do not share it with anyone.') }}
                </p>
            </td>
        </tr>

        <!-- OTP Code Display -->
        <tr>
            <td style="padding: 16px 0;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                    <tr>
                        <td align="center">
                            <div style="display: inline-block; padding: 24px 48px; background-color: #F7F3E7; border: 2px solid #0A1228;">
                                <span class="font-mono" style="font-size: 42px; font-weight: 700; letter-spacing: 12px; color: #0A1228; line-height: 1;">
                                    {{ $code }}
                                </span>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Expiry & Info -->
        <tr>
            <td style="padding-top: 24px; border-top: 1px solid #D8CFB6;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                    <tr>
                        <td style="padding-bottom: 8px;">
                            <span class="spec-label" style="color: #4E5A74;">EXPIRY</span>
                        </td>
                        <td align="right" style="padding-bottom: 8px;">
                            <span class="font-mono" style="font-size: 14px; color: #0A1228; font-weight: bold;">
                                {{ trans('emails.otp.expiry', [], $locale) }}
                            </span>
                        </td>
                    </tr>
                </table>
                <p style="margin: 16px 0 0 0; font-size: 13px; line-height: 18px; color: #4E5A74; text-align: center;">
                    {{ trans('emails.otp.ignore', [], $locale) }}
                </p>
            </td>
        </tr>
    </table>
@endsection
