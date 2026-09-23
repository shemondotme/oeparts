<?php

namespace App\Services\Imports\Stages;

use App\Models\ProductImportRun;
use App\Services\Imports\Contracts\ImportStage;
use App\Services\Imports\Exceptions\ImportException;
use App\Services\Imports\StageStepResult;
use App\Services\ProductImportService;
use Illuminate\Support\Facades\Storage;

/**
 * One-step stage: opens the uploaded CSV, validates the required headers
 * (reusing ProductImportService::validateHeaders(), the exact same rule the
 * old synchronous import used), and does a cheap full-file newline scan for
 * an approximate row count (progress-bar denominator only — NOT relied on
 * for correctness; ImportRowsStage detects real completion via EOF).
 *
 * headers / data_start_offset are written onto $run->meta directly (not this
 * stage's own checkpoint state) because ImportRowsStage — a LATER stage —
 * needs them, and checkpoint state is discarded once a stage completes.
 */
class ValidateHeaderStage implements ImportStage
{
    public function __construct(private readonly ProductImportService $importService) {}

    public function key(): string
    {
        return 'validate_header';
    }

    public function step(ProductImportRun $run, array $state): StageStepResult
    {
        $absolutePath = Storage::disk($run->disk)->path($run->path);

        if (! is_file($absolutePath)) {
            throw new ImportException('Uploaded CSV file is missing.');
        }

        $fileHash = hash_file('sha256', $absolutePath);
        $this->importService->checkDuplicateFileWarning($fileHash);
        $run->setMetaValue('file_hash', $fileHash);

        $handle = fopen($absolutePath, 'r');
        if ($handle === false) {
            throw new ImportException('Cannot open CSV file for reading.');
        }

        $rawHeaders = fgetcsv($handle);
        if ($rawHeaders === false || $rawHeaders === null) {
            fclose($handle);
            throw new ImportException('CSV file appears to be empty.');
        }

        // Strip a UTF-8 BOM from the first header cell. fgetcsv() does not
        // strip it and trim() does not treat it as whitespace — Excel's
        // "CSV UTF-8" export always prepends one, which would otherwise
        // silently fail to match the first REQUIRED_COLUMNS entry even
        // though the file is entirely valid (same bug independently found
        // and fixed in ImportRedirectsFromCsv, Phase 19).
        if (isset($rawHeaders[0])) {
            $rawHeaders[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $rawHeaders[0]);
        }

        $headers = array_map('trim', $rawHeaders);
        $errors = $this->importService->validateHeaders($headers);
        if (! empty($errors)) {
            fclose($handle);
            throw new ImportException('Invalid CSV headers: '.implode('; ', $errors));
        }

        $dataStartOffset = ftell($handle);
        fclose($handle);

        $totalRows = $this->countDataRows($absolutePath, $dataStartOffset);

        $run->setMetaValue('headers', $headers);
        $run->setMetaValue('data_start_offset', $dataStartOffset);
        $run->total_rows = $totalRows;
        $run->save();

        return StageStepResult::complete('Header validated — ~'.number_format($totalRows).' rows found.');
    }

    /** Fast newline count over 1MB blocks (same chunk size the Update downloader streams with) — an estimate, never parses CSV cells. */
    private function countDataRows(string $path, int $fromOffset): int
    {
        $handle = fopen($path, 'r');
        fseek($handle, $fromOffset);

        $count = 0;
        while (! feof($handle)) {
            $chunk = fread($handle, 1 << 20);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $count += substr_count($chunk, "\n");
        }
        fclose($handle);

        return $count;
    }
}
