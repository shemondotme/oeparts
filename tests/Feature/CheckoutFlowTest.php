<?php

namespace Tests\Feature;

use App\Enums\OtpPurpose;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Condition;
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
use Illuminate\Support\Facades\Mail;
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
    private Condition $condition;

    protected function setUp(): void
    {
        parent::setUp();

        // Create default condition
        $this->condition = Condition::firstOrCreate(
            ['slug' => 'new'],
            ['name' => 'New', 'bg_color' => '#ecfdf5', 'text_color' => '#065f46', 'is_active' => true]
        );

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
            'condition_id' => $this->condition->id,
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

        // Regression: the auto-created guest account must actually be
        // marked email-verified. email_verified_at is intentionally not in
        // User::$fillable, so a plain User::create([..., 'email_verified_at'
        // => now()]) silently discards it — every guest account created
        // after checkout was left permanently unverified until this was
        // fixed to use forceFill().
        $guestUser = User::where('email', 'guest@example.com')->first();
        $this->assertNotNull($guestUser, 'Guest account should be auto-created');
        $this->assertNotNull($guestUser->email_verified_at, 'Auto-created guest account should be marked verified');
    }

    #[Test]
    public function guest_checkout_otp_step_flashes_status_and_supports_change_email(): void
    {
        $cart = Cart::create([
            'guest_token' => 'otp-ui-test',
            'expires_at' => now()->addDays(7),
        ]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price_at_add' => $this->product->price,
        ]);

        $this->withCookie('guest_token', 'otp-ui-test')
            ->get('/en/checkout')
            ->assertOk();

        $checkoutId = Session::get('active_checkout_id');
        $checkoutService = app(CheckoutService::class);

        // Initial submit with no code — should send an OTP, flash a
        // 'success' message (the checkout layout only renders
        // error/success/warning session keys — the controller used to flash
        // 'status', which the layout never displayed), and persist the
        // pending email/phone so the view can render the code-entry state.
        $response = $this->withCookie('guest_token', 'otp-ui-test')
            ->from('/en/checkout')
            ->post('/en/checkout', [
                'email' => 'pending@example.com',
                'phone' => '+49123456',
            ]);
        $response->assertRedirect('/en/checkout');
        $response->assertSessionHas('success');

        $checkout = $checkoutService->get($checkoutId);
        $this->assertEquals('pending@example.com', $checkout['data']['otp_pending_email']);
        $this->assertEquals('+49123456', $checkout['data']['otp_pending_phone']);
        $this->assertEquals(1, $checkout['step'], 'Should still be on step 1 awaiting the code');

        $firstOtp = \App\Models\Otp::where('email', 'pending@example.com')
            ->where('purpose', OtpPurpose::GuestCheckout)
            ->first();
        $this->assertNotNull($firstOtp);

        // Resending immediately hits OtpService's own cooldown — the
        // 'resend' flag correctly routes back into the same
        // generate()-or-error branch as the initial send.
        $response = $this->withCookie('guest_token', 'otp-ui-test')
            ->post('/en/checkout', [
                'email' => 'pending@example.com',
                'phone' => '+49123456',
                'resend' => '1',
            ]);
        $response->assertRedirect();
        $response->assertSessionHas('error');

        // "Use a different email" clears the pending state without sending
        // anything new.
        $response = $this->withCookie('guest_token', 'otp-ui-test')
            ->post('/en/checkout', ['change_email' => '1']);
        $response->assertRedirect();
        $checkout = $checkoutService->get($checkoutId);
        $this->assertNull($checkout['data']['otp_pending_email']);

        // Verifying with the correct code advances to step 2.
        $response = $this->withCookie('guest_token', 'otp-ui-test')
            ->post('/en/checkout', [
                'email' => 'pending@example.com',
                'phone' => '+49123456',
                'otp' => $firstOtp->otp_code,
            ]);
        $response->assertRedirect();
        $checkout = $checkoutService->get($checkoutId);
        $this->assertEquals(2, $checkout['step']);
        $this->assertTrue($checkout['data']['otp_verified']);
    }

    #[Test]
    public function guest_checkout_otp_mail_failure_shows_generic_message_not_raw_smtp_error(): void
    {
        // Regression: found live via Playwright against a broken local SMTP
        // config — a real mail-transport failure (Symfony's
        // TransportException extends RuntimeException) used to be caught by
        // the same catch(\RuntimeException) block as OtpService::generate()'s
        // resend-cooldown exception, surfacing the raw SMTP protocol error
        // text as a toast to the customer.
        // Real production installs run with APP_DEBUG=false — that's the
        // behavior this test protects. The testing environment defaults
        // debug on (.env.testing), which deliberately appends exception
        // detail to error messages (same pattern as the pre-existing
        // order-creation-failure handling) for local troubleshooting.
        config(['app.debug' => false]);

        $pendingMail = \Mockery::mock(\Illuminate\Mail\PendingMail::class);
        $pendingMail->shouldReceive('send')->andThrow(new \RuntimeException(
            'Expected response code "250" but got code "530", with message "530 5.7.1 Authentication required".'
        ));
        Mail::shouldReceive('to')->andReturn($pendingMail);

        $cart = Cart::create(['guest_token' => 'otp-mail-fail-test', 'expires_at' => now()->addDays(7)]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price_at_add' => $this->product->price,
        ]);

        $this->withCookie('guest_token', 'otp-mail-fail-test')
            ->get('/en/checkout')
            ->assertOk();

        $response = $this->withCookie('guest_token', 'otp-mail-fail-test')
            ->post('/en/checkout', ['email' => 'mailfail@example.com']);

        $response->assertRedirect();
        $response->assertSessionHas('error', function ($message) {
            return $message === __('checkout.otp_send_failed')
                && ! str_contains($message, '530')
                && ! str_contains($message, 'SMTP');
        });

        // The OTP row was still created (generate() ran before the mail
        // send failure) and the user still lands on the code-entry
        // sub-step, so "Resend code" remains a working retry path.
        $checkoutId = Session::get('active_checkout_id');
        $checkout = app(CheckoutService::class)->get($checkoutId);
        $this->assertEquals('mailfail@example.com', $checkout['data']['otp_pending_email']);
        $this->assertDatabaseHas('otps', [
            'email' => 'mailfail@example.com',
            'purpose' => OtpPurpose::GuestCheckout,
        ]);
    }

    #[Test]
    public function b2b_vat_validation_works(): void
    {
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
        $checkoutService = app(CheckoutService::class);
        $checkoutService->update($checkoutId, [
            'guest_email' => 'vat@example.com',
            'otp_verified' => true,
            'step' => 2,
        ]);

        // Submit step 2 with B2B VAT data
        $response = $this->post('/en/checkout', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'street' => 'Test St 1',
            'city' => 'Berlin',
            'postal_code' => '10115',
            'country_code' => 'DE',
            'is_b2b' => 1,
            'company_name' => 'Test GmbH',
            'vat_number' => 'DE123456789',
            'vat_valid' => 1,
        ]);
        $response->assertRedirect();

        // Verify B2B data was stored
        $checkout = $checkoutService->get($checkoutId);
        $this->assertTrue($checkout['data']['is_b2b']);
        $this->assertEquals('Test GmbH', $checkout['data']['company_name']);
        $this->assertEquals('DE123456789', $checkout['data']['vat_number']);
        $this->assertTrue($checkout['data']['vat_valid']);
        $this->assertTrue($checkout['data']['vat_exempt']);
        $this->assertEquals(3, $checkout['step']);
    }

    #[Test]
    public function checkout_view_receives_seconds_remaining(): void
    {
        $cart = Cart::create([
            'guest_token' => 'timeout-test',
            'expires_at' => now()->addDays(7),
        ]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price_at_add' => $this->product->price,
        ]);

        $response = $this->withCookie('guest_token', 'timeout-test')
            ->get('/en/checkout');

        $response->assertStatus(200);
        $response->assertViewHas('secondsRemaining');
        $this->assertGreaterThan(0, $response->viewData('secondsRemaining'));
    }

    #[Test]
    public function bank_transfer_proof_upload_is_validated(): void
    {
        // Create an order directly for testing payment processing
        $cart = Cart::create([
            'guest_token' => 'proof-test',
            'expires_at' => now()->addDays(7),
        ]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price_at_add' => $this->product->price,
        ]);

        $this->withCookie('guest_token', 'proof-test')
            ->get('/en/checkout')
            ->assertOk();

        // Skip to step 5
        $checkoutId = Session::get('active_checkout_id');
        app(CheckoutService::class)->update($checkoutId, [
            'contact_email' => 'proof@example.com',
            'guest_email' => 'proof@example.com',
            'otp_verified' => true,
            'step' => 5,
            'shipping_address' => ['first_name' => 'John', 'last_name' => 'Doe', 'street' => 'St', 'city' => 'Berlin', 'postal_code' => '10115', 'country_code' => 'DE'],
            'shipping_method_id' => $this->shippingMethod->id,
            'payment_method' => 'bank_transfer',
        ]);

        // Place order (bank transfer)
        $response = $this->post('/en/checkout', [
            'payment_method' => 'bank_transfer',
        ]);
        $response->assertRedirect();

        // Verify order created
        $order = \App\Models\Order::where('guest_email', 'proof@example.com')->first();
        $this->assertNotNull($order);

        // Test payment processing for bank transfer (returns redirect, not JSON)
        $response = $this->post("/en/checkout/payment/{$order->order_number}/process", [
            'payment_method' => 'bank_transfer',
        ]);
        $response->assertRedirect();

        // Verify payment gateway_response includes method
        $payment = $order->payment->fresh();
        $this->assertNotNull($payment);
        $this->assertEquals('pending', $payment->status->value);
        $this->assertArrayHasKey('method', $payment->gateway_response);
        $this->assertEquals('bank_transfer', $payment->gateway_response['method']);
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