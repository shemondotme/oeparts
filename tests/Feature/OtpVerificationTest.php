<?php

namespace Tests\Feature;

use App\Enums\OtpPurpose;
use App\Models\Otp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OtpVerificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function otp_can_be_sent_for_email_verification(): void
    {
        $response = $this->postJson(route('frontend.auth.resend-otp', ['lang' => 'en']), [
            'email'   => 'test@example.com',
            'purpose' => 'email_verify',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('otps', [
            'email'   => 'test@example.com',
            'purpose' => 'email_verify',
        ]);
    }

    #[Test]
    public function otp_can_be_verified(): void
    {
        $otp = Otp::create([
            'email'      => 'test@example.com',
            'otp_code'   => '123456',
            'purpose'    => OtpPurpose::EmailVerify->value,
            'expires_at' => now()->addMinutes(10),
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->postJson(route('frontend.auth.verify-otp', ['lang' => 'en']), [
            'email'   => 'test@example.com',
            'otp'     => '123456',
            'purpose' => 'email_verify',
        ]);

        $response->assertOk();
        $this->assertNotNull($otp->fresh()->verified_at);
    }

    #[Test]
    public function otp_verification_fails_with_wrong_code(): void
    {
        Otp::create([
            'email'      => 'test@example.com',
            'otp_code'   => '999999',
            'purpose'    => OtpPurpose::EmailVerify->value,
            'expires_at' => now()->addMinutes(10),
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->postJson(route('frontend.auth.verify-otp', ['lang' => 'en']), [
            'email'   => 'test@example.com',
            'otp'     => '000000',
            'purpose' => 'email_verify',
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function otp_code_is_six_digits(): void
    {
        $otp = Otp::create([
            'email'      => 'test@example.com',
            'otp_code'   => '654321',
            'purpose'    => OtpPurpose::EmailVerify->value,
            'expires_at' => now()->addMinutes(10),
            'ip_address' => '127.0.0.1',
        ]);

        $this->assertMatchesRegularExpression('/^\d{6}$/', $otp->otp_code);
    }

    #[Test]
    public function otp_expires_after_ten_minutes(): void
    {
        $otp = Otp::create([
            'email'      => 'expired@example.com',
            'otp_code'   => '111111',
            'purpose'    => OtpPurpose::EmailVerify->value,
            'expires_at' => now()->subMinutes(11),
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->postJson(route('frontend.auth.verify-otp', ['lang' => 'en']), [
            'email'   => $otp->email,
            'otp'     => $otp->otp_code,
            'purpose' => $otp->purpose->value,
        ]);

        $response->assertStatus(422);
    }
}
