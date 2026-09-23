<?php

namespace Tests\Feature;

use App\Jobs\NotifyAdminsOfUpdateResult;
use App\Models\UpdateHistory;
use App\Services\Backup\BackupLock;
use App\Services\SettingsService;
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

    private string $rootPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->statePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-update-cleanupcmd-state-'.getmypid();
        @mkdir($this->statePath, 0775, true);
        config(['updates.state_path' => $this->statePath]);

        // See FakeUpdateApplier's own comment on isGitMode() for why this is
        // not optional: reclaimStale() now routes through the REAL
        // UpdateApplier::fail(), which — for a row past the destructive-
        // phase boundary — calls rollback(), which checks
        // GitUpdater::isGitManaged() against config('updates.root_path') ?:
        // base_path(). Without an explicit, .git-free root_path here, that
        // resolves to base_path() — a real git checkout in dev/CI — and a
        // test can end up running real `git checkout`/`composer install`
        // against the actual project. Confirmed live, the hard way, twice.
        $this->rootPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-update-cleanupcmd-root-'.getmypid();
        @mkdir($this->rootPath, 0775, true);
        config(['updates.root_path' => $this->rootPath]);

        Queue::fake();
    }

    protected function tearDown(): void
    {
        @array_map('unlink', glob($this->statePath.DIRECTORY_SEPARATOR.'*') ?: []);
        @rmdir($this->statePath);
        @array_map('unlink', glob($this->rootPath.DIRECTORY_SEPARATOR.'*') ?: []);
        @rmdir($this->rootPath);
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

    /**
     * Pre-Phase-22 backlog sweep (2026-09-24), raised by the user asking
     * whether a genuinely crashed update (not just an abandoned browser tab
     * — the PHP process itself killed, e.g. OOM) leaves the site cleaned up.
     * It didn't: reclaimStale() used to mark the row failed directly,
     * touching maintenance.enabled nowhere at all — a crash mid-update left
     * the site stuck in maintenance mode forever, with nothing (not even
     * this watchdog, 2 hours later) ever turning it back off. Routing
     * through UpdateApplier::fail() instead means a reclaimed row now gets
     * the exact same maintenance-mode handling a normal in-process failure
     * already gets. Uses the 'download' step deliberately — a step BEFORE
     * the destructive-phase boundary, so fail() takes its no-rollback-needed
     * path; a rollback-needed step is deliberately not exercised here (see
     * UpdateApplierTest's own FakeUpdateApplier-based coverage of that path
     * instead — this file has no safe way to fake out the real git/file
     * rollback machinery, and the root_path isolation above is a safety
     * net, not a license to actually invoke it).
     */
    #[Test]
    public function it_turns_maintenance_mode_back_off_when_reclaiming_a_stale_row(): void
    {
        app(SettingsService::class)->set('maintenance.enabled', true);
        $this->createHistory(UpdateHistory::STATUS_DOWNLOADING, now()->subHours(3), initiatedBy: 7);

        $this->artisan('oeparts:update:cleanup-stale')->assertSuccessful();

        $this->assertFalse((bool) settings('maintenance.enabled'), 'a reclaimed row must not leave the site stuck in maintenance mode forever');
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
