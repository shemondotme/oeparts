<?php

namespace Tests\Unit;

use App\Services\Install\InstallManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests InstallManager's chunked state-machine bookkeeping in isolation —
 * step sequencing, progress math, error propagation, resume/reset — via a
 * subclass that fakes runStep() instead of running real Artisan commands.
 * Deliberately never exercises the real 'migrate' step here: running an
 * actual migrate:fresh from inside this suite (which also relies on
 * RefreshDatabase elsewhere, sharing one sqlite :memory: connection) risks
 * leaking rows into later tests since DDL can't be rolled back the way
 * RefreshDatabase's per-test transactions expect. The real steps are
 * exercised by hand against a disposable database before each release.
 */
class InstallManagerTest extends TestCase
{
    private function fakeManager(array $stepBehaviors = []): InstallManager
    {
        return new class($stepBehaviors) extends InstallManager
        {
            public function __construct(private array $behaviors) {}

            protected function runStep(string $key, array $input): string
            {
                $behavior = $this->behaviors[$key] ?? "fake:{$key}";

                if ($behavior instanceof \Throwable) {
                    throw $behavior;
                }

                return $behavior;
            }
        };
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/install/state.json'));

        parent::tearDown();
    }

    #[Test]
    public function start_creates_a_running_state_with_the_expected_step_count(): void
    {
        $manager = $this->fakeManager();

        $progress = $manager->start(['import_demo_data' => false]);

        $this->assertSame('running', $progress['status']);
        $this->assertSame(0, $progress['step_index']);
        $this->assertSame('migrate', $progress['step']);
        $this->assertSame(12, $progress['total_steps']);
    }

    #[Test]
    public function start_adds_a_demo_data_step_when_requested(): void
    {
        $manager = $this->fakeManager();

        $progress = $manager->start(['import_demo_data' => true]);

        $this->assertSame(13, $progress['total_steps']);
    }

    #[Test]
    public function advance_moves_through_every_step_and_reaches_success(): void
    {
        $manager = $this->fakeManager();
        $manager->start(['import_demo_data' => false]);

        $last = null;
        for ($i = 0; $i < 12; $i++) {
            $last = $manager->advance();
            $this->assertNotSame('failed', $last['status']);
        }

        $this->assertSame('success', $last['status']);
        $this->assertSame(100, $last['percent']);
    }

    #[Test]
    public function an_overlapping_advance_reports_progress_instead_of_running_the_step_twice(): void
    {
        // A slow step outlasts the web server's timeout, the wizard's JS re-polls, and the
        // retry lands while the first request is still inside the step. Running migrate:fresh
        // twice at once dropped tables under the first run (found installing the real 1.0.16
        // release on nginx).
        $manager = new class extends InstallManager
        {
            public array $ran = [];

            public ?array $overlap = null;

            protected function runStep(string $key, array $input): string
            {
                $this->ran[] = $key;

                if ($key === 'migrate') {
                    $this->overlap = $this->advance(); // the "retry" arriving mid-step
                }

                return "fake:{$key}";
            }
        };
        $manager->start(['import_demo_data' => false]);

        $first = $manager->advance();

        $this->assertSame(['migrate'], $manager->ran, 'the step must run exactly once');
        $this->assertSame('running', $manager->overlap['status']);
        $this->assertSame(0, $manager->overlap['step_index'], 'the overlapping poll sees the step still in progress');
        $this->assertSame(1, $first['step_index'], 'the original request completes the step');
        $this->assertSame(2, $manager->advance()['step_index'], 'once the step is done the next poll moves on');
    }

    #[Test]
    public function advance_stops_and_reports_the_failing_step_on_error(): void
    {
        $manager = $this->fakeManager(['seed_roles' => new \RuntimeException('boom')]);
        $manager->start(['import_demo_data' => false]);

        // migrate, seed_settings, seed_languages succeed before seed_roles fails.
        $manager->advance();
        $manager->advance();
        $manager->advance();
        $result = $manager->advance();

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('seed_roles', $result['error']);
        $this->assertStringContainsString('boom', $result['error']);
        $this->assertTrue($manager->hasFailedRun());
    }

    #[Test]
    public function advance_after_a_terminal_status_is_idempotent(): void
    {
        $manager = $this->fakeManager();
        $manager->start(['import_demo_data' => false]);
        for ($i = 0; $i < 12; $i++) {
            $manager->advance();
        }

        $again = $manager->advance();

        $this->assertSame('success', $again['status']);
        $this->assertSame(100, $again['percent']);
    }

    /**
     * system-10: mail_password (plaintext SMTP credential) and
     * admin_password_hash used to sit in storage/app/install/state.json
     * indefinitely once an install finished — reset() only ever ran when
     * retrying a FAILED run, never after a successful one.
     */
    #[Test]
    public function successful_install_scrubs_plaintext_secrets_from_the_state_file(): void
    {
        $manager = $this->fakeManager();
        $manager->start([
            'import_demo_data' => false,
            'mail_password' => 'super-secret-smtp-pass',
            'admin_password_hash' => '$2y$10$fakehashfakehashfakehashfa',
            'admin_email' => 'admin@example.com',
        ]);

        for ($i = 0; $i < 12; $i++) {
            $manager->advance();
        }

        $state = json_decode(file_get_contents($manager->statePath()), true);

        $this->assertSame('success', $state['status']);
        $this->assertArrayNotHasKey('mail_password', $state['input']);
        $this->assertArrayNotHasKey('admin_password_hash', $state['input']);
        // Non-secret input is kept — only the two secret keys are scrubbed.
        $this->assertSame('admin@example.com', $state['input']['admin_email']);
    }

    #[Test]
    public function reset_clears_state_so_a_new_run_can_start(): void
    {
        $manager = $this->fakeManager();
        $manager->start(['import_demo_data' => false]);
        $this->assertTrue($manager->isRunning());

        $manager->reset();

        $this->assertFalse($manager->isRunning());
        $this->assertFalse($manager->hasFailedRun());
    }

    #[Test]
    public function current_progress_reports_not_started_with_no_state_file(): void
    {
        $manager = $this->fakeManager();
        $manager->reset();

        $progress = $manager->currentProgress();

        $this->assertSame('not_started', $progress['status']);
    }

    #[Test]
    public function looks_already_installed_is_false_when_admins_table_is_empty(): void
    {
        $manager = new InstallManager;

        $this->assertFalse($manager->looksAlreadyInstalled());
    }
}
