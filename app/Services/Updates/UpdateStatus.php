<?php

namespace App\Services\Updates;

/**
 * Immutable snapshot of the update-availability check (Module 21, Chunk 1.1).
 * Returned by UpdateChecker and safe to cache/serialize.
 */
class UpdateStatus
{
    public function __construct(
        public readonly string $currentVersion,
        public readonly ?string $latestVersion = null,
        public readonly bool $updateAvailable = false,
        public readonly bool $security = false,
        public readonly string $channel = 'stable',
        public readonly ?string $releaseDate = null,
        public readonly ?string $changelogUrl = null,
        public readonly ?string $downloadUrl = null,
        public readonly ?string $sha256 = null,
        public readonly ?int $sizeBytes = null,
        public readonly int $migrationCount = 0,
        public readonly ?string $minPhp = null,
        /** @var string[] ordered list of versions to step through (empty if none / up to date) */
        public readonly array $upgradePath = [],
        /**
         * The full release record for upgradePath[0] — the version an apply
         * should actually target. On a multi-hop path this is an INTERMEDIATE
         * release, not $latestVersion; its own sha256/download_url/
         * migration_count/min_version_to_update_from must be used to build the
         * apply manifest, never the top-level (latest-release) fields above.
         *
         * @var array<string,mixed>|null
         */
        public readonly ?array $nextRelease = null,
        public readonly bool $reachable = true,
        public readonly ?string $error = null,
        public readonly ?string $checkedAt = null,
    ) {}

    /** A multi-hop upgrade (the jump must be applied in steps, not directly). */
    public function isMultiStep(): bool
    {
        return count($this->upgradePath) > 1;
    }

    public function toArray(): array
    {
        return [
            'current_version'  => $this->currentVersion,
            'latest_version'   => $this->latestVersion,
            'update_available' => $this->updateAvailable,
            'security'         => $this->security,
            'channel'          => $this->channel,
            'release_date'     => $this->releaseDate,
            'changelog_url'    => $this->changelogUrl,
            'download_url'     => $this->downloadUrl,
            'sha256'           => $this->sha256,
            'size_bytes'       => $this->sizeBytes,
            'migration_count'  => $this->migrationCount,
            'min_php'          => $this->minPhp,
            'upgrade_path'     => $this->upgradePath,
            'next_release'     => $this->nextRelease,
            'reachable'        => $this->reachable,
            'error'            => $this->error,
            'checked_at'       => $this->checkedAt,
        ];
    }
}
