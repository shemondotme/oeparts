@php
    $success = (bool) ($result['success'] ?? false);
    $rolledBack = (bool) ($result['rolled_back'] ?? false);
    $trigger = $result['trigger'] ?? 'auto';
    $isAuto = $trigger === 'auto';
    $isRestore = $trigger === 'restore';
    $prefix = $isAuto ? 'AUTO-UPDATE' : ($isRestore ? 'RESTORE' : 'UPDATE');
    $noun = $isRestore ? 'restore' : 'update';
@endphp
{{ $success ? $prefix.' APPLIED' : ($rolledBack ? $prefix.' ROLLED BACK' : $prefix.' FAILED') }}

@if($isAuto)
An unattended security update {{ $success ? 'was applied' : 'was attempted' }} automatically
(OE_UPDATE_AUTO_SECURITY is enabled on this install).
@elseif($isRestore)
This was a full production restore (files and database), triggered by an administrator
via Backup Dashboard > Restore into production.
@else
This update was applied by an administrator via System > System Updates.
@endif

From version: {{ $result['from_version'] ?? '?' }}
To version:   {{ $result['to_version'] ?? '?' }}
Started:      {{ $result['started_at'] ?? '?' }}
@if($success)

@if($isRestore)
The production restore completed successfully. No action needed.
@else
The site is now running {{ $result['to_version'] ?? '' }}. No action needed.
@endif
@elseif($rolledBack)

The {{ $noun }} failed partway through and was automatically rolled back — files and
@if($isRestore)
database were restored back to their pre-restore state from the safety backup taken just before this run.
@else
database were restored from the pre-update backup taken just before this run.
The site should be back to normal on {{ $result['from_version'] ?? 'its previous version' }}.
@endif

Error: {{ $result['error'] ?? 'unknown' }}
@else

The {{ $noun }} failed and could not be automatically rolled back. If it never started
(a pre-flight check failing), nothing was changed. Otherwise the site may not be
in a fully working state — check it now and use the emergency recovery console
if needed.

Error: {{ $result['error'] ?? 'unknown' }}
@endif

Log in to the admin panel > System > Update History for full details.
