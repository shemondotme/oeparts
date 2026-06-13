<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Shared date range logic for all report pages.
 */
final class ReportDateRangeService
{
    /**
     * Get the start date based on a period string.
     */
    public static function startDate(string $period): Carbon
    {
        return Carbon::now()->subDays((int) $period);
    }

    /**
     * Available period options for report filter dropdowns.
     */
    public static function periodOptions(): array
    {
        return [
            '7' => 'Last 7 Days',
            '30' => 'Last 30 Days',
            '90' => 'Last 90 Days',
            '365' => 'Last 12 Months',
        ];
    }

    /**
     * Convert period to human-readable label.
     */
    public static function periodLabel(string $period): string
    {
        return self::periodOptions()[$period] ?? "{$period} Days";
    }

    /**
     * Generate a CSV export string from an array of data.
     */
    public static function toCsv(array $headers, array $rows): string
    {
        $csv = implode(',', array_map(fn ($h) => '"' . str_replace('"', '""', $h) . '"', $headers)) . "\n";

        foreach ($rows as $row) {
            $csv .= implode(',', array_map(function ($v) {
                $value = (string) $v;
                if (preg_match('/^[=+\-@\t\r]/', $value)) {
                    $value = "'" . $value;
                }
                return '"' . str_replace('"', '""', $value) . '"';
            }, $row)) . "\n";
        }

        return $csv;
    }

    /**
     * Save CSV to storage and return the filename.
     */
    public static function saveCsv(string $csv, string $prefix): string
    {
        $filename = $prefix . '_export_' . now()->format('Y-m-d_His') . '.csv';
        $path = storage_path('app/exports/' . $filename);

        if (!is_dir(storage_path('app/exports'))) {
            mkdir(storage_path('app/exports'), 0755, true);
        }

        try {
            file_put_contents($path, $csv, LOCK_EX);
        } catch (\Exception $e) {
            Log::error('Failed to save CSV export', [
                'prefix' => $prefix,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $filename;
    }
}
