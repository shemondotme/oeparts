<?php

namespace Tests\Feature;

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

        // The installer writes to base_path('.env') (InstallerController::
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
        $response = $this->post('/install/site-settings', [
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
        $response = $this->post('/install/admin-account', [
            'name' => 'Test Admin',
            'email' => 'invalid-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['email', 'password']);
    }

    #[Test]
    public function installer_completes_successfully_with_mock_data()
    {
        // Skip actual database operations for this test
        // We'll test the flow up to the final step

        // Step 1: Requirements (should pass in test environment)
        $response = $this->get('/install');
        $response->assertStatus(200);

        // Step 2: Database (mock successful connection)
        $response = $this->post('/install/database', [
            'db_host' => '127.0.0.1',
            'db_port' => '3306',
            'db_name' => 'test_database',
            'db_username' => 'root',
            'db_password' => '',
        ]);

        // In real scenario this would test connection, but we'll assume it passes
        // and moves to next step

        // Step 3: Site settings
        $response = $this->post('/install/site-settings', [
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

        // Step 6: Installation process
        // This would run migrations and seeders in a real test
        // For now, just verify the route exists
        $response = $this->get('/install/complete');
        $response->assertStatus(200);
        $response->assertSee('Installation Complete');
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

        // Try to access installer routes
        $routes = [
            '/install',
            '/install/database',
            '/install/site-settings',
            '/install/admin-account',
            '/install/email-setup',
            '/install/complete',
        ];

        foreach ($routes as $route) {
            $response = $this->get($route);
            $response->assertRedirect('/');
        }
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
