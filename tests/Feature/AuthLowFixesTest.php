<?php

namespace Tests\Feature;

use App\Enums\OtpPurpose;
use App\Models\Otp;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthLowFixesTest extends TestCase
{
    use RefreshDatabase;

    // ── auth-7: OTP generation can't leave two live rows for the same email+purpose ──

    #[Test]
    public function generating_an_otp_while_a_lock_is_held_for_the_same_email_and_purpose_is_rejected(): void
    {
        $lock = \Illuminate\Support\Facades\Cache::lock('otp.generate:race@example.com:' . OtpPurpose::EmailVerify->value, 10);
        $this->assertTrue($lock->get(), 'test setup: must be able to acquire the lock first');

        try {
            $this->expectException(\RuntimeException::class);
            app(OtpService::class)->generate('race@example.com', OtpPurpose::EmailVerify);
        } finally {
            $lock->release();
            $this->assertSame(0, Otp::where('email', 'race@example.com')->count(), 'a lock-blocked generate() must not persist a row');
        }
    }

    #[Test]
    public function normal_otp_generation_still_works_once_the_lock_is_free(): void
    {
        $otp = app(OtpService::class)->generate('normal@example.com', OtpPurpose::EmailVerify, '127.0.0.1');

        $this->assertNotNull($otp->id);
        $this->assertSame(1, Otp::where('email', 'normal@example.com')->whereNull('verified_at')->count());
    }

    // ── auth-15: account self-delete requires re-entering the current password ──

    #[Test]
    public function deleting_the_account_without_the_correct_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => Hash::make('CorrectPass123!')]);
        $this->actingAs($user, 'web');

        $response = $this->delete('/en/account', ['current_password' => 'WrongPassword!']);

        $response->assertSessionHasErrors('current_password');
        $this->assertNotNull($user->fresh());
        $this->assertNotSame('Deleted User', $user->fresh()->name);
    }

    #[Test]
    public function deleting_the_account_with_the_correct_password_succeeds(): void
    {
        $user = User::factory()->create(['password' => Hash::make('CorrectPass123!')]);
        $this->actingAs($user, 'web');

        $response = $this->delete('/en/account', ['current_password' => 'CorrectPass123!']);

        $response->assertRedirect();
        $this->assertSame('Deleted User', $user->fresh()->name);
        $this->assertTrue($user->fresh()->trashed());
    }
}
