<?php

namespace App\Console\Commands;

use App\Services\Updates\GitUpdater;
use Illuminate\Console\Command;

/**
 * Manual recovery tool: re-run the dev-file cleanup a git-managed install's
 * checkout() normally runs automatically as its last step. Needed when a
 * PREVIOUS update was killed from outside PHP (a host's own request-time
 * limit) between `git checkout --force` landing and this cleanup running —
 * checkout(), the strip, and everything else happen in one request with no
 * checkpoint between them. Confirmed live: a production install was left
 * with tests/, phpstan.neon, etc. still on disk, a `git checkout --force`
 * having fully applied with nothing to clean up after it.
 *
 * Going forward InterruptedUpdateResumer already re-runs the WHOLE
 * git_checkout step (fetch + checkout + this strip) automatically on the
 * next request that reaches the install, so this command exists for
 * recovering an install whose interrupted update predates that fix, and as
 * a standing "did something leave dev files behind?" sanity check.
 *
 * Safe to run any time, any number of times: GitUpdater::
 * stripDevFilesFromWorkingTree() never touches .env/storage/.git (see its
 * own preserve_paths filtering) and a path already gone is silently skipped.
 */
class StripUpdateDevFiles extends Command
{
    protected $signature = 'oeparts:update:strip-dev-files';

    protected $description = 'Remove dev-only files a git-managed install should never have shipped (Update Engine recovery).';

    public function handle(GitUpdater $gitUpdater): int
    {
        if (! $gitUpdater->isGitManaged()) {
            $this->info('This install is not git-managed (no .git directory) — a zip install never ships these files, nothing to do.');

            return self::SUCCESS;
        }

        $removed = $gitUpdater->stripDevFilesFromWorkingTree();

        if ($removed === []) {
            $this->info('Nothing to remove — the working tree is already clean.');

            return self::SUCCESS;
        }

        $this->info(count($removed).' dev-only path(s) removed:');
        foreach ($removed as $path) {
            $this->line('  - '.$path);
        }

        return self::SUCCESS;
    }
}
