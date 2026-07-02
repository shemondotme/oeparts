<?php

namespace App\Services\Updates;

/**
 * UpdatePreview (Module 21, Chunk 3.5) — the "what will happen" summary shown in
 * the confirm dialog before an update is applied: version jump, download size,
 * migration count, breaking changes, a rough ETA, and the pre-flight readiness.
 */
class UpdatePreview
{
    public function __construct(
        public readonly string $fromVersion,
        public readonly string $toVersion,
        public readonly bool $security,
        public readonly ?int $sizeBytes,
        public readonly int $migrationCount,
        /** @var string[] */
        public readonly array $breakingChanges,
        public readonly int $etaSeconds,
        public readonly PreflightReport $preflight,
        public readonly ?string $preUpdateNotes = null,
    ) {}

    public function canProceed(): bool
    {
        return $this->preflight->canProceed();
    }

    public function toArray(): array
    {
        return [
            'from_version'     => $this->fromVersion,
            'to_version'       => $this->toVersion,
            'security'         => $this->security,
            'size_bytes'       => $this->sizeBytes,
            'migration_count'  => $this->migrationCount,
            'breaking_changes' => $this->breakingChanges,
            'eta_seconds'      => $this->etaSeconds,
            'pre_update_notes' => $this->preUpdateNotes,
            'can_proceed'      => $this->canProceed(),
            'preflight'        => $this->preflight->toArray(),
        ];
    }
}
