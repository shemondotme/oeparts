<?php

namespace App\Services\Migration\Export;

use App\Models\BackupRun;
use App\Security\PackageAudit;
use App\Services\Backup\BackupLock;
use App\Services\Backup\BackupManager;
use App\Services\Backup\BackupManifest;
use App\Services\Backup\BackupProgress;
use App\Services\Backup\Exceptions\BackupLockException;
use App\Services\Backup\LockCustody;
use App\Services\Backup\StageRegistry;
use App\Services\Migration\Export\Transform\Pseudonymizer;
use App\Services\Migration\KeyEscrow;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Package\Kdf;
use App\Services\Migration\Package\PackageWriter;
use App\Services\Migration\Package\PassphraseInput;
use App\Services\Migration\Package\WriterLedger;
use App\Services\Updates\UpdateChecker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * The export engine: the backup engine's resumable state machine, driven through the export stages
 * (plan, database, files, verify, pack) under its own profile and its own lock owner.
 *
 * What is added to the engine, and nothing more:
 *  - startExport() turns a plan and a passphrase into a run: it checks the plan can run at all, derives
 *    the package keys ONCE (the passphrase is never kept), parks them in the KeyEscrow and creates the
 *    package's `.part` file and ledger;
 *  - advance() first makes sure the shared lock is still this run's, taking it back if a janitor released it
 *    while the run sat idle and failing the run (writing nothing more) if somebody else holds it now;
 *  - fail() and finalize() clean up: lift a freeze that belongs to the run, delete the escrow entry, write the
 *    audit line;
 *  - progress is weighted by what each stage really has to do, not one fifth each.
 */
class ExportManager extends BackupManager
{
    public function __construct(
        StageRegistry $stages,
        private readonly BackupLock $exportLock,
        BackupManifest $manifest,
        private readonly KeyEscrow $escrow,
        private readonly ExportFreeze $freeze,
        private readonly PackageAudit $audit,
        private readonly PackageOutbox $outbox,
        private readonly MigrationWorkerProbe $workerProbe,
    ) {
        parent::__construct($stages, $exportLock, $manifest);
    }

    protected function allowedProfiles(): array
    {
        return [...parent::allowedProfiles(), BackupRun::PROFILE_EXPORT];
    }

    /**
     * Begin an export.
     *
     * @param  string  $via  'cli' or 'ui': the interface asking. A freeze must be driven by the command line (or, from step 3.12, a queue
     *                       worker that has proven it serves the migration queue); a browser tab cannot be trusted to release it.
     *
     * @throws MigrationException when the plan cannot run, the passphrase is not good enough, or the shared lock is held
     */
    public function startExport(ExportPlan $plan, string $passphrase, string $via = 'cli'): BackupRun
    {
        foreach ($plan->blockers as $blocker) {
            throw new MigrationException(MigrationErrorCode::from($blocker['code']), $blocker['params']);
        }

        $queueDriven = $plan->isFreeze() && $via === 'ui';

        if ($queueDriven) {
            // A freeze must never depend on a browser tab. From the admin it is driven by a queue worker, and only a worker that has
            // PROVEN it serves the migration queue will do: `queue.default` alone proves nothing.
            if (config('queue.default') === 'sync') {
                throw new MigrationException(MigrationErrorCode::FreezeNeedsQueueOrCli);
            }

            if (! $this->workerProbe->isFresh()) {
                throw new MigrationException(MigrationErrorCode::NoWorker);
            }
        }

        $this->assertPassphrase($passphrase);

        $path = $this->outbox->newPath($this->hostOfSite());
        $meta = ['export_plan' => $plan->toArray(), 'via' => $via, 'final_path' => $path];

        if ($plan->anonymise) {
            // Kept only here, never in the package: two exports of the same shop cannot be lined up.
            $meta['anonymise_salt'] = Pseudonymizer::newSalt();
        }

        try {
            $run = parent::start(BackupRun::PROFILE_EXPORT, BackupRun::TRIGGER_MANUAL, $meta);
        } catch (BackupLockException $e) {
            throw new MigrationException(MigrationErrorCode::LockBusy, [], $e);
        }

        try {
            $writer = PackageWriter::create($path, $passphrase, $this->packageMeta($plan, $run));
            $packageId = $writer->packageIdHex();
            $this->escrow->put($this->escrowId($run), $packageId, $writer->keys());
            unset($writer);

            $run->meta = array_merge((array) $run->meta, ['escrow_id' => $this->escrowId($run), 'package_id' => $packageId]);

            // A queue-driven run waits as `pending` until its driver takes the first step (which is when a freeze engages), so a
            // driver that never starts leaves nothing frozen and the janitor can tell "never picked up" from "stopped moving".
            if ($queueDriven) {
                $run->status = BackupRun::STATUS_PENDING;
            }

            $run->save();

            $this->audit->record('export.started', (string) $run->getKey(), [
                'preset' => $plan->preset, 'groups' => $plan->groups, 'consistency' => $plan->consistency, 'via' => $via,
                'anonymised' => $plan->anonymise, 'secrets' => $plan->includeSecrets, 'package' => basename($path),
            ]);

            if ($queueDriven) {
                RunExportJob::dispatch((int) $run->getKey());
            }
        } catch (\Throwable $e) {
            $this->fail($run, 'Could not start the package: '.$e->getMessage());

            throw $e;
        }

        return $run;
    }

    public function advance(BackupRun $run): BackupProgress
    {
        if ($run->status === BackupRun::STATUS_PENDING) {
            $run->status = BackupRun::STATUS_RUNNING;
            $run->save();
        }

        if (! $run->isTerminal()) {
            $freeze = FreezeState::read();

            // The freeze this run was holding ran out while the run was still going: the copy can no longer be called consistent.
            if ($freeze !== null && $freeze->belongsToRun((int) $run->getKey()) && ! $freeze->isLive()) {
                $meta = (array) $run->meta;
                $meta['error_code'] = 'freeze_expired';
                $meta['export_report'] = array_merge((array) ($meta['export_report'] ?? []), ['consistency' => 'not_consistent']);
                $run->meta = $meta;

                return $this->fail($run, 'The freeze ran out before the export finished: the copy is not consistent. Run the export again.');
            }

            $custody = $this->exportLock->ensureHeld($run->lockOwner());

            if ($custody === LockCustody::Lost) {
                $meta = (array) $run->meta;
                $meta['error_code'] = MigrationErrorCode::LockLost->value;
                $run->meta = $meta;

                return $this->fail($run, 'The shared backup/update lock is held by someone else now: this export stopped without writing anything more.');
            }
        }

        return parent::advance($run);
    }

    public function fail(BackupRun $run, string $error): BackupProgress
    {
        $progress = parent::fail($run, $error);

        $this->endOfRun($run, 'export.failed', ['error' => $error]);

        return $progress;
    }

    protected function finalize(BackupRun $run): BackupProgress
    {
        $package = (array) ($run->meta['package'] ?? []);

        if ($package === []) {
            throw new \RuntimeException('The export finished its stages but no package was sealed.');
        }

        $run->total_bytes = (int) $package['size'];
        $run->part_count = (int) $package['entries'];
        $run->manifest_path = null;
        $run->checksum = (string) $package['sha256'];
        $run->status = BackupRun::STATUS_SUCCESS;
        $run->finished_at = now();
        $run->clearCheckpoint();
        $run->save();

        $this->exportLock->releaseIfOwnedBy($run->lockOwner());

        $plan = ExportPlan::fromArray((array) $run->meta['export_plan']);
        $this->outbox->writeInfo($package['path'], [
            'fingerprint' => $package['fingerprint'], 'sha256' => $package['sha256'], 'size' => $package['size'], 'entries' => $package['entries'],
            'created_at' => now()->toIso8601String(), 'expires_at' => now()->addDays((int) config('migration.retention.export_days', 7))->toIso8601String(),
            'groups' => $plan->groups, 'preset' => $plan->preset, 'consistency' => $run->meta['export_report']['consistency'] ?? $plan->consistency,
            'anonymised' => $plan->anonymise, 'secrets_included' => $plan->includeSecrets, 'run_id' => $run->getKey(),
        ]);

        $this->endOfRun($run, 'export.completed', ['package' => basename($package['path']), 'size' => $package['size'], 'fingerprint' => $package['fingerprint']]);

        return BackupProgress::success($run);
    }

    protected function overallPercent(BackupRun $run, array $stages, int $stageIndex, float $stageFraction): int
    {
        $plan = (array) ($run->meta['export_plan']['estimate'] ?? []);
        $database = max(1, (int) ($plan['database_compressed_bytes'] ?? 0) + 1);
        $files = max(0, (int) ($plan['files_compressed_bytes'] ?? 0));
        $copying = 0.80;

        $weights = [
            'plan' => 0.02,
            'db' => $copying * $database / ($database + $files),
            'files' => $copying * $files / ($database + $files),
            'verify' => 0.03,
            'pack' => 0.15,
        ];

        $done = 0.0;
        $total = 0.0;

        foreach ($stages as $index => $stage) {
            $weight = $weights[$stage->key()] ?? (1 / max(1, count($stages)));
            $total += $weight;

            if ($index < $stageIndex) {
                $done += $weight;
            } elseif ($index === $stageIndex) {
                $done += $weight * $stageFraction;
            }
        }

        return (int) round(100 * $done / max(0.0001, $total));
    }

    // ---- internals ---------------------------------------------------------------------------------------

    private function escrowId(BackupRun $run): string
    {
        return 'export-'.$run->getKey();
    }

    /** Everything that happens when a run is over, however it ended. */
    private function endOfRun(BackupRun $run, string $event, array $context): void
    {
        try {
            $freeze = FreezeState::read();
            $plan = (array) ($run->meta['export_plan'] ?? []);

            // A failed export must not leave the shop frozen. A successful one that holds its freeze keeps it.
            if ($freeze !== null && $freeze->belongsToRun((int) $run->getKey()) && ($event === 'export.failed' || ! ($plan['hold'] ?? false))) {
                $this->freeze->release($event);
            }

            $this->escrow->delete($this->escrowId($run));
            $this->purgeStaging($run, $event === 'export.failed');
            $this->audit->record($event, (string) $run->getKey(), $context);
        } catch (\Throwable $e) {
            // The run is already over; cleanup failing must not turn a finished export into an exception, but it must be seen.
            Log::channel('migration')->error('Cleanup after export '.$run->getKey().' failed: '.$e->getMessage(), ['event' => $event]);
        }
    }

    /**
     * Remove what the run staged. A failed run also loses the package it was building: a half-written file is never kept
     * (and never sits at the final path, only as `.part` with its ledger, which are removed here).
     */
    private function purgeStaging(BackupRun $run, bool $failed): void
    {
        $directory = 'backups/'.$run->getKey();

        foreach (array_unique([(string) $run->disk, (string) config('backup.staging_disk', 'local')]) as $disk) {
            if ($disk !== '' && Storage::disk($disk)->exists($directory)) {
                Storage::disk($disk)->deleteDirectory($directory);
            }
        }

        $final = (string) ($run->meta['final_path'] ?? '');

        if ($failed && $final !== '') {
            @unlink($final.'.part');
            (new WriterLedger($final.'.part.ledger'))->destroy();
        }
    }

    /** @throws MigrationException */
    private function assertPassphrase(string $passphrase): void
    {
        $problem = PassphraseInput::validate($passphrase, Kdf::preferredKdfId());

        if ($problem === null) {
            return;
        }

        throw new MigrationException(
            $problem === PassphraseInput::EMPTY ? MigrationErrorCode::PassphraseEmpty : MigrationErrorCode::KdfPassphraseWeak,
            ['reason' => $problem]
        );
    }

    private function hostOfSite(): ?string
    {
        $url = (string) (settings('general.site_url', '') ?: config('app.url'));
        $host = parse_url(str_contains($url, '://') ? $url : 'https://'.$url, PHP_URL_HOST);

        return is_string($host) ? $host : null;
    }

    /**
     * What the package says about itself (the unencrypted-to-nobody `meta` entry: still encrypted, but
     * readable as soon as the passphrase is typed). Nothing secret and no personal data.
     *
     * @return array<string, mixed>
     */
    private function packageMeta(ExportPlan $plan, BackupRun $run): array
    {
        return [
            'source' => ['domain' => $this->hostOfSite(), 'url' => (string) config('app.url')],
            'created_at' => now()->toIso8601String(),
            'app_version' => app(UpdateChecker::class)->currentVersion(),
            'php_version' => PHP_VERSION,
            'db' => ['driver' => DB::connection()->getDriverName(), 'version' => $run->db_version],
            'export' => [
                'run_id' => $run->getKey(), 'preset' => $plan->preset, 'groups' => $plan->groups, 'consistency' => $plan->consistency,
                'anonymised' => $plan->anonymise, 'secrets_included' => $plan->includeSecrets, 'sequences_reset' => $plan->resetSequences,
                'seo_slice' => $plan->seoSlice,
            ],
        ];
    }
}
