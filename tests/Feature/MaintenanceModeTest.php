<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Services\SettingsService;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 17 (Error Page/Degraded-Mode UX). Every prior test touching
 * maintenance.enabled only asserted the SETTINGS FLAG flipped correctly
 * (UpdateApplierTest, SetupAssistantSafetyTest, ProductionRestoreServiceTest)
 * — nothing ever drove a real HTTP request through MaintenanceMode itself to
 * prove enforcement, which is how the API gap below went unnoticed.
 */
class MaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
    }

    private function enableMaintenance(): void
    {
        app(SettingsService::class)->set('maintenance.enabled', true);
    }

    #[Test]
    public function storefront_is_blocked_with_the_maintenance_view_when_enabled(): void
    {
        $this->enableMaintenance();

        $response = $this->get('/en/');

        $response->assertStatus(503);
        $response->assertViewIs('errors.maintenance');
        $response->assertHeader('Retry-After', '3600');
    }

    #[Test]
    public function storefront_is_reachable_when_maintenance_is_disabled(): void
    {
        $this->get('/en/')->assertStatus(200);
    }

    #[Test]
    public function admin_routes_bypass_maintenance_mode(): void
    {
        $this->enableMaintenance();

        $admin = Admin::factory()->create(['is_active' => true]);
        $this->seed(RolesSeeder::class);
        $admin->assignRole('super_admin');

        $this->actingAs($admin, 'admin')->get('/admin')->assertStatus(200);
    }

    #[Test]
    public function a_whitelisted_ip_bypasses_maintenance_mode(): void
    {
        $this->enableMaintenance();
        app(SettingsService::class)->set('maintenance.allowed_ips', '10.1.2.3');

        $this->get('/en/', ['REMOTE_ADDR' => '10.1.2.3'])->assertStatus(200);
    }

    /**
     * The real bug: Api\CheckoutController::step5() writes a real Order row
     * (and can trigger a real gateway charge) with zero maintenance-mode
     * awareness — a mobile client could place and pay for a real order
     * against a database mid self-update swap/migration while the browser
     * storefront correctly showed the 503 page. Middleware runs before the
     * controller/validation, so an empty payload still proves the block.
     */
    #[Test]
    public function the_api_checkout_endpoint_is_blocked_during_maintenance(): void
    {
        $this->enableMaintenance();

        $response = $this->postJson('/api/checkout/start', []);

        $response->assertStatus(503);
        $response->assertJson(['success' => false]);
    }

    #[Test]
    public function the_api_cart_endpoint_is_blocked_during_maintenance(): void
    {
        $this->enableMaintenance();

        $this->getJson('/api/cart/summary')->assertStatus(503);
    }

    #[Test]
    public function api_ping_stays_up_during_maintenance_for_uptime_monitoring(): void
    {
        $this->enableMaintenance();

        $this->getJson('/api/ping')->assertStatus(200)->assertJson(['ok' => true]);
    }

    /**
     * Not 200 — an empty cart makes CheckoutController::start() correctly
     * return 422. The point here is proving the request reaches the
     * controller at all (not blocked at 503) when maintenance is off.
     */
    #[Test]
    public function api_checkout_reaches_the_controller_when_maintenance_is_disabled(): void
    {
        $this->postJson('/api/checkout/start', [])->assertStatus(422);
    }

    /**
     * settings('maintenance.message') comes back as a raw JSON-encoded
     * string (SettingsSeeder's $ml() helper, no array cast on Setting) —
     * before normalize_multilang_setting(), a JSON API caller during
     * maintenance would have received the literal '{"en":"...","de":"..."}'
     * string as its "message", not the readable English text the HTML page
     * shows via the same underlying value.
     */
    #[Test]
    public function the_json_maintenance_response_resolves_the_localized_message_not_raw_json(): void
    {
        $this->enableMaintenance();

        $response = $this->postJson('/api/checkout/start', []);

        $message = $response->json('message');
        $this->assertIsString($message);
        $this->assertStringNotContainsString('{"en"', $message);
        $this->assertSame("We'll be back soon.", $message);
    }
}
