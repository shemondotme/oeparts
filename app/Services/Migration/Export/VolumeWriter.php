<?php

namespace App\Services\Migration\Export;

/**
 * Packs files into one growing volume file, each file as a run of independently compressed blocks.
 *
 * A volume is a plain concatenation: for every file, its 1 MiB blocks, each gzip-compressed on its own and
 * appended. What the extractor needs is not stored in the volume but in the index: for every file the
 * list of `[compressed offset, compressed length, raw length]`, so one file can be read out of a 512 MiB
 * volume without touching the rest. A file never straddles two volumes.
 *
 * Resume is by truncation. The checkpoint records how many bytes of the volume are committed; opening the
 * writer cuts the file back to exactly that, so a process killed in the middle of a file leaves neither a
 * half file nor a duplicate. A file that fails half way is cut back to where it began.
 */
final class VolumeWriter
{
    public const BLOCK = 1024 * 1024;

    /** @var resource */
    private $handle;

    private int $bytes;

    private function __construct($handle, int $bytes)
    {
        $this->handle = $handle;
        $this->bytes = $bytes;
    }

    /** Open the volume, cut back to the committed length, ready to append. */
    public static function open(string $path, int $committedBytes): self
    {
        $directory = dirname($path);
        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new \RuntimeException("Cannot create the directory {$directory}.");
        }

        $handle = @fopen($path, 'c+b');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open the volume {$path}.");
        }

        $size = (int) (fstat($handle)['size'] ?? 0);
        if ($size < $committedBytes) {
            fclose($handle);

            throw new \RuntimeException("The volume {$path} is shorter ({$size}) than its checkpoint says ({$committedBytes}): the staging was damaged.");
        }

        ftruncate($handle, $committedBytes);
        fseek($handle, $committedBytes);

        return new self($handle, $committedBytes);
    }

    /** Compressed bytes in the volume, including everything appended through this writer. */
    public function bytes(): int
    {
        return $this->bytes;
    }

    /**
     * Append one file. Returns what the index needs, or null when the file is gone or cannot be opened (the
     * caller records it as skipped). The size and hash are those of the bytes actually copied, so a log that
     * grew while it was read is consistent with its own hash.
     *
     * @return array{size: int, mtime: int, sha256: string, segments: array<int, array{0: int, 1: int, 2: int}>}|null
     */
    public function appendFile(string $absolutePath): ?array
    {
        $source = @fopen($absolutePath, 'rb');

        if ($source === false) {
            return null;
        }

        $start = $this->bytes;
        $hash = hash_init('sha256');
        $segments = [];
        $size = 0;

        try {
            while (! feof($source)) {
                $block = fread($source, self::BLOCK);

                if ($block === false) {
                    throw new \RuntimeException('read error');
                }

                if ($block === '') {
                    break;
                }

                hash_update($hash, $block);
                $size += strlen($block);

                $member = gzencode($block, 6);
                if ($member === false || fwrite($this->handle, $member) !== strlen($member)) {
                    throw new \RuntimeException("Short write to the volume: the disk may be full ({$absolutePath}).");
                }

                $segments[] = [$this->bytes, strlen($member), strlen($block)];
                $this->bytes += strlen($member);
            }
        } catch (\Throwable $e) {
            ftruncate($this->handle, $start);
            fseek($this->handle, $start);
            $this->bytes = $start;

            if ($e->getMessage() === 'read error') {
                return null; // an unreadable file is skipped, not fatal
            }

            throw $e;
        } finally {
            fclose($source);
        }

        return ['size' => $size, 'mtime' => (int) @filemtime($absolutePath), 'sha256' => hash_final($hash), 'segments' => $segments];
    }

    /** Flush to disk and release the file. */
    public function close(): void
    {
        if (is_resource($this->handle)) {
            fflush($this->handle);
            if (function_exists('fsync')) {
                @fsync($this->handle);
            }
            fclose($this->handle);
        }
    }
}
