<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RobotsControllerAiRulesTest extends TestCase
{
    use RefreshDatabase;

    private function setAiBotRules(array $rules): void
    {
        Setting::updateOrCreate(
            ['group' => 'crawlers', 'key' => 'ai_bot_rules'],
            ['value' => json_encode($rules), 'type' => 'json', 'is_encrypted' => false]
        );
        app(SettingsService::class)->forget('crawlers');
    }

    #[Test]
    public function default_seeded_rules_allow_known_ai_bots_in_production(): void
    {
        $this->setAiBotRules([
            ['user_agent' => 'GPTBot', 'action' => 'allow'],
            ['user_agent' => 'PerplexityBot', 'action' => 'allow'],
        ]);
        // RedirectIfNotInstalled explicitly bypasses on app()->environment('testing')
        // — forcing 'production' here to exercise RobotsController's own
        // production check also defeats that OTHER middleware's testing
        // carve-out, so it needs its own explicit bypass.
        $this->withoutMiddleware(\App\Http\Middleware\RedirectIfNotInstalled::class);
        $this->app['env'] = 'production';

        $response = $this->get('/robots.txt');

        $response->assertStatus(200);
        $response->assertSee("User-agent: GPTBot\nAllow: /", false);
        $response->assertSee("User-agent: PerplexityBot\nAllow: /", false);
    }

    #[Test]
    public function a_bot_configured_to_block_gets_its_own_disallow_block_scoped_to_that_bot_only(): void
    {
        $this->setAiBotRules([
            ['user_agent' => 'SomeScraperBot', 'action' => 'block'],
        ]);
        // RedirectIfNotInstalled explicitly bypasses on app()->environment('testing')
        // — forcing 'production' here to exercise RobotsController's own
        // production check also defeats that OTHER middleware's testing
        // carve-out, so it needs its own explicit bypass.
        $this->withoutMiddleware(\App\Http\Middleware\RedirectIfNotInstalled::class);
        $this->app['env'] = 'production';

        $response = $this->get('/robots.txt');
        $body = $response->getContent();

        $response->assertSee("User-agent: SomeScraperBot\nDisallow: /", false);
        // The wildcard block (User-agent: *) must still say Allow: / —
        // blocking one specific bot must not affect the general rule.
        $this->assertStringContainsString("User-agent: *", $body);
        $this->assertStringContainsString("Allow: /", $body);
    }

    #[Test]
    public function ai_bot_rules_are_omitted_entirely_outside_production(): void
    {
        $this->setAiBotRules([
            ['user_agent' => 'GPTBot', 'action' => 'allow'],
        ]);
        $this->withoutMiddleware(\App\Http\Middleware\RedirectIfNotInstalled::class);
        $this->app['env'] = 'staging';

        $response = $this->get('/robots.txt');

        // Non-production blocks everything via the wildcard rule — adding
        // a bot-specific Allow: / block here would re-open exactly the
        // bot this environment must stay fully blocked from.
        $response->assertDontSee('GPTBot', false);
        $response->assertSee('Disallow: /', false);
    }

    #[Test]
    public function no_configured_rules_still_renders_a_valid_robots_txt(): void
    {
        // RedirectIfNotInstalled explicitly bypasses on app()->environment('testing')
        // — forcing 'production' here to exercise RobotsController's own
        // production check also defeats that OTHER middleware's testing
        // carve-out, so it needs its own explicit bypass.
        $this->withoutMiddleware(\App\Http\Middleware\RedirectIfNotInstalled::class);
        $this->app['env'] = 'production';

        $response = $this->get('/robots.txt');

        $response->assertStatus(200);
        $response->assertSee('Sitemap:', false);
    }

    #[Test]
    public function cache_control_max_age_is_300_not_3600(): void
    {
        $response = $this->get('/robots.txt');

        // Symfony re-serializes Cache-Control directives in its own
        // canonical order regardless of how the controller wrote them —
        // assert on the directive value, not the exact header string.
        $this->assertStringContainsString('max-age=300', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
    }
}
