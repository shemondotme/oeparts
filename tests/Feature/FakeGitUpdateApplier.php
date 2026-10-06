<?php

namespace Tests\Feature;

use App\Models\UpdateHistory;
use App\Services\Updates\Exceptions\UpdateException;
use App\Services\Updates\UpdateApplier;

/**
 * Same shape as Tests\Feature\FakeUpdateApplier, but forces git mode and fakes
 * the git-path steps instead. Kept in its own file (not inline in
 * GitUpdateApplierTest.php) so it autoloads via PSR-4 regardless of which test
 * file touches it first — InterruptedUpdateResumerTest also drives this to
 * cover a stuck git_checkout/composer_install, not just GitUpdateApplierTest.
 */
class FakeGitUpdateApplier extends UpdateApplier
{
    public array $log = [];

    public ?string $failAt = null;

    public bool $rolledBack = false;

    protected function gate(array $manifest): void {}

    protected function isGitMode(): bool
    {
        return true;
    }

    protected function doBackup(UpdateHistory $h): bool
    {
        $this->tick('backup');

        return true;
    }

    protected function doGitCheckout(UpdateHistory $h): void
    {
        $this->tick('git_checkout');
    }

    protected function doComposerInstall(UpdateHistory $h): void
    {
        $this->tick('composer_install');
    }

    protected function doFinalize(UpdateHistory $h): void
    {
        $this->tick('finalize');
    }

    protected function doVerify(UpdateHistory $h): void
    {
        $this->tick('verify');
    }

    protected function rollback(UpdateHistory $h): bool
    {
        $this->rolledBack = true;

        return true;
    }

    private function tick(string $name): void
    {
        $this->log[] = $name;
        if ($this->failAt === $name) {
            throw new UpdateException('fail@'.$name);
        }
    }
}
