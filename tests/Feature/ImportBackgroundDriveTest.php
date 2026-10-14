<?php

namespace Tests\Feature;

use App\Security\PackageAudit;
use App\Services\Backup\BackupLock;
use App\Services\Migration\Import\Contracts\ImportStage;
use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportManager;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportReportBuilder;
use App\Services\Migration\Import\ImportSelfDrive;
use App\Services\Migration\Import\ImportStageRegistry;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStatus;
use App\Services\Migration\Import\ImportStepResult;
use App\Services\Migration\Import\ImportWatchdog;
use App\Services\Migration\MigrationLedger;
use App\Services\Migration\MigrationPaths;
use App\Support\AtomicJsonFile;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsImportPackages;
use Tests\TestCase;

/**
 * Phase 6.8: the report of a run, the ledger that reads it, and the machines that carry a run nobody is polling: the
 * watchdog (needs cron) and the bounded self-drive of the 503 gate (no cron at all).
 */
class ImportBackgroundDriveTest extends TestCase
{
    use BuildsImportPackages;
    use RefreshDatabase;

    private string $watchdogFile = '';

    private string $watchdogBackup = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareImportTest();
        (new ImportMarker)->remove();
        app(BackupLock::class)->release();

        $this->watchdogFile = MigrationPaths::root().DIRECTORY_SEPARATOR.'watchdog.json';
        $this->watchdogBackup = is_file($this->watchdogFile) ? (string) file_get_contents($this->watchdogFile) : '';
        $this->clearStamp();

        config(['migration.import.self_drive.max_failures' => 5, 'migration.import.self_drive.backoff_base_seconds' => 30, 'migration.import.self_drive.budget_seconds' => 0]);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        (new ImportMarker)->remove();
        app(BackupLock::class)->release();
        $this->clearStamp();

        if ($this->watchdogBackup !== '') {
            file_put_contents($this->watchdogFile, $this->watchdogBackup);
        }

        $this->cleanUpImportTest();

        parent::tearDown();
    }

    /** The stamp and the copies the atomic file keeps beside it (a read falls back to the previous copy). */
    private function clearStamp(): void
    {
        foreach ([$this->watchdogFile, $this->watchdogFile.'.prev', $this->watchdogFile.'.lock'] as $file) {
            @unlink($file);
        }
    }

    // ---- fixtures ---------------------------------------------------------------------------------------------

    /** A stage that does what it is told and counts how often it ran. */
    private function stage(string $key, \Closure $step): ImportStage
    {
        return new class($key, $step) implements ImportStage
        {
            public int $runs = 0;

            public function __construct(private readonly string $key, private readonly \Closure $step) {}

            public function key(): string
            {
                return $this->key;
            }

            public function destructive(): bool
            {
                return false;
            }

            public function step(ImportState $state, ImportContext $context): ImportStepResult
            {
                $this->runs++;

                return ($this->step)($state, $this->runs);
            }
        };
    }

    /** Register these stages as the application's, so the manager, the watchdog and the gate all drive them. */
    private function useStages(ImportStage ...$stages): void
    {
        $this->app->instance(ImportStageRegistry::class, new ImportStageRegistry($stages));
        $this->app->forgetInstance(ImportManager::class);
        $this->app->forgetInstance(ImportSelfDrive::class);
        $this->app->forgetInstance(ImportWatchdog::class);
    }

    private function confirmedRun(string $stage, int $heartbeatAgeSeconds = 300): ImportState
    {
        $state = $this->store()->create('import', ['stage' => $stage]);
        $this->store()->update($state->id, function (ImportState $s) use ($stage, $heartbeatAgeSeconds): void {
            $s->stage = $stage;
            $s->setFlag('confirmed');
            $s->stageState = ['_fraction' => 0.0, '_message' => 'waiting']; // what a stage that has run once has left there
            $s->heartbeatAt = now()->subSeconds($heartbeatAgeSeconds)->toIso8601String();
        });

        return $this->store()->find($state->id);
    }

    /** A stage that never gets anywhere: no error, no progress, the same answer every time. */
    private function stuck(): ImportStage
    {
        return $this->stage('stuck', fn (ImportState $s) => ImportStepResult::progress(0.0, 'waiting'));
    }

    // ---- the report ---------------------------------------------------------------------------------------------

    private function finishedState(): ImportState
    {
        $state = new ImportState('rep'.bin2hex(random_bytes(6)));
        $state->status = ImportStatus::Complete;
        $state->finishedAt = now()->addSeconds(90)->toIso8601String();
        $state->options = ['door' => 'cli', 'launch' => 'live', 'acks' => ['target_populated'], 'passphrase' => 'TOPSECRET-PASSPHRASE'];
        $state->initiator = ['email' => 'admin@site.test', 'ip' => '203.0.113.9'];
        $state->tokenHash = 'deadbeefdeadbeefdeadbeefdeadbeef';
        $state->package = [
            'path' => '/var/inbox/site-2026.oeparts', 'fingerprint' => 'ABCD-1234', 'groups' => ['core', 'catalog'],
            'loaded' => ['products' => 1200, 'settings' => 80],
            'audit' => ['orphans' => ['products.manufacturer_id->manufacturers.id' => 3]],
            'seeded' => ['content' => ['sections' => 14]],
            'files' => ['written' => 10, 'replaced' => 1, 'unchanged' => 2, 'bytes' => 5000, 'rejected' => ['looks_like_code' => 2], 'rejected_sample' => [['path' => 'media/x.jpg', 'reason' => 'looks_like_code']], 'leftovers' => 4],
            'settings_snapshot' => ['mail.password' => 'S3CR3T-MAIL-PASSWORD'],
            'preflight' => ['checks' => [
                ['key' => 'db_engine', 'label' => 'Database server', 'status' => 'warn', 'message' => 'SQLite is only used for tests.', 'meta' => ['secret' => 'META-SECRET']],
                ['key' => 'integrity', 'label' => 'Package integrity', 'status' => 'pass', 'message' => ''],
            ]],
        ];
        $state->flags = ['locate.done' => true, 'db.done' => true, 'confirmed' => true];

        return $state;
    }

    #[Test]
    public function the_report_tells_what_happened_in_counts_and_names_and_never_carries_a_value(): void
    {
        $report = app(ImportReportBuilder::class)->build($this->finishedState());

        $this->assertSame(1, $report['report']);
        $this->assertFalse($report['values_included']);
        $this->assertSame('site-2026.oeparts', $report['package']['file'], 'the file name, never the folder it was in');
        $this->assertSame(2, $report['database']['tables_loaded']);
        $this->assertSame(1280, $report['database']['rows_loaded']);
        $this->assertSame(['products.manufacturer_id->manufacturers.id' => 3], $report['database']['orphans']);
        $this->assertSame(['sections' => 14], $report['database']['seeded_defaults']['content']);
        $this->assertSame(['looks_like_code' => 2], $report['files']['rejected']);
        $this->assertSame(90, $report['run']['seconds']);
        $this->assertSame(['target_populated'], $report['acknowledged']);
        $this->assertContains('locate.done', $report['stages_finished']);
        $this->assertNotContains('confirmed', $report['stages_finished']);
        $this->assertCount(4, $report['warnings'], 'the warned check, the orphans, the refused files and the leftovers');
    }

    #[Test]
    public function nothing_secret_can_reach_the_report_whatever_the_state_holds(): void
    {
        $json = json_encode(app(ImportReportBuilder::class)->build($this->finishedState()));

        foreach (['TOPSECRET-PASSPHRASE', 'S3CR3T-MAIL-PASSWORD', 'META-SECRET', 'deadbeefdeadbeef', '203.0.113.9', '/var/inbox'] as $leak) {
            $this->assertStringNotContainsString($leak, $json, "{$leak} must not be in the report");
        }
    }

    #[Test]
    public function the_report_is_written_beside_the_run_and_the_ledger_only_reads_it(): void
    {
        $state = $this->finishedState();
        $this->store()->save($state);
        $path = app(ImportReportBuilder::class)->write($state);

        $this->assertFileExists($path);
        $ledger = app(MigrationLedger::class);
        $this->assertSame(1280, $ledger->report($state->id)['database']['rows_loaded']);
        $this->assertNull($ledger->report('0123456789abcdef'));
        $this->assertNull($ledger->report('../../etc'), 'an id is validated before it becomes a path');

        $mine = array_values(array_filter($ledger->runs(), fn (array $r): bool => $r['id'] === $state->id));
        $this->assertCount(1, $mine);
        $this->assertTrue($mine[0]['has_report']);

        app(PackageAudit::class)->record('import.completed', $state->id, ['rows' => 1280]);
        $this->assertSame('import.completed', $ledger->history($state->id)[0]['event']);
    }

    #[Test]
    public function a_run_that_ends_gets_its_report_written_once_by_the_manager_itself(): void
    {
        $path = $this->buildImportPackage('gone.oeparts');
        $start = $this->manager()->start(['path' => $path], $this->passphrase, [], 'import', 'cli', ['door' => 'cli']);
        $id = $start['state']->id;
        unlink($path);

        $progress = $this->manager()->advance($id, 30.0);

        $this->assertSame('failed', $progress['status']);
        $report = app(MigrationLedger::class)->report($id);
        $this->assertNotNull($report, 'the failed run explains itself');
        $this->assertSame('failed', $report['outcome']['status']);
        $this->assertFalse($report['run']['changed_the_site']);
        $this->assertNotNull($report['outcome']['error_code']);
    }

    #[Test]
    public function the_report_command_lists_runs_and_shows_one(): void
    {
        $state = $this->finishedState();
        $this->store()->save($state);

        $this->assertSame(0, Artisan::call('oeparts:migrate:report'));
        $this->assertStringContainsString($state->id, Artisan::output());

        $this->assertSame(0, Artisan::call('oeparts:migrate:report', ['run' => $state->id, '--write' => true]));
        $this->assertSame(9, Artisan::call('oeparts:migrate:report', ['run' => $state->id, '--strict' => true]), 'there are warnings to look at');
        $this->assertFileExists(app(ImportReportBuilder::class)->path($state->id));
        Artisan::call('oeparts:migrate:report', ['run' => $state->id]);
        $this->assertStringContainsString('1280 row(s) loaded', Artisan::output());

        $this->assertSame(2, Artisan::call('oeparts:migrate:report', ['run' => 'not-a-run']));
    }

    // ---- accounting of the machines' attempts ---------------------------------------------------------------------

    #[Test]
    public function a_step_that_moves_nothing_lengthens_the_wait_and_the_stage_is_not_run_again_inside_it(): void
    {
        $stuck = $this->stuck();
        $this->useStages($stuck);
        $run = $this->confirmedRun('stuck');
        $manager = app(ImportManager::class);

        $manager->advanceInBackground($run->id, 0.0);
        $state = $this->store()->find($run->id);
        $this->assertSame(1, $state->consecutiveFailures);
        $this->assertNotNull($state->nextSelfDriveAt);

        $again = $manager->advanceInBackground($run->id, 0.0);
        $this->assertTrue($again['backoff']);
        $this->assertSame(1, $stuck->runs, 'inside the wait the stage is left alone');

        $this->travel(31)->seconds();
        $manager->advanceInBackground($run->id, 0.0);
        $this->assertSame(2, $stuck->runs);
        $second = $this->store()->find($run->id);
        $this->assertSame(2, $second->consecutiveFailures);
        $this->assertGreaterThanOrEqual(59, now()->diffInSeconds(Carbon::parse($second->nextSelfDriveAt), false), 'the second wait is twice the first');
    }

    #[Test]
    public function after_enough_attempts_that_moved_nothing_the_run_is_stalled_and_no_machine_touches_it_again(): void
    {
        $stuck = $this->stuck();
        $this->useStages($stuck);
        $run = $this->confirmedRun('stuck');
        $manager = app(ImportManager::class);

        for ($i = 0; $i < 5; $i++) {
            $manager->advanceInBackground($run->id, 0.0);
            $this->travel(20)->minutes();
        }

        $this->assertSame(5, $stuck->runs);
        $this->assertTrue($manager->isStalled($run->id));

        $result = $manager->advanceInBackground($run->id, 0.0);

        $this->assertTrue($result['stalled']);
        $this->assertSame(5, $stuck->runs, 'a stalled run is not driven by machines');
        $this->assertTrue($manager->status($run->id)['stalled']);
    }

    #[Test]
    public function a_person_looking_at_the_run_clears_the_machines_record_and_the_stalled_flag(): void
    {
        $stuck = $this->stuck();
        $this->useStages($stuck);
        $run = $this->confirmedRun('stuck');
        $manager = app(ImportManager::class);

        for ($i = 0; $i < 5; $i++) {
            $manager->advanceInBackground($run->id, 0.0);
            $this->travel(20)->minutes();
        }
        $this->assertTrue($manager->isStalled($run->id));

        $manager->advance($run->id, 0.0, null, 'cli');

        $this->assertFalse($manager->isStalled($run->id));
        $state = $this->store()->find($run->id);
        $this->assertSame(0, $state->consecutiveFailures);
        $this->assertNull($state->nextSelfDriveAt);
    }

    #[Test]
    public function a_step_that_made_progress_keeps_the_record_clean(): void
    {
        $moving = $this->stage('moving', function (ImportState $s, int $n) {
            $s->stageState['n'] = $n;

            return ImportStepResult::progress(min(0.9, $n / 10), 'working');
        });
        $this->useStages($moving);
        $run = $this->confirmedRun('moving');
        $manager = app(ImportManager::class);

        for ($i = 0; $i < 6; $i++) {
            $manager->advanceInBackground($run->id, 0.0);
        }

        $this->assertSame(6, $moving->runs);
        $state = $this->store()->find($run->id);
        $this->assertSame(0, $state->consecutiveFailures);
        $this->assertNull($state->nextSelfDriveAt);
    }

    #[Test]
    public function a_run_waiting_for_the_person_is_never_driven_by_a_machine(): void
    {
        $stuck = $this->stuck();
        $this->useStages($stuck);
        $unconfirmed = $this->store()->create('import', ['stage' => 'stuck']);
        $this->store()->update($unconfirmed->id, function (ImportState $s): void {
            $s->status = ImportStatus::AwaitingConfirmation;
            $s->heartbeatAt = now()->subHour()->toIso8601String();
        });

        app(ImportManager::class)->advanceInBackground($unconfirmed->id, 0.0);
        app(ImportWatchdog::class)->run(microtime(true) + 5);

        $this->assertSame(0, $stuck->runs);
    }

    // ---- the self-drive of the 503 gate ---------------------------------------------------------------------------

    #[Test]
    public function fifty_visitors_during_the_503_run_a_failing_stage_once_per_wait_and_the_run_ends_up_stalled(): void
    {
        $stuck = $this->stuck();
        $this->useStages($stuck);
        $run = $this->confirmedRun('stuck');
        (new ImportMarker)->write($run->id);

        for ($i = 0; $i < 50; $i++) {
            $response = $this->get('/en/');
            $response->assertStatus(503);
        }

        $this->assertSame(1, $stuck->runs, 'fifty requests, one attempt: the persisted wait holds the rest back');

        // The waits pass, one after the other: the stage is tried at most once per wait, and then never again.
        for ($window = 0; $window < 8; $window++) {
            $this->travel(20)->minutes();

            for ($i = 0; $i < 10; $i++) {
                $this->get('/en/');
            }
        }

        $this->assertSame(5, $stuck->runs, 'five attempts and then it stops');
        $this->assertTrue(app(ImportManager::class)->isStalled($run->id));
        $this->get('/en/')->assertStatus(503)->assertSee('attention', false);
        $this->assertSame(5, $stuck->runs, 'the stalled notice is shown without driving');
    }

    #[Test]
    public function the_self_drive_stands_back_when_cron_works_or_somebody_is_already_driving_the_run(): void
    {
        $stuck = $this->stuck();
        $this->useStages($stuck);
        $fresh = $this->confirmedRun('stuck', 5);
        (new ImportMarker)->write($fresh->id);

        app(ImportSelfDrive::class)->maybeDrive();
        $this->assertSame(0, $stuck->runs, 'the run was advanced five seconds ago: someone is on it');

        $this->store()->update($fresh->id, fn (ImportState $s) => $s->heartbeatAt = now()->subMinutes(10)->toIso8601String());
        (new AtomicJsonFile($this->watchdogFile))->write(['at' => now()->subMinutes(2)->toIso8601String()]);
        app(ImportSelfDrive::class)->maybeDrive();
        $this->assertSame(0, $stuck->runs, 'the scheduler ran two minutes ago: cron drives, the self-drive does not');

        $this->clearStamp();
        $lock = fopen($this->store()->directory($fresh->id).DIRECTORY_SEPARATOR.'selfdrive.lock', 'c');
        flock($lock, LOCK_EX | LOCK_NB);
        app(ImportSelfDrive::class)->maybeDrive();
        $this->assertSame(0, $stuck->runs, 'another visitor holds the lock');
        fclose($lock);

        app(ImportSelfDrive::class)->maybeDrive();
        $this->assertSame(1, $stuck->runs, 'with all of that out of the way it does one step');
    }

    #[Test]
    public function the_self_drive_gets_a_stalling_run_through_to_the_end_when_the_stage_does_move(): void
    {
        $steps = $this->stage('work', function (ImportState $s, int $n) {
            return $n >= 3 ? ImportStepResult::done('finished') : ImportStepResult::progress($n / 3, 'working');
        });
        $this->useStages($steps);
        $run = $this->confirmedRun('work');
        (new ImportMarker)->write($run->id);

        for ($i = 0; $i < 3; $i++) {
            $this->store()->update($run->id, fn (ImportState $s) => $s->heartbeatAt = now()->subMinutes(10)->toIso8601String());
            $this->get('/en/');
        }

        $this->assertSame(3, $steps->runs);
        $this->assertSame('awaiting_confirmation', $this->store()->find($run->id)->status->value, 'the last stage finished and the run waits where a run waits at the end of the list');
    }

    // ---- the watchdog ---------------------------------------------------------------------------------------------

    #[Test]
    public function the_watchdog_steps_an_idle_run_again_and_again_for_its_budget(): void
    {
        $steps = $this->stage('work', function (ImportState $s, int $n) {
            $s->stageState['n'] = $n;

            return $n >= 4 ? ImportStepResult::done('finished') : ImportStepResult::progress($n / 4, 'working');
        });
        $this->useStages($steps);
        $run = $this->confirmedRun('work');

        $result = app(ImportWatchdog::class)->run(microtime(true) + 30);

        $this->assertSame(1, $result['nudged']);
        $this->assertSame(4, $steps->runs, 'one visit carries the run to the end of the list');
        $this->assertSame('awaiting_confirmation', $this->store()->find($run->id)->status->value);
    }

    #[Test]
    public function the_watchdog_leaves_a_run_alone_that_somebody_advanced_a_moment_ago(): void
    {
        $stuck = $this->stuck();
        $this->useStages($stuck);
        $this->confirmedRun('stuck', 10);

        $result = app(ImportWatchdog::class)->run(microtime(true) + 5);

        $this->assertSame(0, $result['nudged']);
        $this->assertSame(0, $stuck->runs);
    }

    #[Test]
    public function the_watchdog_marks_a_run_stalled_that_has_been_silent_for_fifteen_minutes_but_not_one_in_a_long_statement(): void
    {
        $stuck = $this->stuck();
        $this->useStages($stuck);
        $silent = $this->confirmedRun('stuck', 20 * 60);
        $building = $this->confirmedRun('stuck', 20 * 60);
        $this->store()->update($building->id, fn (ImportState $s) => $s->expectSilenceFor(3600));

        $result = app(ImportWatchdog::class)->run(microtime(true) + 5);

        $this->assertSame(1, $result['stalled']);
        $this->assertTrue(app(ImportManager::class)->isStalled($silent->id));
        $this->assertFalse(app(ImportManager::class)->isStalled($building->id), 'an announced long statement is not a stall');
    }

    #[Test]
    public function the_watchdog_command_runs_the_import_half_and_stamps_the_scheduler_heartbeat(): void
    {
        $steps = $this->stage('work', fn (ImportState $s, int $n) => ImportStepResult::done('finished'));
        $this->useStages($steps);
        $this->confirmedRun('work');

        $this->assertSame(0, Artisan::call('oeparts:migrate:watchdog'));

        $this->assertSame(1, $steps->runs);
        $stamp = (new AtomicJsonFile($this->watchdogFile))->read();
        $this->assertSame(1, $stamp['imports_nudged']);
        $this->assertStringContainsString('1 import run(s) nudged', Artisan::output());
    }
}
