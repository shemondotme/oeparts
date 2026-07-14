<?php

namespace App\Services\Imports\Stages;

use App\Models\ProductImportRun;
use App\Services\Imports\Contracts\ImportStage;
use App\Services\Imports\Exceptions\ImportException;
use App\Services\Imports\StageStepResult;
use App\Services\ProductImportService;
use Illuminate\Support\Facades\Storage;

/**
 * The chunked core of the import: reads up to config('imports.batch_size')
 * rows starting at a byte offset (fseek — O(1), no re-scan of already
 * processed lines), processes each via ProductImportService::processRow()
 * (the exact same validation/upsert logic the old synchronous importer
 * used, reused as-is), and persists the new offset as checkpoint state.
 * "Done" is detected by hitting EOF mid-batch — the same signal
 * DatabaseBackupStage uses (a short read means the source is exhausted).
 */
class ImportRowsStage implements ImportStage
{
    public function __construct(private readonly ProductImportService $importService) {}

    public function key(): string
    {
        return 'import_rows';
    }

    public function step(ProductImportRun $run, array $state): StageStepResult
    {
        $absolutePath = Storage::disk($run->disk)->path($run->path);
        $headers      = (array) $run->getMetaValue('headers', []);
        $headerCount  = count($headers);

        $offset    = (int) ($state['offset'] ?? $run->getMetaValue('data_start_offset', 0));
        $rowNumber = (int) ($state['row_number'] ?? 1); // 1 = header row, matches legacy numbering

        $handle = fopen($absolutePath, 'r');
        if ($handle === false) {
            throw new ImportException('Cannot open CSV file for reading.');
        }
        fseek($handle, $offset);

        $batchSize = (int) config('imports.batch_size', 500);
        $created = $updated = $skipped = $processedThisStep = 0;
        $eof = false;

        while ($processedThisStep < $batchSize) {
            $rawRow = fgetcsv($handle);
            if ($rawRow === false) {
                $eof = true;
                break;
            }
            $rowNumber++;

            if ($rawRow === [null]) {
                continue; // blank row — not counted as processed, matches legacy behaviour
            }

            $processedThisStep++;

            if (count($rawRow) !== $headerCount) {
                $run->addError($rowNumber, "expected {$headerCount} columns, got ".count($rawRow).'.');
                $skipped++;

                continue;
            }

            $record = array_combine($headers, array_map('trim', $rawRow));
            $result = $this->importService->processRow($record, $run->admin_id, $run->update_existing);

            match ($result) {
                'created' => $created++,
                'updated' => $updated++,
                'skipped' => $skipped++,
                default => (function () use ($run, $rowNumber, $result, &$skipped) {
                    $run->addError($rowNumber, $this->importService->stripErrorPrefix($result));
                    $skipped++;
                })(),
            };
        }

        $offset = ftell($handle);
        fclose($handle);

        $run->created_count   += $created;
        $run->updated_count   += $updated;
        $run->skipped_count   += $skipped;
        $run->processed_rows  += $processedThisStep;
        $run->save();

        if ($eof) {
            return StageStepResult::complete('Import complete.');
        }

        $totalNote = $run->total_rows ? " of ~{$run->total_rows}" : '';

        return StageStepResult::progress(
            ['offset' => $offset, 'row_number' => $rowNumber],
            "Processed {$run->processed_rows}{$totalNote} rows.",
        );
    }
}
