<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\BackupRun;
use App\Models\CleanupRun;
use App\Models\CleanupRunItem;
use App\Models\UpdateHistory;
use App\Services\Cleanup\CleanupManager;
use App\Services\Cleanup\Contracts\CleanupTarget;
use App\Services\Cleanup\Exceptions\CleanupException;
use App\Services\Cleanup\TargetRegistry;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The real registry (config/cleanup.php) against the real tables: every definition is valid, and each
 * special rule (keep-newest, terminal-only, read-only, expired-only, protected backups) does exactly what
 * it says.
 */
class CleanupTargetsTest extends TestCase
{
    use RefreshDatabase;

    private string $state;

    protected function setUp(): void
    {
        parent::setUp();

        $this->state = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-cleanuptargets-'.getmypid();
        @mkdir($this->state, 0775, true);
        // root_path: GitUpdater (the dev-files target) falls back to base_path() — the REAL checkout — when this is
        // unset. Isolate it, as every test touching the update system must.
        config(['updates.state_path' => $this->state, 'updates.root_path' => $this->state.'-root', 'cleanup.chunk_rows' => 200, 'cleanup.sleep_ms' => 0]);
        @mkdir($this->state.'-root', 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->state.'/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->state);
        @rmdir($this->state.'-root');
        parent::tearDown();
    }

    private function clean(string $key, array $options = []): CleanupRun
    {
        $manager = app(CleanupManager::class);

        return $manager->run($manager->start([$key], CleanupRun::MODE_EXECUTE, CleanupRun::TRIGGER_MANUAL, null, $options), 120, 10);
    }

    private function old(int $days = 400): string
    {
        return now()->subDays($days)->toDateTimeString();
    }

    /* ---- the registry itself ------------------------------------------- */

    #[Test]
    public function every_configured_target_is_valid(): void
    {
        $registry = app(TargetRegistry::class);
        $targets = $registry->all();

        $this->assertGreaterThanOrEqual(23, count($targets));

        foreach ($targets as $key => $target) {
            $this->assertSame($key, $target->key());
            $this->assertNotSame('', $target->label(), $key);
            $this->assertNotSame('', $target->description(), "{$key} needs a description for the admin");
            $this->assertLessThanOrEqual($target->defaultDays(), max($target->floorDays(), 0) ?: 0, "{$key}: the floor cannot exceed the default");
            $this->assertContains($target->group(), ['logs', 'history', 'system', 'files', 'backups'], $key);
            $this->assertContains($target->kind(), ['db', 'file', 'callable'], $key);
        }
    }

    #[Test]
    public function every_target_points_at_a_real_table_and_column_and_is_not_a_business_table(): void
    {
        $definitions = array_filter((array) config('cleanup.targets'), fn ($d) => is_array($d) && ($d['type'] ?? 'table') === 'table');

        $this->assertNotEmpty($definitions);

        foreach ($definitions as $def) {
            $this->assertTrue(Schema::hasTable($def['table']), "{$def['key']}: table {$def['table']} must exist");
            $this->assertTrue(Schema::hasColumn($def['table'], $def['column']), "{$def['key']}: {$def['table']}.{$def['column']} must exist");
            $this->assertNotContains($def['table'], TargetRegistry::NEVER_PRUNE, "{$def['key']} is a business table");
        }
    }

    #[Test]
    public function audit_trails_and_lock_bearing_tables_can_never_be_wiped_all_at_once(): void
    {
        $registry = app(TargetRegistry::class);

        foreach (['activity_logs', 'login_logs', 'email_logs', 'update_histories', 'backup_history', 'product_import_runs', 'failed_jobs', 'cleanup_history', 'notifications', 'bulk_update_logs'] as $key) {
            $this->assertFalse($registry->get($key)->truncatable(), "{$key} must not be truncatable");
        }

        foreach (['search_logs', 'failed_search_logs', 'cron_logs', 'indexnow_push_logs', 'cache_metric_snapshots', 'core_web_vitals_snapshots', 'not_found_log_snapshots'] as $key) {
            $this->assertTrue($registry->get($key)->truncatable(), "{$key} is plain telemetry");
        }
    }

    #[Test]
    public function the_seeder_creates_a_retention_and_an_auto_row_for_every_target(): void
    {
        $this->seed(SettingsSeeder::class);

        foreach (app(TargetRegistry::class)->all() as $key => $target) {
            $this->assertDatabaseHas('settings', ['group' => 'cleanup', 'key' => "retention_{$key}", 'value' => (string) $target->defaultDays()]);
            $this->assertDatabaseHas('settings', ['group' => 'cleanup', 'key' => "auto_{$key}"]);
        }
    }

    #[Test]
    public function a_dry_run_of_every_target_works_on_an_empty_database(): void
    {
        // Exercises every scope and keep-rule's SQL end to end.
        $manager = app(CleanupManager::class);
        $keys = array_keys(app(TargetRegistry::class)->all());

        $run = $manager->run($manager->start($keys, CleanupRun::MODE_DRY_RUN), 120, 10);

        $this->assertSame(CleanupRun::STATUS_SUCCESS, $run->status, (string) $run->error);
        $this->assertSame(count($keys), $run->items()->where('status', CleanupRunItem::STATUS_SUCCESS)->count());
    }

    #[Test]
    public function a_real_run_of_every_target_on_an_empty_database_succeeds_too(): void
    {
        $this->seed(SettingsSeeder::class);
        $manager = app(CleanupManager::class);
        // DATABASE targets only: the file targets' roots are this project's real storage/ directories, and a
        // test must never delete real files.
        $keys = array_keys(array_filter(app(TargetRegistry::class)->all(), fn (CleanupTarget $t) => $t->kind() === 'db'));

        $run = $manager->run($manager->start($keys), 120, 10);

        $this->assertSame(CleanupRun::STATUS_SUCCESS, $run->status, json_encode($run->items()->where('status', '!=', 'success')->get(['target', 'status', 'reasons'])));
    }

    /* ---- keep rules ----------------------------------------------------- */

    #[Test]
    public function health_snapshots_keep_the_newest_20_of_each_check_whatever_their_age(): void
    {
        $rows = [];
        foreach (['database', 'cache'] as $check) {
            for ($i = 0; $i < 30; $i++) {
                $rows[] = ['check_key' => $check, 'status' => 'ok', 'checked_at' => now()->subDays(100 + $i)];
            }
        }
        DB::table('health_check_snapshots')->insert($rows);

        $run = $this->clean('health_check_snapshots');

        $this->assertSame(CleanupRun::STATUS_SUCCESS, $run->status);
        $this->assertSame(20, DB::table('health_check_snapshots')->where('check_key', 'database')->count());
        $this->assertSame(20, DB::table('health_check_snapshots')->where('check_key', 'cache')->count());
        $this->assertSame(20, $run->deleted, '10 + 10 beyond the newest 20 per check');
        // the survivors are the NEWEST ones
        $this->assertSame(0, DB::table('health_check_snapshots')->where('check_key', 'database')->where('checked_at', '<', now()->subDays(121))->count());
    }

    #[Test]
    public function the_newest_12_snapshots_always_stay_even_when_all_are_old(): void
    {
        $rows = [];
        for ($i = 0; $i < 20; $i++) {
            $rows[] = ['hit_rate' => 90, 'memory_used_bytes' => 1, 'recorded_at' => now()->subDays(100 + $i)];
        }
        DB::table('cache_metric_snapshots')->insert($rows);

        $this->clean('cache_metric_snapshots');

        $this->assertSame(12, DB::table('cache_metric_snapshots')->count());
    }

    /* ---- scopes --------------------------------------------------------- */

    #[Test]
    public function only_finished_product_imports_are_pruned(): void
    {
        $admin = Admin::factory()->create();
        $base = ['admin_id' => $admin->id, 'original_filename' => 'a.csv', 'path' => 'imports/a.csv', 'created_at' => now(), 'updated_at' => now()];
        $failed = DB::table('product_import_runs')->insertGetId($base + ['status' => 'failed', 'finished_at' => $this->old()]);
        $success = DB::table('product_import_runs')->insertGetId($base + ['status' => 'success', 'finished_at' => $this->old()]);
        // A 'running' import is also the lock that stops a second one: untouchable even with an old finished_at.
        // (its row was last touched a day ago: an abandoned run does not make the system "busy", it is just never pruned)
        $running = DB::table('product_import_runs')->insertGetId(['updated_at' => now()->subDay()] + $base + ['status' => 'running', 'finished_at' => $this->old()]);
        $recent = DB::table('product_import_runs')->insertGetId($base + ['status' => 'success', 'finished_at' => now()->subDay()]);

        $run = $this->clean('product_import_runs');

        $this->assertSame(CleanupRun::STATUS_SUCCESS, $run->status);
        $remaining = DB::table('product_import_runs')->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$running, $recent], $remaining);
        $this->assertNotContains($failed, $remaining);
        $this->assertNotContains($success, $remaining);
    }

    #[Test]
    public function update_history_keeps_the_newest_20_and_never_an_unfinished_update(): void
    {
        $rows = [];
        for ($i = 0; $i < 25; $i++) {
            $rows[] = ['from_version' => '1', 'to_version' => '2', 'status' => 'success', 'type' => 'update', 'finished_at' => now()->subDays(500 - $i), 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('update_histories')->insert($rows);
        $unfinished = UpdateHistory::create(['from_version' => '2', 'to_version' => '3', 'status' => UpdateHistory::STATUS_SWAPPING, 'finished_at' => now()->subDays(900)]);
        DB::table('update_histories')->where('id', $unfinished->id)->update(['updated_at' => now()->subDay()]); // abandoned, not busy

        $run = $this->clean('update_histories');

        $this->assertSame(5, $run->deleted);
        $this->assertSame(21, DB::table('update_histories')->count(), '20 newest finished + the in-progress one');
        $this->assertTrue(DB::table('update_histories')->where('id', $unfinished->id)->exists());
    }

    #[Test]
    public function backup_history_rows_go_only_when_their_files_are_already_gone_and_nothing_needs_them(): void
    {
        $make = function (string $status, array $meta, int $days = 400, ?int $id = null) {
            $run = BackupRun::create(['profile' => 'full', 'status' => $status, 'trigger' => 'scheduled', 'disk' => 'local', 'meta' => $meta]);
            DB::table('backup_runs')->where('id', $run->id)->update(['created_at' => now()->subDays($days)]);

            return $run->id;
        };

        $pruned = $make('success', ['pruned_at' => now()->subYear()->toIso8601String()]);
        $cleaned = $make('failed', ['cleaned_at' => now()->subYear()->toIso8601String()]);
        $liveBackup = $make('success', []);                       // files still on disk: NOT history yet
        $running = $make('running', ['pruned_at' => 'x']);          // never touch a running run
        DB::table('backup_runs')->where('id', $running)->update(['updated_at' => now()->subDay()]); // abandoned, not busy
        $recentPruned = $make('success', ['pruned_at' => now()->toIso8601String()], 5);
        $referenced = $make('success', ['pruned_at' => now()->subYear()->toIso8601String()]);
        UpdateHistory::create(['from_version' => '1', 'to_version' => '2', 'status' => UpdateHistory::STATUS_SUCCESS, 'backup_run_id' => $referenced]);

        $run = $this->clean('backup_history');

        $this->assertSame(CleanupRun::STATUS_SUCCESS, $run->status);
        $left = DB::table('backup_runs')->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$liveBackup, $running, $recentPruned, $referenced], $left);
        $this->assertNotContains($pruned, $left);
        $this->assertNotContains($cleaned, $left);
    }

    #[Test]
    public function only_read_notifications_older_than_the_retention_are_removed(): void
    {
        $admin = Admin::factory()->create();
        $mk = fn (?string $readAt, string $created) => DB::table('notifications')->insert([
            'id' => (string) Str::uuid(), 'type' => 'x', 'notifiable_type' => Admin::class, 'notifiable_id' => $admin->id,
            'data' => '{"email":"customer@example.com"}', 'read_at' => $readAt, 'created_at' => $created, 'updated_at' => $created,
        ]);
        $mk($this->old(), $this->old());              // read + old      -> goes
        $mk(null, $this->old());                      // UNREAD + old    -> stays (not seen yet)
        $mk($this->old(1), $this->old(1));            // read + recent   -> stays

        $run = $this->clean('notifications', ['archive' => true]); // even with export on, PII notifications are never archived

        $this->assertSame(CleanupRun::STATUS_SUCCESS, $run->status);
        $this->assertSame(1, $run->deleted);
        $this->assertSame(2, DB::table('notifications')->count());
        $this->assertSame(1, DB::table('notifications')->whereNull('read_at')->count());
        $this->assertNull($run->items()->first()->archive_path);
    }

    #[Test]
    public function only_expired_api_tokens_are_removed_and_tokens_without_expiry_are_kept(): void
    {
        $admin = Admin::factory()->create();
        $mk = fn (?string $expires) => DB::table('personal_access_tokens')->insert([
            'tokenable_type' => Admin::class, 'tokenable_id' => $admin->id, 'name' => 't', 'token' => Str::random(64),
            'abilities' => '["*"]', 'expires_at' => $expires, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $mk(now()->subDays(10)->toDateTimeString());  // expired
        $mk(null);                                    // never expires
        $mk(now()->addDays(10)->toDateTimeString());  // still valid

        $this->clean('personal_access_tokens');

        $this->assertSame(2, DB::table('personal_access_tokens')->count());
        $this->assertSame(0, DB::table('personal_access_tokens')->where('expires_at', '<', now())->count());
    }

    #[Test]
    public function cleanup_history_keeps_the_newest_50_runs_and_the_last_success_of_each_target(): void
    {
        $ids = [];
        for ($i = 0; $i < 60; $i++) {
            $ids[] = CleanupRun::create([
                'status' => CleanupRun::STATUS_SUCCESS, 'finished_at' => now()->subDays(400 - $i), 'active_key' => null,
            ])->id;
        }
        // The very first (oldest) run is the only one that ever cleaned 'cron_logs' successfully.
        CleanupRunItem::create(['run_id' => $ids[0], 'target' => 'cron_logs', 'status' => 'success']);

        $run = $this->clean('cleanup_history');

        $this->assertSame(CleanupRun::STATUS_SUCCESS, $run->status);
        $left = CleanupRun::pluck('id')->all();
        $this->assertContains($ids[0], $left, 'the last success of a target is the "last cleaned" fact the page shows');
        foreach (array_slice($ids, 10) as $newest) {
            $this->assertContains($newest, $left, 'the newest 50 stay');
        }
        $this->assertNotContains($ids[1], $left);
    }

    #[Test]
    public function email_logs_are_aged_by_sent_at(): void
    {
        DB::table('email_logs')->insert([
            'to_email' => 'a@x.com', 'subject' => 's', 'template_type' => 'welcome', 'status' => 'success',
            'sent_at' => $this->old(), 'created_at' => null,
        ]);

        $run = $this->clean('email_logs');

        $this->assertSame(1, $run->deleted);
        $this->assertSame(0, DB::table('email_logs')->count());
    }

    #[Test]
    public function truncate_all_is_refused_for_an_audit_trail_and_works_for_telemetry(): void
    {
        $manager = app(CleanupManager::class);

        try {
            $manager->start(['activity_logs'], CleanupRun::MODE_EXECUTE, CleanupRun::TRIGGER_MANUAL, null, ['truncate' => true]);
            $this->fail('the admin activity log must never be wipeable in one click');
        } catch (CleanupException $e) {
            $this->assertStringContainsString('cannot be wiped', $e->getMessage());
        }

        DB::table('cron_logs')->insert(array_map(fn ($i) => ['job_name' => "j{$i}", 'status' => 'success', 'duration_ms' => 1, 'ran_at' => now()->subMinutes(5)], range(1, 450)));

        $run = $this->clean('cron_logs', ['truncate' => true]);

        $this->assertSame(CleanupRun::STATUS_SUCCESS, $run->status);
        $this->assertSame(450, $run->deleted);
        $this->assertSame(0, DB::table('cron_logs')->count());
    }

    /* ---- scale behaviour ------------------------------------------------ */

    #[Test]
    public function work_grows_linearly_with_the_amount_of_data_not_worse(): void
    {
        // Per project convention: never an absolute millisecond ceiling (CI timing varies 50%+); compare
        // the same operation at N and 4N rows within one test, and count statements, which are exact.
        $measure = function (int $rows): array {
            DB::table('cron_logs')->delete();
            $batch = [];
            for ($i = 0; $i < $rows; $i++) {
                $batch[] = ['job_name' => 'j', 'status' => 'success', 'duration_ms' => 1, 'ran_at' => now()->subDays(100)];
            }
            foreach (array_chunk($batch, 500) as $part) {
                DB::table('cron_logs')->insert($part);
            }

            $deletes = 0;
            $selects = 0;
            $maxBindings = 0;
            DB::listen(function ($q) use (&$deletes, &$selects, &$maxBindings) {
                $sql = strtolower($q->sql);
                if (str_starts_with($sql, 'delete from "cron_logs"')) {
                    $deletes++;
                    $maxBindings = max($maxBindings, count($q->bindings));
                } elseif (str_starts_with($sql, 'select') && str_contains($sql, 'cron_logs')) {
                    $selects++;
                }
            });

            $started = microtime(true);
            $run = $this->clean('cron_logs');
            $elapsed = microtime(true) - $started;

            $this->assertSame(CleanupRun::STATUS_SUCCESS, $run->status);
            $this->assertSame($rows, $run->deleted);

            return ['seconds' => $elapsed, 'deletes' => $deletes, 'selects' => $selects, 'bindings' => $maxBindings];
        };

        $small = $measure(1000);
        $large = $measure(4000);

        $this->assertLessThanOrEqual(200, $large['bindings'], 'no statement may touch more ids than the chunk size, however big the table');
        $this->assertSame($small['bindings'], $large['bindings'], 'statement size does not depend on table size');
        $this->assertGreaterThanOrEqual(3.0, $large['deletes'] / max(1, $small['deletes']), 'four times the rows is about four times the chunks');
        $this->assertLessThanOrEqual(5.0, $large['deletes'] / max(1, $small['deletes']), 'not worse than linear (no per-row statements, no quadratic rescans)');
        $this->assertLessThanOrEqual(6.0, $large['selects'] / max(1, $small['selects']), 'counting/selecting grows linearly too');
        $this->assertLessThan(12.0, $large['seconds'] / max(0.001, $small['seconds']), 'time grows roughly linearly (4x data, generous headroom)');
    }
}
