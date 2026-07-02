<?php

namespace App\Services\Updates;

use App\Services\Updates\Exceptions\UpdateException;

/**
 * UpdateExtractor (Module 21, Chunk 3.2) — unpacks a verified release zip into a
 * staging directory, safely.
 *
 * ZIP-SLIP / path-traversal guard on EVERY entry (rule #47 / security): absolute
 * paths, drive letters, and any `..` segment are rejected, and each resolved
 * target is confirmed to stay inside the staging root. Entries are streamed to
 * disk (flat memory). A free-disk re-check (sum of uncompressed sizes) runs before
 * extraction. Nothing here touches the live app — that swap is Chunk 3.3.
 */
class UpdateExtractor
{
    public function stagingDir(string $version): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $version);

        return rtrim((string) config('updates.state_path', storage_path('app/updates')), '/\\').'/staging/'.$safe;
    }

    public function extract(string $zipPath, ?string $version = null): string
    {
        if (! is_file($zipPath)) {
            throw new UpdateException('Update archive not found: '.$zipPath);
        }

        $dir = $this->stagingDir($version ?? pathinfo($zipPath, PATHINFO_FILENAME));
        $this->rrmdir($dir);          // discard any prior staging
        $this->ensureDir($dir);

        $zip = new \ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new UpdateException('Cannot open the update archive (corrupt or not a zip).');
        }

        try {
            $this->assertDiskSpace($zip, $dir);
            $realRoot = $this->normalize((string) realpath($dir));

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $this->extractEntry($zip, (string) $zip->getNameIndex($i), $dir, $realRoot);
            }
        } finally {
            $zip->close();
        }

        return $dir;
    }

    private function extractEntry(\ZipArchive $zip, string $name, string $dir, string $realRoot): void
    {
        $norm = str_replace('\\', '/', $name);
        if ($norm === '' || $norm === '/') {
            return;
        }

        $this->guardZipSlip($norm);

        $target = $dir.'/'.$norm;

        if (str_ends_with($norm, '/')) {
            $this->ensureDir($target);

            return;
        }

        $this->ensureDir(dirname($target));

        // Belt-and-suspenders: the resolved parent must remain inside the root.
        $realParent = $this->normalize((string) realpath(dirname($target)));
        if ($realParent === '' || ! $this->within($realRoot, $realParent)) {
            throw new UpdateException('Zip entry escapes the staging directory: '.$name);
        }

        $in = $zip->getStream($name);
        if ($in === false) {
            throw new UpdateException('Cannot read zip entry: '.$name);
        }
        $out = fopen($target, 'wb');
        if ($out === false) {
            fclose($in);
            throw new UpdateException('Cannot write extracted file: '.$norm);
        }
        try {
            stream_copy_to_stream($in, $out);
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    /** Reject absolute paths, drive letters, and any traversal segment. */
    private function guardZipSlip(string $norm): void
    {
        if (str_starts_with($norm, '/') || preg_match('#^[A-Za-z]:#', $norm)) {
            throw new UpdateException('Absolute path in archive rejected: '.$norm);
        }
        foreach (explode('/', $norm) as $segment) {
            if ($segment === '..') {
                throw new UpdateException('Path traversal in archive rejected: '.$norm);
            }
        }
    }

    private function assertDiskSpace(\ZipArchive $zip, string $dir): void
    {
        $needed = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $needed += (int) ($stat['size'] ?? 0);
        }

        $free = @disk_free_space($dir);
        if ($free !== false && $free < ($needed * 1.1) + (10 * 1024 * 1024)) {
            throw new UpdateException('Not enough free disk space to extract the update (need ~'
                .$this->bytes($needed).').');
        }
    }

    private function within(string $root, string $path): bool
    {
        $root = rtrim($root, '/');

        return $path === $root || str_starts_with($path, $root.'/');
    }

    private function normalize(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    private function ensureDir(string $dir): void
    {
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$entry;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    private function bytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pow = min((int) floor(log($bytes) / log(1024)), count($units) - 1);

        return round($bytes / (1024 ** $pow), 1).' '.$units[$pow];
    }
}
