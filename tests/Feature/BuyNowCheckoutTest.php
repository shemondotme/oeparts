<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingCountry;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\Setting;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Session;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BuyNowCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;
    private Manufacturer $manufacturer;
    private Condition $condition;
    private ShippingZone $zone;
    private ShippingMethod $shippingMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->condition = Condition::firstOrCreate(
            ['slug' => 'new'],
            ['name' => 'New', 'bg_color' => '#ecfdf5', 'text_color' => '#065f46', 'is_active' => true]
        );
        $this->manufacturer = Manufacturer::create([
            'name' => 'Test Manufacturer',
            'slug' => 'test-manufacturer',
            'country_code' => 'DE',
            'is_active' => true,
        ]);
        $this->product = Product::create([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => '06L906036L',
            'normalized_oem' => '06L906036L',
            'name' => ['en' => 'Test Product'],
            'price' => 150.00,
            'condition_id' => $this->condition->id,
            'is_in_stock' => true,
            'is_active' => true,
        ]);

        $this->zone = ShippingZone::create(['name' => 'Europe', 'description' => 'EU', 'is_active' => true]);
        ShippingCountry::create(['zone_id' => $this->zone->id, 'country_code' => 'DE', 'country_name' => 'Germany']);
        $this->shippingMethod = ShippingMethod::create([
            'zone_id' => $this->zone->id,
            'name' => ['en' => 'Standard'],
            'description' => ['en' => 'Standard shipping'],
            'flat_rate' => 5.99,
            'estimated_days_min' => 3,
            'estimated_days_max' => 7,
            'is_active' => true,
        ]);
        \App\Models\Sequence::create([
            'type' => \App\Enums\SequenceType::Order,
            'value' => 0,
            'month' => now()->format('Ym'),
        ]);
    }

    private function enableBuyNow(): void
    {
        Setting::updateOrCreate(
            ['group' => 'pdp', 'key' => 'buy_now_enabled'],
            ['value' => '1', 'type' => 'boolean', 'is_encrypted' => false]
        );
        app(\App\Services\SettingsService::class)->forget('pdp');
    }

    #[Test]
    public function buy_now_returns_404_when_toggle_disabled(): void
    {
        // Toggle left at its default (off).
        $response = $this->post('/en/cart/buy-now', ['product_id' => $this->product->id, 'quantity' => 1]);

        $response->assertStatus(404);
    }

    #[Test]
    public function buy_now_creates_an_isolated_cart_and_redirects_to_checkout(): void
    {
        $this->enableBuyNow();

        $response = $this->post('/en/cart/buy-now', ['product_id' => $this->product->id, 'quantity' => 2]);

        $response->assertRedirect('/en/checkout');

        $buyNowCart = Cart::where('is_buy_now', true)->first();
        $this->assertNotNull($buyNowCart, 'A throwaway buy-now cart should have been created');
        $this->assertNull($buyNowCart->user_id);
        $this->assertSame(1, $buyNowCart->items()->count());
        $this->assertSame(2, $buyNowCart->items()->first()->quantity);

        $checkoutId = Session::get('active_checkout_id');
        $this->assertNotNull($checkoutId);
        $checkout = app(CheckoutService::class)->get($checkoutId);
        $this->assertSame($buyNowCart->id, $checkout['cart_id']);
    }

    #[Test]
    public function buy_now_does_not_touch_the_customers_existing_cart_or_set_a_guest_token_cookie(): void
    {
        $this->enableBuyNow();

        $realCart = Cart::create(['guest_token' => 'real-cart-token', 'expires_at' => now()->addDays(7)]);
        CartItem::create([
            'cart_id' => $realCart->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price_at_add' => $this->product->price,
        ]);

        $response = $this->withCookie('guest_token', 'real-cart-token')
            ->post('/en/cart/buy-now', ['product_id' => $this->product->id, 'quantity' => 1]);

        $response->assertRedirect('/en/checkout');
        // The real cart still has exactly the one item it started with —
        // buy-now must never merge into or mutate it.
        $this->assertSame(1, $realCart->fresh()->items()->count());
        $this->assertFalse((bool) $realCart->fresh()->is_buy_now);
        $response->assertCookieMissing('guest_token');
    }

    #[Test]
    public function buy_now_lets_checkout_proceed_even_though_the_customers_real_cart_is_empty(): void
    {
        $this->enableBuyNow();

        $this->post('/en/cart/buy-now', ['product_id' => $this->product->id, 'quantity' => 1])
            ->assertRedirect('/en/checkout');

        // No real cart/cookie exists at all for this client — the old
        // unconditional "real cart empty -> redirect to /cart" guard would
        // have bounced this request before the fix in CheckoutController.
        $response = $this->get('/en/checkout');

        $response->assertOk();
    }

    #[Test]
    public function completing_buy_now_checkout_creates_an_order_and_deletes_the_throwaway_cart(): void
    {
        $this->enableBuyNow();

        $this->post('/en/cart/buy-now', ['product_id' => $this->product->id, 'quantity' => 1])
            ->assertRedirect('/en/checkout');

        $buyNowCartId = Cart::where('is_buy_now', true)->first()->id;
        $checkoutId = Session::get('active_checkout_id');

        app(CheckoutService::class)->update($checkoutId, [
            'contact_email' => 'buynow@example.com',
            'guest_email' => 'buynow@example.com',
            'otp_verified' => true,
            'step' => 5,
            'shipping_address' => ['first_name' => 'John', 'last_name' => 'Doe', 'street' => 'St', 'city' => 'Berlin', 'postal_code' => '10115', 'country_code' => 'DE'],
            'shipping_method_id' => $this->shippingMethod->id,
            'payment_method' => 'bank_transfer',
        ]);

        $response = $this->post('/en/checkout', ['payment_method' => 'bank_transfer']);
        $response->assertRedirect();

        $order = Order::where('guest_email', 'buynow@example.com')->first();
        $this->assertNotNull($order, 'Order should be created from the buy-now cart');
        $this->assertSame(1, $order->items->count());

        $this->assertDatabaseMissing('carts', ['id' => $buyNowCartId]);
    }

    #[Test]
    public function abandoned_buy_now_cart_is_swept_by_the_existing_cart_clean_command(): void
    {
        $buyNowCart = Cart::create([
            'guest_token' => 'stale-buy-now',
            'is_buy_now' => true,
            'expires_at' => now()->subMinutes(5),
        ]);

        Artisan::call('cart:clean');

        $this->assertDatabaseMissing('carts', ['id' => $buyNowCart->id]);
    }
}
