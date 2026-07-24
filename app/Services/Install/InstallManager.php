<?php

namespace App\Services\Install;

use App\Models\Admin;
use App\Models\Setting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * InstallManager — chunked, resumable installer FSM. Mirrors
 * App\Services\Backup\BackupManager's start()/advance() pattern (rule #48):
 * one step per advance() call, so a single HTTP request never runs the whole
 * migrate+seed+admin-creation pipeline. That used to be exactly one big
 * synchronous call in InstallerController::install() — a real timeout risk
 * on shared hosting with a short max_execution_time (public/.htaccess's
 * max_execution_time override only works under mod_php, not PHP-FPM, which
 * is what most shared hosts actually run).
 *
 * State is a plain JSON file (storage/app/install/state.json), not a DB
 * table — the very first step (migrate:fresh) runs BEFORE any app table
 * exists, so there's nowhere in the DB to persist a checkpoint until after
 * that step completes. Same "state lives on disk, not in the app it's
 * building" philosophy as the Recovery Console (rule #50). All wizard
 * decisions (admin credentials, site settings, mail config) are captured
 * into the state file at start() time rather than re-read from the Laravel
 * session on every advance() call — a long install shouldn't be able to
 * lose its own inputs to session expiry mid-run.
 */
class InstallManager
{
    public function statePath(): string
    {
        return storage_path('app/install/state.json');
    }

    public function logPath(): string
    {
        return storage_path('logs/install-'.date('Y-m-d').'.log');
    }

    /**
     * Begin a new install run. Overwrites any previous (e.g. failed) run —
     * the caller is responsible for only calling this from a fresh wizard
     * submission.
     */
    public function start(array $input): array
    {
        File::ensureDirectoryExists(dirname($this->statePath()));

        $state = [
            'started_at' => now()->toIso8601String(),
            'input' => $input,
            'steps' => $this->buildSteps($input),
            'step_index' => 0,
            'status' => 'running',
            'error' => null,
            'log' => [],
        ];

        $this->writeState($state);
        $this->writeLog('info', 'Install run started.', ['keys' => array_keys($input)]);

        return $this->progressPayload($state);
    }

    /** True if a run is currently in progress (state file exists, not yet terminal). */
    public function isRunning(): bool
    {
        $state = $this->readState();

        return $state !== null && $state['status'] === 'running';
    }

    /** True if the last run ended in failure and can be retried. */
    public function hasFailedRun(): bool
    {
        $state = $this->readState();

        return $state !== null && $state['status'] === 'failed';
    }

    public function currentProgress(): array
    {
        $state = $this->readState();

        if ($state === null) {
            return ['status' => 'not_started', 'percent' => 0, 'message' => null, 'error' => null];
        }

        return $this->progressPayload($state);
    }

    /**
     * Perform exactly ONE chunk of work (one step) and persist the checkpoint.
     * Designed to be called once per AJAX poll.
     */
    public function advance(): array
    {
        $state = $this->readState();

        if ($state === null) {
            return ['status' => 'not_started', 'percent' => 0, 'message' => 'No install run in progress.', 'error' => null];
        }

        if (in_array($state['status'], ['success', 'failed'], true)) {
            return $this->progressPayload($state);
        }

        $steps = $state['steps'];
        $index = $state['step_index'];

        if ($index >= count($steps)) {
            $state['status'] = 'success';
            $this->writeState($state);

            return $this->progressPayload($state);
        }

        $key = $steps[$index];

        try {
            $message = $this->runStep($key, $state['input']);

            $state['log'][] = ['step' => $key, 'status' => 'ok', 'message' => $message, 'at' => now()->toIso8601String()];
            $this->writeLog('info', "Step [{$key}] completed: {$message}");

            $state['step_index'] = $index + 1;
            $state['status'] = $state['step_index'] >= count($steps) ? 'success' : 'running';
        } catch (\Throwable $e) {
            $state['status'] = 'failed';
            $state['error'] = "[{$key}] ".$e->getMessage();
            $state['log'][] = ['step' => $key, 'status' => 'failed', 'message' => $e->getMessage(), 'at' => now()->toIso8601String()];
            $this->writeLog('error', "Step [{$key}] failed: ".$e->getMessage());
        }

        $this->writeState($state);

        return $this->progressPayload($state);
    }

    /** Discard the current run's state — used when the user retries after a failure. */
    public function reset(): void
    {
        @unlink($this->statePath());
    }

    /**
     * Refuse to run migrate:fresh over a database that already has a
     * completed installation — this is the single most destructive
     * operation the installer can perform, and the ONLY thing standing
     * between it and a live production database is the operator not having
     * deleted storage/installed.lock. Checked independently here (not just
     * in InstallerMiddleware) so this guard survives even if the middleware
     * is ever bypassed or refactored.
     */
    public function looksAlreadyInstalled(): bool
    {
        try {
            return Schema::hasTable('admins') && DB::table('admins')->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Write .env values atomically (same rename-swap pattern as before —
     * a plain File::put() truncates in place, and a concurrent request
     * reading .env mid-write would see an empty file).
     */
    public function updateEnvFile(array $values): void
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            File::copy(base_path('.env.example'), $envPath);
        }

        $envContent = File::get($envPath);

        foreach ($values as $key => $value) {
            $escapedKey = preg_quote($key, '/');
            $pattern = "/^{$escapedKey}=.*/m";
            $replacement = "{$key}=\"{$value}\"";

            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
            } else {
                $envContent .= "\n{$replacement}";
            }
        }

        $tmpPath = $envPath.'.tmp';
        File::put($tmpPath, $envContent);
        if (file_exists($envPath)) {
            @chmod($tmpPath, @fileperms($envPath) & 0777);
        }
        if (! @rename($tmpPath, $envPath)) {
            File::put($envPath, $envContent);
            @unlink($tmpPath);
        }
    }

    /* ---- Steps ----------------------------------------------------------- */

    private function buildSteps(array $input): array
    {
        $steps = [
            'migrate',
            'seed_settings',
            'seed_languages',
            'seed_roles',
            'seed_sequences',
            'seed_carriers',
            'seed_sections',
            'create_admin',
            'persist_settings',
            'mail_env',
        ];

        if (! empty($input['import_demo_data'])) {
            $steps[] = 'demo_data';
        }

        $steps[] = 'lock';

        return $steps;
    }

    /** protected (not private) so tests can override individual steps without hitting real DB/Artisan work. */
    protected function runStep(string $key, array $input): string
    {
        return match ($key) {
            'migrate' => $this->stepMigrate(),
            'seed_settings' => $this->stepSeed('SettingsSeeder'),
            'seed_languages' => $this->stepSeed('LanguagesSeeder'),
            'seed_roles' => $this->stepSeed('RolesSeeder'),
            'seed_sequences' => $this->stepSeed('SequencesSeeder'),
            'seed_carriers' => $this->stepSeed('CarriersSeeder'),
            'seed_sections' => $this->stepSeed('SectionsSeeder'),
            'create_admin' => $this->stepCreateAdmin($input),
            'persist_settings' => $this->stepPersistSettings($input),
            'mail_env' => $this->stepMailEnv($input),
            'demo_data' => $this->stepDemoData(),
            'lock' => $this->stepLock(),
            default => throw new \RuntimeException("Unknown install step: {$key}"),
        };
    }

    private function stepMigrate(): string
    {
        if ($this->looksAlreadyInstalled()) {
            throw new \RuntimeException(
                'A completed installation was detected (the admins table already has data). '.
                'Refusing to run migrate:fresh, which would erase it. If you really intend to '.
                'reinstall over this database, back it up and empty it manually first.'
            );
        }

        $exit = Artisan::call('migrate:fresh', ['--force' => true, '--seed' => false]);

        if ($exit !== 0) {
            throw new \RuntimeException('migrate:fresh failed (exit '.$exit.'): '.trim(Artisan::output()));
        }

        return 'Database schema created.';
    }

    private function stepSeed(string $class): string
    {
        $exit = Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\'.$class, '--force' => true]);

        if ($exit !== 0) {
            throw new \RuntimeException($class.' seeder failed (exit '.$exit.'): '.trim(Artisan::output()));
        }

        return $class.' seeded.';
    }

    private function stepCreateAdmin(array $input): string
    {
        $admin = Admin::create([
            'name' => $input['admin_name'],
            'email' => $input['admin_email'],
            'password' => $input['admin_password_hash'],
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // Requires RolesSeeder to have already run (Spatie role must exist).
        $admin->assignRole('super_admin');

        return 'Admin account created.';
    }

    private function stepPersistSettings(array $input): string
    {
        // Every one of these keys already exists (seeded by SettingsSeeder above)
        // under the 'general' group — the match must include 'group', not just
        // 'key': the settings table's unique constraint is (group, key), and
        // several groups reuse the same key names (e.g. 'timezone'), so a
        // key-only match risks silently updating an unrelated row instead of
        // this one.
        $map = [
            'site_name' => $input['site_name'],
            'site_url' => $input['site_url'],
            'default_locale' => $input['default_locale'],
            'timezone' => $input['timezone'],
        ];

        foreach ($map as $key => $value) {
            Setting::updateOrCreate(
                ['group' => 'general', 'key' => $key],
                ['value' => $value, 'type' => 'string']
            );
        }

        return 'Site settings saved.';
    }

    private function stepMailEnv(array $input): string
    {
        $this->updateEnvFile([
            'MAIL_MAILER' => $input['mail_driver'] ?? 'smtp',
            'MAIL_HOST' => $input['mail_host'] ?? '',
            'MAIL_PORT' => $input['mail_port'] ?? '587',
            'MAIL_USERNAME' => $input['mail_username'] ?? '',
            'MAIL_PASSWORD' => $input['mail_password'] ?? '',
            'MAIL_ENCRYPTION' => $input['mail_encryption'] ?? 'tls',
            'MAIL_FROM_ADDRESS' => $input['mail_from_address'] ?? '',
            'MAIL_FROM_NAME' => $input['mail_from_name'] ?? '',
        ]);

        return 'Mail settings written to .env.';
    }

    /**
     * Optional demo catalog data (manufacturers/parts/blog posts — never
     * touches admin/customer accounts). Best-effort: a failure here must not
     * fail an otherwise-successful installation, since the site is fully
     * usable without it.
     */
    private function stepDemoData(): string
    {
        try {
            $exit = Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\DemoDataSeeder', '--force' => true]);

            if ($exit !== 0) {
                $this->writeLog('warning', 'Demo data seeder failed (exit '.$exit.'), continuing without it.');

                return 'Demo data skipped (seeder failed) — site is still fully installed.';
            }

            return 'Demo data imported.';
        } catch (\Throwable $e) {
            $this->writeLog('warning', 'Demo data seeder threw: '.$e->getMessage());

            return 'Demo data skipped ('.$e->getMessage().') — site is still fully installed.';
        }
    }

    private function stepLock(): string
    {
        File::ensureDirectoryExists(dirname(storage_path('installed.lock')));
        File::put(storage_path('installed.lock'), 'Installed at '.now()->toDateTimeString());

        session()->forget('installer');
        Artisan::call('view:clear');

        return 'Installation complete.';
    }

    /* ---- State I/O --------------------------------------------------------- */

    private function readState(): ?array
    {
        if (! file_exists($this->statePath())) {
            return null;
        }

        $data = json_decode((string) @file_get_contents($this->statePath()), true);

        return is_array($data) ? $data : null;
    }

    private function writeState(array $state): void
    {
        $tmp = $this->statePath().'.tmp';
        file_put_contents($tmp, json_encode($state, JSON_PRETTY_PRINT), LOCK_EX);
        @rename($tmp, $this->statePath());
    }

    private function progressPayload(array $state): array
    {
        $total = max(1, count($state['steps']));
        $percent = $state['status'] === 'success'
            ? 100
            : (int) round(($state['step_index'] / $total) * 100);

        $lastLog = ! empty($state['log']) ? end($state['log']) : null;

        return [
            'status' => $state['status'],
            'percent' => $percent,
            'step' => $state['steps'][$state['step_index']] ?? null,
            'step_index' => $state['step_index'],
            'total_steps' => $total,
            'message' => $lastLog['message'] ?? null,
            'error' => $state['error'],
        ];
    }

    /** Dedicated per-day install log — readable without SSH/CLI (rule #41). */
    private function writeLog(string $level, string $message, array $context = []): void
    {
        try {
            Log::build(['driver' => 'single', 'path' => $this->logPath()])->{$level}($message, $context);
        } catch (\Throwable) {
            // Logging must never break the install itself.
        }
    }
}
