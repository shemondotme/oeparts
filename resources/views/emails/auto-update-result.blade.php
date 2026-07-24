@extends('emails.layout')

@section('content')
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td style="padding: 32px;">
                @php
                    $success = (bool) ($result['success'] ?? false);
                    $rolledBack = (bool) ($result['rolled_back'] ?? false);
                    $accent = $success ? '#15803D' : ($rolledBack ? '#B45309' : '#B91C1C');
                    $label = $success ? 'Auto-Update Applied' : ($rolledBack ? 'Auto-Update Rolled Back' : 'Auto-Update Failed');
                @endphp

                <p style="margin: 0 0 4px; font-size: 11px; letter-spacing: 0.18em; text-transform: uppercase; color: {{ $accent }}; font-weight: 700;">
                    {{ $label }}
                </p>

                <h1 style="margin: 0 0 16px; font-size: 22px; color: #0A1228; font-weight: 800;">
                    {{ $result['from_version'] ?? '?' }} &rarr; {{ $result['to_version'] ?? '?' }}
                </h1>

                <p style="margin: 0 0 20px; font-size: 15px; line-height: 1.6; color: #333;">
                    An unattended security update {{ $success ? 'was applied' : 'was attempted' }} automatically —
                    <code style="font-family: monospace;">OE_UPDATE_AUTO_SECURITY</code> is enabled on this install.
                </p>

                @if($success)
                    <p style="margin: 0 0 20px; padding: 12px 16px; background: #F0FDF4; border: 1px solid #86EFAC; border-radius: 8px; font-size: 14px; color: #14532D;">
                        The site is now running <strong style="font-family: monospace;">{{ $result['to_version'] ?? '' }}</strong>. No action needed.
                    </p>
                @elseif($rolledBack)
                    <p style="margin: 0 0 20px; padding: 12px 16px; background: #FFFBEB; border: 1px solid #FCD34D; border-radius: 8px; font-size: 14px; color: #78350F;">
                        The update failed partway through and was <strong>automatically rolled back</strong> — files and
                        database were restored from the pre-update backup taken just before this run. The site should be
                        back to normal on <strong style="font-family: monospace;">{{ $result['from_version'] ?? 'its previous version' }}</strong>.
                    </p>
                    <p style="margin: 0 0 20px; font-size: 13px; color: #7F1D1D;">Error: {{ $result['error'] ?? 'unknown' }}</p>
                @else
                    <p style="margin: 0 0 20px; padding: 12px 16px; background: #FEF2F2; border: 1px solid #FCA5A5; border-radius: 8px; font-size: 14px; color: #7F1D1D;">
                        The update could not even start (pre-flight check failed) — <strong>nothing was changed</strong>.
                    </p>
                    <p style="margin: 0 0 20px; font-size: 13px; color: #7F1D1D;">Error: {{ $result['error'] ?? 'unknown' }}</p>
                @endif

                <p style="margin: 0; font-size: 13px; line-height: 1.6; color: #777;">
                    Log in to the admin panel &rarr; <strong>System &rarr; Update History</strong> for full details.
                </p>
            </td>
        </tr>
    </table>
@endsection
