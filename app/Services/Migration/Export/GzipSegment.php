<?php

namespace App\Services\Migration\Export;

/**
 * A growing file made of gzip members, one appended per page of rows.
 *
 * A concatenation of gzip members is itself a valid gzip stream, so the finished segment is one
 * ordinary compressed file; and because each member is complete in itself, a segment can be cut back to
 * the end of its last COMMITTED member after a crash and carry on, with no duplicate and no half page.
 * That cut is the whole resume story: the checkpoint records how many compressed bytes are committed,
 * and every append first truncates the file to exactly that length.
 */
final class GzipSegment
{
    /**
     * Append `$text` as one new gzip member, after cutting the file back to `$committedBytes`.
     *
     * @return int the compressed size of the segment after the append
     */
    public static function append(string $path, int $committedBytes, string $text, int $level = 6): int
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new \RuntimeException("Cannot create the directory {$directory}.");
        }

        $handle = @fopen($path, 'c+b');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open the segment {$path}.");
        }

        try {
            $size = (int) (fstat($handle)['size'] ?? 0);
            if ($size < $committedBytes) {
                throw new \RuntimeException("The segment {$path} is shorter ({$size}) than its checkpoint says ({$committedBytes}): the staging was damaged.");
            }

            ftruncate($handle, $committedBytes);
            fseek($handle, $committedBytes);

            $member = gzencode($text, $level);
            if ($member === false || fwrite($handle, $member) !== strlen($member)) {
                throw new \RuntimeException("Short write to the segment {$path}: the disk may be full.");
            }

            fflush($handle);
            if (function_exists('fsync')) {
                @fsync($handle);
            }

            return $committedBytes + strlen($member);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Finish a segment: cut off anything after the committed bytes and fingerprint what is left.
     *
     * @return array{sha256: string, bytes: int}
     */
    public static function seal(string $path, int $committedBytes): array
    {
        $handle = @fopen($path, 'c+b');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open the segment {$path}.");
        }

        try {
            if ((int) (fstat($handle)['size'] ?? 0) < $committedBytes) {
                throw new \RuntimeException("The segment {$path} is shorter than its checkpoint says: the staging was damaged.");
            }

            ftruncate($handle, $committedBytes);
        } finally {
            fclose($handle);
        }

        $hash = hash_file('sha256', $path);

        if ($hash === false) {
            throw new \RuntimeException("Cannot read the segment {$path} to fingerprint it.");
        }

        return ['sha256' => $hash, 'bytes' => $committedBytes];
    }
}
