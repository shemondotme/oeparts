<?php

namespace App\Services\Backup;

/**
 * The outcome of a single {@see \App\Services\Backup\Contracts\BackupStage::step()}
 * call.
 *
 * - $done   : the stage has no more work; the engine advances to the next stage.
 * - $state  : the checkpoint to hand back on the next step (ignored when $done).
 * - $part   : attributes for a backup_parts row produced by this step, or null.
 *             Shape mirrors the backup_parts columns (type, name, disk, path,
 *             sha256, bytes, rows, meta); 'type'/'sequence' are defaulted by the
 *             engine when omitted.
 * - $message: short human-readable progress note (for the poll UI / logs).
 * - $fraction: this stage's own completion fraction (0.0-1.0), used by the engine
 *              to compute an overall run percentage for the poll UI. Optional —
 *              stages that can't cheaply estimate it may omit it (treated as 0).
 */
class StageStepResult
{
    public function __construct(
        public readonly bool $done,
        public readonly array $state = [],
        public readonly ?array $part = null,
        public readonly ?string $message = null,
        public readonly float $fraction = 0.0,
    ) {}

    /** More work remains; persist $state and poll again. */
    public static function progress(array $state, ?array $part = null, ?string $message = null, float $fraction = 0.0): self
    {
        return new self(done: false, state: $state, part: $part, message: $message, fraction: max(0.0, min(1.0, $fraction)));
    }

    /** This stage is finished (optionally emitting one final part). */
    public static function complete(?array $part = null, ?string $message = null): self
    {
        return new self(done: true, state: [], part: $part, message: $message, fraction: 1.0);
    }
}
