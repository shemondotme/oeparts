<?php

namespace App\Services\Migration\Export;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * The folder finished exports are kept in: `storage/app/migration/exports/<domain>-<date>.oeparts`, each
 * with a small plain `.json` beside it (what the file is, when it expires). A package is a full copy of
 * the shop's customer data, so it is kept only for a while (default 7 days) and every way of reaching one
 * by name goes through find(), which accepts nothing but a plain file name inside this folder.
 */
final class PackageOutbox
{
    private const NAME = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,149}\.oeparts$/';

    public function __construct(private readonly ?string $directory = null) {}

    public function directory(): string
    {
        return $this->directory ?? storage_path('app/migration/exports');
    }

    /** A fresh, unused path for an export of `$host`. */
    public function newPath(?string $host, ?\DateTimeInterface $now = null): string
    {
        $safe = trim((string) preg_replace('/[^a-z0-9.-]+/', '-', Str::lower((string) $host)), '-.');
        $safe = $safe === '' ? 'site' : substr($safe, 0, 80);
        $stamp = CarbonImmutable::instance($now ?? now())->format('Ymd-His');

        $base = $this->directory().DIRECTORY_SEPARATOR."{$safe}-{$stamp}";
        $path = $base.'.oeparts';

        for ($suffix = 2; file_exists($path) || file_exists($path.'.part'); $suffix++) {
            $path = $base."-{$suffix}.oeparts";
        }

        return $path;
    }

    /**
     * Record what a finished package is, beside it.
     *
     * @param  array<string, mixed>  $info
     */
    public function writeInfo(string $packagePath, array $info): void
    {
        file_put_contents($packagePath.'.json', json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * Finished packages, newest first.
     *
     * @return array<int, array{name: string, path: string, size: int, modified: int, info: array<string, mixed>}>
     */
    public function list(): array
    {
        $found = [];

        foreach (glob($this->directory().DIRECTORY_SEPARATOR.'*.oeparts') ?: [] as $path) {
            if (! is_file($path) || is_link($path) || preg_match(self::NAME, basename($path)) !== 1) {
                continue;
            }

            $info = [];
            if (is_file($path.'.json')) {
                $decoded = json_decode((string) file_get_contents($path.'.json'), true);
                $info = is_array($decoded) ? $decoded : [];
            }

            $found[] = ['name' => basename($path), 'path' => $path, 'size' => (int) filesize($path), 'modified' => (int) filemtime($path), 'info' => $info];
        }

        usort($found, static fn (array $a, array $b): int => $b['modified'] <=> $a['modified']);

        return $found;
    }

    /** The path of a package in this folder by its file name, or null for anything else (a path, a link, a stranger). */
    public function find(string $name): ?string
    {
        if (preg_match(self::NAME, $name) !== 1) {
            return null;
        }

        $path = $this->directory().DIRECTORY_SEPARATOR.$name;

        if (! is_file($path) || is_link($path)) {
            return null;
        }

        $real = realpath($path);
        $root = realpath($this->directory());

        return $real !== false && $root !== false && str_starts_with($real, $root.DIRECTORY_SEPARATOR) ? $path : null;
    }

    public function delete(string $name): bool
    {
        $path = $this->find($name);

        if ($path === null) {
            return false;
        }

        @unlink($path.'.json');

        return @unlink($path);
    }

    /**
     * Remove packages older than `$days`. Returns how many.
     */
    public function pruneOlderThan(int $days, ?\DateTimeInterface $now = null): int
    {
        if ($days <= 0) {
            return 0;
        }

        $cutoff = CarbonImmutable::instance($now ?? now())->subDays($days)->getTimestamp();
        $removed = 0;

        foreach ($this->list() as $package) {
            if ($package['modified'] < $cutoff && $this->delete($package['name'])) {
                $removed++;
            }
        }

        return $removed;
    }
}
