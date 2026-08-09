<?php

namespace Tests\Feature;

use App\Enums\DiscountType;
use App\Models\Admin;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Condition;
use App\Models\Coupon;
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
 * CheckoutService::createOrder() used to trust the discount_amount
 * CouponAjaxController cached in the checkout session whenever the coupon
 * was first applied — computed against whatever the cart subtotal was at
 * that moment. If the customer changed cart contents afterwards (this test:
 * added another item after applying a 10% coupon), the order was created
 * with the stale discount instead of 10% of the actual final subtotal.
 */
class CheckoutCouponTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private Product $secondProduct;

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
        $this->secondProduct = Product::create([
            'manufacturer_id' => $manufacturer->id,
            'oem_number' => '06L906036M', 'normalized_oem' => '06L906036M',
            'name' => 'Second Product', 'description' => 'Test description',
            'price' => 50.00, 'condition_id' => $condition->id,
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

    #[Test]
    public function order_discount_reflects_the_final_cart_not_the_subtotal_when_the_coupon_was_applied(): void
    {
        $admin = Admin::factory()->create();
        $coupon = Coupon::factory()->create([
            'created_by' => $admin->id,
            'code' => 'SAVE10',
            'discount_type' => DiscountType::Percentage,
            'discount_value' => 10,
            'is_active' => true,
            'usage_limit' => null,
            'usage_limit_per_user' => null,
            'min_order_amount' => null,
            'expires_at' => now()->addDays(30),
        ]);

        $cart = Cart::create(['user_id' => $this->user->id, 'expires_at' => now()->addDays(7)]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $this->product->id, 'quantity' => 1, 'price_at_add' => $this->product->price]);

        $checkoutService = app(CheckoutService::class);
        $checkoutId = $checkoutService->start($cart);

        // Coupon applied while the cart subtotal is still €100 — 10% = €10,
        // exactly what CouponAjaxController would have cached in the
        // session at this point. coupon_id/discount_amount aren't in
        // CheckoutService::update()'s $allowed key list — CouponAjaxController
        // writes them straight into the session, so this mirrors that.
        Session::put("checkout.{$checkoutId}.data.coupon_id", $coupon->id);
        Session::put("checkout.{$checkoutId}.data.discount_amount", '10.00');

        // Customer adds another item after applying the coupon — the real
        // subtotal is now €150, so a correct 10% discount is €15, not the
        // stale €10 cached above.
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $this->secondProduct->id, 'quantity' => 1, 'price_at_add' => $this->secondProduct->price]);

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

        $order = $checkoutService->createOrder($checkoutId, $this->user->id, '127.0.0.1');

        $this->assertSame('150.00', $order->subtotal);
        $this->assertSame('15.00', $order->discount_amount);
        $this->assertDatabaseHas('coupon_usages', ['coupon_id' => $coupon->id, 'order_id' => $order->id]);
    }

    #[Test]
    public function order_drops_the_coupon_entirely_if_it_no_longer_qualifies_at_order_creation(): void
    {
        $admin = Admin::factory()->create();
        $coupon = Coupon::factory()->create([
            'created_by' => $admin->id,
            'code' => 'MIN200',
            'discount_type' => DiscountType::Fixed,
            'discount_value' => 20,
            'is_active' => true,
            'usage_limit' => null,
            'usage_limit_per_user' => null,
            'min_order_amount' => '120.00',
            'expires_at' => now()->addDays(30),
        ]);

        $cart = Cart::create(['user_id' => $this->user->id, 'expires_at' => now()->addDays(7)]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $this->product->id, 'quantity' => 1, 'price_at_add' => $this->product->price]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $this->secondProduct->id, 'quantity' => 1, 'price_at_add' => $this->secondProduct->price]);

        $checkoutService = app(CheckoutService::class);
        $checkoutId = $checkoutService->start($cart);

        // Applied while subtotal (€150) still met the €120 minimum.
        Session::put("checkout.{$checkoutId}.data.coupon_id", $coupon->id);
        Session::put("checkout.{$checkoutId}.data.discount_amount", '20.00');

        // Customer removes the second item — subtotal drops to €100, below
        // the coupon's minimum order amount.
        CartItem::where('cart_id', $cart->id)->where('product_id', $this->secondProduct->id)->delete();

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

        $order = $checkoutService->createOrder($checkoutId, $this->user->id, '127.0.0.1');

        $this->assertSame('100.00', $order->subtotal);
        $this->assertSame('0.00', $order->discount_amount);
        $this->assertNull($order->coupon_id);
        $this->assertDatabaseMissing('coupon_usages', ['coupon_id' => $coupon->id]);
    }
}
