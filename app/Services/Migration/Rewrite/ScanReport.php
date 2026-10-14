<?php

namespace App\Services\Migration\Rewrite;

/** What a leak scan found. */
final class ScanReport
{
    /**
     * @param  array<int, string>  $hosts
     * @param  array<int, array{table: string, column: string, class: string, rows: int, incomplete: bool}>  $entries
     * @param  array<int, string>  $skippedShortColumns  short columns of big tables that were not asked
     */
    public function __construct(
        public readonly array $hosts,
        public readonly array $entries,
        public readonly array $skippedShortColumns = [],
    ) {}

    /** @return array<int, array{table: string, column: string, class: string, rows: int, incomplete: bool}> */
    public function ofClass(string $class): array
    {
        return array_values(array_filter($this->entries, static fn (array $e): bool => $e['class'] === $class));
    }

    /** @return array<int, array{table: string, column: string, class: string, rows: int, incomplete: bool}> */
    public function incomplete(): array
    {
        return array_values(array_filter($this->entries, static fn (array $e): bool => $e['incomplete']));
    }

    public function hasLeaks(): bool
    {
        return $this->ofClass(DomainLeakScanner::LEAK) !== [];
    }

    /** True when a leak was found, or when a column could not be checked in time (so "clean" cannot be claimed). */
    public function needsAttention(): bool
    {
        return $this->hasLeaks() || $this->incomplete() !== [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $counts = [];

        foreach ($this->entries as $entry) {
            $counts[$entry['class']] = ($counts[$entry['class']] ?? 0) + 1;
        }

        return [
            'hosts' => $this->hosts, 'counts' => $counts, 'entries' => $this->entries,
            'incomplete' => count($this->incomplete()), 'skipped_short_columns' => $this->skippedShortColumns,
            'needs_attention' => $this->needsAttention(),
        ];
    }
}
