<?php

namespace App\Services\Migration\Export\Stages;

use App\Models\BackupChunk;
use App\Models\BackupRun;
use App\Services\Backup\Contracts\BackupStage;
use App\Services\Backup\StageStepResult;
use App\Services\Migration\Export\ExportFreeze;
use App\Services\Migration\Export\ExportPlan;
use App\Services\Migration\Export\SecretsStaging;
use App\Services\Migration\KeyEscrow;
use App\Services\Migration\Package\PackageReader;
use App\Services\Migration\Package\PackageWriter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * The last stage: turns the staged parts into the one `.oeparts` file.
 *
 *   ADD     every staged part becomes one entry, in the order the parts were created, and its staging file
 *           is deleted once the entry is committed (so a 25 GB export never needs 50 GB). The secrets, if
 *           any, become the separately-keyed `secrets` entry, then the report, and the package is sealed.
 *   VERIFY  the finished package is opened with the real reader and checked end to end, in budgeted slices
 *           through the reader's resumable cursor: what is promised to the person is what was verified.
 *
 * The keys come from the KeyEscrow (the passphrase is never kept); the writer is resumed from them every
 * step. An entry is the unit of work: a step adds entries until its time budget is spent.
 */
class PackageAssembleStage implements BackupStage
{
    public function __construct(
        private readonly KeyEscrow $escrow,
        private readonly ExportFreeze $freeze,
    ) {}

    public function key(): string
    {
        return 'pack';
    }

    public function step(BackupRun $run, array $state): StageStepResult
    {
        $plan = ExportPlan::fromArray((array) ($run->meta['export_plan'] ?? throw new \RuntimeException('This export run has no plan.')));
        $state = $state === [] ? ['phase' => 'add', 'verify' => null, 'added' => 0] : $state;
        $deadline = microtime(true) + max(0.0, (float) config('migration.export.batch_seconds', 10));

        if ($state['phase'] === 'add') {
            return $this->add($run, $plan, $state, $deadline);
        }

        return $this->verify($run, $plan, $state, $deadline);
    }

    // ---- add ---------------------------------------------------------------------------------------

    /** @param  array<string, mixed>  $state */
    private function add(BackupRun $run, ExportPlan $plan, array $state, float $deadline): StageStepResult
    {
        $finalPath = (string) ($run->meta['final_path'] ?? throw new \RuntimeException('This export run has no package path.'));
        $keys = $this->escrow->require((string) ($run->meta['escrow_id'] ?? ''))->keys;
        $writer = PackageWriter::resumeWithKeys($finalPath, $keys);

        $known = array_column($writer->entryRecords(), 'name');
        /** @var Collection<int, BackupChunk> $parts */
        $parts = $run->parts()->orderBy('id')->get();
        $disk = Storage::disk((string) config('backup.staging_disk', 'local'));

        foreach ($parts as $part) {
            if (in_array($part->name, $known, true)) {
                // Committed before a crash, staging file not yet deleted.
                $disk->delete($part->path);

                continue;
            }

            $meta = (array) $part->meta;
            $options = [
                'group' => $meta['group'] ?? null,
                'codec' => $meta['codec'] ?? 'none',
                'raw_len' => $meta['raw_len'] ?? null,
                'rows' => $part->rows,
                'attrs' => array_filter(['table' => $meta['table'] ?? null, 'segment' => $meta['segment'] ?? null, 'kind' => $meta['kind'] ?? null, 'volume' => $meta['index'] ?? null], static fn ($v): bool => $v !== null),
            ];

            if ($options['raw_len'] === null) {
                unset($options['raw_len']);
            }

            $writer->addEntryFromFile((string) $part->name, $this->kindOf($part, $meta), $disk->path($part->path), $options);
            $disk->delete($part->path);
            $state['added']++;

            if (microtime(true) >= $deadline) {
                return StageStepResult::progress($state, null, "Packing: {$state['added']} of {$parts->count()} pieces", 0.8 * $state['added'] / max(1, $parts->count()));
            }
        }

        $this->addSecrets($run, $plan, $writer);
        $this->addReport($run, $plan, $writer);
        $sealed = $writer->seal();

        // The escrow entry stays: verification (next phase) still needs the keys. The manager deletes it when the run ends.
        $meta = $run->meta ?? [];
        $meta['package'] = ['path' => $sealed['path'], 'size' => $sealed['size'], 'sha256' => $sealed['sha256'], 'fingerprint' => $sealed['fingerprint'], 'entries' => $sealed['entries']];
        $run->meta = $meta;

        $state['phase'] = 'verify';

        return StageStepResult::progress($state, null, 'The package is sealed; checking it end to end.', 0.8);
    }

    /** @param  array<string, mixed>  $meta */
    private function kindOf(BackupChunk $part, array $meta): string
    {
        return match ($meta['kind'] ?? null) {
            'volume' => 'files',
            'index' => 'files-index',
            default => $part->type === BackupChunk::TYPE_DB ? 'db' : 'files',
        };
    }

    private function addSecrets(BackupRun $run, ExportPlan $plan, PackageWriter $writer): void
    {
        if (! $plan->includeSecrets) {
            return;
        }

        $disk = Storage::disk((string) config('backup.staging_disk', 'local'));
        $path = $disk->path(sprintf('backups/%d/export/secrets.staging', $run->getKey()));
        $staging = new SecretsStaging($path, $this->escrow->require((string) $run->meta['escrow_id'])->keys->secrets());

        $lines = '';
        foreach ($staging->read() as $secret) {
            $lines .= json_encode($secret, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
        }

        if (! in_array('secrets', array_column($writer->entryRecords(), 'name'), true)) {
            $writer->addSecrets($lines);
        }

        $staging->destroy();
    }

    private function addReport(BackupRun $run, ExportPlan $plan, PackageWriter $writer): void
    {
        if (in_array('report/export-report.json', array_column($writer->entryRecords(), 'name'), true)) {
            return;
        }

        $report = (array) ($run->meta['export_report'] ?? []);
        unset($report['fence']['before'], $report['fence']['after']); // the pictures are for the operator's log, the package carries the verdict

        $json = json_encode([
            'plan' => ['preset' => $plan->preset, 'groups' => $plan->groups, 'consistency' => $plan->consistency, 'anonymised' => $plan->anonymise, 'secrets_included' => $plan->includeSecrets, 'sequences_reset' => $plan->resetSequences],
            'report' => $report,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

        $writer->addEntryFromString('report/export-report.json', 'report', $json, ['codec' => 'none']);
    }

    // ---- verify --------------------------------------------------------------------------------------

    /** @param  array<string, mixed>  $state */
    private function verify(BackupRun $run, ExportPlan $plan, array $state, float $deadline): StageStepResult
    {
        $package = (array) $run->meta['package'];
        $reader = PackageReader::openWithKeys($package['path'], $this->escrow->require((string) $run->meta['escrow_id'])->keys);

        try {
            $cursor = $state['verify'];

            do {
                $step = $reader->verifyStep($cursor, max(1, (int) config('migration.export.verify_budget_bytes', 64 * 1024 * 1024)));
                $cursor = $step['cursor'];
            } while (! $step['done'] && microtime(true) < $deadline);
        } finally {
            $reader->close();
        }

        if (! $step['done']) {
            $state['verify'] = $cursor;
            $done = ($cursor['phase'] ?? 'hash') === 'entries' ? 0.9 : 0.85;

            return StageStepResult::progress($state, null, 'Checking the package: '.($cursor['phase'] ?? 'hash'), $done);
        }

        // The package is sealed and verified: a held freeze now runs on its own timer.
        if ($plan->isFreeze() && $plan->hold) {
            $this->freeze->hold($plan->holdReleaseMinutes);
        }

        return StageStepResult::complete(null, 'The package is complete and verified.');
    }
}
