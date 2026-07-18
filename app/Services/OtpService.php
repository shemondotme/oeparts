<?php

namespace App\Services;

use App\Enums\OtpPurpose;
use App\Models\Otp;
use Illuminate\Support\Facades\Log;

/**
 * OtpService — generate and verify one-time passcodes.
 *
 * Settings consumed (never hardcoded):
 *   auth.otp_length         (default 6)
 *   auth.otp_expiry_minutes (default 10)
 *   auth.otp_max_attempts   (default 3)
 *   auth.otp_resend_cooldown (default 60 seconds)
 */
class OtpService
{
    /**
     * Result codes returned by verify().
     */
    const RESULT_OK           = 'ok';
    const RESULT_INVALID      = 'invalid';
    const RESULT_EXPIRED      = 'expired';
    const RESULT_MAX_ATTEMPTS = 'max_attempts';
    const RESULT_ALREADY_USED = 'already_used';

    /**
     * Generate a new OTP for the given email + purpose.
     * Any previous unverified OTP for the same email+purpose is replaced.
     *
     * @throws \RuntimeException if a resend cooldown is active
     */
    public function generate(string $email, OtpPurpose $purpose, ?string $ipAddress = null): Otp
    {
        $cooldown = (int) settings('auth.otp_resend_cooldown', 60);

        // Enforce resend cooldown
        $existing = Otp::where('email', $email)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->latest('expires_at')
            ->first();

        if ($existing) {
            $sentAt   = $existing->expires_at->subMinutes((int) settings('auth.otp_expiry_minutes', 10));
            $cooldownEnd = $sentAt->addSeconds($cooldown);

            if (now()->lt($cooldownEnd)) {
                $wait = (int) ceil(now()->diffInSeconds($cooldownEnd, false));
                throw new \RuntimeException(__('otp.wait_before_resend', ['wait' => $wait]));
            }

            // Invalidate old OTPs
            Otp::where('email', $email)
                ->where('purpose', $purpose)
                ->whereNull('verified_at')
                ->delete();
        }

        $length  = (int) settings('auth.otp_length', 6);
        $expiry  = (int) settings('auth.otp_expiry_minutes', 10);
        $code    = $this->generateCode($length);

        $otp = Otp::create([
            'email'      => $email,
            'otp_code'   => $code,
            'purpose'    => $purpose,
            'expires_at' => now()->addMinutes($expiry),
            'attempts'   => 0,
            'ip_address' => $ipAddress,
        ]);

        Log::info('OTP generated', ['email' => $email, 'purpose' => $purpose->value]);

        return $otp;
    }

    /**
     * Verify a submitted OTP code.
     *
     * Returns one of the RESULT_* constants.
     * On success, marks the OTP as verified.
     */
    public function verify(string $email, string $code, OtpPurpose $purpose): string
    {
        $maxAttempts = (int) settings('auth.otp_max_attempts', 3);

        $otp = Otp::where('email', $email)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->latest('expires_at')
            ->first();

        if (! $otp) {
            return self::RESULT_INVALID;
        }

        if ($otp->isVerified()) {
            return self::RESULT_ALREADY_USED;
        }

        if ($otp->isExpired()) {
            return self::RESULT_EXPIRED;
        }

        if ($otp->attempts >= $maxAttempts) {
            return self::RESULT_MAX_ATTEMPTS;
        }

        // Increment attempt count
        $otp->increment('attempts');

        if (! hash_equals($otp->otp_code, $code)) {
            return self::RESULT_INVALID;
        }

        $otp->update(['verified_at' => now()]);

        Log::info('OTP verified', ['email' => $email, 'purpose' => $purpose->value]);

        return self::RESULT_OK;
    }

    /**
     * Master kill switch for storefront OTP/Two-Step verification
     * (Admin → Settings → Security). When disabled, every storefront OTP
     * touchpoint (registration/login email verify, guest checkout) must
     * skip its verification step entirely rather than calling generate()/
     * verify() — this is the single source of truth callers check first.
     */
    public function enabled(): bool
    {
        return filter_var(settings('security.otp_enabled', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Check if there is a valid (unexpired, unverified) OTP for the given email+purpose.
     */
    public function hasPending(string $email, OtpPurpose $purpose): bool
    {
        return Otp::where('email', $email)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Check whether the given email+purpose was successfully verified recently.
     *
     * Used by multi-step flows where verification happens in an earlier request
     * than the action it gates (e.g. verify-email-then-submit-a-form) — the OTP
     * row's verified_at is the server-authoritative proof, since the client
     * cannot be trusted to self-report "I verified".
     */
    public function isRecentlyVerified(string $email, OtpPurpose $purpose, int $withinMinutes = 30): bool
    {
        return Otp::where('email', $email)
            ->where('purpose', $purpose)
            ->whereNotNull('verified_at')
            ->where('verified_at', '>=', now()->subMinutes($withinMinutes))
            ->exists();
    }

    /**
     * Get the human-readable message for a verification result code.
     */
    public function message(string $result): string
    {
        return match ($result) {
            self::RESULT_OK           => __('otp.code_verified'),
            self::RESULT_INVALID      => __('otp.invalid_code'),
            self::RESULT_EXPIRED      => __('otp.code_expired'),
            self::RESULT_MAX_ATTEMPTS => __('otp.max_attempts'),
            self::RESULT_ALREADY_USED => __('otp.already_used'),
            default                   => __('otp.verification_failed'),
        };
    }

    /**
     * Generate a numeric OTP code of given length.
     */
    private function generateCode(int $length): string
    {
        $min = (int) str_pad('1', $length, '0');
        $max = (int) str_repeat('9', $length);

        return str_pad((string) random_int($min, $max), $length, '0', STR_PAD_LEFT);
    }
}
