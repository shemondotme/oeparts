<?php

namespace Tests\Unit\Cleanup;

use App\Models\Admin;
use App\Models\BackupRun;
use App\Models\ProductImportRun;
use App\Models\UpdateHistory;
use App\Services\Backup\BackupLock;
use App\Services\Cleanup\SystemBusyGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SystemBusyGuardTest extends TestCase
{
    use RefreshDatabase;

    private string $state;

    protected function setUp(): void
    {
        parent::setUp();

        // Isolate the shared lock / arm-flag directory from the real storage/app/updates.
        $this->state = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-busyguard-'.getmypid();
        @mkdir($this->state, 0775, true);
        config(['updates.state_path' => $this->state]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->state.'/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->state);
        parent::tearDown();
    }

    private function guard(): SystemBusyGuard
    {
        return app(SystemBusyGuard::class);
    }

    #[Test]
    public function an_idle_system_is_not_busy(): void
    {
        $this->assertNull($this->guard()->reason());
        $this->assertFalse($this->guard()->isBusy());
    }

    #[Test]
    public function a_held_backup_lock_makes_it_busy_and_names_the_owner(): void
    {
        app(BackupLock::class)->acquire('backup:7');

        $this->assertStringContainsString('backup:7', (string) $this->guard()->reason());
    }

    #[Test]
    public function an_armed_recovery_window_makes_it_busy(): void
    {
        file_put_contents($this->state.'/arm.flag', '{}');

        $this->assertTrue($this->guard()->isBusy());
    }

    #[Test]
    public function an_unfinished_update_makes_it_busy_but_a_finished_one_does_not(): void
    {
        UpdateHistory::create(['from_version' => '1', 'to_version' => '2', 'status' => UpdateHistory::STATUS_SUCCESS]);
        $this->assertFalse($this->guard()->isBusy());

        UpdateHistory::create(['from_version' => '2', 'to_version' => '3', 'status' => UpdateHistory::STATUS_SWAPPING]);
        $this->assertTrue($this->guard()->isBusy());
    }

    #[Test]
    public function a_running_backup_makes_it_busy_even_without_a_lock_file(): void
    {
        BackupRun::create(['status' => BackupRun::STATUS_RUNNING, 'profile' => 'full', 'trigger' => 'manual']);

        $this->assertTrue($this->guard()->isBusy());
    }

    #[Test]
    public function abandoned_unfinished_rows_do_not_block_cleanup_forever(): void
    {
        // A tab closed mid-import, a killed update worker: the row still says "running" days later.
        // Real work keeps its row moving or holds the lock, so a long-untouched row is not "busy".
        $old = now()->subDays(2);

        $import = ProductImportRun::create([
            'admin_id' => Admin::factory()->create()->id, 'status' => ProductImportRun::STATUS_RUNNING,
            'original_filename' => 'p.csv', 'path' => 'imports/p.csv',
        ]);
        $update = UpdateHistory::create(['from_version' => '1', 'to_version' => '2', 'status' => UpdateHistory::STATUS_SWAPPING]);
        $backup = BackupRun::create(['status' => BackupRun::STATUS_RUNNING, 'profile' => 'full', 'trigger' => 'manual']);

        $this->assertTrue($this->guard()->isBusy(), 'while fresh they DO block');

        foreach (['product_import_runs' => $import->id, 'update_histories' => $update->id, 'backup_runs' => $backup->id] as $table => $id) {
            DB::table($table)->where('id', $id)->update(['updated_at' => $old]);
        }

        $this->assertNull($this->guard()->reason(), 'abandoned rows are for the stale-run reclaim jobs to deal with, not a reason to refuse');
    }

    #[Test]
    public function a_held_lock_still_blocks_even_when_every_row_is_stale(): void
    {
        app(BackupLock::class)->acquire('update:1.2.3');

        $this->assertStringContainsString('update:1.2.3', (string) $this->guard()->reason());
    }

    #[Test]
    public function our_own_lock_does_not_count_as_busy_but_someone_elses_does(): void
    {
        app(BackupLock::class)->acquire('cleanup:7');

        $this->assertNull($this->guard()->reason('cleanup:7'));
        $this->assertNotNull($this->guard()->reason('cleanup:8'));
        $this->assertNotNull($this->guard()->reason());
    }

    #[Test]
    public function a_running_product_import_makes_it_busy(): void
    {
        ProductImportRun::create([
            'admin_id' => Admin::factory()->create()->id,
            'status' => ProductImportRun::STATUS_RUNNING,
            'original_filename' => 'products.csv',
            'path' => 'imports/x.csv',
        ]);

        $this->assertTrue($this->guard()->isBusy());
    }
}
