<?php

namespace App\Services\Migration\Import;

use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\MigrationPaths;

/**
 * The folder where packages wait for an operator: uploaded, fetched from a link, or copied in by hand. It is a plain
 * directory outside the web root and outside every web-served storage path, and nothing in it is ever executed or
 * read as anything but a package.
 *
 * A web request names a package by its file NAME only, and the name has to look exactly like a package name. The
 * resolved path must lie inside the inbox and must not be a symbolic link (a link could point anywhere on the server).
 * The command line may name any readable file and does not use this class for that.
 */
final class ImportInbox
{
    public const NAME_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,149}\.oeparts$/';

    public function __construct(private readonly ?string $directory = null) {}

    public function directory(): string
    {
        return $this->directory ?? MigrationPaths::inbox();
    }

    public static function isPackageName(string $name): bool
    {
        return preg_match(self::NAME_PATTERN, $name) === 1 && ! str_contains($name, '..');
    }

    /**
     * The absolute path of a package in the inbox.
     *
     * @throws MigrationException package_not_found for a bad name, a missing file, a link or anything outside the inbox
     */
    public function resolve(string $name): string
    {
        $missing = new MigrationException(MigrationErrorCode::PackageNotFound, ['path' => basename($name)]);

        if (! self::isPackageName($name)) {
            throw $missing;
        }

        $root = realpath($this->directory());
        $candidate = $this->directory().DIRECTORY_SEPARATOR.$name;

        if ($root === false || is_link($candidate) || ! is_file($candidate) || ! is_readable($candidate)) {
            throw $missing;
        }

        $real = realpath($candidate);

        if ($real === false || ! str_starts_with($real, $root.DIRECTORY_SEPARATOR) || dirname($real) !== $root) {
            throw $missing;
        }

        return $real;
    }

    /** @return array<int, array{name: string, size: int, modified: int}> packages waiting, newest first */
    public function list(): array
    {
        $found = [];

        foreach (glob($this->directory().DIRECTORY_SEPARATOR.'*.oeparts') ?: [] as $path) {
            $name = basename($path);

            if (! self::isPackageName($name) || is_link($path) || ! is_file($path)) {
                continue;
            }

            $found[] = ['name' => $name, 'size' => (int) filesize($path), 'modified' => (int) filemtime($path)];
        }

        usort($found, static fn (array $a, array $b): int => $b['modified'] <=> $a['modified']);

        return $found;
    }
}
