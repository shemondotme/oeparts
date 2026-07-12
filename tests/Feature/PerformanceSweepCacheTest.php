<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Condition;
use App\Models\Coupon;
use App\Models\Faq;
use App\Models\Product;
use App\Models\Testimonial;
use App\Services\CartService;
use App\Services\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Performance sweep Stage C: homepage content-block caches (testimonials/
 * faqs/blog preview) were previously hit live on every render; the active
 * condition list was a raw uncached query duplicated in the controller and
 * the view; CartService::getSummary() N+1'd on `product` for every caller
 * except CartController::index()/preview().
 */
class PerformanceSweepCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([\Database\Seeders\SettingsSeeder::class]);
    }

    #[Test]
    public function testimonial_write_invalidates_the_home_testimonials_cache(): void
    {
        Cache::put('home.testimonials', 'stale', 600);

        Testimonial::create([
            'name' => 'Jane Doe',
            'company' => 'Acme Motors',
            'location' => 'Berlin, Germany',
            'quote' => ['en' => 'Great parts.'],
            'rating' => 5,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->assertNull(Cache::get('home.testimonials'));
    }

    #[Test]
    public function faq_write_invalidates_the_home_faqs_cache(): void
    {
        Cache::put('home.faqs', 'stale', 600);

        Faq::create([
            'question' => ['en' => 'Do you ship to the EU?'],
            'answer' => ['en' => 'Yes.'],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->assertNull(Cache::get('home.faqs'));
    }

    #[Test]
    public function blog_post_write_invalidates_the_home_blog_posts_cache(): void
    {
        Cache::put('home.blog_posts', 'stale', 600);

        BlogPost::factory()->create();

        $this->assertNull(Cache::get('home.blog_posts'));
    }

    #[Test]
    public function condition_write_invalidates_the_active_conditions_cache(): void
    {
        Cache::put('conditions.active', 'stale', 600);

        Condition::create([
            'name' => 'Refurbished', 'slug' => 'refurbished',
            'bg_color' => '#000', 'text_color' => '#fff',
            'is_active' => true, 'sort_order' => 1,
        ]);

        $this->assertNull(Cache::get('conditions.active'));
    }

    #[Test]
    public function coupon_lookup_is_cached_and_renaming_invalidates_both_old_and_new_code(): void
    {
        $coupon = Coupon::factory()->create([
            'code' => 'SAVE10',
            'min_order_amount' => '0.00',
            'usage_limit' => 1000,
            'usage_limit_per_user' => 1000,
            'expires_at' => null,
            'created_by' => \App\Models\Admin::factory()->create()->id,
        ]);

        // First validate() call populates the cache for the original code.
        app(CouponService::class)->validate('SAVE10', '100.00', null);
        $this->assertNotNull(Cache::get('coupon.code.SAVE10'));

        $coupon->update(['code' => 'SAVE20']);

        // The stale OLD-code cache entry must be gone (else a retired code
        // keeps "working" off cache until TTL expiry), and the coupon is
        // reachable under its new code without a stale cache blocking it.
        $this->assertNull(Cache::get('coupon.code.SAVE10'));
        $result = app(CouponService::class)->validate('SAVE20', '100.00', null);
        $this->assertTrue($result['valid']);
    }

    #[Test]
    public function cart_summary_does_not_n_plus_one_when_items_are_not_preloaded(): void
    {
        $cart = Cart::create(['expires_at' => now()->addDays(30)]);
        $condition = Condition::firstOrCreate(
            ['slug' => 'new'],
            ['name' => 'New', 'bg_color' => '#fff', 'text_color' => '#000', 'is_active' => true, 'sort_order' => 0],
        );

        for ($i = 0; $i < 5; $i++) {
            $product = Product::factory()->create(['condition_id' => $condition->id, 'price' => '10.00']);
            CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'price_at_add' => '10.00',
            ]);
        }

        // A fresh Cart instance — items relation is NOT preloaded, mirroring
        // every getSummary() caller except CartController::index()/preview().
        $freshCart = Cart::find($cart->id);
        $this->assertFalse($freshCart->relationLoaded('items'));

        DB::enableQueryLog();
        app(CartService::class)->getSummary($freshCart);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Without the fix this scales 1:1 with cart line count (5 extra
        // product queries); with the fix it's a small constant regardless
        // of how many items are in the cart.
        $this->assertLessThan(10, $queryCount, "Expected a small constant query count, got {$queryCount} for 5 items");
    }
}
