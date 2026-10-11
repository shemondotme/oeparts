<?php

namespace App\Services\Cleanup;

use Carbon\CarbonInterface;

/**
 * The daily time window in which AUTOMATIC cleanup is allowed to run (default 02:00-06:00,
 * store local time). Outside it a scheduled run pauses and resumes the next night, so a
 * cleanup never competes with peak traffic. A window that crosses midnight (22:00-04:00)
 * is supported; start == end means "always open".
 */
final class CleanupWindow
{
    public function start(): string
    {
        return $this->normalise((string) settings('cleanup.window_start', config('cleanup.window_start', '02:00')), '02:00');
    }

    public function end(): string
    {
        return $this->normalise((string) settings('cleanup.window_end', config('cleanup.window_end', '06:00')), '06:00');
    }

    public function isOpen(?CarbonInterface $at = null): bool
    {
        $at ??= now();
        $minute = ((int) $at->format('G')) * 60 + (int) $at->format('i');
        $start = $this->minutes($this->start());
        $end = $this->minutes($this->end());

        if ($start === $end) {
            return true;
        }

        return $start < $end
            ? ($minute >= $start && $minute < $end)
            : ($minute >= $start || $minute < $end); // crosses midnight
    }

    private function minutes(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));

        return $h * 60 + $m;
    }

    /** Accepts HH:MM / H:MM; anything else falls back to the default instead of silently opening or closing the window. */
    private function normalise(string $value, string $fallback): string
    {
        if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', trim($value), $m) !== 1) {
            return $fallback;
        }

        return sprintf('%02d:%s', (int) $m[1], $m[2]);
    }
}
