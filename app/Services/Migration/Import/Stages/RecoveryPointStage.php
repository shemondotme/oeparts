<?php

namespace App\Services\Migration\Import\Stages;

use App\Models\BackupChunk;
use App\Models\BackupRun;
use App\Services\Instance\InstanceEnv;
use App\Services\Migration\Export\DependencyResolver;
use App\Services\Migration\Export\ExportEstimator;
use App\Services\Migration\Export\ExportOptions;
use App\Services\Migration\Export\ExportPlanner;
use App\Services\Migration\Export\FkGraph;
use App\Services\Migration\Export\LogicalRefRegistry;
use App\Services\Migration\Export\Stages\ExportDatabaseStage;
use App\Services\Migration\Groups\GroupKey;
use App\Services\Migration\Groups\GroupRegistry;
use App\Services\Migration\Import\Contracts\ImportStage;
use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\ImportStepResult;
use App\Services\Migration\Import\Recovery\RecoveryPoint;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\MigrationPaths;
use App\Support\AtomicJsonFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Stage 5. Makes the way back BEFORE anything is changed: a copy of every table of the site (all of them but the system
 * tables) exactly as stored, and the .env keys the import will change, then proves the copy readable and intact.
 *
 * The copy is made by the export engine's own table copier, in a mode with no rule at all (a recovery point that changed
 * a value would not restore the site), so it inherits what is already proven there: keyset reading with no OFFSET, a
 * high-water mark so a busy site cannot make it chase new rows for ever, and exact resume after a kill. The parts it
 * makes are moved into the run's private recovery directory once the copy is complete, with a manifest of sizes, row
 * counts and SHA-256 per segment that arm compares with the live site and rollback replays.
 *
 * Nothing on the site changes. It reads the database and writes files in the run's own directory.
 */
final class RecoveryPointStage implements ImportStage
{
    public function __construct(
        private readonly ExportDatabaseStage $copier,
        private readonly GroupRegistry $registry,
        private readonly ImportStateStore $store,
        private readonly InstanceEnv $env,
    ) {}

    public function key(): string
    {
        return 'recovery_point';
    }

    public function destructive(): bool
    {
        return false;
    }

    public function step(ImportState $state, ImportContext $context): ImportStepResult
    {
        if ($state->flag('recovery.done')) {
            return ImportStepResult::done('The way back is ready.');
        }

        $ss = $state->stageState;
        $phase = (string) ($ss['phase'] ?? 'init');

        $result = match ($phase) {
            'init' => $this->begin($state),
            'dump' => $this->dump($state, $context),
            'collect' => $this->collect($state),
            'env' => $this->saveEnv($state),
            'verify' => $this->verify($state, $context),
            default => throw new \LogicException("Unknown recovery phase {$phase}."),
        };

        return $result;
    }

    // ---- phases -------------------------------------------------------------------------------------------

    private function begin(ImportState $state): ImportStepResult
    {
        $groups = array_filter(GroupKey::cases(), static fn (GroupKey $g): bool => $g !== GroupKey::System);
        $plan = (new ExportPlanner($this->registry, new DependencyResolver($this->registry, new FkGraph), new LogicalRefRegistry($this->registry), new ExportEstimator))
            ->plan($groups, new ExportOptions);

        $planData = $plan->toArray();
        $planData['blockers'] = []; // disk and the rest are the preflight's business: this copy is read-only

        $run = BackupRun::create([
            'profile' => BackupRun::PROFILE_EXPORT, 'status' => BackupRun::STATUS_RUNNING, 'trigger' => BackupRun::TRIGGER_MANUAL,
            'disk' => (string) config('backup.staging_disk', 'local'),
            'meta' => ['export_plan' => $planData, 'raw' => true, 'recovery_for' => $state->id],
        ]);

        $state->stageState = ['phase' => 'dump', 'run_id' => $run->getKey(), 'db' => [], 'tables' => $plan->tables];

        return ImportStepResult::progress(0.0, 'Saving a way back.');
    }

    private function dump(ImportState $state, ImportContext $context): ImportStepResult
    {
        $ss = $state->stageState;
        $run = BackupRun::findOrFail($ss['run_id']);

        do {
            $step = $this->copier->step($run, (array) ($ss['db'] ?? []));

            foreach ([...($step->part !== null ? [$step->part] : []), ...$step->parts] as $attrs) {
                $run->parts()->create($attrs + ['sequence' => $run->parts()->where('type', $attrs['type'])->count()]);
            }

            $ss['db'] = $step->state;
            BackupRun::whereKey($run->getKey())->update(['updated_at' => now()]);
        } while (! $step->done && ! $context->expired());

        if (! $step->done) {
            $state->stageState = $ss;

            return ImportStepResult::progress(0.7 * $step->fraction, (string) $step->message);
        }

        $run->save(); // the report the copier put in the run's meta
        $ss['phase'] = 'collect';
        $ss['db'] = [];
        $state->stageState = $ss;

        return ImportStepResult::progress(0.7, 'Collecting the copy.');
    }

    /** Move the finished segments into the run's own directory and write the manifest. Safe to repeat: a moved part is skipped. */
    private function collect(ImportState $state): ImportStepResult
    {
        $ss = $state->stageState;
        $run = BackupRun::findOrFail($ss['run_id']);
        $point = RecoveryPoint::of($state->id, $this->store);
        $point->ensure();
        $disk = Storage::disk((string) $run->disk);
        $parts = [];
        $tables = [];

        foreach ($run->parts()->orderBy('id')->get() as $part) {
            /** @var BackupChunk $part */
            $table = (string) $part->meta['table'];
            $segment = (int) $part->meta['segment'];
            $file = sprintf('%s.%04d%s', $table, $segment, RecoveryPoint::SEGMENT_SUFFIX);
            $target = $point->path($file);

            if (is_file($disk->path((string) $part->path))) {
                if (! @rename($disk->path((string) $part->path), $target)) {
                    throw new \RuntimeException("Cannot move a recovery segment to {$target}.");
                }
            } elseif (! is_file($target)) {
                throw new MigrationException(MigrationErrorCode::ImportFailedStep, ['stage' => 'recovery_point', 'part' => $file]);
            }

            $parts[] = [
                'table' => $table, 'segment' => $segment, 'file' => $file, 'rows' => (int) $part->rows, 'bytes' => (int) $part->bytes,
                'sha256' => (string) $part->sha256, 'raw_len' => (int) ($part->meta['raw_len'] ?? 0),
            ];
            $tables[$table] = ($tables[$table] ?? 0) + (int) $part->rows;
        }

        // A table with no rows still has a segment (its header), so every table of the plan appears.
        $ordered = [];
        foreach ((array) $ss['tables'] as $table) {
            $ordered[$table] = $tables[$table] ?? 0;
        }

        $point->manifest()->write([
            'v' => 1, 'run_id' => $state->id, 'created_at' => now()->toIso8601String(), 'complete' => false,
            'tables' => $ordered, 'rows' => array_sum($ordered), 'parts' => $parts,
        ]);

        $ss['phase'] = 'env';
        $state->stageState = $ss;

        return ImportStepResult::progress(0.85, 'Saving the settings that will change.');
    }

    /** The .env keys the import is allowed to change, as they are now (null = the key is not in the file at all). */
    private function saveEnv(ImportState $state): ImportStepResult
    {
        $journal = $this->store->directory($state->id).DIRECTORY_SEPARATOR.'journal';
        MigrationPaths::ensureDirectory($journal);

        $file = new AtomicJsonFile($journal.DIRECTORY_SEPARATOR.'env-before.json');

        if ($file->read() === null) { // the first image wins: a resume never overwrites it with already-changed values
            $file->write(['at' => now()->toIso8601String()] + $this->env->snapshot());
        }

        $ss = $state->stageState;
        $ss['phase'] = 'verify';
        $ss['vi'] = 0;
        $state->stageState = $ss;

        return ImportStepResult::progress(0.9, 'Checking the copy.');
    }

    /** Read every segment back: size, SHA-256, and the number of rows, against the manifest. */
    private function verify(ImportState $state, ImportContext $context): ImportStepResult
    {
        $ss = $state->stageState;
        $point = RecoveryPoint::of($state->id, $this->store);
        $manifest = (array) $point->read();
        $parts = array_values((array) ($manifest['parts'] ?? []));
        $index = (int) ($ss['vi'] ?? 0);

        while ($index < count($parts)) {
            $this->verifyPart($point, $parts[$index]);
            $index++;

            $ss['vi'] = $index;
            $state->stageState = $ss;

            if ($index < count($parts) && $context->expired()) {
                return ImportStepResult::progress(0.9 + 0.1 * ($index / max(1, count($parts))), 'Checking the copy.');
            }
        }

        $manifest['complete'] = true;
        $manifest['verified_at'] = now()->toIso8601String();
        $point->manifest()->write($manifest);

        $this->cleanUpRun((int) $ss['run_id']);

        $state->package['recovery'] = [
            'tables' => count((array) $manifest['tables']), 'rows' => (int) $manifest['rows'], 'parts' => count($parts),
            'bytes' => array_sum(array_column($parts, 'bytes')), 'verified_at' => $manifest['verified_at'],
        ];
        $state->setFlag('recovery.done');
        $state->stageState = [];

        return ImportStepResult::done('The way back is saved and checked.');
    }

    /**
     * @param  array<string, mixed>  $part
     */
    private function verifyPart(RecoveryPoint $point, array $part): void
    {
        $path = $point->path((string) $part['file']);

        if (! is_file($path) || filesize($path) !== (int) $part['bytes'] || ! hash_equals((string) $part['sha256'], (string) hash_file('sha256', $path))) {
            throw new MigrationException(MigrationErrorCode::PackageCorrupt, ['part' => 'recovery '.$part['file']]);
        }

        $rows = 0;
        foreach ($point->lines($part) as $unused) {
            $rows++;
        }

        if ($rows !== (int) $part['rows']) {
            throw new MigrationException(MigrationErrorCode::PackageCorrupt, ['part' => 'recovery rows of '.$part['file']]);
        }
    }

    /** The scaffolding run of the copier is not needed any more. */
    private function cleanUpRun(int $runId): void
    {
        $run = BackupRun::find($runId);

        if ($run === null) {
            return;
        }

        $disk = Storage::disk((string) $run->disk);
        $directory = 'backups/'.$runId;

        if ($disk->exists($directory)) {
            File::deleteDirectory($disk->path($directory));
        }

        $run->parts()->delete();
        $run->delete();
    }
}
