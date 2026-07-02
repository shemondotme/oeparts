<?php

namespace App\Services\Updates;

use App\Services\Backup\BackupLock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * PreflightService (Module 21, Chunk 3.1) — the environment + safety gate that
 * runs BEFORE any update is applied. Pure detection: it changes nothing, it only
 * reports whether a one-click update can safely proceed.
 *
 * A FAIL blocks the update; a WARN lets the operator proceed with acknowledgement.
 * Every check is individually callable (and unit-tested) and aggregated by run().
 * Takes the TARGET release manifest (version.json fields of the release to apply).
 */
class PreflightService
{
    public function __construct(private readonly BackupLock $lock) {}

    /** @param array<string,mixed> $manifest target release manifest */
    public function run(array $manifest): PreflightReport
    {
        return new PreflightReport([
            $this->checkLock(),
            $this->checkVersionGate($manifest),
            $this->checkPhpVersion($manifest),
            $this->checkExtensions($manifest),
            $this->checkDatabaseVersion($manifest),
            $this->checkDiskSpace($manifest),
            $this->checkWritability(),
            $this->checkOpcache(),
            $this->checkDeploymentType(),
            $this->checkMultiServer(),
            $this->checkEnvKeys($manifest),
            $this->checkSchemaDrift($manifest),
        ]);
    }

    /* ---- Checks --------------------------------------------------------- */

    /** No backup/update may already be running (they share one lock). */
    public function checkLock(): PreflightCheck
    {
        $key = 'lock';
        $label = 'No update or backup in progress';

        if ($this->lock->isLocked()) {
            return PreflightCheck::fail($key, $label,
                'A backup or update is already in progress.', $this->lock->owner());
        }

        return PreflightCheck::pass($key, $label);
    }

    /** The installed version must satisfy the release's min_version_to_update_from. */
    public function checkVersionGate(array $manifest): PreflightCheck
    {
        $key = 'version_gate';
        $label = 'Update path is valid';
        $current = app(UpdateChecker::class)->currentVersion();
        $minFrom = (string) ($manifest['min_version_to_update_from'] ?? '0.0.0');

        if ($current === 'unknown') {
            return PreflightCheck::warn($key, $label, 'Installed version is unknown; cannot verify the update path.');
        }

        if (version_compare($current, $minFrom, '<')) {
            return PreflightCheck::fail($key, $label,
                "This release requires updating from at least v{$minFrom}; you are on v{$current}. Apply intermediate releases first.",
                ['current' => $current, 'min_from' => $minFrom]);
        }

        return PreflightCheck::pass($key, $label, "From v{$current}.");
    }

    public function checkPhpVersion(array $manifest): PreflightCheck
    {
        $key = 'php';
        $label = 'PHP version';
        $min = (string) ($manifest['min_php'] ?? '8.2');
        $max = $manifest['max_php'] ?? null;

        if (version_compare(PHP_VERSION, $min, '<')) {
            return PreflightCheck::fail($key, $label, "Requires PHP ≥ {$min}; running ".PHP_VERSION.'.');
        }

        if ($max && version_compare(PHP_VERSION, (string) $max, '>')) {
            return PreflightCheck::fail($key, $label, "Requires PHP ≤ {$max}; running ".PHP_VERSION.'.');
        }

        return PreflightCheck::pass($key, $label, 'PHP '.PHP_VERSION.'.');
    }

    public function checkExtensions(array $manifest): PreflightCheck
    {
        $key = 'extensions';
        $label = 'Required PHP extensions';
        $required = (array) ($manifest['required_extensions'] ?? config('updates.required_extensions', []));

        $missing = array_values(array_filter($required, fn ($ext) => ! extension_loaded((string) $ext)));

        if ($missing !== []) {
            return PreflightCheck::fail($key, $label,
                'Missing PHP extension(s): '.implode(', ', $missing).'.', ['missing' => $missing]);
        }

        return PreflightCheck::pass($key, $label, count($required).' present.');
    }

    public function checkDatabaseVersion(array $manifest): PreflightCheck
    {
        $key = 'database';
        $label = 'Database version';
        $driver = DB::connection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return PreflightCheck::pass($key, $label, "Skipped (driver: {$driver}).");
        }

        $min = (string) ($manifest['min_mysql'] ?? config('updates.min_mysql', '8.0.16'));

        try {
            $raw = (string) (DB::selectOne('select version() as v')->v ?? '');
        } catch (\Throwable $e) {
            return PreflightCheck::warn($key, $label, 'Could not read the database version.');
        }

        preg_match('/(\d+\.\d+\.\d+)/', $raw, $m);
        $version = $m[1] ?? null;

        if ($version && version_compare($version, $min, '<')) {
            return PreflightCheck::fail($key, $label, "Requires MySQL ≥ {$min}; running {$version}.");
        }

        return PreflightCheck::pass($key, $label, $raw ?: 'ok');
    }

    public function checkDiskSpace(array $manifest): PreflightCheck
    {
        $key = 'disk';
        $label = 'Free disk space';
        $size = (int) ($manifest['size_bytes'] ?? 0);
        $multiplier = max(1, (int) config('updates.preflight.disk_multiplier', 3));
        $floor = (int) config('updates.preflight.min_free_bytes', 200 * 1024 * 1024);

        $free = @disk_free_space($this->root());
        if ($free === false) {
            return PreflightCheck::warn($key, $label, 'Could not determine free disk space.');
        }

        $needed = max($floor, $size * $multiplier);

        if ($size <= 0) {
            return $free < $floor
                ? PreflightCheck::fail($key, $label, 'Low free space ('.$this->bytes((int) $free).').')
                : PreflightCheck::warn($key, $label, 'Release size unknown; '.$this->bytes((int) $free).' free.');
        }

        if ($free < $needed) {
            return PreflightCheck::fail($key, $label,
                'Need ~'.$this->bytes($needed).' free, have '.$this->bytes((int) $free).'.',
                ['needed' => $needed, 'free' => (int) $free]);
        }

        return PreflightCheck::pass($key, $label, $this->bytes((int) $free).' free.');
    }

    /** Core paths + the swap root must be writable (dir-rename swap needs it). */
    public function checkWritability(): PreflightCheck
    {
        $key = 'writable';
        $label = 'File permissions';
        $root = $this->root();
        $notWritable = [];

        if (! is_writable($root)) {
            $notWritable[] = '(root)';
        }

        $statePath = (string) config('updates.state_path');
        if ($statePath && is_dir($statePath) && ! is_writable($statePath)) {
            $notWritable[] = 'storage/app/updates';
        }

        foreach ((array) config('updates.core_paths', []) as $rel) {
            $abs = $root.DIRECTORY_SEPARATOR.$rel;
            if (file_exists($abs) && ! is_writable($abs)) {
                $notWritable[] = $rel;
            }
        }

        if ($notWritable !== []) {
            return PreflightCheck::fail($key, $label,
                'Not writable: '.implode(', ', $notWritable).'.', ['paths' => $notWritable]);
        }

        return PreflightCheck::pass($key, $label, 'Core paths writable.');
    }

    /** After a file swap the updater must reset opcache so new classes load. */
    public function checkOpcache(): PreflightCheck
    {
        $key = 'opcache';
        $label = 'OPcache reset';
        $enabled = (bool) ini_get('opcache.enable') || (bool) ini_get('opcache.enable_cli');

        if (! $enabled) {
            return PreflightCheck::pass($key, $label, 'OPcache disabled — nothing to reset.');
        }

        if (function_exists('opcache_reset')) {
            return PreflightCheck::pass($key, $label, 'opcache_reset() available.');
        }

        // Reset unavailable: safe only if timestamps are validated (files re-checked).
        $validates = (int) ini_get('opcache.validate_timestamps') !== 0;

        return $validates
            ? PreflightCheck::warn($key, $label, 'opcache_reset() unavailable; relying on validate_timestamps.')
            : PreflightCheck::fail($key, $label, 'opcache_reset() unavailable and validate_timestamps=0 — new code would not load. A manual FPM reload is required.');
    }

    /** Git / symlink-release deployments must use their own tooling, not one-click. */
    public function checkDeploymentType(): PreflightCheck
    {
        $key = 'deployment';
        $label = 'Deployment type';
        $root = $this->root();

        if (is_dir($root.DIRECTORY_SEPARATOR.'.git')) {
            return PreflightCheck::fail($key, $label,
                'Git-managed deployment detected — update with git, not the one-click updater.');
        }

        if (is_link(rtrim($root, '/\\')) || is_dir(dirname($root).DIRECTORY_SEPARATOR.'releases')) {
            return PreflightCheck::fail($key, $label,
                'Symlink-release deployment detected — use your deploy tool to release the new version.');
        }

        return PreflightCheck::pass($key, $label, 'Standard deployment.');
    }

    public function checkMultiServer(): PreflightCheck
    {
        $key = 'multi_server';
        $label = 'Single-server deployment';

        if ((bool) config('updates.multi_server', false)) {
            return PreflightCheck::warn($key, $label,
                'Multi-server deployment flagged — apply on each node / via your orchestrator; do not auto-apply.');
        }

        return PreflightCheck::pass($key, $label);
    }

    public function checkEnvKeys(array $manifest): PreflightCheck
    {
        $key = 'env_keys';
        $label = 'New environment keys';
        $keys = (array) ($manifest['new_env_keys'] ?? []);

        if ($keys === []) {
            return PreflightCheck::pass($key, $label, 'No new keys.');
        }

        $envPath = $this->root().DIRECTORY_SEPARATOR.'.env';
        $env = is_file($envPath) ? (string) @file_get_contents($envPath) : '';

        $missing = array_values(array_filter($keys, fn ($k) => ! preg_match('/^'.preg_quote((string) $k, '/').'=/m', $env)));

        if ($missing !== []) {
            return PreflightCheck::warn($key, $label,
                'These keys will be appended to .env: '.implode(', ', $missing).'.', ['keys' => $missing]);
        }

        return PreflightCheck::pass($key, $label, 'All present.');
    }

    /** Compare the live schema fingerprint against the release's expected baseline. */
    public function checkSchemaDrift(array $manifest): PreflightCheck
    {
        $key = 'schema';
        $label = 'Database schema baseline';
        $expected = $manifest['schema_fingerprint_from'] ?? null;

        if (! $expected) {
            return PreflightCheck::pass($key, $label, 'No baseline shipped — skipped.');
        }

        $actual = $this->schemaFingerprint();

        if (! hash_equals((string) $expected, $actual)) {
            return PreflightCheck::warn($key, $label,
                'Live schema differs from the release baseline (customisations detected). Back up before applying.',
                ['expected' => $expected, 'actual' => $actual]);
        }

        return PreflightCheck::pass($key, $label, 'Matches baseline.');
    }

    /* ---- Helpers -------------------------------------------------------- */

    public function schemaFingerprint(): string
    {
        $tables = array_map(
            fn ($t) => Str::contains($t, '.') ? Str::afterLast($t, '.') : $t,
            Schema::getTableListing()
        );
        sort($tables);

        $parts = [];
        foreach ($tables as $table) {
            $cols = Schema::getColumnListing($table);
            sort($cols);
            $parts[] = $table.':'.implode(',', $cols);
        }

        return hash('sha256', implode('|', $parts));
    }

    private function root(): string
    {
        return rtrim((string) (config('updates.root_path') ?: base_path()), '/\\');
    }

    private function bytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pow = min((int) floor(log($bytes) / log(1024)), count($units) - 1);

        return round($bytes / (1024 ** $pow), 1).' '.$units[$pow];
    }
}
