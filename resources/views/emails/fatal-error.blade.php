@extends('emails.layout')

@section('content')
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td style="padding: 32px;">
                <p style="margin: 0 0 4px; font-size: 11px; letter-spacing: 0.18em; text-transform: uppercase; color: #B91C1C; font-weight: 700;">
                    Admin Panel Error
                </p>

                <h1 style="margin: 0 0 16px; font-size: 20px; color: #0A1228; font-weight: 800; font-family: monospace;">
                    {{ $error['exception_class'] ?? 'Unknown' }}
                </h1>

                <p style="margin: 0 0 20px; padding: 12px 16px; background: #FEF2F2; border: 1px solid #FCA5A5; border-radius: 8px; font-size: 14px; color: #7F1D1D; font-family: monospace;">
                    {{ $error['message'] ?? '' }}
                </p>

                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="font-size: 13px; color: #555; margin-bottom: 20px;">
                    <tr><td style="padding: 3px 0; width: 90px;">Location:</td><td style="font-family: monospace;">{{ $error['file'] ?? '?' }}:{{ $error['line'] ?? '?' }}</td></tr>
                    <tr><td style="padding: 3px 0;">URL:</td><td style="font-family: monospace; word-break: break-all;">{{ $error['url'] ?? '?' }}</td></tr>
                    <tr><td style="padding: 3px 0;">Occurred:</td><td>{{ $error['occurred_at'] ?? '?' }}</td></tr>
                </table>

                <p style="margin: 0 0 12px; font-size: 13px; line-height: 1.6; color: #777;">
                    Check <code>storage/logs/laravel.log</code> for the full stack trace.
                </p>

                <p style="margin: 0; font-size: 12px; line-height: 1.6; color: #999;">
                    You won't be re-notified about this exact error on this page again for an hour, even if it keeps
                    happening — that's a throttle to avoid spamming you, not a sign it only happened once.
                </p>
            </td>
        </tr>
    </table>
@endsection
