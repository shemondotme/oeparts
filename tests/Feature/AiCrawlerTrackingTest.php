<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\AiCrawlers;
use App\Models\Admin;
use App\Models\AiCrawlerHit;
use App\Services\AiCrawlerDetector;
use App\Services\SettingsService;
use Database\Seeders\AdminSeeder;
use Database\Seeders\LanguagesSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Daily counters of AI-crawler visits, and the page that shows them next to the robots.txt policy. */
class AiCrawlerTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingsSeeder::class, LanguagesSeeder::class]);
    }

    public function test_known_bots_are_recognised_by_their_user_agent(): void
    {
        $this->assertSame('GPTBot', AiCrawlerDetector::detect('Mozilla/5.0 AppleWebKit/537.36; compatible; GPTBot/1.2; +https://openai.com/gptbot'));
        $this->assertSame('ChatGPT-User', AiCrawlerDetector::detect('Mozilla/5.0 (compatible; ChatGPT-User/1.0)'));
        $this->assertSame('ClaudeBot', AiCrawlerDetector::detect('Mozilla/5.0 (compatible; ClaudeBot/1.0; +claudebot@anthropic.com)'));
        $this->assertSame('OAI-SearchBot', AiCrawlerDetector::detect('OAI-SearchBot/1.0'));
        $this->assertNull(AiCrawlerDetector::detect('Mozilla/5.0 (Windows NT 10.0) Chrome/120 Safari/537.36'));
        $this->assertNull(AiCrawlerDetector::detect('Googlebot/2.1'));
        $this->assertNull(AiCrawlerDetector::detect(null));
    }

    public function test_a_visit_is_counted_once_per_request_in_one_row_per_bot_and_day(): void
    {
        $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; GPTBot/1.2)')->get('/robots.txt')->assertOk();
        $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; GPTBot/1.2)')->get('/llms.txt')->assertOk();
        $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; PerplexityBot/1.0)')->get('/robots.txt')->assertOk();

        $this->assertSame(2, (int) AiCrawlerHit::where('bot', 'GPTBot')->sum('hits'));
        $this->assertSame(1, AiCrawlerHit::where('bot', 'GPTBot')->count());
        $this->assertSame(1, AiCrawlerHit::where('bot', 'PerplexityBot')->sum('hits'));
    }

    public function test_ordinary_visitors_post_requests_and_the_admin_are_not_counted(): void
    {
        $this->withHeader('User-Agent', 'Mozilla/5.0 Chrome/120')->get('/robots.txt');
        $this->withHeader('User-Agent', 'GPTBot/1.0')->post('/en/contact', []);
        $this->withHeader('User-Agent', 'GPTBot/1.0')->get('/admin/login');

        $this->assertSame(0, AiCrawlerHit::count());
    }

    public function test_counting_can_be_switched_off(): void
    {
        app(SettingsService::class)->set('seo.ai_crawler_tracking', 'false');

        $this->withHeader('User-Agent', 'GPTBot/1.0')->get('/robots.txt');

        $this->assertSame(0, AiCrawlerHit::count());
    }

    public function test_the_page_lists_the_bots_with_their_robots_policy(): void
    {
        $this->seed([RolesSeeder::class, AdminSeeder::class]);
        $this->actingAs(Admin::where('email', 'superadmin@oeparts.test')->firstOrFail(), 'admin');
        AiCrawlerHit::create(['hit_date' => now()->toDateString(), 'bot' => 'GPTBot', 'hits' => 42, 'last_seen_at' => now()]);
        AiCrawlerHit::create(['hit_date' => now()->subDays(40)->toDateString(), 'bot' => 'CCBot', 'hits' => 9, 'last_seen_at' => now()->subDays(40)]);

        Livewire::test(AiCrawlers::class)
            ->assertOk()
            ->assertSee('GPTBot')
            ->assertSee('OpenAI')
            ->assertSee('Allowed in robots.txt')
            ->assertSee('42')
            ->assertDontSee('CCBot'); // outside the 30-day window
    }
}
