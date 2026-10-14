<?php

namespace App\Services\Migration\Import;

use App\Services\Migration\Import\Contracts\ImportStage;
use App\Services\Migration\Import\Stages\ArmStage;
use App\Services\Migration\Import\Stages\DbAuditStage;
use App\Services\Migration\Import\Stages\DbClearStage;
use App\Services\Migration\Import\Stages\DbDataStage;
use App\Services\Migration\Import\Stages\DbIndexDropStage;
use App\Services\Migration\Import\Stages\DbIndexRestoreStage;
use App\Services\Migration\Import\Stages\DbSeedDefaultsStage;
use App\Services\Migration\Import\Stages\DryRunPlanStage;
use App\Services\Migration\Import\Stages\EnsureSchemaStage;
use App\Services\Migration\Import\Stages\FilesStage;
use App\Services\Migration\Import\Stages\FinCheckMigrationsStage;
use App\Services\Migration\Import\Stages\FinNormalizeStage;
use App\Services\Migration\Import\Stages\FinOriginResetStage;
use App\Services\Migration\Import\Stages\FinRewriteStage;
use App\Services\Migration\Import\Stages\FinSecretsStage;
use App\Services\Migration\Import\Stages\LocateStage;
use App\Services\Migration\Import\Stages\PreflightStage;
use App\Services\Migration\Import\Stages\RecoveryPointStage;
use App\Services\Migration\Import\Stages\VerifyStage;

/**
 * The stages of an import in the order they run. The one list the manager, the progress bar and the kill-matrix
 * guard test all read, so a stage cannot exist without being in the order and without a row in the matrix.
 */
final class ImportStageRegistry
{
    /** A dry run does everything that changes nothing and stops after this stage... */
    public const DRY_RUN_LAST = 'preflight';

    /** ...then adds these stages, which exist only for a dry run (and never run in a real import). */
    public const DRY_RUN_ONLY = ['dry_run_plan'];

    /** @var array<string, ImportStage> */
    private array $stages = [];

    /** @param  array<int, ImportStage>  $stages */
    public function __construct(array $stages)
    {
        foreach ($stages as $stage) {
            if (isset($this->stages[$stage->key()])) {
                throw new \LogicException("The stage {$stage->key()} is registered twice.");
            }

            $this->stages[$stage->key()] = $stage;
        }
    }

    public static function forApplication(): self
    {
        return new self([
            app(LocateStage::class),
            app(VerifyStage::class),
            app(EnsureSchemaStage::class),
            app(PreflightStage::class),
            app(RecoveryPointStage::class),
            app(ArmStage::class),
            app(DbClearStage::class),
            app(DbIndexDropStage::class),
            app(DbDataStage::class),
            app(DbIndexRestoreStage::class),
            app(DbAuditStage::class),
            app(DbSeedDefaultsStage::class),
            app(FilesStage::class),
            app(FinSecretsStage::class),
            app(FinCheckMigrationsStage::class),
            app(FinRewriteStage::class),
            app(FinNormalizeStage::class),
            app(FinOriginResetStage::class),
            app(DryRunPlanStage::class),
        ]);
    }

    /** @return array<int, string> */
    public function keys(string $kind = 'import'): array
    {
        $real = array_values(array_diff(array_keys($this->stages), self::DRY_RUN_ONLY));

        if ($kind === 'dry_run') {
            $at = array_search(self::DRY_RUN_LAST, $real, true);
            $upToPreflight = $at === false ? $real : array_slice($real, 0, $at + 1);

            return [...$upToPreflight, ...array_values(array_intersect(self::DRY_RUN_ONLY, array_keys($this->stages)))];
        }

        return $real;
    }

    public function get(string $key): ?ImportStage
    {
        return $this->stages[$key] ?? null;
    }

    /** The key of the stage after this one for this kind of run, or null when it was the last. */
    public function nextAfter(string $key, string $kind = 'import'): ?string
    {
        $keys = $this->keys($kind);
        $at = array_search($key, $keys, true);

        return $at === false ? null : ($keys[$at + 1] ?? null);
    }

    /** How far through the whole run a stage is, for the progress bar: its position plus its own fraction, over the count. */
    public function percent(string $key, float $fraction, string $kind = 'import'): float
    {
        $keys = $this->keys($kind);
        $at = array_search($key, $keys, true);

        if ($at === false) {
            return 0.0;
        }

        return round(100 * ($at + max(0.0, min(1.0, $fraction))) / count($keys), 1);
    }
}
