<?php

namespace App\Filament\Widgets\Concerns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

/**
 * Shared helpers that turn plain system-health numbers into modern
 * monitoring visuals — sparkline series, status-page uptime strips, and
 * live pulse dots — using only Filament-native Stat features
 * (->chart(), Htmlable ->description(), ->extraAttributes()).
 *
 * Two data sources:
 *  - hourlyTrend(): real historical counts from a log table (per hour).
 *  - rollingSamples()/rollingStates(): a bounded rolling window kept in the
 *    cache, sampled once per poll, for point-in-time metrics that keep no
 *    history of their own (disk %, cache key count, health check results).
 */
trait HasMonitoringVisuals
{
    /**
     * Real per-hour counts from a log table for the last $hours hours,
     * oldest→newest, suitable for a Stat sparkline.
     *
     * @return array<int, int>
     */
    protected function hourlyTrend(string $table, string $timeColumn = 'created_at', int $hours = 12): array
    {
        try {
            $since = now()->subHours($hours - 1)->startOfHour();

            $rows = DB::table($table)
                ->where($timeColumn, '>=', $since)
                ->selectRaw("DATE_FORMAT({$timeColumn}, '%Y-%m-%d %H') as hr, COUNT(*) as c")
                ->groupBy('hr')
                ->pluck('c', 'hr');

            $out = [];
            for ($i = $hours - 1; $i >= 0; $i--) {
                $out[] = (int) ($rows[now()->subHours($i)->format('Y-m-d H')] ?? 0);
            }

            return $out;
        } catch (\Throwable $e) {
            return array_fill(0, $hours, 0);
        }
    }

    /**
     * Append the current value to a bounded rolling window in the cache
     * (throttled to one sample per $everySeconds) and return the window,
     * for a live trend of a point-in-time metric.
     *
     * @return array<int, float|int>
     */
    protected function rollingSamples(string $key, float|int $value, int $max = 12, int $everySeconds = 45): array
    {
        $base = 'dash:spark:' . $key;
        $samples = Cache::get($base, []);
        $lastAt = (int) Cache::get($base . ':at', 0);
        $now = now()->getTimestamp();

        if ($now - $lastAt >= $everySeconds) {
            $samples[] = round((float) $value, 2);
            if (count($samples) > $max) {
                $samples = array_slice($samples, -$max);
            }
            Cache::put($base, $samples, now()->addDay());
            Cache::put($base . ':at', $now, now()->addDay());
        }

        // A sparkline needs at least two points to draw a line, not a dot.
        if (count($samples) === 1) {
            return [$samples[0], $samples[0]];
        }

        return $samples ?: [0, 0];
    }

    /**
     * Map a Filament semantic color to a health-accent state used for the
     * card's left-edge status accent (op-health-*).
     */
    protected function colorToState(string $color): string
    {
        return match ($color) {
            'success' => 'ok',
            'warning' => 'warn',
            'danger' => 'down',
            default => 'muted',
        };
    }

    /**
     * A conditional "alert" left-edge accent for a metric Stat: red for a
     * danger state, amber for a warning, nothing when healthy — so a normal
     * dashboard stays clean and only real problems draw the eye. Reuses the
     * shared op-health-* accent classes.
     *
     * @return array<string, string>
     */
    protected function alertAccent(?string $level): array
    {
        return match ($level) {
            'danger' => ['class' => 'op-health-down'],
            'warning' => ['class' => 'op-health-warn'],
            default => [],
        };
    }

    /**
     * Render a Stat description as raw HTML: an optional live pulse dot plus
     * the text line. Returned as HtmlString so Filament renders it unescaped
     * inside the Stat description.
     */
    protected function statusDescription(string $text, bool $live = false): HtmlString
    {
        $dot = $live ? '<span class="op-dot op-dot-live"></span>' : '';

        return new HtmlString(
            '<span class="op-status-line">' . $dot . e($text) . '</span>'
        );
    }
}
