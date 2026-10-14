<?php

namespace App\Services\Migration\Import\Files;

use App\Security\PackagePathPolicy;
use App\Services\Migration\Groups\GroupRegistry;
use App\Services\Migration\Import\Contracts\RollbackHook;
use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\SelectedGroups;
use Illuminate\Support\Facades\Log;

/**
 * The files half of the rollback, replayed from the journal the file stage wrote: the temporary file that was being
 * written is removed, every file the import created is deleted, every original it replaced is moved back.
 *
 * Resumable and safe to repeat: a file that is already gone is not an error, an original that is already back is skipped,
 * and the position in each journal is kept in the state. A journal line is never trusted blindly: its path is judged by the
 * same policy as at import time, and only a regular file (never a link or a folder) is deleted.
 */
final class FilesRollbackHook implements RollbackHook
{
    /** @param  array<string, array{path: string, prefix: string}>  $disks */
    public function __construct(
        private readonly ImportStateStore $store,
        private readonly GroupRegistry $registry,
        private readonly array $disks,
        private readonly PackagePathPolicy $policy = new PackagePathPolicy,
    ) {}

    public function name(): string
    {
        return 'files';
    }

    public function run(ImportState $state, ImportContext $context): bool
    {
        $journal = new FilesJournal($this->store, $state->id);
        $allowed = $this->allowedRoots($state);
        $position = (array) ($state->stageState['frb'] ?? ['phase' => 'tmp', 'off' => 0]);

        if ($position['phase'] === 'tmp') {
            $this->removeTemporary($journal);
            $position = ['phase' => 'created', 'off' => 0];
            $this->remember($state, $position);
        }

        if ($position['phase'] === 'created') {
            if (! $this->replay($journal->createdPath(), $position, $state, $context, fn (array $line): bool => $this->deleteCreated($line, $allowed))) {
                return false;
            }

            $position = ['phase' => 'replaced', 'off' => 0];
            $this->remember($state, $position);
        }

        if ($position['phase'] === 'replaced') {
            if (! $this->replay($journal->replacedPath(), $position, $state, $context, fn (array $line): bool => $this->restoreReplaced($line, $journal, $allowed))) {
                return false;
            }

            $position = ['phase' => 'done', 'off' => 0];
            $this->remember($state, $position);
        }

        return true;
    }

    /** @return array<int, string> */
    private function allowedRoots(ImportState $state): array
    {
        $roots = [];

        foreach ($this->registry->fileRootsOf(SelectedGroups::of($state)) as $root) {
            $roots[] = $this->disks[$root->disk]['prefix'].'/'.$root->path;
        }

        return $roots;
    }

    private function removeTemporary(FilesJournal $journal): void
    {
        $file = $journal->temporaryPath();

        if (! is_file($file)) {
            return;
        }

        $note = json_decode((string) file_get_contents($file), true);
        $absolute = is_array($note) ? (string) ($note['abs'] ?? '') : '';

        if ($absolute !== '' && str_contains(basename($absolute), '.oeimp-') && is_file($absolute) && ! is_link($absolute)) {
            @unlink($absolute);
        }
    }

    /**
     * @param  array<string, mixed>  $position
     * @param  \Closure(array<string, mixed>): bool  $each
     * @return bool true when the whole journal is replayed
     */
    private function replay(string $file, array &$position, ImportState $state, ImportContext $context, \Closure $each): bool
    {
        if (! is_file($file)) {
            return true;
        }

        $handle = fopen($file, 'rb');

        if ($handle === false) {
            throw new \RuntimeException("Cannot read the files journal {$file}.");
        }

        try {
            fseek($handle, (int) $position['off']);

            while (($line = fgets($handle)) !== false) {
                if (! str_ends_with($line, "\n")) {
                    break; // a torn last line: that change never happened
                }

                $decoded = json_decode($line, true);

                if (is_array($decoded)) {
                    $each($decoded);
                }

                $position['off'] = (int) ftell($handle);

                if ($context->expired()) {
                    $this->remember($state, $position);

                    return false;
                }
            }
        } finally {
            fclose($handle);
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  array<int, string>  $allowed
     */
    private function deleteCreated(array $line, array $allowed): bool
    {
        $absolute = $this->resolve($line, $allowed);

        if ($absolute !== null && is_file($absolute) && ! is_link($absolute)) {
            @unlink($absolute);
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  array<int, string>  $allowed
     */
    private function restoreReplaced(array $line, FilesJournal $journal, array $allowed): bool
    {
        $absolute = $this->resolve($line, $allowed);
        $n = (string) ($line['n'] ?? '');
        $saved = $journal->replacedFolder().DIRECTORY_SEPARATOR.$n;

        if ($absolute === null || preg_match('/^[a-f0-9]{12}$/', $n) !== 1 || ! is_file($saved)) {
            return true; // the original was never moved, or is already back
        }

        if (is_link($absolute)) {
            return true;
        }

        $directory = dirname($absolute);

        if (! is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        if (! @rename($saved, $absolute) && (! @copy($saved, $absolute) || ! @unlink($saved))) {
            throw new \RuntimeException('Cannot put back the original of '.($line['p'] ?? '?').'.');
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  array<int, string>  $allowed
     */
    private function resolve(array $line, array $allowed): ?string
    {
        $disk = (string) ($line['d'] ?? '');
        $path = (string) ($line['p'] ?? '');

        if (! isset($this->disks[$disk]) || $this->policy->reject($this->disks[$disk]['prefix'].'/'.$path, $allowed) !== null) {
            Log::channel('migration')->warning('The files journal names a path the rollback will not touch: '.$path);

            return null;
        }

        return $this->disks[$disk]['path'].DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    /** @param  array<string, mixed>  $position */
    private function remember(ImportState $state, array $position): void
    {
        $state->stageState['frb'] = $position;
        $state->touch();
        $this->store->save($state);
    }
}
