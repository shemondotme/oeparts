<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LlmsTxtTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function returns_200_plain_text_with_the_configured_body(): void
    {
        $response = $this->get('/llms.txt');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->assertSee('OeParts', false);
    }

    #[Test]
    public function site_url_placeholder_is_substituted(): void
    {
        $response = $this->get('/llms.txt');

        $response->assertDontSee('{site_url}', false);
        $response->assertSee(url('/'), false);
    }

    #[Test]
    public function respects_an_admin_configured_body(): void
    {
        Setting::updateOrCreate(
            ['group' => 'crawlers', 'key' => 'llms_txt_body'],
            ['value' => json_encode(['en' => 'Custom llms.txt body for testing.']), 'type' => 'json', 'is_encrypted' => false]
        );
        app(SettingsService::class)->forget('crawlers');

        $response = $this->get('/llms.txt');

        $response->assertSee('Custom llms.txt body for testing.', false);
    }
}
