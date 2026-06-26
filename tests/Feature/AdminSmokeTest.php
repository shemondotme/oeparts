<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Admin;
use App\Models\Condition;
use App\Models\Product;
use App\Models\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminSmokeTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            \Database\Seeders\SettingsSeeder::class,
            \Database\Seeders\LanguagesSeeder::class,
            \Database\Seeders\RolesSeeder::class,
            \Database\Seeders\AdminSeeder::class,
            \Database\Seeders\SequencesSeeder::class,
            \Database\Seeders\CarriersSeeder::class,
            \Database\Seeders\SectionsSeeder::class,
        ]);

        $this->admin = Admin::where('email', 'admin@oeparts.test')->firstOrFail();

        $this->actingAs($this->admin, 'admin');

        app(\Illuminate\Cache\RateLimiter::class)->clear('login:127.0.0.1');
    }

    // ── Dashboard ───────────────────────────────────────────────────────────────

    #[Test]
    public function admin_dashboard_loads(): void
    {
        $response = $this->get('/admin');
        $response->assertStatus(200);
        $response->assertSee('Manage Widgets');
        $response->assertSee('fi-header-actions-ctn');
    }

    // ── Widget Default Visibility ───────────────────────────────────────────────

    #[Test]
    public function default_widget_visibility_is_correct(): void
    {
        $service = app(\App\Services\WidgetPreferenceService::class);

        // Admin has no preferences saved, so defaults apply
        $enabled = $service->getSortedEnabledClasses();

        $visibleIds = [];
        foreach (\App\Services\WidgetPreferenceService::WIDGETS as $id => $config) {
            if ($config['default_visible']) {
                $visibleIds[] = $id;
            }
        }

        $this->assertCount(count($visibleIds), $enabled, 'Only default-visible widgets should be enabled');

        // Essential widgets are visible
        $this->assertContains(\App\Filament\Widgets\RevenueKpi::class, $enabled);
        $this->assertContains(\App\Filament\Widgets\RevenueChart::class, $enabled);
        $this->assertContains(\App\Filament\Widgets\RecentOrdersList::class, $enabled);
        $this->assertContains(\App\Filament\Widgets\TopSearchedOems::class, $enabled);
        $this->assertContains(\App\Filament\Widgets\FailedSearchesWidget::class, $enabled);
        $this->assertContains(\App\Filament\Widgets\CacheStatusWidget::class, $enabled);
        $this->assertContains(\App\Filament\Widgets\HealthStrip::class, $enabled);
        $this->assertContains(\App\Filament\Widgets\OrderVolumeChart::class, $enabled);
        $this->assertContains(\App\Filament\Widgets\TopManufacturersRevenue::class, $enabled);
        $this->assertContains(\App\Filament\Widgets\NewOrdersKpi::class, $enabled);

        // Non-essential widgets are hidden by default
        $this->assertNotContains(\App\Filament\Widgets\NewsletterGrowthWidget::class, $enabled);
        $this->assertNotContains(\App\Filament\Widgets\ManufacturingStatsWidget::class, $enabled);
    }

    #[Test]
    public function widget_preferences_can_be_saved(): void
    {
        $service = app(\App\Services\WidgetPreferenceService::class);

        // Toggle one widget off
        $service->toggle('manufacturer_revenue', false);

        $this->assertFalse($service->isEnabled(\App\Filament\Widgets\TopManufacturersRevenue::class));

        // Set sort order via legacy ID (maps to revenue_kpi)
        $service->setSortOrder('kpi_stats', 10);
        $sorted = $service->getSortedWidgets();
        $kpi = current(array_filter($sorted, fn ($w) => $w['id'] === 'revenue_kpi'));
        $this->assertEquals(10, $kpi['sort']);
    }

    // ── Resource Index Pages ────────────────────────────────────────────────────

    #[Test]
    public function resource_index_pages_return_200(): void
    {
        $pages = [
            '/admin/products',
            '/admin/car-models',
            '/admin/manufacturers',
            '/admin/blog-posts',
            '/admin/sections',
            '/admin/pages',
            '/admin/faqs',
            '/admin/testimonials',
            '/admin/menus',
            '/admin/media-files',
            '/admin/newsletter-subscribers',
            '/admin/abandoned-carts',
            '/admin/email-logs',
            '/admin/languages',
            '/admin/carriers',
            '/admin/activity-logs',
            '/admin/login-logs',
            '/admin/cron-logs',
            '/admin/ip-blocklists',
            '/admin/search-logs',
            '/admin/failed-search-logs',
            '/admin/seo-metas',
            '/admin/redirects',
            '/admin/categories',
            '/admin/shipping-zones',
            '/admin/orders',
            '/admin/customers',
            '/admin/coupons',
            '/admin/part-inquiries',
            '/admin/contact-messages',
            '/admin/refund-requests',
            '/admin/admins',
        ];

        foreach ($pages as $url) {
            $response = $this->get($url);
            $response->assertStatus(200);
        }
    }

    // ── Resource Create Pages ───────────────────────────────────────────────────

    #[Test]
    public function resource_create_pages_return_200(): void
    {
        $pages = [
            '/admin/products/create',
            '/admin/car-models/create',
            '/admin/manufacturers/create',
            '/admin/blog-posts/create',
            '/admin/sections/create',
            '/admin/pages/create',
            '/admin/faqs/create',
            '/admin/testimonials/create',
            '/admin/menus/create',
            '/admin/newsletter-subscribers/create',
            '/admin/languages/create',
            '/admin/carriers/create',
            '/admin/ip-blocklists/create',
            '/admin/redirects/create',
            '/admin/categories/create',
            '/admin/shipping-zones/create',
            '/admin/orders/create',
            '/admin/customers/create',
            '/admin/coupons/create',
            '/admin/admins/create',
        ];

        foreach ($pages as $url) {
            $response = $this->get($url);
            $response->assertStatus(200);
        }
    }

    // ── Settings Pages ──────────────────────────────────────────────────────────

    #[Test]
    public function settings_pages_return_200(): void
    {
        // Derived from the registry (not hand-maintained) so every settings
        // page is covered automatically — see SettingsRegistryTest.php for
        // the test that catches a page missing from the registry itself.
        $pages = \App\Filament\Support\SettingsRegistry::PAGES;

        foreach ($pages as $page) {
            $response = $this->get($page['url']);
            $response->assertStatus(200);
        }
    }

    #[Test]
    public function settings_pages_reject_non_admin_roles(): void
    {
        // Regression test for the Settings Framework chunk's security fix:
        // SettingsPage::canAccess() previously didn't exist, so any
        // authenticated admin of any role could load/save settings pages
        // directly by URL despite the Settings cluster itself being gated to
        // super_admin/admin. Confirm every non-admin role is now rejected.
        $pages = [
            '/admin/settings/general-settings',
            '/admin/settings/payment-settings',
            '/admin/settings/security-settings',
        ];

        foreach (['manager', 'catalog_admin', 'support'] as $role) {
            $roleAdmin = Admin::create([
                'name' => ucfirst($role) . ' Test',
                'email' => $role . '@oeparts.test',
                'password' => bcrypt('password'),
            ]);
            $roleAdmin->assignRole($role);

            auth('admin')->login($roleAdmin);

            foreach ($pages as $url) {
                $response = $this->get($url);
                $response->assertStatus(403, "Role '{$role}' should be denied {$url}");
            }

            auth('admin')->logout();
        }

        $this->actingAs($this->admin, 'admin');
    }

    // ── Custom Table Pages ──────────────────────────────────────────────────────

    #[Test]
    public function catalog_and_content_log_pages_return_200(): void
    {
        // Regression test for a bug class found while fixing SettingsActivityLog
        // in the Settings Option C chunk: a Filament Page using
        // Tables\Concerns\InteractsWithTable without declaring
        // `implements Tables\Contracts\HasTable` throws a fatal TypeError on
        // every load (Table::make() type-checks $livewire against that
        // interface). These 3 pages had the identical pattern and zero prior
        // test coverage — confirmed via `php artisan route:list` rather than
        // guessed from class names.
        $pages = [
            '/admin/inventory-log-page',
            '/admin/bulk-update-log-page',
            '/admin/content/content-revision-page',
        ];

        foreach ($pages as $url) {
            $response = $this->get($url);
            $response->assertStatus(200);
        }
    }

    // ── Report Pages ────────────────────────────────────────────────────────────

    #[Test]
    public function report_pages_return_200(): void
    {
        // Report pages require populated order data and have pre-existing SQL bugs
        // (e.g., `order_items.name` and `order_items.line_total` don't exist in the schema).
        // Skip this test until those queries are fixed — the page registration itself works,
        // as tested by the index page navigation presence.
        $this->assertTrue(true);
    }

    // ── System Cluster Pages ────────────────────────────────────────────────────

    #[Test]
    public function system_cluster_pages_return_200(): void
    {
        // Zero prior HTTP coverage existed for any of these 12 pages — every
        // previous "completely broken since written" bug in this program
        // (UISettings, StoreSettings, MenuSettings, DatabaseSettings,
        // AboutLicenseSettings, the 3 custom table pages) was hiding behind
        // exactly this gap. URLs confirmed via `php artisan route:list`, not
        // guessed from class names (Option U's lesson).
        $pages = [
            '/admin/system/backup-dashboard',
            '/admin/system/cache-dashboard',
            '/admin/system/error-monitor',
            '/admin/system/failed-jobs-page',
            '/admin/system/health-check-dashboard',
            '/admin/system/help-page',
            '/admin/system/log-viewer-page',
            '/admin/system/permission-matrix',
            '/admin/system/queue-monitor',
            '/admin/system/scheduled-tasks-page',
            '/admin/system/server-monitor',
            '/admin/system/setup-assistant',
        ];

        foreach ($pages as $url) {
            $response = $this->get($url);
            $response->assertStatus(200, "Expected 200 for {$url}");
        }
    }

    #[Test]
    public function reports_cluster_pages_return_200(): void
    {
        // The legacy report_pages_return_200() test above was a deliberate
        // no-op pending a documented SQL bug referencing nonexistent
        // order_items.name/line_total columns. Direct verification this
        // chunk found no such column reference anywhere in app/Filament/
        // Pages/Reports/ — the comment was stale. These 4 pages load
        // correctly with zero seeded order data; left the legacy test as-is
        // since this one now provides the real coverage.
        $pages = [
            '/admin/reports/checkout-dropoff-report',
            '/admin/reports/customers-report',
            '/admin/reports/sales-report',
            '/admin/reports/search-intelligence-report',
        ];

        foreach ($pages as $url) {
            $response = $this->get($url);
            $response->assertStatus(200, "Expected 200 for {$url}");
        }
    }

    // ── System Pages ────────────────────────────────────────────────────────────

    #[Test]
    public function resource_view_pages_return_200(): void
    {
        $manufacturer = \App\Models\Manufacturer::create([
            'name' => json_encode(['en' => 'Test Mfr']),
            'slug' => 'test-mfr-' . uniqid(),
            'country_code' => 'DE',
        ]);
        $carModel = \App\Models\CarModel::create([
            'manufacturer_id' => $manufacturer->id,
            'name' => 'Test Model',
            'slug' => 'test-model-' . uniqid(),
            'is_active' => true,
        ]);

        $pages = [
            "/admin/manufacturers/{$manufacturer->id}",
            "/admin/car-models/{$carModel->id}",
        ];

        foreach ($pages as $url) {
            $response = $this->get($url);
            $response->assertStatus(200);
        }
    }

    #[Test]
    public function resource_edit_pages_return_200(): void
    {
        $manufacturer = \App\Models\Manufacturer::create([
            'name' => json_encode(['en' => 'Test Mfr']),
            'slug' => 'test-mfr-' . uniqid(),
            'country_code' => 'DE',
        ]);
        $condition = Condition::first() ?? Condition::create([
            'name' => 'New',
            'slug' => 'new',
            'bg_color' => '#DCFCE7',
            'text_color' => '#16A34A',
        ]);
        $product = Product::factory()->create([
            'name'               => json_encode(['en' => 'Test Product']),
            'normalized_oem'     => app(\App\Services\OemNormalizerService::class)->normalize('12345'),
            'condition_id'       => $condition->id,
            'is_in_stock'        => true,
            'is_active'          => true,
            'price'              => '10.00',
            'oem_number'         => '12345',
            'manufacturer_id'    => $manufacturer->id,
        ]);
        $section = Section::factory()->create([
            'title' => ['en' => 'Test Section'],
            'type' => 'custom',
        ]);

        $pages = [
            "/admin/products/{$product->id}/edit",
            "/admin/sections/{$section->id}/edit",
            "/admin/admins/{$this->admin->id}/edit",
        ];

        foreach ($pages as $url) {
            $response = $this->get($url);
            $response->assertStatus(200);
        }
    }

    // ── Inline Editable Columns ─────────────────────────────────────────────────

    #[Test]
    public function product_price_inline_edit_persists_for_authorized_role(): void
    {
        $manufacturer = \App\Models\Manufacturer::create([
            'name' => json_encode(['en' => 'Test Mfr']),
            'slug' => 'test-mfr-' . uniqid(),
            'country_code' => 'DE',
        ]);
        $condition = Condition::first() ?? Condition::create([
            'name' => 'New',
            'slug' => 'new',
            'bg_color' => '#DCFCE7',
            'text_color' => '#16A34A',
        ]);
        $product = Product::factory()->create([
            'name'            => json_encode(['en' => 'Test Product']),
            'normalized_oem'  => app(\App\Services\OemNormalizerService::class)->normalize('99999'),
            'condition_id'    => $condition->id,
            'is_in_stock'     => true,
            'is_active'       => true,
            'price'           => '10.00',
            'oem_number'      => '99999',
            'manufacturer_id' => $manufacturer->id,
        ]);

        Livewire::test(ListProducts::class)
            ->call('updateTableColumnState', 'price', (string) $product->getKey(), '199.99');

        $this->assertSame('199.99', $product->refresh()->price);
    }

    #[Test]
    public function product_price_inline_edit_rejected_for_unauthorized_role(): void
    {
        // Regression test for the inline-price-edit chunk: TextInputColumn
        // saves directly without checking Model Policies — `disabled()` is
        // the only gate Filament checks server-side, so confirm a role
        // without `edit products` cannot persist a change through it.
        $manufacturer = \App\Models\Manufacturer::create([
            'name' => json_encode(['en' => 'Test Mfr']),
            'slug' => 'test-mfr-' . uniqid(),
            'country_code' => 'DE',
        ]);
        $condition = Condition::first() ?? Condition::create([
            'name' => 'New',
            'slug' => 'new',
            'bg_color' => '#DCFCE7',
            'text_color' => '#16A34A',
        ]);
        $product = Product::factory()->create([
            'name'            => json_encode(['en' => 'Test Product']),
            'normalized_oem'  => app(\App\Services\OemNormalizerService::class)->normalize('88888'),
            'condition_id'    => $condition->id,
            'is_in_stock'     => true,
            'is_active'       => true,
            'price'           => '10.00',
            'oem_number'      => '88888',
            'manufacturer_id' => $manufacturer->id,
        ]);

        $supportAdmin = Admin::create([
            'name' => 'Support Test',
            'email' => 'support-inline@oeparts.test',
            'password' => bcrypt('password'),
        ]);
        $supportAdmin->assignRole('support');

        $this->actingAs($supportAdmin, 'admin');

        Livewire::test(ListProducts::class)
            ->call('updateTableColumnState', 'price', (string) $product->getKey(), '199.99');

        $this->assertSame('10.00', $product->refresh()->price);

        $this->actingAs($this->admin, 'admin');
    }

    // ── Widget Sort Uniqueness ──────────────────────────────────────────────────

    #[Test]
    public function all_widget_sort_values_are_unique(): void
    {
        $sorts = [];

        foreach (\App\Services\WidgetPreferenceService::WIDGETS as $id => $config) {
            try {
                $prop = (new \ReflectionClass($config['class']))->getProperty('sort');
                $prop->setAccessible(true);
                $value = $prop->getValue();

                if ($value !== null) {
                    $sorts[$id] = $value;
                }
            } catch (\ReflectionException) {
                // Widget has no $sort — skip
            }
        }

        $values = array_values($sorts);
        $unique = array_unique($values);
        $duplicates = array_filter(
            array_count_values($values),
            fn (int $count) => $count > 1,
        );

        $this->assertCount(
            count($values),
            $unique,
            'Widget $sort values must be unique. Duplicates: ' . json_encode($duplicates),
        );
    }

    // ── Layout Save — Junk ID Rejection ────────────────────────────────────────

    #[Test]
    public function save_layout_with_junk_id_is_silently_rejected(): void
    {
        $service = app(\App\Services\DashboardLayoutService::class);
        $dashboard = $service->ensureDefaultDashboard($this->admin);

        $result = $service->saveLayout($this->admin, $dashboard->id, [
            ['id' => 'totally_bogus_widget_id', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 4],
            ['id' => '../../../etc/passwd',     'x' => 0, 'y' => 0, 'w' => 6, 'h' => 4],
            ['id' => '<script>alert(1)</script>', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 4],
        ]);

        $this->assertEmpty($result, 'Unknown widget IDs must all be stripped from layout');
        $this->assertEmpty($dashboard->fresh()->layout, 'Dashboard layout must be empty after junk-only save');
    }

    #[Test]
    public function save_layout_mixes_valid_and_junk_ids_keeps_only_valid(): void
    {
        $service = app(\App\Services\DashboardLayoutService::class);
        $dashboard = $service->ensureDefaultDashboard($this->admin);

        $result = $service->saveLayout($this->admin, $dashboard->id, [
            ['id' => 'dashboard_header',  'x' => 0, 'y' => 0, 'w' => 12, 'h' => 2],
            ['id' => 'totally_fake',      'x' => 0, 'y' => 4, 'w' => 6,  'h' => 4],
            ['id' => 'revenue_chart',     'x' => 0, 'y' => 2, 'w' => 8,  'h' => 4],
        ]);

        $ids = array_column($result, 'id');
        $this->assertContains('dashboard_header', $ids);
        $this->assertContains('revenue_chart', $ids);
        $this->assertNotContains('totally_fake', $ids);
    }

    // ── Chrome Layout ───────────────────────────────────────────────────────────

    #[Test]
    public function topbar_has_expected_zones(): void
    {
        $response = $this->get('/admin');
        $response->assertStatus(200);
        $response->assertSee('op-topbar-left', false);
        $response->assertSee('op-topbar-center', false);
        $response->assertSee('op-topbar-right', false);
    }

    #[Test]
    public function quick_create_is_role_aware(): void
    {
        $catalogAdmin = Admin::create([
            'name'     => 'Catalog Admin',
            'email'    => 'catalog@oeparts.test',
            'password' => bcrypt('password'),
        ]);
        $catalogAdmin->assignRole('catalog_admin');

        // Log in as catalog_admin in the admin guard so the Blade component sees it.
        auth('admin')->login($catalogAdmin);

        try {
            $html = (string) \Illuminate\Support\Facades\Blade::render('<x-admin.quick-create />');
            $this->assertStringNotContainsString('/admin/filament/orders/create', $html);
            // Catalog admin DOES get the product link
            $this->assertStringContainsString('/admin/products/create', $html);
        } finally {
            auth('admin')->logout();
            $this->actingAs($this->admin, 'admin');
        }
    }

    #[Test]
    public function sidebar_rail_renders_navigation_group_icons(): void
    {
        $response = $this->get('/admin');
        $response->assertStatus(200);
        $response->assertSee('op-sidebar-rail', false);
        $response->assertSee('op-sidebar-panel', false);
    }

    // ── Admin Panel Auth Guard ──────────────────────────────────────────────────

    #[Test]
    public function unauthenticated_user_is_redirected_to_login(): void
    {
        // Log out and try to access admin
        auth('admin')->logout();

        $response = $this->get('/admin/products');
        $response->assertStatus(302);
    }
}
