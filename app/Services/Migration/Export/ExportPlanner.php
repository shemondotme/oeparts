<?php

namespace App\Services\Migration\Export;

use App\Services\Migration\Groups\GroupKey;
use App\Services\Migration\Groups\GroupRegistry;
use App\Services\Migration\Groups\Preset;
use App\Services\Migration\MigrationErrorCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns a person's choice (a preset or any set of groups, plus a few options) into a frozen ExportPlan:
 * what exactly will be exported, what that choice drags in, what it breaks, how big and how long it is,
 * and whether it can run at all.
 *
 * Rules, all enforced here and nowhere else:
 *  - core is always in, system is never in;
 *  - scrambling personal data forces secrets out and drops logs, carts and payment proofs (free text and
 *    files cannot be shown clean);
 *  - the two "new store" presets restart numbering; a custom selection keeps it unless asked;
 *  - a freeze is refused (and a live copy suggested) when the estimate says the shop would be offline
 *    longer than allowed; otherwise its cap is 1.5 times the pessimistic estimate, at least the configured one.
 */
final class ExportPlanner
{
    /** Groups that cannot be shown free of personal data, so they never go into a scrambled copy. */
    private const NOT_SCRUBBABLE = [GroupKey::Logs, GroupKey::Carts, GroupKey::SalesFiles];

    /** @var \Closure(): int|null */
    private readonly ?\Closure $freeBytes;

    /**
     * @param  \Closure(): int|null  $freeBytes  free space on the export volume; only tests pass this
     * @param  \Closure(): array<int, string>|null  $liveTables  only tests pass this
     */
    public function __construct(
        private readonly GroupRegistry $registry,
        private readonly DependencyResolver $resolver,
        private readonly LogicalRefRegistry $logical,
        private readonly ExportEstimator $estimator,
        ?\Closure $freeBytes = null,
        private readonly ?\Closure $liveTables = null,
        private readonly ?\Closure $clock = null,
    ) {
        $this->freeBytes = $freeBytes;
    }

    /**
     * @param  Preset|array<int, GroupKey>  $selection
     * @param  array{count: int, bytes: int, complete: bool}|null  $files  a FileCounter result, when one has been made
     */
    public function plan(Preset|array $selection, ExportOptions $options = new ExportOptions, ?array $files = null): ExportPlan
    {
        $preset = $selection instanceof Preset ? $selection : null;
        $requested = $preset?->groups() ?? $selection;

        $anonymise = $options->anonymise ?? $preset?->anonymises() ?? false;
        $resetSequences = $options->resetSequences ?? $preset?->resetsSequences() ?? false;
        $includeSecrets = $options->includeSecrets && ! $anonymise && ! ($preset?->forcesSecretsOut() ?? false);

        $dropped = [];
        if ($anonymise) {
            foreach (self::NOT_SCRUBBABLE as $group) {
                if (in_array($group, $requested, true)) {
                    $requested = array_values(array_filter($requested, static fn (GroupKey $k): bool => $k !== $group));
                    $dropped[] = ['group' => $group->value, 'reason' => 'anonymise'];
                }
            }
        }

        $resolution = $this->resolver->resolve($requested);

        foreach (self::NOT_SCRUBBABLE as $group) {
            if ($anonymise && $resolution->includes($group)) {
                throw new \LogicException("A scrambled copy would still contain {$group->value}: it was pulled back in by a reference.");
            }
        }

        $estimate = $this->estimator->estimate($resolution, $files);
        $export = (array) config('migration.export', []);
        $holdMinutes = $options->holdReleaseMinutes ?? (int) ($export['hold_auto_release_minutes'] ?? 120);
        $freezeCap = max((int) ($export['freeze_max_minutes'] ?? 180), (int) ceil(1.5 * $estimate->minutesHigh));

        $blockers = [];

        if ($options->freeze() && $estimate->minutesHigh > (int) ($export['freeze_refuse_minutes'] ?? 720)) {
            $blockers[] = [
                'code' => MigrationErrorCode::FreezeTooLong->value,
                'params' => ['minutes' => $estimate->minutesHigh, 'limit' => (int) ($export['freeze_refuse_minutes'] ?? 720)],
            ];
        }

        $free = $this->freeBytes !== null ? ($this->freeBytes)() : $this->freeBytesOnExportVolume();
        if ($free !== null && $free < $estimate->requiredFreeBytes) {
            $blockers[] = [
                'code' => MigrationErrorCode::PreflightDisk->value,
                'params' => ['needed' => $estimate->requiredFreeBytes, 'free' => $free],
            ];
        }

        $groupValues = array_map(static fn (GroupKey $k): string => $k->value, $resolution->groups);

        return new ExportPlan(
            preset: $preset?->value,
            requested: array_map(static fn (GroupKey $k): string => $k->value, $resolution->requested),
            groups: $groupValues,
            tables: $resolution->tables,
            fileRoots: array_map('strval', $this->registry->fileRootsOf($resolution->groups)),
            nullified: $resolution->nullifiedColumns(),
            seoSlice: $this->seoSlice($resolution),
            consistency: $options->consistency,
            includeSecrets: $includeSecrets,
            anonymise: $anonymise,
            resetSequences: $resetSequences,
            hold: $options->freeze() && $options->hold,
            holdReleaseMinutes: $holdMinutes,
            freezeCapMinutes: $freezeCap,
            added: array_map(static fn (array $reasons): array => array_map(static fn (Reason $r): array => $r->toArray(), $reasons), $resolution->added),
            dropped: $dropped,
            warnings: array_map(static fn (LogicalWarning $w): array => $w->toArray(), $this->logical->warningsFor($resolution)),
            unclassified: $this->registry->unclassified($this->liveTableNames()),
            estimate: $estimate,
            blockers: $blockers,
            createdAt: $this->clock !== null ? ($this->clock)() : time(),
        );
    }

    /** Which rows of the shared SEO table travel: all, only the product ones, only the others, or none. */
    private function seoSlice(Resolution $resolution): ?string
    {
        $product = $resolution->includes(GroupKey::Catalog);
        $other = $resolution->includes(GroupKey::Content);

        return match (true) {
            $product && $other => ExportPlan::SEO_ALL,
            $product => ExportPlan::SEO_PRODUCT,
            $other => ExportPlan::SEO_OTHER,
            default => null,
        };
    }

    private function freeBytesOnExportVolume(): ?int
    {
        $directory = storage_path('app');
        $free = @disk_free_space(is_dir($directory) ? $directory : base_path());

        return $free === false ? null : (int) $free;
    }

    /** @return array<int, string> */
    private function liveTableNames(): array
    {
        if ($this->liveTables !== null) {
            return ($this->liveTables)();
        }

        $connection = DB::connection();
        $schema = in_array($connection->getDriverName(), ['mysql', 'mariadb'], true) ? $connection->getDatabaseName() : null;

        return array_values(array_filter(
            Schema::getTableListing($schema, schemaQualified: false),
            static fn (string $t): bool => ! str_starts_with($t, 'sqlite_')
        ));
    }
}
