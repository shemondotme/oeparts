<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Otp;
use App\Enums\OtpPurpose;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear login rate limiter between tests
        app(\Illuminate\Cache\RateLimiter::class)->clear('login:127.0.0.1');
    }

    // ── Registration ───────────────────────────────────────────────────────────

    #[Test]
    public function user_can_register_with_valid_data(): void
    {
        $response = $this->postJson('/en/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Xk9#mP2$vR',
            'password_confirmation' => 'Xk9#mP2$vR',
            'agree_terms' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['requires_otp']
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'name' => 'John Doe',
        ]);

        $user = User::where('email', 'john@example.com')->first();
        $this->assertTrue(Hash::check('Xk9#mP2$vR', $user->password));
    }

    #[Test]
    public function registration_requires_valid_email(): void
    {
        $response = $this->postJson('/en/register', [
            'name' => 'John Doe',
            'email' => 'invalid-email',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'agree_terms' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function registration_requires_strong_password(): void
    {
        $response = $this->postJson('/en/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'weak',
            'password_confirmation' => 'weak',
            'agree_terms' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    #[Test]
    public function registration_requires_terms_agreement(): void
    {
        $response = $this->postJson('/en/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'agree_terms' => false,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['agree_terms']);
    }

    // ── Login ─────────────────────────────────────────────────────────────────

    #[Test]
    public function user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('Password123!'),
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/en/login', [
            'email' => 'john@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['user' => ['id', 'name', 'email'], 'token']
            ]);
    }

    #[Test]
    public function login_fails_with_incorrect_password(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $response = $this->postJson('/en/login', [
            'email' => 'john@example.com',
            'password' => 'WrongPassword!',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid email or password.',
            ]);
    }

    #[Test]
    public function login_requires_email_verification(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('Password123!'),
            'email_verified_at' => null,
        ]);

        $response = $this->postJson('/en/login', [
            'email' => 'john@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['requires_otp']
            ]);

        $this->assertDatabaseHas('otps', [
            'email' => 'john@example.com',
            'purpose' => OtpPurpose::EmailVerify->value,
        ]);
    }

    // ── OTP Verification ──────────────────────────────────────────────────────

    #[Test]
    public function user_can_verify_email_with_correct_otp(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'email_verified_at' => null,
        ]);

        $otp = Otp::create([
            'email' => 'john@example.com',
            'otp_code' => '123456',
            'purpose' => OtpPurpose::EmailVerify,
            'expires_at' => now()->addMinutes(10),
            'ip_address' => '127.0.0.1',
            'attempts' => 0,
        ]);

        $response = $this->postJson('/en/verify-otp', [
            'email' => 'john@example.com',
            'otp' => '123456',
            'purpose' => OtpPurpose::EmailVerify->value,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'OTP verified successfully.',
            ]);

        // OTP should be marked as verified (verified_at set) but not deleted
        $this->assertDatabaseHas('otps', [
            'email' => 'john@example.com',
            'otp_code' => '123456',
            'verified_at' => now(),
        ]);

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
    }

    #[Test]
    public function otp_verification_fails_with_incorrect_code(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'email_verified_at' => null,
        ]);

        $otp = Otp::create([
            'email' => 'john@example.com',
            'otp_code' => '123456',
            'purpose' => OtpPurpose::EmailVerify,
            'expires_at' => now()->addMinutes(10),
            'ip_address' => '127.0.0.1',
            'attempts' => 0,
        ]);

        $response = $this->postJson('/en/verify-otp', [
            'email' => 'john@example.com',
            'otp' => '999999',
            'purpose' => OtpPurpose::EmailVerify->value,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid verification code.',
            ]);
    }

    #[Test]
    public function user_can_resend_otp(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'email_verified_at' => null,
        ]);

        $response = $this->postJson('/en/resend-otp', [
            'email' => 'john@example.com',
            'purpose' => OtpPurpose::EmailVerify->value,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'OTP resent successfully.',
            ]);

        $this->assertDatabaseHas('otps', [
            'email' => 'john@example.com',
            'purpose' => OtpPurpose::EmailVerify->value,
        ]);
    }

    // ── Logout ────────────────────────────────────────────────────────────────

    #[Test]
    public function authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $response = $this->postJson('/en/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully.',
            ]);
    }

    // ── Password Reset ────────────────────────────────────────────────────────

    #[Test]
    public function user_can_request_password_reset(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
        ]);

        $response = $this->post('/en/reset-password', [
            'email' => 'john@example.com',
        ]);

        $response->assertStatus(302) // Redirect back
            ->assertSessionHas('status', 'Password reset link sent');
    }

    #[Test]
    public function password_reset_requires_valid_email(): void
    {
        $response = $this->post('/en/reset-password', [
            'email' => 'invalid-email',
        ]);

        $response->assertStatus(302) // Redirect back with validation errors
            ->assertSessionHasErrors(['email']);
    }

    // ── Account Routes (Protected) ────────────────────────────────────────────

    #[Test]
    public function guest_cannot_access_account_dashboard(): void
    {
        $response = $this->get('/en/account/dashboard');
        $response->assertStatus(302); // Redirects to login (or home with auth modal)
    }

    #[Test]
    public function authenticated_user_can_access_account_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $response = $this->get('/en/account/dashboard');
        $response->assertStatus(200);
    }

    #[Test]
    public function authenticated_user_can_view_orders(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $response = $this->get('/en/account/orders');
        $response->assertStatus(200);
    }

    #[Test]
    public function authenticated_user_can_view_settings(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $response = $this->get('/en/account/settings');
        $response->assertStatus(200);
    }

    #[Test]
    public function authenticated_user_can_view_addresses(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $response = $this->get('/en/account/addresses');
        $response->assertStatus(200);
    }
}