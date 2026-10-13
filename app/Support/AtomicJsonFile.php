<?php

namespace App\Support;

/**
 * One JSON document on disk that can be read and rewritten safely by several processes and
 * survives a crash half way through a write.
 *
 * Why it exists: the install, import and export state machines keep their state in a file,
 * not in the database they are about to replace. Each had its own copy of "write a temp file
 * and rename it", and one of them (InstallManager::writeState) ignored a failed rename(), so
 * the OLD state silently stayed on disk and the same step ran again on every poll.
 *
 * Guarantees:
 *  - writers and update() calls take an exclusive lock (<file>.lock), so two processes
 *    cannot lose each other's change;
 *  - a write goes to a temp file in the same directory, is flushed to disk (fsync), the
 *    previous version is kept as <file>.prev, and the temp file is renamed over the target;
 *  - a rename that fails falls back to writing in place; if that fails too, it THROWS
 *    (never "succeeds" with stale data);
 *  - read() falls back to <file>.prev when the main file is torn or corrupt. It returns null
 *    only when no state exists at all; if a file exists but nothing readable is left it
 *    throws, because a state machine that mistakes "corrupt" for "empty" would start over.
 */
class AtomicJsonFile
{
    public function __construct(
        private readonly string $path,
        private readonly float $lockTimeoutSeconds = 10.0,
    ) {}

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, mixed>|null null only when neither the file nor its .prev exists
     *
     * @throws \RuntimeException when a file exists but none of them holds valid JSON
     */
    public function read(): ?array
    {
        $main = $this->decode($this->path);

        if (is_array($main)) {
            return $main;
        }

        $previous = $this->decode($this->path.'.prev');

        if (is_array($previous)) {
            return $previous;
        }

        if (is_file($this->path) || is_file($this->path.'.prev')) {
            throw new \RuntimeException("The state file {$this->path} (and its .prev copy) exists but cannot be read as JSON.");
        }

        return null;
    }

    /**
     * Replace the whole document.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws \RuntimeException when the document could not be written
     */
    public function write(array $data): void
    {
        $this->withLock(function () use ($data): void {
            $this->writeLocked($data);
        });
    }

    /**
     * Read-modify-write under the lock, so concurrent callers each see the other's result.
     *
     * @param  callable(array<string, mixed>): array<string, mixed>  $mutate  receives [] when no state exists yet
     * @return array<string, mixed> the document as written
     */
    public function update(callable $mutate): array
    {
        $result = [];

        $this->withLock(function () use ($mutate, &$result): void {
            $result = $mutate($this->read() ?? []);
            $this->writeLocked($result);
        });

        return $result;
    }

    /** The one place that renames, so a test can make it fail. */
    protected function renameFile(string $from, string $to): bool
    {
        return @rename($from, $to);
    }

    /** @param array<string, mixed> $data */
    private function writeLocked(array $data): void
    {
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tmp = $this->path.'.'.getmypid().'.tmp';

        $handle = @fopen($tmp, 'wb');
        if ($handle === false) {
            throw new \RuntimeException("Cannot create the temporary state file {$tmp}.");
        }

        try {
            $written = fwrite($handle, $json);
            fflush($handle);
            if (function_exists('fsync')) {
                @fsync($handle);
            }
        } finally {
            fclose($handle);
        }

        if ($written !== strlen($json)) {
            @unlink($tmp);

            throw new \RuntimeException("Short write to {$tmp}: the disk may be full.");
        }

        // Keep the last good version. A copy, not a rename: the main file must never be absent.
        if (is_file($this->path) && is_array($this->decode($this->path))) {
            @copy($this->path, $this->path.'.prev');
        }

        if ($this->renameFile($tmp, $this->path)) {
            return;
        }

        // A host where rename() cannot replace an existing file: write in place instead.
        $direct = @file_put_contents($this->path, $json, LOCK_EX);
        @unlink($tmp);

        if ($direct !== strlen($json)) {
            throw new \RuntimeException("Cannot write the state file {$this->path}: rename() and the in-place fallback both failed.");
        }
    }

    /** @return array<string, mixed>|null */
    private function decode(string $file): ?array
    {
        if (! is_file($file)) {
            return null;
        }

        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return null;
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    private function withLock(callable $work): void
    {
        $dir = dirname($this->path);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new \RuntimeException("Cannot create the directory {$dir} for the state file.");
        }

        $lock = @fopen($this->path.'.lock', 'cb');
        if ($lock === false) {
            throw new \RuntimeException("Cannot open the lock file {$this->path}.lock.");
        }

        try {
            $deadline = microtime(true) + $this->lockTimeoutSeconds;

            while (! flock($lock, LOCK_EX | LOCK_NB)) {
                if (microtime(true) >= $deadline) {
                    throw new \RuntimeException("Timed out waiting for the lock on {$this->path}.");
                }

                usleep(10_000);
            }

            try {
                $work();
            } finally {
                flock($lock, LOCK_UN);
            }
        } finally {
            fclose($lock);
        }
    }
}
