<?php

namespace Tests\Support;

use App\Models\BackupRun;
use App\Services\Backup\StageStepResult;
use App\Services\Migration\Export\DependencyResolver;
use App\Services\Migration\Export\ExportEstimator;
use App\Services\Migration\Export\ExportOptions;
use App\Services\Migration\Export\ExportPlan;
use App\Services\Migration\Export\ExportPlanner;
use App\Services\Migration\Export\FkGraph;
use App\Services\Migration\Export\KeysetPager;
use App\Services\Migration\Export\LogicalRefRegistry;
use App\Services\Migration\Export\Stages\ExportDatabaseStage;
use App\Services\Migration\Groups\GroupKey;
use App\Services\Migration\Groups\GroupRegistry;
use App\Services\Migration\Package\BoundedInflater;
use Illuminate\Support\Facades\Storage;

/**
 * Helpers for tests that run the export database stage the way the engine does: build a plan, create the
 * run, step the stage to completion registering the parts it hands back, and read the segments back through
 * the same safe inflater an importer uses.
 */
trait DrivesExportStage
{
    private function stage(?string $secretsKey = null): ExportDatabaseStage
    {
        return new ExportDatabaseStage(new GroupRegistry, new KeysetPager, $secretsKey === null ? null : fn (): string => $secretsKey);
    }

    /** @param  array<int, GroupKey>  $groups */
    private function realPlan(array $groups, ExportOptions $options = new ExportOptions): ExportPlan
    {
        $registry = new GroupRegistry;

        return (new ExportPlanner(
            $registry, new DependencyResolver($registry, new FkGraph), new LogicalRefRegistry($registry), new ExportEstimator,
            fn (): int => PHP_INT_MAX, null, fn (): int => 1_800_000_000
        ))->plan($groups, $options);
    }

    /** A plan that exports exactly these tables, whatever the registry says (for the throw-away tables). */
    private function planOf(array $tables, array $over = []): ExportPlan
    {
        $data = $this->realPlan([GroupKey::Media])->toArray();
        $data['tables'] = $tables;
        $data['nullified'] = [];
        $data['estimate']['tables'] = [];
        $data['estimate']['total_rows'] = 100;

        return ExportPlan::fromArray(array_merge($data, $over));
    }

    private function run_(ExportPlan $plan, array $meta = []): BackupRun
    {
        return BackupRun::create([
            'profile' => BackupRun::PROFILE_EXPORT, 'status' => BackupRun::STATUS_RUNNING, 'trigger' => BackupRun::TRIGGER_MANUAL, 'disk' => 'exportstage',
            'meta' => array_merge(['export_plan' => $plan->toArray()], $meta),
        ]);
    }

    /**
     * Drive the stage to completion, registering parts the way the engine does. $beforeStep(int $n, array $state) runs before each step.
     *
     * @return array{steps: int, state_sizes: array<int, int>}
     */
    private function drive(BackupRun $run, ?ExportDatabaseStage $stage = null, ?\Closure $beforeStep = null): array
    {
        $state = [];
        $steps = 0;
        $sizes = [];

        do {
            $stage = $stage ?? $this->stage();
            $beforeStep?->__invoke($steps, $state);

            $result = $stage->step($run, $state);
            $this->register($run, $result);
            $state = json_decode((string) json_encode($result->state), true) ?? [];
            $sizes[] = strlen((string) json_encode($state));
            $this->assertLessThan(2000, ++$steps, 'the stage never finishes');
        } while (! $result->done);

        $run->save();

        return ['steps' => $steps, 'state_sizes' => $sizes];
    }

    private function register(BackupRun $run, StageStepResult $result): void
    {
        foreach ([...$result->parts, ...($result->part !== null ? [$result->part] : [])] as $attrs) {
            $run->parts()->create($attrs + ['sequence' => $run->parts()->where('type', $attrs['type'])->count()]);
        }
    }

    /**
     * Every row of a table as the package would hand it to an importer: the header and the decoded row arrays,
     * read back through the same safe inflater, segment by segment.
     *
     * @return array{headers: array<int, array<string, mixed>>, rows: array<int, array<int, mixed>>}
     */
    private function exported(BackupRun $run, string $table): array
    {
        $headers = [];
        $rows = [];

        foreach ($run->parts()->get()->filter(fn ($p) => ($p->meta['table'] ?? null) === $table)->sortBy('name') as $part) {
            $inflater = new BoundedInflater((int) $part->meta['raw_len']);
            $text = $inflater->inflateString((string) Storage::disk('exportstage')->get($part->path));

            $lines = array_values(array_filter(explode("\n", $text), fn (string $l): bool => $l !== ''));
            $headers[] = json_decode(array_shift($lines), true);
            $this->assertSame(hash('sha256', (string) Storage::disk('exportstage')->get($part->path)), $part->sha256);
            $this->assertSame(Storage::disk('exportstage')->size($part->path), $part->bytes);
            $this->assertCount($part->rows, $lines, 'the part says how many rows it holds');

            foreach ($lines as $line) {
                $rows[] = json_decode($line, true);
            }
        }

        return ['headers' => $headers, 'rows' => $rows];
    }
}
