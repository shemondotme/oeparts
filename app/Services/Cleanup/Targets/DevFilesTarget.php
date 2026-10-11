<?php

namespace App\Services\Cleanup\Targets;

use App\Services\Cleanup\CleanupContext;
use App\Services\Cleanup\Contracts\CleanupTarget;
use App\Services\Updates\GitUpdater;

/**
 * Dev-only files (tests/, phpstan*.neon, compose.yaml, docker/, ...) that an interrupted git-managed
 * update can leave on a live install. The page's "Dev-only files" card already lists and removes them;
 * this adapter puts the same operation under the engine so it gets the lock, the busy guard, the
 * before/after verification and the history like everything else.
 *
 * It does NOT fork the file list: it delegates to GitUpdater, which derives it from the release
 * pipeline's own `updates.build.exclude` (minus .git, .env and storage/, which are never touched).
 * Only meaningful on a git-managed install; on any other install it simply finds nothing.
 * Off in the scheduled run by default: removing files from a live tree is a deliberate action.
 */
final class DevFilesTarget implements CleanupTarget
{
    public function key(): string
    {
        return 'dev_files';
    }

    public function label(): string
    {
        return 'Dev-only files';
    }

    public function description(): string
    {
        return 'Test, tooling and Docker files a release never ships, left on a git-managed install by an interrupted update. .env, storage/ and .git are never touched.';
    }

    public function group(): string
    {
        return 'files';
    }

    public function kind(): string
    {
        return 'callable';
    }

    public function defaultDays(): int
    {
        return 0; // not age-based
    }

    public function floorDays(): int
    {
        return 0;
    }

    public function truncatable(): bool
    {
        return false;
    }

    public function reportOnly(): bool
    {
        return false;
    }

    public function autoByDefault(): bool
    {
        return false;
    }

    public function needsBackupLock(): bool
    {
        return true; // it rewrites the working tree: no backup or update may run underneath it
    }

    public function totalRows(): ?int
    {
        return null;
    }

    public function begin(CleanupContext $ctx): array
    {
        return ['git' => app(GitUpdater::class)->isGitManaged()];
    }

    public function plan(CleanupContext $ctx, array &$cp, float $deadline): bool
    {
        $cp['targeted'] = $cp['git'] ? count(app(GitUpdater::class)->previewDevFilesInWorkingTree()) : 0;

        return true;
    }

    public function delete(CleanupContext $ctx, array &$cp, float $deadline): bool
    {
        if ($cp['git']) {
            $cp['deleted'] = count(app(GitUpdater::class)->stripDevFilesFromWorkingTree());
        }

        return true;
    }

    public function verify(CleanupContext $ctx, array &$cp, float $deadline): bool
    {
        $cp['remaining'] = $cp['git'] ? count(app(GitUpdater::class)->previewDevFilesInWorkingTree()) : 0;
        $cp['failed'] = $cp['remaining'];
        $cp['late_rows'] = 0;

        return true;
    }
}
