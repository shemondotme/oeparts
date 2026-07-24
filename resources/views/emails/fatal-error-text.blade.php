ADMIN PANEL ERROR

An unexpected error occurred in the OeParts admin panel.

Error:     {{ $error['exception_class'] ?? 'Unknown' }}
Message:   {{ $error['message'] ?? '' }}
Location:  {{ $error['file'] ?? '?' }}:{{ $error['line'] ?? '?' }}
URL:       {{ $error['url'] ?? '?' }}
Occurred:  {{ $error['occurred_at'] ?? '?' }}

Check storage/logs/laravel.log for the full stack trace.

You won't be re-notified about this exact error on this page again for an
hour, even if it keeps happening — this is a throttle to avoid spamming you,
not a sign it only happened once.
