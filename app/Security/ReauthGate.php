<?php

namespace App\Security;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * The one "confirm your password" check behind every destructive admin action
 * (restore, delete backups, delete telemetry logs, apply an update, ...).
 *
 * It used to be copied into three pages, none of which limited wrong guesses: a
 * hijacked admin session could try passwords against a restore or an update as
 * fast as it could click. Now a wrong password counts against the admin+IP pair AND, separately, against the admin alone
 * (so rotating source addresses does not help); after MAX_ATTEMPTS wrong ones in DECAY_SECONDS even the right password
 * is refused until the window passes. The counter lives in the cache (the
 * RateLimiter), never in the session, so opening a new session does not reset it.
 */
final class ReauthGate
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 600;

    /** The per-admin budget ignores the source address, so rotating IPs cannot buy fresh guesses. */
    public const ADMIN_MAX_ATTEMPTS = 20;

    public const ADMIN_DECAY_SECONDS = 3600;

    /**
     * @param  string  $field  the form field the error is shown on
     * @param  string  $incorrectMessage  lets a page keep its own translated wording
     *
     * @throws ValidationException when the password is wrong or the admin is locked out
     */
    public static function confirm(?string $password, string $field = 'password', string $incorrectMessage = 'Your password is incorrect.'): void
    {
        $admin = auth('admin')->user();
        $key = self::key($admin?->getAuthIdentifier());
        $adminKey = 'reauth-admin:'.($admin?->getAuthIdentifier() ?? 'guest');

        $locked = RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS) ? $key : (RateLimiter::tooManyAttempts($adminKey, self::ADMIN_MAX_ATTEMPTS) ? $adminKey : null);

        if ($locked !== null) {
            $minutes = max(1, (int) ceil(RateLimiter::availableIn($locked) / 60));

            throw ValidationException::withMessages([
                $field => "Too many wrong passwords. Try again in {$minutes} minute".($minutes === 1 ? '' : 's').'.',
            ]);
        }

        if (! $admin || ! Hash::check((string) $password, (string) $admin->password)) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            RateLimiter::hit($adminKey, self::ADMIN_DECAY_SECONDS);

            throw ValidationException::withMessages([$field => $incorrectMessage]);
        }

        RateLimiter::clear($key);
        RateLimiter::clear($adminKey);
    }

    private static function key(mixed $adminId): string
    {
        return 'reauth:'.($adminId ?? 'guest').'|'.request()->ip();
    }
}
