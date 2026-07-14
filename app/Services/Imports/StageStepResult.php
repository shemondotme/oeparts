<?php

namespace App\Services\Imports;

/**
 * The outcome of a single {@see \App\Services\Imports\Contracts\ImportStage::step()}
 * call.
 *
 * - $done   : the stage has no more work; the engine advances to the next stage.
 * - $state  : the checkpoint to hand back on the next step (ignored when $done).
 * - $message: short human-readable progress note (for the poll UI / logs).
 */
class StageStepResult
{
    public function __construct(
        public readonly bool $done,
        public readonly array $state = [],
        public readonly ?string $message = null,
    ) {}

    /** More work remains; persist $state and poll again. */
    public static function progress(array $state, ?string $message = null): self
    {
        return new self(done: false, state: $state, message: $message);
    }

    /** This stage is finished. */
    public static function complete(?string $message = null): self
    {
        return new self(done: true, state: [], message: $message);
    }
}
