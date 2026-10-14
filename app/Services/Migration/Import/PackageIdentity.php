<?php

namespace App\Services\Migration\Import;

use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Package\PackageFormat;
use App\Services\Migration\Package\PackageLimits;

/**
 * Which file, exactly, a run is importing. Taken when the package is first located and checked again before anything
 * is changed: a file that was swapped, appended to, truncated or even just touched between "verified" and "go" must
 * stop the run while it is still harmless, not be imported on the strength of a check made of a different file.
 *
 * Cheap on purpose (a stat, the 128 header bytes and the 64 trailer bytes), because it runs on every advance. The
 * full integrity pass is a separate stage; this only proves it is still the file that passed it.
 */
final class PackageIdentity
{
    /**
     * @return array<string, mixed>
     */
    public static function capture(string $path, ?string $fingerprint = null): array
    {
        clearstatcache(true, $path);
        $stat = @stat($path);

        if ($stat === false) {
            throw new MigrationException(MigrationErrorCode::PackageNotFound, ['path' => basename($path)]);
        }

        [$header, $trailer] = self::edges($path, (int) $stat['size']);

        return [
            'size' => (int) $stat['size'],
            'mtime' => (int) $stat['mtime'],
            // Windows reports 0; a value of 0 on either side is simply not compared.
            'inode' => (int) $stat['ino'],
            'device' => (int) $stat['dev'],
            'header_sha256' => hash('sha256', $header),
            'trailer_sha256' => hash('sha256', $trailer),
            'file_sha256' => bin2hex(substr($trailer, 24, 32)),
            'package_id' => self::packageIdOf($header),
            'fingerprint' => $fingerprint,
        ];
    }

    /**
     * @param  array<string, mixed>  $pinned
     *
     * @throws MigrationException package_changed, naming what differs
     */
    public static function assertUnchanged(string $path, array $pinned): void
    {
        $now = self::capture($path);
        $different = [];

        foreach (['size', 'mtime', 'header_sha256', 'trailer_sha256', 'package_id'] as $key) {
            if (($pinned[$key] ?? null) !== $now[$key]) {
                $different[] = $key;
            }
        }

        foreach (['inode', 'device'] as $key) {
            $then = (int) ($pinned[$key] ?? 0);

            if ($then !== 0 && $now[$key] !== 0 && $then !== $now[$key]) {
                $different[] = $key;
            }
        }

        if ($different !== []) {
            throw new MigrationException(MigrationErrorCode::PackageChanged, ['what' => implode(', ', $different)]);
        }
    }

    /** @return array{0: string, 1: string} the first HEADER_LEN bytes and the last TRAILER_LEN bytes */
    private static function edges(string $path, int $size): array
    {
        if ($size < PackageFormat::HEADER_LEN + PackageLimits::TRAILER_LEN) {
            throw new MigrationException(MigrationErrorCode::PackageIncomplete, ['path' => basename($path)]);
        }

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw new MigrationException(MigrationErrorCode::PackageNotFound, ['path' => basename($path)]);
        }

        try {
            $header = (string) fread($handle, PackageFormat::HEADER_LEN);
            fseek($handle, $size - PackageLimits::TRAILER_LEN);
            $trailer = (string) fread($handle, PackageLimits::TRAILER_LEN);
        } finally {
            fclose($handle);
        }

        if (strlen($header) !== PackageFormat::HEADER_LEN || strlen($trailer) !== PackageLimits::TRAILER_LEN) {
            throw new MigrationException(MigrationErrorCode::PackageIncomplete, ['path' => basename($path)]);
        }

        return [$header, $trailer];
    }

    /** The package id sits at byte 56 of the header. Only the signature is checked here: the real parse is the reader's job. */
    private static function packageIdOf(string $header): string
    {
        if (! str_starts_with($header, PackageFormat::MAGIC)) {
            throw new MigrationException(MigrationErrorCode::PackageNotOeparts);
        }

        return bin2hex(substr($header, self::PACKAGE_ID_OFFSET, PackageFormat::PACKAGE_ID_LEN));
    }

    private const PACKAGE_ID_OFFSET = 56;
}
