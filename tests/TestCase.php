<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Named rate limiters (RateLimiter::for('register'), 'refund-request',
        // 'login', etc. — app/Providers/AppServiceProvider.php) are keyed by
        // IP and backed by the cache store. CACHE_STORE=array in testing is
        // per-PROCESS memory, not per-test — with ~900 tests running
        // sequentially in one PHP process and no isolation, hit counts from
        // EARLIER tests (often the same 127.0.0.1 test-client IP) accumulate
        // across the whole suite and eventually throttle unrelated LATER
        // tests with an unexpected 429, non-deterministically depending on
        // run order. Explicitly targets the 'array' store (not Cache::flush(),
        // which flushes whatever store config('cache.default') CURRENTLY
        // resolves to) — defense in depth in case an earlier test temporarily
        // swaps the default store (e.g. to test Redis-specific behavior) and
        // a bug in its own teardown leaves that swap in place; this must
        // never be able to reach the real Redis/production cache.
        Cache::store('array')->flush();

        // The Cleanup Engine keys its per-run lock files and candidate lists by run id, and every test process starts
        // its own empty database at run id 1. Under `--parallel` several processes would otherwise fight over the very
        // same storage/framework/cleanup/run-1.lock. A directory per process keeps them apart (and keeps tests out of
        // the project's real storage/).
        config(['cleanup.work_dir' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-cleanup-work-'.getmypid()]);
    }
}
