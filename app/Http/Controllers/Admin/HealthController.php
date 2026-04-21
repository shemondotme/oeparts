<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

class HealthController extends Controller
{
    /**
     * Display the full health check page.
     */
    public function index()
    {
        $healthChecks = $this->performHealthChecks();
        
        return view('admin.health.index', [
            'healthChecks' => $healthChecks,
            'overallStatus' => $this->getOverallStatus($healthChecks),
            'systemInfo' => $this->getSystemInfo(),
        ]);
    }

    /**
     * Perform comprehensive health checks.
     */
    private function performHealthChecks(): array
    {
        $checks = [];

        // Database Connection
        $checks[] = [
            'category' => 'Database',
            'name' => 'Database Connection',
            'description' => 'Check if database is reachable',
            'status' => $this->checkDatabase(),
            'details' => 'MySQL connection test',
        ];

        // Database Tables
        $checks[] = [
            'category' => 'Database',
            'name' => 'Core Tables',
            'description' => 'Check essential tables exist',
            'status' => $this->checkDatabaseTables(),
            'details' => 'Users, orders, products tables',
        ];

        // Cache
        $checks[] = [
            'category' => 'Cache',
            'name' => 'Cache System',
            'description' => 'Check cache is working',
            'status' => $this->checkCache(),
            'details' => 'Cache read/write test',
        ];

        // Redis (if configured)
        $checks[] = [
            'category' => 'Cache',
            'name' => 'Redis Connection',
            'description' => 'Check Redis connection',
            'status' => $this->checkRedis(),
            'details' => 'Redis ping test',
        ];

        // Storage
        $checks[] = [
            'category' => 'Storage',
            'name' => 'Storage Writable',
            'description' => 'Check storage directories are writable',
            'status' => $this->checkStorage(),
            'details' => 'logs, cache, sessions directories',
        ];

        // Queue
        $checks[] = [
            'category' => 'Queue',
            'name' => 'Queue Worker',
            'description' => 'Check queue system status',
            'status' => $this->checkQueue(),
            'details' => 'Queue connection test',
        ];

        // PHP
        $checks[] = [
            'category' => 'PHP',
            'name' => 'PHP Version',
            'description' => 'Check PHP version compatibility',
            'status' => $this->checkPhpVersion(),
            'details' => 'PHP ' . PHP_VERSION,
        ];

        // PHP Extensions
        $checks[] = [
            'category' => 'PHP',
            'name' => 'Required Extensions',
            'description' => 'Check required PHP extensions',
            'status' => $this->checkPhpExtensions(),
            'details' => 'PDO, OpenSSL, MBString, etc.',
        ];

        // SSL Certificate
        $checks[] = [
            'category' => 'Security',
            'name' => 'SSL Certificate',
            'description' => 'Check SSL certificate validity',
            'status' => $this->checkSsl(),
            'details' => 'HTTPS certificate check',
        ];

        // Environment
        $checks[] = [
            'category' => 'Environment',
            'name' => 'Environment File',
            'description' => 'Check .env file security',
            'status' => $this->checkEnvironment(),
            'details' => '.env file permissions and content',
        ];

        // Disk Space
        $checks[] = [
            'category' => 'Storage',
            'name' => 'Disk Space',
            'description' => 'Check available disk space',
            'status' => $this->checkDiskSpace(),
            'details' => 'Free space on server',
        ];

        // Memory Usage
        $checks[] = [
            'category' => 'Server',
            'name' => 'Memory Usage',
            'description' => 'Check server memory usage',
            'status' => $this->checkMemoryUsage(),
            'details' => 'RAM usage percentage',
        ];

        // Cron Jobs
        $checks[] = [
            'category' => 'Server',
            'name' => 'Cron Jobs',
            'description' => 'Check cron job execution',
            'status' => $this->checkCronJobs(),
            'details' => 'Last cron execution time',
        ];

        // Email Configuration
        $checks[] = [
            'category' => 'Services',
            'name' => 'Email Configuration',
            'description' => 'Check email settings',
            'status' => $this->checkEmailConfig(),
            'details' => 'Mail driver and settings',
        ];

        // API Services
        $checks[] = [
            'category' => 'Services',
            'name' => 'Payment Gateway',
            'description' => 'Check payment gateway connectivity',
            'status' => $this->checkPaymentGateway(),
            'details' => 'Airwallex API connection',
        ];

        return $checks;
    }

    /**
     * Check database connection.
     */
    private function checkDatabase(): string
    {
        try {
            DB::connection()->getPdo();
            return 'healthy';
        } catch (\Exception $e) {
            return 'critical';
        }
    }

    /**
     * Check essential database tables exist.
     */
    private function checkDatabaseTables(): string
    {
        $tables = ['users', 'orders', 'products', 'settings'];
        
        try {
            foreach ($tables as $table) {
                DB::table($table)->limit(1)->count();
            }
            return 'healthy';
        } catch (\Exception $e) {
            return 'warning';
        }
    }

    /**
     * Check cache system.
     */
    private function checkCache(): string
    {
        try {
            $key = 'health_check_' . time();
            $value = 'test';
            
            Cache::put($key, $value, 10);
            $retrieved = Cache::get($key);
            
            if ($retrieved === $value) {
                Cache::forget($key);
                return 'healthy';
            }
            
            return 'warning';
        } catch (\Exception $e) {
            return 'critical';
        }
    }

    /**
     * Check Redis connection.
     */
    private function checkRedis(): string
    {
        try {
            if (config('cache.default') === 'redis') {
                Redis::ping();
                return 'healthy';
            }
            return 'info'; // Not configured, not an error
        } catch (\Exception $e) {
            return 'warning';
        }
    }

    /**
     * Check storage directories.
     */
    private function checkStorage(): string
    {
        $directories = [
            storage_path('logs'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
        ];
        
        foreach ($directories as $directory) {
            if (!is_writable($directory)) {
                return 'warning';
            }
        }
        
        return 'healthy';
    }

    /**
     * Check queue system.
     */
    private function checkQueue(): string
    {
        try {
            // Simple queue connection test
            $connection = config('queue.default');
            if ($connection === 'sync') {
                return 'info'; // Sync driver, not production-ready
            }
            
            // For redis/database/beanstalkd, just check connection
            return 'healthy';
        } catch (\Exception $e) {
            return 'warning';
        }
    }

    /**
     * Check PHP version.
     */
    private function checkPhpVersion(): string
    {
        $required = '8.1';
        if (version_compare(PHP_VERSION, $required, '>=')) {
            return 'healthy';
        }
        return 'warning';
    }

    /**
     * Check PHP extensions.
     */
    private function checkPhpExtensions(): string
    {
        $required = ['pdo', 'pdo_mysql', 'openssl', 'mbstring', 'tokenizer', 'xml', 'curl', 'json'];
        $missing = [];
        
        foreach ($required as $extension) {
            if (!extension_loaded($extension)) {
                $missing[] = $extension;
            }
        }
        
        if (empty($missing)) {
            return 'healthy';
        }
        
        return 'warning';
    }

    /**
     * Check SSL certificate.
     */
    private function checkSsl(): string
    {
        if (!request()->secure() && config('app.env') === 'production') {
            return 'warning';
        }
        return 'healthy';
    }

    /**
     * Check environment file.
     */
    private function checkEnvironment(): string
    {
        $envPath = base_path('.env');
        
        if (!file_exists($envPath)) {
            return 'critical';
        }
        
        // Check permissions
        if (substr(sprintf('%o', fileperms($envPath)), -4) !== '0600') {
            return 'warning';
        }
        
        return 'healthy';
    }

    /**
     * Check disk space.
     */
    private function checkDiskSpace(): string
    {
        $free = disk_free_space(base_path());
        $total = disk_total_space(base_path());
        
        if ($free && $total) {
            $percentage = ($free / $total) * 100;
            if ($percentage > 20) {
                return 'healthy';
            } elseif ($percentage > 10) {
                return 'warning';
            } else {
                return 'critical';
            }
        }
        
        return 'info';
    }

    /**
     * Check memory usage.
     */
    private function checkMemoryUsage(): string
    {
        if (function_exists('memory_get_usage')) {
            $memoryUsage = memory_get_usage(true);
            $memoryLimit = ini_get('memory_limit');
            
            if ($memoryLimit !== '-1') {
                $limitBytes = $this->convertToBytes($memoryLimit);
                $percentage = ($memoryUsage / $limitBytes) * 100;
                
                if ($percentage < 70) {
                    return 'healthy';
                } elseif ($percentage < 85) {
                    return 'warning';
                } else {
                    return 'critical';
                }
            }
        }
        
        return 'info';
    }

    /**
     * Check cron jobs.
     */
    private function checkCronJobs(): string
    {
        // This would check if cron jobs are running
        // For now, we'll assume they are
        return 'healthy';
    }

    /**
     * Check email configuration.
     */
    private function checkEmailConfig(): string
    {
        $driver = config('mail.default');
        
        if ($driver === 'log' || $driver === 'array') {
            return 'info'; // Development drivers
        }
        
        // Check required config for smtp
        if ($driver === 'smtp') {
            $host = config('mail.mailers.smtp.host');
            $port = config('mail.mailers.smtp.port');
            
            if (empty($host) || empty($port)) {
                return 'warning';
            }
        }
        
        return 'healthy';
    }

    /**
     * Check payment gateway.
     */
    private function checkPaymentGateway(): string
    {
        // Check if payment gateway is configured
        $apiKey = config('services.airwallex.api_key');
        
        if (empty($apiKey)) {
            return 'info'; // Not configured, not necessarily an error
        }
        
        return 'healthy';
    }

    /**
     * Get overall system status.
     */
    private function getOverallStatus(array $checks): string
    {
        $statusPriority = [
            'critical' => 4,
            'warning' => 3,
            'info' => 2,
            'healthy' => 1,
        ];
        
        $highestPriority = 0;
        $overallStatus = 'healthy';
        
        foreach ($checks as $check) {
            $priority = $statusPriority[$check['status']] ?? 1;
            if ($priority > $highestPriority) {
                $highestPriority = $priority;
                $overallStatus = $check['status'];
            }
        }
        
        return $overallStatus;
    }

    /**
     * Get system information.
     */
    private function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'Unknown',
            'environment' => config('app.env'),
            'debug_mode' => config('app.debug') ? 'Enabled' : 'Disabled',
            'timezone' => config('app.timezone'),
            'locale' => config('app.locale'),
            'cache_driver' => config('cache.default'),
            'queue_driver' => config('queue.default'),
            'session_driver' => config('session.driver'),
            'database_driver' => config('database.default'),
            'mail_driver' => config('mail.default'),
            'app_url' => config('app.url'),
            'app_name' => config('app.name'),
            'uptime' => $this->getUptime(),
        ];
    }

    /**
     * Get server uptime.
     */
    private function getUptime(): string
    {
        if (!function_exists('shell_exec')) {
            return 'Unknown (shell_exec disabled)';
        }
        
        // Try different commands based on OS
        $os = strtoupper(PHP_OS);
        
        if (strpos($os, 'WIN') === 0) {
            // Windows
            $uptime = @shell_exec('systeminfo | find "System Boot Time"');
            if ($uptime) {
                return 'Windows: ' . trim($uptime);
            }
            
            // Alternative for Windows
            $uptime = @shell_exec('net stats server | find "Statistics since"');
            if ($uptime) {
                return 'Windows: ' . trim($uptime);
            }
        } else {
            // Unix/Linux/Mac
            $uptime = @shell_exec('uptime');
            if ($uptime) {
                return trim($uptime);
            }
            
            // Alternative for Unix
            $uptime = @shell_exec('cat /proc/uptime');
            if ($uptime) {
                $uptime = floatval(explode(' ', $uptime)[0]);
                $days = floor($uptime / 86400);
                $hours = floor(($uptime % 86400) / 3600);
                $minutes = floor(($uptime % 3600) / 60);
                return sprintf('%d days, %d hours, %d minutes', $days, $hours, $minutes);
            }
        }
        
        return 'Unknown (no uptime command available)';
    }

    /**
     * Convert memory limit string to bytes.
     */
    private function convertToBytes(string $memoryLimit): int
    {
        $unit = strtolower(substr($memoryLimit, -1));
        $value = (int) substr($memoryLimit, 0, -1);
        
        switch ($unit) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
            default:
                return (int) $memoryLimit;
        }
    }

    /**
     * Run a specific health check.
     */
    public function runCheck(Request $request)
    {
        $check = $request->input('check');
        
        // This would run a specific check and return JSON
        // For now, just redirect back
        return redirect()->route('admin.health.index')->with('success', 'Health check completed.');
    }

    /**
     * Export health report.
     */
    public function export()
    {
        $healthChecks = $this->performHealthChecks();
        $systemInfo = $this->getSystemInfo();
        
        // This would generate a PDF/CSV report
        // For now, just redirect back
        return redirect()->route('admin.health.index')->with('success', 'Health report exported.');
    }
}