<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\Sequence;
use App\Models\ShippingCountry;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CheckoutService::createOrder() used to never check product availability at
 * all — a product could go out of stock between add-to-cart and checkout
 * (admin deactivation, or a second concurrent checkout for the same item)
 * and the order would still be created and charged.
 */
class CheckoutStockTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private User $user;

    private ShippingMethod $shippingMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $condition = Condition::firstOrCreate(
            ['slug' => 'new'],
            ['name' => 'New', 'bg_color' => '#ecfdf5', 'text_color' => '#065f46', 'is_active' => true]
        );
        $manufacturer = Manufacturer::create([
            'name' => 'Test Manufacturer', 'slug' => 'test-manufacturer',
            'country_code' => 'DE', 'is_active' => true,
        ]);
        $this->product = Product::create([
            'manufacturer_id' => $manufacturer->id,
            'oem_number' => '06L906036L', 'normalized_oem' => '06L906036L',
            'name' => 'Test Product', 'description' => 'Test description',
            'price' => 100.00, 'condition_id' => $condition->id,
            'is_in_stock' => true, 'is_active' => true,
        ]);
        $this->user = User::factory()->create();

        $zone = ShippingZone::create(['name' => 'Europe', 'is_active' => true]);
        ShippingCountry::create(['zone_id' => $zone->id, 'country_code' => 'DE', 'country_name' => 'Germany']);
        $this->shippingMethod = ShippingMethod::create([
            'zone_id' => $zone->id, 'name' => ['en' => 'Standard'],
            'flat_rate' => 0, 'estimated_days_min' => 3, 'estimated_days_max' => 7, 'is_active' => true,
        ]);

        Sequence::create(['type' => \App\Enums\SequenceType::Order, 'value' => 0, 'month' => now()->format('Ym')]);
    }

    private function startCheckout(Cart $cart): string
    {
        $checkoutService = app(CheckoutService::class);
        $checkoutId = $checkoutService->start($cart);

        $checkoutService->update($checkoutId, [
            'step' => 5,
            'contact_email' => $this->user->email,
            'shipping_address' => [
                'first_name' => 'John', 'last_name' => 'Doe', 'street' => 'St',
                'city' => 'Berlin', 'postal_code' => '10115', 'country_code' => 'DE',
            ],
            'shipping_method_id' => $this->shippingMethod->id,
            'payment_method' => 'card',
        ]);

        return $checkoutId;
    }

    #[Test]
    public function order_creation_is_rejected_when_a_cart_item_went_out_of_stock_before_checkout(): void
    {
        $cart = Cart::create(['user_id' => $this->user->id, 'expires_at' => now()->addDays(7)]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $this->product->id, 'quantity' => 1, 'price_at_add' => $this->product->price]);

        $checkoutId = $this->startCheckout($cart);

        // Product goes out of stock (e.g. an admin deactivated it, or a
        // concurrent checkout already claimed it) after the cart was built
        // but before this checkout reaches order creation.
        $this->product->update(['is_in_stock' => false]);

        $checkoutService = app(CheckoutService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($this->product->oem_number);

        try {
            $checkoutService->createOrder($checkoutId, $this->user->id, '127.0.0.1');
        } finally {
            // The transaction must have rolled back cleanly: no order was
            // created, and the cart/item survive so the customer can fix
            // their cart instead of losing it silently.
            $this->assertDatabaseMissing('orders', ['user_id' => $this->user->id]);
            $this->assertDatabaseHas('carts', ['id' => $cart->id]);
            $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'product_id' => $this->product->id]);
        }
    }

    #[Test]
    public function order_creation_succeeds_when_all_cart_items_are_in_stock(): void
    {
        $cart = Cart::create(['user_id' => $this->user->id, 'expires_at' => now()->addDays(7)]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $this->product->id, 'quantity' => 1, 'price_at_add' => $this->product->price]);

        $checkoutId = $this->startCheckout($cart);

        $order = app(CheckoutService::class)->createOrder($checkoutId, $this->user->id, '127.0.0.1');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'user_id' => $this->user->id]);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'product_id' => $this->product->id]);
    }
}
