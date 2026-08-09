<?php

namespace Tests\Feature;

use App\Enums\OtpPurpose;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Sequence;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Api\CheckoutController had two bugs found in the checkout-order audit:
 * step1() passed the raw string 'guest_checkout' where OtpService::verify()
 * requires an OtpPurpose enum, and assigned its RESULT_* return string
 * straight into the otp_verified flag — so even a wrong/expired code (e.g.
 * RESULT_INVALID) evaluated truthy and was stored as verified. step5() also
 * never allowed 'paysera' as a payment_method, even though CheckoutService
 * and the Frontend checkout flow both fully support it.
 */
class ApiCheckoutTest extends TestCase
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
            'oem_number' => '06L906036L',
            'normalized_oem' => '06L906036L',
            'name' => 'Test Product',
            'description' => 'Test description',
            'price' => 150.00,
            'condition_id' => $condition->id,
            'is_in_stock' => true,
            'is_active' => true,
        ]);
        $this->user = User::factory()->create();

        $zone = ShippingZone::create(['name' => 'Europe', 'description' => 'European countries', 'is_active' => true]);
        $this->shippingMethod = ShippingMethod::create([
            'zone_id' => $zone->id,
            'name' => ['en' => 'Standard'],
            'description' => ['en' => 'Standard shipping'],
            'flat_rate' => 5.99,
            'estimated_days_min' => 3,
            'estimated_days_max' => 7,
            'is_active' => true,
        ]);

        Sequence::create([
            'type' => \App\Enums\SequenceType::Order,
            'value' => 0,
            'month' => now()->format('Ym'),
        ]);
    }

    private function startCheckout(): string
    {
        $cart = Cart::create(['user_id' => $this->user->id, 'expires_at' => now()->addDays(7)]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price_at_add' => $this->product->price,
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/checkout/start');
        $response->assertCreated();

        return $response->json('data.checkout_id');
    }

    #[Test]
    public function step1_marks_otp_verified_true_when_the_correct_code_is_submitted(): void
    {
        $checkoutId = $this->startCheckout();

        app(OtpService::class)->generate('guest@example.com', OtpPurpose::GuestCheckout, '127.0.0.1');
        $otp = \App\Models\Otp::where('email', 'guest@example.com')->where('purpose', OtpPurpose::GuestCheckout)->first();

        $response = $this->actingAs($this->user)->postJson("/api/checkout/{$checkoutId}/step1", [
            'email' => 'guest@example.com',
            'otp' => $otp->otp_code,
        ]);
        $response->assertOk();

        $checkout = app(CheckoutService::class)->get($checkoutId);
        $this->assertTrue($checkout['data']['otp_verified']);
    }

    #[Test]
    public function step1_marks_otp_verified_false_when_an_incorrect_code_is_submitted(): void
    {
        $checkoutId = $this->startCheckout();

        app(OtpService::class)->generate('guest2@example.com', OtpPurpose::GuestCheckout, '127.0.0.1');

        // Before the fix, OtpService::verify()'s RESULT_INVALID string was
        // assigned directly to otp_verified — a non-empty string is truthy,
        // so a wrong code was stored as "verified".
        $response = $this->actingAs($this->user)->postJson("/api/checkout/{$checkoutId}/step1", [
            'email' => 'guest2@example.com',
            'otp' => '000000',
        ]);
        $response->assertOk();

        $checkout = app(CheckoutService::class)->get($checkoutId);
        $this->assertFalse($checkout['data']['otp_verified']);
    }

    #[Test]
    public function step5_accepts_paysera_as_a_payment_method(): void
    {
        $checkoutId = $this->startCheckout();

        app(CheckoutService::class)->update($checkoutId, [
            'step' => 5,
            'contact_email' => $this->user->email,
            'shipping_address' => [
                'first_name' => 'John', 'last_name' => 'Doe', 'street' => 'St',
                'city' => 'Berlin', 'postal_code' => '10115', 'country_code' => 'DE',
            ],
            'shipping_method_id' => $this->shippingMethod->id,
        ]);

        $response = $this->actingAs($this->user)->postJson("/api/checkout/{$checkoutId}/step5", [
            'payment_method' => 'paysera',
        ]);

        $response->assertCreated()->assertJsonPath('data.payment_method', 'paysera');

        $order = Order::where('user_id', $this->user->id)->firstOrFail();
        $this->assertSame('paysera', $order->payment_method->value);
    }
}
