<?php

namespace App\Services\Updates;

use App\Services\Updates\Exceptions\UpdateException;
use Symfony\Component\Process\Process;

/**
 * GitUpdater (Module 21, git-deployment path) — the git-based equivalent of
 * download+extract+swap for installs where PreflightService::checkDeploymentType()
 * detects a `.git` directory in the app root. Shells out to `git`/`composer`
 * directly rather than downloading+extracting a release zip, so the one-click
 * updater works for a git-cloned production deployment instead of refusing to
 * run at all.
 *
 * This is a genuinely different risk profile from the shared-hosting zip-swap
 * path (requires exec/proc_open, a `git` binary, a `composer` binary, and
 * network access to both the git remote and Packagist/a Composer mirror) — see
 * PreflightService::checkGitTooling(), which fails closed if any of that isn't
 * available, rather than leaving a git-managed install to silently fall through
 * to a half-working state.
 */
class GitUpdater
{
    public function isGitManaged(): bool
    {
        return is_dir($this->root().DIRECTORY_SEPARATOR.'.git');
    }

    /** True when exec/proc_open works and both binaries resolve — everything this class needs to actually run. */
    public function toolingAvailable(): bool
    {
        if (! function_exists('proc_open') || in_array('proc_open', $this->disabledFunctions(), true)) {
            return false;
        }

        return $this->binaryExists('git') && $this->binaryExists('composer');
    }

    /**
     * Fetch and check out the target release's tag. Leaves the working tree in
     * detached HEAD at that tag — the normal, expected state for a production
     * checkout (it doesn't need to be "on a branch").
     */
    public function checkout(string $version): void
    {
        $this->run(['git', 'fetch', '--tags', '--force', 'origin']);
        $this->run(['git', 'checkout', '--force', $this->tag($version)]);
    }

    public function composerInstall(): void
    {
        $this->run(
            ['composer', 'install', '--no-dev', '--optimize-autoloader', '--no-interaction'],
            timeout: 300,
        );
    }

    /**
     * Used by UpdateApplier's failure/rollback matrix: check out the PREVIOUS
     * (known-good) tag and reinstall its dependencies, so a failure partway
     * through composer install never leaves vendor/ in a state that matches
     * neither the old nor the new release.
     */
    public function rollbackTo(string $previousVersion): void
    {
        $this->run(['git', 'checkout', '--force', $this->tag($previousVersion)]);
        $this->run(
            ['composer', 'install', '--no-dev', '--optimize-autoloader', '--no-interaction'],
            timeout: 300,
        );
    }

    /** The tag currently checked out, or null if HEAD isn't exactly on a tag. */
    public function currentTag(): ?string
    {
        $process = $this->process(['git', 'describe', '--tags', '--exact-match']);
        $process->run();

        return $process->isSuccessful() ? trim($process->getOutput()) : null;
    }

    private function tag(string $version): string
    {
        return 'v'.ltrim($version, 'v');
    }

    private function run(array $command, int $timeout = 120): void
    {
        $process = $this->process($command, $timeout);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new UpdateException(
                implode(' ', $command).' failed: '.trim($process->getErrorOutput() ?: $process->getOutput())
            );
        }
    }

    private function process(array $command, int $timeout = 120): Process
    {
        $process = new Process($command, $this->root());
        $process->setTimeout($timeout);

        return $process;
    }

    private function binaryExists(string $name): bool
    {
        $finder = new \Symfony\Component\Process\ExecutableFinder;

        return $finder->find($name) !== null;
    }

    /** @return array<int,string> */
    private function disabledFunctions(): array
    {
        return array_map('trim', explode(',', (string) ini_get('disable_functions')));
    }

    private function root(): string
    {
        return rtrim((string) (config('updates.root_path') ?: base_path()), '/\\');
    }
}
