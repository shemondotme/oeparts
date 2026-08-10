<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Filament\Resources\BlogPostResource\Pages\ListBlogPosts;
use App\Models\AbandonedCart;
use App\Models\Admin;
use App\Models\BlogPost;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MarketingLowFixesTest extends TestCase
{
    use RefreshDatabase;

    // ── marketing-8: a cart still "in flight" (not yet confirmed sent) isn't double-handled ──

    #[Test]
    public function a_cart_with_a_still_pending_abandoned_cart_row_is_not_processed_again(): void
    {
        Queue::fake();

        $manufacturer = Manufacturer::create(['name' => ['en' => 'Mfr'], 'slug' => 'mfr', 'country_code' => 'DE', 'is_active' => true]);
        $condition = Condition::firstOrCreate(['slug' => 'new'], ['name' => 'New', 'bg_color' => '#fff', 'text_color' => '#000', 'is_active' => true]);
        $product = Product::create([
            'manufacturer_id' => $manufacturer->id, 'oem_number' => 'AC1', 'normalized_oem' => 'AC1',
            'name' => ['en' => 'Part'], 'description' => ['en' => 'x'], 'price' => 10,
            'condition_id' => $condition->id, 'is_active' => true, 'is_in_stock' => true,
        ]);
        $user = User::factory()->create(['email' => 'shopper@example.com']);
        $cart = Cart::create(['user_id' => $user->id, 'expires_at' => now()->addDays(7)]);
        $cart->forceFill(['updated_at' => now()->subHours(3)])->save();
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price_at_add' => 10]);

        // Simulates a recovery already queued for this cart but not yet
        // confirmed sent (recovery_email_sent still false).
        AbandonedCart::create([
            'user_id' => $user->id, 'guest_email' => null,
            'cart_snapshot' => ['items' => [], 'total' => '10.00'],
            'last_active_at' => now()->subHours(3), 'recovery_email_sent' => false,
        ]);

        Artisan::call('abandoned-cart:process');

        $this->assertSame(1, AbandonedCart::where('user_id', $user->id)->count());
    }

    #[Test]
    public function a_genuinely_new_stale_cart_still_gets_processed(): void
    {
        Queue::fake();

        $manufacturer = Manufacturer::create(['name' => ['en' => 'Mfr'], 'slug' => 'mfr2', 'country_code' => 'DE', 'is_active' => true]);
        $condition = Condition::firstOrCreate(['slug' => 'new'], ['name' => 'New', 'bg_color' => '#fff', 'text_color' => '#000', 'is_active' => true]);
        $product = Product::create([
            'manufacturer_id' => $manufacturer->id, 'oem_number' => 'AC2', 'normalized_oem' => 'AC2',
            'name' => ['en' => 'Part'], 'description' => ['en' => 'x'], 'price' => 10,
            'condition_id' => $condition->id, 'is_active' => true, 'is_in_stock' => true,
        ]);
        $user = User::factory()->create(['email' => 'freshshopper@example.com']);
        $cart = Cart::create(['user_id' => $user->id, 'expires_at' => now()->addDays(7)]);
        $cart->forceFill(['updated_at' => now()->subHours(3)])->save();
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price_at_add' => 10]);

        Artisan::call('abandoned-cart:process');

        $this->assertSame(1, AbandonedCart::where('user_id', $user->id)->count());
    }

    // ── marketing-12: publishing preserves an already-scheduled future date ──

    #[Test]
    public function republishing_does_not_overwrite_a_future_scheduled_date(): void
    {
        $this->seed(\Database\Seeders\RolesSeeder::class);
        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');

        $future = now()->addWeek()->startOfMinute();
        $post = BlogPost::factory()->create(['status' => ContentStatus::Draft, 'published_at' => $future]);

        Livewire::test(ListBlogPosts::class)->callTableAction('togglePublish', $post);

        $this->assertSame(ContentStatus::Published, $post->fresh()->status);
        $this->assertTrue($post->fresh()->published_at->equalTo($future));
    }

    #[Test]
    public function publishing_a_post_with_no_date_defaults_to_now(): void
    {
        $this->seed(\Database\Seeders\RolesSeeder::class);
        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');

        $post = BlogPost::factory()->create(['status' => ContentStatus::Draft, 'published_at' => null]);

        Livewire::test(ListBlogPosts::class)->callTableAction('togglePublish', $post);

        $this->assertSame(ContentStatus::Published, $post->fresh()->status);
        $this->assertTrue($post->fresh()->published_at->isToday());
    }

    // ── marketing-15: testimonial rating is guarded against out-of-range values ──

    #[Test]
    public function an_out_of_range_rating_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Testimonial::create(['name' => 'Jane', 'quote' => ['en' => 'Great!'], 'rating' => 7, 'is_active' => true, 'sort_order' => 1]);
    }

    #[Test]
    public function a_valid_rating_saves_fine(): void
    {
        $testimonial = Testimonial::create(['name' => 'Jane', 'quote' => ['en' => 'Great!'], 'rating' => 5, 'is_active' => true, 'sort_order' => 1]);

        $this->assertSame(5, $testimonial->rating);
    }
}
