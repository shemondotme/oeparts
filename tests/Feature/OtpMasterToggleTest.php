<?php

namespace Tests\Feature;

use App\Enums\SettingType;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression tests for the `security.otp_enabled` master switch (Admin ->
 * Settings -> Security) — a single kill switch meant to skip every
 * storefront OTP/Two-Step step (registration/login email verify, guest
 * checkout) when off, so testing/staging environments without working SMTP
 * are never blocked. Restores full behavior when on (the default).
 */
class OtpMasterToggleTest extends TestCase
{
    use RefreshDatabase;

    private function disableOtp(): void
    {
        Setting::updateOrCreate(
            ['group' => 'security', 'key' => 'otp_enabled'],
            ['value' => 'false', 'type' => SettingType::Boolean],
        );
    }

    #[Test]
    public function registration_skips_otp_and_logs_in_immediately_when_master_switch_is_off(): void
    {
        $this->disableOtp();

        $response = $this->postJson('/en/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'Xk9#mP2$vR',
            'password_confirmation' => 'Xk9#mP2$vR',
            'agree_terms' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['requires_otp' => false]]);

        $this->assertDatabaseMissing('otps', ['email' => 'jane@example.com']);

        $user = User::where('email', 'jane@example.com')->first();
        $this->assertNotNull($user->email_verified_at, 'Account should be auto-verified when OTP is disabled');
        $this->assertAuthenticatedAs($user, 'web');
    }

    #[Test]
    public function login_skips_otp_for_unverified_account_when_master_switch_is_off(): void
    {
        $this->disableOtp();

        $user = User::factory()->create([
            'email' => 'unverified@example.com',
            'password' => Hash::make('Password123!'),
            'email_verified_at' => null,
        ]);

        $response = $this->postJson('/en/login', [
            'email' => 'unverified@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonMissingPath('data.requires_otp');

        $this->assertDatabaseMissing('otps', ['email' => 'unverified@example.com']);
        $this->assertAuthenticatedAs($user->fresh(), 'web');
    }

    #[Test]
    public function guest_checkout_skips_otp_entirely_when_master_switch_is_off_even_if_cart_setting_is_on(): void
    {
        $this->disableOtp();

        // Granular per-feature toggle stays ON — the master switch must
        // still win and skip OTP.
        Setting::updateOrCreate(
            ['group' => 'cart', 'key' => 'otp_required_guest'],
            ['value' => 'true', 'type' => SettingType::Boolean],
        );

        $condition = Condition::firstOrCreate(
            ['slug' => 'new'],
            ['name' => 'New', 'bg_color' => '#ecfdf5', 'text_color' => '#065f46', 'is_active' => true]
        );
        $manufacturer = Manufacturer::create([
            'name' => 'Test Manufacturer', 'slug' => 'test-manufacturer-otp', 'country_code' => 'DE', 'is_active' => true,
        ]);
        $product = Product::create([
            'manufacturer_id' => $manufacturer->id,
            'oem_number' => 'OTPTOGGLE1',
            'normalized_oem' => 'OTPTOGGLE1',
            'name' => ['en' => 'Test Product'],
            'description' => ['en' => 'Test description'],
            'price' => 50.00,
            'condition_id' => $condition->id,
            'is_in_stock' => true,
            'is_active' => true,
        ]);

        $cart = Cart::create(['guest_token' => 'master-off-test', 'expires_at' => now()->addDays(7)]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price_at_add' => $product->price]);

        $this->withCookie('guest_token', 'master-off-test')->get('/en/checkout')->assertOk();

        $checkoutId = Session::get('active_checkout_id');

        // A single submit with no OTP code must advance straight to step 2 —
        // no OTP generated, no pending state.
        $response = $this->withCookie('guest_token', 'master-off-test')
            ->post('/en/checkout', ['email' => 'skip-otp@example.com']);
        $response->assertRedirect();

        $this->assertDatabaseMissing('otps', ['email' => 'skip-otp@example.com']);

        $checkout = app(CheckoutService::class)->get($checkoutId);
        $this->assertEquals(2, $checkout['step']);
        $this->assertTrue($checkout['data']['otp_verified']);
        $this->assertNull($checkout['data']['otp_pending_email']);
    }
}
