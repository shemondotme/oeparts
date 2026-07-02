@php
    $security = (bool) ($status['security'] ?? false);
    $latest = $status['latest_version'] ?? '';
    $current = $status['current_version'] ?? '';
@endphp
{{ $security ? 'SECURITY UPDATE AVAILABLE' : 'UPDATE AVAILABLE' }}

OeParts {{ $latest }} is available.

Installed version: {{ $current }}
New version:       {{ $latest }}
Channel:           {{ $status['channel'] ?? 'stable' }}
@if(!empty($status['release_date']))
Released:          {{ $status['release_date'] }}
@endif
DB migrations:     {{ $status['migration_count'] ?? 0 }}
@if($security)

This is a SECURITY release — please review and update as soon as possible.
@endif
@if(!empty($status['changelog_url']))

Changelog: {{ $status['changelog_url'] }}
@endif

Log in to the admin panel > System > System Updates to review this release.
Always take a backup before updating.
