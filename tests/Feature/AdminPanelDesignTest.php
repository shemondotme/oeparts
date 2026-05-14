<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminPanelDesignTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'name' => 'Design Admin',
            'email' => 'design-admin@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
    }

    #[Test]
    public function product_catalog_index_renders_blueprint_shell(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.catalog.products.index'));

        $response->assertOk();
        $response->assertViewIs('admin.catalog.products.index');
        $response->assertSee('Product Catalog');
        $response->assertSee('Search OEM number');
        $response->assertSee('No products found');
    }

    #[Test]
    public function admin_bundle_defines_shared_blueprint_primitives(): void
    {
        $adminCss = file_get_contents(resource_path('css/admin.css'));

        $this->assertStringContainsString("@fontsource/plus-jakarta-sans/700.css", $adminCss);
        $this->assertStringContainsString('.bp-card', $adminCss);
        $this->assertStringContainsString('.bp-spec', $adminCss);
        $this->assertStringContainsString('.bp-btn-primary', $adminCss);
        $this->assertStringContainsString('.bp-input', $adminCss);
    }
}
