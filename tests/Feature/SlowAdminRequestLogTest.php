<?php

namespace Tests\Feature;

use App\Http\Middleware\LogSlowAdminRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SlowAdminRequestLogTest extends TestCase
{
    private function hit(string $path, float $threshold, int $sleepMs = 0): void
    {
        config(['app.slow_admin_log_seconds' => $threshold]);

        (new LogSlowAdminRequests)->handle(Request::create($path), function () use ($sleepMs) {
            usleep($sleepMs * 1000);

            return new Response('ok');
        });
    }

    public function test_a_slow_admin_request_is_logged_without_values(): void
    {
        Log::shouldReceive('warning')->once()->withArgs(fn ($m, $ctx) => $m === 'Slow admin request'
            && $ctx['path'] === '/admin/settings' && $ctx['seconds'] >= 0.01 && isset($ctx['queries']));

        $this->hit('/admin/settings', 0.01, 30);
    }

    public function test_a_fast_request_and_a_storefront_request_are_not_logged(): void
    {
        Log::shouldReceive('warning')->never();

        $this->hit('/admin/settings', 5);
        $this->hit('/en/parts/ABC', 0.01, 30);
    }

    public function test_zero_switches_it_off(): void
    {
        Log::shouldReceive('warning')->never();

        $this->hit('/admin/settings', 0, 30);
    }
}
