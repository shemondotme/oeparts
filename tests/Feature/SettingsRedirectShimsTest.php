<?php

namespace Tests\Feature;

use App\Models\Admin;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 8 hardening — every SettingsPage class deleted by the settings
 * reorg (Phases 1-7) must leave a 301 redirect shim from its old slug to
 * the new merged page, so old bookmarks/links don't 404. This is the one
 * automated check tying routes/web.php's shim list back to the actual
 * deletion map in modular-floating-sparkle.md — a shim silently dropped
 * during a future refactor would otherwise go unnoticed until a user hit
 * a dead link.
 */
class SettingsRedirectShimsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Old slug => new page slug it must redirect to. One entry per
     * deleted SettingsPage class (32 total) — see the plan's "Full page
     * deletion/merge map" table for the source of truth.
     *
     * @return array<string, string>
     */
    private const SHIMS = [
        'auth-security-settings' => 'security-access-settings',
        'security-settings' => 'security-access-settings',
        'maintenance-settings' => 'system-maintenance-settings',
        'about-license-settings' => 'system-maintenance-settings',
        'database-settings' => 'system-maintenance-settings',
        'general-settings' => 'general-brand-settings',
        'company-settings' => 'general-brand-settings',
        'store-settings' => 'general-brand-settings',
        'preloader-settings' => 'appearance-settings',
        'stats-counter-settings' => 'appearance-settings',
        'ui-settings' => 'customization-settings',
        'navbar-settings' => 'customization-settings',
        'footer-settings' => 'customization-settings',
        'announcement-settings' => 'customization-settings',
        'sections-settings' => 'customization-settings',
        'menu-settings' => 'customization-settings',
        'social-link-settings' => 'customization-settings',
        'orders-settings' => 'store-operations-settings',
        'customers-settings' => 'store-operations-settings',
        'cart-settings' => 'store-operations-settings',
        'dashboard-settings' => 'store-operations-settings',
        'shipping-settings' => 'store-operations-settings',
        'tax-settings' => 'store-operations-settings',
        'checkout-settings' => 'store-operations-settings',
        'payment-settings' => 'store-operations-settings',
        'email-settings' => 'store-operations-settings',
        'contact-settings' => 'store-operations-settings',
        'part-inquiry-settings' => 'store-operations-settings',
        'search-settings' => 'search-catalog-settings',
        'pdp-settings' => 'search-catalog-settings',
        'integrations-settings' => 'marketing-settings',
        'newsletter-settings' => 'marketing-settings',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    #[Test]
    public function every_deleted_settings_page_slug_redirects_to_its_merged_destination(): void
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');

        foreach (self::SHIMS as $oldSlug => $newSlug) {
            $response = $this->get("/admin/settings/{$oldSlug}");

            $response->assertStatus(301, "Expected /admin/settings/{$oldSlug} to 301 redirect.");
            $response->assertRedirect("/admin/settings/{$newSlug}");
        }
    }

    #[Test]
    public function every_redirect_destination_is_itself_a_live_registered_page(): void
    {
        // Guards against a shim quietly pointing at a slug that was later
        // renamed again — the redirect would 301 to a dead page.
        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');

        $destinations = collect(self::SHIMS)->unique()->values();

        foreach ($destinations as $newSlug) {
            $this->get("/admin/settings/{$newSlug}")->assertSuccessful();
        }
    }
}
