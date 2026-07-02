<?php

namespace App\Services\Updates;

/**
 * Aggregated update pre-flight result (Module 21, Chunk 3.1). The update may
 * proceed only when there are zero FAIL checks; WARNs require operator ack.
 */
class PreflightReport
{
    /** @param PreflightCheck[] $checks */
    public function __construct(public readonly array $checks) {}

    public function canProceed(): bool
    {
        return $this->failures() === [];
    }

    public function hasWarnings(): bool
    {
        return $this->warnings() !== [];
    }

    /** @return PreflightCheck[] */
    public function failures(): array
    {
        return array_values(array_filter($this->checks, fn (PreflightCheck $c) => $c->isFail()));
    }

    /** @return PreflightCheck[] */
    public function warnings(): array
    {
        return array_values(array_filter($this->checks, fn (PreflightCheck $c) => $c->isWarn()));
    }

    public function get(string $key): ?PreflightCheck
    {
        foreach ($this->checks as $check) {
            if ($check->key === $key) {
                return $check;
            }
        }

        return null;
    }

    public function toArray(): array
    {
        return [
            'can_proceed'   => $this->canProceed(),
            'has_warnings'  => $this->hasWarnings(),
            'failure_count' => count($this->failures()),
            'warning_count' => count($this->warnings()),
            'checks'        => array_map(fn (PreflightCheck $c) => $c->toArray(), $this->checks),
        ];
    }
}
