<?php

namespace App\Services\Install;

use App\Models\Admin;
use App\Models\Setting;
use App\Services\Backup\BackupCipher;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * InstallManager — chunked, resumable installer FSM. Mirrors
 * App\Services\Backup\BackupManager's start()/advance() pattern:
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
 * building" philosophy as the Recovery Console. All wizard
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

    /** A run that has not finished yet: in progress, or failed and waiting for a retry. */
    public function hasUnfinishedRun(): bool
    {
        return $this->isRunning() || $this->hasFailedRun();
    }

    /**
     * Can an unfinished run be started over from the first step? Only while no
     * admin account exists yet: the first step runs migrate:fresh, which
     * stepMigrate() refuses to do over a database that already has an admin.
     * After that point the only way forward is resuming at the failed step.
     */
    public function canRestart(): bool
    {
        return ! $this->looksAlreadyInstalled();
    }

    /**
     * Put a failed run back to 'running' at the step that failed (the step
     * index is only advanced after a step succeeds, so it still points there).
     * Returns false when there is no failed run to resume.
     */
    public function resumeFailed(): bool
    {
        $lock = $this->acquireAdvanceLock();
        if ($lock === null) {
            return false; // A poll is mid-step right now; nothing to resume yet.
        }

        try {
            $state = $this->readState();

            if ($state === null || $state['status'] !== 'failed') {
                return false;
            }

            $state['status'] = 'running';
            $state['error'] = null;
            $state['log'][] = ['step' => $state['steps'][$state['step_index']] ?? null, 'status' => 'resumed', 'message' => 'Resuming after a failure.', 'at' => now()->toIso8601String()];
            $this->writeState($state);
            $this->writeLog('info', 'Install run resumed at step index '.$state['step_index'].'.');

            return true;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
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
        // The wizard's JS re-polls after ANY failed response — including a gateway
        // timeout on a slow step (migrate:fresh over ~150 migrations can outlast a
        // web server's 30–60 s limit) — while the first request is still running.
        // Without this guard the retry starts a second migrate:fresh on top of the
        // first, dropping tables out from under it ("Base table or view not found"),
        // and the two runs overwrite each other's state file. Found by installing the
        // real 1.0.16 release on a stock nginx. An overlapping poll just reports
        // progress; the JS keeps polling until the running step finishes.
        $lock = $this->acquireAdvanceLock();
        if ($lock === null) {
            return $this->currentProgress();
        }

        try {
            return $this->advanceStep();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** A file lock, not a Cache/DB one: migrate:fresh drops every table, cache and lock tables included. */
    private function acquireAdvanceLock()
    {
        File::ensureDirectoryExists(dirname($this->statePath()));

        $handle = @fopen(dirname($this->statePath()).'/advance.lock', 'c');
        if ($handle === false) {
            return null;
        }
        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return null;
        }

        return $handle;
    }

    private function advanceStep(): array
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
            $state = $this->scrubSecretsOnSuccess($state);
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

            if ($state['status'] === 'success') {
                $state = $this->scrubSecretsOnSuccess($state);
            }
        } catch (\Throwable $e) {
            $state['status'] = 'failed';
            $state['error'] = "[{$key}] ".$e->getMessage();
            $state['log'][] = ['step' => $key, 'status' => 'failed', 'message' => $e->getMessage(), 'at' => now()->toIso8601String()];
            $this->writeLog('error', "Step [{$key}] failed: ".$e->getMessage());
        }

        $this->writeState($state);

        return $this->progressPayload($state);
    }

    /**
     * Discard the current run's state so a new run can start from the top —
     * used when the user retries after a failure. Refused (returns false, state
     * kept) once an admin account exists: the next request would find no run,
     * see the populated admins table, and write installed.lock onto an install
     * that never reached its own lock step (settings, mail and backup key
     * missing). Resume with resumeFailed() instead.
     */
    public function reset(): bool
    {
        if (! $this->canRestart()) {
            return false;
        }

        @unlink($this->statePath());

        return true;
    }

    /**
     * mail_password (plaintext SMTP credential) and admin_password_hash sat
     * in $state['input'] indefinitely once an install finished — reset()
     * only ever ran on a FAILED run being retried, never on success, so
     * these secrets stayed readable on disk (storage/app/install/state.json)
     * for as long as the file existed, which was forever. Called the moment
     * a run transitions to 'success', not after; the rest of $state (steps,
     * log, timestamps) is kept so the progress screen still renders normally.
     */
    private function scrubSecretsOnSuccess(array $state): array
    {
        unset($state['input']['mail_password'], $state['input']['admin_password_hash']);

        return $state;
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
        $envPath = $this->envPath();

        if (! file_exists($envPath)) {
            File::copy(base_path('.env.example'), $envPath);
        }

        $envContent = File::get($envPath);

        foreach ($values as $key => $value) {
            if (! preg_match('/^[A-Z][A-Z0-9_]*$/', (string) $key)) {
                throw new \InvalidArgumentException("Not a valid .env key: {$key}");
            }

            $replacement = $key.'='.self::formatEnvValue((string) $value);
            $pattern = '/^'.preg_quote($key, '/').'=.*/m';

            if (preg_match($pattern, $envContent)) {
                // A callback, not a replacement string: preg_replace would read
                // "\" and "$1" inside a password or URL as back-references.
                $envContent = preg_replace_callback($pattern, fn () => $replacement, $envContent);
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

    /** protected so tests can point the installer at a throw-away file instead of the real .env. */
    protected function envPath(): string
    {
        return base_path('.env');
    }

    /**
     * One value as a double-quoted .env entry the dotenv parser reads back
     * exactly: backslash, double quote and dollar sign are escaped (an unescaped
     * backslash makes the whole file unparseable, so the app cannot boot, and a
     * "${NAME}" would be expanded). Line breaks and other control characters are
     * refused, because they would start a new entry.
     */
    public static function formatEnvValue(string $value): string
    {
        if (preg_match('/[\x00-\x08\x0A-\x1F\x7F]/', $value)) {
            throw new \InvalidArgumentException('A value written to .env must not contain line breaks or control characters.');
        }

        return '"'.strtr($value, ['\\' => '\\\\', '"' => '\\"', '$' => '\\$']).'"';
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
            'backup_key',
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
            'backup_key' => $this->stepBackupKey(),
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
        // updateOrCreate + syncRoles, not create + assignRole: when this step
        // dies half way (the admin row is written, the role assignment throws)
        // the run is resumed at this same step and must not trip over its own
        // earlier row (unique email).
        $admin = Admin::updateOrCreate(
            ['email' => $input['admin_email']],
            [
                'name' => $input['admin_name'],
                'password' => $input['admin_password_hash'],
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        // Requires RolesSeeder to have already run (Spatie role must exist).
        $admin->syncRoles(['super_admin']);

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

        // The site URL the owner confirmed in the wizard is also the app's own
        // base URL. Without it .env keeps the .env.example value (http://localhost)
        // and everything built from config('app.url') is wrong on a real install:
        // the public disk URL (every uploaded image), the cron-built sitemap, mail
        // links, and the https redirect that only switches on for an https APP_URL.
        $appUrl = rtrim(trim((string) $input['site_url']), '/');

        if (! preg_match('#^https?://[^\s/]+#i', $appUrl)) {
            throw new \RuntimeException('The site URL must start with http:// or https:// (got "'.$appUrl.'").');
        }

        $this->updateEnvFile(['APP_URL' => $appUrl]);

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
     * Generate a dedicated backup-encryption key so the Backup Engine (and
     * the update system's mandatory pre-update backup) work the moment the
     * install finishes — without this, an admin's first "Run backup now"
     * click (or the very first self-update attempt) fails with a
     * GDPR-driven "mandatorily encrypted, OE_BACKUP_KEY is not set" error
     * that nothing in the wizard warned them about. Only generates when
     * missing: re-running the installer after a failed run must never
     * silently replace an existing key and orphan backups already
     * encrypted with it.
     */
    private function stepBackupKey(): string
    {
        return $this->ensureBackupKey() ? 'Backup encryption key generated.' : 'Backup encryption key already present — left unchanged.';
    }

    /**
     * Give this site its OWN backup key when it has none (also used by a site import on the installer path, whose package never
     * carries the old site's key). Never replaces an existing key.
     *
     * @return bool true when a key was generated, false when one was already there
     */
    public function ensureBackupKey(): bool
    {
        // The config is read once per process, so a key written to .env a moment ago by this very process is not in it yet:
        // the file is the truth, and a key found there is never replaced.
        if (trim((string) config('backup.encryption.key', '')) !== '' || $this->envValue('OE_BACKUP_KEY') !== '') {
            return false;
        }

        $this->updateEnvFile([
            'OE_BACKUP_KEY' => app(BackupCipher::class)->generateKey(),
        ]);

        return true;
    }

    /** The value a key has in the .env file right now (unquoted), or '' when the file or the key is missing. */
    private function envValue(string $key): string
    {
        $path = $this->envPath();

        if (! is_file($path)) {
            return '';
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (str_starts_with($line, $key.'=')) {
                return trim(substr($line, strlen($key) + 1), " \t\"'");
            }
        }

        return '';
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

    /**
     * Mark the installation as finished. The one place that writes installed.lock, so the wizard and a site import cannot
     * disagree about what "installed" means. An existing lock is left alone (its date is the real one).
     */
    public function finalizeLock(): void
    {
        $path = storage_path('installed.lock');

        File::ensureDirectoryExists(dirname($path));

        if (! File::exists($path)) {
            File::put($path, 'Installed at '.now()->toDateTimeString());
        }
    }

    private function stepLock(): string
    {
        $this->finalizeLock();

        session()->forget('installer');
        Artisan::call('view:clear');

        // Without this, uploaded media (product images, blog/CMS images) 404
        // on every fresh install regardless of web server — the update system's
        // post_swap.artisan list runs storage:link on every subsequent update
        // (config/updates.php), but nothing ran it on first install. Best-effort:
        // a symlink failure (e.g. disabled on some shared-hosting filesystems)
        // must not block "installation complete" — the admin can run it manually.
        try {
            Artisan::call('storage:link');
        } catch (\Throwable $e) {
            $this->writeLog('warning', 'storage:link failed during install: '.$e->getMessage());
        }

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
        $json = json_encode($state, JSON_PRETTY_PRINT);
        $tmp = $this->statePath().'.tmp';
        file_put_contents($tmp, $json, LOCK_EX);

        // A failed rename (a host where rename() cannot replace an existing
        // file) used to be ignored, silently leaving the OLD state on disk, so
        // every poll re-ran the same step. Fall back to writing in place.
        if (! @rename($tmp, $this->statePath())) {
            file_put_contents($this->statePath(), $json, LOCK_EX);
            @unlink($tmp);
        }
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
            // A failed run can be started over only before the admin exists;
            // afterwards the UI offers "resume at the failed step" instead.
            'can_restart' => $state['status'] === 'failed' ? $this->canRestart() : null,
        ];
    }

    /** Dedicated per-day install log — readable without SSH/CLI. */
    private function writeLog(string $level, string $message, array $context = []): void
    {
        try {
            Log::build(['driver' => 'single', 'path' => $this->logPath()])->{$level}($message, $context);
        } catch (\Throwable) {
            // Logging must never break the install itself.
        }
    }
}
