@extends('emails.layout')

@section('content')
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td style="padding: 32px;">
                @php
                    $security = (bool) ($status['security'] ?? false);
                    $latest = $status['latest_version'] ?? '';
                    $current = $status['current_version'] ?? '';
                    $accent = $security ? '#B91C1C' : '#B45309';
                @endphp

                <p style="margin: 0 0 4px; font-size: 11px; letter-spacing: 0.18em; text-transform: uppercase; color: {{ $accent }}; font-weight: 700;">
                    {{ $security ? 'Security Update' : 'Update Available' }}
                </p>

                <h1 style="margin: 0 0 16px; font-size: 22px; color: #0A1228; font-weight: 800;">
                    OeParts {{ $latest }} is available
                </h1>

                <p style="margin: 0 0 20px; font-size: 15px; line-height: 1.6; color: #333;">
                    Your installation is currently running <strong style="font-family: monospace;">{{ $current }}</strong>.
                    A new release <strong style="font-family: monospace;">{{ $latest }}</strong> is now available on the
                    <strong>{{ $status['channel'] ?? 'stable' }}</strong> channel.
                </p>

                @if($security)
                    <p style="margin: 0 0 20px; padding: 12px 16px; background: #FEF2F2; border: 1px solid #FCA5A5; border-radius: 8px; font-size: 14px; color: #7F1D1D;">
                        This is a <strong>security release</strong> — please review and update as soon as possible.
                    </p>
                @endif

                @if(!empty($status['release_date']))
                    <p style="margin: 0 0 8px; font-size: 14px; color: #555;">
                        Released: <strong>{{ $status['release_date'] }}</strong>
                    </p>
                @endif
                <p style="margin: 0 0 24px; font-size: 14px; color: #555;">
                    Database migrations in this release: <strong>{{ $status['migration_count'] ?? 0 }}</strong>
                </p>

                @if(!empty($status['changelog_url']))
                    <p style="margin: 0 0 24px;">
                        <a href="{{ $status['changelog_url'] }}" style="color: #1D4ED8; font-size: 14px;">Read the changelog &rarr;</a>
                    </p>
                @endif

                <p style="margin: 0; font-size: 13px; line-height: 1.6; color: #777;">
                    Log in to the admin panel &rarr; <strong>System &rarr; System Updates</strong> to review this release.
                    Always take a backup before updating.
                </p>
            </td>
        </tr>
    </table>
@endsection
