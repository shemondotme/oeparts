<?php

namespace Tests\Feature;

use App\Enums\OtpPurpose;
use App\Models\Otp;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthMediumFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(\Illuminate\Cache\RateLimiter::class)->clear('login:victim@example.com|127.0.0.1');
    }

    // ── auth-2: login throttle keyed by email+IP, not IP alone ─────────────

    #[Test]
    public function exhausting_the_login_throttle_for_one_email_does_not_lock_out_a_different_email_on_the_same_ip(): void
    {
        User::factory()->create(['email' => 'victim@example.com', 'password' => Hash::make('CorrectPass123!'), 'email_verified_at' => now(), 'is_active' => true]);
        User::factory()->create(['email' => 'other@example.com', 'password' => Hash::make('CorrectPass123!'), 'email_verified_at' => now(), 'is_active' => true]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/en/login', ['email' => 'victim@example.com', 'password' => 'wrong-password']);
        }

        // The 6th attempt against the SAME email is throttled...
        $this->postJson('/en/login', ['email' => 'victim@example.com', 'password' => 'wrong-password'])
            ->assertStatus(429);

        // ...but a different email from the same IP is unaffected.
        $this->postJson('/en/login', ['email' => 'other@example.com', 'password' => 'CorrectPass123!'])
            ->assertStatus(200);
    }

    // ── auth-6: expired-OTP cleanup respects the recently-verified grace window ─

    #[Test]
    public function logs_clean_does_not_delete_a_recently_verified_otp_still_inside_its_grace_window(): void
    {
        $recentlyVerified = Otp::create([
            'email' => 'a@example.com', 'otp_code' => '123456', 'purpose' => OtpPurpose::ContactForm, 'ip_address' => '127.0.0.1',
            'expires_at' => now()->subMinutes(5), 'verified_at' => now()->subMinutes(10),
        ]);
        $longVerified = Otp::create([
            'email' => 'b@example.com', 'otp_code' => '123456', 'purpose' => OtpPurpose::ContactForm, 'ip_address' => '127.0.0.1',
            'expires_at' => now()->subHours(2), 'verified_at' => now()->subMinutes(45),
        ]);
        $neverVerified = Otp::create([
            'email' => 'c@example.com', 'otp_code' => '123456', 'purpose' => OtpPurpose::ContactForm, 'ip_address' => '127.0.0.1',
            'expires_at' => now()->subMinutes(5), 'verified_at' => null,
        ]);

        Artisan::call('otp:clean');

        $this->assertDatabaseHas('otps', ['id' => $recentlyVerified->id]);
        $this->assertDatabaseMissing('otps', ['id' => $longVerified->id]);
        $this->assertDatabaseMissing('otps', ['id' => $neverVerified->id]);
    }

    // ── auth-9: account password-change form honors the configurable minimum ─

    #[Test]
    public function changing_password_enforces_the_configured_minimum_length_not_a_hardcoded_8(): void
    {
        Setting::updateOrCreate(['group' => 'auth', 'key' => 'customer_password_min'], ['value' => '12', 'type' => 'integer']);

        $user = User::factory()->create(['password' => Hash::make('OldPassw0rd!'), 'is_active' => true]);
        $this->actingAs($user, 'web');

        $this->post('/en/account/settings/password', [
            'current_password' => 'OldPassw0rd!',
            'new_password' => 'Ab1!efgh', // 8 chars — used to pass, now below the configured 12
            'new_password_confirmation' => 'Ab1!efgh',
        ])->assertSessionHasErrors('new_password');

        $this->assertTrue(Hash::check('OldPassw0rd!', $user->fresh()->password));
    }
}
