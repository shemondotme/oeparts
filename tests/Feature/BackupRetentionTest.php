<?php

namespace Tests\Feature;

use App\Models\BackupRun;
use App\Services\Backup\BackupRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * GFS backup retention (Module 21, Chunk 2.6). Keeps the newest successful
 * backup per day/week/month and reclaims the rest's files.
 */
class BackupRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function makeRun(\Illuminate\Support\Carbon $finishedAt): BackupRun
    {
        $run = BackupRun::create([
            'profile'     => BackupRun::PROFILE_FULL,
            'status'      => BackupRun::STATUS_SUCCESS,
            'trigger'     => BackupRun::TRIGGER_SCHEDULED,
            'disk'        => 'local',
            'started_at'  => $finishedAt,
            'finished_at' => $finishedAt,
        ]);

        $path = 'backups/'.$run->id.'/db/part.sql.gz.enc';
        Storage::disk('local')->put($path, 'data');
        $run->parts()->create([
            'type' => 'db', 'sequence' => 0, 'disk' => 'local', 'path' => $path, 'bytes' => 4,
        ]);

        return $run;
    }

    #[Test]
    public function it_keeps_recent_days_and_prunes_older_backups(): void
    {
        config(['backup.retention' => ['daily' => 3, 'weekly' => 0, 'monthly' => 0]]);

        $runs = [];
        for ($i = 0; $i < 5; $i++) {
            $runs[$i] = $this->makeRun(now()->subDays($i)->setTime(12, 0));
        }

        $result = app(BackupRetentionService::class)->prune();

        $this->assertSame(3, $result['kept']);
        $this->assertSame(2, $result['pruned']);

        // 3 newest kept (files intact, no pruned flag); 2 oldest pruned (files gone).
        foreach ([0, 1, 2] as $keptIndex) {
            $runs[$keptIndex]->refresh();
            $this->assertNull($runs[$keptIndex]->meta['pruned_at'] ?? null);
            Storage::disk('local')->assertExists($runs[$keptIndex]->parts()->first()->path);
        }
        foreach ([3, 4] as $prunedIndex) {
            $runs[$prunedIndex]->refresh();
            $this->assertNotNull($runs[$prunedIndex]->meta['pruned_at'] ?? null);
            Storage::disk('local')->assertMissing('backups/'.$runs[$prunedIndex]->id.'/db/part.sql.gz.enc');
        }
    }

    #[Test]
    public function it_keeps_only_the_newest_backup_of_a_day(): void
    {
        config(['backup.retention' => ['daily' => 1, 'weekly' => 0, 'monthly' => 0]]);

        $newest = $this->makeRun(now()->setTime(15, 0));
        $older  = $this->makeRun(now()->setTime(9, 0));

        $result = app(BackupRetentionService::class)->prune();

        $this->assertSame(1, $result['kept']);
        $this->assertSame(1, $result['pruned']);
        $this->assertNull($newest->refresh()->meta['pruned_at'] ?? null);
        $this->assertNotNull($older->refresh()->meta['pruned_at'] ?? null);
    }

    #[Test]
    public function pruning_is_idempotent(): void
    {
        config(['backup.retention' => ['daily' => 1, 'weekly' => 0, 'monthly' => 0]]);
        $this->makeRun(now()->setTime(15, 0));
        $this->makeRun(now()->subDay()->setTime(15, 0));

        app(BackupRetentionService::class)->prune();
        $second = app(BackupRetentionService::class)->prune(); // already-pruned runs are skipped

        $this->assertSame(0, $second['pruned']);
    }
}
