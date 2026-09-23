<?php

namespace Tests\Feature;

use App\Jobs\NotifyAdminsOfUpdateResult;
use App\Models\UpdateHistory;
use App\Services\Backup\BackupLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * oeparts:update:cleanup-stale — mirrors CleanupStaleBackupsCommandTest for
 * the update side: a poll-driven apply abandoned mid-way (tab closed) sat
 * non-terminal forever with nothing to reclaim it or tell an admin.
 */
class CleanupStaleUpdatesCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $statePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->statePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-update-cleanupcmd-state-'.getmypid();
        @mkdir($this->statePath, 0775, true);
        config(['updates.state_path' => $this->statePath]);

        Queue::fake();
    }

    protected function tearDown(): void
    {
        @array_map('unlink', glob($this->statePath.DIRECTORY_SEPARATOR.'*') ?: []);
        @rmdir($this->statePath);
        parent::tearDown();
    }

    private function createHistory(string $status, \DateTimeInterface $updatedAt, ?int $initiatedBy = null): UpdateHistory
    {
        $history = UpdateHistory::create([
            'from_version' => '1.0.0',
            'to_version' => '1.1.0',
            'channel' => 'stable',
            'status' => $status,
            'step' => 'download',
            'initiated_by' => $initiatedBy,
            'started_at' => $updatedAt,
            'meta' => ['step_index' => 1],
        ]);

        DB::table('update_histories')->where('id', $history->id)->update(['updated_at' => $updatedAt]);

        return $history->refresh();
    }

    #[Test]
    public function it_reclaims_a_row_stuck_past_the_stale_threshold(): void
    {
        $stale = $this->createHistory(UpdateHistory::STATUS_DOWNLOADING, now()->subHours(3), initiatedBy: 7);

        $this->artisan('oeparts:update:cleanup-stale')->assertSuccessful();

        $this->assertSame(UpdateHistory::STATUS_FAILED, $stale->refresh()->status);
        $this->assertNotNull($stale->finished_at);
        $this->assertStringContainsString('Reclaimed', $stale->error);

        Queue::assertPushed(NotifyAdminsOfUpdateResult::class, fn ($job) => $job->result['trigger'] === 'manual' && $job->result['success'] === false
        );
    }

    #[Test]
    public function it_leaves_a_row_still_advancing_alone(): void
    {
        // updated_at recent (a poll just ran), even though started_at is old
        // — must not be falsely reclaimed just because the OVERALL update is
        // taking a while.
        $advancing = $this->createHistory(UpdateHistory::STATUS_SWAPPING, now()->subMinutes(5));
        DB::table('update_histories')->where('id', $advancing->id)->update(['started_at' => now()->subHours(3)]);

        $this->artisan('oeparts:update:cleanup-stale')->assertSuccessful();

        $this->assertSame(UpdateHistory::STATUS_SWAPPING, $advancing->refresh()->status);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_leaves_terminal_rows_alone(): void
    {
        $done = $this->createHistory(UpdateHistory::STATUS_SUCCESS, now()->subHours(5));

        $this->artisan('oeparts:update:cleanup-stale')->assertSuccessful();

        $this->assertSame(UpdateHistory::STATUS_SUCCESS, $done->refresh()->status);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_releases_a_stale_shared_lock(): void
    {
        $lock = app(BackupLock::class);
        file_put_contents($lock->path(), json_encode([
            'owner' => 'update:1.1.0',
            'acquired_at' => now()->subHours(3)->toIso8601String(),
        ]));
        $this->assertTrue($lock->isLocked());

        $this->artisan('oeparts:update:cleanup-stale')->assertSuccessful();

        $this->assertFalse($lock->isLocked(), 'the stale lock should be released');
    }
}
