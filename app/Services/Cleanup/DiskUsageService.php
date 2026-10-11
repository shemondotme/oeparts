<?php

namespace App\Services\Cleanup;

use Closure;

/**
 * Free / used space of the volume the application stores its data on — the one number the
 * Cleanup page, the Health Check disk warning and the "bytes actually freed" measurement all
 * need, previously re-implemented with raw disk_free_space() calls in seven places.
 *
 * Fails open: disk_total_space()/disk_free_space() return false on some hosts (open_basedir,
 * restricted shared hosting). Then every number is null and callers show "unknown" instead of
 * inventing 0 bytes free.
 *
 * Note on measuring "freed" space: the filesystem updates free-space figures a moment after a
 * delete and other processes write concurrently (logs, sessions). A shortfall against the
 * summed file sizes is therefore a NOTE, never a failure.
 */
final class DiskUsageService
{
    /** @var Closure(string):(float|false)|null */
    private ?Closure $totalReader;

    /** @var Closure(string):(float|false)|null */
    private ?Closure $freeReader;

    /**
     * @param  Closure(string):(float|false)|null  $totalReader  override for tests
     * @param  Closure(string):(float|false)|null  $freeReader  override for tests
     */
    public function __construct(
        private readonly ?string $path = null,
        ?Closure $totalReader = null,
        ?Closure $freeReader = null,
    ) {
        $this->totalReader = $totalReader;
        $this->freeReader = $freeReader;
    }

    public function path(): string
    {
        return $this->path ?? storage_path();
    }

    public function totalBytes(): ?int
    {
        $v = $this->totalReader !== null ? ($this->totalReader)($this->path()) : @disk_total_space($this->path());

        return $v === false || $v <= 0 ? null : (int) $v;
    }

    public function freeBytes(): ?int
    {
        $v = $this->freeReader !== null ? ($this->freeReader)($this->path()) : @disk_free_space($this->path());

        return $v === false || $v < 0 ? null : (int) $v;
    }

    public function usedBytes(): ?int
    {
        $total = $this->totalBytes();
        $free = $this->freeBytes();

        return $total === null || $free === null ? null : max(0, $total - $free);
    }

    /** Percent of the volume in use, 0-100, or null when it cannot be read. */
    public function usedPercent(): ?float
    {
        $total = $this->totalBytes();
        $used = $this->usedBytes();

        return $total === null || $used === null ? null : round($used / $total * 100, 1);
    }

    /**
     * @return array{total:int|null, free:int|null, used:int|null, used_pct:float|null}
     */
    public function snapshot(): array
    {
        return [
            'total' => $this->totalBytes(),
            'free' => $this->freeBytes(),
            'used' => $this->usedBytes(),
            'used_pct' => $this->usedPercent(),
        ];
    }

    /** 'ok' | 'warn' | 'fail' | 'unknown' against the configured thresholds. */
    public function level(?int $warnPct = null, ?int $failPct = null): string
    {
        $pct = $this->usedPercent();
        if ($pct === null) {
            return 'unknown';
        }

        $warn = $warnPct ?? (int) settings('cleanup.disk_alert_pct', config('cleanup.disk_alert_pct', 80));
        $fail = $failPct ?? (int) config('cleanup.disk_fail_pct', 95);

        return $pct >= $fail ? 'fail' : ($pct >= $warn ? 'warn' : 'ok');
    }
}
