<?php

namespace Tests\Feature;

use App\Enums\SequenceType;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Sequence;
use App\Models\ShippingCountry;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 9 (Business/Domain Edge Cases) — double-submit / double-charge
 * protection on "Place Order". A genuinely simultaneous double-click
 * (two requests racing at the same instant) is guarded by
 * Cart::lockForUpdate() inside CheckoutService::createOrder() (see that
 * method's own comment) — this covers the far more common case instead:
 * two SEQUENTIAL submissions of the same checkout (a slow first response,
 * user clicks again; a client-side retry; a resubmitted form after
 * pressing back) milliseconds to seconds apart, not the same instant.
 *
 * createOrder() deletes the Cart and clears the checkout session inside
 * its own transaction before returning — so a second call for the same
 * checkoutId must fail cleanly on "session not found", not create a
 * second order.
 */
class CheckoutDoubleSubmitTest extends TestCase
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

        Sequence::create(['type' => SequenceType::Order, 'value' => 0, 'month' => now()->format('Ym')]);
    }

    #[Test]
    public function a_second_submission_of_the_same_checkout_session_does_not_create_a_second_order(): void
    {
        $cart = Cart::create(['user_id' => $this->user->id, 'expires_at' => now()->addDays(7)]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $this->product->id, 'quantity' => 1, 'price_at_add' => $this->product->price]);

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
            'terms_accepted' => true,
        ]);

        $firstOrder = $checkoutService->createOrder($checkoutId, $this->user->id, '127.0.0.1');

        $this->expectException(\RuntimeException::class);

        try {
            $checkoutService->createOrder($checkoutId, $this->user->id, '127.0.0.1');
        } finally {
            $this->assertSame(1, Order::where('user_id', $this->user->id)->count(),
                'a second submission of the same checkout must not create a second order');
            $this->assertDatabaseHas('orders', ['id' => $firstOrder->id]);
        }
    }
}
