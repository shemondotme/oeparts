<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\CleanupDiskSample;
use App\Models\CleanupRun;
use App\Models\CleanupRunItem;
use App\Models\CleanupTargetStat;
use App\Services\Cleanup\CleanupMeasurer;
use App\Services\Cleanup\CleanupOverview;
use App\Services\Cleanup\DirectorySizer;
use App\Services\Cleanup\DiskUsageService;
use App\Services\Cleanup\FileLogManager;
use App\Services\Cleanup\TargetRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The numbers the Cleanup page shows (measurer + overview) and the file-log list.
 */
class CleanupOverviewTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        // Inside the project (a ProtectedPath requirement) but away from the real storage/logs.
        $this->dir = storage_path('framework/testing/cleanup-overview-'.getmypid());
        $this->rrmdir($this->dir);
        mkdir($this->dir, 0775, true);
        config(['cleanup.log_dir' => $this->dir, 'updates.root_path' => $this->dir.'-root']);
        @mkdir($this->dir.'-root', 0775, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->dir);
        $this->rrmdir($this->dir.'-root');
        parent::tearDown();
    }

    private function rrmdir(string $path): void
    {
        if (is_link($path) || (file_exists($path) && ! is_dir($path))) {
            @unlink($path);

            return;
        }
        if (is_dir($path)) {
            foreach (scandir($path) ?: [] as $e) {
                if ($e !== '.' && $e !== '..') {
                    $this->rrmdir($path.DIRECTORY_SEPARATOR.$e);
                }
            }
            @rmdir($path);
        }
    }

    private function log(string $name, int $bytes = 100, int $ageHours = 48): string
    {
        $path = $this->dir.'/'.$name;
        file_put_contents($path, str_repeat('x', $bytes));
        touch($path, time() - $ageHours * 3600);

        return $path;
    }

    /* ---- measurer ------------------------------------------------------- */

    #[Test]
    public function measuring_a_table_target_records_size_oldest_and_what_is_eligible(): void
    {
        $rows = [];
        for ($i = 0; $i < 50; $i++) {
            $rows[] = ['search_query' => 'q', 'normalized_query' => 'q', 'result_count' => 1, 'lang' => 'en', 'ip_address' => '1.1.1.1', 'created_at' => now()->subDays(200)];
        }
        for ($i = 0; $i < 10; $i++) {
            $rows[] = ['search_query' => 'n', 'normalized_query' => 'n', 'result_count' => 1, 'lang' => 'en', 'ip_address' => '1.1.1.1', 'created_at' => now()->subDay()];
        }
        DB::table('search_logs')->insert($rows);

        $stat = app(CleanupMeasurer::class)->measureTarget(app(TargetRegistry::class)->get('search_logs'));

        $this->assertSame(60, $stat->rows_est);
        $this->assertSame(50, $stat->eligible_rows);
        $this->assertNotNull($stat->oldest_at);
        $this->assertTrue($stat->oldest_at->lt(now()->subDays(100)), 'oldest by insert order');
        $this->assertNotNull($stat->measured_at);
        $this->assertSame(1, CleanupTargetStat::where('target', 'search_logs')->count(), 'updated in place, not duplicated');

        app(CleanupMeasurer::class)->measureTarget(app(TargetRegistry::class)->get('search_logs'));
        $this->assertSame(1, CleanupTargetStat::where('target', 'search_logs')->count());
        $this->assertSame(60, DB::table('search_logs')->count(), 'measuring never deletes');
    }

    #[Test]
    public function measuring_a_file_target_counts_and_sizes_what_is_eligible_and_leaves_nothing_behind(): void
    {
        $tmp = Storage::disk('local')->path('livewire-tmp');
        mkdir($tmp, 0775, true);
        foreach (['a', 'b'] as $name) {
            file_put_contents($tmp.'/'.$name, str_repeat('z', 1000));
            touch($tmp.'/'.$name, time() - 72 * 3600);
        }
        file_put_contents($tmp.'/fresh', 'new');

        $stat = app(CleanupMeasurer::class)->measureTarget(app(TargetRegistry::class)->get('livewire_tmp'));

        $this->assertSame(2, $stat->eligible_rows);
        $this->assertSame(2000, $stat->eligible_bytes_est);
        $this->assertFileExists($tmp.'/a', 'measuring never deletes');
        $this->assertSame([], glob(config('cleanup.work_dir').'/-livewire_tmp.ndjson') ?: [], 'no work file left behind');
    }

    #[Test]
    public function a_target_that_cannot_finish_within_its_budget_reports_eligible_as_unknown_not_a_partial_guess(): void
    {
        $tmp = Storage::disk('local')->path('livewire-tmp');
        mkdir($tmp, 0775, true);
        for ($i = 0; $i < 450; $i++) {
            file_put_contents($tmp.'/f'.$i, 'x');
            touch($tmp.'/f'.$i, time() - 72 * 3600);
        }

        // 0.2s is the floor; with 450 files the first scan batch (200) can finish the budget on a slow disk, so
        // assert the CONTRACT instead of the timing: either the exact count, or null — never a partial number.
        $stat = app(CleanupMeasurer::class)->measureTarget(app(TargetRegistry::class)->get('livewire_tmp'), 0.0);

        $this->assertContains($stat->eligible_rows, [null, 450]);
    }

    #[Test]
    public function the_command_measures_every_target_and_samples_the_disk(): void
    {
        $this->artisan('oeparts:cleanup:measure --budget=1')->assertSuccessful();

        $this->assertSame(count(app(TargetRegistry::class)->all()), CleanupTargetStat::count());
        $this->assertSame(1, CleanupDiskSample::count());
    }

    #[Test]
    public function a_disk_sample_breaks_usage_down_by_category_and_marks_incomplete_walks(): void
    {
        $this->log('laravel.log', 5000);

        $sample = app(CleanupMeasurer::class)->sampleDisk(5.0);

        $this->assertSame(5000, $sample->breakdown['logs']['bytes']);
        $this->assertTrue($sample->breakdown['logs']['complete']);

        $big = $this->dir.'/many';
        mkdir($big, 0775, true);
        for ($i = 0; $i < 600; $i++) {
            file_put_contents($big.'/f'.$i, 'x');
        }
        $partial = (new DirectorySizer)->size($this->dir, 0.0);
        $this->assertFalse($partial['complete'], 'a spent budget is reported as incomplete ("at least X"), not as the total');
        $this->assertGreaterThan(0, $partial['bytes']);
    }

    /* ---- overview ------------------------------------------------------- */

    #[Test]
    public function the_overview_lists_every_target_with_its_settings_and_cached_numbers(): void
    {
        CleanupTargetStat::create(['target' => 'cron_logs', 'rows_est' => 1_100_000, 'eligible_rows' => 900_000, 'eligible_bytes_est' => 40_000_000, 'measured_at' => now()]);

        $rows = app(CleanupOverview::class)->targets();

        $this->assertSame(array_keys(app(TargetRegistry::class)->all()), array_keys($rows));
        $cron = $rows['cron_logs'];
        $this->assertSame(30, $cron['days']);
        $this->assertSame(3, $cron['floor_days']);
        $this->assertTrue($cron['truncatable']);
        $this->assertSame(1_100_000, $cron['rows']);
        $this->assertSame(900_000, $cron['eligible_rows']);
        $this->assertSame(40_000_000, app(CleanupOverview::class)->reclaimableBytes());
        $this->assertFalse($rows['activity_logs']['truncatable']);
    }

    #[Test]
    public function the_overview_reports_the_last_real_cleaning_not_a_preview(): void
    {
        $preview = CleanupRun::create(['mode' => 'dry_run', 'status' => 'success', 'finished_at' => now()]);
        CleanupRunItem::create(['run_id' => $preview->id, 'target' => 'cron_logs', 'status' => 'success', 'deleted' => 0, 'finished_at' => now()]);

        $this->assertNull(app(CleanupOverview::class)->targets()['cron_logs']['last_status'], 'a preview is not a cleaning');

        $real = CleanupRun::create(['mode' => 'execute', 'status' => 'success', 'finished_at' => now()->subHour()]);
        CleanupRunItem::create(['run_id' => $real->id, 'target' => 'cron_logs', 'status' => 'success', 'deleted' => 321, 'bytes_freed_apparent' => 5000, 'finished_at' => now()->subHour()]);

        $row = app(CleanupOverview::class)->targets()['cron_logs'];
        $this->assertSame('success', $row['last_status']);
        $this->assertSame(321, $row['last_deleted']);
        $this->assertSame(5000, $row['last_bytes']);
    }

    #[Test]
    public function the_overview_disk_block_reports_level_and_growth_since_the_oldest_recent_sample(): void
    {
        app()->instance(DiskUsageService::class, new DiskUsageService('/x', fn () => 1000.0, fn () => 150.0)); // 85% used
        CleanupDiskSample::create(['measured_at' => now()->subDays(10), 'total_bytes' => 1000, 'free_bytes' => 400, 'used_bytes' => 600, 'breakdown' => ['logs' => ['bytes' => 10, 'complete' => true]]]);

        $disk = app(CleanupOverview::class)->disk();

        $this->assertSame(850, $disk['used']);
        $this->assertSame(85.0, $disk['used_pct']);
        $this->assertSame('warn', $disk['level']);
        $this->assertSame(250, $disk['growth_bytes']);
        $this->assertSame(10, $disk['growth_days']);
        $this->assertArrayHasKey('logs', $disk['breakdown']);
    }

    #[Test]
    public function the_overview_makes_a_bounded_number_of_queries_however_many_targets_exist(): void
    {
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });

        app(CleanupOverview::class)->targets();

        $this->assertLessThanOrEqual(12, $count, 'one query for stats, one for last runs, plus settings reads — never one per target row');
    }

    /* ---- file logs ------------------------------------------------------ */

    #[Test]
    public function the_file_log_list_shows_only_plain_log_files_newest_first_with_flags(): void
    {
        $this->log('laravel-2026-09-01.log', 100, 24 * 40);
        $this->log('laravel-2026-10-10.log', 200, 24);
        $this->log('laravel-2026-10-11.log', 300, 0);
        $this->log('notes.txt');
        $this->log('.hidden.log');

        $files = app(FileLogManager::class)->files();

        $this->assertSame(['laravel-2026-10-11.log', 'laravel-2026-10-10.log', 'laravel-2026-09-01.log'], array_column($files, 'name'));
        $this->assertTrue($files[0]['active']);
        $this->assertFalse($files[0]['deletable']);
        $this->assertTrue($files[2]['deletable']);
    }

    #[Test]
    public function chosen_log_files_are_deleted_verified_and_audited_once(): void
    {
        $this->log('a.log', 1000);
        $this->log('b.log', 2000);
        $today = $this->log('today.log', 50, 0);

        $result = app(FileLogManager::class)->delete(['a.log', 'b.log', 'today.log'], null);

        $this->assertSame(2, $result['deleted']);
        $this->assertSame(3000, $result['bytes']);
        $this->assertSame(['today.log' => 'too_new'], $result['failed'], 'a file written in the last hour cannot be deleted');
        $this->assertFileDoesNotExist($this->dir.'/a.log');
        $this->assertFileExists($today);
        $this->assertSame(1, ActivityLog::where('action', 'cleanup.file_delete')->count());
        $this->assertSame(['a.log', 'b.log'], ActivityLog::where('action', 'cleanup.file_delete')->first()->new_values['files']);
    }

    #[Test]
    public function an_active_log_can_be_emptied_in_place(): void
    {
        $active = $this->log('laravel.log', 5000, 0);
        $writer = fopen($active, 'ab');

        $result = app(FileLogManager::class)->truncate(['laravel.log']);

        $this->assertSame(1, $result['deleted']);
        $this->assertSame(5000, $result['bytes']);
        clearstatcache();
        $this->assertSame(0, filesize($active));
        fwrite($writer, "still logging\n");
        fclose($writer);
        clearstatcache();
        $this->assertGreaterThan(0, filesize($active));
    }

    #[Test]
    public function names_that_are_not_plain_log_files_are_refused_before_touching_the_disk(): void
    {
        $outside = $this->dir.'-root/secret.log';
        file_put_contents($outside, 'keep');
        touch($outside, time() - 72 * 3600);
        $this->log('data.sqlite');

        $result = app(FileLogManager::class)->delete(['../'.basename($this->dir).'-root/secret.log', 'a/b.log', '.env', '.hidden.log', 'data.sqlite', "x\0.log"]);

        $this->assertSame(0, $result['deleted']);
        $this->assertCount(6, $result['failed']);
        $this->assertFileExists($outside);
        $this->assertFileExists($this->dir.'/data.sqlite');
    }
}
