@extends('emails.layout')

@section('content')
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td style="padding: 40px 40px 20px; text-align: center;">
                <h1 style="margin: 0; font-size: 24px; line-height: 32px; color: #333333; font-weight: 600;">
                    {{ trans('emails.otp.title', [], $locale) }}
                </h1>
                <p style="margin: 20px 0 0; font-size: 16px; line-height: 24px; color: #666666;">
                    {{ trans('emails.otp.body', [], $locale) }}
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding: 20px 40px; text-align: center;">
                <div style="display: inline-block; padding: 20px 40px; background-color: #f9fafb; border: 2px solid #e5e7eb; border-radius: 8px;">
                    <span style="font-size: 36px; font-weight: 700; letter-spacing: 8px; color: #0B3A68; font-family: 'Courier New', monospace;">
                        {{ $code }}
                    </span>
                </div>
                <p style="margin: 16px 0 0; font-size: 14px; line-height: 20px; color: #666666;">
                    {{ trans('emails.otp.expiry', [], $locale) }}
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding: 20px 40px;">
                <p style="margin: 0; font-size: 14px; line-height: 20px; color: #666666; text-align: center;">
                    {{ trans('emails.otp.ignore', [], $locale) }}
                </p>
            </td>
        </tr>
    </table>
@endsection
