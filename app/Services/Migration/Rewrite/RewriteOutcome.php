<?php

namespace App\Services\Migration\Rewrite;

/** What rewriting one stored value came to. */
final class RewriteOutcome
{
    public function __construct(
        /** The text to store: the rewritten text, or the original when nothing changed or the row was skipped. */
        public readonly string $text,
        public readonly int $replacements,
        /** The rewrite would have broken the JSON, so the original was kept and the row must be counted and reported. */
        public readonly bool $invalidJson,
    ) {}

    public function changed(string $original): bool
    {
        return ! $this->invalidJson && $this->text !== $original;
    }
}
