<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Jobs\RegenerateSitemapGroups;
use App\Models\Admin;
use App\Models\BlogPost;
use App\Services\SettingsService;
use App\Services\SitemapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A scheduled post goes public when its time arrives, with nobody saving anything — this command is what puts
 * it into the sitemap then.
 */
class SitemapPublishScheduledTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(string $slug, $publishedAt, ContentStatus $status = ContentStatus::Published): BlogPost
    {
        return BlogPost::create([
            'title' => ['en' => $slug], 'slug' => $slug, 'content' => ['en' => '<p>Body text.</p>'],
            'status' => $status, 'published_at' => $publishedAt, 'author_id' => Admin::factory()->create()->id,
        ]);
    }

    private function ready(): void
    {
        config(['seo.live_sitemap_in_tests' => true]);
        Http::fake();
        app(SitemapService::class)->generateAll();
        app(SettingsService::class)->set('seo.sitemap_live_update', '1');
        Bus::fake();
    }

    #[Test]
    public function a_post_whose_scheduled_time_just_passed_queues_a_blog_sitemap_update(): void
    {
        $this->ready();
        $this->makePost('went-live-just-now', now()->subMinutes(3));

        $this->artisan('sitemap:publish-scheduled')->assertSuccessful();

        Bus::assertDispatched(RegenerateSitemapGroups::class, fn ($job) => $job->groups === ['blog']);
    }

    #[Test]
    public function old_future_and_draft_posts_queue_nothing(): void
    {
        $this->ready();
        $this->makePost('old-post', now()->subHours(5));
        $this->makePost('future-post', now()->addHours(5));
        $this->makePost('draft-post', now()->subMinutes(2), ContentStatus::Draft);
        Bus::fake(); // the saves above queue their own updates; only the command is under test

        $this->artisan('sitemap:publish-scheduled')->assertSuccessful();

        Bus::assertNotDispatched(RegenerateSitemapGroups::class);
    }

    #[Test]
    public function the_command_is_on_the_schedule(): void
    {
        $this->assertTrue(collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->contains(fn ($e) => str_contains((string) $e->command, 'sitemap:publish-scheduled')));
    }
}
