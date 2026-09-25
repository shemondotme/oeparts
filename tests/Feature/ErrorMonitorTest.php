<?php

namespace Tests\Feature;

use App\Filament\Pages\System\ErrorMonitor;
use App\Models\Admin;
use Database\Seeders\RolesSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
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
        // Pin the resolver to the 'single' channel these tests target —
        // the app default is 'daily' (Phase 18), which resolveActiveLogPath()
        // covers separately below.
        config([
            'logging.channels.stack.channels' => ['single'],
            'logging.channels.single.path' => $this->logPath,
        ]);
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
            .' {"exception":"[object] (App\\\\Services\\\\Foo(code: 0): Something broke at '.base_path('app/Services/Foo.php').':42)'."\n"
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
        // base_path() is stripped for cleaner display. The fixture is built from
        // base_path() itself — a hardcoded /var/www/html only matched the Docker dev
        // container and failed on any other checkout (GitHub Actions runners).
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

    /**
     * Phase 18 (Infrastructure/Ops Resilience). The app's default log
     * channel is 'daily' (rotates + auto-prunes; the old 'single' default
     * never did either, growing unbounded on a real deployment) —
     * getExceptionLog() must resolve the DATE-SUFFIXED file 'daily'
     * actually writes (Monolog's RotatingFileHandler), not the bare
     * configured path, or this dashboard would silently show zero
     * exceptions forever the moment 'daily' became the default.
     */
    #[Test]
    public function it_reads_todays_dated_file_when_the_daily_channel_is_active(): void
    {
        $dailyBasePath = storage_path('logs/error-monitor-daily-test-'.getmypid().'.log');
        $todaysFile = storage_path('logs/error-monitor-daily-test-'.getmypid().'-'.now()->format('Y-m-d').'.log');

        config([
            'logging.channels.stack.channels' => ['daily'],
            'logging.channels.daily.path' => $dailyBasePath,
        ]);

        $line = '['.now()->format('Y-m-d H:i:s').'] local.ERROR: Daily channel broke'
            .' {"exception":"[object] (App\\\\Services\\\\Bar(code: 0): Daily channel broke at /var/www/html/app/Services/Bar.php:7)'."\n"
            .'[stacktrace]'."\n"
            .'#0 {main}"}'."\n";
        file_put_contents($todaysFile, $line);

        $errors = (new ErrorMonitor)->getExceptionLog();

        @unlink($todaysFile);

        $this->assertNotEmpty($errors);
        $this->assertSame('Daily channel broke', $errors[0]['message']);
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

    /**
     * Phase 18 (Infrastructure/Ops Resilience). getLogFileInfo() replaces
     * the "Log File" dashboard tile's old hardcoded "laravel.log" label +
     * a direct filesize(storage_path('logs/laravel.log')) call — broken the
     * moment 'daily' became the default channel, since the real file is
     * date-suffixed and that bare path stopped existing. Neither this nor
     * the page-render test below existed before this phase: the whole
     * ErrorMonitor Livewire page was never actually rendered by any test,
     * only its individual methods called directly.
     */
    #[Test]
    public function get_log_file_info_reports_the_real_dated_filename_and_size(): void
    {
        config(['logging.channels.stack.channels' => ['daily']]);
        config(['logging.channels.daily.path' => $this->logPath]);
        $todaysFile = preg_replace('/\.log$/', '-'.now()->format('Y-m-d').'.log', $this->logPath);
        file_put_contents($todaysFile, str_repeat('x', 2048));

        $info = (new ErrorMonitor)->getLogFileInfo();

        @unlink($todaysFile);

        $this->assertSame(basename($todaysFile), $info['name']);
        $this->assertEqualsWithDelta(2.0, $info['size_kb'], 0.1);
    }

    #[Test]
    public function get_log_file_info_does_not_error_when_no_log_file_exists_yet(): void
    {
        $info = (new ErrorMonitor)->getLogFileInfo();

        $this->assertSame(0.0, $info['size_kb']);
    }

    /**
     * The whole point of a page-render test: getExceptionLog()'s own regex
     * rebuild (Phase 14) and getLogFileInfo()'s path fix (Phase 18) were
     * both found by reading the code, not by a failing test — nothing had
     * ever actually mounted this Livewire page and rendered its Blade view,
     * the same class of gap Phase 15/17 found repeatedly elsewhere
     * (Mail::fake() intercepting before render, error views never rendered).
     */
    #[Test]
    public function the_page_renders_successfully_for_an_authorized_admin(): void
    {
        $this->seed(RolesSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');

        Livewire::test(ErrorMonitor::class)->assertOk();
    }
}
