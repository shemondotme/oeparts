<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The shared layout only emits a <link rel="preconnect"> for a third-party
 * origin when that integration is actually configured — an unconditional
 * hint just wastes an early connection slot on a service the site never
 * calls.
 */
class ResourceHintsTest extends TestCase
{
    use RefreshDatabase;

    private function setIntegration(string $key, string $value): void
    {
        Setting::updateOrCreate(
            ['group' => 'integrations', 'key' => $key],
            ['value' => $value, 'type' => 'string', 'is_encrypted' => false]
        );
        app(SettingsService::class)->forget('integrations');
    }

    #[Test]
    public function no_preconnect_hints_render_without_any_integration_configured(): void
    {
        $response = $this->get('/en/');

        $response->assertDontSee('rel="preconnect"', false);
    }

    #[Test]
    public function gtm_configured_emits_a_googletagmanager_preconnect(): void
    {
        $this->setIntegration('gtm_id', 'GTM-ABCDEF');

        $this->get('/en/')->assertSee('preconnect" href="https://www.googletagmanager.com"', false);
    }

    #[Test]
    public function fb_pixel_configured_emits_a_facebook_preconnect(): void
    {
        $this->setIntegration('fb_pixel_id', '123456789');

        $this->get('/en/')->assertSee('preconnect" href="https://connect.facebook.net"', false);
    }

    #[Test]
    public function crisp_configured_emits_a_crisp_preconnect(): void
    {
        $this->setIntegration('crisp_website_id', 'crisp-uuid');

        $this->get('/en/')->assertSee('preconnect" href="https://client.crisp.chat"', false);
    }
}
