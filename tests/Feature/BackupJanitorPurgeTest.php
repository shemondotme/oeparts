<?php

namespace Tests\Feature;

use App\Models\BackupRun;
use App\Services\Backup\BackupJanitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * BackupJanitor::purgeFiles — the shared "delete everything a backup run left on
 * disk" routine (used by the stale-run janitor, GFS retention and the manual
 * Delete action).
 */
class BackupJanitorPurgeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_also_purges_the_run_directory_on_a_separate_staging_disk(): void
    {
        // Destination (e.g. off-site) and staging (always local-driver) are different.
        Storage::fake('dest');
        Storage::fake('stage');
        config(['backup.staging_disk' => 'stage']);

        $run = BackupRun::create([
            'profile' => 'full', 'status' => BackupRun::STATUS_FAILED, 'trigger' => 'manual',
            'disk' => 'dest',
        ]);
        $dir = 'backups/'.$run->getKey();

        Storage::disk('dest')->put($dir.'/db-1.enc', 'encrypted');
        // Working files that exist ONLY on the staging disk, never registered as a part.
        Storage::disk('stage')->put($dir.'/_work/tables.ndjson', 'plaintext customer rows');
        Storage::disk('stage')->put($dir.'/db-2.sql', 'plaintext dump');

        $ok = app(BackupJanitor::class)->purgeFiles($run);

        $this->assertTrue($ok);
        Storage::disk('dest')->assertMissing($dir.'/db-1.enc');
        Storage::disk('stage')->assertMissing($dir.'/_work/tables.ndjson');
        Storage::disk('stage')->assertMissing($dir.'/db-2.sql');
        $this->assertFalse(Storage::disk('stage')->exists($dir), 'the whole run directory should be gone from staging');
    }

    #[Test]
    public function it_still_works_when_destination_and_staging_are_the_same_disk(): void
    {
        Storage::fake('local');
        config(['backup.staging_disk' => 'local']);

        $run = BackupRun::create([
            'profile' => 'full', 'status' => BackupRun::STATUS_FAILED, 'trigger' => 'manual',
            'disk' => 'local',
        ]);
        $dir = 'backups/'.$run->getKey();
        Storage::disk('local')->put($dir.'/db-1.enc', 'x');

        $this->assertTrue(app(BackupJanitor::class)->purgeFiles($run));
        $this->assertFalse(Storage::disk('local')->exists($dir));
    }

    #[Test]
    public function it_leaves_other_runs_directories_alone(): void
    {
        Storage::fake('local');
        config(['backup.staging_disk' => 'local']);

        $run = BackupRun::create(['profile' => 'full', 'status' => BackupRun::STATUS_FAILED, 'trigger' => 'manual', 'disk' => 'local']);
        $other = BackupRun::create(['profile' => 'full', 'status' => BackupRun::STATUS_SUCCESS, 'trigger' => 'manual', 'disk' => 'local']);
        Storage::disk('local')->put('backups/'.$run->getKey().'/a', 'x');
        Storage::disk('local')->put('backups/'.$other->getKey().'/b', 'keep');

        app(BackupJanitor::class)->purgeFiles($run);

        Storage::disk('local')->assertExists('backups/'.$other->getKey().'/b');
    }
}
