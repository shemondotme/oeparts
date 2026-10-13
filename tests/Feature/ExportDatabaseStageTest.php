<?php

namespace Tests\Feature;

use App\Models\BackupRun;
use App\Services\Migration\Export\ExportOptions;
use App\Services\Migration\Export\ExportPlan;
use App\Services\Migration\Export\SecretsStaging;
use App\Services\Migration\Groups\GroupKey;
use App\Services\Migration\Package\BoundedInflater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DrivesExportStage;
use Tests\TestCase;

/**
 * Phase 3.9: the database stage. Rows come out complete, in order, exactly once, however often the
 * process is killed; the checkpoint stays small; secrets and maintenance never leave as they are.
 */
class ExportDatabaseStageTest extends TestCase
{
    use DrivesExportStage;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Its own fake disk, so this test cannot wipe the files of another test running at the same time.
        Storage::fake('exportstage');
        config(['backup.staging_disk' => 'exportstage']);
        // batch_seconds 0: every step does the minimum (one page, or closing one table), so a run takes many steps and resuming is really exercised.
        config(['migration.export.page_rows' => 5, 'migration.export.batch_seconds' => 0]);
    }

    private function types(): void
    {
        Schema::create('zz_types', function ($table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->double('ratio')->nullable();
            $table->boolean('flag')->nullable();
            $table->text('doc')->nullable();
            $table->binary('raw')->nullable();
            $table->dateTime('happened_at')->nullable();
            $table->unsignedBigInteger('doubled')->virtualAs('id * 2')->nullable();
        });
    }

    // ---- what comes out ------------------------------------------------------------------------------

    #[Test]
    public function every_kind_of_cell_survives_exactly(): void
    {
        $this->types();
        DB::table('zz_types')->insert([
            ['id' => 1, 'name' => 'Brake pad ü 日本', 'price' => '12.5', 'ratio' => 0.1, 'flag' => 1, 'doc' => '{"a":[1,2]}', 'raw' => "\x00\x01\xFF", 'happened_at' => '2026-03-01 10:00:00'],
            ['id' => 2, 'name' => null, 'price' => null, 'ratio' => null, 'flag' => null, 'doc' => null, 'raw' => null, 'happened_at' => null],
            ['id' => 3, 'name' => '', 'price' => '0', 'ratio' => 2.0, 'flag' => 0, 'doc' => '', 'raw' => '', 'happened_at' => '2026-12-31 23:59:59'],
            ['id' => 4, 'name' => "bad \xC3\x28 bytes", 'price' => '99999999.99', 'ratio' => 1.5e-9, 'flag' => 1, 'doc' => "x\ny", 'raw' => 'plain text in a blob', 'happened_at' => null],
        ]);

        $run = $this->run_($this->planOf(['zz_types']));
        $this->drive($run);
        $export = $this->exported($run, 'zz_types');

        $this->assertSame(['id', 'name', 'price', 'ratio', 'flag', 'doc', 'raw', 'happened_at'], $export['headers'][0]['columns'], 'the generated column is not exported');
        $this->assertSame(['int', 'string', 'decimal', 'float', 'int', 'string', 'binary', 'datetime'], $export['headers'][0]['types']);
        $this->assertSame('oe-rows/1', $export['headers'][0]['$']);
        $this->assertSame(['id'], $export['headers'][0]['pk']);

        $this->assertSame([1, 'Brake pad ü 日本', '12.5', 0.1, 1, '{"a":[1,2]}', ['$b' => base64_encode("\x00\x01\xFF")], '2026-03-01 10:00:00'], $export['rows'][0]);
        $this->assertSame([2, null, null, null, null, null, null, null], $export['rows'][1], 'NULL stays NULL');
        $this->assertSame([3, '', '0', 2.0, 0, '', ['$b' => ''], '2026-12-31 23:59:59'], $export['rows'][2], 'empty string and zero are not NULL');
        $this->assertSame([4, ['$b' => base64_encode("bad \xC3\x28 bytes")], '99999999.99', 1.5e-9, 1, "x\ny", ['$b' => base64_encode('plain text in a blob')], null], $export['rows'][3], 'invalid UTF-8 and blobs are base64, never dropped');
    }

    #[Test]
    public function a_table_comes_out_complete_once_and_in_key_order_across_many_pages_and_segments(): void
    {
        $this->types();
        $ids = range(1, 53);
        shuffle($ids);
        foreach ($ids as $id) {
            DB::table('zz_types')->insert(['id' => $id, 'name' => "row {$id}"]);
        }
        config(['migration.export.segment_bytes' => 200]); // forces several segments

        $run = $this->run_($this->planOf(['zz_types']));
        $this->drive($run);

        $export = $this->exported($run, 'zz_types');

        $this->assertSame(range(1, 53), array_column($export['rows'], 0));
        $this->assertGreaterThan(2, count($export['headers']), 'small segments: several parts');
        $this->assertSame(range(0, count($export['headers']) - 1), $run->parts()->get()->sortBy('name')->pluck('meta.segment')->values()->all());
        $this->assertSame(count($export['rows']), (int) $run->parts()->sum('rows'));
    }

    #[Test]
    public function an_empty_table_still_produces_a_header_only_part_so_the_target_knows_to_empty_it(): void
    {
        $this->types();

        $run = $this->run_($this->planOf(['zz_types']));
        $this->drive($run);
        $export = $this->exported($run, 'zz_types');

        $this->assertCount(1, $export['headers']);
        $this->assertSame([], $export['rows']);
        $this->assertSame(0, $run->parts()->first()->rows);
    }

    #[Test]
    public function the_decompressed_length_of_every_part_is_recorded_for_the_safe_inflater(): void
    {
        $this->types();
        foreach (range(1, 12) as $id) {
            DB::table('zz_types')->insert(['id' => $id, 'name' => str_repeat('x', 100)]);
        }

        $run = $this->run_($this->planOf(['zz_types']));
        $this->drive($run);
        $part = $run->parts()->first();

        $compressed = (string) Storage::disk('exportstage')->get($part->path);

        $this->assertSame('gzip', $part->meta['codec']);
        $this->assertSame($part->meta['raw_len'], strlen((new BoundedInflater($part->meta['raw_len']))->inflateString($compressed)), 'the recorded length is the length of ALL the members');
        $this->assertLessThan($part->meta['raw_len'], strlen((string) gzdecode($compressed)), 'gzdecode sees only the first member (the header): the very reason the length is recorded');
    }

    // ---- resume --------------------------------------------------------------------------------------

    #[Test]
    public function a_stage_killed_after_every_single_step_and_leaving_a_torn_append_still_produces_the_identical_export(): void
    {
        $this->types();
        foreach (range(1, 31) as $id) {
            DB::table('zz_types')->insert(['id' => $id, 'name' => "row {$id}", 'price' => '1.10']);
        }
        config(['migration.export.segment_bytes' => 300]);

        $clean = $this->run_($this->planOf(['zz_types']));
        $this->drive($clean);
        $expected = $this->exported($clean, 'zz_types')['rows'];

        $crashy = $this->run_($this->planOf(['zz_types']));
        $this->drive($crashy, null, function (int $step, array $state) use ($crashy): void {
            // A new process every step, and the previous one died half way through an append: garbage after the committed bytes.
            if (isset($state['seg']) && $state['seg'] !== null) {
                $path = Storage::disk('exportstage')->path(sprintf('backups/%d/export/db/zz_types.%04d.ndjson.gz', $crashy->id, $state['seg']['n']));
                file_put_contents($path, "\x1f\x8b\x08TORN-APPEND-GARBAGE", FILE_APPEND);
            }
        });

        $this->assertSame($expected, $this->exported($crashy, 'zz_types')['rows']);
        $this->assertSame($clean->parts()->count(), $crashy->parts()->count());
    }

    #[Test]
    public function a_step_that_died_after_sealing_a_segment_but_before_the_checkpoint_does_not_register_the_part_twice(): void
    {
        $this->types();
        foreach (range(1, 20) as $id) {
            DB::table('zz_types')->insert(['id' => $id, 'name' => str_repeat('n', 60)]);
        }
        config(['migration.export.segment_bytes' => 150, 'migration.export.batch_seconds' => 0.001]);

        $run = $this->run_($this->planOf(['zz_types']));
        $stage = $this->stage();

        $state = [];
        $result = $stage->step($run, $state); // work happens, possibly sealing a segment
        $this->register($run, $result);
        $registered = $run->parts()->count();

        // "Crash": the engine never saved the checkpoint, so the same step runs again from the OLD state.
        $again = $this->stage()->step($run, $state);
        $this->register($run, $again);

        $paths = $run->parts()->pluck('path')->all();
        $this->assertSame(count($paths), count(array_unique($paths)), 'no segment is registered twice');
        $this->assertGreaterThanOrEqual($registered, count($paths));
    }

    #[Test]
    public function the_checkpoint_stays_small_however_many_rows_a_table_has(): void
    {
        $this->types();
        config(['migration.export.page_rows' => 500, 'migration.export.segment_bytes' => 5_000_000]);

        $rows = fn (int $from, int $count): array => array_map(fn (int $i): array => ['id' => $i, 'name' => "row {$i}"], range($from, $from + $count - 1));
        foreach (array_chunk($rows(1, 20_000), 500) as $chunk) {
            DB::table('zz_types')->insert($chunk);
        }

        $small = $this->run_($this->planOf(['zz_types']));
        DB::table('zz_types')->where('id', '>', 1000)->delete();
        $smallSizes = $this->drive($small)['state_sizes'];

        DB::table('zz_types')->insert($rows(1001, 19_000));
        $large = $this->run_($this->planOf(['zz_types']));
        $largeSizes = $this->drive($large)['state_sizes'];

        $this->assertLessThan(max($smallSizes) * 1.5, max($largeSizes), 'twenty times the rows must not make the checkpoint bigger');
        $this->assertLessThan(2000, max($largeSizes), 'a few hundred bytes: a key and some counters');
    }

    // ---- consistency ---------------------------------------------------------------------------------

    #[Test]
    public function rows_added_after_the_table_started_are_not_chased_thanks_to_the_high_water_mark(): void
    {
        $this->types();
        foreach (range(1, 12) as $id) {
            DB::table('zz_types')->insert(['id' => $id, 'name' => 'early']);
        }
        config(['migration.export.page_rows' => 3]);

        $added = false;
        $run = $this->run_($this->planOf(['zz_types']));
        $this->drive($run, null, function (int $step) use (&$added): void {
            if ($step === 1 && ! $added) {
                DB::table('zz_types')->insert(['id' => 13, 'name' => 'late']);
                DB::table('zz_types')->insert(['id' => 14, 'name' => 'later']);
                $added = true;
            }
        });

        $this->assertTrue($added);
        $this->assertSame(range(1, 12), array_column($this->exported($run, 'zz_types')['rows'], 0));
    }

    #[Test]
    public function a_composite_key_table_is_read_completely(): void
    {
        $rows = [];
        foreach ([1, 2, 3] as $role) {
            foreach ([10, 20] as $model) {
                $rows[] = ['role_id' => $role, 'model_type' => 'App\\Models\\Admin', 'model_id' => $model];
            }
        }
        $permission = DB::table('roles')->insertGetId(['name' => 'x', 'guard_name' => 'admin', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([1, 2, 3] as $i) {
            DB::table('roles')->insertOrIgnore(['id' => $i, 'name' => "r{$i}", 'guard_name' => 'admin', 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('model_has_roles')->insert($rows);

        $run = $this->run_($this->planOf(['model_has_roles']));
        $this->drive($run);

        $export = $this->exported($run, 'model_has_roles');
        $this->assertCount(6, $export['rows']);
        $this->assertGreaterThan(0, $permission);
    }

    // ---- the plan's rules reach the rows --------------------------------------------------------------

    #[Test]
    public function the_shared_seo_table_is_cut_by_the_slice_in_the_plan(): void
    {
        $now = now();
        DB::table('seo_meta')->insert([
            ['metable_type' => 'App\\Models\\Product', 'metable_id' => 1, 'meta_title' => 'P1', 'created_at' => $now, 'updated_at' => $now],
            ['metable_type' => 'App\\Models\\Page', 'metable_id' => 1, 'meta_title' => 'G1', 'created_at' => $now, 'updated_at' => $now],
            ['metable_type' => 'App\\Models\\BlogPost', 'metable_id' => 1, 'meta_title' => 'B1', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $titles = function (string $slice): array {
            $run = $this->run_($this->planOf(['seo_meta'], ['seo_slice' => $slice]));
            $this->drive($run);
            $export = $this->exported($run, 'seo_meta');
            $position = array_search('meta_title', $export['headers'][0]['columns'], true);

            return array_column($export['rows'], $position);
        };

        $this->assertSame(['P1'], $titles(ExportPlan::SEO_PRODUCT));
        $this->assertEqualsCanonicalizing(['G1', 'B1'], $titles(ExportPlan::SEO_OTHER));
        $this->assertCount(3, $titles(ExportPlan::SEO_ALL));
    }

    #[Test]
    public function settings_maintenance_credentials_numbering_and_cut_references_are_applied_to_the_rows(): void
    {
        $now = now();
        DB::table('settings')->insert([
            ['group' => 'maintenance', 'key' => 'enabled', 'value' => '1', 'type' => 'boolean', 'is_encrypted' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'email', 'key' => 'smtp_password', 'value' => Crypt::encryptString('hunter2'), 'type' => 'encrypted', 'is_encrypted' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'general', 'key' => 'site_name', 'value' => 'Shop', 'type' => 'string', 'is_encrypted' => 0, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('sequences')->insert(['type' => 'order', 'current_value' => 16, 'resets_monthly' => 1, 'last_reset_month' => '2026-10', 'created_at' => $now, 'updated_at' => $now]);

        $plan = $this->realPlan([GroupKey::Catalog], new ExportOptions(resetSequences: true));
        $plan = $this->planOf(['settings', 'sequences'], ['reset_sequences' => true]);
        $run = $this->run_($plan);
        $this->drive($run);

        $settings = $this->exported($run, 'settings');
        $columns = $settings['headers'][0]['columns'];
        $byKey = [];
        foreach ($settings['rows'] as $row) {
            $byKey[$row[array_search('group', $columns, true)].'.'.$row[array_search('key', $columns, true)]] = $row[array_search('value', $columns, true)];
        }

        $this->assertSame('0', $byKey['maintenance.enabled']);
        $this->assertSame('', $byKey['email.smtp_password']);
        $this->assertSame('Shop', $byKey['general.site_name']);
        $this->assertStringNotContainsString('hunter2', (string) json_encode($settings));
        $this->assertSame(['email.smtp_password'], $run->meta['export_report']['secrets']['configured_keys']);

        $sequences = $this->exported($run, 'sequences');
        $seqColumns = $sequences['headers'][0]['columns'];
        $this->assertSame(0, $sequences['rows'][0][array_search('current_value', $seqColumns, true)]);
        $this->assertNull($sequences['rows'][0][array_search('last_reset_month', $seqColumns, true)]);
    }

    #[Test]
    public function included_secrets_are_staged_sealed_under_the_secrets_key_and_never_appear_in_the_rows(): void
    {
        $now = now();
        DB::table('settings')->insert([
            ['group' => 'email', 'key' => 'smtp_password', 'value' => Crypt::encryptString('hunter2'), 'type' => 'encrypted', 'is_encrypted' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'payment', 'key' => 'broken', 'value' => 'not-ciphertext', 'type' => 'encrypted', 'is_encrypted' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
        $key = str_repeat('K', 32);

        $plan = $this->planOf(['settings'], ['include_secrets' => true]);
        $run = $this->run_($plan);
        $this->drive($run, $this->stage($key));

        $path = Storage::disk('exportstage')->path(sprintf('backups/%d/export/secrets.staging', $run->id));
        $this->assertFileExists($path);
        $this->assertStringNotContainsString('hunter2', (string) file_get_contents($path), 'the staging file holds no plaintext');

        $staged = iterator_to_array((new SecretsStaging($path, $key))->read(), false);
        $this->assertSame([['group' => 'email', 'key' => 'smtp_password', 'value' => 'hunter2']], $staged);

        $this->assertStringNotContainsString('hunter2', (string) json_encode($this->exported($run, 'settings')));
        $report = $run->meta['export_report']['secrets'];
        $this->assertSame(['email.smtp_password'], $report['included_keys']);
        $this->assertSame(['payment.broken'], $report['undecryptable_keys']);
    }

    #[Test]
    public function a_run_without_a_plan_is_refused(): void
    {
        $run = BackupRun::create(['profile' => BackupRun::PROFILE_EXPORT, 'status' => BackupRun::STATUS_RUNNING, 'trigger' => 'manual', 'disk' => 'local']);

        $this->expectException(\RuntimeException::class);
        $this->stage()->step($run, []);
    }
}
