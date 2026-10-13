<?php

namespace App\Services\Migration\Package;

use App\Support\AtomicJsonFile;

/**
 * The bookkeeping that lets a half-written package be resumed after a crash, a killed process or
 * a closed terminal, at entry granularity.
 *
 *   <package>.part.ledger/state.json     the COMMIT POINT: how many bytes of the .part are good,
 *                                        the next entry id, the running whole-file hash
 *   <package>.part.ledger/entries.ndjson one line per committed entry (its table-of-contents record)
 *
 * Order per entry: frames written and fsynced, ledger line appended, then state.json replaced.
 * state.json is written last, so it is the only truth: a crash before it leaves bytes beyond
 * committed_offset (cut off on resume) and possibly one ledger line for an entry the state never
 * acknowledged (dropped on resume). The entry is simply written again.
 */
final class WriterLedger
{
    public function __construct(private readonly string $directory) {}

    public function directory(): string
    {
        return $this->directory;
    }

    public function state(): AtomicJsonFile
    {
        return new AtomicJsonFile($this->directory.'/state.json');
    }

    public function exists(): bool
    {
        return is_file($this->directory.'/state.json') || is_file($this->directory.'/state.json.prev');
    }

    /** @param  array<string, mixed>  $record */
    public function appendEntry(array $record): void
    {
        if (! is_dir($this->directory) && ! @mkdir($this->directory, 0775, true) && ! is_dir($this->directory)) {
            throw new \RuntimeException("Cannot create the writer ledger directory {$this->directory}.");
        }

        $handle = @fopen($this->directory.'/entries.ndjson', 'ab');
        if ($handle === false) {
            throw new \RuntimeException('Cannot open the writer ledger.');
        }

        try {
            flock($handle, LOCK_EX);
            $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";

            if (fwrite($handle, $line) !== strlen($line)) {
                throw new \RuntimeException('Short write to the writer ledger: the disk may be full.');
            }

            fflush($handle);
            if (function_exists('fsync')) {
                @fsync($handle);
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * Records of committed entries, keeping only entries the state acknowledged.
     *
     * @return array<int, array<string, mixed>>
     */
    public function entries(int $nextEntryId): array
    {
        $path = $this->directory.'/entries.ndjson';
        if (! is_file($path)) {
            return [];
        }

        $records = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $record = json_decode($line, true);

            if (is_array($record) && isset($record['id']) && (int) $record['id'] < $nextEntryId) {
                $records[(int) $record['id']] = $record;
            }
        }

        ksort($records);

        return array_values($records);
    }

    /** Rewrite the entries file without lines the state never acknowledged. */
    public function keepOnly(int $nextEntryId): void
    {
        $kept = $this->entries($nextEntryId);
        $path = $this->directory.'/entries.ndjson';
        $body = '';

        foreach ($kept as $record) {
            $body .= json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
        }

        if (@file_put_contents($path, $body, LOCK_EX) === false) {
            throw new \RuntimeException('Cannot rewrite the writer ledger.');
        }
    }

    public function destroy(): void
    {
        foreach (glob($this->directory.'/{,.}*', GLOB_BRACE) ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        @rmdir($this->directory);
    }
}
