<?php

namespace App\Services;

use App\Models\CoreWebVitalsSnapshot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Real-user LCP/INP/CLS via the public Chrome UX Report (CrUX) API — free,
 * API-key-only (no OAuth), with a hard 150 req/min/project cap that cannot
 * be paid-upgraded. Queried at the origin level (not a specific URL) so a
 * low-traffic catalog still has a chance of having enough Chrome data —
 * CrUX can return "insufficient data" (HTTP 404) for either scope, which
 * is a real platform limitation, not a bug in this integration.
 */
class CoreWebVitalsService
{
    /** 2026 Google "Good" thresholds — LCP tightened from 2.5s to 2.0s. */
    private const LCP_GOOD_MS = 2000;

    private const LCP_NEEDS_IMPROVEMENT_MS = 4000;

    private const CLS_GOOD = 0.1;

    private const CLS_NEEDS_IMPROVEMENT = 0.25;

    private const INP_GOOD_MS = 200;

    private const INP_NEEDS_IMPROVEMENT_MS = 500;

    public function isConfigured(): bool
    {
        return trim((string) settings('seo.crux_api_key', '')) !== '';
    }

    /**
     * @return array{lcp_ms: ?int, cls: ?float, inp_ms: ?int}|array{insufficientData: true}|array{error: string}
     */
    public function getMetrics(): array
    {
        try {
            $key = trim((string) settings('seo.crux_api_key', ''));
            $origin = $this->resolveOrigin();

            $response = Http::timeout(15)->post(
                "https://chromeuxreport.googleapis.com/v1/records:queryRecord?key={$key}",
                ['origin' => $origin]
            );

            if ($response->status() === 404) {
                return ['insufficientData' => true];
            }

            if (! $response->successful()) {
                return ['error' => "CrUX API returned HTTP {$response->status()}"];
            }

            $metrics = $response->json('record.metrics', []);

            $lcp = $this->extractP75($metrics, 'largest_contentful_paint');
            $cls = $this->extractP75($metrics, 'cumulative_layout_shift');
            $inp = $this->extractP75($metrics, 'interaction_to_next_paint');

            return [
                'lcp_ms' => $lcp === null ? null : (int) $lcp,
                'cls' => $cls === null ? null : (float) $cls,
                'inp_ms' => $inp === null ? null : (int) $inp,
            ];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Records a snapshot row for trend tracking — CrUX's own 28-day rolling
     * window barely moves day to day, so this is meant to be scheduled
     * weekly (routes/console.php), not on every dashboard view. Throttled
     * the same way CacheMetricsService::snapshot() is, so a manual run
     * (`php artisan cwv:snapshot`) right after the scheduled one is a no-op
     * rather than a duplicate row. Silently skips storing anything when
     * unconfigured, erroring, or CrUX has no data yet — a trend chart
     * should never plot a gap as a data point.
     */
    public function snapshot(): array
    {
        $metrics = $this->getMetrics();

        if (isset($metrics['error']) || isset($metrics['insufficientData']) || ! $this->isConfigured()) {
            return $metrics;
        }

        if (! Cache::add('cwv:snapshot_lock', 1, 3600)) {
            return $metrics;
        }

        CoreWebVitalsSnapshot::create([
            'lcp_ms' => $metrics['lcp_ms'],
            'cls' => $metrics['cls'],
            'inp_ms' => $metrics['inp_ms'],
            'lcp_rating' => $this->lcpRating($metrics['lcp_ms']),
            'cls_rating' => $this->clsRating($metrics['cls']),
            'inp_rating' => $this->inpRating($metrics['inp_ms']),
            'recorded_at' => now(),
        ]);

        return $metrics;
    }

    public function lcpRating(?int $ms): ?string
    {
        return $this->rate($ms, self::LCP_GOOD_MS, self::LCP_NEEDS_IMPROVEMENT_MS);
    }

    public function clsRating(?float $value): ?string
    {
        return $this->rate($value, self::CLS_GOOD, self::CLS_NEEDS_IMPROVEMENT);
    }

    public function inpRating(?int $ms): ?string
    {
        return $this->rate($ms, self::INP_GOOD_MS, self::INP_NEEDS_IMPROVEMENT_MS);
    }

    private function rate(int|float|null $value, int|float $good, int|float $needsImprovement): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value <= $good) {
            return 'good';
        }

        return $value <= $needsImprovement ? 'needs-improvement' : 'poor';
    }

    private function extractP75(array $metrics, string $metric): int|float|null
    {
        $value = $metrics[$metric]['percentiles']['p75'] ?? null;

        return $value === null ? null : (float) $value;
    }

    private function resolveOrigin(): string
    {
        $host = trim((string) settings('seo.canonical_host', ''));

        return $host !== '' ? "https://{$host}" : rtrim(url('/'), '/');
    }
}
