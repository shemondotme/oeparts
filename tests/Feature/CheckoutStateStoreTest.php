<?php

namespace Tests\Feature;

use App\Services\Checkout\CheckoutStateStore;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Checkout progress lives in the cache, keyed by the checkout id — not in
 * the session (see CheckoutStateStore's docblock for the two defects that
 * motivated it: last-writer-wins clobbering by concurrent requests, and the
 * mobile API's checkout state being discarded between requests).
 */
class CheckoutStateStoreTest extends TestCase
{
    private const ID = '11111111-2222-3333-4444-555555555555';

    private function state(array $overrides = []): array
    {
        return array_merge([
            'cart_id' => 7,
            'step' => 1,
            'data' => ['contact_email' => null],
            'created_at' => now()->toIso8601String(),
            'expires_at' => now()->addMinutes(30)->toIso8601String(),
        ], $overrides);
    }

    private function store(int $lockWait = 3): CheckoutStateStore
    {
        return new CheckoutStateStore($lockWait);
    }

    protected function tearDown(): void
    {
        Cache::forget('checkout_state:'.self::ID);
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function it_round_trips_a_state_and_returns_null_for_an_unknown_id(): void
    {
        $store = $this->store();

        $this->assertNull($store->get(self::ID));

        $store->put(self::ID, $this->state(['step' => 3]));

        $this->assertSame(3, $store->get(self::ID)['step']);
        $this->assertNull($store->get('99999999-9999-9999-9999-999999999999'));
    }

    #[Test]
    public function forget_removes_the_state(): void
    {
        $store = $this->store();
        $store->put(self::ID, $this->state());

        $store->forget(self::ID);

        $this->assertNull($store->get(self::ID));
    }

    #[Test]
    public function an_expired_state_is_treated_as_missing_and_removed(): void
    {
        $store = $this->store();
        $store->put(self::ID, $this->state(['expires_at' => now()->addMinutes(30)->toIso8601String()]));

        Carbon::setTestNow(now()->addMinutes(31));

        $this->assertNull($store->get(self::ID), 'past expires_at the checkout no longer exists');
        $this->assertFalse(Cache::has('checkout_state:'.self::ID), 'and the stale entry is cleaned up rather than left behind');
    }

    #[Test]
    public function the_entry_is_kept_a_little_past_expiry_but_not_forever(): void
    {
        $store = $this->store();
        $store->put(self::ID, $this->state(['expires_at' => now()->addMinutes(30)->toIso8601String()]));

        // Still physically present shortly after expiry (grace window) ...
        Carbon::setTestNow(now()->addMinutes(35));
        $this->assertTrue(Cache::has('checkout_state:'.self::ID));

        // ... but evicted by its TTL well after, with no cleanup job needed.
        Carbon::setTestNow(now()->addMinutes(20));
        $this->assertFalse(Cache::has('checkout_state:'.self::ID));
    }

    #[Test]
    public function mutate_persists_changes_when_the_callback_returns_true(): void
    {
        $store = $this->store();
        $store->put(self::ID, $this->state());

        $persisted = $store->mutate(self::ID, function (array &$state): bool {
            $state['step'] = 2;
            $state['data']['contact_email'] = 'a@example.com';

            return true;
        });

        $this->assertTrue($persisted);
        $this->assertSame(2, $store->get(self::ID)['step']);
        $this->assertSame('a@example.com', $store->get(self::ID)['data']['contact_email']);
    }

    #[Test]
    public function mutate_leaves_the_stored_state_alone_when_the_callback_declines(): void
    {
        $store = $this->store();
        $store->put(self::ID, $this->state(['step' => 1]));

        $persisted = $store->mutate(self::ID, function (array &$state): bool {
            $state['step'] = 99; // modified locally, but the callback declines to persist it

            return false;
        });

        $this->assertFalse($persisted);
        $this->assertSame(1, $store->get(self::ID)['step']);
    }

    #[Test]
    public function mutate_on_a_missing_or_expired_checkout_does_nothing_and_never_calls_back(): void
    {
        $store = $this->store();
        $called = false;

        $this->assertFalse($store->mutate(self::ID, function (array &$state) use (&$called): bool {
            $called = true;

            return true;
        }));
        $this->assertFalse($called);
        $this->assertNull($store->get(self::ID), 'mutate must not conjure a state into existence');
    }

    #[Test]
    public function sequential_mutations_each_see_the_previous_ones_result(): void
    {
        $store = $this->store();
        $store->put(self::ID, $this->state());

        foreach (['contact_email' => 'a@example.com', 'shipping_method_id' => 4, 'terms_accepted' => true] as $key => $value) {
            $store->mutate(self::ID, function (array &$state) use ($key, $value): bool {
                $state['data'][$key] = $value;

                return true;
            });
        }

        $data = $store->get(self::ID)['data'];
        $this->assertSame('a@example.com', $data['contact_email']);
        $this->assertSame(4, $data['shipping_method_id']);
        $this->assertTrue($data['terms_accepted'], 'no update lost another');
    }

    #[Test]
    public function the_lock_is_released_after_a_mutation_and_after_a_throwing_callback(): void
    {
        $store = $this->store();
        $store->put(self::ID, $this->state());

        try {
            $store->mutate(self::ID, function (array &$state): bool {
                throw new \RuntimeException('callback blew up');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $lock = Cache::lock('checkout_state_lock:'.self::ID, 5);
        $this->assertTrue($lock->get(), 'a failed mutation must not leave the checkout locked');
        $lock->release();
    }

    #[Test]
    public function a_lock_that_cannot_be_acquired_degrades_to_unlocked_rather_than_bricking_checkout(): void
    {
        Log::spy();
        $store = $this->store(lockWait: 1);
        $store->put(self::ID, $this->state());

        // A stuck holder.
        $held = Cache::lock('checkout_state_lock:'.self::ID, 30);
        $this->assertTrue($held->get());

        try {
            $persisted = $store->mutate(self::ID, function (array &$state): bool {
                $state['step'] = 2;

                return true;
            });
        } finally {
            $held->release();
        }

        $this->assertTrue($persisted, 'availability over strictness — the customer is not stuck');
        $this->assertSame(2, $store->get(self::ID)['step']);
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'lock unavailable'))->once();
    }
}
