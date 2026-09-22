<?php

namespace Tests\Feature;

use App\Filament\Widgets\System\QueueStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 14 (Monitoring/Logging Audit). All 4 queue-health queries here were
 * independently try/caught with a completely empty catch — a broken DB/
 * queue connection rendered as "0 pending / 0 failed" on the widget whose
 * entire purpose is showing queue health, with no trace that a query
 * failed vs. the queue genuinely being empty. readStats() is private,
 * invoked here via reflection rather than rendering the full widget.
 */
class QueueStatsWidgetTest extends TestCase
{
    use RefreshDatabase;

    private function readStats(QueueStats $widget): array
    {
        $method = new \ReflectionMethod($widget, 'readStats');

        return $method->invoke($widget);
    }

    #[Test]
    public function a_broken_failed_jobs_query_logs_a_warning_instead_of_silently_showing_zero(): void
    {
        Log::spy();

        Schema::drop('failed_jobs');

        $stats = $this->readStats(new QueueStats);

        $this->assertSame(0, $stats['failed_24h']);
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn ($message) => str_contains($message, 'QueueStats') && str_contains($message, 'failed-24h'));
    }

    #[Test]
    public function a_broken_job_batches_query_logs_a_warning(): void
    {
        Log::spy();

        Schema::drop('job_batches');

        $this->readStats(new QueueStats);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn ($message) => str_contains($message, 'QueueStats') && str_contains($message, 'completed-hour'));
    }

    #[Test]
    public function it_does_not_log_when_every_query_succeeds(): void
    {
        Log::spy();

        $this->readStats(new QueueStats);

        Log::shouldNotHaveReceived('warning');
    }
}
