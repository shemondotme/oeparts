<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\PreloaderService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PreloaderServiceTest extends TestCase
{
    use RefreshDatabase;

    private function setRequestPath(string $path): void
    {
        $r = Request::create('http://localhost/'.$path, 'GET');
        $this->app->instance('request', $r);
    }

    private function forgetPreloaderCache(): void
    {
        app(SettingsService::class)->forget('preloader');
    }

    #[Test]
    public function preloader_stays_off_when_enabled_is_zero(): void
    {
        $this->setRequestPath('en');
        $this->assertFalse(app(PreloaderService::class)->shouldRender());
    }

    #[Test]
    public function include_mode_shows_on_locale_root_only(): void
    {
        Setting::updateOrCreate(
            ['group' => 'preloader', 'key' => 'enabled'],
            ['value' => '1', 'type' => 'boolean', 'is_encrypted' => false]
        );
        Setting::updateOrCreate(
            ['group' => 'preloader', 'key' => 'path_mode'],
            ['value' => 'include', 'type' => 'string', 'is_encrypted' => false]
        );
        Setting::updateOrCreate(
            ['group' => 'preloader', 'key' => 'path_patterns'],
            ['value' => json_encode(['en', 'de', 'lt', 'fr', 'es']), 'type' => 'json', 'is_encrypted' => false]
        );
        $this->forgetPreloaderCache();

        $this->setRequestPath('en');
        $this->assertTrue(app(PreloaderService::class)->shouldRender());

        $this->setRequestPath('en/cart');
        $this->assertFalse(app(PreloaderService::class)->shouldRender());
    }

    #[Test]
    public function exclude_mode_blocks_admin_routes(): void
    {
        Setting::updateOrCreate(
            ['group' => 'preloader', 'key' => 'enabled'],
            ['value' => '1', 'type' => 'boolean', 'is_encrypted' => false]
        );
        Setting::updateOrCreate(
            ['group' => 'preloader', 'key' => 'path_mode'],
            ['value' => 'exclude', 'type' => 'string', 'is_encrypted' => false]
        );
        Setting::updateOrCreate(
            ['group' => 'preloader', 'key' => 'path_patterns'],
            ['value' => json_encode(['admin*', 'install*']), 'type' => 'json', 'is_encrypted' => false]
        );
        $this->forgetPreloaderCache();

        $this->setRequestPath('en/parts/ABC');
        $this->assertTrue(app(PreloaderService::class)->shouldRender());

        $this->setRequestPath('admin/login');
        $this->assertFalse(app(PreloaderService::class)->shouldRender());
    }

    #[Test]
    public function all_mode_ignores_patterns(): void
    {
        Setting::updateOrCreate(
            ['group' => 'preloader', 'key' => 'enabled'],
            ['value' => '1', 'type' => 'boolean', 'is_encrypted' => false]
        );
        Setting::updateOrCreate(
            ['group' => 'preloader', 'key' => 'path_mode'],
            ['value' => 'all', 'type' => 'string', 'is_encrypted' => false]
        );
        $this->forgetPreloaderCache();

        $this->setRequestPath('en/cart');
        $this->assertTrue(app(PreloaderService::class)->shouldRender());
    }
}
