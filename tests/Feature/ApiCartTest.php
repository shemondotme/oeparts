<?php

namespace Tests\Feature;

use App\Enums\DiscountType;
use App\Models\Admin;
use App\Models\Cart;
use App\Models\Condition;
use App\Models\Coupon;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Api\CartController (routes/api.php, /api/cart/*) had zero test coverage —
 * exactly why every method's phantom `string $lang` parameter (no route in
 * this group ever supplies one), update()'s call to a nonexistent method,
 * and applyCoupon()/removeCoupon()'s wrong-signature CouponService calls all
 * shipped throwing on every real request. Covers the fixed paths directly
 * against the routed endpoints. Uses an authenticated user throughout
 * (rather than the guest_token cookie) so cart continuity across requests
 * doesn't depend on cookie encryption round-tripping through the test client.
 */
class ApiCartTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private User $user;

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
            'oem_number' => '06L906036L',
            'normalized_oem' => '06L906036L',
            'name' => 'Test Product',
            'description' => 'Test description',
            'price' => 100.00,
            'condition_id' => $condition->id,
            'is_in_stock' => true,
            'is_active' => true,
        ]);
        $this->user = User::factory()->create();
    }

    #[Test]
    public function add_then_update_quantity_round_trips_through_the_real_endpoints(): void
    {
        $this->actingAs($this->user);

        $add = $this->postJson('/api/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);
        $add->assertOk()->assertJson(['success' => true]);

        $cart = Cart::where('user_id', $this->user->id)->firstOrFail();
        $item = $cart->items()->firstOrFail();

        // This is the exact call that threw "Call to undefined method
        // CartService::updateItemQuantity()" before the fix.
        $update = $this->putJson("/api/cart/update/{$item->id}", ['quantity' => 3]);

        $update->assertOk()->assertJson(['success' => true, 'itemCount' => 3]);
        $this->assertSame(3, $item->fresh()->quantity);
    }

    #[Test]
    public function adding_an_out_of_stock_product_returns_a_422_not_a_500(): void
    {
        $this->actingAs($this->user);
        $this->product->update(['is_in_stock' => false]);

        // Before the fix, CartService threw plain \Exception while this
        // controller only caught \RuntimeException — this request 500'd.
        $response = $this->postJson('/api/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);

        $response->assertStatus(422)->assertJson(['success' => false]);
    }

    #[Test]
    public function apply_coupon_validates_and_persists_onto_the_cart(): void
    {
        $this->actingAs($this->user);

        $admin = Admin::factory()->create();
        Coupon::factory()->create([
            'created_by' => $admin->id,
            'code' => 'SAVE10',
            'discount_type' => DiscountType::Percentage,
            'discount_value' => 10,
            'is_active' => true,
            'usage_limit' => null,
            'min_order_amount' => null, // factory default is a random 100-999, would randomly fail this test's €100 cart
            'expires_at' => now()->addDays(30),
        ]);

        $this->postJson('/api/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);

        // This is the exact call that threw a TypeError (string given,
        // Coupon expected) before the fix — apply() used to be called with
        // CouponService's order-based signature.
        $response = $this->postJson('/api/cart/coupon/apply', ['code' => 'SAVE10']);

        $response->assertOk()->assertJson(['success' => true]);

        $cart = Cart::where('user_id', $this->user->id)->firstOrFail();
        $this->assertSame('SAVE10', $cart->coupon_code);
        $this->assertSame('10.00', $response->json('summary.coupon_discount'));
    }

    #[Test]
    public function apply_invalid_coupon_returns_422_and_never_touches_the_cart(): void
    {
        $this->actingAs($this->user);
        $this->postJson('/api/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);

        $response = $this->postJson('/api/cart/coupon/apply', ['code' => 'DOES-NOT-EXIST']);

        $response->assertStatus(422)->assertJson(['success' => false]);

        $cart = Cart::where('user_id', $this->user->id)->firstOrFail();
        $this->assertNull($cart->coupon_code);
    }

    #[Test]
    public function remove_coupon_clears_it_from_the_cart(): void
    {
        $this->actingAs($this->user);
        $cart = Cart::create(['user_id' => $this->user->id, 'coupon_code' => 'SOMECODE', 'expires_at' => now()->addDays(7)]);

        // This is the exact call that threw a TypeError (Cart given, string
        // expected) before the fix — remove() used to be called with
        // CouponService's session-checkout-id-based signature.
        $response = $this->deleteJson('/api/cart/coupon/remove');

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertNull($cart->fresh()->coupon_code);
    }

    #[Test]
    public function summary_does_not_break_when_a_cart_item_product_has_been_soft_deleted(): void
    {
        $this->actingAs($this->user);
        $this->postJson('/api/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);

        $this->product->delete(); // soft delete (Product uses SoftDeletes)

        $response = $this->getJson('/api/cart/summary');

        $response->assertOk();
        $this->assertSame('0.00', $response->json('summary.subtotal'));
        $this->assertSame(0, $response->json('summary.item_count'));
    }
}
