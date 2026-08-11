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
        // Symfony Process (what run()/process() below use) needs the full
        // proc_* family, not just proc_open — a host that disables e.g.
        // proc_close or proc_get_status while leaving proc_open enabled (a
        // plausible partial-hardening policy) previously passed this check
        // and then hit an uncaught fatal on the first real git command,
        // instead of being caught cleanly by this pre-flight gate.
        $required = ['proc_open', 'proc_close', 'proc_get_status', 'proc_terminate'];
        $disabled = $this->disabledFunctions();

        foreach ($required as $fn) {
            if (! function_exists($fn) || in_array($fn, $disabled, true)) {
                return false;
            }
        }

        return $this->binaryExists('git') && $this->binaryExists('composer');
    }

    /**
     * Fetch and check out the target release's tag. Leaves the working tree in
     * detached HEAD at that tag — the normal, expected state for a production
     * checkout (it doesn't need to be "on a branch").
     *
     * @param string|null $expectedCommitSha When given (from a signed release
     *     manifest's git_commit_sha — see ReleaseSignature::verifyGitManifest()),
     *     the actually-checked-out commit MUST match it exactly. Without this,
     *     nothing here confirms the tag the remote served is the release that
     *     was actually signed/published — a compromised or re-pushed remote
     *     tag would be checked out and trusted silently.
     */
    public function checkout(string $version, ?string $expectedCommitSha = null): void
    {
        $this->run(['git', 'fetch', '--tags', '--force', 'origin']);
        $this->run(['git', 'checkout', '--force', $this->tag($version)]);

        if ($expectedCommitSha !== null && $expectedCommitSha !== '') {
            $actual = $this->currentCommitSha();
            if ($actual === null || ! hash_equals(strtolower($expectedCommitSha), strtolower($actual))) {
                throw new UpdateException(
                    'Checked-out commit ('.($actual ?? 'unknown').') does not match the signed release manifest ('
                    .$expectedCommitSha.') for tag '.$this->tag($version).' — the git remote may be compromised '
                    .'or the tag was re-pushed. Refusing to proceed.'
                );
            }
        }

        $this->stripDevFilesFromWorkingTree();
    }

    /** The commit SHA currently checked out (HEAD), or null if it can't be determined. */
    public function currentCommitSha(): ?string
    {
        $process = $this->process(['git', 'rev-parse', 'HEAD']);
        $process->run();

        return $process->isSuccessful() ? trim($process->getOutput()) : null;
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
        $this->stripDevFilesFromWorkingTree();
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

    /**
     * `git checkout --force` puts EVERY tracked file on disk, including
     * config('updates.build.exclude') paths that were only ever meant to
     * ship in the git repository itself, never on an installed site
     * (docker/, compose.yaml, tests/, .github/, build tooling configs, …).
     * The zip-export release pipeline (ReleaseBuilder::stripDevFiles(),
     * via oeparts:build) already strips exactly this list — but only for a
     * throwaway export directory, never for a git-managed install's live
     * working tree, which this method now also runs it against. Reuses the
     * SAME exclude list (one source of truth for "what's a dev file") minus
     * the three entries a live git repo still needs to keep functioning:
     * .git itself, plus .gitignore/.gitattributes (harmless to keep, and
     * their absence only makes `git status` noisier for no benefit).
     *
     * Confirmed live: a self-hosted git deployment failed to update because
     * `docker/nginx/oeparts.docker.conf` — a file that should never have
     * been on that server's filesystem in the first place — had different
     * ownership than the rest of the repo (hand-edited via sudo at some
     * point) and `git checkout --force` couldn't unlink it. Stripping these
     * paths after every checkout means they simply aren't there to ever
     * cause that class of problem again.
     */
    private function stripDevFilesFromWorkingTree(): void
    {
        $config = (array) config('updates.build', []);
        $exclude = array_values(array_diff(
            (array) ($config['exclude'] ?? []),
            ['.git', '.gitignore', '.gitattributes']
        ));

        (new ReleaseBuilder(array_merge($config, ['exclude' => $exclude])))
            ->stripDevFiles($this->root());
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
