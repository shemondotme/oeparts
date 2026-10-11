<?php

namespace App\Services\Cleanup;

/**
 * Stops an AUTOMATIC run from wiping a table's history because of a bad setting.
 *
 * A retention typo (3 days instead of 365, a "1" that was meant to be "100") is the one
 * way a scheduled job could destroy data nobody asked it to. If a target would delete
 * more than `max_delete_pct` of what is in its table, the automatic run skips that
 * target and alerts an admin instead; the admin then previews and runs it by hand with an
 * explicit typed confirmation.
 *
 * Tiny tables are exempt: deleting 90% of 40 rows loses nothing worth guarding.
 */
final class OverDeleteGuard
{
    /** Below this many qualifying rows the percentage is meaningless. */
    public const MIN_ROWS = 500;

    /**
     * @return string|null a reason code when the run must not proceed automatically, else null
     */
    public function evaluate(int $targeted, ?int $totalRows, int $maxPct): ?string
    {
        if ($totalRows === null || $totalRows <= 0 || $targeted < self::MIN_ROWS) {
            return null; // cannot judge, or too small to matter
        }

        $pct = ($targeted / $totalRows) * 100;

        return $pct > max(1, $maxPct) ? 'over_delete_pct' : null;
    }

    /**
     * The effective retention an operator-supplied value resolves to: never below the
     * target's floor, and never below 1 day for anything age-based.
     */
    public function effectiveDays(int $configuredDays, int $floorDays, int $defaultDays): int
    {
        if ($defaultDays === 0) {
            return 0; // not age-based (e.g. "already expired") — nothing to clamp
        }

        return max($floorDays, 1, $configuredDays);
    }
}
