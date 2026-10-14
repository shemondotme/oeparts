<?php

namespace App\Services\Migration\Import;

/** What one budgeted step of a stage came to. */
final class ImportStepResult
{
    private function __construct(
        public readonly bool $done,
        public readonly float $fraction,
        public readonly string $message,
        public readonly bool $needsPassphrase,
        public readonly bool $awaitConfirmation,
    ) {}

    /** The stage has finished everything it had to do. */
    public static function done(string $message = ''): self
    {
        return new self(true, 1.0, $message, false, false);
    }

    /** More to do: call again. `$fraction` is how far through THIS stage it is (0..1). */
    public static function progress(float $fraction, string $message = ''): self
    {
        return new self(false, max(0.0, min(1.0, $fraction)), $message, false, false);
    }

    /** The key for the package is no longer available (expired, or never stored): a person has to type the passphrase again. */
    public static function needsPassphrase(string $message = ''): self
    {
        return new self(false, 0.0, $message, true, false);
    }

    /** Everything that could be checked without changing anything has been: wait for a person to say go. */
    public static function awaitConfirmation(string $message = ''): self
    {
        return new self(false, 1.0, $message, false, true);
    }
}
