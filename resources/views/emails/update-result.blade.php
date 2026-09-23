@extends('emails.layout')

@section('content')
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td style="padding: 32px;">
                @php
                    $success = (bool) ($result['success'] ?? false);
                    $rolledBack = (bool) ($result['rolled_back'] ?? false);
                    $trigger = $result['trigger'] ?? 'auto';
                    $isAuto = $trigger === 'auto';
                    $isRestore = $trigger === 'restore';
                    $prefix = $isAuto ? 'Auto-Update' : ($isRestore ? 'Restore' : 'Update');
                    $noun = $isRestore ? 'restore' : 'update';
                    $accent = $success ? '#15803D' : ($rolledBack ? '#B45309' : '#B91C1C');
                    $label = $success ? $prefix.' Applied' : ($rolledBack ? $prefix.' Rolled Back' : $prefix.' Failed');
                @endphp

                <p style="margin: 0 0 4px; font-size: 11px; letter-spacing: 0.18em; text-transform: uppercase; color: {{ $accent }}; font-weight: 700;">
                    {{ $label }}
                </p>

                <h1 style="margin: 0 0 16px; font-size: 22px; color: #0A1228; font-weight: 800;">
                    {{ $result['from_version'] ?? '?' }} &rarr; {{ $result['to_version'] ?? '?' }}
                </h1>

                @if($isAuto)
                    <p style="margin: 0 0 20px; font-size: 15px; line-height: 1.6; color: #333;">
                        An unattended security update {{ $success ? 'was applied' : 'was attempted' }} automatically —
                        <code style="font-family: monospace;">OE_UPDATE_AUTO_SECURITY</code> is enabled on this install.
                    </p>
                @elseif($isRestore)
                    <p style="margin: 0 0 20px; font-size: 15px; line-height: 1.6; color: #333;">
                        This was a full production restore (files and database), triggered by an administrator via
                        <strong>Backup Dashboard &rarr; Restore into production</strong>.
                    </p>
                @else
                    <p style="margin: 0 0 20px; font-size: 15px; line-height: 1.6; color: #333;">
                        This update was applied by an administrator via <strong>System &rarr; System Updates</strong>.
                    </p>
                @endif

                @if($success)
                    <p style="margin: 0 0 20px; padding: 12px 16px; background: #F0FDF4; border: 1px solid #86EFAC; border-radius: 8px; font-size: 14px; color: #14532D;">
                        @if($isRestore)
                            The production restore completed successfully. No action needed.
                        @else
                            The site is now running <strong style="font-family: monospace;">{{ $result['to_version'] ?? '' }}</strong>. No action needed.
                        @endif
                    </p>
                @elseif($rolledBack)
                    <p style="margin: 0 0 20px; padding: 12px 16px; background: #FFFBEB; border: 1px solid #FCD34D; border-radius: 8px; font-size: 14px; color: #78350F;">
                        The {{ $noun }} failed partway through and was <strong>automatically rolled back</strong> — files
                        @if($isRestore)
                            and database were restored back to their pre-restore state from the safety backup taken just before this run.
                        @else
                            and database were restored from the pre-update backup taken just before this run. The site should be
                            back to normal on <strong style="font-family: monospace;">{{ $result['from_version'] ?? 'its previous version' }}</strong>.
                        @endif
                    </p>
                    <p style="margin: 0 0 20px; font-size: 13px; color: #7F1D1D;">Error: {{ $result['error'] ?? 'unknown' }}</p>
                @else
                    <p style="margin: 0 0 20px; padding: 12px 16px; background: #FEF2F2; border: 1px solid #FCA5A5; border-radius: 8px; font-size: 14px; color: #7F1D1D;">
                        The {{ $noun }} failed and could not be automatically rolled back. If it never started (a pre-flight
                        check failing), nothing was changed. Otherwise the site may not be in a fully working state —
                        check it now and use the emergency recovery console if needed.
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
