<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Full-screen preloader visibility: Admin → Settings → preloader
 * (enable, path mode, patterns, min/max display ms, multilang copy in settings).
 */
class PreloaderService
{
    public function shouldRender(): bool
    {
        $raw = settings('preloader.enabled', '0');
        if (! filter_var($raw, FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $path = $this->normalizedPath();

        $mode = (string) settings('preloader.path_mode', 'include');
        $patterns = $this->pathPatterns();
        if ($mode === 'all') {
            return true;
        }

        $match = $this->pathMatchesAny($path, $patterns);

        return $mode === 'include' ? $match : ! $match;
    }

    /**
     * @return array{min_ms: int, max_ms: int}
     */
    public function timingConfig(): array
    {
        return [
            'min_ms' => max(0, (int) settings('preloader.min_display_ms', 450)),
            'max_ms' => min(600_000, max(500, (int) settings('preloader.max_display_ms', 6_000))),
        ];
    }

    /**
     * @return list<string>
     */
    public function pathPatterns(): array
    {
        $raw = settings('preloader.path_patterns', '[]');
        if (is_array($raw)) {
            return array_values(array_filter($raw, static fn ($p) => $p !== null && $p !== ''));
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_values(array_filter($decoded, static fn ($p) => $p !== null && $p !== ''));
            }
        }

        return [];
    }

    private function normalizedPath(): string
    {
        $p = ltrim((string) request()->path(), '/');

        return $p;
    }

    /**
     * @param  list<string>  $patterns
     */
    private function pathMatchesAny(string $path, array $patterns): bool
    {
        if ($patterns === []) {
            return false;
        }

        foreach ($patterns as $pattern) {
            $p = ltrim($pattern, '/');
            if ($p === '' && $path === '') {
                return true;
            }
            if (Str::is($p, $path)) {
                return true;
            }
        }

        return false;
    }
}
