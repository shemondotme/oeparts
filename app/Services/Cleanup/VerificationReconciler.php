<?php

namespace App\Services\Cleanup;

use App\Models\CleanupRunItem;

/**
 * Turns the raw numbers of one target into a verdict — the thing that makes "100%
 * successful" a provable statement instead of "the command did not throw".
 *
 * Inputs (all measured independently):
 *   targeted   counted BEFORE deleting, with the same predicate used below
 *   deleted    what the database/filesystem reported actually removing
 *   failed     rows/files that threw while being removed
 *   remaining  counted AFTER deleting, again with the same predicate
 *
 * Identity that must hold for a clean run:  targeted = deleted + external_gone + remaining
 * where `external_gone` (= targeted - deleted - remaining) are rows some OTHER process
 * removed in the meantime (an old logs:clean, an admin) — allowed, and reported.
 *
 * Verdicts:
 *   SUCCESS    nothing left, nothing failed, nothing odd
 *   PARTIAL    some rows/files remain or failed, but nothing is inconsistent
 *   FAILED     the numbers contradict each other (deleted more than was targeted, an archive
 *              that does not cover what was deleted) — treated as a bug, never as success
 *   CANCELLED  the admin stopped it; numbers are still the truth about what happened
 */
final class VerificationReconciler
{
    public const REASON_REMAINING = 'remaining_rows';

    public const REASON_FAILED_ITEMS = 'items_failed';

    public const REASON_EXTERNAL = 'external_deleted';

    public const REASON_OVER_DELETE = 'over_delete';

    public const REASON_ARCHIVE_MISMATCH = 'archive_mismatch';

    public const REASON_CANCELLED = 'cancelled_by_admin';

    public const REASON_DRY_RUN = 'dry_run';

    public const REASON_LATE_ROWS = 'late_rows';

    /**
     * @return array{status:string, reasons:list<string>, external_gone:int, equation_holds:bool}
     */
    public function reconcile(
        int $targeted,
        int $deleted,
        int $failed,
        int $remaining,
        ?int $archiveRows = null,
        bool $cancelled = false,
        int $lateRows = 0,
    ): array {
        $reasons = [];
        $difference = $targeted - $deleted - $remaining;
        $externalGone = max(0, $difference);
        $equationHolds = $difference >= 0;

        if ($externalGone > 0) {
            $reasons[] = self::REASON_EXTERNAL;
        }
        if ($lateRows > 0) {
            $reasons[] = self::REASON_LATE_ROWS;
        }

        // More removed (or still present) than was ever counted: the plan and the delete
        // disagree about what qualifies. That is a defect, not a partial success.
        $overDelete = ! $equationHolds || $deleted > $targeted;
        if ($overDelete) {
            $reasons[] = self::REASON_OVER_DELETE;
        }

        $archiveMismatch = $archiveRows !== null && $archiveRows < $deleted;
        if ($archiveMismatch) {
            $reasons[] = self::REASON_ARCHIVE_MISMATCH;
        }

        if ($cancelled) {
            $reasons[] = self::REASON_CANCELLED;

            return $this->result(CleanupRunItem::STATUS_CANCELLED, $reasons, $externalGone, $equationHolds);
        }

        if ($overDelete || $archiveMismatch) {
            return $this->result(CleanupRunItem::STATUS_FAILED, $reasons, $externalGone, $equationHolds);
        }

        if ($remaining > 0) {
            $reasons[] = self::REASON_REMAINING;
        }
        if ($failed > 0) {
            $reasons[] = self::REASON_FAILED_ITEMS;
        }

        if ($remaining > 0 || $failed > 0) {
            return $this->result(CleanupRunItem::STATUS_PARTIAL, $reasons, $externalGone, $equationHolds);
        }

        return $this->result(CleanupRunItem::STATUS_SUCCESS, $reasons, $externalGone, $equationHolds);
    }

    /**
     * A dry run only counts. It is "successful" when planning finished; nothing is verified
     * because nothing was deleted.
     *
     * @return array{status:string, reasons:list<string>, external_gone:int, equation_holds:bool}
     */
    public function dryRun(): array
    {
        return $this->result(CleanupRunItem::STATUS_SUCCESS, [self::REASON_DRY_RUN], 0, true);
    }

    /**
     * Roll item statuses up into the run's verdict. FAILED beats everything; a skip (guard, floor,
     * busy) or any PARTIAL makes the run PARTIAL; CANCELLED only when nothing worse happened.
     *
     * @param  list<string>  $itemStatuses
     */
    public function runStatus(array $itemStatuses): string
    {
        if ($itemStatuses === []) {
            return CleanupRunItem::STATUS_SUCCESS;
        }

        if (in_array(CleanupRunItem::STATUS_FAILED, $itemStatuses, true)) {
            return CleanupRunItem::STATUS_FAILED;
        }
        if (in_array(CleanupRunItem::STATUS_CANCELLED, $itemStatuses, true)) {
            return CleanupRunItem::STATUS_CANCELLED;
        }

        foreach ($itemStatuses as $status) {
            if ($status !== CleanupRunItem::STATUS_SUCCESS) {
                return CleanupRunItem::STATUS_PARTIAL; // partial, skipped_guard, skipped_floor, blocked_busy
            }
        }

        return CleanupRunItem::STATUS_SUCCESS;
    }

    /**
     * @param  list<string>  $reasons
     * @return array{status:string, reasons:list<string>, external_gone:int, equation_holds:bool}
     */
    private function result(string $status, array $reasons, int $externalGone, bool $equationHolds): array
    {
        return [
            'status' => $status,
            'reasons' => array_values(array_unique($reasons)),
            'external_gone' => $externalGone,
            'equation_holds' => $equationHolds,
        ];
    }
}
