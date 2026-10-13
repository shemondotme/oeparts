<?php

namespace App\Support;

/**
 * PHP's "shorthand" sizes ("256M", "1G", "512k", "-1", "1048576") as bytes, and bytes as text.
 * The preflight compares upload_max_filesize, post_max_size and memory_limit with what a
 * package needs, and every one of them is written this way in php.ini.
 */
final class PhpSize
{
    /** Bytes for a php.ini size. "-1" (unlimited) is PHP_INT_MAX; anything unreadable is 0. */
    public static function toBytes(string|int|false|null $value): int
    {
        if ($value === false || $value === null) {
            return 0;
        }

        $text = strtolower(trim((string) $value));

        if ($text === '-1') {
            return PHP_INT_MAX;
        }

        if (! preg_match('/^(\d+)\s*([kmg])?b?$/', $text, $m)) {
            return 0;
        }

        $number = (int) $m[1];

        return match ($m[2] ?? '') {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /** "1.5 GB", "256 MB", "12 KB", "0 B": one decimal at most, no trailing ".0". */
    public static function format(int $bytes): string
    {
        if ($bytes === PHP_INT_MAX) {
            return 'unlimited';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) max(0, $bytes);
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        $rounded = rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');

        return $rounded.' '.$units[$unit];
    }
}
