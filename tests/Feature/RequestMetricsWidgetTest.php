<?php

namespace Tests\Feature;

use App\Filament\Widgets\RequestMetricsWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 14 (Monitoring/Logging Audit). All 4 metric queries here were
 * independently try/caught with a completely empty catch — a broken DB
 * connection or a dropped table rendered as a falsely-reassuring "0" on
 * this exact activity-monitoring widget, with nothing anywhere to say the
 * metric itself was broken vs. genuinely zero. getStats() is protected
 * (a Filament widget internal), invoked here via reflection rather than
 * rendering the full widget, since only the catch-path logging matters.
 */
class RequestMetricsWidgetTest extends TestCase
{
    use RefreshDatabase;

    private function getStats(RequestMetricsWidget $widget): array
    {
        $method = new \ReflectionMethod($widget, 'getStats');

        return $method->invoke($widget);
    }

    #[Test]
    public function a_broken_email_logs_query_logs_a_warning_instead_of_silently_showing_zero(): void
    {
        Log::spy();

        Schema::drop('email_logs');

        $this->getStats(new RequestMetricsWidget);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn ($message) => str_contains($message, 'RequestMetricsWidget') && str_contains($message, 'emails-sent'));
    }

    #[Test]
    public function a_broken_search_logs_query_logs_a_warning(): void
    {
        Log::spy();

        Schema::drop('search_logs');

        $this->getStats(new RequestMetricsWidget);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn ($message) => str_contains($message, 'RequestMetricsWidget') && str_contains($message, 'searches'));
    }

    #[Test]
    public function it_does_not_log_when_every_query_succeeds(): void
    {
        Log::spy();

        $this->getStats(new RequestMetricsWidget);

        Log::shouldNotHaveReceived('warning');
    }
}
