@php
    $success = (bool) ($result['success'] ?? false);
    $rolledBack = (bool) ($result['rolled_back'] ?? false);
@endphp
{{ $success ? 'AUTO-UPDATE APPLIED' : ($rolledBack ? 'AUTO-UPDATE ROLLED BACK' : 'AUTO-UPDATE FAILED') }}

An unattended security update {{ $success ? 'was applied' : 'was attempted' }} automatically
(OE_UPDATE_AUTO_SECURITY is enabled on this install).

From version: {{ $result['from_version'] ?? '?' }}
To version:   {{ $result['to_version'] ?? '?' }}
Started:      {{ $result['started_at'] ?? '?' }}
@if($success)

The site is now running {{ $result['to_version'] ?? '' }}. No action needed.
@elseif($rolledBack)

The update failed partway through and was automatically rolled back — files and
database were restored from the pre-update backup taken just before this run.
The site should be back to normal on {{ $result['from_version'] ?? 'its previous version' }}.

Error: {{ $result['error'] ?? 'unknown' }}
@else

The update could not even start (pre-flight check failed) — nothing was changed.

Error: {{ $result['error'] ?? 'unknown' }}
@endif

Log in to the admin panel > System > Update History for full details.
