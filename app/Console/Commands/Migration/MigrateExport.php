<?php

namespace App\Console\Commands\Migration;

use App\Console\Concerns\EmitsJson;
use App\Console\Concerns\PassphraseSourceException;
use App\Console\Concerns\ReadsPassphrase;
use App\Models\BackupRun;
use App\Services\Migration\ExitCode;
use App\Services\Migration\Export\ExportManager;
use App\Services\Migration\Export\ExportOptions;
use App\Services\Migration\Export\ExportPlan;
use App\Services\Migration\Export\ExportPlanner;
use App\Services\Migration\Export\FileCounter;
use App\Services\Migration\Export\ProgressPresenter;
use App\Services\Migration\Groups\FileRoot;
use App\Services\Migration\Groups\GroupKey;
use App\Services\Migration\Groups\GroupRegistry;
use App\Services\Migration\Groups\Preset;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Package\PassphraseInput;
use App\Services\Migration\Ux\MigrationText;
use Illuminate\Console\Command;
use Illuminate\Support\Number;

/**
 * Export the whole site, or the parts of it you choose, into one encrypted `.oeparts` file.
 *
 * A live copy (the default) keeps the shop running. `--freeze` puts the shop in maintenance while it
 * copies, for a consistent cut-over, and releases it afterwards (or holds it until you run
 * `oeparts:migrate:unfreeze`). This command drives the export to the end itself, so a freeze never depends on
 * a browser tab or on a queue worker.
 */
class MigrateExport extends Command
{
    use EmitsJson;
    use ReadsPassphrase;

    protected $signature = 'oeparts:migrate:export
        {--preset= : full_live, staging, new_store_same_catalog or catalog_only}
        {--groups= : Comma-separated data groups instead of a preset (core is always included)}
        {--freeze : Put the shop in maintenance while copying, for a consistent cut-over (default: a live copy, the shop keeps running)}
        {--no-hold : With --freeze: reopen the shop as soon as the package is verified}
        {--hold-minutes= : With --freeze: keep the shop frozen this long after the package is sealed (0 = until you run oeparts:migrate:unfreeze)}
        {--include-secrets : Include passwords and API keys, locked separately inside the package}
        {--anonymise : Scramble customer details in the copy}
        {--reset-sequences : Order and invoice numbers start again in the copy}
        {--generate-passphrase : Make a strong passphrase and show it once}
        {--plan-only : Show what would be exported and stop}
        {--json : Print newline-delimited JSON events on standard output}
        '.self::PASSPHRASE_SIGNATURE;

    protected $description = 'Export the site (or chosen parts of it) into one encrypted .oeparts package';

    public function handle(GroupRegistry $registry, ExportPlanner $planner, ExportManager $manager, ProgressPresenter $presenter): int
    {
        try {
            $selection = $this->selection($registry);
        } catch (\InvalidArgumentException $e) {
            return $this->finish($this->reportUsage($e->getMessage()));
        }

        try {
            $options = new ExportOptions(
                consistency: $this->option('freeze') ? ExportOptions::FREEZE : ExportOptions::LIVE,
                includeSecrets: (bool) $this->option('include-secrets'),
                anonymise: $this->option('anonymise') ? true : null,
                resetSequences: $this->option('reset-sequences') ? true : null,
                hold: ! $this->option('no-hold'),
                holdReleaseMinutes: $this->option('hold-minutes') !== null ? (int) $this->option('hold-minutes') : null,
            );
        } catch (\InvalidArgumentException $e) {
            return $this->finish($this->reportUsage($e->getMessage()));
        }

        $plan = $planner->plan($selection, $options, $this->countFiles($selection, $registry, $planner));
        $this->describe($plan);

        if (! $plan->isRunnable()) {
            foreach ($plan->blockers as $blocker) {
                $this->reportError(new MigrationException(MigrationErrorCode::from($blocker['code']), $blocker['params']));
            }

            return $this->finish(ExitCode::PreflightBlocked, ['plan' => $plan->toArray()]);
        }

        if ($this->option('plan-only')) {
            return $this->finish(ExitCode::Ok, ['plan' => $plan->toArray()]);
        }

        try {
            $passphrase = $this->passphrase();
        } catch (PassphraseSourceException $e) {
            return $this->finish($this->reportUsage($e->getMessage()));
        }

        try {
            $run = $manager->startExport($plan, $passphrase, 'cli');
        } catch (MigrationException $e) {
            $this->reportError($e);

            return $this->finish(match ($e->errorCode) {
                MigrationErrorCode::LockBusy => ExitCode::SystemBusy,
                MigrationErrorCode::PassphraseEmpty, MigrationErrorCode::KdfPassphraseWeak => ExitCode::Usage,
                default => ExitCode::PreflightBlocked,
            });
        }

        $this->human('Export '.$run->getKey().' started.'.($plan->isFreeze() ? ' The shop is in maintenance until it finishes.' : ' The shop keeps running.'));
        $this->event('started', ['run_id' => $run->getKey(), 'consistency' => $plan->consistency]);

        return $this->drive($run, $manager, $presenter, $plan);
    }

    // ---- choosing what to export ---------------------------------------------------------------------------

    /**
     * @return Preset|array<int, GroupKey>
     *
     * @throws \InvalidArgumentException with a message that says what to type instead
     */
    private function selection(GroupRegistry $registry): Preset|array
    {
        $preset = $this->option('preset');
        $groups = $this->option('groups');

        if ($preset !== null && $groups !== null) {
            throw new \InvalidArgumentException('Give --preset or --groups, not both.');
        }

        if ($preset !== null) {
            return Preset::tryFrom((string) $preset)
                ?? throw new \InvalidArgumentException("Unknown preset '{$preset}'. Choose one of: ".implode(', ', array_map(fn (Preset $p): string => $p->value, Preset::cases())).'.');
        }

        if ($groups !== null) {
            $keys = [];

            foreach (array_filter(array_map('trim', explode(',', (string) $groups))) as $name) {
                $group = GroupKey::tryFrom($name);

                if ($group === null || $group === GroupKey::System) {
                    throw new \InvalidArgumentException("Unknown data group '{$name}'. Choose from: ".implode(', ', array_keys($registry->selectable())).'.');
                }

                $keys[] = $group;
            }

            return $keys;
        }

        throw new \InvalidArgumentException('Say what to export: --preset='.implode('|', array_map(fn (Preset $p): string => $p->value, Preset::cases())).' or --groups=catalog,content,...');
    }

    /**
     * Count the files that would travel, so the estimate includes them. Takes as long as it takes.
     *
     * @param  Preset|array<int, GroupKey>  $selection
     * @return array{count: int, bytes: int, complete: bool}
     */
    private function countFiles(Preset|array $selection, GroupRegistry $registry, ExportPlanner $planner): array
    {
        $groups = $selection instanceof Preset ? $selection->groups() : $selection;
        $roots = array_map(FileRoot::parse(...), array_map('strval', $registry->fileRootsOf([GroupKey::Core, ...$groups])));

        if ($roots === []) {
            return ['count' => 0, 'bytes' => 0, 'complete' => true];
        }

        $this->human('Counting files...');
        $counter = FileCounter::forApplication();
        $state = null;

        do {
            $result = $counter->advance($roots, $state, 2.0);
            $state = $result['state'];
        } while (! $result['done']);

        return ['count' => $result['count'], 'bytes' => $result['bytes'], 'complete' => true];
    }

    private function describe(ExportPlan $plan): void
    {
        $estimate = $plan->estimate;

        $this->human('This export will include: '.implode(', ', $plan->groups).'.');

        foreach ($plan->added as $group => $reasons) {
            $this->human("  added {$group}: ".$reasons[0]['text']);
        }
        foreach ($plan->dropped as $dropped) {
            $this->human("  left out {$dropped['group']} (it cannot be scrubbed of personal data)");
        }

        $this->human(sprintf(
            'Size: about %s of data and %s of files; time: roughly %d to %d minutes. These are estimates.',
            Number::fileSize($estimate->databaseBytes), Number::fileSize($estimate->files['bytes']), $estimate->minutesLow, $estimate->minutesHigh
        ));

        foreach ($plan->warnings as $warning) {
            $this->humanWarn(app(MigrationText::class)->get('logical.'.$warning['id'], ['count' => $warning['count'], 'approximate' => $warning['approximate'] ? 'about ' : '']));
        }

        if ($plan->unclassified !== []) {
            $this->humanWarn('Tables nobody has classified yet are NOT exported: '.implode(', ', $plan->unclassified).'.');
        }

        $this->event('plan', ['groups' => $plan->groups, 'minutes_low' => $estimate->minutesLow, 'minutes_high' => $estimate->minutesHigh, 'consistency' => $plan->consistency, 'warnings' => array_column($plan->warnings, 'id'), 'blockers' => array_column($plan->blockers, 'code')]);
    }

    // ---- the passphrase -----------------------------------------------------------------------------------------

    /** @throws PassphraseSourceException */
    private function passphrase(): string
    {
        if ($this->option('generate-passphrase')) {
            $passphrase = PassphraseInput::generate();
            $this->humanWarn("Passphrase for this package: {$passphrase}");
            $this->humanWarn('Write it down now. It is not stored anywhere, and without it the package cannot be opened.');

            return $passphrase;
        }

        return $this->readNewPassphrase('New passphrase for this package');
    }

    // ---- driving the run --------------------------------------------------------------------------------------------

    private function drive(BackupRun $run, ExportManager $manager, ProgressPresenter $presenter, ExportPlan $plan): int
    {
        $lastLine = '';

        do {
            $progress = $manager->advance($run);
            $run->refresh();
            $shown = $presenter->present($progress, $run);

            $line = sprintf('%3d%%  %s', $shown->percent(), $shown->message);
            if ($line !== $lastLine) {
                $this->human($line);
                $lastLine = $line;
            }
            $this->event('progress', ['percent' => $shown->percent(), 'phase' => $shown->phase, 'message' => $shown->message]);
        } while (! $progress->done);

        if ($progress->status !== BackupRun::STATUS_SUCCESS) {
            $this->human('<error>The export failed: '.$progress->error.'</error>');
            $this->event('error', ['code' => (string) ($run->meta['error_code'] ?? 'import_failed_step'), 'message' => (string) $progress->error]);

            return $this->finish(ExitCode::Failed, ['run_id' => $run->getKey()]);
        }

        $package = (array) $run->meta['package'];
        $verdict = (string) ($run->meta['export_report']['consistency'] ?? $plan->consistency);

        $this->human('Done: '.$package['path'].' ('.Number::fileSize($package['size']).')');
        $this->human('Fingerprint: '.$package['fingerprint'].'   (compare it with the one shown when you import)');
        $this->human('Consistency: '.$verdict.($verdict === 'not_consistent' ? ' - something changed while the shop was frozen; see the report inside the package.' : ''));

        if ($plan->isFreeze() && $plan->hold) {
            $this->humanWarn('The shop is still frozen. Run `php artisan oeparts:migrate:unfreeze` when you are ready to reopen it'.($plan->holdReleaseMinutes > 0 ? " (it reopens by itself in {$plan->holdReleaseMinutes} minutes)." : '.'));
        }

        $report = (array) ($run->meta['export_report']['secrets'] ?? []);
        $left = $report['configured_keys'] ?? [];
        if ($left !== []) {
            $this->humanWarn('Settings you must enter again on the new site: '.implode(', ', $left).'.');
        }

        return $this->finish(
            $verdict === 'not_consistent' ? ExitCode::FinishedWithWarnings : ExitCode::Ok,
            ['run_id' => $run->getKey(), 'package' => ['path' => $package['path'], 'size' => $package['size'], 'sha256' => $package['sha256'], 'fingerprint' => $package['fingerprint']], 'consistency' => $verdict, 'reenter' => $left]
        );
    }
}
