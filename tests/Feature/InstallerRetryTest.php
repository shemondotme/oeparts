<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Services\Install\InstallManager;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Retry after a failed install run.
 *
 * The bug this pins: "Retry" used to delete the run's state file. When the run
 * had failed AFTER the admin account was created (settings, mail or backup-key
 * step), the next request found no run, saw the populated admins table, and
 * InstallerMiddleware wrote installed.lock onto the unfinished install — and
 * starting over was impossible anyway because migrate:fresh refuses to run over
 * an existing admin. Now Retry resumes at the step that failed.
 */
class InstallerRetryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        File::delete(storage_path('installed.lock'));
        @unlink(storage_path('app/install/state.json'));
    }

    protected function tearDown(): void
    {
        File::delete(storage_path('installed.lock'));
        @unlink(storage_path('app/install/state.json'));

        parent::tearDown();
    }

    /**
     * An InstallManager whose steps are fake (no migrate:fresh, no .env writes).
     * 'create_admin' really creates an admin row, because that row is exactly what
     * makes a restart impossible. The 'lock' step does NOT write installed.lock, so
     * a lock file appearing in a test can only come from the middleware.
     *
     * @param  array<int, string>  $failOnce  steps that throw the first time they run
     */
    private function fakeInstaller(array $failOnce = []): InstallManager
    {
        $manager = new class($failOnce) extends InstallManager
        {
            public function __construct(private array $failOnce) {}

            protected function runStep(string $key, array $input): string
            {
                if (($i = array_search($key, $this->failOnce, true)) !== false) {
                    unset($this->failOnce[$i]);

                    throw new \RuntimeException("simulated failure in {$key}");
                }

                if ($key === 'create_admin') {
                    Admin::factory()->create(['email' => 'owner@example.test']);
                }

                return "fake:{$key}";
            }

            /** Lets a test run one real step. */
            public function realStep(string $key, array $input): string
            {
                return parent::runStep($key, $input);
            }
        };

        $this->app->instance(InstallManager::class, $manager);

        return $manager;
    }

    private function advanceUntilFailed(InstallManager $manager): array
    {
        $manager->start(['import_demo_data' => false]);

        for ($i = 0; $i < 30; $i++) {
            $progress = $manager->advance();

            if (in_array($progress['status'], ['failed', 'success'], true)) {
                return $progress;
            }
        }

        $this->fail('The fake run never reached a terminal state.');
    }

    #[Test]
    public function a_failure_after_the_admin_exists_cannot_be_started_over_and_reports_it(): void
    {
        $manager = $this->fakeInstaller(['persist_settings']);

        $progress = $this->advanceUntilFailed($manager);

        $this->assertSame('failed', $progress['status']);
        $this->assertStringContainsString('persist_settings', $progress['error']);
        $this->assertSame(1, Admin::count());
        $this->assertFalse($progress['can_restart'], 'once the admin exists the run can only be resumed');
        $this->assertFalse($manager->canRestart());
    }

    #[Test]
    public function a_failure_before_the_admin_exists_can_be_started_over(): void
    {
        $manager = $this->fakeInstaller(['seed_roles']);

        $progress = $this->advanceUntilFailed($manager);

        $this->assertSame('failed', $progress['status']);
        $this->assertTrue($progress['can_restart']);
    }

    #[Test]
    public function retry_after_the_admin_exists_resumes_at_the_failed_step_and_never_writes_the_lock(): void
    {
        $manager = $this->fakeInstaller(['persist_settings']);
        $this->advanceUntilFailed($manager);

        $this->post('/install/retry')->assertRedirect(route('installer.install'));

        // The state survived and points at the step that failed.
        $this->assertFileExists(storage_path('app/install/state.json'));
        $progress = $manager->currentProgress();
        $this->assertSame('running', $progress['status']);
        $this->assertSame('persist_settings', $progress['step']);
        $this->assertNull($progress['error']);

        // The page the redirect lands on shows the progress screen and must not touch the lock or the run.
        $this->withSession(['installer.email_setup_done' => true])
            ->get(route('installer.install'))
            ->assertOk();
        $this->assertFileDoesNotExist(storage_path('installed.lock'));
        $this->assertSame('persist_settings', $manager->currentProgress()['step']);

        // The run continues from there and finishes without creating a second admin.
        for ($i = 0; $i < 10 && $manager->currentProgress()['status'] === 'running'; $i++) {
            $manager->advance();
        }

        $this->assertSame('success', $manager->currentProgress()['status']);
        $this->assertSame(1, Admin::count());
    }

    #[Test]
    public function retry_before_the_admin_exists_discards_the_run_so_it_starts_over(): void
    {
        $manager = $this->fakeInstaller(['seed_roles']);
        $this->advanceUntilFailed($manager);

        $this->post('/install/retry')->assertRedirect(route('installer.install'));

        $this->assertFileDoesNotExist(storage_path('app/install/state.json'));
    }

    #[Test]
    public function reset_is_refused_and_keeps_the_state_once_an_admin_exists(): void
    {
        $manager = $this->fakeInstaller(['mail_env']);
        $this->advanceUntilFailed($manager);

        $this->assertFalse($manager->reset());
        $this->assertFileExists(storage_path('app/install/state.json'));
        $this->assertTrue($manager->hasFailedRun());
    }

    #[Test]
    public function a_failed_run_with_an_admin_is_not_mistaken_for_a_finished_install_by_the_middleware(): void
    {
        $manager = $this->fakeInstaller(['persist_settings']);
        $this->advanceUntilFailed($manager);

        $this->get('/install/database')->assertOk();

        $this->assertFileDoesNotExist(storage_path('installed.lock'));
    }

    #[Test]
    public function the_create_admin_step_can_run_twice_without_a_duplicate_or_a_missing_role(): void
    {
        $this->seed(RolesSeeder::class);
        $manager = $this->fakeInstaller();

        $input = [
            'admin_name' => 'Site Owner',
            'admin_email' => 'owner@example.test',
            'admin_password_hash' => bcrypt('Sup3r-Secret!'),
        ];

        // The real step, twice: the second call is what a resume after a half-finished first run does.
        $manager->realStep('create_admin', $input);
        $manager->realStep('create_admin', $input);

        $this->assertSame(1, Admin::where('email', 'owner@example.test')->count());
        $this->assertTrue(Admin::where('email', 'owner@example.test')->first()->hasRole('super_admin'));
    }
}
