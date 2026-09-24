<?php

namespace Tests\Feature;

use App\Enums\OtpPurpose;
use App\Enums\SequenceType;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Order;
use App\Models\Otp;
use App\Models\Product;
use App\Models\Sequence;
use App\Models\ShippingCountry;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
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
        ShippingCountry::create(['zone_id' => $zone->id, 'country_code' => 'DE', 'country_name' => 'Germany']);
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
            'type' => SequenceType::Order,
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
        $otp = Otp::where('email', 'guest@example.com')->where('purpose', OtpPurpose::GuestCheckout)->first();

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
            'terms_accepted' => true,
        ]);

        $response = $this->actingAs($this->user)->postJson("/api/checkout/{$checkoutId}/step5", [
            'payment_method' => 'paysera',
        ]);

        $response->assertCreated()->assertJsonPath('data.payment_method', 'paysera');

        $order = Order::where('user_id', $this->user->id)->firstOrFail();
        $this->assertSame('paysera', $order->payment_method->value);
    }

    // ── Phase 11 (Compliance/Legal): step5 used to place a real, chargeable
    // order with no dependency at all on step4 ever having been called —
    // each step is its own independently-callable REST endpoint here
    // (unlike the web checkout flow, which always routes through the
    // session's own server-side step counter and structurally can't skip
    // ahead). A client could call /start then straight to /step5 and
    // complete an order having never agreed to any terms. ──

    #[Test]
    public function step5_rejects_placing_an_order_if_step4_terms_acceptance_was_never_submitted(): void
    {
        $checkoutId = $this->startCheckout();

        // Every other step's data is present and valid — step4 (terms) is
        // the only one deliberately skipped.
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
            'payment_method' => 'card',
        ]);

        $response->assertStatus(409);
        $this->assertDatabaseMissing('orders', ['user_id' => $this->user->id]);
    }

    #[Test]
    public function calling_step4_then_step5_through_the_real_endpoints_places_the_order(): void
    {
        $checkoutId = $this->startCheckout();

        app(CheckoutService::class)->update($checkoutId, [
            'step' => 4,
            'contact_email' => $this->user->email,
            'shipping_address' => [
                'first_name' => 'John', 'last_name' => 'Doe', 'street' => 'St',
                'city' => 'Berlin', 'postal_code' => '10115', 'country_code' => 'DE',
            ],
            'shipping_method_id' => $this->shippingMethod->id,
        ]);

        $this->actingAs($this->user)
            ->postJson("/api/checkout/{$checkoutId}/step4", ['agree_terms' => true])
            ->assertOk();

        $response = $this->actingAs($this->user)->postJson("/api/checkout/{$checkoutId}/step5", [
            'payment_method' => 'card',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('orders', ['user_id' => $this->user->id]);
    }

    // ── stateless by design: state lives in the store, not the session ───────

    #[Test]
    public function a_started_checkout_can_be_continued_when_no_session_survives_between_requests(): void
    {
        // The API group has no session middleware in production, so anything
        // written with Session::put() is gone when the request ends — verified
        // against the real stack: POST /checkout/start returned an id and the
        // very next GET for it answered 404. The array session driver used by
        // the test suite persists in-process, which is why every other test
        // here passed regardless. Wiping the session between requests is what
        // production looks like.
        $checkoutId = $this->startCheckout();
        Session::flush();

        $this->actingAs($this->user)->getJson("/api/checkout/{$checkoutId}")
            ->assertOk()
            ->assertJsonPath('data.step', 1);
        Session::flush();

        $this->actingAs($this->user)->postJson("/api/checkout/{$checkoutId}/step1", ['email' => 'shopper@example.com'])
            ->assertOk()
            ->assertJsonPath('data.step', 2);
        Session::flush();

        $this->actingAs($this->user)->getJson("/api/checkout/{$checkoutId}")
            ->assertOk()
            ->assertJsonPath('data.step', 2)
            ->assertJsonPath('data.data.contact_email', 'shopper@example.com');
    }

    #[Test]
    public function the_checkout_state_is_not_written_to_the_session_at_all(): void
    {
        $checkoutId = $this->startCheckout();

        $this->assertArrayNotHasKey("checkout.{$checkoutId}", Session::all());
        $this->assertNotNull(app(CheckoutService::class)->get($checkoutId));
    }

    // ── the id is a bearer credential, so the caller must own the cart ───────

    #[Test]
    public function another_user_cannot_read_or_change_a_checkout_they_do_not_own(): void
    {
        $checkoutId = $this->startCheckout();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->getJson("/api/checkout/{$checkoutId}")->assertNotFound();

        $this->actingAs($intruder)->postJson("/api/checkout/{$checkoutId}/step1", ['email' => 'evil@example.com'])->assertNotFound();
        $this->actingAs($intruder)->postJson("/api/checkout/{$checkoutId}/step2", [
            'first_name' => 'E', 'last_name' => 'V', 'street' => 'x', 'city' => 'y', 'postal_code' => '1', 'country_code' => 'DE',
        ])->assertNotFound();
        $this->actingAs($intruder)->postJson("/api/checkout/{$checkoutId}/step5", ['payment_method' => 'bank_transfer'])->assertNotFound();

        $state = app(CheckoutService::class)->get($checkoutId);
        $this->assertSame(1, $state['step'], 'the rejected calls must not have advanced the owner\'s checkout');
        $this->assertNull($state['data']['contact_email']);
        $this->assertSame(0, Order::count());
    }

    #[Test]
    public function an_anonymous_caller_cannot_use_a_user_owned_checkout(): void
    {
        $checkoutId = $this->startCheckout();

        // actingAs() sticks to the guard for the rest of the test — drop it so
        // the following calls really are anonymous.
        $this->app['auth']->forgetGuards();

        $this->getJson("/api/checkout/{$checkoutId}")->assertNotFound();
        $this->postJson("/api/checkout/{$checkoutId}/step1", ['email' => 'anon@example.com'])->assertNotFound();
    }

    #[Test]
    public function a_guest_checkout_is_only_usable_with_the_guest_token_of_its_own_cart(): void
    {
        $token = str_repeat('a', 32);
        $cart = Cart::create(['user_id' => null, 'guest_token' => $token, 'expires_at' => now()->addDays(7)]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $this->product->id, 'quantity' => 1, 'price_at_add' => $this->product->price]);

        // The JSON helpers only send cookies when withCredentials() is set.
        $checkoutId = $this->withCredentials()->withUnencryptedCookie('guest_token', $token)
            ->postJson('/api/checkout/start')
            ->assertCreated()
            ->json('data.checkout_id');

        $this->withCredentials()->withUnencryptedCookie('guest_token', $token)
            ->getJson("/api/checkout/{$checkoutId}")->assertOk();

        // Somebody else's guest token — same treatment as a missing checkout.
        $this->withCredentials()->withUnencryptedCookie('guest_token', str_repeat('b', 32))
            ->getJson("/api/checkout/{$checkoutId}")->assertNotFound();

        // No token at all (the cookies set above persist on the test case, so clear them).
        $this->unencryptedCookies = [];
        $this->withCredentials()->getJson("/api/checkout/{$checkoutId}")->assertNotFound();
    }

    #[Test]
    public function an_unknown_checkout_id_is_a_404_not_a_server_error(): void
    {
        $this->actingAs($this->user)->getJson('/api/checkout/00000000-0000-0000-0000-000000000000')->assertNotFound();
    }
}
