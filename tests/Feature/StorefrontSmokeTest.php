<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\CarModel;
use App\Models\Manufacturer;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StorefrontSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            \Database\Seeders\SettingsSeeder::class,
            \Database\Seeders\LanguagesSeeder::class,
            \Database\Seeders\RolesSeeder::class,
            \Database\Seeders\AdminSeeder::class,
            \Database\Seeders\SequencesSeeder::class,
            \Database\Seeders\CarriersSeeder::class,
            \Database\Seeders\SectionsSeeder::class,
            \Database\Seeders\ShippingZonesAndMethodsSeeder::class,
            \Database\Seeders\DemoManufacturersAndPartsSeeder::class,
            \Database\Seeders\CmsFooterPagesSeeder::class,
            \Database\Seeders\BlogPostsSeeder::class,
        ]);

        // Create at least one car model and attach it to some products
        $manufacturer = Manufacturer::where('is_active', true)->first();
        if ($manufacturer) {
            $carModel = CarModel::create([
                'manufacturer_id' => $manufacturer->id,
                'name' => 'Golf VII',
                'slug' => 'golf-vii',
                'is_active' => true,
            ]);

            $product = \App\Models\Product::where('manufacturer_id', $manufacturer->id)->first();
            if ($product) {
                $product->carModels()->attach($carModel->id);
            }
        }
    }

    #[Test]
    public function storefront_pages_return_200_for_en(): void
    {
        $this->assertLocalePages('en');
    }

    #[Test]
    public function storefront_pages_return_200_for_de(): void
    {
        $this->assertLocalePages('de');
    }

    #[Test]
    public function storefront_pages_return_200_for_lt(): void
    {
        $this->assertLocalePages('lt');
    }

    #[Test]
    public function storefront_pages_return_200_for_fr(): void
    {
        $this->assertLocalePages('fr');
    }

    #[Test]
    public function storefront_pages_return_200_for_es(): void
    {
        $this->assertLocalePages('es');
    }

    private function assertLocalePages(string $lang): void
    {
        // Get actual entities from the seeded DB
        $manufacturer = Manufacturer::where('is_active', true)->first();
        $this->assertNotNull($manufacturer, 'Seeded manufacturer must exist');

        $carModel = CarModel::where('manufacturer_id', $manufacturer->id)->where('is_active', true)->first();
        $this->assertNotNull($carModel, 'Seeded car model must exist');

        $blogPost = BlogPost::where('status', 'published')->first();
        $this->assertNotNull($blogPost, 'Seeded blog post must exist');

        // 1. Homepage
        $this->get("/{$lang}")->assertStatus(200);
        $this->get("/{$lang}/")->assertStatus(200);

        // 2. Parts Search Console
        $this->get("/{$lang}/parts")->assertStatus(200);

        // 3. Sitemap
        $this->get("/{$lang}/sitemap")->assertStatus(200);

        // 4. Brands index
        $this->get("/{$lang}/brands")->assertStatus(200);

        // 5. Brand show
        $this->get("/{$lang}/brand/{$manufacturer->slug}")->assertStatus(200);

        // 6. Brand Models
        $this->get("/{$lang}/brand/{$manufacturer->slug}/models")->assertStatus(200);

        // 7. Brand Model details
        $this->get("/{$lang}/brand/{$manufacturer->slug}/{$carModel->slug}")->assertStatus(200);

        // 8. Cart
        $this->get("/{$lang}/cart")->assertStatus(200);

        // 9. Checkout (redirects to cart when cart is empty)
        $this->get("/{$lang}/checkout")->assertRedirect("/{$lang}/cart");

        // 9b. Checkout (returns 200 when cart is populated)
        $product = \App\Models\Product::where('manufacturer_id', $manufacturer->id)->first();
        $cart = \App\Models\Cart::create([
            'guest_token' => 'smoke-test-guest-token-' . $lang,
            'expires_at' => now()->addDays(7),
        ]);
        \App\Models\CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price_at_add' => $product->price,
        ]);
        $this->withCookie('guest_token', 'smoke-test-guest-token-' . $lang)
            ->get("/{$lang}/checkout")
            ->assertStatus(200);

        // 10. Blog index
        $this->get("/{$lang}/blog")->assertStatus(200);

        // 11. Blog post detail
        $this->get("/{$lang}/blog/{$blogPost->slug}")->assertStatus(200);

        // 12. Contact page
        $this->get("/{$lang}/contact")->assertStatus(200);

        // 13. CMS Pages
        $this->get("/{$lang}/about")->assertStatus(200);
        $this->get("/{$lang}/privacy-policy")->assertStatus(200);
        $this->get("/{$lang}/terms-of-service")->assertStatus(200);
        $this->get("/{$lang}/cookie-policy")->assertStatus(200);
    }
}
