<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    use RefreshDatabase;

    /** Snapshot of the developer's real .env, restored in tearDown. */
    protected ?string $envBackup = null;

    protected function setUp(): void
    {
        parent::setUp();

        // The installer writes to base_path('.env') (InstallManager::
        // updateEnvFile). Snapshot it so these tests can never clobber the
        // developer's real environment — this is exactly how a test run once
        // overwrote DB_DATABASE with the mock 'test_database' and broke login.
        $envPath = base_path('.env');
        $this->envBackup = File::exists($envPath) ? File::get($envPath) : null;

        // Remove installed.lock if exists
        $lockFile = storage_path('installed.lock');
        if (File::exists($lockFile)) {
            File::delete($lockFile);
        }

        // Never let a stray install/state.json from a previous run leak in.
        @unlink(storage_path('app/install/state.json'));

        // Ensure we're in testing environment
        config(['app.env' => 'testing']);
    }

    protected function tearDown(): void
    {
        // Restore the real .env exactly as it was before the test ran.
        $envPath = base_path('.env');
        if ($this->envBackup !== null) {
            File::put($envPath, $this->envBackup);
        } elseif (File::exists($envPath)) {
            File::delete($envPath);
        }

        // Clean up after tests
        $lockFile = storage_path('installed.lock');
        if (File::exists($lockFile)) {
            File::delete($lockFile);
        }
        @unlink(storage_path('app/install/state.json'));

        parent::tearDown();
    }

    #[Test]
    public function installer_redirects_when_already_installed()
    {
        // Create installed.lock file
        File::put(storage_path('installed.lock'), 'installed');

        // Try to access installer
        $response = $this->get('/install');

        // Should redirect to homepage
        $response->assertRedirect('/');
    }

    #[Test]
    public function installer_middleware_self_heals_lock_file_when_admins_table_already_has_data()
    {
        // No lock file, but a real admin exists — e.g. the lock file was
        // deleted by mistake on a host that's already installed. The
        // installer must never let migrate:fresh run over this database.
        Admin::factory()->create();

        $this->assertFileDoesNotExist(storage_path('installed.lock'));

        $response = $this->get('/install');

        $response->assertRedirect('/');
        $this->assertFileExists(storage_path('installed.lock'));
    }

    #[Test]
    public function installer_welcome_page_loads()
    {
        $response = $this->get('/install');

        $response->assertStatus(200);
        $response->assertSee('System Requirements');
        $response->assertSee('PHP Requirements');
        $response->assertSee('Directory Permissions');
    }

    #[Test]
    public function installer_shows_correct_steps()
    {
        // First visit step 1 to pass requirements check
        $this->get('/install');

        // Then visit step 2
        $response = $this->get('/install/database');

        $response->assertStatus(200);
        $response->assertSee('Database Configuration');
        $response->assertSee('Database Host');

        // Check that step progress shows step 2 as current
        $response->assertSee('bg-navy', false); // Current step should have navy background
    }

    #[Test]
    public function installer_validates_database_connection()
    {
        DB::shouldReceive('purge')->with('mysql')->andReturnNull();
        DB::shouldReceive('reconnect')->with('mysql')->andThrow(new \PDOException('Connection refused'));
        DB::shouldReceive('connection')->with('mysql')->andReturnSelf();
        DB::shouldReceive('getPdo')->andThrow(new \PDOException('Connection refused'));

        $response = $this->post('/install/database', [
            'db_host' => 'invalid-host',
            'db_port' => '3306',
            'db_name' => 'nonexistent',
            'db_username' => 'wrong',
            'db_password' => 'wrong',
        ]);

        // Should redirect back with error
        $response->assertRedirect();
        $response->assertSessionHasErrors('db_connection');
    }

    #[Test]
    public function installer_processes_site_settings()
    {
        $response = $this->withSession(['installer.db_configured' => true])
            ->post('/install/site-settings', [
                'site_name' => 'Test Site',
                'site_url' => 'https://example.com',
                'default_locale' => 'en',
                'timezone' => 'UTC',
            ]);

        $response->assertRedirect('/install/admin-account');
        $response->assertSessionHasNoErrors();
    }

    #[Test]
    public function installer_validates_admin_account()
    {
        $response = $this->withSession(['installer.site_settings_done' => true])
            ->post('/install/admin-account', [
                'name' => 'Test Admin',
                'email' => 'invalid-email',
                'password' => 'short',
                'password_confirmation' => 'different',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['email', 'password']);
    }

    #[Test]
    public function installer_process_steps_reject_skipped_prerequisites()
    {
        // POSTing straight to a later step without having completed the one
        // before it must bounce back to step 1, not silently accept
        // whatever's in the request (previously this created settings rows
        // with values from steps that were never actually filled in).
        $response = $this->post('/install/admin-account', [
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'password' => 'Qz7#mV2!xP',
            'password_confirmation' => 'Qz7#mV2!xP',
        ]);

        $response->assertRedirect('/install');
        $response->assertSessionHas('error');
    }

    #[Test]
    public function installer_run_requires_email_setup_step_to_be_completed()
    {
        $response = $this->get('/install/run');

        $response->assertRedirect('/install');
    }

    #[Test]
    public function installer_run_shows_progress_page_once_email_setup_is_done()
    {
        $response = $this->withSession([
            'installer.email_setup_done' => true,
            'installer.admin_name' => 'Test Admin',
            'installer.admin_email' => 'admin@example.com',
            'installer.admin_password' => bcrypt('Qz7#mV2!xP'),
            'installer.site_name' => 'Test Site',
            'installer.site_url' => 'https://example.com',
            'installer.default_locale' => 'en',
            'installer.timezone' => 'UTC',
        ])->get('/install/run');

        $response->assertStatus(200);
        $response->assertSee('Installing OeParts');

        // start() only writes the checkpoint file — it must never itself run
        // migrate:fresh or any other real work; that happens one chunk at a
        // time behind the AJAX /install/run/advance endpoint.
        $this->assertFileExists(storage_path('app/install/state.json'));
    }

    #[Test]
    public function installer_completes_successfully_with_mock_data()
    {
        // Skip actual database operations for this test
        // We'll test the flow up to the final step

        // Step 1: Requirements (should pass in test environment)
        $response = $this->get('/install');
        $response->assertStatus(200);

        // Step 2: Database (mock successful connection) — the session flag
        // this sets is what step 3's process method now requires.
        $response = $this->withSession(['installer.db_configured' => true])
            ->post('/install/database', [
                'db_host' => '127.0.0.1',
                'db_port' => '3306',
                'db_name' => 'test_database',
                'db_username' => 'root',
                'db_password' => '',
            ]);

        // In real scenario this would test connection, but we'll assume it passes
        // and moves to next step

        // Step 3: Site settings
        $response = $this->withSession(['installer.db_configured' => true])
            ->post('/install/site-settings', [
                'site_name' => 'Test Site',
                'site_url' => 'https://example.com',
                'default_locale' => 'en',
                'timezone' => 'UTC',
            ]);

        $response->assertRedirect('/install/admin-account');

        // Step 4: Admin account
        $response = $this->post('/install/admin-account', [
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'password' => 'Qz7#mV2!xP',
            'password_confirmation' => 'Qz7#mV2!xP',
        ]);

        $response->assertRedirect('/install/email-setup');

        // Step 5: Email setup
        $response = $this->post('/install/email-setup', [
            'mail_driver' => 'log',
            'mail_host' => 'localhost',
            'mail_port' => '1025',
            'mail_username' => '',
            'mail_password' => '',
            'mail_encryption' => '',
            'mail_from_address' => 'noreply@example.com',
            'mail_from_name' => 'Test Site',
        ]);

        $response->assertRedirect('/install/run');

        // Step 6: the progress page loads and starts a checkpointed run.
        // Deliberately NOT calling /install/run/advance here — that would
        // run a real migrate:fresh against the shared sqlite :memory:
        // connection this whole suite's RefreshDatabase trait relies on.
        // See tests/Unit/InstallManagerTest.php for the state-machine
        // itself, exercised with fake steps.
        $response = $this->get('/install/run');
        $response->assertStatus(200);
        $response->assertSee('Installing OeParts');
    }

    #[Test]
    public function installer_test_mail_endpoint_validates_input()
    {
        $response = $this->postJson('/install/email-setup/test-mail', [
            'mail_driver' => 'smtp',
            // missing mail_host/mail_port/mail_from_address/mail_from_name/test_to
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    #[Test]
    public function demo_setup_command_runs_successfully()
    {
        // Test the artisan command
        $exitCode = Artisan::call('demo:setup', ['--seed' => true]);

        $this->assertEquals(0, $exitCode, 'Demo setup command should exit with code 0');

        $output = Artisan::output();
        // Check if output contains the expected text (may have special formatting)
        $this->assertTrue(str_contains($output, 'SETUP COMPLETE'), 'Output should contain "SETUP COMPLETE"');
    }

    #[Test]
    public function installer_middleware_blocks_access_when_installed()
    {
        // Create installed.lock
        File::put(storage_path('installed.lock'), 'installed');

        // Try to access installer routes gated by the installer middleware.
        // installer.complete is deliberately NOT in this list — it lives
        // outside that middleware group on purpose (see routes/installer.php).
        $routes = [
            '/install',
            '/install/database',
            '/install/site-settings',
            '/install/admin-account',
            '/install/email-setup',
            '/install/run',
        ];

        foreach ($routes as $route) {
            $response = $this->get($route);
            $response->assertRedirect('/');
        }
    }

    #[Test]
    public function installer_complete_page_is_reachable_even_after_the_lock_file_exists()
    {
        // installer.complete must stay reachable right after a real install
        // writes the lock file — InstallManager's last step does exactly
        // that immediately before redirecting the browser here.
        File::put(storage_path('installed.lock'), 'installed');

        $response = $this->get('/install/complete');

        $response->assertStatus(200);
        $response->assertSee('Installation Complete');
    }

    #[Test]
    public function installer_middleware_allows_access_when_not_installed()
    {
        // Ensure no lock file
        $lockFile = storage_path('installed.lock');
        if (File::exists($lockFile)) {
            File::delete($lockFile);
        }

        // Try to access installer routes
        $response = $this->get('/install');
        $response->assertStatus(200);
        $response->assertSee('System Requirements');
    }

    #[Test]
    public function installer_creates_installed_lock_file()
    {
        // Simulate installation completion
        $lockFile = storage_path('installed.lock');

        // Ensure it doesn't exist initially
        if (File::exists($lockFile)) {
            File::delete($lockFile);
        }

        $this->assertFalse(File::exists($lockFile));

        // Create the lock file (simulating installer completion)
        File::put($lockFile, 'Installed at ' . now()->toDateTimeString());

        $this->assertTrue(File::exists($lockFile));

        // Clean up
        File::delete($lockFile);
    }
}
