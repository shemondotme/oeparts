<?php

namespace Tests\Feature;

use App\Services\Backup\BackupLock;
use App\Services\Migration\Groups\GroupKey;
use App\Services\Migration\Groups\GroupRegistry;
use App\Services\Migration\Import\Files\FileApplier;
use App\Services\Migration\Import\Files\FilesJournal;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\RowLoader;
use App\Services\Migration\Import\Stages\FilesStage;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Package\Kdf;
use App\Services\Migration\Package\PackageReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DrivesRealImports;
use Tests\TestCase;

/**
 * Owner decision OD3: a live site that has been USED (orders, customers, payments, invoices) may be replaced, knowingly.
 * The target is classified populated, the checks say so, a run without the typed confirmation does not start, the way back
 * is made and verified first, the import replaces everything, and the rollback then restores EVERY row, file and .env key
 * exactly: after a clean run, after a fault in the middle of the data load and after a fault in the middle of the files.
 */
class PopulatedTargetReplaceTest extends TestCase
{
    use DrivesRealImports;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareRealImport();
    }

    protected function tearDown(): void
    {
        $this->cleanUpRealImport();

        parent::tearDown();
    }

    // ---- fixtures ---------------------------------------------------------------------------------------------

    private function sourceFile(string $path, string $bytes): void
    {
        @mkdir(dirname($this->base.'/public/'.$path), 0775, true);
        file_put_contents($this->base.'/public/'.$path, $bytes);
    }

    private function targetFile(string $path, string $bytes): void
    {
        @mkdir(dirname($this->base.'/target/public/'.$path), 0775, true);
        file_put_contents($this->base.'/target/public/'.$path, $bytes);
    }

    /** @return array<string, string> */
    private function targetTree(): array
    {
        $tree = [];
        $root = $this->base.'/target/public';

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile()) {
                $tree[substr($file->getPathname(), strlen($root) + 1)] = hash_file('sha256', $file->getPathname());
            }
        }

        ksort($tree);

        return $tree;
    }

    /**
     * The package comes from one site; then the TARGET is made into a used shop with its own files.
     *
     * @return array{package: string, source: array<string, string>, before: array{rows: array<string, string>, files: array<string, string>, env: string}}
     */
    private function usedTarget(): array
    {
        $this->sourceFile('media/photo.jpg', 'SOURCE photo');
        $this->sourceFile('media/sub/only-in-package.jpg', 'package only');
        $this->sourceFile('branding/logo.png', 'SOURCE logo');
        [$package, $source] = $this->packageOfThisSite(3, [GroupKey::Catalog, GroupKey::Content, GroupKey::Media]);

        // The live shop: customers have ordered and paid, there is an invoice and a support message, and its own files.
        $this->plantRow('orders', ['order_number' => 'LIVE-1']);
        $this->plantRow('orders', ['order_number' => 'LIVE-2']);
        $this->plantRow('payments', []);
        $this->plantRow('custom_invoices', []);
        $this->plantRow('contact_messages', []);
        $this->plantRow('redirects', ['from_url' => '/only-on-the-live-site', 'to_url' => '/x']);
        // The tests run inside a transaction, where SQLite cannot switch foreign keys off, so the live products point at parents that
        // are not there: a parent's TRUNCATE would otherwise cascade into products the rollback has already put back (MySQL, with the
        // checks off, does not behave like that).
        $this->deferForeignKeys();
        DB::table('products')->update(['manufacturer_id' => 9999, 'condition_id' => 9999]);
        DB::table('manufacturers')->delete();
        DB::table('conditions')->delete();
        $this->targetFile('media/photo.jpg', 'LIVE photo (will be replaced)');
        $this->targetFile('media/leftover.jpg', 'live file that is not in the package');

        return ['package' => $package, 'source' => $source, 'before' => ['rows' => $this->picture(), 'files' => $this->targetTree(), 'env' => file_get_contents($this->envFile)]];
    }

    private function confirmPopulated(string $id): void
    {
        $acks = (array) $this->store()->find($id)->package['preflight']['required_acks'];
        $this->manager()->confirm($id, $acks, ['door' => 'cli', 'typed' => 'REPLACE', 'replace_populated' => true]);
    }

    /** Advance with fresh services until the run is no longer running. */
    private function runOn(string $id): array
    {
        $progress = [];

        for ($i = 0; $i < 400; $i++) {
            $this->rebuildImportServices();
            $progress = $this->manager()->advance($id, 120.0);

            if ($progress['status'] !== 'running') {
                return $progress;
            }
        }

        $this->fail('the run never stopped');
    }

    private function rollBackFully(string $id): array
    {
        $progress = [];

        for ($i = 0; $i < 400; $i++) {
            $this->rebuildImportServices();
            $progress = $this->manager()->advance($id, 120.0);

            if ($progress['status'] !== 'rolling_back') {
                return $progress;
            }
        }

        $this->fail('the rollback never ended');
    }

    private function assertBackToBefore(array $before): void
    {
        $this->assertSame($before['rows'], $this->picture(), 'every row of every table is back');
        $this->assertSame($before['files'], $this->targetTree(), 'every file is back, the created ones gone');
        $this->assertSame($before['env'], file_get_contents($this->envFile), 'every .env key is back');
        $this->assertFalse((new ImportMarker)->isActive());
        $this->assertFalse(app(BackupLock::class)->isLocked());
    }

    // ---- classification and confirmation -----------------------------------------------------------------------------

    #[Test]
    public function a_used_site_is_classified_populated_and_nothing_starts_without_the_typed_confirmation(): void
    {
        $site = $this->usedTarget();

        $run = $this->runToPreflight($site['package'], ['door' => 'cli']);
        $id = $run['state']->id;

        $this->assertSame('awaiting_confirmation', $run['progress']['status']);
        $check = $this->checksOf($run['state'])['target_state'];
        $this->assertSame('warn', $check['status']);
        $this->assertSame('target_populated', $check['ack']);
        $this->assertContains('target_populated', $run['state']->package['preflight']['required_acks']);

        $acks = (array) $run['state']->package['preflight']['required_acks'];

        foreach ([['door' => 'cli'], ['door' => 'cli', 'typed' => 'REPLACE'], ['door' => 'cli', 'typed' => 'replace', 'replace_populated' => true]] as $attempt) {
            try {
                $this->manager()->confirm($id, $acks, $attempt);
                $this->fail('a populated site must not be replaced without every confirmation');
            } catch (MigrationException $e) {
                $this->assertSame(MigrationErrorCode::ReplaceNotConfirmed, $e->errorCode);
            }
        }

        try {
            $this->manager()->confirm($id, [], ['door' => 'cli', 'typed' => 'REPLACE', 'replace_populated' => true]);
            $this->fail('the acknowledgement of the populated target is required too');
        } catch (MigrationException $e) {
            $this->assertSame(MigrationErrorCode::AcknowledgementMissing, $e->errorCode);
        }

        $this->assertFalse($this->store()->find($id)->flag('confirmed'));
        $this->assertSame($site['before']['rows'], $this->picture(), 'not a row was touched');
        $this->assertFalse((new ImportMarker)->isActive());
    }

    // ---- the replacement and the way back -----------------------------------------------------------------------------

    #[Test]
    public function the_import_replaces_everything_and_the_rollback_restores_every_row_file_and_env_key(): void
    {
        $site = $this->usedTarget();
        $run = $this->runToPreflight($site['package'], ['door' => 'cli']);
        $id = $run['state']->id;
        $this->confirmPopulated($id);

        $progress = $this->runOn($id);

        $this->assertNotContains($progress['status'], ['failed', 'rolled_back', 'rollback_failed'], json_encode($progress['error'] ?? null));
        $state = $this->store()->find($id);
        $this->assertTrue($state->flag('recovery.done'), 'the way back was made first');
        $this->assertTrue($state->flag('files.done'));
        $this->assertSame(0, DB::table('orders')->count(), 'the old orders are gone');
        $this->assertSame(0, DB::table('payments')->count());
        $this->assertSame(0, DB::table('custom_invoices')->count());
        $this->assertSame(0, DB::table('contact_messages')->count());
        $this->assertSame($site['source']['products'], $this->picture()['products'], 'the package\'s products are in');
        $this->assertSame($site['source']['redirects'], $this->picture()['redirects']);
        $this->assertSame('SOURCE photo', file_get_contents($this->base.'/target/public/media/photo.jpg'), 'the same-named file was replaced: '.json_encode($state->package['files'] ?? null));
        $this->assertFileExists($this->base.'/target/public/media/sub/only-in-package.jpg');
        $this->assertFileExists($this->base.'/target/public/media/leftover.jpg', 'a file that was not in the package stays');
        $this->assertGreaterThanOrEqual(1, $state->package['files']['leftovers'], 'and is reported');

        $this->rebuildImportServices();
        $this->manager()->rollbackRun($id, 120.0);
        $progress = $this->rollBackFully($id);

        $this->assertSame('rolled_back', $progress['status']);
        $this->assertBackToBefore($site['before']);
        $this->assertSame('LIVE photo (will be replaced)', file_get_contents($this->base.'/target/public/media/photo.jpg'));
        $this->assertFileDoesNotExist($this->base.'/target/public/media/sub/only-in-package.jpg');
    }

    #[Test]
    public function a_fault_in_the_middle_of_the_data_load_puts_the_whole_used_shop_back(): void
    {
        $site = $this->usedTarget();
        $run = $this->runToPreflight($site['package'], ['door' => 'cli']);
        $id = $run['state']->id;
        $this->confirmPopulated($id);

        $loader = new class extends RowLoader
        {
            public bool $armed = true;

            public function insert(string $table, array $rows): void
            {
                if ($this->armed && $table === 'redirects') {
                    throw new \RuntimeException('disk full');
                }

                parent::insert($table, $rows);
            }
        };
        $this->app->instance(RowLoader::class, $loader);

        $progress = [];
        for ($i = 0; $i < 400; $i++) {
            $this->rebuildImportServices();
            $progress = $this->manager()->advance($id, 120.0);

            if ($progress['status'] !== 'running') {
                break;
            }
        }
        $this->assertSame('rolling_back', $progress['status'], 'the failed insert sent the run into its rollback');
        $loader->armed = false;

        $progress = $this->rollBackFully($id);

        $this->assertSame('rolled_back', $progress['status']);
        $this->assertBackToBefore($site['before']);
    }

    #[Test]
    public function a_fault_in_the_middle_of_the_files_puts_back_the_data_and_the_files(): void
    {
        $site = $this->usedTarget();
        $run = $this->runToPreflight($site['package'], ['door' => 'cli']);
        $id = $run['state']->id;
        $this->confirmPopulated($id);

        $disks = [
            'public' => ['path' => $this->base.'/target/public', 'prefix' => 'storage/app/public'],
            'local' => ['path' => $this->base.'/target/local', 'prefix' => 'storage/app/private'],
        ];
        // The third file fails: by then the first two are on the disk and journalled (one replaced an original, one is new).
        $this->app->bind(FilesStage::class, fn () => new class(app(Kdf::class), new GroupRegistry, app(ImportMarker::class), app(ImportStateStore::class), $disks) extends FilesStage
        {
            private int $calls = 0;

            public function __construct(Kdf $kdf, GroupRegistry $registry, ImportMarker $marker, ImportStateStore $store, private readonly array $myDisks)
            {
                parent::__construct($kdf, $registry, $marker, $store, $myDisks);
            }

            protected function applier(array $allowedRoots, FilesJournal $journal, string $runId): FileApplier
            {
                return new class($this->myDisks, $allowedRoots, $journal, $runId, $this) extends FileApplier
                {
                    public function __construct(array $disks, array $roots, FilesJournal $journal, string $runId, private readonly object $owner)
                    {
                        parent::__construct($disks, $roots, $journal, $runId);
                    }

                    public function apply(array $row, PackageReader $reader): array
                    {
                        if ($this->owner->bump() === 3) {
                            throw new \RuntimeException('power cut in the middle of the files');
                        }

                        return parent::apply($row, $reader);
                    }
                };
            }

            public function bump(): int
            {
                return ++$this->calls;
            }
        });

        $progress = [];
        for ($i = 0; $i < 400; $i++) {
            $this->rebuildImportServices();
            $progress = $this->manager()->advance($id, 120.0);

            if ($progress['status'] !== 'running') {
                break;
            }
        }
        $this->assertSame('rolling_back', $progress['status'], 'the fault sent the run into its rollback after the data was already loaded');
        $this->assertSame(0, DB::table('orders')->count(), 'the data really had been replaced by then');

        $progress = $this->rollBackFully($id);

        $this->assertSame('rolled_back', $progress['status']);
        $this->assertBackToBefore($site['before']);
    }
}
