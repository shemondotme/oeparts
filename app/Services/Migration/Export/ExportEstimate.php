<?php

namespace App\Services\Migration\Export;

/**
 * How big an export will be and how long it will take, as a range and clearly an estimate.
 */
final class ExportEstimate
{
    /**
     * @param  array<string, array{rows: int, approximate: bool}>  $tables
     * @param  array{count: int, bytes: int, complete: bool}  $files  `complete` is false while the file count is still running
     */
    public function __construct(
        public readonly array $tables,
        public readonly int $totalRows,
        public readonly bool $rowsApproximate,
        public readonly int $databaseBytes,
        public readonly int $databaseCompressedBytes,
        public readonly array $files,
        public readonly int $filesCompressedBytes,
        public readonly int $minutesLow,
        public readonly int $minutesHigh,
        public readonly int $requiredFreeBytes,
    ) {}

    public function packageBytes(): int
    {
        return $this->databaseCompressedBytes + $this->filesCompressedBytes;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'tables' => $this->tables, 'total_rows' => $this->totalRows, 'rows_approximate' => $this->rowsApproximate,
            'database_bytes' => $this->databaseBytes, 'database_compressed_bytes' => $this->databaseCompressedBytes,
            'files' => $this->files, 'files_compressed_bytes' => $this->filesCompressedBytes,
            'minutes_low' => $this->minutesLow, 'minutes_high' => $this->minutesHigh, 'required_free_bytes' => $this->requiredFreeBytes,
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['tables'], $data['total_rows'], $data['rows_approximate'], $data['database_bytes'], $data['database_compressed_bytes'],
            $data['files'], $data['files_compressed_bytes'], $data['minutes_low'], $data['minutes_high'], $data['required_free_bytes']
        );
    }
}
