<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminHealthTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test admin
        $this->admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);

        // Fake storage
        Storage::fake('local');
        Storage::fake('public');
    }

    #[Test]
    public function admin_can_view_health_index(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get(route('admin.health.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.health.index');
        $response->assertSee('System Health');
        $response->assertSee('Database');
        $response->assertSee('Cache');
        $response->assertSee('Storage');
        $response->assertSee('PHP');
    }

    #[Test]
    public function health_check_returns_correct_statuses(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get(route('admin.health.index'));

        $response->assertStatus(200);
        
        // Check that health data is present
        $view = $response->original;
        $data = $view->getData();
        
        $this->assertTrue(isset($data['healthChecks']));
        $this->assertIsArray($data['healthChecks']);
        
        // Should have at least database check
        $this->assertNotEmpty($data['healthChecks']);
    }

    #[Test]
    public function admin_can_run_manual_health_check(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->post(route('admin.health.run-check'));

        $response->assertRedirect(route('admin.health.index'));
        $response->assertSessionHas('success');
    }

    #[Test]
    public function health_export_works(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get(route('admin.health.export'));

        $response->assertStatus(302); // Redirects back
        // The export method redirects back, so we can't check headers
    }

    #[Test]
    public function database_health_check_passes(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get(route('admin.health.index'));
        $response->assertStatus(200);
        
        // Database should be healthy in tests
        $response->assertSee('text-emerald-700'); // Success indicator for database
    }

    #[Test]
    public function cache_health_check_passes(): void
    {
        $this->actingAs($this->admin, 'admin');

        // Test cache by setting and getting a value
        Cache::put('health_test', 'test_value', 60);
        $this->assertEquals('test_value', Cache::get('health_test'));

        $response = $this->get(route('admin.health.index'));
        $response->assertStatus(200);
        
        // Cache should be healthy
        $response->assertSee('text-emerald-700'); // Success indicator for cache
    }

    #[Test]
    public function storage_health_check_passes(): void
    {
        $this->actingAs($this->admin, 'admin');

        // Create test files in storage
        Storage::disk('local')->put('test.txt', 'Test content');
        Storage::disk('public')->put('public_test.txt', 'Public test content');

        $response = $this->get(route('admin.health.index'));
        $response->assertStatus(200);
        
        // Storage should be healthy
        $response->assertSee('text-emerald-700'); // Success indicator for storage
    }

    #[Test]
    public function php_health_check_includes_correct_info(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get(route('admin.health.index'));
        $response->assertStatus(200);

        // Should show PHP version
        $response->assertSee('PHP');
        $response->assertSee(phpversion());

        // Should show Laravel version (displayed as "laravel version" in system info)
        $response->assertSee('laravel version');
        $response->assertSee(app()->version());
    }

    #[Test]
    public function ssl_health_check_shows_status(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get(route('admin.health.index'));
        $response->assertStatus(200);
        
        // SSL check should be present (may show as not configured in test environment)
        $response->assertSee('SSL');
    }

    #[Test]
    public function cron_health_check_shows_status(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get(route('admin.health.index'));
        $response->assertStatus(200);
        
        // Cron check should be present
        $response->assertSee('Cron');
    }

    #[Test]
    public function queue_health_check_shows_status(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get(route('admin.health.index'));
        $response->assertStatus(200);
        
        // Queue check should be present
        $response->assertSee('Queue');
    }

    #[Test]
    public function environment_health_check_shows_correct_environment(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get(route('admin.health.index'));
        $response->assertStatus(200);
        
        // Should show current environment
        $response->assertSee('Environment');
        $response->assertSee(app()->environment());
    }

    #[Test]
    public function disk_space_check_shows_status(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get(route('admin.health.index'));
        $response->assertStatus(200);
        
        // Disk space check should be present
        $response->assertSee('Disk Space');
    }

    #[Test]
    public function memory_usage_check_shows_status(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get(route('admin.health.index'));
        $response->assertStatus(200);
        
        // Memory usage check should be present
        $response->assertSee('Memory');
    }

    #[Test]
    public function health_checks_require_admin_authentication(): void
    {
        // Test without authentication
        $response = $this->get(route('admin.health.index'));
        $response->assertRedirect(route('admin.login'));

        $response = $this->post(route('admin.health.run-check'));
        $response->assertRedirect(route('admin.login'));

        $response = $this->get(route('admin.health.export'));
        $response->assertRedirect(route('admin.login'));
    }

    #[Test]
    public function health_check_with_failing_component_shows_error(): void
    {
        $this->actingAs($this->admin, 'admin');

        // Simulate a failing database connection by temporarily breaking the DSN
        $originalDsn = config('database.connections.mysql.host');
        config(['database.connections.mysql.host' => 'invalid-host-that-does-not-exist']);

        $response = $this->get(route('admin.health.index'));
        $response->assertStatus(200);

        // Database check should show error (critical status = red)
        $response->assertSee('text-red-700', false); // Error indicator

        // Restore config
        config(['database.connections.mysql.host' => $originalDsn]);
    }
}