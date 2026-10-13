<?php

namespace App\Services\Migration\Export;

/**
 * One non-blocking warning: this much data refers to something that is not travelling.
 */
final class LogicalWarning
{
    public function __construct(
        public readonly LogicalRef $ref,
        /** Rows (or 1 for a setting) that carry the reference. When $approximate it is an upper bound. */
        public readonly int $count,
        public readonly bool $approximate,
    ) {}

    /** @return array<string, scalar|null> parameters for the interface text */
    public function params(): array
    {
        return ['count' => $this->count, 'approximate' => $this->approximate ? 'about ' : ''];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->ref->toArray() + ['count' => $this->count, 'approximate' => $this->approximate];
    }
}
