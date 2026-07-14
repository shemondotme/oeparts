<?php

namespace App\Services\Imports;

use App\Models\Language;
use App\Services\ProductImportService;

/**
 * Generates the two downloadable CSV templates for the Import page. Both are
 * built dynamically from ProductImportService's own column contract and the
 * app's active languages — never hand-maintained, so they can't go stale
 * (unlike the old inline help-text hint, which already didn't match the
 * real required columns).
 */
class ProductImportTemplateService
{
    /** Required columns only, plus one example row — for a first-time / simple import. */
    public function quickCsv(): string
    {
        return $this->toCsv([
            ProductImportService::REQUIRED_COLUMNS,
            $this->exampleRow(ProductImportService::REQUIRED_COLUMNS),
        ]);
    }

    /** Every column the importer understands, including a name/description pair per active language. */
    public function fullCsv(): string
    {
        $headers = ProductImportService::REQUIRED_COLUMNS;

        $languageCodes = Language::where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('code')
            ->all();

        if (empty($languageCodes)) {
            $languageCodes = ProductImportService::LANGUAGES;
        }

        foreach ($languageCodes as $code) {
            $headers[] = "name_{$code}";
        }
        foreach ($languageCodes as $code) {
            $headers[] = "description_{$code}";
        }

        array_push($headers, ...ProductImportService::OPTIONAL_COLUMNS);

        return $this->toCsv([$headers, $this->exampleRow($headers)]);
    }

    /** One realistic example row; only the required columns get real sample values, everything else stays blank. */
    private function exampleRow(array $headers): array
    {
        $samples = [
            'oem_number'        => '0242229799',
            'manufacturer_slug' => 'bmw',
            'condition_slug'    => 'new',
            'price'             => '49.99',
            'is_in_stock'       => '1',
        ];

        return array_map(fn (string $col) => $samples[$col] ?? '', $headers);
    }

    /** @param  array<int, array<int, string>>  $rows */
    private function toCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
