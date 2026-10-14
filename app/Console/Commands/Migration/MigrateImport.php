<?php

namespace App\Console\Commands\Migration;

use App\Console\Concerns\EmitsJson;
use App\Console\Concerns\PassphraseSourceException;
use App\Console\Concerns\ReadsPassphrase;
use App\Services\Instance\SafeMode;
use App\Services\Migration\ExitCode;
use App\Services\Migration\Import\ImportGroupChoice;
use App\Services\Migration\Import\ImportInbox;
use App\Services\Migration\Import\ImportManager;
use App\Services\Migration\Import\ImportReportBuilder;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\RecoveryNote;
use App\Services\Migration\Import\TargetClass;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use Illuminate\Console\Command;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\select;

/**
 * Replace this site with the contents of a .oeparts package, from the command line.
 *
 * It does the same steps as the web runner, in the same order and with the same safeguards, and none of the flags can skip one:
 * the package is opened and checked, the launch checks are shown, a recovery point is made before anything changes, and the site
 * is only replaced after the typed confirmation (`--confirm=REPLACE`; a site that has been USED also needs `--replace-populated`,
 * so a script written for fresh installs can never wipe a live shop by accident).
 *
 *   --preflight-only   show what the checks say, change nothing, discard the run
 *   --dry-run          do everything that changes nothing and print the plan
 *   --resume           carry on a run that stopped (a closed terminal, a budget, a power cut); --run=ID picks which
 *   --rollback         take back a run that failed half way, or a completed one (refused if the site was used since,
 *                      unless --accept-loss)
 *   --budget=SECONDS   stop after this long and say so (exit 10); --resume continues
 *
 * Exit codes: 0 ok; 1 failed; 2 usage; 3 checks block it; 4 package unreadable; 5 wrong passphrase; 6 system busy;
 * 7 not confirmed; 8 stopped by the person (Ctrl+C); 9 finished with warnings (only under --strict); 10 not finished (budget).
 */
class MigrateImport extends Command
{
    use EmitsJson;
    use ReadsPassphrase;

    protected $signature = 'oeparts:migrate:import
        {source? : Path to the .oeparts file, or the name of a file in storage/app/import}
        {--preset= : full_live, staging, new_store_same_catalog or catalog_only (only what the package holds comes)}
        {--groups= : Comma-separated groups instead of a preset (core always comes)}
        {--without= : Comma-separated groups to leave out}
        {--launch= : live (this is the real shop) or staging (a copy that must stay harmless); there is no default}
        {--site-url= : The address this site is reached at (default: APP_URL)}
        {--also-from=* : Another old host whose links should be rewritten too}
        {--instance-id= : A short identity for this installation (default: made from the run)}
        {--mail-allowlist= : Addresses or @domains a staging copy may still mail}
        {--include-secrets : Bring the passwords and keys the package carries}
        {--extra-admin-email= : Also create this administrator (password from OEPARTS_EXTRA_ADMIN_PASSWORD or a hidden prompt)}
        {--extra-admin-name=Administrator : Name of that administrator}
        {--preflight-only : Show what the checks say and change nothing}
        {--dry-run : Do everything that changes nothing and print the plan}
        {--confirm= : Type REPLACE to confirm that this site is to be replaced}
        {--replace-populated : Also confirm replacing a site that has orders, customers or payments}
        {--accept-warnings : Accept the warnings that need an acknowledgement}
        {--resume : Carry on a run that stopped}
        {--rollback : Take back a run that failed half way, or a completed one}
        {--accept-loss : With --rollback of a completed import: accept losing what was done on the site since}
        {--run= : The id of the run for --resume or --rollback (default: the one in progress)}
        {--budget=0 : Stop after this many seconds (0 = no limit); --resume continues}
        {--strict : Exit with 9 when the import finished but has warnings}
        {--json : Print newline-delimited JSON events on standard output}
        '.self::PASSPHRASE_SIGNATURE;

    protected $description = 'Replace this site with the contents of a .oeparts package';

    /** Set by Ctrl+C: the loop stops after the step in progress (a test sets it to prove the exit code). */
    protected bool $stop = false;

    private float $deadline = 0.0;

    public function handle(ImportManager $manager, ImportStateStore $store): int
    {
        $this->trapInterrupt();
        $budget = max(0.0, (float) $this->option('budget'));
        $this->deadline = $budget > 0 ? microtime(true) + $budget : PHP_FLOAT_MAX;

        try {
            if ($this->option('rollback')) {
                return $this->rollback($manager, $store);
            }

            if ($this->option('resume')) {
                return $this->resume($manager, $store);
            }

            return $this->import($manager, $store);
        } catch (MigrationException $e) {
            $this->reportError($e);

            return $this->finish($this->exitFor($e), ['error' => $e->errorCode->value]);
        } catch (PassphraseSourceException $e) {
            return $this->finish($this->reportUsage($e->getMessage()));
        }
    }

    // ---- a new import ------------------------------------------------------------------------------------------

    private function import(ImportManager $manager, ImportStateStore $store): int
    {
        $missing = $this->missingOptions();

        if ($missing !== []) {
            return $this->finish($this->reportUsage('Cannot run without asking: '.implode('; ', $missing).'.'));
        }

        $source = $this->source();
        $passphrase = $this->readPassphrase();
        $dry = (bool) $this->option('dry-run');

        $started = $manager->start(
            ['path' => $source],
            $passphrase,
            ['via' => 'command line'],
            $dry ? 'dry_run' : 'import',
            'cli',
            $this->runOptions(),
        );
        $id = $started['state']->id;
        $this->event('run', ['run_id' => $id]);

        $progress = $started['progress'];

        if (isset($progress['input_error'])) {
            $manager->discard($id);
            $code = MigrationErrorCode::from((string) $progress['input_error']);

            throw new MigrationException($code, (array) ($progress['input_params'] ?? []));
        }

        $progress = $this->drive($manager, $id, $passphrase, fn (array $p): bool => $p['status'] === 'awaiting_confirmation');

        if (($progress['terminal'] ?? false) || $this->stop || microtime(true) >= $this->deadline) {
            return $this->conclude($manager, $store, $id, $progress);
        }

        $state = $store->find($id);

        if ($state === null) {
            return $this->finish(ExitCode::Failed);
        }

        return $this->afterChecks($manager, $store, $state, $passphrase);
    }

    /** The checks are done: show them, then either stop (preflight only, blocked, not confirmed) or go on. */
    private function afterChecks(ImportManager $manager, ImportStateStore $store, ImportState $state, ?string $passphrase): int
    {
        $preflight = (array) ($state->package['preflight'] ?? []);
        $this->showChecks($preflight);
        $warnings = $this->warningLines($preflight);

        if (($preflight['can_proceed'] ?? false) !== true) {
            $manager->discard($state->id);
            $this->humanWarn('Something blocks this import. Nothing was changed.');

            return $this->finish(ExitCode::PreflightBlocked, ['run_id' => $state->id, 'preflight' => $this->plain($preflight)], $warnings);
        }

        if ($this->option('preflight-only')) {
            $manager->discard($state->id);
            $this->human('The checks passed. Nothing was changed.');

            return $this->finish($this->option('strict') && $warnings !== [] ? ExitCode::FinishedWithWarnings : ExitCode::Ok, ['run_id' => $state->id, 'preflight' => $this->plain($preflight)], $warnings);
        }

        try {
            $choice = (new ImportGroupChoice)->resolve((array) ($state->package['groups'] ?? []), $this->option('preset') ?: null, $this->option('groups') ?: null, $this->option('without') ?: null);
        } catch (\InvalidArgumentException $e) {
            $manager->discard($state->id);

            return $this->finish($this->reportUsage($e->getMessage()));
        }

        foreach ($choice['added'] as $added) {
            $this->humanWarn("Added the group {$added}: what you chose cannot work without it.");
        }

        $populated = $this->targetClass($preflight) === TargetClass::Populated;
        $confirm = (string) $this->option('confirm');

        if ($confirm !== 'REPLACE' || ($populated && ! $this->option('replace-populated'))) {
            $manager->discard($state->id);
            $this->humanWarn('This site would be replaced: everything on it. Nothing was changed.');
            $this->human('To go ahead, add --confirm=REPLACE'.($populated ? ' --replace-populated (this site holds orders, customers or payments)' : '').'.');

            return $this->finish(ExitCode::NotConfirmed, ['run_id' => $state->id], $warnings);
        }

        $needed = array_values(array_map('strval', (array) ($preflight['required_acks'] ?? [])));

        if ($needed !== [] && ! $this->option('accept-warnings')) {
            $manager->discard($state->id);
            $this->humanWarn('These warnings need your acknowledgement: '.implode(', ', $needed).'. Nothing was changed.');
            $this->human('Read them above, then add --accept-warnings.');

            return $this->finish(ExitCode::NotConfirmed, ['run_id' => $state->id, 'needs' => $needed], $warnings);
        }

        $manager->confirm($state->id, $needed, ['door' => 'cli', 'typed' => $confirm, 'replace_populated' => (bool) $this->option('replace-populated'), 'options' => ['groups' => $choice['groups']]]);
        $this->human('Confirmed. The site is closed while it is replaced.');

        $progress = $this->drive($manager, $state->id, $passphrase, static fn (array $p): bool => false);

        return $this->conclude($manager, $store, $state->id, $progress);
    }

    // ---- resuming and taking back ---------------------------------------------------------------------------------------

    private function resume(ImportManager $manager, ImportStateStore $store): int
    {
        $id = $this->runId($store, requireActive: true);
        $passphrase = null;
        $state = $store->find($id) ?? throw new MigrationException(MigrationErrorCode::NotReadyToConfirm);

        if ($state->flag('needs_passphrase') || ($state->stage === 'locate' && ! $state->flag('locate.done'))) {
            $passphrase = $this->readPassphrase();
        }

        $progress = $this->drive($manager, $id, $passphrase, static fn (array $p): bool => $p['status'] === 'awaiting_confirmation');

        if (! ($progress['terminal'] ?? false) && ! $this->stop && microtime(true) < $this->deadline && $progress['status'] === 'awaiting_confirmation') {
            $latest = $this->reload($store, $id);

            // A run that stopped while it was still checking has not been confirmed yet: the same flags confirm it now.
            if ($latest !== null && ! $latest->flag('confirmed')) {
                return $this->afterChecks($manager, $store, $latest, $passphrase);
            }
        }

        return $this->conclude($manager, $store, $id, $progress);
    }

    private function rollback(ImportManager $manager, ImportStateStore $store): int
    {
        $id = $this->runId($store, requireActive: false);
        $this->human("Taking run {$id} back. The site is closed while it is put back.");

        $progress = $manager->rollbackRun($id, $this->slice(), (bool) $this->option('accept-loss'));
        $progress = $this->drive($manager, $id, null, static fn (array $p): bool => $p['status'] !== 'rolling_back', $progress);

        return $this->conclude($manager, $store, $id, $progress);
    }

    // ---- driving a run ---------------------------------------------------------------------------------------------------

    /**
     * Step the run until it ends, the stop test says so, the person presses Ctrl+C or the budget is spent. Reports every step.
     *
     * @param  callable(array<string, mixed>): bool  $stopWhen
     * @param  array<string, mixed>|null  $first  the answer of a call just made, so it is not repeated
     * @return array<string, mixed>
     */
    private function drive(ImportManager $manager, string $id, ?string $passphrase, callable $stopWhen, ?array $first = null): array
    {
        $progress = $first ?? $manager->status($id);

        while (true) {
            $this->say($progress);

            if (($progress['terminal'] ?? false) || $stopWhen($progress) || $this->stop || microtime(true) >= $this->deadline) {
                return $progress;
            }

            if (($progress['needs_passphrase'] ?? false) || isset($progress['input_error'])) {
                if ($passphrase === null) {
                    $passphrase = $this->readPassphrase();
                }

                $progress = $manager->provideSecret($id, $passphrase);

                continue;
            }

            if (($progress['busy'] ?? false) === true) {
                usleep(500_000); // another process is stepping this very run: let it

                $progress = $manager->status($id) + ['busy' => true];

                if (! ($progress['stalled'] ?? false) && microtime(true) < $this->deadline) {
                    $progress = $manager->advance($id, $this->slice());
                }

                continue;
            }

            $progress = $manager->advance($id, $this->slice());
        }
    }

    /** The run as it is on disk NOW (a run changes between two reads: the answer of an earlier read must not be reused). */
    private function reload(ImportStateStore $store, string $id): ?ImportState
    {
        return $store->find($id);
    }

    /** One step of the loop works this long, and never past the budget. */
    private function slice(): float
    {
        return max(1.0, min(10.0, $this->deadline - microtime(true)));
    }

    /** What happened in the end, as text, as the last JSON line and as an exit code. @param  array<string, mixed>  $progress */
    private function conclude(ImportManager $manager, ImportStateStore $store, string $id, array $progress): int
    {
        $state = $store->find($id);
        $status = (string) ($progress['status'] ?? '');

        if ($this->stop) {
            $this->humanWarn("Stopped. Carry on with: php artisan oeparts:migrate:import --resume --run={$id}");

            return $this->finish(ExitCode::Cancelled, ['run_id' => $id]);
        }

        if ($status === 'complete' && $state !== null) {
            return $this->completed($state);
        }

        if ($status === 'rolled_back') {
            $this->human('The site was put back exactly as it was.');

            return $this->finish($this->rolledBackAfterFailure($state), ['run_id' => $id, 'status' => $status]);
        }

        if (in_array($status, ['failed', 'rolling_back', 'rollback_failed'], true)) {
            return $this->failed($state, $progress);
        }

        if ($status === 'awaiting_confirmation' && $state !== null && ! $state->flag('confirmed')) {
            $this->showChecks((array) ($state->package['preflight'] ?? []));
            $this->humanWarn('This run is only waiting for your confirmation. Start it again with --confirm=REPLACE (and the other options); nothing was changed.');

            return $this->finish(ExitCode::NotConfirmed, ['run_id' => $id, 'status' => $status]);
        }

        $this->humanWarn("Not finished: the time budget ran out. Carry on with: php artisan oeparts:migrate:import --resume --run={$id}");

        return $this->finish(ExitCode::NotFinished, ['run_id' => $id, 'status' => $status]);
    }

    private function completed(ImportState $state): int
    {
        $report = app(ImportReportBuilder::class)->build($state);
        $warnings = (array) $report['warnings'];

        $this->human(($state->kind === 'dry_run' ? 'The rehearsal is done. Nothing was changed.' : 'The import is complete.').' '.$report['database']['rows_loaded'].' row(s) in '.$report['database']['tables_loaded'].' table(s).');

        foreach ($warnings as $warning) {
            $this->humanWarn('  - '.$warning);
        }

        if ($state->kind === 'import') {
            $this->human('Sign in with the administrators of the OLD site. The site is in "'.$this->mode().'" mode; check it with: php artisan oeparts:instance:readiness');
        }

        return $this->finish($this->option('strict') && $warnings !== [] ? ExitCode::FinishedWithWarnings : ExitCode::Ok, ['run_id' => $state->id, 'report' => $report], $warnings);
    }

    /** @param  array<string, mixed>  $progress */
    private function failed(?ImportState $state, array $progress): int
    {
        $id = (string) ($state->id ?? '');
        $code = MigrationErrorCode::tryFrom((string) ($progress['error']['code'] ?? '')) ?? MigrationErrorCode::ImportFailedStep;
        $e = new MigrationException($code, (array) ($progress['error']['params'] ?? []));
        $this->reportError($e);

        if (($progress['status'] ?? '') === 'rollback_failed') {
            $this->humanWarn('The way back did not finish; the site stays closed so nothing writes to a half restored database.');
            $this->human(RecoveryNote::text($id));
        } elseif (($progress['destructive'] ?? false) === true) {
            $this->human('The site is being put back. Carry on with: php artisan oeparts:migrate:import --resume --run='.$id);
        } else {
            $this->human('Nothing was changed.');
        }

        return $this->finish($this->exitFor($e), ['run_id' => $id, 'status' => $progress['status'] ?? null, 'error' => $code->value]);
    }

    // ---- small helpers ---------------------------------------------------------------------------------------------------------

    /** @return array<int, string> what a command that cannot ask has not been told */
    private function missingOptions(): array
    {
        if ($this->input->isInteractive()) {
            return [];
        }

        $missing = [];

        if ($this->argument('source') === null) {
            $missing[] = 'the package (a path, or a name in storage/app/import)';
        }

        if (! $this->option('dry-run') && ! $this->option('preflight-only') && $this->option('launch') === null) {
            $missing[] = '--launch=live or --launch=staging';
        }

        if (! $this->option('dry-run') && ! $this->option('preflight-only') && ! $this->option('preset') && ! $this->option('groups')) {
            $missing[] = '--preset=... or --groups=...';
        }

        return $missing;
    }

    private function source(): string
    {
        $given = $this->argument('source');

        if ($given === null && $this->input->isInteractive()) {
            $packages = app(ImportInbox::class)->list();

            if ($packages === []) {
                throw new MigrationException(MigrationErrorCode::PackageNotFound);
            }

            $given = select('Which package?', array_column($packages, 'name'));
        }

        $given = (string) $given;

        if (is_file($given) && is_readable($given)) {
            return (string) realpath($given);
        }

        return app(ImportInbox::class)->resolve($given); // a name in the inbox, or package_not_found
    }

    /** @return array<string, mixed> */
    private function runOptions(): array
    {
        $launch = $this->option('launch');

        if ($launch === null && $this->input->isInteractive() && ! $this->option('dry-run') && ! $this->option('preflight-only')) {
            $launch = select('Is this your real, live site?', ['live' => 'Yes, this will be my live site', 'staging' => 'No, this is a test copy']);
        }

        $options = ['door' => 'cli', 'launch' => (string) ($launch ?? 'live'), 'include_secrets' => (bool) $this->option('include-secrets')];

        foreach (['site-url' => 'url', 'instance-id' => 'instance_id', 'mail-allowlist' => 'mail_allowlist'] as $flag => $key) {
            if ($this->option($flag) !== null && $this->option($flag) !== '') {
                $options[$key] = (string) $this->option($flag);
            }
        }

        $also = array_values(array_filter((array) $this->option('also-from')));

        if ($also !== []) {
            $options['also_from'] = $also;
        }

        if ($this->option('extra-admin-email')) {
            $options['extra_admin'] = ['email' => (string) $this->option('extra-admin-email'), 'name' => (string) $this->option('extra-admin-name'), 'password_hash' => $this->extraAdminHash()];
        }

        return $options;
    }

    private function extraAdminHash(): string
    {
        $password = getenv('OEPARTS_EXTRA_ADMIN_PASSWORD');

        if (! is_string($password) || $password === '') {
            if (! $this->input->isInteractive()) {
                throw new PassphraseSourceException('The extra administrator needs a password: set OEPARTS_EXTRA_ADMIN_PASSWORD, or run the command interactively.');
            }

            $password = \Laravel\Prompts\password(label: 'Password for the extra administrator', required: true);
        }

        return password_hash($password, PASSWORD_BCRYPT);
    }

    private function runId(ImportStateStore $store, bool $requireActive): string
    {
        $given = (string) $this->option('run');

        if ($given !== '') {
            if (! ImportStateStore::validId($given) || $store->find($given) === null) {
                throw new MigrationException(MigrationErrorCode::NotReadyToConfirm, ['what' => 'no_such_run']);
            }

            return $given;
        }

        foreach ($store->all() as $state) {
            $usable = $requireActive ? ! $state->status->isTerminal() : ($state->status->mayHoldTheSite() || $state->status->value === 'complete');

            if ($usable && $state->kind === 'import') {
                return $state->id;
            }
        }

        throw new MigrationException(MigrationErrorCode::NotReadyToConfirm, ['what' => 'no_run_to_continue']);
    }

    /** @param  array<string, mixed>  $preflight */
    private function showChecks(array $preflight): void
    {
        foreach ((array) ($preflight['checks'] ?? []) as $check) {
            $status = (string) ($check['status'] ?? '');
            $mark = match ($status) {
                'pass' => 'PASS', 'warn' => 'WARN', 'fail' => 'FAIL', default => $status,
            };
            $this->human(sprintf('  %-4s %s%s', $mark, $check['label'] ?? $check['key'] ?? '', ($check['message'] ?? '') !== '' ? ' - '.$check['message'] : ''));
        }
    }

    /**
     * @param  array<string, mixed>  $preflight
     * @return array<int, string>
     */
    private function warningLines(array $preflight): array
    {
        $lines = [];

        foreach ((array) ($preflight['checks'] ?? []) as $check) {
            if (($check['status'] ?? '') === 'warn') {
                $lines[] = ($check['label'] ?? $check['key']).': '.($check['message'] ?? '');
            }
        }

        return $lines;
    }

    /** @param  array<string, mixed>  $preflight @return array<string, mixed> */
    private function plain(array $preflight): array
    {
        return ['can_proceed' => $preflight['can_proceed'] ?? false, 'required_acks' => $preflight['required_acks'] ?? [], 'checks' => array_map(static fn (array $c): array => ['key' => $c['key'] ?? '', 'status' => $c['status'] ?? '', 'message' => $c['message'] ?? ''], (array) ($preflight['checks'] ?? []))];
    }

    /** @param  array<string, mixed>  $preflight */
    private function targetClass(array $preflight): ?TargetClass
    {
        foreach ((array) ($preflight['checks'] ?? []) as $check) {
            if (($check['key'] ?? '') === 'target_state') {
                return TargetClass::tryFrom((string) ($check['meta']['class'] ?? ''));
            }
        }

        return null;
    }

    private function rolledBackAfterFailure(?ImportState $state): ExitCode
    {
        // A rollback that the person asked for succeeded; one that ended an import that had failed is a failed import.
        return $state === null || $state->error === null ? ExitCode::Ok : ExitCode::Failed;
    }

    /** @param  array<string, mixed>  $progress */
    private function say(array $progress): void
    {
        $status = (string) ($progress['status'] ?? '');
        $percent = (float) ($progress['percent'] ?? 0);
        $line = sprintf('%5.1f%%  %s%s', $percent, $progress['stage'] ?? '', ($progress['detail'] ?? '') !== '' ? ': '.$progress['detail'] : '');

        $this->event('progress', ['status' => $status, 'stage' => $progress['stage'] ?? null, 'percent' => $percent, 'detail' => $progress['detail'] ?? '']);

        if (! $this->jsonOutput() && $status !== '') {
            $this->output->writeln($line, OutputInterface::VERBOSITY_VERBOSE);
        }
    }

    private function mode(): string
    {
        return app(SafeMode::class)->mode()->value;
    }

    private function exitFor(MigrationException $e): ExitCode
    {
        return match ($e->errorCode) {
            MigrationErrorCode::PackageNotFound, MigrationErrorCode::PackageNotOeparts, MigrationErrorCode::PackageIncomplete, MigrationErrorCode::PackageCorrupt,
            MigrationErrorCode::PackageTextMode, MigrationErrorCode::PackageHashMismatch, MigrationErrorCode::PackageChanged, MigrationErrorCode::VersionTooNew => ExitCode::PackageUnreadable,
            MigrationErrorCode::PassphraseEmpty, MigrationErrorCode::PassphraseWrong, MigrationErrorCode::PassphraseLocked => ExitCode::WrongPassphrase,
            MigrationErrorCode::LockBusy, MigrationErrorCode::LockLost, MigrationErrorCode::FreezeActive => ExitCode::SystemBusy,
            MigrationErrorCode::ReplaceNotConfirmed, MigrationErrorCode::AcknowledgementMissing => ExitCode::NotConfirmed,
            MigrationErrorCode::PreflightFailed, MigrationErrorCode::TargetNotFresh, MigrationErrorCode::RecoveryPointTooLarge, MigrationErrorCode::MigrationSetDiffers => ExitCode::PreflightBlocked,
            default => ExitCode::Failed,
        };
    }

    /** Ctrl+C stops the loop after the step in progress: the run is saved at every step, so nothing is lost. */
    private function trapInterrupt(): void
    {
        if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal') && defined('SIGINT')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, function (): void {
                $this->stop = true;
            });
        }
    }
}
