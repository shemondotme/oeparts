<?php

namespace Tests\Feature;

use App\Models\UpdateHistory;
use App\Services\Updates\Exceptions\UpdateException;
use App\Services\Updates\GitUpdater;
use App\Services\Updates\UpdateApplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Git-managed deployment apply path (UpdateApplier::GIT_STEPS). Same FSM as
 * UpdateApplierTest, but forced into git mode and driving GitUpdater's own
 * git_checkout/composer_install steps instead of download/extract/swap — proves
 * the step list swap, the status labels, and the composer_install-onward
 * rollback boundary (a step earlier than the zip path's, since a composer
 * install failure can leave vendor/ inconsistent even though nothing was
 * "swapped").
 */
class GitUpdateApplierTest extends TestCase
{
    use RefreshDatabase;

    private string $state;

    protected function setUp(): void
    {
        parent::setUp();

        $this->state = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-git-apply-'.getmypid();
        @mkdir($this->state, 0775, true);
        config(['updates.state_path' => $this->state]);
    }

    protected function tearDown(): void
    {
        @array_map('unlink', glob($this->state.DIRECTORY_SEPARATOR.'*') ?: []);
        @rmdir($this->state);
        parent::tearDown();
    }

    private function manifest(array $overrides = []): array
    {
        return array_merge([
            'version'         => '1.1.0',
            'channel'         => 'stable',
            'migration_count' => 1,
        ], $overrides);
    }

    #[Test]
    public function it_runs_the_git_steps_instead_of_the_zip_steps(): void
    {
        $applier = new FakeGitUpdateApplier;

        $history = $applier->run($applier->start($this->manifest()));

        $this->assertSame(
            ['backup', 'git_checkout', 'composer_install', 'finalize', 'verify'],
            $applier->log
        );
        $this->assertSame(UpdateHistory::STATUS_SUCCESS, $history->status);
    }

    #[Test]
    public function status_labels_reflect_the_git_steps_as_they_run(): void
    {
        $applier = new FakeGitUpdateApplier;
        $history = $applier->start($this->manifest());

        $history = $applier->advance($history->refresh()); // backup done → now on git_checkout
        $this->assertSame('git_checkout', $history->step);
        $this->assertSame(UpdateHistory::STATUS_PULLING, $history->status);

        $history = $applier->advance($history->refresh()); // git_checkout done → now on composer_install
        $this->assertSame('composer_install', $history->step);
        $this->assertSame(UpdateHistory::STATUS_INSTALLING, $history->status);
    }

    #[Test]
    public function a_failure_at_git_checkout_does_not_roll_back(): void
    {
        // Nothing has touched the filesystem yet at this point — same as the
        // zip path's "failure before swap" case.
        $applier = new FakeGitUpdateApplier;
        $applier->failAt = 'git_checkout';

        $history = $applier->run($applier->start($this->manifest()));

        $this->assertSame(UpdateHistory::STATUS_FAILED, $history->status);
        $this->assertFalse($applier->rolledBack);
    }

    #[Test]
    public function a_failure_at_composer_install_rolls_back(): void
    {
        // Unlike the zip path (whose atomic per-directory rename means only
        // finalize/verify need rollback), the git path must also roll back a
        // composer_install failure — a partial install can leave vendor/
        // inconsistent even though git_checkout itself succeeded cleanly.
        $applier = new FakeGitUpdateApplier;
        $applier->failAt = 'composer_install';

        $history = $applier->run($applier->start($this->manifest()));

        $this->assertSame(UpdateHistory::STATUS_ROLLED_BACK, $history->status);
        $this->assertTrue($applier->rolledBack);
    }

    #[Test]
    public function a_failure_at_finalize_still_rolls_back(): void
    {
        $applier = new FakeGitUpdateApplier;
        $applier->failAt = 'finalize';

        $history = $applier->run($applier->start($this->manifest()));

        $this->assertSame(UpdateHistory::STATUS_ROLLED_BACK, $history->status);
        $this->assertTrue($applier->rolledBack);
    }
}

/**
 * Same shape as Tests\Feature\FakeUpdateApplier, but forces git mode and
 * fakes the git-path steps instead — kept local to this file since (unlike
 * FakeUpdateApplier) only one test class needs it.
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

    protected function doBackup(UpdateHistory $h): void { $this->tick('backup'); }
    protected function doGitCheckout(UpdateHistory $h): void { $this->tick('git_checkout'); }
    protected function doComposerInstall(UpdateHistory $h): void { $this->tick('composer_install'); }
    protected function doFinalize(UpdateHistory $h): void { $this->tick('finalize'); }
    protected function doVerify(UpdateHistory $h): void { $this->tick('verify'); }

    protected function rollback(UpdateHistory $h): bool { $this->rolledBack = true; return true; }

    private function tick(string $name): void
    {
        $this->log[] = $name;
        if ($this->failAt === $name) {
            throw new UpdateException('fail@'.$name);
        }
    }
}
