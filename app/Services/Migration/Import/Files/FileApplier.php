<?php

namespace App\Services\Migration\Import\Files;

use App\Security\PackagePathPolicy;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Package\Exceptions\PackageException;
use App\Services\Migration\Package\PackageReader;

/**
 * Puts one file of the package on the disk, safely: only under a folder of a group being imported, never through a link,
 * never as something a web server would run, always through a temporary file that is checked (size and SHA-256) before it
 * takes its final name. What it replaces or creates is written to the journal BEFORE the rename, so a rollback can undo it.
 *
 * It judges a record; it never trusts one: every number and name is validated, and the bytes are authenticated by the
 * package reader and checked again against the record's hash.
 *
 * Not final on purpose: a test replaces it to fail at a chosen file.
 */
class FileApplier
{
    public const WRITTEN = 'written';

    public const REPLACED = 'replaced';

    public const SAME = 'same';

    public const REJECTED = 'rejected';

    /**
     * @param  array<string, array{path: string, prefix: string}>  $disks  disk => absolute folder and its path relative to the application root
     * @param  array<int, string>  $allowedRoots  application-root-relative folders of the groups being imported
     */
    public function __construct(
        private readonly array $disks,
        private readonly array $allowedRoots,
        private readonly FilesJournal $journal,
        private readonly string $runId,
        private readonly PackagePathPolicy $policy = new PackagePathPolicy,
    ) {}

    /**
     * @param  array<string, mixed>  $row  one line of the file index
     * @return array{outcome: string, bytes: int, reason: string|null}
     */
    public function apply(array $row, PackageReader $reader): array
    {
        $record = $this->validated($row);

        $relative = $this->disks[$record['d']]['prefix'].'/'.$record['p'];
        $rejection = $this->policy->reject($relative, $this->allowedRoots);

        if ($rejection !== null) {
            return $this->rejected($rejection->value);
        }

        $absolute = $this->disks[$record['d']]['path'].DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $record['p']);

        if ($this->hasLinkOnTheWay($this->disks[$record['d']]['path'], $record['p'])) {
            return $this->rejected('symlink');
        }

        if (is_dir($absolute)) {
            return $this->rejected('is_directory');
        }

        $exists = is_file($absolute);

        if ($exists && (int) filesize($absolute) === $record['s'] && hash_file('sha256', $absolute) === $record['h']) {
            return ['outcome' => self::SAME, 'bytes' => 0, 'reason' => null];
        }

        $directory = dirname($absolute);
        $this->ensureDirectory($directory, $this->disks[$record['d']]['path']);

        $temporary = $directory.DIRECTORY_SEPARATOR.'.oeimp-'.$this->runId.'-'.sprintf('%08x', crc32($record['d'].':'.$record['p'])).'.tmp';
        $this->journal->noteTemporary($record['d'], $record['p'], $temporary);

        $head = $this->extract($record, $reader, $temporary);

        if ($this->policy->looksLikeCode($head)) {
            @unlink($temporary);

            return $this->rejected('looks_like_code');
        }

        if ($exists) {
            $this->journal->replaced($record['d'], $record['p'], $absolute);
        } else {
            $this->journal->created($record['d'], $record['p']);
        }

        if (! @rename($temporary, $absolute)) {
            @unlink($temporary);

            throw new \RuntimeException("Cannot move the finished file into place: {$record['p']}.");
        }

        @chmod($absolute, 0664);
        $this->journal->giveToStorageOwner($absolute);

        return ['outcome' => $exists ? self::REPLACED : self::WRITTEN, 'bytes' => $record['s'], 'reason' => null];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{d: string, p: string, s: int, h: string, v: int, seg: array<int, array{0: int, 1: int, 2: int}>}
     */
    private function validated(array $row): array
    {
        $disk = $row['d'] ?? null;
        $path = $row['p'] ?? null;
        $size = $row['s'] ?? null;
        $hash = $row['h'] ?? null;
        $volume = $row['v'] ?? null;
        $segments = $row['seg'] ?? null;

        $ok = is_string($disk) && isset($this->disks[$disk])
            && is_string($path) && $path !== ''
            && is_int($size) && $size >= 0
            && is_string($hash) && preg_match('/^[a-f0-9]{64}$/', $hash) === 1
            && is_int($volume) && $volume >= 0
            && is_array($segments);

        $raw = 0;

        if ($ok) {
            foreach ($segments as $segment) {
                if (! is_array($segment) || count($segment) !== 3 || array_filter($segment, 'is_int') !== $segment || min($segment) < 0 || $segment[2] > 1024 * 1024) {
                    $ok = false;

                    break;
                }

                $raw += $segment[2];
            }
        }

        if (! $ok || $raw !== $size) {
            throw new MigrationException(MigrationErrorCode::PackageCorrupt, ['part' => 'file index record']);
        }

        /** @var array{d: string, p: string, s: int, h: string, v: int, seg: array<int, array{0: int, 1: int, 2: int}>} $record */
        $record = ['d' => $disk, 'p' => $path, 's' => $size, 'h' => $hash, 'v' => $volume, 'seg' => array_values($segments)];

        return $record;
    }

    /**
     * Write the file's bytes to the temporary file and prove them. Returns the first bytes (for the code check).
     *
     * @param  array{d: string, p: string, s: int, h: string, v: int, seg: array<int, array{0: int, 1: int, 2: int}>}  $record
     */
    private function extract(array $record, PackageReader $reader, string $temporary): string
    {
        $handle = @fopen($temporary, 'wb');

        if ($handle === false) {
            throw new \RuntimeException("Cannot create the temporary file for {$record['p']}.");
        }

        $hash = hash_init('sha256');
        $written = 0;
        $head = '';
        $volume = sprintf('files/vol-%04d', $record['v']);

        try {
            foreach ($record['seg'] as [$offset, $length, $raw]) {
                try {
                    // A record that points outside its volume is a damaged index, not something to read past.
                    if ($offset + $length > (int) $reader->entry($volume)['plain_len']) {
                        throw new MigrationException(MigrationErrorCode::PackageCorrupt, ['part' => $volume]);
                    }

                    $member = $reader->readRange($volume, $offset, $length);
                } catch (PackageException $e) {
                    throw $e->toMigrationException();
                }

                $block = @gzdecode($member, $raw + 1);

                if ($block === false || strlen($block) !== $raw) {
                    throw new MigrationException(MigrationErrorCode::PackageCorrupt, ['part' => $volume]);
                }

                if (strlen($head) < 4096) {
                    $head .= substr($block, 0, 4096 - strlen($head));
                }

                hash_update($hash, $block);

                if (fwrite($handle, $block) !== $raw) {
                    throw new \RuntimeException('Short write: the disk may be full.');
                }

                $written += $raw;
            }
        } catch (\Throwable $e) {
            fclose($handle);
            @unlink($temporary);

            throw $e;
        }

        fflush($handle);
        fclose($handle);

        if ($written !== $record['s'] || hash_final($hash) !== $record['h']) {
            @unlink($temporary);

            throw new MigrationException(MigrationErrorCode::PackageCorrupt, ['part' => $record['p']]);
        }

        return $head;
    }

    /** Is the file, or any folder of its path that already exists under the root, a link? (The root itself may be one.) */
    private function hasLinkOnTheWay(string $root, string $relative): bool
    {
        $current = rtrim($root, '/\\');

        foreach (explode('/', $relative) as $segment) {
            $current .= DIRECTORY_SEPARATOR.$segment;

            if (is_link($current)) {
                return true;
            }

            if (! file_exists($current)) {
                return false; // nothing below exists yet, so nothing below can be a link
            }
        }

        return false;
    }

    private function ensureDirectory(string $directory, string $root): void
    {
        if (is_dir($directory)) {
            return;
        }

        $previous = umask(0002);

        try {
            // Created one level at a time so each new folder can be given to the owner of the storage tree.
            $missing = [];

            for ($path = $directory; ! is_dir($path) && strlen($path) > strlen($root); $path = dirname($path)) {
                array_unshift($missing, $path);
            }

            foreach ($missing as $path) {
                if (! @mkdir($path, 0775) && ! is_dir($path)) {
                    throw new \RuntimeException("Cannot create the folder {$path}.");
                }

                $this->journal->giveToStorageOwner($path);
            }
        } finally {
            umask($previous);
        }
    }

    /** @return array{outcome: string, bytes: int, reason: string|null} */
    private function rejected(string $reason): array
    {
        return ['outcome' => self::REJECTED, 'bytes' => 0, 'reason' => $reason];
    }
}
