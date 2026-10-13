<?php

namespace Tests\Feature;

use App\Models\BackupRun;
use App\Services\Backup\StageStepResult;
use App\Services\Migration\Export\AllowlistWalker;
use App\Services\Migration\Export\ExportPlan;
use App\Services\Migration\Export\Stages\ExportFilesStage;
use App\Services\Migration\Package\BoundedInflater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DrivesExportStage;
use Tests\TestCase;

/**
 * Phase 3.10: the files stage. Every allowed file is stored exactly once, and a volume can give any one of
 * them back byte for byte using only the index; hostile files never get in; and a process killed at any
 * moment resumes without a duplicate or a torn record.
 */
class ExportFilesStageTest extends TestCase
{
    use DrivesExportStage;
    use RefreshDatabase;

    private string $base = '';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('exportstage');
        config(['backup.staging_disk' => 'exportstage', 'migration.export.batch_seconds' => 60, 'migration.export.batch_files' => 256]);

        $this->base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-filestage-'.getmypid().'-'.uniqid();
        mkdir($this->base.'/public/media/nested/deep', 0775, true);
        mkdir($this->base.'/local/payment-proofs', 0775, true);
        mkdir($this->base.'/outside', 0775, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->base));
        parent::tearDown();
    }

    // ---- helpers ---------------------------------------------------------------------------------

    private function stage(): ExportFilesStage
    {
        return new ExportFilesStage([
            'public' => ['path' => $this->base.'/public', 'prefix' => 'storage/app/public'],
            'local' => ['path' => $this->base.'/local', 'prefix' => 'storage/app/private'],
        ]);
    }

    private function filesPlan(array $roots = ['public:media', 'local:payment-proofs']): ExportPlan
    {
        return $this->planOf([], ['file_roots' => $roots]);
    }

    private function putFile(string $relative, string $content): void
    {
        @mkdir(dirname($this->base.'/'.$relative), 0775, true);
        file_put_contents($this->base.'/'.$relative, $content);
    }

    /**
     * Drive the files stage to completion.
     *
     * @return array{steps: int}
     */
    private function driveFiles(BackupRun $run, ?\Closure $beforeStep = null, ?ExportFilesStage $stage = null): array
    {
        $state = [];
        $steps = 0;

        do {
            $beforeStep?->__invoke($steps, $state);
            $result = ($stage ?? $this->stage())->step($run, $state);
            $this->register($run, $result);
            $state = json_decode((string) json_encode($result->state), true) ?? [];
            $this->assertLessThan(3000, ++$steps, 'the stage never finishes');
        } while (! $result->done);

        $run->save();

        return ['steps' => $steps];
    }

    /**
     * The index of a finished export, read back the way an importer would: every shard, decompressed safely.
     *
     * @return array<int, array<string, mixed>>
     */
    private function index(BackupRun $run): array
    {
        $rows = [];

        foreach ($run->parts()->get()->filter(fn ($p) => ($p->meta['kind'] ?? null) === 'index')->sortBy('name') as $part) {
            $text = (new BoundedInflater($part->meta['raw_len']))->inflateString((string) Storage::disk('exportstage')->get($part->path));
            $this->assertSame(hash('sha256', (string) Storage::disk('exportstage')->get($part->path)), $part->sha256);

            $lines = array_values(array_filter(explode("\n", $text), fn (string $l): bool => $l !== ''));
            $this->assertCount($part->rows, $lines);

            foreach ($lines as $line) {
                $rows[] = json_decode($line, true);
            }
        }

        return $rows;
    }

    /** Pull one file back out of its volume using only the index row. */
    private function extract(BackupRun $run, array $row): string
    {
        $volume = $run->parts()->get()->first(fn ($p) => ($p->meta['kind'] ?? null) === 'volume' && $p->meta['index'] === $row['v']);
        $this->assertNotNull($volume, 'the volume named by the index exists');
        $bytes = (string) Storage::disk('exportstage')->get($volume->path);
        $out = '';

        foreach ($row['seg'] as [$offset, $length, $raw]) {
            $this->assertLessThanOrEqual($volume->bytes, $offset + $length, 'a segment lies inside its volume');
            $out .= (new BoundedInflater($raw))->inflateString(substr($bytes, $offset, $length));
        }

        return $out;
    }

    // ---- what is stored ---------------------------------------------------------------------------

    #[Test]
    public function every_allowed_file_comes_back_byte_for_byte_from_its_volume_using_only_the_index(): void
    {
        $this->putFile('public/media/a.jpg', 'small file');
        $this->putFile('public/media/nested/deep/ünï çode ☃.png', random_bytes(300_000));
        $this->putFile('public/media/empty.txt', '');
        $this->putFile('local/payment-proofs/proof.pdf', str_repeat('PDF', 1000));
        $big = random_bytes(2_500_000);
        $this->putFile('public/media/big.bin', $big);

        $run = $this->run_($this->filesPlan());
        $this->driveFiles($run);

        $index = $this->index($run);
        $byPath = [];
        foreach ($index as $row) {
            $byPath[$row['d'].':'.$row['p']] = $row;
        }

        $this->assertEqualsCanonicalizing([
            'public:media/a.jpg', 'public:media/nested/deep/ünï çode ☃.png', 'public:media/empty.txt',
            'local:payment-proofs/proof.pdf', 'public:media/big.bin',
        ], array_keys($byPath));

        foreach ($byPath as $key => $row) {
            $disk = explode(':', $key)[0];
            $original = (string) file_get_contents($this->base.'/'.$disk.'/'.$row['p']);
            $restored = $this->extract($run, $row);

            $this->assertSame($original, $restored, $key);
            $this->assertSame(hash('sha256', $original), $row['h'], $key);
            $this->assertSame(strlen($original), $row['s'], $key);
        }

        $this->assertCount(3, $byPath['public:media/big.bin']['seg'], '2.5 MB is three 1 MiB blocks');
        $this->assertSame([], $byPath['public:media/empty.txt']['seg'], 'an empty file has no blocks');
        $this->assertGreaterThan(0, filemtime($this->base.'/public/media/a.jpg'));
        $this->assertSame(filemtime($this->base.'/public/media/a.jpg'), $byPath['public:media/a.jpg']['m']);
    }

    #[Test]
    public function hostile_files_never_get_in_and_the_report_says_why(): void
    {
        $this->putFile('public/media/ok.jpg', 'fine');
        $this->putFile('public/media/.env', 'APP_KEY=secret');
        $this->putFile('public/media/shell.php', '<?php evil();');
        $this->putFile('outside/secret.txt', 'outside the disk');
        symlink($this->base.'/outside/secret.txt', $this->base.'/public/media/link.jpg');

        $run = $this->run_($this->filesPlan(['public:media']));
        $this->driveFiles($run);

        $this->assertSame(['public:media/ok.jpg'], array_map(fn ($r) => $r['d'].':'.$r['p'], $this->index($run)));

        $report = $run->meta['export_report']['files'];
        $this->assertSame(1, $report['archived']);
        $counts = $report['skipped']['counts'];
        ksort($counts);
        $this->assertSame(['executable_extension' => 1, 'forbidden_name' => 1, 'symlink' => 1], $counts);
        $this->assertCount(3, $report['skipped']['sample']);
        $this->assertStringNotContainsString('APP_KEY', (string) json_encode($run->parts()->get()->all()));
    }

    #[Test]
    public function the_work_files_are_cleaned_up_and_only_volumes_and_index_shards_remain_as_parts(): void
    {
        $this->putFile('public/media/a.jpg', 'a');

        $run = $this->run_($this->filesPlan(['public:media']));
        $this->driveFiles($run);

        $this->assertSame(['index', 'volume'], $run->parts()->get()->map(fn ($p) => $p->meta['kind'])->unique()->sort()->values()->all());
        Storage::disk('exportstage')->assertMissing("backups/{$run->id}/export/files/entries.ndjson");
        Storage::disk('exportstage')->assertMissing("backups/{$run->id}/export/files/skipped.ndjson");
        Storage::disk('exportstage')->assertMissing("backups/{$run->id}/export/files/index.000.ndjson");
    }

    #[Test]
    public function nothing_to_copy_finishes_cleanly_with_no_parts(): void
    {
        $run = $this->run_($this->filesPlan(['public:media']));
        $this->driveFiles($run);

        $this->assertSame(0, $run->parts()->count());
        $this->assertSame(0, $run->meta['export_report']['files']['archived']);
    }

    #[Test]
    public function a_selection_with_no_file_groups_does_nothing_at_all(): void
    {
        $run = $this->run_($this->filesPlan([]));
        $result = $this->stage()->step($run, []);

        $this->assertTrue($result->done);
        $this->assertSame(0, $run->parts()->count());
    }

    // ---- shape of the output -------------------------------------------------------------------------

    #[Test]
    public function volumes_are_closed_at_their_size_and_a_file_never_straddles_two(): void
    {
        foreach (range(1, 12) as $i) {
            $this->putFile("public/media/f{$i}.bin", random_bytes(40_000));
        }
        config(['migration.export.volume_bytes' => 100_000]);

        $run = $this->run_($this->filesPlan(['public:media']));
        $this->driveFiles($run);

        $volumes = $run->parts()->get()->filter(fn ($p) => $p->meta['kind'] === 'volume');
        $this->assertGreaterThan(2, $volumes->count());

        $index = $this->index($run);
        $this->assertCount(12, $index);
        $this->assertSame(12, (int) $volumes->sum('rows'), 'each volume knows how many files it holds');

        foreach ($index as $row) {
            $this->assertSame((string) file_get_contents($this->base.'/public/'.$row['p']), $this->extract($run, $row));
        }

        foreach ($volumes as $volume) {
            $this->assertSame(hash('sha256', (string) Storage::disk('exportstage')->get($volume->path)), $volume->sha256);
            $this->assertSame(Storage::disk('exportstage')->size($volume->path), $volume->bytes);
        }
    }

    #[Test]
    public function index_shards_rotate_after_the_configured_number_of_rows(): void
    {
        foreach (range(1, 17) as $i) {
            $this->putFile("public/media/f{$i}.txt", "file {$i}");
        }
        config(['migration.export.index_shard_rows' => 7]);

        $run = $this->run_($this->filesPlan(['public:media']));
        $this->driveFiles($run);

        $shards = $run->parts()->get()->filter(fn ($p) => $p->meta['kind'] === 'index')->sortBy('name')->values();
        $this->assertSame([7, 7, 3], $shards->pluck('rows')->all());
        $this->assertSame(['files/index.000', 'files/index.001', 'files/index.002'], $shards->pluck('name')->all());
        $this->assertCount(17, $this->index($run));
    }

    // ---- resume ---------------------------------------------------------------------------------------

    #[Test]
    public function a_stage_killed_after_every_step_with_torn_appends_everywhere_still_produces_the_same_export(): void
    {
        foreach (range(1, 40) as $i) {
            $this->putFile('public/media/dir'.($i % 4)."/f{$i}.bin", random_bytes(500 + $i * 37));
        }
        config(['migration.export.batch_files' => 3, 'migration.export.batch_seconds' => 0, 'migration.export.volume_bytes' => 9000, 'migration.export.index_shard_rows' => 11]);

        $clean = $this->run_($this->filesPlan(['public:media']));
        $this->driveFiles($clean);
        $expected = $this->contentsByPath($clean);

        $crashy = $this->run_($this->filesPlan(['public:media']));
        $this->driveFiles($crashy, function (int $step, array $state) use ($crashy): void {
            if ($state === []) {
                return;
            }

            // The previous process died in the middle of appending: junk after the committed bytes of each work file.
            $disk = Storage::disk('exportstage');
            $volume = sprintf('backups/%d/export/files/vol-%04d.oevol', $crashy->id, $state['vol']['n']);
            $shard = sprintf('backups/%d/export/files/index.%03d.ndjson', $crashy->id, $state['shard']['n']);
            $entries = sprintf('backups/%d/export/files/entries.ndjson', $crashy->id);

            foreach ([$volume, $shard, $entries] as $relative) {
                if (is_file($disk->path($relative))) {
                    file_put_contents($disk->path($relative), "\x1f\x8bTORN-AFTER-THE-CHECKPOINT", FILE_APPEND);
                }
            }
        });

        $this->assertSame($expected, $this->contentsByPath($crashy));
        $this->assertSame($clean->parts()->count(), $crashy->parts()->count());
        $this->assertCount(40, $this->index($crashy));
    }

    /** @return array<string, string> path => sha256 of what a restore would produce */
    private function contentsByPath(BackupRun $run): array
    {
        $out = [];
        foreach ($this->index($run) as $row) {
            $out[$row['d'].':'.$row['p']] = hash('sha256', $this->extract($run, $row));
        }
        ksort($out);

        return $out;
    }

    #[Test]
    public function a_file_that_vanishes_between_the_scan_and_its_turn_is_recorded_as_skipped_not_fatal(): void
    {
        foreach (range(1, 6) as $i) {
            $this->putFile("public/media/f{$i}.txt", "file {$i}");
        }
        config(['migration.export.batch_files' => 2, 'migration.export.batch_seconds' => 0]);

        $removed = false;
        $run = $this->run_($this->filesPlan(['public:media']));
        $this->driveFiles($run, function (int $step, array $state) use (&$removed): void {
            // Once the scan is over and archiving has begun, delete a file that is still waiting its turn.
            if (! $removed && ($state['phase'] ?? null) === 'archive') {
                unlink($this->base.'/public/media/f6.txt');
                $removed = true;
            }
        });

        $this->assertTrue($removed);
        $this->assertSame(['f1.txt', 'f2.txt', 'f3.txt', 'f4.txt', 'f5.txt'], collect($this->index($run))->pluck('p')->map(fn ($p) => basename($p))->sort()->values()->all());
        $this->assertSame(1, $run->meta['export_report']['files']['vanished']);
    }

    #[Test]
    public function the_checkpoint_is_taken_once_per_batch_not_once_per_file(): void
    {
        foreach (range(1, 300) as $i) {
            $this->putFile("public/media/f{$i}.txt", "x{$i}");
        }

        $run = $this->run_($this->filesPlan(['public:media']));
        $steps = $this->driveFiles($run)['steps'];

        $this->assertCount(300, $this->index($run));
        $this->assertLessThanOrEqual(300 / 50, $steps, 'a few steps for 300 files: far below one checkpoint per file');
    }

    #[Test]
    public function the_state_handed_back_each_step_stays_small(): void
    {
        foreach (range(1, 200) as $i) {
            $this->putFile("public/media/f{$i}.php", '<?php');
        }
        $this->putFile('public/media/ok.txt', 'ok');
        config(['migration.export.batch_seconds' => 0]);

        $run = $this->run_($this->filesPlan(['public:media']));
        $state = [];
        $largest = 0;

        do {
            $result = $this->stage()->step($run, $state);
            $this->register($run, $result);
            $state = json_decode((string) json_encode($result->state), true) ?? [];
            $largest = max($largest, strlen((string) json_encode($state)));
        } while (! $result->done);

        $this->assertLessThan(4000, $largest, 'two hundred refused files must not swell the checkpoint: the sample lives in a file');
        $this->assertCount(AllowlistWalkerSample::LIMIT, $run->meta['export_report']['files']['skipped']['sample']);
        $this->assertSame(200, $run->meta['export_report']['files']['skipped']['counts']['executable_extension']);
    }

    #[Test]
    public function a_run_without_a_plan_is_refused(): void
    {
        $run = BackupRun::create(['profile' => BackupRun::PROFILE_EXPORT, 'status' => BackupRun::STATUS_RUNNING, 'trigger' => 'manual', 'disk' => 'exportstage']);

        $this->expectException(\RuntimeException::class);
        $this->stage()->step($run, []);
    }

    #[Test]
    public function the_step_result_type_is_the_engines(): void
    {
        $run = $this->run_($this->filesPlan(['public:media']));

        $this->assertInstanceOf(StageStepResult::class, $this->stage()->step($run, []));
    }
}

/** The sample size the walker keeps, named for readability in the assertion above. */
final class AllowlistWalkerSample
{
    public const LIMIT = AllowlistWalker::SKIPPED_SAMPLE;
}
