<?php

namespace Tests\Feature;

use App\Filament\Pages\System\HealthCheckDashboard;
use App\Filament\Pages\System\SetupAssistant;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CriticalActionSafetyTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;

    private Admin $supportAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            \Database\Seeders\SettingsSeeder::class,
            \Database\Seeders\LanguagesSeeder::class,
            \Database\Seeders\RolesSeeder::class,
            \Database\Seeders\AdminSeeder::class,
        ]);

        $this->superAdmin = Admin::where('email', 'admin@oeparts.test')->firstOrFail();

        $this->supportAdmin = Admin::create([
            'name' => 'Support Agent',
            'email' => 'support@oeparts.test',
            'password' => bcrypt('password'),
        ]);
        $this->supportAdmin->assignRole('support');
    }

    // ── SetupAssistant: Role Access ────────────────────────────────────────

    #[Test]
    public function setup_assistant_requires_super_admin(): void
    {
        $this->actingAs($this->superAdmin, 'admin');
        $this->assertTrue(SetupAssistant::canAccess());

        $this->actingAs($this->supportAdmin, 'admin');
        $this->assertFalse(SetupAssistant::canAccess());
    }

    // ── SetupAssistant: Action Risk Levels ─────────────────────────────────

    #[Test]
    public function seed_demo_data_is_high_risk_and_requires_typed_confirmation(): void
    {
        $this->actingAs($this->superAdmin, 'admin');
        $page = $this->buildSetupPage();

        $risk = $page->getActionRisk('seedDemoData');

        $this->assertEquals('HIGH', $risk['level']);
        $this->assertEquals('danger', $risk['color']);
        $this->assertTrue($risk['requireTypedConfirmation']);
        $this->assertEquals('SEED', $risk['confirmText']);
    }

    #[Test]
    public function run_migrations_is_medium_risk(): void
    {
        $page = $this->buildSetupPage();

        $risk = $page->getActionRisk('runMigrations');

        $this->assertEquals('MEDIUM', $risk['level']);
        $this->assertEquals('warning', $risk['color']);
        $this->assertFalse($risk['requireTypedConfirmation']);
    }

    #[Test]
    public function toggle_maintenance_is_medium_risk(): void
    {
        $page = $this->buildSetupPage();

        $risk = $page->getActionRisk('toggleMaintenance');

        $this->assertEquals('MEDIUM', $risk['level']);
        $this->assertEquals('warning', $risk['color']);
    }

    #[Test]
    public function clear_cache_is_low_risk(): void
    {
        $page = $this->buildSetupPage();

        $risk = $page->getActionRisk('clearCache');

        $this->assertEquals('LOW', $risk['level']);
        $this->assertEquals('success', $risk['color']);
    }

    #[Test]
    public function clear_views_is_low_risk(): void
    {
        $page = $this->buildSetupPage();

        $risk = $page->getActionRisk('clearViews');

        $this->assertEquals('LOW', $risk['level']);
        $this->assertEquals('success', $risk['color']);
    }

    #[Test]
    public function default_risk_is_low_for_unknown_actions(): void
    {
        $page = $this->buildSetupPage();

        $risk = $page->getActionRisk('unknownAction');

        $this->assertEquals('LOW', $risk['level']);
    }

    // ── HealthCheckDashboard: Role Access ──────────────────────────────────

    #[Test]
    public function health_check_requires_super_admin(): void
    {
        $this->actingAs($this->superAdmin, 'admin');
        $this->assertTrue(HealthCheckDashboard::canAccess());

        $this->actingAs($this->supportAdmin, 'admin');
        $this->assertFalse(HealthCheckDashboard::canAccess());
    }

    // ── HealthCheckDashboard: Remediation Config ───────────────────────────

    #[Test]
    public function database_remediation_does_not_need_confirmation(): void
    {
        $remediation = $this->buildHealthPage()->getRemediationForCheck('database');

        $this->assertNotNull($remediation);
        $this->assertFalse($remediation['needsConfirmation']);
        $this->assertEquals('Check Config', $remediation['label']);
        $this->assertEquals('remediateDatabase', $remediation['action']);
    }

    #[Test]
    public function cache_remediation_requires_confirmation(): void
    {
        $remediation = $this->buildHealthPage()->getRemediationForCheck('cache');

        $this->assertNotNull($remediation);
        $this->assertTrue($remediation['needsConfirmation']);
        $this->assertEquals('Clear Cache', $remediation['label']);
        $this->assertEquals('clearCacheRemediation', $remediation['action']);
        $this->assertStringContainsString('flush all cached data', $remediation['confirmMessage']);
    }

    #[Test]
    public function queue_remediation_links_to_setup_assistant(): void
    {
        $remediation = $this->buildHealthPage()->getRemediationForCheck('queue');

        $this->assertNotNull($remediation);
        $this->assertArrayHasKey('externalUrl', $remediation);
        $this->assertStringContainsString('setup-assistant', $remediation['externalUrl']);
        $this->assertNull($remediation['action']);
    }

    #[Test]
    public function asset_remediation_links_to_setup_assistant(): void
    {
        $remediation = $this->buildHealthPage()->getRemediationForCheck('assets');

        $this->assertNotNull($remediation);
        $this->assertArrayHasKey('externalUrl', $remediation);
        $this->assertNull($remediation['action']);
    }

    #[Test]
    public function scheduler_remediation_requires_confirmation(): void
    {
        $remediation = $this->buildHealthPage()->getRemediationForCheck('scheduler');

        $this->assertNotNull($remediation);
        $this->assertTrue($remediation['needsConfirmation']);
        $this->assertEquals('Reset Heartbeat', $remediation['label']);
        $this->assertEquals('resetSchedulerHeartbeat', $remediation['action']);
    }

    #[Test]
    public function unknown_check_returns_null_remediation(): void
    {
        $remediation = $this->buildHealthPage()->getRemediationForCheck('nonexistent_check');

        $this->assertNull($remediation);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function buildSetupPage(): SetupAssistant
    {
        $this->actingAs($this->superAdmin, 'admin');

        return new SetupAssistant;
    }

    private function buildHealthPage(): HealthCheckDashboard
    {
        $this->actingAs($this->superAdmin, 'admin');

        return new HealthCheckDashboard;
    }
}
