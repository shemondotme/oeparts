<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Security\PackageAudit;
use App\Services\Backup\BackupLock;
use App\Services\Install\InstallManager;
use App\Services\Instance\InstanceState;
use App\Services\Migration\Import\Contracts\QueueGuard;
use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\ImportStatus;
use App\Services\Migration\Import\SequenceFloor;
use App\Services\Migration\Import\Stages\CompleteStage;
use App\Services\Migration\Import\Stages\FinAdminsStage;
use App\Services\Migration\Import\Stages\FinBackupKeyStage;
use App\Services\Migration\Import\Stages\FinCachesStage;
use App\Services\Migration\Import\Stages\FinQueueStage;
use App\Services\Migration\Import\Stages\FinReadinessStage;
use App\Services\Migration\Import\Stages\FinSequencesStage;
use App\Services\Migration\Import\Stages\FinSessionsStage;
use App\Services\Migration\Import\Stages\FinSitemapStage;
use App\Services\Migration\KeyEscrow;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Package\PackageReader;
use App\Support\DatabaseTranslationLoader;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsImportPackages;
use Tests\Support\PlantsRows;
use Tests\TestCase;

/**
 * Phase 7.2: the rest of the finishing stages (numbering, caches, administrators, sessions, links, sitemap, workers, backup key)
 * and the stage that lets go of everything and ends the run.
 */
class ImportCompletionTest extends TestCase
{
    use BuildsImportPackages;
    use PlantsRows;
    use RefreshDatabase;

    private string $ledgerBackup = '';

    private string $lockBackup = '';

    private bool $hadLock = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareImportTest();
        (new ImportMarker)->remove();
        app(BackupLock::class)->release();

        $ledger = InstanceState::file()->path();
        $this->ledgerBackup = is_file($ledger) ? (string) file_get_contents($ledger) : '';
        @unlink($ledger);
        @unlink($ledger.'.prev');

        $this->hadLock = is_file(storage_path('installed.lock'));
        $this->lockBackup = $this->hadLock ? (string) file_get_contents(storage_path('installed.lock')) : '';
    }

    protected function tearDown(): void
    {
        (new ImportMarker)->remove();
        app(BackupLock::class)->release();
        $ledger = InstanceState::file()->path();
        @unlink($ledger);
        @unlink($ledger.'.prev');

        if ($this->ledgerBackup !== '') {
            file_put_contents($ledger, $this->ledgerBackup);
        }

        // The tests that exercise the installer door delete and rewrite the real marker of an installed site: put it back.
        if ($this->hadLock) {
            file_put_contents(storage_path('installed.lock'), $this->lockBackup);
        }

        $this->cleanUpImportTest();

        parent::tearDown();
    }

    // ---- fixtures ---------------------------------------------------------------------------------------------

    private function state(array $flags, array $options = [], array $package = []): ImportState
    {
        $state = new ImportState('cmp'.bin2hex(random_bytes(6)), options: $options);
        $state->package = $package;

        foreach ($flags as $flag) {
            $state->setFlag($flag);
        }

        (new ImportMarker)->write($state->id);
        (new ImportStateStore)->save($state);

        return $state;
    }

    private function context(): ImportContext
    {
        return new ImportContext(microtime(true) + 60, app(KeyEscrow::class));
    }

    private function sequence(string $type, int $value, ?string $lastReset, bool $monthly = true): void
    {
        DB::table('sequences')->updateOrInsert(['type' => $type], ['current_value' => $value, 'resets_monthly' => $monthly, 'last_reset_month' => $lastReset, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function counter(string $type): int
    {
        return (int) DB::table('sequences')->where('type', $type)->value('current_value');
    }

    // ---- numbering --------------------------------------------------------------------------------------------

    #[Test]
    public function a_counter_below_the_numbers_already_issued_is_raised_and_one_above_them_is_never_lowered(): void
    {
        $ym = now()->format('Ym');
        $month = now()->format('Y-m');
        $this->plantRow('orders', ['order_number' => "ORD-{$ym}-000042", 'invoice_number' => "INV-{$ym}-000007"]);
        $this->plantRow('orders', ['order_number' => "ORD-{$ym}-000009", 'invoice_number' => null]);
        $this->plantRow('orders', ['order_number' => 'ORD-202001-000999', 'invoice_number' => null]); // another month: ignored
        $this->sequence('order', 5, $month);
        $this->sequence('invoice', 90, $month);

        $report = (new SequenceFloor)->apply();

        $this->assertSame(42, $this->counter('order'));
        $this->assertSame(90, $this->counter('invoice'), 'a counter that is already ahead is never lowered');
        $this->assertSame(5, $report['order']['raised_from']);
        $this->assertNull($report['invoice']['raised_from']);
    }

    #[Test]
    public function documents_with_their_own_series_are_covered_and_a_stale_month_catches_up(): void
    {
        $ym = now()->format('Ym');
        $this->sequence('credit_note', 1, '2020-01');
        $this->sequence('quote', 0, now()->format('Y-m'));
        DB::table('custom_invoices')->insert(['invoice_number' => "CRN-{$ym}-000030", 'document_type' => 'credit_note', 'status' => 'draft', 'client_name' => 'x', 'client_email' => 'x@example.test', 'client_address_line1' => 'x', 'client_city' => 'x', 'client_postal_code' => '1', 'client_country_code' => 'LT', 'currency' => 'EUR', 'issue_date' => now()->toDateString(), 'due_date' => now()->toDateString(), 'items' => '[]', 'subtotal' => 0, 'vat_amount' => 0, 'total' => 0, 'vat_rate' => 0, 'created_at' => now(), 'updated_at' => now()]);

        (new SequenceFloor)->apply();

        $this->assertSame(30, $this->counter('credit_note'));
        $this->assertSame(now()->format('Y-m'), DB::table('sequences')->where('type', 'credit_note')->value('last_reset_month'), 'the month catches up so the next number does not start again at 1');
        $this->assertSame(0, $this->counter('quote'));
    }

    #[Test]
    public function the_stage_writes_the_starting_point_of_this_site_to_the_ledger(): void
    {
        $this->plantRow('orders', ['order_number' => 'ORD-'.now()->format('Ym').'-000003']);
        $orders = (int) DB::table('orders')->max('id');
        $this->sequence('order', 3, now()->format('Y-m'));
        $state = $this->state(['fin.origin.done']);

        app(FinSequencesStage::class)->step($state, $this->context());

        $ledger = (new InstanceState)->read();
        $this->assertSame($orders, $ledger['watermarks']['orders']);
        $this->assertSame(0, $ledger['watermarks']['payments']);
        $this->assertArrayHasKey('order', $ledger['sequence_baseline']['series']);
        $this->assertTrue($state->flag('fin.sequences.done'));
    }

    // ---- caches -----------------------------------------------------------------------------------------------

    #[Test]
    public function a_translation_override_cached_as_empty_before_the_table_existed_is_forgotten(): void
    {
        DB::table('languages')->updateOrInsert(['code' => 'en'], ['name' => 'English', 'native_name' => 'English', 'locale' => 'en_US', 'flag_emoji' => 'x', 'is_active' => 1, 'is_default' => 1, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('language_strings')->insert(['lang_code' => 'en', 'group' => 'cmp', 'key' => 'hello', 'value' => 'Hello from the package', 'created_at' => now(), 'updated_at' => now()]);
        Cache::forever('translations.db.en.cmp', []); // what the installer path can leave behind
        $state = $this->state(['fin.sequences.done']);

        app(FinCachesStage::class)->step($state, $this->context());

        $this->assertFalse(Cache::has('translations.db.en.cmp'));
        $loader = new DatabaseTranslationLoader(new class implements Loader
        {
            public function load($locale, $group, $namespace = null)
            {
                return [];
            }

            public function addNamespace($namespace, $hint) {}

            public function addJsonPath($path) {}

            public function namespaces()
            {
                return [];
            }
        });
        $this->assertSame(['hello' => 'Hello from the package'], $loader->load('en', 'cmp'), 'the override is visible on the next load');
    }

    #[Test]
    public function an_empty_answer_is_never_cached_forever_just_because_the_table_was_not_there_yet(): void
    {
        Schema::drop('language_strings');
        Cache::forget('translations.db.en.cmp');
        $loader = new DatabaseTranslationLoader(new class implements Loader
        {
            public function load($locale, $group, $namespace = null)
            {
                return ['file' => 'from the file'];
            }

            public function addNamespace($namespace, $hint) {}

            public function addJsonPath($path) {}

            public function namespaces()
            {
                return [];
            }
        });

        $this->assertSame(['file' => 'from the file'], $loader->load('en', 'cmp'));
        $this->assertFalse(Cache::has('translations.db.en.cmp'), 'nothing was remembered, so the table appearing later is noticed at once');
    }

    // ---- administrators ---------------------------------------------------------------------------------------

    #[Test]
    public function at_least_one_administrator_must_be_able_to_sign_in_or_the_run_stops(): void
    {
        DB::table('admins')->delete();
        $nobody = $this->state(['fin.caches.done']);

        try {
            app(FinAdminsStage::class)->step($nobody, $this->context());
            $this->fail('a site nobody can enter must not be finished');
        } catch (MigrationException) {
            $this->addToAssertionCount(1);
        }

        $this->plantRow('admins', ['email' => 'root@site.test', 'password' => '$2y$04$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ01234', 'is_active' => 1, 'role' => 'super_admin']);
        $state = $this->state(['fin.caches.done']);

        app(FinAdminsStage::class)->step($state, $this->context());

        $this->assertSame(1, $state->package['admins']['usable']);
        $this->assertFalse($state->package['admins']['extra_created']);
    }

    #[Test]
    public function an_extra_administrator_is_created_by_email_and_its_hash_leaves_the_state(): void
    {
        DB::table('admins')->delete();
        $hash = password_hash('a-long-secret-password', PASSWORD_BCRYPT, ['cost' => 4]);
        $state = $this->state(['fin.caches.done'], ['extra_admin' => ['email' => 'break@glass.test', 'name' => 'Break Glass', 'password_hash' => $hash]]);

        app(FinAdminsStage::class)->step($state, $this->context());

        $this->assertSame(1, DB::table('admins')->where('email', 'break@glass.test')->where('is_active', 1)->where('role', 'super_admin')->count());
        $this->assertTrue($state->package['admins']['extra_created']);
        $this->assertArrayNotHasKey('password_hash', $state->options['extra_admin'], 'the hash is gone from the run once it was used');
        $this->assertStringNotContainsString('$2y$', json_encode($state->toArray()));

        $again = $this->state(['fin.caches.done'], ['extra_admin' => ['email' => 'break@glass.test', 'name' => 'Break Glass', 'password_hash' => $hash]]);
        app(FinAdminsStage::class)->step($again, $this->context());
        $this->assertSame(1, DB::table('admins')->where('email', 'break@glass.test')->count(), 'the same address is the same account');
    }

    // ---- sessions, workers, backup key, readiness ------------------------------------------------------------------

    #[Test]
    public function nobody_stays_signed_in_from_before(): void
    {
        DB::table('sessions')->insert(['id' => 'abc', 'payload' => 'x', 'last_activity' => time()]);
        $folder = $this->workDir.'/sessions';
        mkdir($folder, 0775, true);
        file_put_contents($folder.'/s1', 'x');
        file_put_contents($folder.'/.gitignore', '*');
        config(['session.files' => $folder]);
        $state = $this->state(['fin.admins.done']);

        app(FinSessionsStage::class)->step($state, $this->context());

        $this->assertSame(0, DB::table('sessions')->count());
        $this->assertFileDoesNotExist($folder.'/s1');
        $this->assertFileExists($folder.'/.gitignore', 'the placeholder that keeps the folder in the repository stays');
        $this->assertSame(2, $state->package['sessions_removed']);
    }

    #[Test]
    public function the_paused_queues_are_released_when_the_stage_runs(): void
    {
        $guard = new class implements QueueGuard
        {
            public int $resumed = 0;

            public function hold(): void {}

            public function resume(): void
            {
                $this->resumed++;
            }
        };
        $this->app->instance(QueueGuard::class, $guard);
        $this->app->forgetInstance(FinQueueStage::class);

        app(FinQueueStage::class)->step($this->state(['fin.sitemap.done']), $this->context());

        $this->assertSame(1, $guard->resumed);
    }

    #[Test]
    public function a_large_catalog_leaves_the_sitemap_to_the_nightly_schedule(): void
    {
        config(['migration.import.sitemap_inline_max' => -1]);
        $state = $this->state(['fin.links.done']);

        app(FinSitemapStage::class)->step($state, $this->context());

        $this->assertSame('scheduled', $state->package['sitemap']);
        $this->assertNotEmpty($state->package['stage_warnings']['fin_sitemap']);
    }

    private function installerWithTempEnv(string $text): InstallManager
    {
        $path = $this->workDir.'/.env';
        file_put_contents($path, $text);

        return new class($path) extends InstallManager
        {
            public function __construct(private readonly string $path) {}

            protected function envPath(): string
            {
                return $this->path;
            }
        };
    }

    #[Test]
    public function the_installer_door_gives_the_site_its_own_backup_key_once_and_never_replaces_one(): void
    {
        config(['backup.encryption.key' => '']);
        $installer = $this->installerWithTempEnv("APP_NAME=OeParts\nOE_BACKUP_KEY=\n");
        $this->app->instance(InstallManager::class, $installer);
        $this->app->forgetInstance(FinBackupKeyStage::class);

        $state = $this->state(['fin.queue.done'], ['door' => 'installer']);
        app(FinBackupKeyStage::class)->step($state, $this->context());

        $env = (string) file_get_contents($this->workDir.'/.env');
        $this->assertMatchesRegularExpression('/OE_BACKUP_KEY="?[A-Za-z0-9+\/=:_-]{20,}"?/', $env);
        $this->assertTrue($state->package['backup_key']['generated']);

        // Run again in the same process, whose config still says "no key": the file already has one and it is kept.
        $first = $env;
        $again = $this->state(['fin.queue.done'], ['door' => 'installer']);
        app(FinBackupKeyStage::class)->step($again, $this->context());

        $this->assertSame($first, (string) file_get_contents($this->workDir.'/.env'));
        $this->assertFalse($again->package['backup_key']['generated']);
    }

    #[Test]
    public function the_other_doors_leave_the_backup_key_alone(): void
    {
        config(['backup.encryption.key' => '']);
        $this->app->instance(InstallManager::class, $this->installerWithTempEnv("OE_BACKUP_KEY=\n"));
        $this->app->forgetInstance(FinBackupKeyStage::class);
        $state = $this->state(['fin.queue.done'], ['door' => 'cli']);

        app(FinBackupKeyStage::class)->step($state, $this->context());

        $this->assertSame("OE_BACKUP_KEY=\n", file_get_contents($this->workDir.'/.env'));
        $this->assertFalse($state->package['backup_key']['generated']);
    }

    #[Test]
    public function the_readiness_stage_passes_and_says_it_is_not_there_yet(): void
    {
        $state = $this->state(['fin.backup_key.done']);

        $this->assertTrue(app(FinReadinessStage::class)->step($state, $this->context())->done);
        $this->assertSame('not_implemented', $state->package['readiness']['status']);
    }

    // ---- complete ---------------------------------------------------------------------------------------------

    private function completeRun(array $options = []): ImportState
    {
        $state = $this->state(['fin.readiness.done'], $options + ['door' => 'cli'], ['groups' => ['core'], 'loaded' => ['settings' => 5]]);
        app(BackupLock::class)->acquire('import:'.$state->id);
        $file = 'complete-'.uniqid().'.oeparts';
        $reader = $this->newWriter($file);
        $reader->addEntryFromString('db/settings.0000', 'db', "{\"\$\":\"oe-rows/1\",\"table\":\"settings\"}\n", ['group' => 'core', 'rows' => 0, 'attrs' => ['table' => 'settings']]);
        $reader->seal();
        $opened = PackageReader::open($this->packagePath($file), $this->passphrase, $this->weakKdf());
        app(KeyEscrow::class)->put(ImportContext::escrowId($state->id), $opened->packageIdHex(), $opened->keys());
        $opened->close();

        return $state;
    }

    private function historyLines(string $id, string $event): int
    {
        return count(array_filter(app(PackageAudit::class)->history($id), fn (array $l): bool => $l['event'] === $event));
    }

    private function activityRows(string $id): int
    {
        return ActivityLog::query()->where('action', 'import.completed')->where('new_values->run_id', $id)->count();
    }

    #[Test]
    public function completing_lets_go_of_everything_and_ends_the_run(): void
    {
        $state = $this->completeRun();

        $result = app(CompleteStage::class)->step($state, $this->context());

        $this->assertTrue($result->done);
        $this->assertSame(ImportStatus::Complete, $state->status);
        $this->assertNotNull($state->finishedAt);
        $this->assertFalse((new ImportMarker)->isActive(), 'the 503 page and the worker hold end');
        $this->assertFalse(app(BackupLock::class)->isLocked());
        $this->assertNull(app(KeyEscrow::class)->get(ImportContext::escrowId($state->id)));
        $this->assertSame(1, $this->historyLines($state->id, 'import.completed'));
        $this->assertSame(1, $this->activityRows($state->id));

        foreach (['scrubbed', 'escrow', 'marker', 'lock', 'history', 'activity', 'installed_lock'] as $part) {
            $this->assertTrue($state->flag('complete.'.$part), $part);
        }
    }

    #[Test]
    public function a_run_killed_after_any_sub_step_resumes_to_one_line_one_row_and_one_lock_file(): void
    {
        $parts = ['scrubbed', 'escrow', 'marker', 'lock', 'history', 'activity', 'installed_lock'];

        foreach (range(0, count($parts) - 1) as $killedAfter) {
            $state = $this->completeRun(['door' => 'installer']);
            @unlink(storage_path('installed.lock'));

            // The first process did the first sub-steps and died: its flags are on disk, the rest has not happened.
            $done = array_slice($parts, 0, $killedAfter);
            $this->preDo($state, $done);

            app(CompleteStage::class)->step($state, $this->context());

            $this->assertSame(ImportStatus::Complete, $state->status, "after {$killedAfter} sub-steps");
            $this->assertSame(1, $this->historyLines($state->id, 'import.completed'), 'one history line');
            $this->assertSame(1, $this->activityRows($state->id), 'one Activity Log row');
            $this->assertFileExists(storage_path('installed.lock'));
            $this->assertFalse((new ImportMarker)->isActive());
        }
    }

    /** Make the sub-steps that "already happened" really have happened, and flag them, as a killed process would have left it. */
    private function preDo(ImportState $state, array $done): void
    {
        foreach ($done as $part) {
            match ($part) {
                'escrow' => app(KeyEscrow::class)->delete(ImportContext::escrowId($state->id)),
                'marker' => (new ImportMarker)->remove(),
                'lock' => app(BackupLock::class)->releaseIfOwnedBy('import:'.$state->id),
                'history' => app(PackageAudit::class)->record('import.completed', $state->id, ['rows' => 5]),
                'activity' => app(PackageAudit::class)->recordActivityLog('import.completed', $state->id, null, ['rows' => 5]),
                'installed_lock' => app(InstallManager::class)->finalizeLock(),
                default => null,
            };

            $state->setFlag('complete.'.$part);
        }

        (new ImportStateStore)->save($state);
    }

    #[Test]
    public function installed_lock_is_written_last_and_only_on_the_installer_door(): void
    {
        @unlink(storage_path('installed.lock'));
        $admin = $this->completeRun(['door' => 'admin']);
        app(CompleteStage::class)->step($admin, $this->context());
        $this->assertFileDoesNotExist(storage_path('installed.lock'), 'an admin or command-line site has one already: nothing to write');

        $recorder = new class extends InstallManager
        {
            /** @var array<string, bool> */
            public array $whenWritten = [];

            public function __construct() {}

            public function finalizeLock(): void
            {
                $this->whenWritten = ['marker' => (new ImportMarker)->isActive(), 'lock' => app(BackupLock::class)->isLocked()];
                parent::finalizeLock();
            }
        };
        $this->app->instance(InstallManager::class, $recorder);
        $this->app->forgetInstance(CompleteStage::class);

        app(CompleteStage::class)->step($this->completeRun(['door' => 'installer']), $this->context());

        $this->assertFileExists(storage_path('installed.lock'));
        $this->assertSame(['marker' => false, 'lock' => false], $recorder->whenWritten, 'installed.lock is written strictly last: never while the marker or the lock still exists');
    }
}
