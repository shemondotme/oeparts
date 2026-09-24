<?php

namespace Tests\Feature;

use App\Enums\DiscountType;
use App\Enums\SequenceType;
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

        Sequence::create(['type' => SequenceType::Order, 'value' => 0, 'month' => now()->format('Ym')]);
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
        // exactly what CouponAjaxController would have stored on the
        // checkout state at this point (via CheckoutService::update()).
        app(CheckoutService::class)->update($checkoutId, [
            'coupon_id' => $coupon->id,
            'discount_amount' => '10.00',
        ]);

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
            'terms_accepted' => true,
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
        app(CheckoutService::class)->update($checkoutId, [
            'coupon_id' => $coupon->id,
            'discount_amount' => '20.00',
        ]);

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
            'terms_accepted' => true,
        ]);

        $order = $checkoutService->createOrder($checkoutId, $this->user->id, '127.0.0.1');

        $this->assertSame('100.00', $order->subtotal);
        $this->assertSame('0.00', $order->discount_amount);
        $this->assertNull($order->coupon_id);
        $this->assertDatabaseMissing('coupon_usages', ['coupon_id' => $coupon->id]);
    }

    /**
     * EU VAT Directive Art. 79(b): discounts "allowed to the customer and
     * accounted for at the time of the supply" are excluded from the VAT
     * taxable amount — VAT is due on what the customer actually pays, not
     * the pre-discount list price. CartService::getSummary()'s cart-page
     * estimate already gets this right (computes VAT on subtotal minus
     * coupon_discount — see its "Subtotal after discount for VAT" comment),
     * so the order actually charged at checkout must match that, not
     * silently diverge from what the customer was shown.
     */
    #[Test]
    public function vat_is_calculated_on_the_subtotal_after_the_coupon_discount_not_before(): void
    {
        $admin = Admin::factory()->create();
        $coupon = Coupon::factory()->create([
            'created_by' => $admin->id,
            'code' => 'SAVE20',
            'discount_type' => DiscountType::Percentage,
            'discount_value' => 20,
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

        app(CheckoutService::class)->update($checkoutId, [
            'coupon_id' => $coupon->id,
            'discount_amount' => '20.00',
        ]);

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

        $order = $checkoutService->createOrder($checkoutId, $this->user->id, '127.0.0.1');

        // subtotal 100.00, 20% off -> discount 20.00, shipping 0.00 (free
        // shipping method in setUp), default VAT rate 21%.
        // Correct: VAT on (100.00 - 20.00) = 80.00 -> 16.80. Grand total
        // 80.00 + 16.80 = 96.80.
        // Bug (pre-fix): VAT on the full pre-discount 100.00 -> 21.00, then
        // discount subtracted only from the post-VAT total: 100.00 + 21.00
        // - 20.00 = 101.00 — the customer would be charged VAT on money
        // they never actually paid.
        $this->assertSame('100.00', $order->subtotal);
        $this->assertSame('20.00', $order->discount_amount);
        $this->assertSame('16.80', $order->vat_amount);
        $this->assertSame('96.80', $order->grand_total);
    }

    /**
     * 100%-off edge case: with the entire product subtotal discounted away,
     * VAT must be due only on the non-discounted portion (shipping/fees),
     * never on the fully-discounted subtotal itself.
     */
    #[Test]
    public function a_full_subtotal_discount_only_charges_vat_on_the_remaining_shipping_cost(): void
    {
        $admin = Admin::factory()->create();
        $coupon = Coupon::factory()->create([
            'created_by' => $admin->id,
            'code' => 'FREE100',
            'discount_type' => DiscountType::Fixed,
            'discount_value' => 100,
            'is_active' => true,
            'usage_limit' => null,
            'usage_limit_per_user' => null,
            'min_order_amount' => null,
            'expires_at' => now()->addDays(30),
        ]);

        // A paid shipping method this time, so there's a non-zero taxable
        // remainder after the subtotal is fully discounted away.
        $paidShipping = ShippingMethod::create([
            'zone_id' => $this->shippingMethod->zone_id,
            'name' => ['en' => 'Express'],
            'flat_rate' => '10.00',
            'estimated_days_min' => 1, 'estimated_days_max' => 2, 'is_active' => true,
        ]);

        $cart = Cart::create(['user_id' => $this->user->id, 'expires_at' => now()->addDays(7)]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $this->product->id, 'quantity' => 1, 'price_at_add' => $this->product->price]);

        $checkoutService = app(CheckoutService::class);
        $checkoutId = $checkoutService->start($cart);

        app(CheckoutService::class)->update($checkoutId, [
            'coupon_id' => $coupon->id,
            'discount_amount' => '100.00',
        ]);

        $checkoutService->update($checkoutId, [
            'step' => 5,
            'contact_email' => $this->user->email,
            'shipping_address' => [
                'first_name' => 'John', 'last_name' => 'Doe', 'street' => 'St',
                'city' => 'Berlin', 'postal_code' => '10115', 'country_code' => 'DE',
            ],
            'shipping_method_id' => $paidShipping->id,
            'payment_method' => 'card',
            'terms_accepted' => true,
        ]);

        $order = $checkoutService->createOrder($checkoutId, $this->user->id, '127.0.0.1');

        // subtotal 100.00 fully discounted -> 0.00 taxable from products.
        // + shipping 10.00 -> taxable base 10.00 -> VAT 21% = 2.10.
        // Grand total: 10.00 + 2.10 = 12.10.
        $this->assertSame('100.00', $order->subtotal);
        $this->assertSame('100.00', $order->discount_amount);
        $this->assertSame('2.10', $order->vat_amount);
        $this->assertSame('12.10', $order->grand_total);
    }
}
