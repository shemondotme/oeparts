<?php

namespace Tests\Feature;

use App\Livewire\HealthIndicator;
use App\Models\Admin;
use App\Services\HealthCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HealthIndicatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([\Database\Seeders\RolesSeeder::class]);
        Cache::forget('admin_health_indicator');
    }

    private function activeAdmin(string $role): Admin
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->assignRole($role);

        return $admin;
    }

    #[Test]
    public function renders_green_dot_when_health_status_is_ok(): void
    {
        $admin = $this->activeAdmin('super_admin');
        $this->actingAs($admin, 'admin');
        $this->get('/admin');

        // The dashboard visit above already rendered (and cached) the real,
        // unmocked HealthIndicator via its TOPBAR_END render hook — clear it
        // so this test's mock is what actually gets cached/asserted below.
        Cache::forget('admin_health_indicator');

        $this->mock(HealthCheckService::class)
            ->shouldReceive('runAll')
            ->andReturn(['status' => 'ok', 'checks' => []]);

        Livewire::test(HealthIndicator::class)
            ->assertSee('op-health-dot-ok', false);
    }

    #[Test]
    public function renders_amber_dot_when_health_status_is_degraded(): void
    {
        $admin = $this->activeAdmin('super_admin');
        $this->actingAs($admin, 'admin');
        $this->get('/admin');

        Cache::forget('admin_health_indicator');

        $this->mock(HealthCheckService::class)
            ->shouldReceive('runAll')
            ->andReturn(['status' => 'degraded', 'checks' => []]);

        Livewire::test(HealthIndicator::class)
            ->assertSee('op-health-dot-degraded', false);
    }

    #[Test]
    public function drill_through_link_is_present_only_for_super_admin(): void
    {
        $superAdmin = $this->activeAdmin('super_admin');
        $this->actingAs($superAdmin, 'admin');
        $this->get('/admin');

        Cache::forget('admin_health_indicator');

        $this->mock(HealthCheckService::class)
            ->shouldReceive('runAll')
            ->andReturn(['status' => 'ok', 'checks' => []]);

        Livewire::test(HealthIndicator::class)->assertSeeHtml('<a');

        $manager = $this->activeAdmin('manager');
        $this->actingAs($manager, 'admin');
        $this->get('/admin');

        Cache::forget('admin_health_indicator');

        $this->mock(HealthCheckService::class)
            ->shouldReceive('runAll')
            ->andReturn(['status' => 'ok', 'checks' => []]);

        Livewire::test(HealthIndicator::class)->assertDontSeeHtml('<a');
    }
}
