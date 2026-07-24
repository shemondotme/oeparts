<?php

namespace Tests\Feature;

use App\Models\UpdateHistory;
use App\Services\Updates\Exceptions\UpdateException;
use App\Services\Updates\UpdateApplier;

/**
 * Test double: records step order, optionally fails at a named step, and stubs
 * the swap/DB rollback — no real download / swap / restore. Used by both
 * UpdateApplierTest and AutoApplySecurityUpdateTest (the latter proves it's
 * just a scheduled trigger around the same, already-tested apply FSM) — kept
 * in its own file so both are loadable independently under parallel test
 * workers, which don't share the class-loading of a single PHPUnit process.
 */
class FakeUpdateApplier extends UpdateApplier
{
    public array $log = [];
    public ?string $failAt = null;
    public bool $rolledBack = false;

    protected function gate(array $manifest): void {}

    protected function doBackup(UpdateHistory $h): void { $this->tick('backup'); }
    protected function doDownload(UpdateHistory $h): void { $this->tick('download'); }
    protected function doExtract(UpdateHistory $h): void { $this->tick('extract'); }
    protected function doSwap(UpdateHistory $h): void { $this->tick('swap'); }
    protected function doFinalize(UpdateHistory $h): void { $this->tick('finalize'); }
    protected function doVerify(UpdateHistory $h): void { $this->tick('verify'); }

    protected function rollback(UpdateHistory $h): void { $this->rolledBack = true; }

    private function tick(string $name): void
    {
        $this->log[] = $name;
        if ($this->failAt === $name) {
            throw new UpdateException('fail@'.$name);
        }
    }
}
