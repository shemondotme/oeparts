<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IndexNowControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function correct_key_returns_the_key_as_plain_text(): void
    {
        Setting::updateOrCreate(['group' => 'seo', 'key' => 'indexnow_api_key'], ['value' => 'testkey123', 'type' => 'encrypted', 'is_encrypted' => true]);
        app(SettingsService::class)->forget('seo');

        $response = $this->get('/testkey123.txt');

        $response->assertStatus(200);
        $response->assertSee('testkey123');
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    #[Test]
    public function wrong_key_returns_404(): void
    {
        Setting::updateOrCreate(['group' => 'seo', 'key' => 'indexnow_api_key'], ['value' => 'testkey123', 'type' => 'encrypted', 'is_encrypted' => true]);
        app(SettingsService::class)->forget('seo');

        $response = $this->get('/wrongkey.txt');

        $response->assertStatus(404);
    }

    #[Test]
    public function no_configured_key_returns_404(): void
    {
        $response = $this->get('/anything.txt');

        $response->assertStatus(404);
    }
}
