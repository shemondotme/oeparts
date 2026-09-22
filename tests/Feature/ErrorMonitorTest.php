<?php

namespace Tests\Feature;

use App\Filament\Pages\System\ErrorMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 14 (Monitoring/Logging Audit). This IS the admin's own error-
 * monitoring dashboard — both getExceptionLog() and getFailedJobStats() had
 * completely silent catch blocks (no log call), so a broken log file or a
 * broken failed_jobs query would render as "0 errors" / "no failed jobs",
 * false reassurance from the exact tool meant to surface real failures.
 * getExceptionLog() also switched from catch(\Exception) to catch(\Throwable)
 * — a raw fopen() failure passed into fseek() raises a TypeError (\Error),
 * not an \Exception, which the original catch type wouldn't have caught at
 * all. The log path now reads config('logging.channels.single.path') (with
 * the same real-world default) specifically so this is testable without
 * ever touching the real dev-environment laravel.log, shared across every
 * other test running in this same container.
 */
class ErrorMonitorTest extends TestCase
{
    use RefreshDatabase;

    private string $logPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logPath = storage_path('logs/error-monitor-test-'.getmypid().'.log');
        config(['logging.channels.single.path' => $this->logPath]);
    }

    protected function tearDown(): void
    {
        @unlink($this->logPath);
        parent::tearDown();
    }

    #[Test]
    public function it_parses_real_exception_entries_from_the_log_file(): void
    {
        // The ORIGINAL regex here required an ISO8601-with-microseconds
        // timestamp and separate "message"/"file"/"line" JSON keys that
        // Laravel's actual default log format never produces — confirmed
        // live via `report()`-ing a real exception in this exact app and
        // reading the resulting laravel.log line (no custom Monolog
        // formatter is configured anywhere). This fixture matches that
        // real, confirmed format exactly.
        $line = '['.now()->format('Y-m-d H:i:s').'] local.ERROR: Something broke'
            .' {"exception":"[object] (App\\\\Services\\\\Foo(code: 0): Something broke at /var/www/html/app/Services/Foo.php:42)'."\n"
            .'[stacktrace]'."\n"
            .'#0 {main}"}'."\n";
        file_put_contents($this->logPath, $line);

        $errors = (new ErrorMonitor)->getExceptionLog();

        $this->assertNotEmpty($errors);
        // Pins the regex rebuild directly: before it, this dashboard never
        // matched a single real log line at all (wrong timestamp format),
        // and even when it hit test fixtures shaped like the old assumed
        // format, an off-by-one group count meant 'type' held the message
        // text, 'message' held the file path, 'file' was always '', and
        // 'line' was always 0.
        $this->assertSame('ERROR', $errors[0]['type']);
        $this->assertSame('Something broke', $errors[0]['message']);
        // base_path() is stripped for cleaner display — this test runs
        // under the same /var/www/html root the fixture line was written
        // against.
        $this->assertSame('app/Services/Foo.php', $errors[0]['file']);
        $this->assertSame('42', (string) $errors[0]['line']);
    }

    #[Test]
    public function it_returns_an_empty_list_when_no_log_file_exists_yet(): void
    {
        $this->assertSame([], (new ErrorMonitor)->getExceptionLog());
    }

    #[Test]
    public function it_logs_and_returns_empty_when_the_log_file_cannot_be_opened(): void
    {
        Log::spy();

        // A directory at the configured path: file_exists() is true, but
        // fopen(..., 'r') on a directory fails — exactly the "can't open"
        // failure mode the fix's explicit fopen() check targets.
        mkdir($this->logPath, 0775, true);

        $errors = (new ErrorMonitor)->getExceptionLog();

        $this->assertSame([], $errors);
        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(fn ($message) => str_contains($message, 'getExceptionLog'));

        rmdir($this->logPath);
    }

    #[Test]
    public function it_logs_when_the_failed_job_stats_query_breaks(): void
    {
        Log::spy();

        Schema::drop('failed_jobs');

        $stats = (new ErrorMonitor)->getFailedJobStats();

        $this->assertSame(['total' => 0, 'by_queue' => []], $stats);
        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(fn ($message) => str_contains($message, 'getFailedJobStats'));
    }
}
