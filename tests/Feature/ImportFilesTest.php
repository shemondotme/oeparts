<?php

namespace Tests\Feature;

use App\Services\Migration\Groups\GroupRegistry;
use App\Services\Migration\Import\Files\FileApplier;
use App\Services\Migration\Import\Files\FilesJournal;
use App\Services\Migration\Import\Files\FilesRollbackHook;
use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\Stages\FilesStage;
use App\Services\Migration\KeyEscrow;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Package\PackageReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsImportPackages;
use Tests\TestCase;

/**
 * Phase 6.7: the files of a package are put on the disk safely (only inside the folders of the groups being imported,
 * never through a link, never as a script), resumably, with a journal that lets a rollback put every file back exactly.
 */
class ImportFilesTest extends TestCase
{
    use BuildsImportPackages;
    use RefreshDatabase;

    private string $target = '';

    /** @var array<string, array{path: string, prefix: string}> */
    private array $disks = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareImportTest();
        (new ImportMarker)->remove();

        $this->target = $this->workDir.'/target';
        mkdir($this->target.'/public', 0775, true);
        mkdir($this->target.'/local', 0775, true);
        $this->disks = [
            'public' => ['path' => $this->target.'/public', 'prefix' => 'storage/app/public'],
            'local' => ['path' => $this->target.'/local', 'prefix' => 'storage/app/private'],
        ];
    }

    protected function tearDown(): void
    {
        (new ImportMarker)->remove();
        $this->cleanUpImportTest();

        parent::tearDown();
    }

    // ---- fixtures ---------------------------------------------------------------------------------------------

    /**
     * Build a package holding these files the way the export writes them: one volume of independently compressed 1 MiB
     * blocks and one index shard. A file's record can be altered with 'row' to craft a hostile or damaged package.
     *
     * @param  array<int, array{p: string, bytes: string, d?: string, row?: array<string, mixed>}>  $files
     */
    private function filePackage(array $files, string $name = 'files.oeparts'): string
    {
        $writer = $this->newWriter($name, ['source' => ['domain' => 'old.example']]);
        $volume = '';
        $index = '';

        foreach ($files as $file) {
            $segments = [];

            foreach ($file['bytes'] === '' ? [] : str_split($file['bytes'], 1024 * 1024) as $block) {
                $member = gzencode($block, 6);
                $segments[] = [strlen($volume), strlen($member), strlen($block)];
                $volume .= $member;
            }

            $row = ['d' => $file['d'] ?? 'public', 'p' => $file['p'], 's' => strlen($file['bytes']), 'm' => 1700000000, 'h' => hash('sha256', $file['bytes']), 'v' => 0, 'seg' => $segments];
            $index .= json_encode(array_replace($row, $file['row'] ?? []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
        }

        $writer->addEntryFromString('files/vol-0000', 'files', $volume, ['group' => 'media', 'rows' => count($files), 'codec' => 'none']);
        $writer->addEntryFromString('files/index.000', 'files-index', $index, ['group' => 'media', 'rows' => count($files), 'codec' => 'none']);
        $writer->seal();

        return $this->packagePath($name);
    }

    private function state(string $package, array $groups = ['core', 'media']): ImportState
    {
        $state = new ImportState('fil'.bin2hex(random_bytes(6)));
        $state->package = ['path' => $package, 'groups' => $groups];
        $state->setFlag('seed.done');
        (new ImportMarker)->write($state->id);
        (new ImportStateStore)->save($state);

        $reader = PackageReader::open($package, $this->passphrase, $this->weakKdf());
        app(KeyEscrow::class)->put(ImportContext::escrowId($state->id), $reader->packageIdHex(), $reader->keys());
        $reader->close();

        return $state;
    }

    private function stage(?FileApplier $applier = null): FilesStage
    {
        $stage = new class($this->weakKdf(), new GroupRegistry, new ImportMarker, new ImportStateStore, $this->disks) extends FilesStage
        {
            public ?FileApplier $forced = null;

            protected function applier(array $allowedRoots, FilesJournal $journal, string $runId): FileApplier
            {
                return $this->forced ?? parent::applier($allowedRoots, $journal, $runId);
            }
        };
        $stage->forced = $applier;

        return $stage;
    }

    private function context(float $seconds = 60.0): ImportContext
    {
        return new ImportContext(microtime(true) + $seconds, app(KeyEscrow::class));
    }

    private function expired(): ImportContext
    {
        return new ImportContext(microtime(true) - 1, app(KeyEscrow::class));
    }

    private function hook(): FilesRollbackHook
    {
        return new FilesRollbackHook(new ImportStateStore, new GroupRegistry, $this->disks);
    }

    private function onDisk(string $path, string $disk = 'public'): string
    {
        return $this->disks[$disk]['path'].'/'.$path;
    }

    private function placeFile(string $path, string $bytes, string $disk = 'public'): void
    {
        $file = $this->onDisk($path, $disk);
        @mkdir(dirname($file), 0775, true);
        file_put_contents($file, $bytes);
    }

    /** @return array<string, string> relative path => sha256 of every file under both disks */
    private function tree(): array
    {
        $tree = [];

        foreach ($this->disks as $disk => $info) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($info['path'], \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile()) {
                    $tree[$disk.':'.substr($file->getPathname(), strlen($info['path']) + 1)] = hash_file('sha256', $file->getPathname());
                }
            }
        }

        ksort($tree);

        return $tree;
    }

    /** Run the stage to its end, a fresh stage and a fresh state read for every step. */
    private function runToEnd(ImportState $state, ?\Closure $stageFactory = null, ?float $budget = null): ImportState
    {
        for ($i = 0; $i < 500; $i++) {
            $stage = $stageFactory === null ? $this->stage() : $stageFactory();
            $current = (new ImportStateStore)->find($state->id);
            $result = $stage->step($current, $budget === null ? $this->context() : $this->expired());
            (new ImportStateStore)->save($current);

            if ($result->done) {
                return (new ImportStateStore)->find($state->id);
            }
        }

        $this->fail('the files stage never finished');
    }

    // ---- writing ---------------------------------------------------------------------------------------------

    #[Test]
    public function every_file_comes_out_byte_for_byte_with_its_folders_and_the_stage_reports_what_it_did(): void
    {
        $big = random_bytes(2 * 1024 * 1024 + 12345);
        $package = $this->filePackage([
            ['p' => 'media/a.jpg', 'bytes' => 'small file'],
            ['p' => 'media/deep/er/b.png', 'bytes' => $big],
            ['p' => 'media/é/ü.jpg', 'bytes' => 'unicode'],
            ['p' => 'media/empty.txt', 'bytes' => ''],
            ['p' => 'branding/logo.svg', 'bytes' => '<svg xmlns="http://www.w3.org/2000/svg"/>'],
        ]);
        $state = $this->state($package);

        $done = $this->runToEnd($state);

        $this->assertTrue($done->flag('files.done'));
        $this->assertSame($big, file_get_contents($this->onDisk('media/deep/er/b.png')));
        $this->assertSame('unicode', file_get_contents($this->onDisk('media/é/ü.jpg')));
        $this->assertSame('', file_get_contents($this->onDisk('media/empty.txt')));
        $this->assertFileExists($this->onDisk('branding/logo.svg'));
        $this->assertSame(5, $done->package['files']['written']);
        $this->assertSame(0, $done->package['files']['replaced']);
        $this->assertSame(0, $done->package['files']['unchanged']);
        $this->assertSame([], $done->package['files']['rejected']);
        $this->assertSame(strlen($big) + 10 + 7 + 0 + 41, $done->package['files']['bytes']);
        $this->assertSame([], glob($this->onDisk('media').'/.oeimp-*'), 'no temporary file is left behind');
    }

    #[Test]
    public function files_get_the_permissions_of_the_storage_tree_not_the_package(): void
    {
        $package = $this->filePackage([['p' => 'media/x/y.jpg', 'bytes' => 'x']]);

        $this->runToEnd($this->state($package));

        $this->assertSame('0664', substr(sprintf('%o', fileperms($this->onDisk('media/x/y.jpg'))), -4));
        $this->assertSame('0775', substr(sprintf('%o', fileperms($this->onDisk('media/x'))), -4));
    }

    #[Test]
    public function only_the_folders_of_the_selected_groups_are_filled(): void
    {
        $package = $this->filePackage([
            ['p' => 'media/m.jpg', 'bytes' => 'media'],
            ['p' => 'branding/b.svg', 'bytes' => '<svg/>'],
        ]);

        $done = $this->runToEnd($this->state($package, ['core']));

        $this->assertFileDoesNotExist($this->onDisk('media/m.jpg'), 'media was not selected');
        $this->assertFileExists($this->onDisk('branding/b.svg'));
        $this->assertSame(['outside_allowed_roots' => 1], $done->package['files']['rejected']);
    }

    #[Test]
    public function nothing_unsafe_is_ever_written_and_the_rest_of_the_files_still_are(): void
    {
        mkdir($this->target.'/outside', 0775, true);
        $package = $this->filePackage([
            ['p' => 'media/ok.jpg', 'bytes' => 'fine'],
            ['p' => 'media/../../../outside/escape.jpg', 'bytes' => 'x'],
            ['p' => 'media/shell.php', 'bytes' => 'x'],
            ['p' => 'media/photo.jpg.php', 'bytes' => 'x'],
            ['p' => 'media/.htaccess', 'bytes' => 'x'],
            ['p' => 'media/.env', 'bytes' => 'x'],
            ['p' => '/etc/passwd', 'bytes' => 'x'],
            ['p' => 'media\\win.jpg', 'bytes' => 'x'],
            ['p' => 'media/disguised.jpg', 'bytes' => "GIF89a<?php system(\$_GET['c']); ?>"],
            ['p' => 'media/also-ok.png', 'bytes' => 'fine too'],
        ]);

        $done = $this->runToEnd($this->state($package));

        $this->assertSame(['media/also-ok.png', 'media/ok.jpg'], array_values(array_map(fn (string $k): string => substr($k, 7), array_keys($this->tree()))));
        $this->assertSame(2, $done->package['files']['written']);
        $rejected = $done->package['files']['rejected'];
        $this->assertSame(1, $rejected['traversal']);
        $this->assertSame(2, $rejected['executable_extension']);
        $this->assertSame(2, $rejected['forbidden_name']);
        $this->assertSame(1, $rejected['not_canonical'], 'a leading slash becomes an empty segment once it is joined to the folder');
        $this->assertSame(1, $rejected['backslash']);
        $this->assertSame(1, $rejected['looks_like_code']);
        $this->assertFileDoesNotExist($this->target.'/outside/escape.jpg');
        $this->assertSame([], glob($this->onDisk('media').'/.oeimp-*'));
        $this->assertCount(8, $done->package['files']['rejected_sample'] + array_fill(0, 8, null), 'a sample of what was refused is kept for the report');
    }

    #[Test]
    public function a_file_is_never_written_through_a_link(): void
    {
        mkdir($this->target.'/elsewhere', 0775, true);
        mkdir($this->onDisk('media'), 0775, true);

        if (! @symlink($this->target.'/elsewhere', $this->onDisk('media/linked'))) {
            $this->markTestSkipped('This filesystem cannot create symbolic links.');
        }

        $package = $this->filePackage([
            ['p' => 'media/linked/pwned.jpg', 'bytes' => 'x'],
            ['p' => 'media/fine.jpg', 'bytes' => 'ok'],
        ]);

        $done = $this->runToEnd($this->state($package));

        $this->assertFileDoesNotExist($this->target.'/elsewhere/pwned.jpg');
        $this->assertSame(['symlink' => 1], $done->package['files']['rejected']);
        $this->assertFileExists($this->onDisk('media/fine.jpg'));
    }

    #[Test]
    public function a_damaged_file_stops_the_stage_and_leaves_neither_the_file_nor_a_temporary_one(): void
    {
        $package = $this->filePackage([
            ['p' => 'media/good.jpg', 'bytes' => 'good'],
            ['p' => 'media/bad.jpg', 'bytes' => 'the real bytes', 'row' => ['h' => str_repeat('a', 64)]],
        ]);
        $state = $this->state($package);

        try {
            $this->stage()->step((new ImportStateStore)->find($state->id), $this->context());
            $this->fail('the hash does not match: the stage must stop');
        } catch (MigrationException $e) {
            $this->assertSame(MigrationErrorCode::PackageCorrupt, $e->errorCode);
        }

        $this->assertFileDoesNotExist($this->onDisk('media/bad.jpg'));
        $this->assertSame([], glob($this->onDisk('media').'/.oeimp-*'));
    }

    #[Test]
    public function an_index_record_that_contradicts_itself_is_a_corrupt_package_not_a_skipped_file(): void
    {
        $package = $this->filePackage([['p' => 'media/a.jpg', 'bytes' => 'abcdef', 'row' => ['s' => 99]]]);

        $this->expectException(MigrationException::class);
        $this->stage()->step($this->state($package), $this->context());
    }

    // ---- resuming ---------------------------------------------------------------------------------------------

    #[Test]
    public function a_run_stopped_after_every_file_by_a_fresh_process_ends_with_exactly_the_same_files(): void
    {
        $files = [];
        foreach (range(1, 12) as $i) {
            $files[] = ['p' => "media/f{$i}.bin", 'bytes' => random_bytes(500 + $i)];
        }
        $package = $this->filePackage($files);

        $reference = $this->runToEnd($this->state($package));
        $expected = $this->tree();
        foreach (array_keys($expected) as $key) {
            @unlink($this->disks['public']['path'].'/'.substr($key, 7));
        }

        $state = $this->state($package);
        $steps = 0;
        do {
            $this->stage()->step($current = (new ImportStateStore)->find($state->id), $this->expired());
            (new ImportStateStore)->save($current);
            $steps++;
        } while (! (new ImportStateStore)->find($state->id)->flag('files.done') && $steps < 100);

        $this->assertGreaterThan(5, $steps);
        $this->assertSame($expected, $this->tree());
        $this->assertSame($reference->package['files']['written'], (new ImportStateStore)->find($state->id)->package['files']['written']);
    }

    #[Test]
    public function a_file_that_is_already_there_with_the_right_content_is_counted_and_left_alone(): void
    {
        $package = $this->filePackage([['p' => 'media/a.jpg', 'bytes' => 'same bytes'], ['p' => 'media/b.jpg', 'bytes' => 'new']]);
        $this->placeFile('media/a.jpg', 'same bytes');
        touch($this->onDisk('media/a.jpg'), 1_000_000_000);

        $done = $this->runToEnd($this->state($package));

        $this->assertSame(1, $done->package['files']['unchanged']);
        $this->assertSame(1, $done->package['files']['written']);
        $this->assertSame(1_000_000_000, filemtime($this->onDisk('media/a.jpg')), 'an identical file is not rewritten');
        $journal = new FilesJournal(new ImportStateStore, $state = $done->id);
        $this->assertFileDoesNotExist($journal->replacedPath(), 'nothing was replaced, so nothing is journalled as replaced');
    }

    #[Test]
    public function a_crash_in_the_middle_is_picked_up_where_it_stopped(): void
    {
        $files = [];
        foreach (range(1, 6) as $i) {
            $files[] = ['p' => "media/f{$i}.bin", 'bytes' => "content {$i}"];
        }
        $package = $this->filePackage($files);
        $state = $this->state($package);

        $failing = new class($this->disks, new FilesJournal(new ImportStateStore, $state->id), $state->id) extends FileApplier
        {
            public int $calls = 0;

            public function __construct(array $disks, FilesJournal $journal, string $runId)
            {
                parent::__construct($disks, ['storage/app/public/media'], $journal, $runId);
            }

            public function apply(array $row, PackageReader $reader): array
            {
                if (++$this->calls === 4) {
                    throw new \RuntimeException('power cut');
                }

                return parent::apply($row, $reader);
            }
        };

        try {
            $this->stage($failing)->step((new ImportStateStore)->find($state->id), $this->context());
            $this->fail('the crash must surface');
        } catch (\RuntimeException) {
            $this->addToAssertionCount(1);
        }

        $done = $this->runToEnd($state);

        $this->assertSame(6, count($this->tree()));
        $this->assertTrue($done->flag('files.done'));
    }

    // ---- the journal and the rollback ------------------------------------------------------------------------

    #[Test]
    public function the_rollback_deletes_what_was_created_and_puts_every_replaced_original_back(): void
    {
        $this->placeFile('media/keep-me.jpg', 'not in the package');
        $this->placeFile('media/replace-me.jpg', 'the ORIGINAL');
        $package = $this->filePackage([
            ['p' => 'media/replace-me.jpg', 'bytes' => 'from the package'],
            ['p' => 'media/new/created.jpg', 'bytes' => 'created'],
        ]);
        $before = $this->tree();
        $state = $this->state($package);

        $done = $this->runToEnd($state);

        $this->assertSame('from the package', file_get_contents($this->onDisk('media/replace-me.jpg')));
        $this->assertSame(1, $done->package['files']['replaced']);
        $this->assertSame(1, $done->package['files']['leftovers'], 'the file that was not in the package stays, and is reported');
        $this->assertFileExists($this->onDisk('media/keep-me.jpg'));

        $this->assertTrue($this->hook()->run((new ImportStateStore)->find($state->id), $this->context()));

        $this->assertSame($before, $this->tree(), 'the tree is exactly what it was before the import');
        $this->assertSame('the ORIGINAL', file_get_contents($this->onDisk('media/replace-me.jpg')));
    }

    #[Test]
    public function the_rollback_of_a_half_finished_import_removes_the_temporary_file_too(): void
    {
        $package = $this->filePackage([['p' => 'media/a.jpg', 'bytes' => 'a']]);
        $state = $this->state($package);
        $journal = new FilesJournal(new ImportStateStore, $state->id);
        $this->placeFile('media/.oeimp-'.$state->id.'-deadbeef.tmp', 'half written');
        $journal->noteTemporary('public', 'media/a.jpg', $this->onDisk('media/.oeimp-'.$state->id.'-deadbeef.tmp'));

        $this->assertTrue($this->hook()->run((new ImportStateStore)->find($state->id), $this->context()));

        $this->assertSame([], glob($this->onDisk('media').'/.oeimp-*'));
    }

    #[Test]
    public function the_rollback_replayed_one_line_at_a_time_by_fresh_processes_ends_the_same_and_twice_is_harmless(): void
    {
        $this->placeFile('media/r1.jpg', 'orig 1');
        $this->placeFile('media/r2.jpg', 'orig 2');
        $files = [['p' => 'media/r1.jpg', 'bytes' => 'new 1'], ['p' => 'media/r2.jpg', 'bytes' => 'new 2']];
        foreach (range(1, 5) as $i) {
            $files[] = ['p' => "media/c{$i}.jpg", 'bytes' => "created {$i}"];
        }
        $package = $this->filePackage($files);
        $before = $this->tree();
        $state = $this->state($package);
        $this->runToEnd($state);

        $steps = 0;
        do {
            $current = (new ImportStateStore)->find($state->id);
            $finished = $this->hook()->run($current, $this->expired());
            (new ImportStateStore)->save($current);
            $steps++;
        } while (! $finished && $steps < 100);

        $this->assertGreaterThan(3, $steps);
        $this->assertSame($before, $this->tree());

        $again = (new ImportStateStore)->find($state->id);
        $again->stageState = [];
        $this->assertTrue($this->hook()->run($again, $this->context()));
        $this->assertSame($before, $this->tree(), 'replaying the whole journal again changes nothing');
    }

    #[Test]
    public function the_rollback_will_not_touch_a_path_the_policy_refuses_even_if_the_journal_names_it(): void
    {
        $state = $this->state($this->filePackage([['p' => 'media/a.jpg', 'bytes' => 'a']]));
        $victim = $this->target.'/precious.txt';
        file_put_contents($victim, 'keep');
        $journal = new FilesJournal(new ImportStateStore, $state->id);
        mkdir($journal->directory(), 0775, true);
        file_put_contents($journal->createdPath(), json_encode(['d' => 'public', 'p' => '../../precious.txt'])."\n".json_encode(['d' => 'public', 'p' => 'media/.env'])."\n".'{"d":"public","p":"media/torn');

        $this->assertTrue($this->hook()->run((new ImportStateStore)->find($state->id), $this->context()));

        $this->assertFileExists($victim);
    }

    #[Test]
    public function a_package_whose_files_are_all_outside_the_selected_groups_finishes_and_writes_nothing(): void
    {
        $state = $this->state($this->filePackage([['p' => 'media/a.jpg', 'bytes' => 'a']]), ['core']);

        $done = $this->runToEnd($state);

        $this->assertSame([], $this->tree());
        $this->assertSame(['outside_allowed_roots' => 1], $done->package['files']['rejected']);
    }
}
