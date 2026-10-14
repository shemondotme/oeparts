<?php

namespace Tests\Feature;

use App\Services\Backup\BackupJanitor;
use App\Services\Backup\BackupLock;
use App\Services\Cleanup\SystemBusyGuard;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportRunMutex;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\ImportStatus;
use App\Services\Migration\Import\PackageIdentity;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Updates\UpdateWatchdog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Unit\Migration\Package\Concerns\BuildsPackages;

/**
 * Phase 6.1: what an import remembers, the marker that closes the site, the mutex that keeps two processes off one
 * run, the identity of the package it was started on, and the rule that neither janitor may reclaim the lock of an
 * import that is still moving.
 */
class ImportStateAndMarkerTest extends TestCase
{
    use BuildsPackages;
    use RefreshDatabase;

    private string $base = '';

    private ImportStateStore $store;

    private ImportMarker $marker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeWorkDir();
        $this->base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-import-state-'.getmypid().'-'.uniqid();
        mkdir($this->base.'/runs', 0775, true);
        $this->store = new ImportStateStore($this->base.'/runs');
        $this->marker = new ImportMarker($this->base.'/importing.json');
        app(BackupLock::class)->release();
        $this->realMarker()->remove();
        config(['updates.stale_after_seconds' => 7200, 'backup.stale_after_seconds' => 3600, 'migration.import.heartbeat_stale_seconds' => 1800]);
    }

    protected function tearDown(): void
    {
        app(BackupLock::class)->release();
        $this->realMarker()->remove();
        $this->removeRealRuns();
        Carbon::setTestNow();
        exec('rm -rf '.escapeshellarg($this->base));
        $this->removeWorkDir();

        parent::tearDown();
    }

    private function realMarker(): ImportMarker
    {
        return new ImportMarker;
    }

    /** The lock check reads the REAL marker and run directory, so those tests put their data there and clean up. */
    private array $realRuns = [];

    private function removeRealRuns(): void
    {
        foreach ($this->realRuns as $id) {
            exec('rm -rf '.escapeshellarg((new ImportStateStore)->directory($id)));
        }
    }

    // ---- the state file ---------------------------------------------------------------------------------------

    #[Test]
    public function a_run_is_created_found_changed_and_listed(): void
    {
        $state = $this->store->create('import', ['stage' => 'locate', 'initiator' => ['email' => 'a@b.test']]);

        $this->assertTrue(ImportStateStore::validId($state->id));
        $this->assertSame(ImportStatus::Running, $this->store->find($state->id)->status);
        $this->assertSame('a@b.test', $this->store->find($state->id)->initiator['email']);

        $this->store->update($state->id, function (ImportState $s): void {
            $s->status = ImportStatus::AwaitingConfirmation;
            $s->stage = 'preflight';
            $s->setFlag('arm.lock');
            $s->indexesDropped = ['idx_a'];
        });

        $again = $this->store->find($state->id);
        $this->assertSame(ImportStatus::AwaitingConfirmation, $again->status);
        $this->assertSame('preflight', $again->stage);
        $this->assertTrue($again->flag('arm.lock'));
        $this->assertFalse($again->flag('arm.marker'));
        $this->assertSame(['idx_a'], $again->indexesDropped);
        $this->assertCount(1, $this->store->all());
    }

    #[Test]
    public function a_run_id_from_a_request_can_never_reach_outside_the_runs_directory(): void
    {
        foreach (['../../etc', '..', '', 'a/b', 'UPPERCASE1234', 'short', str_repeat('a', 41), "abc\0def12345678"] as $bad) {
            $this->assertFalse(ImportStateStore::validId($bad), $bad);
            $this->assertNull($this->store->find($bad));
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->store->directory('../../evil');
    }

    #[Test]
    public function updating_a_run_that_does_not_exist_is_an_error_not_a_new_run(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->store->update('0123456789abcdef', function (ImportState $s): void {
            $s->stage = 'x';
        });
    }

    #[Test]
    public function a_torn_write_falls_back_to_the_previous_version_and_a_wrecked_file_is_an_error_not_an_empty_run(): void
    {
        $state = $this->store->create();
        $this->store->update($state->id, function (ImportState $s): void {
            $s->stage = 'verify';
        });
        $file = $this->store->file($state->id)->path();

        file_put_contents($file, '{"id": "tor'); // a write that was cut short
        $this->assertSame('locate', $this->store->find($state->id)->stage, 'the version before the last write is used');

        file_put_contents($file.'.prev', 'not json either');
        $this->expectException(\RuntimeException::class);
        $this->store->find($state->id);
    }

    #[Test]
    public function the_active_run_is_the_one_that_is_not_terminal(): void
    {
        $done = $this->store->create();
        $this->store->update($done->id, function (ImportState $s): void {
            $s->status = ImportStatus::Complete;
        });
        $live = $this->store->create();

        $this->assertSame($live->id, $this->store->active()->id);

        $this->store->update($live->id, function (ImportState $s): void {
            $s->status = ImportStatus::RolledBack;
        });
        $this->assertNull($this->store->active());
    }

    #[Test]
    public function an_unreadable_run_does_not_hide_the_others_from_the_list(): void
    {
        $good = $this->store->create();
        $bad = $this->store->create();
        file_put_contents($this->store->file($bad->id)->path(), 'garbage');

        $this->assertSame([$good->id], array_map(fn (ImportState $s): string => $s->id, $this->store->all()));
    }

    #[Test]
    public function the_state_never_holds_a_secret_only_a_hash_of_the_runner_token(): void
    {
        $token = bin2hex(random_bytes(32));
        $state = $this->store->create('import', ['token_hash' => hash('sha256', $token)]);

        $raw = (string) file_get_contents($this->store->file($state->id)->path());

        $this->assertStringContainsString(hash('sha256', $token), $raw);
        $this->assertStringNotContainsString($token, $raw);
    }

    // ---- heartbeat and long statements ---------------------------------------------------------------------------

    #[Test]
    public function a_run_is_alive_by_its_heartbeat_and_a_silent_one_is_not(): void
    {
        $state = new ImportState('0123456789abcdef');
        $this->assertTrue($state->isAlive(1800));

        Carbon::setTestNow(now()->addSeconds(1801));
        $this->assertFalse($state->isAlive(1800), 'no advance for longer than the horizon');

        $state->touch();
        $this->assertTrue($state->isAlive(1800));
    }

    #[Test]
    public function an_announced_long_statement_keeps_the_run_alive_until_its_time_is_up(): void
    {
        $state = new ImportState('0123456789abcdef');
        $state->expectSilenceFor(7200);

        Carbon::setTestNow(now()->addMinutes(45));
        $this->assertTrue($state->isAlive(1800), 'a 45 minute ALTER is not a dead run');

        Carbon::setTestNow(now()->addMinutes(120));
        $this->assertFalse($state->isAlive(1800), 'but an announcement does not last for ever');

        $state->clearExpectedSilence();
        $state->touch();
        $this->assertTrue($state->isAlive(1800));
    }

    #[Test]
    public function a_finished_run_is_never_alive(): void
    {
        $state = new ImportState('0123456789abcdef');
        $state->status = ImportStatus::Complete;

        $this->assertFalse($state->isAlive(1800));
    }

    #[Test]
    public function the_first_destructive_statement_is_recorded_once(): void
    {
        $state = new ImportState('0123456789abcdef');
        $this->assertFalse($state->isDestructive());

        $state->markDestructive();
        $first = $state->destructiveStartedAt;
        Carbon::setTestNow(now()->addMinute());
        $state->markDestructive();

        $this->assertTrue($state->isDestructive());
        $this->assertSame($first, $state->destructiveStartedAt, 'the moment of the FIRST change is what matters');
    }

    // ---- the marker -----------------------------------------------------------------------------------------------

    #[Test]
    public function the_marker_is_one_file_that_names_its_run(): void
    {
        $this->assertFalse($this->marker->isActive());
        $this->assertNull($this->marker->runId());

        $this->marker->write('0123456789abcdef');

        $this->assertTrue($this->marker->isActive());
        $this->assertSame('0123456789abcdef', $this->marker->runId());

        $this->marker->remove();
        $this->assertFalse($this->marker->isActive());
        $this->assertFileDoesNotExist($this->marker->path().'.prev');
    }

    #[Test]
    public function asking_whether_the_marker_is_there_touches_no_database_and_no_cache(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->marker->isActive();
        $this->marker->write('0123456789abcdef');
        $this->marker->isActive();

        $this->assertSame([], $queries);
    }

    #[Test]
    public function a_marker_with_a_bad_run_id_is_active_but_names_no_run(): void
    {
        file_put_contents($this->marker->path(), json_encode(['run_id' => '../../evil']));

        $this->assertTrue($this->marker->isActive(), 'presence is what closes the site');
        $this->assertNull($this->marker->runId());
    }

    #[Test]
    public function a_busy_check_by_the_cleanup_engine_sees_the_marker(): void
    {
        $guard = app(SystemBusyGuard::class);
        $this->assertNull($guard->reason());

        $this->realMarker()->write('0123456789abcdef');

        $this->assertStringContainsString('import', (string) $guard->reason());
    }

    // ---- the mutex ----------------------------------------------------------------------------------------------

    #[Test]
    public function only_one_process_can_hold_a_run_and_the_loser_is_told_so(): void
    {
        $state = $this->store->create();
        $mutex = new ImportRunMutex($this->store);

        $first = $mutex->acquire($state->id);
        $this->assertNotNull($first);
        $this->assertNull($mutex->acquire($state->id), 'the second driver gets nothing');

        $second = $mutex->withLock($state->id, fn () => 'ran');
        $this->assertFalse($second['ran']);

        $mutex->release($first);

        $third = $mutex->withLock($state->id, fn () => 'ran');
        $this->assertTrue($third['ran']);
        $this->assertSame('ran', $third['result']);
    }

    #[Test]
    public function the_lock_is_released_even_when_the_work_throws(): void
    {
        $state = $this->store->create();
        $mutex = new ImportRunMutex($this->store);

        try {
            $mutex->withLock($state->id, function (): never {
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
        }

        $this->assertTrue($mutex->withLock($state->id, fn () => 1)['ran']);
    }

    #[Test]
    public function different_locks_of_one_run_do_not_block_each_other(): void
    {
        $state = $this->store->create();
        $mutex = new ImportRunMutex($this->store);

        $run = $mutex->acquire($state->id, 'run');
        $selfDrive = $mutex->acquire($state->id, 'selfdrive');

        $this->assertNotNull($selfDrive);

        $mutex->release($run);
        $mutex->release($selfDrive);
    }

    // ---- package identity -------------------------------------------------------------------------------------------

    private function package(string $name = 'p.oeparts'): string
    {
        $writer = $this->newWriter($name);
        $writer->addEntryFromString('db/x.0000', 'db', "hello\n", ['codec' => 'none']);
        $writer->seal();

        return $this->packagePath($name);
    }

    #[Test]
    public function an_untouched_package_matches_its_identity(): void
    {
        $path = $this->package();
        $identity = PackageIdentity::capture($path, 'ABCD-EFGH');

        PackageIdentity::assertUnchanged($path, $identity);

        $this->assertSame('ABCD-EFGH', $identity['fingerprint']);
        $this->assertSame(64, strlen($identity['file_sha256']));
        $this->assertSame(32, strlen($identity['package_id']));
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function touching_the_file_is_noticed(): void
    {
        $path = $this->package();
        $identity = PackageIdentity::capture($path);

        touch($path, time() + 100);
        clearstatcache();

        $this->expectChanged(fn () => PackageIdentity::assertUnchanged($path, $identity), 'mtime');
    }

    #[Test]
    public function appending_to_the_file_is_noticed(): void
    {
        $path = $this->package();
        $identity = PackageIdentity::capture($path);
        $mtime = filemtime($path);

        file_put_contents($path, 'x', FILE_APPEND);
        touch($path, $mtime); // even with the modification time put back

        $this->expectChanged(fn () => PackageIdentity::assertUnchanged($path, $identity), 'size');
    }

    #[Test]
    public function replacing_the_file_with_another_package_of_the_same_size_and_time_is_noticed(): void
    {
        $path = $this->package('first.oeparts');
        $other = $this->package('second.oeparts');
        $identity = PackageIdentity::capture($path);

        $this->assertSame(filesize($path), filesize($other), 'the test needs two packages of the same size');
        copy($other, $path);
        touch($path, $identity['mtime']);

        $this->expectChanged(fn () => PackageIdentity::assertUnchanged($path, $identity), 'header_sha256');
    }

    #[Test]
    public function a_flipped_byte_in_the_trailer_is_noticed(): void
    {
        $path = $this->package();
        $identity = PackageIdentity::capture($path);
        $mtime = filemtime($path);

        $handle = fopen($path, 'r+b');
        fseek($handle, -40, SEEK_END);
        $byte = fread($handle, 1);
        fseek($handle, -40, SEEK_END);
        fwrite($handle, chr(ord($byte) ^ 0xFF));
        fclose($handle);
        touch($path, $mtime);

        $this->expectChanged(fn () => PackageIdentity::assertUnchanged($path, $identity), 'trailer_sha256');
    }

    #[Test]
    public function a_missing_or_tiny_file_is_a_clear_error(): void
    {
        $this->expectException(MigrationException::class);
        PackageIdentity::capture($this->base.'/nope.oeparts');
    }

    #[Test]
    public function a_file_too_small_to_be_a_package_is_called_incomplete(): void
    {
        file_put_contents($this->base.'/tiny.oeparts', 'abc');

        try {
            PackageIdentity::capture($this->base.'/tiny.oeparts');
            $this->fail('expected a refusal');
        } catch (MigrationException $e) {
            $this->assertSame(MigrationErrorCode::PackageIncomplete, $e->errorCode);
        }
    }

    private function expectChanged(\Closure $check, string $what): void
    {
        try {
            $check();
            $this->fail('the change must be noticed');
        } catch (MigrationException $e) {
            $this->assertSame(MigrationErrorCode::PackageChanged, $e->errorCode);
            $this->assertStringContainsString($what, (string) ($e->params['what'] ?? ''));
        }
    }

    // ---- the lock of a live import survives both janitors ------------------------------------------------------------

    private function holdImportLock(string $runId, \DateTimeInterface $since): void
    {
        $lock = app(BackupLock::class);
        file_put_contents($lock->path(), json_encode(['owner' => 'import:'.$runId, 'acquired_at' => $since->format(DATE_ATOM)]));
    }

    private function realRun(array $changes = []): ImportState
    {
        $store = new ImportStateStore;
        $state = $store->create();
        $this->realRuns[] = $state->id;

        return $changes === [] ? $state : $store->update($state->id, function (ImportState $s) use ($changes): void {
            foreach ($changes as $property => $value) {
                $s->{$property} = $value;
            }
        });
    }

    private function bothJanitors(): void
    {
        app(UpdateWatchdog::class)->reclaimStale();
        app(BackupJanitor::class)->cleanupPartials();
    }

    #[Test]
    public function an_import_lock_older_than_every_generic_threshold_survives_both_janitors_while_its_heartbeat_is_fresh(): void
    {
        $run = $this->realRun();
        $this->realMarker()->write($run->id);
        $this->holdImportLock($run->id, now()->subHours(5));

        $this->assertTrue(app(BackupLock::class)->heldByLiveImport());
        $this->bothJanitors();

        $this->assertTrue(app(BackupLock::class)->isLocked(), 'the import still owns the lock');
    }

    #[Test]
    public function an_import_that_stopped_moving_loses_its_lock_to_the_janitors(): void
    {
        $run = $this->realRun();
        $this->realMarker()->write($run->id);
        $this->holdImportLock($run->id, now()->subHours(5));

        Carbon::setTestNow(now()->addSeconds(1801));
        $this->assertFalse(app(BackupLock::class)->heldByLiveImport());

        $this->bothJanitors();

        $this->assertFalse(app(BackupLock::class)->isLocked());
    }

    #[Test]
    public function a_forty_five_minute_statement_keeps_the_lock_and_the_janitors_leave_it_alone(): void
    {
        $run = $this->realRun();
        (new ImportStateStore)->update($run->id, fn (ImportState $s) => $s->expectSilenceFor(7200));
        $this->realMarker()->write($run->id);
        $this->holdImportLock($run->id, now()->subHours(5));

        Carbon::setTestNow(now()->addMinutes(45));

        $this->assertTrue(app(BackupLock::class)->heldByLiveImport());
        $this->bothJanitors();
        $this->assertTrue(app(BackupLock::class)->isLocked());
    }

    #[Test]
    public function without_the_marker_the_import_is_not_holding_the_site_so_its_lock_is_reclaimable(): void
    {
        $run = $this->realRun();
        $this->holdImportLock($run->id, now()->subHours(5));

        $this->assertFalse(app(BackupLock::class)->heldByLiveImport());
        $this->bothJanitors();

        $this->assertFalse(app(BackupLock::class)->isLocked());
    }

    #[Test]
    public function a_finished_or_unknown_run_never_vouches_for_the_lock(): void
    {
        $run = $this->realRun(['status' => ImportStatus::Complete]);
        $this->realMarker()->write($run->id);
        $this->holdImportLock($run->id, now()->subHours(5));
        $this->assertFalse(app(BackupLock::class)->heldByLiveImport());

        $this->holdImportLock('0123456789abcdef', now()->subHours(5));
        $this->assertFalse(app(BackupLock::class)->heldByLiveImport(), 'no state file');

        $this->holdImportLock('../../evil', now()->subHours(5));
        $this->assertFalse(app(BackupLock::class)->heldByLiveImport(), 'not an id at all');
    }

    #[Test]
    public function the_single_dispatcher_sends_an_import_owner_to_the_import_rule(): void
    {
        $run = $this->realRun();
        $this->realMarker()->write($run->id);
        $this->holdImportLock($run->id, now()->subHours(5));

        $this->assertTrue(app(BackupLock::class)->heldByLiveOwner(7200));
    }
}
