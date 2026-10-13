<?php

namespace App\Services\Migration\Export;

use App\Models\BackupRun;

/**
 * The one way an expired freeze is ended, shared by everything that may notice it first: the watchdog,
 * the next web request, the driver of the export itself.
 *
 * If the export is still running when its freeze runs out, the copy can no longer be called consistent
 * (the shop has been open, or about to be, while rows were still being read), so the run is failed with
 * that reason; failing it also lifts the freeze. If the export had finished and only its HOLD ran out, the
 * package is fine and the freeze is simply lifted.
 */
final class FreezeReleaser
{
    public function __construct(private readonly ExportFreeze $freeze) {}

    /** @return string|null why the freeze was lifted, or null when there was nothing to lift */
    public function releaseIfExpired(): ?string
    {
        $state = FreezeState::read();

        if ($state === null || $state->isLive()) {
            return null;
        }

        $run = BackupRun::query()->exports()->whereKey($state->runId)->first();

        if ($run !== null && ! $run->isTerminal()) {
            $meta = (array) $run->meta;
            $meta['error_code'] = 'freeze_expired';
            $meta['export_report'] = array_merge((array) ($meta['export_report'] ?? []), ['consistency' => 'not_consistent']);
            $run->meta = $meta;

            // ExportManager::fail() lifts the freeze as part of ending the run.
            app(ExportManager::class)->fail($run, 'The freeze ran out before the export finished: the copy is not consistent. Run the export again.');

            return 'the export ran past its freeze deadline';
        }

        $this->freeze->release('expired');

        return $state->phase === FreezeState::PHASE_HOLD ? 'the hold time ended' : 'the freeze deadline passed';
    }
}
