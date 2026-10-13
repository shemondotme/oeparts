<?php

namespace Tests\Feature;

use App\Http\Middleware\RedirectIfNotInstalled;
use App\Models\Setting;
use App\Services\Instance\SafeMode;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 4.3: a copy of the shop tells every crawler to stay away, whatever APP_ENV says. A staging copy is
 * deliberately run production-like (APP_ENV=production), so the mode, not the environment, has to decide.
 */
class RobotsInstanceModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SafeMode::resetLogMemory();
        // RedirectIfNotInstalled bypasses only in the testing environment; forcing production (to prove the mode, not APP_ENV, decides) needs its own bypass.
        $this->withoutMiddleware(RedirectIfNotInstalled::class);
        $this->app['env'] = 'production';
    }

    private function mode(string $mode): void
    {
        config(['instance.mode' => $mode]);
    }

    #[Test]
    public function the_real_shop_still_publishes_its_normal_rules_and_a_sitemap_line(): void
    {
        $this->mode('production');

        $body = $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertStringContainsString('Disallow: /admin/', $body);
        $this->assertStringContainsString('Allow: /', $body);
        $this->assertStringContainsString('Sitemap: ', $body);
        $this->assertStringNotContainsString("Disallow: /\n", $body);
    }

    #[Test]
    #[DataProvider('copyModes')]
    public function a_copy_disallows_everything_and_advertises_no_sitemap_even_with_app_env_production(string $mode): void
    {
        $this->mode($mode);

        $response = $this->get('/robots.txt')->assertOk();
        $body = $response->getContent();

        $this->assertSame("User-agent: *\nDisallow: /\n", $body);
        $this->assertStringNotContainsString('Sitemap', $body);
        $this->assertStringNotContainsString('Allow: /', $body);
        $this->assertStringNotContainsString('GPTBot', $body);
    }

    /** @return array<string, array{0: string}> */
    public static function copyModes(): array
    {
        return ['staging' => ['staging'], 'quarantine' => ['quarantine'], 'typo (fails closed)' => ['stagging']];
    }

    #[Test]
    public function an_admin_ai_bot_allow_rule_cannot_reopen_a_copy(): void
    {
        Setting::updateOrCreate(
            ['group' => 'crawlers', 'key' => 'ai_bot_rules'],
            ['value' => json_encode([['user_agent' => 'GPTBot', 'action' => 'allow']]), 'type' => 'json', 'is_encrypted' => false]
        );
        app(SettingsService::class)->forget('crawlers');
        $this->mode('staging');

        $this->assertStringNotContainsString('GPTBot', (string) $this->get('/robots.txt')->getContent());
    }

    #[Test]
    public function llms_txt_does_not_exist_on_a_copy_and_still_does_on_the_real_shop(): void
    {
        $this->mode('production');
        $this->get('/llms.txt')->assertOk();

        $this->mode('staging');
        $this->get('/llms.txt')->assertNotFound();

        $this->mode('quarantine');
        $this->get('/llms.txt')->assertNotFound();
    }

    #[Test]
    public function the_page_itself_says_noindex_on_a_copy_even_when_a_page_asks_for_index(): void
    {
        $this->mode('production');
        $this->assertStringContainsString('<meta name="robots" content="index,follow">', $this->get('/en/')->getContent());

        $this->mode('staging');
        $html = $this->get('/en/')->getContent();
        $this->assertStringContainsString('<meta name="robots" content="noindex,nofollow">', $html);
        $this->assertStringNotContainsString('content="index', $html);
    }
}
