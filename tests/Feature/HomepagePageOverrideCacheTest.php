<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Admin;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * HomeController's "Set as Homepage" Page lookup ran unconditionally at the
 * top of every single homepage request — the hottest single page in the
 * app — before the section-based path (already cached) is even reached.
 * Now backed by CacheService::rememberHomepagePageOverride(), which wraps
 * the nullable result in an array specifically so the common case (no page
 * flagged as homepage) is actually cacheable — Laravel's Cache::remember()
 * re-invokes its callback whenever the stored value reads back as literal
 * null, which would make a bare nullable cache a permanent no-op.
 */
class HomepagePageOverrideCacheTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_no_override_case_is_actually_cached_not_a_permanent_no_op(): void
    {
        $this->get('/en/')->assertOk();

        // The array-wrapping trick: has() must see the wrapper key even
        // though the real value inside it is null.
        $this->assertTrue(Cache::has('page.homepage_override'));
    }

    #[Test]
    public function creating_a_homepage_override_invalidates_the_stale_no_override_cache(): void
    {
        $this->get('/en/');
        $this->assertTrue(Cache::has('page.homepage_override'));

        Page::create([
            'title' => ['en' => 'Custom Home'], 'slug' => 'custom-home-'.uniqid(),
            'content' => ['en' => 'Hello'], 'status' => ContentStatus::Published,
            'published_at' => now()->subMinute(), 'is_homepage' => true,
            'created_by' => Admin::factory()->create()->id,
        ]);

        $this->assertFalse(Cache::has('page.homepage_override'), 'PageObserver must forget the stale lookup');

        $this->get('/en/')->assertSeeText('Custom Home');
    }
}
