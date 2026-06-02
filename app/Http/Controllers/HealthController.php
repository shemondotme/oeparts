<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache'    => $this->checkCache(),
        ];

        $healthy = ! in_array('fail', $checks, true);

        return response()->json([
            'status'    => $healthy ? 'ok' : 'degraded',
            'version'   => $this->version(),
            'timestamp' => now()->toIso8601String(),
            'checks'    => $checks,
        ], $healthy ? 200 : 503);
    }

    private function checkDatabase(): string
    {
        try {
            DB::connection()->getPdo();
            return 'ok';
        } catch (\Throwable) {
            return 'fail';
        }
    }

    private function checkCache(): string
    {
        try {
            $key = 'health_ping_' . uniqid();
            Cache::put($key, 'ok', 5);
            $result = Cache::get($key);
            Cache::forget($key);
            return $result === 'ok' ? 'ok' : 'fail';
        } catch (\Throwable) {
            return 'fail';
        }
    }

    private function version(): string
    {
        $path = base_path('version.json');
        if (! file_exists($path)) {
            return 'unknown';
        }
        $data = json_decode(file_get_contents($path), true);
        return $data['version'] ?? 'unknown';
    }
}
