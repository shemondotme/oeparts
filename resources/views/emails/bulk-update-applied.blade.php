@extends('emails.layout')

@section('content')
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td style="padding: 32px;">
                <p style="margin: 0 0 4px; font-size: 11px; letter-spacing: 0.18em; text-transform: uppercase; color: #9A5A00; font-weight: 700;">
                    Bulk Update Applied
                </p>

                <h1 style="margin: 0 0 16px; font-size: 22px; color: #0A1228; font-weight: 800;">
                    {{ number_format($affectedCount) }} products changed
                </h1>

                <p style="margin: 0 0 20px; padding: 12px 16px; background: #FFFBEB; border: 1px solid #FDE68A; border-radius: 8px; font-size: 14px; color: #7C4A03;">
                    {{ $adminName }} applied &ldquo;{{ $actionLabel }}&rdquo; to {{ number_format($affectedCount) }} products — above the automatic large-batch alert threshold.
                </p>

                <p style="margin: 0; font-size: 13px; line-height: 1.6; color: #777;">
                    Log in to the admin panel &rarr; <strong>Catalog &rarr; Bulk Update Log</strong> to review exactly
                    what changed, or revert it from that page if this was unintended.
                </p>
            </td>
        </tr>
    </table>
@endsection
