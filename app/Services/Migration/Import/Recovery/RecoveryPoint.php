<?php

namespace App\Services\Migration\Import\Recovery;

use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\RowDecoder;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\MigrationPaths;
use App\Support\AtomicJsonFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * The way back: a copy of every table of the site (except the system ones) exactly as it was just before an import
 * touched it, kept in the run's own private directory (storage/app/migration/runs/<id>/recovery), plus the .env keys the
 * import will change.
 *
 * A recovery point is the very same row format as a package (header line, then one JSON array per row, gzip), so the code
 * that loads a package loads this too. Its manifest records, for every segment, its size, row count and SHA-256, and for
 * every table the rows that were copied: that is what verify() checks, what arm compares with the live site (a site that
 * changed while the copy was made is refused rather than rolled back to a stale picture) and what rollback replays.
 *
 * It is NOT encrypted: it holds the site's own rows as stored (secrets stay as the ciphertext they already are under this
 * site's key), in a directory no web server serves and no cleanup touches, readable only by the site's user.
 */
final class RecoveryPoint
{
    public const SEGMENT_SUFFIX = '.ndjson.gz';

    public function __construct(private readonly string $directory) {}

    public static function of(string $runId, ?ImportStateStore $store = null): self
    {
        return new self(($store ?? new ImportStateStore)->directory($runId).DIRECTORY_SEPARATOR.'recovery');
    }

    public function directory(): string
    {
        return $this->directory;
    }

    public function ensure(): void
    {
        MigrationPaths::ensureDirectory($this->directory);
    }

    public function manifest(): AtomicJsonFile
    {
        return new AtomicJsonFile($this->directory.DIRECTORY_SEPARATOR.'manifest.json');
    }

    /** @return array<string, mixed>|null */
    public function read(): ?array
    {
        return is_dir($this->directory) ? $this->manifest()->read() : null;
    }

    public function isComplete(): bool
    {
        try {
            return (bool) ($this->read()['complete'] ?? false);
        } catch (\RuntimeException) {
            return false;
        }
    }

    /** Where the segment of a part lives. */
    public function path(string $file): string
    {
        if (preg_match('/^[A-Za-z0-9_.-]+$/', $file) !== 1 || str_contains($file, '..')) {
            throw new \InvalidArgumentException('Not a recovery segment name.');
        }

        return $this->directory.DIRECTORY_SEPARATOR.$file;
    }

    /**
     * Every part of a table, in order.
     *
     * @return array<int, array<string, mixed>>
     */
    public function partsOf(string $table): array
    {
        $parts = array_values(array_filter((array) ($this->read()['parts'] ?? []), static fn (array $p): bool => ($p['table'] ?? '') === $table));
        usort($parts, static fn (array $a, array $b): int => $a['segment'] <=> $b['segment']);

        return $parts;
    }

    /** @return array<int, string> the tables the recovery point holds, in the order they were copied (children first) */
    public function tables(): array
    {
        return array_keys((array) ($this->read()['tables'] ?? []));
    }

    /**
     * The row lines of one part (the header line is checked and skipped), streamed: a 64 MB segment is never in memory.
     *
     * @param  array<string, mixed>  $part
     * @return \Generator<int, string>
     */
    public function lines(array $part): \Generator
    {
        $handle = @gzopen($this->path((string) $part['file']), 'rb');

        if ($handle === false) {
            throw new MigrationException(MigrationErrorCode::PackageCorrupt, ['part' => 'recovery '.$part['file']]);
        }

        try {
            $first = true;

            while (($line = gzgets($handle)) !== false) {
                $line = rtrim($line, "\n");

                if ($first) {
                    $first = false;
                    RowDecoder::fromHeader($line); // refuses a segment that is not the row format

                    continue;
                }

                if ($line !== '') {
                    yield $line;
                }
            }
        } finally {
            gzclose($handle);
        }
    }

    /** @param  array<string, mixed>  $part */
    public function decoderOf(array $part): RowDecoder
    {
        $handle = @gzopen($this->path((string) $part['file']), 'rb');

        if ($handle === false) {
            throw new MigrationException(MigrationErrorCode::PackageCorrupt, ['part' => 'recovery '.$part['file']]);
        }

        try {
            return RowDecoder::fromHeader(rtrim((string) gzgets($handle), "\n"));
        } finally {
            gzclose($handle);
        }
    }

    /**
     * Tables whose row count now differs from what was copied: the site changed while (or after) the way back was made,
     * so rolling back to it would lose those rows. Compared before the site is closed.
     *
     * @return array<string, array{copied: int, now: int}>
     */
    public function drift(): array
    {
        $drift = [];

        foreach ((array) ($this->read()['tables'] ?? []) as $table => $copied) {
            $now = (int) DB::table((string) $table)->count();

            if ($now !== (int) $copied) {
                $drift[(string) $table] = ['copied' => (int) $copied, 'now' => $now];
            }
        }

        return $drift;
    }

    /** Remove the whole recovery point (the import finished, or was discarded). */
    public function destroy(): void
    {
        File::deleteDirectory($this->directory);
    }
}
