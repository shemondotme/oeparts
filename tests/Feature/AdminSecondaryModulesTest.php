<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminSecondaryModulesTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'name' => 'Secondary Admin',
            'email' => 'secondary-admin@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
    }

    #[Test]
    public function reports_index_uses_real_stats_and_truthful_csv_exports(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.reports.index'));

        $response->assertOk();
        $response->assertSee('Reports &amp; Analytics', false);
        $response->assertSee('Export CSV');
        $response->assertDontSee('value="excel"', false);
        $response->assertDontSee('value="pdf"', false);
    }

    #[Test]
    public function secondary_admin_index_surfaces_render_without_errors(): void
    {
        $routeNames = [
            'admin.health.index',
            'admin.logs.activity',
            'admin.logs.login',
            'admin.logs.cron',
            'admin.logs.email',
            'admin.marketing.abandoned-carts.index',
            'admin.translations.index',
            'admin.cms.sections.index',
            'admin.cms.newsletter.index',
            'admin.cms.testimonials.index',
            'admin.cms.faqs.index',
        ];

        foreach ($routeNames as $routeName) {
            $this->actingAs($this->admin, 'admin')
                ->get(route($routeName))
                ->assertOk();
        }
    }
}
