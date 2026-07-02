@extends('emails.layout')

@section('content')
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td style="padding: 32px;">
                <p style="margin: 0 0 4px; font-size: 11px; letter-spacing: 0.18em; text-transform: uppercase; color: #B91C1C; font-weight: 700;">
                    Backup Failed
                </p>

                <h1 style="margin: 0 0 16px; font-size: 22px; color: #0A1228; font-weight: 800;">
                    A {{ $profile }} backup did not complete
                </h1>

                <p style="margin: 0 0 20px; padding: 12px 16px; background: #FEF2F2; border: 1px solid #FCA5A5; border-radius: 8px; font-size: 14px; color: #7F1D1D;">
                    {{ $reason }}
                </p>

                @if(!empty($runId))
                    <p style="margin: 0 0 8px; font-size: 14px; color: #555;">
                        Backup run: <strong style="font-family: monospace;">#{{ $runId }}</strong>
                    </p>
                @endif
                @if(!empty($failedAt))
                    <p style="margin: 0 0 20px; font-size: 14px; color: #555;">
                        Failed at: <strong>{{ $failedAt }}</strong>
                    </p>
                @endif

                <p style="margin: 0; font-size: 13px; line-height: 1.6; color: #777;">
                    Log in to the admin panel &rarr; <strong>System &rarr; Backup Management</strong> to review the run
                    and retry. Your site is unprotected until a backup succeeds — please resolve this promptly.
                </p>
            </td>
        </tr>
    </table>
@endsection
