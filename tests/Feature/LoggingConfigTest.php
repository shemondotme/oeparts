<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 18 (Infrastructure/Ops Resilience). The 'single' log driver never
 * rotates or prunes storage/logs/laravel.log — on a real deployment (this
 * app explicitly supports shared hosting with no OS-level logrotate) that
 * file grows unbounded until the disk fills. Pins the real, booted default
 * (config/logging.php's own env('LOG_STACK', ...) fallback, which
 * .env.testing does not override) so a future edit can't silently revert
 * to 'single' without this failing — deliberately does not touch this
 * config itself, unlike ErrorMonitorTest, which overrides it to test its
 * own path-resolution logic in isolation.
 */
class LoggingConfigTest extends TestCase
{
    #[Test]
    public function the_default_log_stack_uses_the_rotating_daily_driver_not_single(): void
    {
        $this->assertSame(['daily'], config('logging.channels.stack.channels'));
    }

    #[Test]
    public function the_daily_channel_has_a_bounded_retention_window(): void
    {
        $this->assertGreaterThan(0, config('logging.channels.daily.days'));
    }
}
