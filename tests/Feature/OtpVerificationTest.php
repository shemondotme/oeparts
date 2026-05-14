<?php

namespace Tests\Feature;

use App\Models\EmailVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtpVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_can_be_sent_for_email_verification()
    {
        $response = $this->postJson(route('frontend.auth.resend-otp', ['lang' => 'en']), [
            'email' => 'test@example.com',
            'purpose' => 'email_verify',
        ], [
            'X-CSRF-TOKEN' => csrf_token(),
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('email_verifications', [
            'email' => 'test@example.com',
            'purpose' => 'email_verify',
        ]);
    }

    public function test_otp_can_be_verified()
    {
        $verification = EmailVerification::factory()->create([
            'email' => 'test@example.com',
            'purpose' => 'email_verify',
        ]);

        $response = $this->postJson(route('frontend.auth.verify-otp', ['lang' => 'en']), [
            'email' => 'test@example.com',
            'otp' => $verification->code,
            'purpose' => 'email_verify',
        ], [
            'X-CSRF-TOKEN' => csrf_token(),
        ]);

        $response->assertOk();
        $this->assertTrue($verification->fresh()->is_verified);
    }

    public function test_otp_verification_fails_with_wrong_code()
    {
        EmailVerification::factory()->create([
            'email' => 'test@example.com',
            'purpose' => 'email_verify',
        ]);

        $response = $this->postJson(route('frontend.auth.verify-otp', ['lang' => 'en']), [
            'email' => 'test@example.com',
            'otp' => '000000',
            'purpose' => 'email_verify',
        ], [
            'X-CSRF-TOKEN' => csrf_token(),
        ]);

        $response->assertStatus(422);
    }

    public function test_otp_code_is_six_digits()
    {
        $verification = EmailVerification::factory()->create();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $verification->code);
    }

    public function test_otp_expires_after_ten_minutes()
    {
        $verification = EmailVerification::factory()->create([
            'created_at' => now()->subMinutes(11),
        ]);

        $response = $this->postJson(route('frontend.auth.verify-otp', ['lang' => 'en']), [
            'email' => $verification->email,
            'otp' => $verification->code,
            'purpose' => $verification->purpose,
        ], [
            'X-CSRF-TOKEN' => csrf_token(),
        ]);

        $response->assertStatus(422);
    }
}
