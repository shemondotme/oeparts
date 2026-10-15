<?php

namespace Tests\Feature\Migration;

use App\Security\PackageAudit;
use App\Services\Backup\BackupLock;
use App\Services\Install\InstallManager;
use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStatus;
use App\Services\Migration\Import\Stages\CompleteStage;
use App\Services\Migration\KeyEscrow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DrivesInstallerRuns;
use Tests\TestCase;

/**
 * When an import that began in the installer is complete, the installer's wizard file (the browser's choices; no secret, but it
 * names the browser that owned the branch) is removed together with the other leftovers, before `installed.lock` is written.
 * Any other door leaves a wizard alone: it is somebody else's.
 *
 * The wizard file, the marker, the backup lock, the run state, the key escrow and the audit history are all temporary, and the
 * installer's `installed.lock` is a recorder: nothing here touches the developer's storage.
 */
class InstallerWizardScrubTest extends TestCase
{
    use DrivesInstallerRuns;
    use RefreshDatabase;

    /** @var object{locks: int, wizardFileThere: array<int, bool>} */
    private object $installer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareInstallerRunTest();
        $this->app->instance(PackageAudit::class, new PackageAudit($this->tmp.DIRECTORY_SEPARATOR.'history.ndjson'));

        $wizardPath = $this->wizard()->path();
        $this->installer = new class($wizardPath) extends InstallManager
        {
            public int $locks = 0;

            /** @var array<int, bool> whether the wizard file still existed each time installed.lock was written */
            public array $wizardFileThere = [];

            public function __construct(private readonly string $wizardPath) {}

            public function finalizeLock(): void
            {
                $this->locks++;
                $this->wizardFileThere[] = is_file($this->wizardPath);
            }
        };
        $this->app->instance(InstallManager::class, $this->installer);
        $this->app->forgetInstance(CompleteStage::class);
    }

    protected function tearDown(): void
    {
        $this->cleanUpInstallerRunTest();

        parent::tearDown();
    }

    /** A run at the last stage on the given door, the site closed (temporary marker and lock), and the wizard file on disk. */
    private function finishingRun(string $door, array $flags = []): ImportState
    {
        $state = $this->runStore()->create('import', [
            'options' => ['door' => $door],
            'package' => ['groups' => ['core'], 'loaded' => ['settings' => 5]],
            'flags' => ['fin.readiness.done' => true] + array_fill_keys($flags, true),
        ]);
        app(ImportMarker::class)->write($state->id);
        app(BackupLock::class)->acquire('import:'.$state->id);

        $this->wizard()->reset();
        $this->assertNotNull($this->wizard()->claim('10.0.0.1'));
        $this->wizard()->change(['choices' => ['launch' => 'live', 'url' => 'http://shop.example'], 'run' => $state->id]);

        return $state;
    }

    private function complete(ImportState $state): void
    {
        app(CompleteStage::class)->step($state, new ImportContext(microtime(true) + 60, app(KeyEscrow::class)));
    }

    /** @return array<int, string> */
    private function wizardFiles(): array
    {
        $path = $this->wizard()->path();

        return array_values(array_filter([$path, $path.'.prev', $path.'.lock'], 'is_file'));
    }

    #[Test]
    public function the_installers_wizard_file_is_removed_when_its_import_completes(): void
    {
        $state = $this->finishingRun('installer');
        $this->assertNotEmpty($this->wizardFiles(), 'the wizard is on disk before the last stage');
        $this->assertTrue($this->wizard()->exists());

        $this->complete($state);

        $this->assertSame([], $this->wizardFiles(), 'the wizard file, its previous copy and its lock are all gone');
        $this->assertFalse($this->wizard()->exists());
        $this->assertTrue($state->flag('complete.wizard'));
        $this->assertSame(ImportStatus::Complete, $state->status);
        $this->assertSame(1, $this->installer->locks);
    }

    #[Test]
    public function the_wizard_is_gone_before_installed_lock_is_written_which_stays_the_last_thing_that_happens(): void
    {
        $this->complete($this->finishingRun('installer'));

        $this->assertSame([false], $this->installer->wizardFileThere, 'installed.lock is written strictly last');
    }

    #[Test]
    public function another_door_leaves_a_wizard_alone(): void
    {
        foreach (['admin', 'cli'] as $door) {
            $state = $this->finishingRun($door);
            $before = (string) file_get_contents($this->wizard()->path());

            $this->complete($state);

            $this->assertSame($before, (string) file_get_contents($this->wizard()->path()), "{$door}: somebody else's wizard is untouched");
            $this->assertTrue($state->flag('complete.wizard'), 'the sub-step is recorded either way');
        }

        $this->assertSame(0, $this->installer->locks, 'only the installer door writes installed.lock');
    }

    #[Test]
    public function a_run_killed_before_the_scrub_removes_the_wizard_when_it_resumes_and_a_second_pass_changes_nothing(): void
    {
        // The first process did everything up to and including the history line and died.
        $state = $this->finishingRun('installer', ['complete.scrubbed', 'complete.escrow', 'complete.marker', 'complete.lock', 'complete.history']);
        app(ImportMarker::class)->remove();
        app(BackupLock::class)->release();
        $this->assertNotEmpty($this->wizardFiles());

        $this->complete($state);

        $this->assertSame([], $this->wizardFiles());
        $this->assertSame(1, $this->installer->locks);

        // Resuming a run that already finished does nothing again.
        $this->complete($state);
        $this->assertSame(1, $this->installer->locks);
        $this->assertSame([], $this->wizardFiles());
    }

    #[Test]
    public function a_missing_wizard_is_not_an_error(): void
    {
        $state = $this->finishingRun('installer');
        $this->wizard()->reset();

        $this->complete($state);

        $this->assertTrue($state->flag('complete.wizard'));
        $this->assertSame(ImportStatus::Complete, $state->status);
    }
}
