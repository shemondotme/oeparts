<?php

namespace Tests\Feature;

use App\Jobs\PushIndexNow;
use App\Models\Setting;
use App\Services\Instance\SafeMode;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 4.3: a copy that has IndexNow switched on and a key stored (both copied from the real shop) must still
 * neither publish the key file nor tell Bing and Yandex that its test pages exist.
 */
class LlmsTxtAndIndexNowNonProdTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SafeMode::resetLogMemory();
        Setting::create(['group' => 'seo', 'key' => 'indexnow_enabled', 'value' => '1', 'type' => 'boolean', 'is_encrypted' => false]);
        Setting::create(['group' => 'seo', 'key' => 'indexnow_api_key', 'value' => Crypt::encryptString('abc123key'), 'type' => 'string', 'is_encrypted' => true]);
        app(SettingsService::class)->forget('seo');
    }

    private function mode(string $mode): void
    {
        config(['instance.mode' => $mode]);
    }

    #[Test]
    public function the_real_shop_serves_the_key_file_and_pushes(): void
    {
        $this->mode('production');
        Http::fake(['api.indexnow.org/*' => Http::response('', 200)]);

        $this->get('/abc123key.txt')->assertOk()->assertSee('abc123key', false);

        (new PushIndexNow(['https://shop.example/en/p/1']))->handle();

        Http::assertSentCount(1);
    }

    #[Test]
    public function a_copy_does_not_publish_the_key_file(): void
    {
        foreach (['staging', 'quarantine'] as $mode) {
            $this->mode($mode);

            $this->get('/abc123key.txt')->assertNotFound();
        }
    }

    #[Test]
    public function a_copy_never_calls_the_indexnow_api(): void
    {
        Http::fake();

        foreach (['staging', 'quarantine', 'a-typo'] as $mode) {
            $this->mode($mode);

            (new PushIndexNow(['https://shop.example/en/p/1']))->handle();
        }

        Http::assertNothingSent();
    }

    #[Test]
    public function the_stored_switch_and_key_are_not_touched_by_any_of_this(): void
    {
        $this->mode('staging');
        $this->get('/abc123key.txt');
        (new PushIndexNow(['https://shop.example/en/p/1']))->handle();

        $this->assertSame('1', Setting::where('group', 'seo')->where('key', 'indexnow_enabled')->value('value'));
        $this->assertNotSame('', Setting::where('group', 'seo')->where('key', 'indexnow_api_key')->value('value'));
    }
}
