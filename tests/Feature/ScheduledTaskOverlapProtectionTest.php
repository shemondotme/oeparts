<?php

namespace Tests\Feature;

use App\Support\ScheduleCommandName;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 16 (Scheduled Task/Cron Reliability). Verifies withoutOverlapping()
 * against the REAL registered schedule in routes/console.php, not a
 * freshly-constructed Schedule::command() call — the point is confirming
 * what's actually wired up, not that the method itself works (that's
 * Laravel's own concern). Event::$withoutOverlapping is protected; Laravel
 * exposes no public getter, so this reads it via reflection the same way
 * RequestMetricsWidgetTest reaches a protected method elsewhere this phase.
 */
class ScheduledTaskOverlapProtectionTest extends TestCase
{
    private function withoutOverlappingFlag(Event $event): bool
    {
        $property = new \ReflectionProperty($event, 'withoutOverlapping');

        return (bool) $property->getValue($event);
    }

    private function findEvent(string $commandName): Event
    {
        foreach (app(Schedule::class)->events() as $event) {
            if (ScheduleCommandName::for($event) === $commandName) {
                return $event;
            }
        }

        $this->fail("No scheduled event found for '{$commandName}'.");
    }

    /**
     * sitemap:generate and the SEO Control Center's "Regenerate Sitemap"
     * admin button both write the exact same fixed set of files with no
     * coordination between them — SitemapService::generateAll()'s own
     * Cache::lock() (SitemapGenerationLockTest) closes that cross-trigger
     * race; this withoutOverlapping() is cheap additional insurance
     * against the scheduled entry racing itself.
     */
    #[Test]
    public function sitemap_generate_prevents_overlapping_runs(): void
    {
        $this->assertTrue($this->withoutOverlappingFlag($this->findEvent('sitemap:generate')));
    }

    /**
     * The command's own query re-selects every Shipped order with no
     * per-order claim — two concurrent runs racing the same SELECT before
     * either had updated a row could both transition (and both notify the
     * customer about) the same order.
     */
    #[Test]
    public function orders_auto_complete_prevents_overlapping_runs(): void
    {
        $this->assertTrue($this->withoutOverlappingFlag($this->findEvent('oeparts:orders:auto-complete')));
    }

    /**
     * Already-correct baseline, pinned so a future edit can't silently
     * drop these: each already has its own withoutOverlapping() for a
     * documented reason (a slow abandoned-cart backlog, avoiding a
     * pointless duplicate newsletter-dispatch pass, OPTIMIZE TABLE
     * running long on a large catalog).
     */
    #[Test]
    public function the_already_protected_tasks_still_have_it(): void
    {
        foreach ([
            'abandoned-cart:process',
            'oeparts:newsletter:send-due',
            'oeparts:products:optimize-search-index',
        ] as $command) {
            $this->assertTrue($this->withoutOverlappingFlag($this->findEvent($command)), "{$command} lost its withoutOverlapping()");
        }
    }
}
