<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Jobs\PushIndexNow;
use App\Models\Admin;
use App\Models\BlogPost;
use App\Models\CarModel;
use App\Models\Manufacturer;
use App\Models\Page;
use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Only products got a proactive IndexNow push (ProductObserver) — blog
 * posts, CMS pages, manufacturer pages, and car-model pages all relied
 * purely on the once-daily sitemap regeneration + organic crawl. Each
 * content type's observer now pushes the same way, gated on the same
 * "is this URL actually reachable" condition its own sitemap generator
 * already uses.
 */
class ContentIndexNowTest extends TestCase
{
    use RefreshDatabase;

    private function enableIndexNow(): void
    {
        Setting::updateOrCreate(['group' => 'seo', 'key' => 'indexnow_enabled'], ['value' => '1', 'type' => 'boolean', 'is_encrypted' => false]);
        Setting::updateOrCreate(['group' => 'seo', 'key' => 'indexnow_api_key'], ['value' => 'testkey123', 'type' => 'encrypted', 'is_encrypted' => true]);
        app(SettingsService::class)->forget('seo');
    }

    #[Test]
    public function a_published_blog_post_is_pushed_to_indexnow(): void
    {
        $this->enableIndexNow();
        Bus::fake();
        $admin = Admin::factory()->create();

        BlogPost::create([
            'title' => ['en' => 'How To Choose Brake Pads'], 'slug' => 'brake-pads-guide-'.uniqid(),
            'content' => ['en' => 'x'], 'status' => ContentStatus::Published,
            'published_at' => now()->subDay(), 'author_id' => $admin->id,
        ]);

        Bus::assertDispatched(PushIndexNow::class);
    }

    #[Test]
    public function a_draft_blog_post_is_not_pushed(): void
    {
        $this->enableIndexNow();
        Bus::fake();
        $admin = Admin::factory()->create();

        BlogPost::create([
            'title' => ['en' => 'Draft Post'], 'slug' => 'draft-post-'.uniqid(),
            'content' => ['en' => 'x'], 'status' => ContentStatus::Draft,
            'author_id' => $admin->id,
        ]);

        Bus::assertNotDispatched(PushIndexNow::class);
    }

    #[Test]
    public function a_published_page_is_pushed_to_indexnow(): void
    {
        $this->enableIndexNow();
        Bus::fake();
        $admin = Admin::factory()->create();

        Page::create([
            'title' => ['en' => 'Shipping Policy'], 'slug' => 'shipping-policy-'.uniqid(),
            'content' => ['en' => 'x'], 'status' => ContentStatus::Published, 'created_by' => $admin->id,
        ]);

        Bus::assertDispatched(PushIndexNow::class);
    }

    #[Test]
    public function a_homepage_flagged_page_is_not_pushed(): void
    {
        // Its reachable URL is "/{locale}/", not "/{locale}/{slug}" —
        // pushing the slug URL would announce a path nothing serves.
        $this->enableIndexNow();
        Bus::fake();
        $admin = Admin::factory()->create();

        Page::create([
            'title' => ['en' => 'Home'], 'slug' => 'home-'.uniqid(), 'content' => ['en' => 'x'],
            'status' => ContentStatus::Published, 'is_homepage' => true, 'created_by' => $admin->id,
        ]);

        Bus::assertNotDispatched(PushIndexNow::class);
    }

    #[Test]
    public function an_active_manufacturer_is_pushed_to_indexnow(): void
    {
        $this->enableIndexNow();
        Bus::fake();

        Manufacturer::create(['name' => ['en' => 'Bosch'], 'slug' => 'bosch-'.uniqid(), 'country_code' => 'DE', 'is_active' => true]);

        Bus::assertDispatched(PushIndexNow::class);
    }

    #[Test]
    public function an_inactive_manufacturer_is_not_pushed(): void
    {
        $this->enableIndexNow();
        Bus::fake();

        Manufacturer::create(['name' => ['en' => 'Retired Brand'], 'slug' => 'retired-'.uniqid(), 'country_code' => 'DE', 'is_active' => false]);

        Bus::assertNotDispatched(PushIndexNow::class);
    }

    #[Test]
    public function a_car_model_of_an_active_manufacturer_is_pushed_to_indexnow(): void
    {
        $this->enableIndexNow();
        $manufacturer = Manufacturer::create(['name' => ['en' => 'Bosch'], 'slug' => 'bosch-'.uniqid(), 'country_code' => 'DE', 'is_active' => true]);
        Bus::fake();

        CarModel::create(['manufacturer_id' => $manufacturer->id, 'name' => 'Golf', 'slug' => 'golf-'.uniqid(), 'is_active' => true]);

        Bus::assertDispatched(PushIndexNow::class);
    }

    #[Test]
    public function a_car_model_of_an_inactive_manufacturer_is_not_pushed(): void
    {
        // CarModelController::show() 404s unless BOTH are active — pushing
        // here would announce a URL that immediately 404s on crawl.
        $this->enableIndexNow();
        $manufacturer = Manufacturer::create(['name' => ['en' => 'Retired Brand'], 'slug' => 'retired-'.uniqid(), 'country_code' => 'DE', 'is_active' => false]);
        Bus::fake();

        CarModel::create(['manufacturer_id' => $manufacturer->id, 'name' => 'Golf', 'slug' => 'golf-'.uniqid(), 'is_active' => true]);

        Bus::assertNotDispatched(PushIndexNow::class);
    }

    #[Test]
    public function nothing_is_pushed_when_indexnow_is_disabled(): void
    {
        // Default state — disabled.
        Bus::fake();
        $admin = Admin::factory()->create();

        BlogPost::create([
            'title' => ['en' => 'How To Choose Brake Pads'], 'slug' => 'brake-pads-guide-'.uniqid(),
            'content' => ['en' => 'x'], 'status' => ContentStatus::Published,
            'published_at' => now()->subDay(), 'author_id' => $admin->id,
        ]);
        Page::create([
            'title' => ['en' => 'Shipping Policy'], 'slug' => 'shipping-policy-'.uniqid(),
            'content' => ['en' => 'x'], 'status' => ContentStatus::Published, 'created_by' => $admin->id,
        ]);
        Manufacturer::create(['name' => ['en' => 'Bosch'], 'slug' => 'bosch-'.uniqid(), 'country_code' => 'DE', 'is_active' => true]);

        Bus::assertNotDispatched(PushIndexNow::class);
    }
}
