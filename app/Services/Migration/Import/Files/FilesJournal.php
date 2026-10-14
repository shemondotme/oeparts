<?php

namespace App\Services\Migration\Import\Files;

use App\Services\Migration\Import\ImportStateStore;

/**
 * What the file stage did to the disk, written down BEFORE each change so the rollback can undo it:
 *
 *   files-created.ndjson   a file that did not exist and now does: the rollback deletes it;
 *   files-replaced.ndjson  a file that existed and was replaced: the original was moved to replaced/<n> first, the rollback
 *                          moves it back;
 *   files-tmp.json         the temporary file being written right now, so a kill in the middle leaves nothing behind.
 *
 * Lines are appended and flushed before the rename that makes the change, so a kill between the two leaves a line for a
 * change that did not happen, which the rollback treats as nothing to undo (a missing file is not an error there).
 */
class FilesJournal
{
    public function __construct(private readonly ImportStateStore $store, private readonly string $runId) {}

    public function directory(): string
    {
        return $this->store->directory($this->runId).DIRECTORY_SEPARATOR.'journal';
    }

    public function createdPath(): string
    {
        return $this->directory().DIRECTORY_SEPARATOR.'files-created.ndjson';
    }

    public function replacedPath(): string
    {
        return $this->directory().DIRECTORY_SEPARATOR.'files-replaced.ndjson';
    }

    public function temporaryPath(): string
    {
        return $this->directory().DIRECTORY_SEPARATOR.'files-tmp.json';
    }

    public function replacedFolder(): string
    {
        return $this->directory().DIRECTORY_SEPARATOR.'replaced';
    }

    public function noteTemporary(string $disk, string $path, string $absolute): void
    {
        $this->ensure($this->directory());
        file_put_contents($this->temporaryPath(), json_encode(['d' => $disk, 'p' => $path, 'abs' => $absolute], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public function created(string $disk, string $path): void
    {
        $this->append($this->createdPath(), ['d' => $disk, 'p' => $path]);
    }

    /** Record the replacement and move the original out of the way into the journal's own folder. */
    public function replaced(string $disk, string $path, string $absolute): void
    {
        $n = bin2hex(random_bytes(6));
        $this->append($this->replacedPath(), ['n' => $n, 'd' => $disk, 'p' => $path]);

        $this->ensure($this->replacedFolder());
        $saved = $this->replacedFolder().DIRECTORY_SEPARATOR.$n;

        if (! @rename($absolute, $saved)) {
            if (! @copy($absolute, $saved) || ! @unlink($absolute)) {
                @unlink($saved);

                throw new \RuntimeException("Cannot save the original of {$path} before replacing it.");
            }
        }
    }

    /** When the process runs as root (docker exec), what it creates would be unreadable by the web server: hand it to the storage owner. */
    public function giveToStorageOwner(string $path): void
    {
        if (! function_exists('posix_geteuid') || posix_geteuid() !== 0) {
            return;
        }

        $reference = @stat(storage_path('app'));

        if ($reference !== false) {
            @chown($path, $reference['uid']);
            @chgrp($path, $reference['gid']);
        }
    }

    /** @param  array<string, mixed>  $line */
    private function append(string $file, array $line): void
    {
        $this->ensure(dirname($file));
        $handle = fopen($file, 'ab');

        if ($handle === false) {
            throw new \RuntimeException('Cannot write the files journal.');
        }

        $text = json_encode($line, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
        $ok = fwrite($handle, $text) === strlen($text) && fflush($handle);

        if ($ok && function_exists('fsync')) {
            @fsync($handle);
        }

        fclose($handle);

        if (! $ok) {
            throw new \RuntimeException('Short write to the files journal: the disk may be full.');
        }
    }

    private function ensure(string $directory): void
    {
        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new \RuntimeException("Cannot create {$directory}.");
        }
    }
}
