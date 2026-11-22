<?php

namespace Tests\Feature;

use App\Http\Middleware\RedirectIfNotInstalled;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Search Console listed "/en?auth=register" (the home page again, opened by the Register link) and addresses ending in
 * "?PageSpeed=noscript" (handed out by a page-speed module on the server) as separate pages. robots.txt now keeps
 * crawlers off both, and keeps the blog list's pages crawlable.
 */
class RobotsCrawlWasteRulesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_crawl_waste_addresses_are_disallowed_and_the_blog_list_stays_open(): void
    {
        $this->seed(SettingsSeeder::class);
        $this->withoutMiddleware(RedirectIfNotInstalled::class);
        $this->app['env'] = 'production';

        $response = $this->get('/robots.txt')->assertStatus(200);

        $response->assertSee("Disallow: /*?auth=\n", false);
        $response->assertSee("Disallow: /*?PageSpeed=\n", false);
        $response->assertSee("Disallow: /*&PageSpeed=\n", false);
        $response->assertSee("Allow: /*/blog?page=\n", false);
    }
}
