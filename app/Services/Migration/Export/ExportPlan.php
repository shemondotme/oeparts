<?php

namespace App\Services\Migration\Export;

/**
 * Everything decided before an export starts, frozen into one plain value that is stored with the run.
 * Later stages (database, files, packaging) read their instructions from here and from nowhere else, so a
 * run that is resumed hours later does what it was told when it started, even if settings changed meanwhile.
 */
final class ExportPlan
{
    public const SEO_ALL = 'all';

    public const SEO_PRODUCT = 'product';

    public const SEO_OTHER = 'other';

    /**
     * @param  array<int, string>  $requested  group names the person asked for (core included)
     * @param  array<int, string>  $groups  group names that will be exported
     * @param  array<int, string>  $tables  every table exported, children first
     * @param  array<int, string>  $fileRoots  `disk:folder` strings
     * @param  array<string, array<int, string>>  $nullified  table => columns exported as NULL
     * @param  array<string, array<int, array<string, mixed>>>  $added  group => why it was added
     * @param  array<int, array{group: string, reason: string}>  $dropped  groups taken out of the selection, and why
     * @param  array<int, array<string, mixed>>  $warnings  LogicalWarning::toArray()
     * @param  array<int, string>  $unclassified  tables of the live database that no group owns (treated as system data)
     * @param  array<int, array{code: string, params: array<string, scalar|null>}>  $blockers  reasons the plan cannot be run as it stands
     */
    public function __construct(
        public readonly ?string $preset,
        public readonly array $requested,
        public readonly array $groups,
        public readonly array $tables,
        public readonly array $fileRoots,
        public readonly array $nullified,
        public readonly ?string $seoSlice,
        public readonly string $consistency,
        public readonly bool $includeSecrets,
        public readonly bool $anonymise,
        public readonly bool $resetSequences,
        public readonly bool $hold,
        public readonly int $holdReleaseMinutes,
        public readonly int $freezeCapMinutes,
        public readonly array $added,
        public readonly array $dropped,
        public readonly array $warnings,
        public readonly array $unclassified,
        public readonly ExportEstimate $estimate,
        public readonly array $blockers,
        public readonly int $createdAt,
    ) {}

    public function isFreeze(): bool
    {
        return $this->consistency === ExportOptions::FREEZE;
    }

    public function isRunnable(): bool
    {
        return $this->blockers === [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'v' => 1, 'preset' => $this->preset, 'requested' => $this->requested, 'groups' => $this->groups, 'tables' => $this->tables,
            'file_roots' => $this->fileRoots, 'nullified' => $this->nullified, 'seo_slice' => $this->seoSlice,
            'consistency' => $this->consistency, 'include_secrets' => $this->includeSecrets, 'anonymise' => $this->anonymise,
            'reset_sequences' => $this->resetSequences, 'hold' => $this->hold, 'hold_release_minutes' => $this->holdReleaseMinutes,
            'freeze_cap_minutes' => $this->freezeCapMinutes, 'added' => $this->added, 'dropped' => $this->dropped,
            'warnings' => $this->warnings, 'unclassified' => $this->unclassified, 'estimate' => $this->estimate->toArray(),
            'blockers' => $this->blockers, 'created_at' => $this->createdAt,
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        if (($data['v'] ?? null) !== 1) {
            throw new \InvalidArgumentException('This export plan was made by a different version.');
        }

        return new self(
            $data['preset'], $data['requested'], $data['groups'], $data['tables'], $data['file_roots'], $data['nullified'], $data['seo_slice'],
            $data['consistency'], $data['include_secrets'], $data['anonymise'], $data['reset_sequences'], $data['hold'], $data['hold_release_minutes'],
            $data['freeze_cap_minutes'], $data['added'], $data['dropped'], $data['warnings'], $data['unclassified'], ExportEstimate::fromArray($data['estimate']),
            $data['blockers'], $data['created_at']
        );
    }
}
