<?php

namespace App\Services\Checkout;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Where a checkout's progress (current step, contact details, address,
 * chosen shipping method, coupon, terms) lives — keyed by the checkout's own
 * uuid, in the cache.
 *
 * It used to live inside the Laravel session. That had two real defects:
 *
 *  1. The session is ONE blob that StartSession re-saves in full at the end
 *     of every request, so any concurrent request holding a stale copy
 *     overwrote checkout progress last-writer-wins. A slow background
 *     GET /cart/summary reverted customers to the previous step with an
 *     empty form (found in Phase 22; ReadOnlySession patched the known
 *     background endpoints, but any future unflagged writer could have
 *     reintroduced it). Checkout state is now isolated from the session
 *     entirely — nothing that touches the session can clobber it.
 *
 *  2. The mobile API is documented as stateless — "the mobile app stores the
 *     checkout_id locally and submits each step" — and its route group has
 *     no session middleware, so state written with Session::put() was
 *     discarded when the request ended. POST /api/checkout/start returned an
 *     id and the next request for it answered 404. Verified against the real
 *     stack; the API tests only passed because the array session driver
 *     persists in-process across requests. Keying the state by checkout id in
 *     the cache is what makes the documented design actually work.
 *
 * The web flow still remembers WHICH checkout is active per browser
 * (`active_checkout_id` in the session) — just a pointer, never the state.
 *
 * Read-modify-write goes through mutate(), which serializes concurrent
 * requests for the same checkout with a lock, so a double-clicked "Continue"
 * or two overlapping API calls can no longer lose each other's update.
 */
class CheckoutStateStore
{
    /** Kept past `expires_at` so a late request still finds (and clears) the entry rather than racing the eviction. */
    private const GRACE_SECONDS = 600;

    private const LOCK_HOLD_SECONDS = 10;

    public function __construct(private readonly int $lockWaitSeconds = 3) {}

    /** The live state, or null if it never existed, was cleared, or has expired (an expired entry is removed). */
    public function get(string $checkoutId): ?array
    {
        $state = Cache::get($this->key($checkoutId));

        if (! is_array($state)) {
            return null;
        }

        if ($this->isExpired($state)) {
            $this->forget($checkoutId);

            return null;
        }

        return $state;
    }

    public function put(string $checkoutId, array $state): void
    {
        Cache::put($this->key($checkoutId), $state, $this->ttlSeconds($state));
    }

    public function forget(string $checkoutId): void
    {
        Cache::forget($this->key($checkoutId));
    }

    /**
     * Atomic read-modify-write on a live checkout.
     *
     * The callback receives the current state by reference and returns true to
     * persist its changes or false to leave the stored state untouched.
     * Returns whether anything was persisted — false when the checkout
     * doesn't exist / has expired, or the callback declined.
     *
     * @param  Closure(array &$state): bool  $callback
     */
    public function mutate(string $checkoutId, Closure $callback): bool
    {
        return (bool) $this->withLock($checkoutId, function () use ($checkoutId, $callback): bool {
            $state = $this->get($checkoutId);

            if ($state === null || $callback($state) !== true) {
                return false;
            }

            $this->put($checkoutId, $state);

            return true;
        });
    }

    private function withLock(string $checkoutId, Closure $work): mixed
    {
        try {
            return Cache::lock('checkout_state_lock:'.$checkoutId, self::LOCK_HOLD_SECONDS)
                ->block($this->lockWaitSeconds, $work);
        } catch (LockTimeoutException|\BadMethodCallException $e) {
            // Availability over strictness: a lock that can't be had (a stuck
            // holder, or a cache store with no lock support) must not brick
            // checkout — degrade to the unlocked read-modify-write that
            // existed before, and say so.
            Log::warning('Checkout state lock unavailable — proceeding without it', [
                'checkout_id' => $checkoutId,
                'reason' => $e::class,
            ]);

            return $work();
        }
    }

    private function key(string $checkoutId): string
    {
        return 'checkout_state:'.$checkoutId;
    }

    private function isExpired(array $state): bool
    {
        $expiresAt = $state['expires_at'] ?? null;

        return $expiresAt !== null && Carbon::parse($expiresAt)->isPast();
    }

    private function ttlSeconds(array $state): int
    {
        $remaining = isset($state['expires_at'])
            ? max(0, (int) now()->diffInSeconds(Carbon::parse($state['expires_at']), false))
            : 0;

        return max(60, $remaining + self::GRACE_SECONDS);
    }
}
