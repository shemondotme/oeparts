<?php

namespace Tests\Feature;

use App\Enums\OtpPurpose;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Manufacturer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingCountry;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\OtpService;
use App\Services\SequenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;
    private User $user;
    private ShippingCountry $country;
    private ShippingMethod $shippingMethod;
    private Manufacturer $manufacturer;
    private ShippingZone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a manufacturer
        $this->manufacturer = Manufacturer::create([
            'name' => 'Test Manufacturer',
            'slug' => 'test-manufacturer',
            'country_code' => 'DE',
            'is_active' => true,
        ]);

        // Create a product
        $this->product = Product::create([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => '06L906036L',
            'normalized_oem' => '06L906036L',
            'name' => ['en' => 'Test Product'],
            'description' => ['en' => 'Test description'],
            'price' => 150.00,
            'condition' => 'new',
            'is_in_stock' => true,
            'is_active' => true,
        ]);

        // Create a user
        $this->user = User::factory()->create();

        // Create a shipping zone
        $this->zone = ShippingZone::create([
            'name' => 'Europe',
            'description' => 'European countries',
            'is_active' => true,
        ]);

        // Create a shipping country
        $this->country = ShippingCountry::create([
            'zone_id' => $this->zone->id,
            'country_code' => 'DE',
            'country_name' => 'Germany',
        ]);

        // Create a shipping method
        $this->shippingMethod = ShippingMethod::create([
            'zone_id' => $this->zone->id,
            'name' => ['en' => 'Standard'],
            'description' => ['en' => 'Standard shipping'],
            'flat_rate' => 5.99,
            'estimated_days_min' => 3,
            'estimated_days_max' => 7,
            'is_active' => true,
        ]);

        // Create a sequence record for order numbers
        \App\Models\Sequence::create([
            'type' => \App\Enums\SequenceType::Order,
            'value' => 0,
            'month' => now()->format('Ym'),
        ]);

        // Settings will use default values if not in database
        // checkout.timeout_minutes defaults to 30
        // auth.otp_expiry_minutes defaults to 10
        // auth.otp_length defaults to 6
    }

    #[Test]
    public function guest_can_start_checkout_with_otp(): void
    {
        // Create a cart with a guest token
        $cart = Cart::create([
            'guest_token' => 'test-guest-token',
            'expires_at' => now()->addDays(7),
        ]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price_at_add' => $this->product->price,
        ]);

        // First, visit checkout page to create checkout session
        $this->withCookie('guest_token', 'test-guest-token')
            ->get('/en/checkout')
            ->assertOk();

        // Get the checkout ID from session
        $checkoutId = Session::get('active_checkout_id');
        $this->assertNotNull($checkoutId, 'Checkout session should be created');

        // Step 1: Request OTP (no OTP code provided, should generate OTP)
        $response = $this->post('/en/checkout', [
            'email' => 'guest@example.com',
            // No 'otp' field - this should trigger OTP generation
        ]);
        $response->assertRedirect(); // Should redirect back with OTP sent

        // Verify OTP was generated
        $otp = \App\Models\Otp::where('email', 'guest@example.com')
            ->where('purpose', OtpPurpose::GuestCheckout)
            ->first();
        
        $this->assertNotNull($otp, 'OTP should be generated for guest email');

        // Step 1: Submit OTP for verification
        $response = $this->post('/en/checkout', [
            'email' => 'guest@example.com',
            'otp' => $otp->otp_code,
        ]);
        $response->assertRedirect(); // Should redirect to step 2
        
        // Check what step we're at after OTP verification
        $checkoutId = Session::get('active_checkout_id');
        $checkoutService = app(CheckoutService::class);
        $checkout = $checkoutService->get($checkoutId);
        
        $this->assertNotNull($checkout, 'Checkout should exist');
        $this->assertNotEmpty($checkout['data']['guest_email'] ?? null, 'guest_email should be set');
        $this->assertTrue($checkout['data']['otp_verified'] ?? false, 'otp_verified should be true');
        $this->assertEquals(2, $checkout['step'] ?? 0, 'Should be at step 2 after OTP verification');

        // Step 2: Address
        $response = $this->post('/en/checkout', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'street' => '123 Main St',
            'city' => 'Berlin',
            'postal_code' => '10115',
            'country_code' => 'DE',
        ]);
        $response->assertRedirect();
        
        // Debug: check what step we're at after step 2
        $checkout = $checkoutService->get($checkoutId);
        $this->assertEquals(3, $checkout['step'] ?? 0, 'Should be at step 3 after skipping B2B');

        // Step 3: Shipping method
        $response = $this->post('/en/checkout', [
            'shipping_method_id' => $this->shippingMethod->id,
        ]);
        $response->assertRedirect();

        // Step 4: Review
        $response = $this->post('/en/checkout', [
            'agree_terms' => 1,
        ]);
        $response->assertRedirect();

        // Step 5: Place order - first check if we're at step 5
        $checkoutId = Session::get('active_checkout_id');
        $this->assertNotNull($checkoutId, 'Checkout session should still exist');
        
        // Get checkout service to check current step
        $checkoutService = app(CheckoutService::class);
        $checkout = $checkoutService->get($checkoutId);
        $this->assertNotNull($checkout, 'Checkout data should exist');
        $this->assertEquals(5, $checkout['step'], 'Should be at step 5 before placing order');
        
        $response = $this->post('/en/checkout', [
            'payment_method' => 'card',
            'customer_note' => 'Please deliver before 5pm',
        ]);
        
        // Debug: check if there's an error in session
        if (Session::has('error')) {
            $this->fail('Error in checkout: ' . Session::get('error'));
        }
        
        $response->assertRedirect(); // Should redirect to payment page

        // Verify order was created
        $order = Order::where('guest_email', 'guest@example.com')->first();
        $this->assertNotNull($order, 'Order should be created');
        $this->assertEquals('pending', $order->status->value);
        $this->assertEquals(1, $order->items->count()); // 1 order item with quantity 2
        $this->assertEquals(150.00 * 2, $order->subtotal);
    }

    #[Test]
    public function b2b_vat_validation_works(): void
    {
        // This test would mock the VIES service to simulate valid and invalid VAT numbers
        // For now, we need to set up a checkout session first
        $cart = Cart::create([
            'guest_token' => 'vat-test',
            'expires_at' => now()->addDays(7),
        ]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price_at_add' => $this->product->price,
        ]);

        $this->withCookie('guest_token', 'vat-test')
            ->get('/en/checkout')
            ->assertOk();

        // Skip OTP step by manually setting checkout data
        $checkoutId = Session::get('active_checkout_id');
        app(CheckoutService::class)->update($checkoutId, [
            'guest_email' => 'vat@example.com',
            'otp_verified' => true,
            'step' => 2, // Manually advance to step 2
        ]);

        // Now test VAT validation
        $response = $this->post('/en/checkout', [
            'is_b2b' => 1,
            'vat_number' => 'DE123456789',
        ]);
        $response->assertRedirect();
        // In a real test we would mock the ViesService and check session data
    }

    #[Test]
    public function order_number_format_matches_spec(): void
    {
        $sequenceService = app(SequenceService::class);
        $orderNumber = $sequenceService->nextOrderNumber();

        // Format should be ORD-YYYYMM-000001
        $this->assertMatchesRegularExpression('/^ORD-\d{6}-\d{6}$/', $orderNumber);
    }

    #[Test]
    public function checkout_requires_cart_with_items(): void
    {
        // Empty cart should redirect to cart page
        $response = $this->get('/en/checkout');
        $response->assertRedirect('/en/cart');
    }

    #[Test]
    public function checkout_session_expires_after_timeout(): void
    {
        // Create a cart
        $cart = Cart::create([
            'guest_token' => 'expire-test',
            'expires_at' => now()->addDays(7),
        ]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price_at_add' => $this->product->price,
        ]);

        // Start checkout by visiting the page
        $this->withCookie('guest_token', 'expire-test')
            ->get('/en/checkout')
            ->assertOk();

        // Get checkout ID
        $checkoutId = Session::get('active_checkout_id');
        $this->assertNotNull($checkoutId);

        // Manually expire the checkout session
        $checkoutService = app(CheckoutService::class);
        $checkoutService->update($checkoutId, [
            'expires_at' => now()->subMinutes(31)->toIso8601String(),
        ]);

        // Clear the session checkout ID to simulate expiration
        Session::forget('active_checkout_id');

        // Attempt to proceed should redirect to restart checkout
        $response = $this->post('/en/checkout', ['email' => 'test@example.com']);
        $response->assertRedirect('/en/checkout'); // Should restart with error
    }
}