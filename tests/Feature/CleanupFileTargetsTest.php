<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\BackupRun;
use App\Models\CleanupRun;
use App\Models\ProductImportRun;
use App\Services\Backup\BackupLock;
use App\Services\Cleanup\CleanupContext;
use App\Services\Cleanup\CleanupManager;
use App\Services\Cleanup\Contracts\CleanupTarget;
use App\Services\Cleanup\ProtectedPath;
use App\Services\Cleanup\TargetRegistry;
use App\Services\Cleanup\Targets\ConfiguredFileTarget;
use App\Services\Cleanup\Targets\FileSweepTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * File and folder cleanup. Part 1 drives FileSweepTarget phase by phase inside a throw-away "project" so every
 * edge (symlinks, resume, vanished files, recent files) can be provoked safely. Part 2 runs the real configured
 * targets against roots redirected, via config and a faked disk, to directories inside the test sandbox.
 * No test here ever touches this project's real storage/.
 */
class CleanupFileTargetsTest extends TestCase
{
    use RefreshDatabase;

    private string $base;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        // A sandbox "project": ProtectedPath is told this is the project base, so roots inside it are valid.
        $this->base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-filetargets-'.getmypid();
        $this->rrmdir($this->base);
        $this->root = $this->base.'/storage/scratch';
        mkdir($this->root, 0775, true);
        mkdir($this->base.'/storage/app/updates', 0775, true);
        mkdir($this->base.'/vendor', 0775, true);

        config([
            'updates.state_path' => $this->base.'/storage/app/updates',
            'updates.root_path' => $this->base.'/root', // never the real checkout
            'cleanup.chunk_rows' => 200,
            'cleanup.sleep_ms' => 0,
        ]);
        mkdir($this->base.'/root', 0775, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->base);
        foreach (glob(storage_path('framework/cleanup/*-tmp_sweep.ndjson')) ?: [] as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    private function rrmdir(string $path): void
    {
        if (is_link($path) || (file_exists($path) && ! is_dir($path))) {
            @unlink($path);

            return;
        }
        if (! is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $e) {
            if ($e !== '.' && $e !== '..') {
                $this->rrmdir($path.DIRECTORY_SEPARATOR.$e);
            }
        }
        @rmdir($path);
    }

    private function file(string $path, string $content = 'data', int $ageHours = 48): string
    {
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, $content);
        touch($path, time() - $ageHours * 3600);

        return $path;
    }

    /** Make a file or a whole folder tree look old (a directory's own mtime counts as activity, so touch them all). */
    private function age(string $path, int $hours = 72): void
    {
        $when = time() - $hours * 3600;
        if (is_dir($path) && ! is_link($path)) {
            foreach (scandir($path) ?: [] as $e) {
                if ($e !== '.' && $e !== '..') {
                    $this->age($path.DIRECTORY_SEPARATOR.$e, $hours);
                }
            }
        }
        @touch($path, $when);
    }

    private function sweep(bool $dirs = false, bool $recursive = false, string $pattern = '/.*/'): TmpSweepTarget
    {
        return new TmpSweepTarget(new ProtectedPath($this->base), [$this->root], $dirs, $recursive, $pattern);
    }

    /** @return array{0: CleanupContext, 1: CleanupRun} */
    private function context(bool $dry = false, int $days = 1): array
    {
        $run = CleanupRun::create(['status' => 'running', 'active_key' => 'cleanup', 'mode' => $dry ? 'dry_run' : 'execute']);
        $item = $run->items()->create(['target' => 'tmp_sweep', 'cutoff' => now()->subDays($days), 'retention_days' => $days]);

        return [new CleanupContext($run, $item, $item->cutoff, $days, $dry, false, 200, 0, null, fn () => false), $run];
    }

    /**
     * Drive every phase to completion in many tiny ticks (each with an already-expired deadline, so every call does the
     * minimum unit of work), round-tripping the checkpoint through JSON like the manager does between requests.
     *
     * @return array<string, mixed>
     */
    private function drive(TmpSweepTarget $target, CleanupContext $ctx, bool $delete = true, ?callable $between = null): array
    {
        $cp = $target->begin($ctx);
        $ticks = 0;

        // A preview (dry run) only ever plans: the manager never calls delete() or verify() for one.
        foreach ($ctx->dryRun ? ['plan'] : ['plan', 'delete', 'verify'] as $phase) {
            if ($phase === 'delete' && ! $delete) {
                continue;
            }
            do {
                $done = $target->{$phase}($ctx, $cp, microtime(true) - 1);
                $cp = json_decode((string) json_encode($cp), true);
                if ($between !== null) {
                    $between($phase, $cp);
                }
                $this->assertLessThan(10000, ++$ticks, 'a phase must make progress every tick');
            } while (! $done);
        }

        return $cp;
    }

    /* ---- part 1: FileSweepTarget --------------------------------------- */

    #[Test]
    public function it_removes_old_files_keeps_recent_ones_and_measures_what_it_freed(): void
    {
        $this->file($this->root.'/old-a.log', str_repeat('a', 1000));
        $this->file($this->root.'/old-b.log', str_repeat('b', 2500));
        $this->file($this->root.'/recent.log', 'keep', 0); // modified just now

        [$ctx] = $this->context();
        $cp = $this->drive($this->sweep(), $ctx);

        $this->assertSame(2, $cp['targeted']);
        $this->assertSame(2, $cp['deleted']);
        $this->assertSame(0, $cp['failed']);
        $this->assertSame(0, $cp['remaining']);
        $this->assertSame(3500, $cp['bytes_apparent']);
        $this->assertGreaterThanOrEqual(3500, $cp['bytes_allocated']);
        $this->assertFileDoesNotExist($this->root.'/old-a.log');
        $this->assertFileExists($this->root.'/recent.log');
        $this->assertSame([], glob(storage_path('framework/cleanup/*-tmp_sweep.ndjson')) ?: [], 'the work file is removed when the target finishes');
    }

    #[Test]
    public function a_preview_counts_and_sizes_but_deletes_nothing_and_leaves_no_work_file(): void
    {
        $this->file($this->root.'/old.log', str_repeat('x', 4000));

        [$ctx] = $this->context(dry: true);
        $cp = $this->drive($this->sweep(), $ctx, delete: false);

        $this->assertSame(1, $cp['targeted']);
        $this->assertSame(4000, $cp['bytes_apparent'], 'what a real run would free');
        $this->assertFileExists($this->root.'/old.log');
        $this->assertSame([], glob(storage_path('framework/cleanup/*-tmp_sweep.ndjson')) ?: []);
    }

    #[Test]
    public function it_resumes_across_many_ticks_without_duplicating_or_skipping_anything(): void
    {
        for ($i = 0; $i < 450; $i++) { // more than two scan batches of 200
            $this->file($this->root."/f{$i}.tmp", 'x');
        }

        [$ctx] = $this->context();
        $cp = $this->drive($this->sweep(), $ctx);

        $this->assertSame(450, $cp['targeted']);
        $this->assertSame(450, $cp['deleted']);
        $this->assertSame(0, $cp['remaining']);
        $this->assertSame([], glob($this->root.'/*') ?: []);
    }

    #[Test]
    public function a_crash_between_writing_the_list_and_saving_the_checkpoint_never_duplicates_list_lines(): void
    {
        for ($i = 0; $i < 450; $i++) {
            $this->file($this->root."/f{$i}.tmp", 'x');
        }
        [$ctx] = $this->context();
        $target = $this->sweep();

        $cp = $target->begin($ctx);
        $target->plan($ctx, $cp, microtime(true) - 1);      // tick 1: checkpoint saved
        $saved = json_decode((string) json_encode($cp), true);
        $target->plan($ctx, $cp, microtime(true) - 1);      // tick 2 ran...
        $cp = $saved;                                       // ...but the process died before its checkpoint was saved

        do {
            $done = $target->plan($ctx, $cp, microtime(true) - 1);
            $cp = json_decode((string) json_encode($cp), true);
        } while (! $done);

        $this->assertSame(450, $cp['targeted'], 'every file counted exactly once');
        $work = storage_path('framework/cleanup/'.$ctx->run->id.'-tmp_sweep.ndjson');
        $this->assertCount(450, file($work, FILE_IGNORE_NEW_LINES));
        @unlink($work);
    }

    #[Test]
    public function a_file_that_vanishes_between_plan_and_delete_is_reported_as_removed_by_someone_else(): void
    {
        $this->file($this->root.'/a.log');
        $this->file($this->root.'/b.log');
        [$ctx] = $this->context();

        $cp = $this->drive($this->sweep(), $ctx, true, function (string $phase) {
            if ($phase === 'plan') {
                @unlink($this->root.'/b.log'); // gone before the delete phase
            }
        });

        $this->assertSame(2, $cp['targeted']);
        $this->assertSame(1, $cp['deleted']);
        $this->assertSame(0, $cp['failed'], 'already-gone is not a failure');
        $this->assertSame(0, $cp['remaining']);
        $this->assertSame(1, $cp['targeted'] - $cp['deleted'] - $cp['remaining'], 'reconciles as external_gone = 1');
    }

    #[Test]
    public function a_file_touched_after_planning_is_refused_not_deleted(): void
    {
        $this->file($this->root.'/busy.log');
        [$ctx] = $this->context();

        $cp = $this->drive($this->sweep(), $ctx, true, function (string $phase) {
            if ($phase === 'plan') {
                touch($this->root.'/busy.log'); // something wrote to it right now
            }
        });

        $this->assertFileExists($this->root.'/busy.log', 'safety is re-checked at the moment of deletion');
        $this->assertSame(0, $cp['deleted']);
        $this->assertSame(1, $cp['failed']);
    }

    #[Test]
    public function a_symlink_is_never_followed_and_what_it_points_at_survives(): void
    {
        $outside = $this->file($this->base.'/storage/precious.txt', 'do not delete');
        $link = $this->root.'/innocent.log';
        if (! @symlink($outside, $link)) {
            $this->markTestSkipped('Symlinks are not available in this environment.');
        }
        touch($link, time() - 48 * 3600);

        [$ctx] = $this->context();
        $cp = $this->drive($this->sweep(), $ctx);

        $this->assertSame(0, $cp['targeted'], 'a link is not even a candidate');
        $this->assertFileExists($outside);
        $this->assertTrue(is_link($link));
    }

    #[Test]
    public function folders_are_removed_whole_without_following_links_inside_them(): void
    {
        $outside = $this->file($this->base.'/storage/precious.txt', 'safe');
        $this->file($this->root.'/run-7/a/b/deep.bin', str_repeat('z', 500));
        $this->file($this->root.'/run-7/top.bin', str_repeat('z', 300));
        @symlink($outside, $this->root.'/run-7/link-out');
        $this->age($this->root.'/run-7');
        $this->file($this->root.'/run-8/recent.bin', 'new', 0);
        touch($this->root.'/run-8', time() - 48 * 3600);

        [$ctx] = $this->context();
        $cp = $this->drive($this->sweep(dirs: true, pattern: '/^run-\d+$/'), $ctx);

        $this->assertSame(1, $cp['targeted']);
        $this->assertSame(1, $cp['deleted']);
        $this->assertSame(0, $cp['remaining']);
        $this->assertDirectoryDoesNotExist($this->root.'/run-7');
        $this->assertFileExists($this->root.'/run-8/recent.bin');
        $this->assertFileExists($outside, 'the link inside the removed folder was unlinked, not followed');
        $this->assertGreaterThanOrEqual(800, $cp['bytes_apparent']);
    }

    #[Test]
    public function a_protected_path_inside_a_swept_folder_stops_that_folder_from_being_removed(): void
    {
        // A root that (wrongly) contains the update recovery net must never lose it.
        $swept = new TmpSweepTarget(new ProtectedPath($this->base), [$this->base.'/storage/app'], true, false, '/^updates$/');
        $this->file($this->base.'/storage/app/updates/last-swap.json', '{}');
        touch($this->base.'/storage/app/updates', time() - 48 * 3600);

        [$ctx] = $this->context();
        $cp = $this->drive($swept, $ctx);

        $this->assertSame(0, $cp['targeted'], 'storage/app/updates holds last-swap.json: never a candidate');
        $this->assertFileExists($this->base.'/storage/app/updates/last-swap.json');
    }

    #[Test]
    public function recursive_roots_only_return_files_and_skip_entries_that_fail_the_pattern(): void
    {
        $this->file($this->root.'/ab/cd/'.str_repeat('a', 40));
        $this->file($this->root.'/ab/keep.txt');
        $this->file($this->root.'/top-'.str_repeat('b', 40));

        [$ctx] = $this->context();
        $cp = $this->drive($this->sweep(recursive: true, pattern: '/^(top-)?[a-f0-9]{40}$/'), $ctx);

        $this->assertSame(2, $cp['targeted']);
        $this->assertFileExists($this->root.'/ab/keep.txt');
        $this->assertFileDoesNotExist($this->root.'/ab/cd/'.str_repeat('a', 40));
    }

    /* ---- part 2: the configured targets -------------------------------- */

    private function configured(string $key): ConfiguredFileTarget
    {
        foreach ((array) config('cleanup.targets') as $def) {
            if (is_array($def) && ($def['key'] ?? null) === $key) {
                return new ConfiguredFileTarget($def, new ProtectedPath);
            }
        }
        $this->fail("No file target '{$key}' is configured.");
    }

    private function runConfigured(string $key, ?array $options = null): CleanupRun
    {
        $target = $this->configured($key);
        app()->instance(TargetRegistry::class, new TargetRegistry([TargetFactory::for($target)]));
        $manager = app(CleanupManager::class);

        return $manager->run($manager->start([$key], CleanupRun::MODE_EXECUTE, CleanupRun::TRIGGER_MANUAL, null, $options ?? []), 120, 10);
    }

    private function sandbox(string $name): string
    {
        $dir = storage_path('framework/testing/cleanup-'.getmypid().'-'.$name);
        $this->rrmdir($dir);
        mkdir($dir, 0775, true);

        return $dir;
    }

    #[Test]
    public function every_configured_file_target_names_a_known_root_rule_and_pattern(): void
    {
        $files = array_filter((array) config('cleanup.targets'), fn ($d) => is_array($d) && ($d['type'] ?? '') === 'file');
        $this->assertGreaterThanOrEqual(14, count($files));

        foreach ($files as $def) {
            $target = new ConfiguredFileTarget($def, new ProtectedPath);   // throws on an unknown root / rule / action
            $this->assertSame('file', $target->kind());
            $this->assertNotSame('', $target->description());
            $this->assertNotFalse(@preg_match($def['pattern'], 'x'), "{$def['key']}: invalid pattern");
            $this->assertFalse($target->truncatable(), $def['key']);
        }
    }

    #[Test]
    public function an_unknown_root_or_action_in_a_definition_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ConfiguredFileTarget(['key' => 'x', 'label' => 'x', 'root' => '/etc', 'pattern' => '/.*/', 'days' => 1]);
    }

    #[Test]
    public function orphaned_backup_folders_go_but_a_live_backup_never_does(): void
    {
        Storage::fake('local');
        config(['backup.disk' => 'local', 'backup.staging_disk' => 'local']);
        $backups = Storage::disk('local')->path('backups');

        $mk = function (string $status, array $meta = []) use ($backups) {
            $run = BackupRun::create(['profile' => 'full', 'status' => $status, 'trigger' => 'scheduled', 'disk' => 'local', 'meta' => $meta]);
            $this->file($backups.'/'.$run->id.'/db/part.enc', 'payload');
            $this->age($backups.'/'.$run->id);
            DB::table('backup_runs')->where('id', $run->id)->update(['updated_at' => now()->subDays(3)]);

            return $run->id;
        };

        $live = $mk('success');                                  // finished, files meant to exist
        $failed = $mk('failed');
        $pruned = $mk('success', ['pruned_at' => now()->toIso8601String()]);
        $pending = $mk('pending');
        $this->file($backups.'/999/db/part.enc', 'orphan');       // no row at all
        $this->age($backups.'/999');
        $this->file($backups.'/'.$live.'-notes.txt', 'not a run folder');

        $run = $this->runConfigured('backup_orphans');

        $this->assertSame(CleanupRun::STATUS_SUCCESS, $run->status, json_encode($run->items()->first()->reasons));
        $this->assertDirectoryExists($backups.'/'.$live, 'a finished backup is NEVER an orphan');
        $this->assertDirectoryExists($backups.'/'.$pending, 'a running backup is never touched');
        $this->assertDirectoryDoesNotExist($backups.'/'.$failed);
        $this->assertDirectoryDoesNotExist($backups.'/'.$pruned);
        $this->assertDirectoryDoesNotExist($backups.'/999');
        $this->assertFileExists($backups.'/'.$live.'-notes.txt', 'does not match the folder pattern');
        $this->assertSame(3, $run->deleted);
    }

    #[Test]
    public function restore_working_folders_are_removed_only_for_finished_or_unknown_runs(): void
    {
        $restore = $this->sandbox('restore');
        config(['cleanup.restore_dir' => $restore]);
        $made = [];
        $mk = function (int $id) use ($restore, &$made) {
            $this->file($restore.'/run-'.$id.'/app/Model.php', 'x');
            $this->age($restore.'/run-'.$id, 24 * 10);
            $made[] = $restore.'/run-'.$id;
        };
        try {
            $ok = BackupRun::create(['profile' => 'full', 'status' => BackupRun::STATUS_SUCCESS, 'trigger' => 'manual', 'disk' => 'local']);
            $mk($ok->id);
            $mk(98765); // no such run
            $gone = BackupRun::create(['profile' => 'full', 'status' => BackupRun::STATUS_FAILED, 'trigger' => 'manual', 'disk' => 'local']);
            $mk($gone->id);

            $run = $this->runConfigured('restore_work');

            $this->assertSame(CleanupRun::STATUS_SUCCESS, $run->status);
            $this->assertSame(3, $run->deleted);
        } finally {
            $this->rrmdir($restore);
        }
    }

    #[Test]
    public function expired_cache_entries_go_live_ones_and_foreign_files_stay(): void
    {
        $dir = $this->sandbox('cache');
        config(['cache.default' => 'file', 'cache.stores.file' => ['driver' => 'file', 'path' => $dir]]);
        $expired = $this->file($dir.'/ab/cd/'.sha1('expired'), (now()->subDay()->getTimestamp()).'s:5:"hello";');
        $live = $this->file($dir.'/ab/ce/'.sha1('live'), (now()->addDay()->getTimestamp()).'s:5:"hello";');
        $lock = $this->file($dir.'/ab/cf/'.sha1('a held lock'), (now()->addMinutes(5)->getTimestamp()).'owner');
        $foreign = $this->file($dir.'/ab/readme.txt', 'x');

        $run = $this->runConfigured('file_cache');

        $this->assertSame(CleanupRun::STATUS_SUCCESS, $run->status);
        $this->assertFileDoesNotExist($expired);
        $this->assertFileExists($live);
        $this->assertFileExists($lock, 'a Cache::lock that has not expired is a live lock');
        $this->assertFileExists($foreign);
        $this->rrmdir($dir);
    }

    #[Test]
    public function the_file_cache_target_does_nothing_when_the_cache_driver_is_not_file(): void
    {
        $dir = $this->sandbox('cache2');
        config(['cache.default' => 'array', 'cache.stores.file' => ['driver' => 'file', 'path' => $dir]]);
        $expired = $this->file($dir.'/ab/cd/'.sha1('x'), (now()->subDay()->getTimestamp()).'s:1:"x";');

        $run = $this->runConfigured('file_cache');

        $this->assertSame(0, $run->targeted);
        $this->assertFileExists($expired);
        $this->rrmdir($dir);
    }

    #[Test]
    public function a_session_file_is_kept_until_well_after_its_lifetime(): void
    {
        $dir = $this->sandbox('sessions');
        config(['session.driver' => 'file', 'session.files' => $dir, 'session.lifetime' => 120]);

        $stale = $this->file($dir.'/'.str_repeat('a', 40), 's', 5);        // 5h > 120 min lifetime + margin
        $valid = $this->file($dir.'/'.str_repeat('b', 40), 's', 1);        // 1h < lifetime: STILL a valid session
        $edge = $this->file($dir.'/'.str_repeat('c', 40), 's', 2);         // 2h = lifetime: inside the margin

        $run = $this->runConfigured('file_sessions');

        $this->assertSame(CleanupRun::STATUS_SUCCESS, $run->status);
        $this->assertFileDoesNotExist($stale);
        $this->assertFileExists($valid, 'an active session must never be logged out by a cleanup');
        $this->assertFileExists($edge);
        $this->rrmdir($dir);
    }

    #[Test]
    public function an_oversized_log_is_emptied_in_place_and_a_small_or_unrelated_file_is_left_alone(): void
    {
        $dir = $this->sandbox('logs');
        config(['cleanup.log_dir' => $dir]);

        $big = $dir.'/laravel.log';
        $handle = fopen($big, 'wb');
        ftruncate($handle, ConfiguredFileTarget::OVERSIZED_LOG_BYTES + 1024); // sparse: no real 50 MB on disk
        fclose($handle);
        $small = $this->file($dir.'/laravel-2026-10-01.log', 'small', 0);
        $notALog = $dir.'/data.sqlite';
        file_put_contents($notALog, 'x');

        // A logger holds the file open for appending, like Monolog.
        $writer = fopen($big, 'ab');
        $inodeBefore = fileinode($big);

        $run = $this->runConfigured('oversized_logs');

        $this->assertSame(CleanupRun::STATUS_SUCCESS, $run->status);
        $this->assertFileExists($big, 'the file is emptied, never unlinked');
        clearstatcache();
        $this->assertSame(0, filesize($big));
        $this->assertSame($inodeBefore, fileinode($big), 'same inode: the open handle keeps working');
        fwrite($writer, "after truncate\n");
        fclose($writer);
        clearstatcache();
        $this->assertGreaterThan(0, filesize($big));
        $this->assertSame(5, filesize($small));
        $this->assertFileExists($notALog);
        $this->assertGreaterThanOrEqual(ConfiguredFileTarget::OVERSIZED_LOG_BYTES, $run->items()->first()->bytes_freed_apparent);
        $this->rrmdir($dir);
    }

    #[Test]
    public function rotated_log_files_older_than_the_retention_go_but_todays_never(): void
    {
        $dir = $this->sandbox('logs2');
        config(['cleanup.log_dir' => $dir]);
        $old = $this->file($dir.'/laravel-2026-09-01.log', 'old', 24 * 30);
        $today = $this->file($dir.'/laravel-2026-10-11.log', 'today', 0);
        $update = $this->file($dir.'/updates-2026-09-01.log', 'kept: update logs have their own, longer retention', 24 * 20);

        $run = $this->runConfigured('file_logs');

        $this->assertSame(CleanupRun::STATUS_SUCCESS, $run->status);
        $this->assertFileDoesNotExist($old);
        $this->assertFileExists($today);
        $this->assertFileExists($update);
        $this->rrmdir($dir);
    }

    #[Test]
    public function leftover_imports_go_but_one_still_referenced_by_a_running_import_stays(): void
    {
        Storage::fake('local');
        $imports = Storage::disk('local')->path('imports');
        $orphan = $this->file($imports.'/aaaa.csv');
        $running = $this->file($imports.'/bbbb.csv');
        ProductImportRun::create([
            'admin_id' => Admin::factory()->create()->id, 'status' => ProductImportRun::STATUS_RUNNING,
            'original_filename' => 'p.csv', 'path' => 'imports/bbbb.csv',
        ]);
        DB::table('product_import_runs')->update(['updated_at' => now()->subDays(2)]); // abandoned row: not "busy", but its file is still its own

        $run = $this->runConfigured('import_csv');

        $this->assertSame(CleanupRun::STATUS_SUCCESS, $run->status);
        $this->assertFileDoesNotExist($orphan);
        $this->assertFileExists($running, 'a running (resumable) import keeps its CSV');
    }

    #[Test]
    public function a_file_target_takes_the_shared_lock_while_it_works_and_releases_it(): void
    {
        Storage::fake('local');
        config(['backup.disk' => 'local', 'backup.staging_disk' => 'local']);
        $lock = app(BackupLock::class);
        $this->file(Storage::disk('local')->path('backups').'/777/part', 'x');
        $this->age(Storage::disk('local')->path('backups').'/777');

        $target = $this->configured('backup_orphans');
        app()->instance(TargetRegistry::class, new TargetRegistry([TargetFactory::for($target)]));
        $manager = app(CleanupManager::class);
        $run = $manager->start(['backup_orphans']);

        $this->assertTrue($lock->isLocked());
        $this->assertSame($run->lockOwner(), $lock->owner()['owner']);

        $manager->run($run, 60, 5);

        $this->assertFalse($lock->isLocked());
    }
}

/**
 * A FileSweepTarget over an explicit root inside the sandbox project.
 */
class TmpSweepTarget extends FileSweepTarget
{
    /** @param list<string> $roots */
    public function __construct(ProtectedPath $protected, private array $rootList, private bool $dirs, private bool $recursiveWalk, private string $pattern)
    {
        parent::__construct($protected);
    }

    protected function roots(): array
    {
        return $this->rootList;
    }

    protected function allowDirs(): bool
    {
        return $this->dirs;
    }

    protected function recursive(): bool
    {
        return $this->recursiveWalk;
    }

    protected function matches(string $basename): bool
    {
        return preg_match($this->pattern, $basename) === 1;
    }

    public function key(): string
    {
        return 'tmp_sweep';
    }

    public function label(): string
    {
        return 'Sandbox files';
    }

    public function description(): string
    {
        return 'Test.';
    }

    public function group(): string
    {
        return 'files';
    }

    public function defaultDays(): int
    {
        return 1;
    }

    public function floorDays(): int
    {
        return 1;
    }

    public function autoByDefault(): bool
    {
        return true;
    }

    public function needsBackupLock(): bool
    {
        return false;
    }
}

/** Lets the registry hand back an already-built target instance. */
final class TargetFactory
{
    /** @var array<string, CleanupTarget> */
    private static array $instances = [];

    public static function for(CleanupTarget $target): string
    {
        $class = 'FixedTarget_'.md5($target->key().spl_object_id($target));
        self::$instances[$class] = $target;

        app()->bind($class, fn () => self::$instances[$class]);

        return $class;
    }
}
