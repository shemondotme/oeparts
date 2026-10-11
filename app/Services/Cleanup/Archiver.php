<?php

namespace App\Services\Cleanup;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Optional "export before delete": appends the rows about to be removed to a CSV.gz
 * so an operator can recover them if retention turns out to have been set too short.
 *
 * Append-only, one gzip member per call (`gzopen 'ab9'`), so a resumed run simply keeps
 * appending and concatenated members still read back as one stream. Delivery is
 * AT-LEAST-ONCE: a crash between writing a batch and checkpointing it means the same
 * rows are written again on resume, so the reconciler accepts `archived >= deleted`.
 *
 * The archive holds whatever the table held, including personal data (IPs, emails,
 * search text). It lives on the private disk, is swept by its own cleanup target after
 * 30 days, and is only reachable through an authorised download route.
 *
 * Cells that begin with = + - @ (or a tab / carriage return) are prefixed with an
 * apostrophe so opening the file in a spreadsheet cannot execute attacker-supplied text
 * (search queries are user input) as a formula (OWASP "CSV injection").
 */
final class Archiver
{
    public const DIRECTORY = 'cleanup-archives';

    /** Refuse to archive more rows than this in one run: the file would be unmanageable. */
    public const MAX_ROWS = 5_000_000;

    /** Never let an archive eat more than this share of the free disk space. */
    public const MAX_FREE_SHARE = 0.25;

    public function __construct(
        private readonly string $disk,
        private readonly string $relativePath,
    ) {}

    public static function forRun(int $runId, string $target, string $disk = 'local'): self
    {
        return new self($disk, self::DIRECTORY.'/'.$runId.'/'.preg_replace('/[^a-z0-9_]/', '_', strtolower($target)).'.csv.gz');
    }

    /** Path relative to the disk root (what is stored on the item row). */
    public function path(): string
    {
        return $this->relativePath;
    }

    public function disk(): string
    {
        return $this->disk;
    }

    /**
     * Why archiving must be refused for this run, or null when it is fine.
     */
    public static function refusalReason(int $estimatedRows, ?int $freeBytes, int $avgRowBytes = 300): ?string
    {
        if ($estimatedRows > self::MAX_ROWS) {
            return 'too_many_rows_to_archive';
        }

        if ($freeBytes !== null && ($estimatedRows * $avgRowBytes) > ($freeBytes * self::MAX_FREE_SHARE)) {
            return 'archive_would_fill_disk';
        }

        return null;
    }

    /**
     * Append rows (list of associative arrays / objects) and return how many were written.
     *
     * @param  iterable<array<string, mixed>|object>  $rows
     */
    public function append(iterable $rows): int
    {
        $abs = Storage::disk($this->disk)->path($this->relativePath);
        $dir = dirname($abs);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException("Cannot create the archive directory {$dir}");
        }

        $isNew = ! is_file($abs) || filesize($abs) === 0;

        $buffer = fopen('php://temp', 'r+');
        $count = 0;
        $headerWritten = ! $isNew;

        foreach ($rows as $row) {
            $row = is_object($row) ? (array) $row : $row;

            if (! $headerWritten) {
                fputcsv($buffer, array_keys($row), ',', '"', '\\');
                $headerWritten = true;
            }

            fputcsv($buffer, array_map([$this, 'cell'], array_values($row)), ',', '"', '\\');
            $count++;
        }

        if ($count === 0) {
            fclose($buffer);

            return 0;
        }

        rewind($buffer);
        $csv = (string) stream_get_contents($buffer);
        fclose($buffer);

        $gz = gzopen($abs, 'ab9');
        if ($gz === false) {
            throw new RuntimeException("Cannot open the archive {$abs} for writing");
        }

        $written = gzwrite($gz, $csv);
        gzclose($gz);

        if ($written === false || $written < strlen($csv)) {
            throw new RuntimeException("Short write to the archive {$abs}");
        }

        return $count;
    }

    private function cell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value) || is_object($value)) {
            $value = (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $value = (string) $value;

        // A plain number such as -5 or +3.2 is harmless and must stay a number.
        if ($value !== '' && ! is_numeric($value) && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
