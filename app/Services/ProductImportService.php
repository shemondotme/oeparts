<?php

namespace App\Services;

use App\Enums\BulkUpdateAction;
use App\Enums\ProductCondition;
use App\Models\BulkUpdateLog;
use App\Models\InventoryLog;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductCrossReference;
use Illuminate\Support\Facades\Log;

class ProductImportService
{
    private const REQUIRED_COLUMNS = ['oem_number', 'manufacturer_slug', 'condition_slug', 'price', 'is_in_stock'];

    private const LANGUAGES = ['en', 'de', 'lt', 'fr', 'es'];

    /** @var array<string, Manufacturer|null> */
    private array $manufacturerCache = [];

    /** @var array<string, ProductCondition|null> */
    private array $conditionCache = [];  // slug → enum case or null

    public function __construct(private OemNormalizerService $normalizer) {}

    /**
     * Process an uploaded CSV file path.
     *
     * @return array{created: int, updated: int, skipped: int, errors: string[]}
     *
     * @throws \RuntimeException for file-level failures (bad file, missing headers)
     */
    public function process(string $absolutePath, int $adminId, bool $updateExisting): array
    {
        $handle = fopen($absolutePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Cannot open CSV file for reading.');
        }

        $rawHeaders = fgetcsv($handle);
        if ($rawHeaders === false || $rawHeaders === null) {
            fclose($handle);
            throw new \RuntimeException('CSV file appears to be empty.');
        }

        $headers = array_map('trim', $rawHeaders);
        $headerErrs = $this->validateHeaders($headers);
        if (! empty($headerErrs)) {
            fclose($handle);
            throw new \RuntimeException('Invalid CSV headers: '.implode('; ', $headerErrs));
        }

        $headerCount = count($headers);
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $rowErrors = [];
        $rowNum = 1; // 1 = header row

        while (($rawRow = fgetcsv($handle)) !== false) {
            $rowNum++;

            // Skip completely blank rows
            if ($rawRow === [null]) {
                continue;
            }

            if (count($rawRow) !== $headerCount) {
                $rowErrors[] = "Row {$rowNum}: expected {$headerCount} columns, got ".count($rawRow).'.';
                $skipped++;

                continue;
            }

            $record = array_combine($headers, array_map('trim', $rawRow));
            $result = $this->processRow($record, $adminId, $updateExisting);

            match ($result) {
                'created' => $created++,
                'updated' => $updated++,
                'skipped' => $skipped++,
                default => (function () use ($result, $rowNum, &$rowErrors, &$skipped) {
                    $rowErrors[] = "Row {$rowNum}: ".ltrim($result, 'error:');
                    $skipped++;
                })(),
            };
        }

        fclose($handle);
        $this->logBulkAction($adminId, $created + $updated, $created, $updated, $skipped, $rowErrors);

        return compact('created', 'updated', 'skipped', 'rowErrors');
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function validateHeaders(array $headers): array
    {
        $missing = [];
        foreach (self::REQUIRED_COLUMNS as $col) {
            if (! in_array($col, $headers, true)) {
                $missing[] = "missing column '{$col}'";
            }
        }

        return $missing;
    }

    /**
     * @return 'created'|'updated'|'skipped'|string string starting with 'error:' on failure
     */
    private function processRow(array $record, int $adminId, bool $updateExisting): string
    {
        // ── required-field presence ──────────────────────────────────────────
        foreach (self::REQUIRED_COLUMNS as $col) {
            if ($record[$col] === '') {
                return "error:{$col} is empty";
            }
        }

        // ── condition ────────────────────────────────────────────────────────
        $condition = $this->resolveCondition($record['condition_slug']);
        if ($condition === null) {
            return "error:condition slug '{$record['condition_slug']}' not found";
        }

        // ── price ────────────────────────────────────────────────────────────
        if (! is_numeric($record['price']) || bccomp((string) $record['price'], '0', 2) < 0) {
            return "error:invalid price '{$record['price']}'";
        }
        $price = bcadd((string) $record['price'], '0', 2); // normalize to 2dp via bcmath

        // ── is_in_stock ──────────────────────────────────────────────────────
        if (! in_array(strtolower($record['is_in_stock']), ['0', '1', 'true', 'false'], true)) {
            return 'error:is_in_stock must be 0 or 1';
        }
        $isInStock = in_array(strtolower($record['is_in_stock']), ['1', 'true'], true);

        // ── manufacturer ─────────────────────────────────────────────────────
        $manufacturer = $this->resolveManufacturer($record['manufacturer_slug']);
        if ($manufacturer === null) {
            return "error:manufacturer slug '{$record['manufacturer_slug']}' not found";
        }

        // ── moq ──────────────────────────────────────────────────────────────
        $moq = 1;
        if (! empty($record['moq'] ?? '')) {
            if (! is_numeric($record['moq']) || (int) $record['moq'] < 1) {
                return 'error:moq must be a positive integer';
            }
            $moq = (int) $record['moq'];
        }

        $normalizedOem = $this->normalizer->normalize($record['oem_number']);
        $deliveryTime = ($record['delivery_time'] ?? '') !== '' ? $record['delivery_time'] : null;
        $name = $this->buildJsonField($record, 'name', $record['oem_number']);
        $description = $this->buildJsonField($record, 'description');

        // ── upsert ───────────────────────────────────────────────────────────
        $existing = Product::where('manufacturer_id', $manufacturer->id)
            ->where('normalized_oem', $normalizedOem)
            ->first();

        if ($existing !== null) {
            if (! $updateExisting) {
                return 'skipped';
            }

            $oldStock = $existing->is_in_stock;

            $existing->update([
                'oem_number' => $record['oem_number'],
                'normalized_oem' => $normalizedOem,
                'name' => $name,
                'description' => $description ?? $existing->description,
                'condition' => $condition->value,
                'price' => $price,
                'delivery_time' => $deliveryTime,
                'moq' => $moq,
                'is_in_stock' => $isInStock,
            ]);

            if ((bool) $oldStock !== $isInStock) {
                InventoryLog::create([
                    'product_id' => $existing->id,
                    'admin_id' => $adminId,
                    'change_type' => 'csv_import',
                    'old_status' => $oldStock,
                    'new_status' => $isInStock,
                    'note' => 'Stock updated via CSV import',
                ]);
            }

            $this->processCrossReferences($existing->id, $record['cross_oem_numbers'] ?? '');

            return 'updated';
        }

        // ── create ───────────────────────────────────────────────────────────
        $product = Product::create([
            'manufacturer_id' => $manufacturer->id,
            'oem_number' => $record['oem_number'],
            'normalized_oem' => $normalizedOem,
            'name' => $name,
            'description' => $description,
            'condition_id' => $condition->id,
            'price' => $price,
            'delivery_time' => $deliveryTime,
            'moq' => $moq,
            'is_in_stock' => $isInStock,
            'is_active' => true,
        ]);

        InventoryLog::create([
            'product_id' => $product->id,
            'admin_id' => $adminId,
            'change_type' => 'csv_import',
            'old_status' => false,
            'new_status' => $isInStock,
            'note' => 'Created via CSV import',
        ]);

        $this->processCrossReferences($product->id, $record['cross_oem_numbers'] ?? '');

        return 'created';
    }

    /**
     * Build a JSON language array from columns like name_en, name_de, …
     * Falls back to $fallback for the 'en' key if all are empty.
     */
    private function buildJsonField(array $record, string $field, string $fallback = ''): ?array
    {
        $result = [];
        foreach (self::LANGUAGES as $lang) {
            $value = $record["{$field}_{$lang}"] ?? '';
            if ($value !== '') {
                $result[$lang] = $value;
            }
        }

        if (empty($result)) {
            return $fallback !== '' ? ['en' => $fallback] : null;
        }

        return $result;
    }

    /**
     * Pipe-separated cross OEM numbers, e.g. "0242229799|0242240650"
     */
    private function processCrossReferences(int $productId, string $raw): void
    {
        if ($raw === '') {
            return;
        }

        foreach (array_filter(array_map('trim', explode('|', $raw))) as $crossOem) {
            $normalizedCross = $this->normalizer->normalize($crossOem);
            ProductCrossReference::firstOrCreate(
                ['product_id' => $productId, 'normalized_cross_oem' => $normalizedCross],
                ['cross_oem_number' => $crossOem],
            );
        }
    }

    private function resolveManufacturer(string $slug): ?Manufacturer
    {
        if (! array_key_exists($slug, $this->manufacturerCache)) {
            $this->manufacturerCache[$slug] = Manufacturer::where('slug', $slug)->first();
        }

        return $this->manufacturerCache[$slug];
    }

    private function resolveCondition(string $slug): ?ProductCondition
    {
        if (! array_key_exists($slug, $this->conditionCache)) {
            $this->conditionCache[$slug] = ProductCondition::tryFrom($slug);
        }

        return $this->conditionCache[$slug];
    }

    private function logBulkAction(int $adminId, int $affected, int $created, int $updated, int $skipped, array $errors): void
    {
        try {
            BulkUpdateLog::create([
                'admin_id' => $adminId,
                'action_type' => BulkUpdateAction::Import->value,
                'affected_rows_count' => $affected,
                'payload' => [
                    'created' => $created,
                    'updated' => $updated,
                    'skipped' => $skipped,
                    'errors' => array_slice($errors, 0, 200),
                ],
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Could not write BulkUpdateLog after CSV import', ['error' => $e->getMessage()]);
        }
    }
}
