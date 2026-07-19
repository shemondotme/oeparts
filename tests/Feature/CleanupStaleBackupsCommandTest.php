<?php

namespace Tests\Feature;

use App\Services\Backup\BackupLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * oeparts:backup:cleanup-stale — a thin wrapper around
 * BackupJanitor::cleanupPartials() (already covered by
 * BackupEngineCoreTest's janitor tests), scheduled hourly in
 * routes/console.php so a run abandoned mid-progress (e.g. an admin
 * navigates away while "Run backup now" is still polling) no longer sits
 * holding the shared lock forever, silently blocking every future backup
 * AND update until an operator notices and manually intervenes.
 */
class CleanupStaleBackupsCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $statePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->statePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-cleanupcmd-state-'.getmypid();
        @mkdir($this->statePath, 0775, true);
        config(['updates.state_path' => $this->statePath]);
    }

    protected function tearDown(): void
    {
        @array_map('unlink', glob($this->statePath.DIRECTORY_SEPARATOR.'*') ?: []);
        @rmdir($this->statePath);
        parent::tearDown();
    }

    #[Test]
    public function it_releases_a_stale_lock_left_by_an_abandoned_run(): void
    {
        $lock = app(BackupLock::class);
        file_put_contents($lock->path(), json_encode([
            'owner'       => 'backup:dead',
            'acquired_at' => now()->subHours(2)->toIso8601String(),
        ]));
        $this->assertTrue($lock->isLocked());

        $this->artisan('oeparts:backup:cleanup-stale')->assertSuccessful();

        $this->assertFalse($lock->isLocked(), 'the stale lock should be released');
    }

    #[Test]
    public function it_leaves_a_fresh_lock_alone(): void
    {
        $lock = app(BackupLock::class);
        $lock->acquire('backup:live');

        $this->artisan('oeparts:backup:cleanup-stale')->assertSuccessful();

        $this->assertTrue($lock->isLocked(), 'a live lock must not be reaped');
    }
}
