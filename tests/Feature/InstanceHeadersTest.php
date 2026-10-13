<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Services\Instance\SafeMode;
use App\Support\InstanceBanner;
use Database\Seeders\AdminSeeder;
use Database\Seeders\LanguagesSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 4.3: every response from a copy says "do not index" in the header too, so a file, an image or an API
 * answer is covered as well as a page, and the storefront and the admin panel both show that this is not the
 * real shop.
 */
class InstanceHeadersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SafeMode::resetLogMemory();
    }

    private function mode(string $mode): void
    {
        config(['instance.mode' => $mode]);
    }

    #[Test]
    public function the_real_shop_sends_no_instance_header(): void
    {
        $this->mode('production');

        $response = $this->get('/en/');

        $this->assertNull($response->headers->get('X-Robots-Tag'));
        $this->assertNull($response->headers->get('X-OE-Instance-Mode'));
    }

    #[Test]
    public function a_copy_marks_every_kind_of_response(): void
    {
        $this->mode('staging');

        foreach (['/en/', '/robots.txt', '/health', '/en/this-page-does-not-exist', '/up'] as $path) {
            $response = $this->get($path);

            $this->assertSame('noindex, nofollow, noarchive', $response->headers->get('X-Robots-Tag'), $path);
            $this->assertSame('staging', $response->headers->get('X-OE-Instance-Mode'), $path);
        }
    }

    #[Test]
    public function a_json_answer_and_a_redirect_carry_the_header_too(): void
    {
        $this->mode('quarantine');

        $this->assertSame('noindex, nofollow, noarchive', $this->getJson('/api/v1/products')->headers->get('X-Robots-Tag'));
        $this->assertSame('noindex, nofollow, noarchive', $this->get('/')->headers->get('X-Robots-Tag'));
    }

    #[Test]
    public function the_storefront_of_a_copy_shows_a_ribbon_and_the_real_shop_does_not(): void
    {
        $this->mode('production');
        $this->assertStringNotContainsString('data-instance-ribbon', $this->get('/en/')->getContent());

        $this->mode('staging');
        $html = $this->get('/en/')->getContent();
        $this->assertStringContainsString('data-instance-ribbon="staging"', $html);
        $this->assertStringContainsString('STAGING', $html);

        $this->mode('quarantine');
        $this->assertStringContainsString('data-instance-ribbon="quarantine"', $this->get('/en/')->getContent());
    }

    #[Test]
    public function a_typo_in_the_mode_shows_the_quarantine_ribbon(): void
    {
        $this->mode('stagging');

        $this->assertStringContainsString('data-instance-ribbon="quarantine"', $this->get('/en/')->getContent());
    }

    #[Test]
    public function the_admin_banner_is_stock_filament_parts_and_names_the_mode(): void
    {
        $banner = app(InstanceBanner::class);

        $this->mode('production');
        $this->assertSame('', $banner->render());

        $this->mode('staging');
        $html = $banner->render();
        $this->assertStringContainsString('fi-section', $html);
        $this->assertStringContainsString('fi-badge', $html);
        $this->assertStringContainsString('STAGING', $html);
        $this->assertStringContainsString('masking', $html, 'it says how many settings are being hidden');

        $this->mode('quarantine');
        $this->assertStringContainsString('QUARANTINE', $banner->render());
    }

    #[Test]
    public function a_typo_gets_a_red_banner_that_says_the_value_is_wrong(): void
    {
        $this->mode('stagging');

        $html = app(InstanceBanner::class)->render();

        $this->assertStringContainsString('QUARANTINE', $html);
        $this->assertStringContainsString('stagging', $html);
        $this->assertStringContainsString('OE_MODE', $html);
    }

    #[Test]
    public function the_admin_panel_shows_the_banner_on_a_copy_and_not_on_the_real_shop(): void
    {
        $this->seed([SettingsSeeder::class, LanguagesSeeder::class, RolesSeeder::class, AdminSeeder::class]);
        $this->actingAs(Admin::where('email', 'superadmin@oeparts.test')->firstOrFail(), 'admin');

        $this->mode('production');
        $this->get('/admin')->assertOk()->assertDontSee('Safe mode is masking', false);

        $this->mode('staging');
        $this->get('/admin')->assertOk()
            ->assertSee('STAGING', false)
            ->assertSee('Safe mode is masking', false);

        $response = $this->get('/admin');
        $this->assertSame('noindex, nofollow, noarchive', $response->headers->get('X-Robots-Tag'));
    }
}
