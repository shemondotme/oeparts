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
            $this->checkGitTooling(),
            $this->checkMultiServer(),
            $this->checkEnvKeys($manifest),
            $this->checkSchemaDrift($manifest),
            $this->checkSignature($manifest),
            $this->checkRecoveryConsole(),
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
            // A git-managed install's rollback path (GitUpdater::rollbackTo())
            // checks out a tag literally derived from this version — 'unknown'
            // becomes tag "vunknown", which doesn't exist. If a later step then
            // fails, rollback silently fails to reverse the file side while the
            // DB restore still proceeds, leaving new code paired with the old
            // database. The zip path's rollback (UpdateSwapper::rollback(),
            // reads last-swap.json) has no such dependency, so only block here
            // for git-managed installs — the case that's actually unsafe.
            return app(GitUpdater::class)->isGitManaged()
                ? PreflightCheck::fail($key, $label,
                    'Installed version is unknown and this is a git-managed install — a failed update could not be '
                    .'safely rolled back (the rollback path needs a known version to check out). Fix version.json '
                    .'or resolve manually via SSH before updating.')
                : PreflightCheck::warn($key, $label, 'Installed version is unknown; cannot verify the update path.');
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
        $min = (string) ($manifest['min_php'] ?? '8.3');
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

    /**
     * Git-managed installs take a different apply path (UpdateApplier::GIT_STEPS —
     * git fetch/checkout + composer install instead of download/extract/swap); see
     * checkGitTooling() for the actual pass/fail gate on whether that path can run.
     * Symlink-release deployments (Capistrano/Deployer-style) still have no
     * supported one-click path and are refused outright.
     */
    public function checkDeploymentType(): PreflightCheck
    {
        $key = 'deployment';
        $label = 'Deployment type';
        $root = $this->root();

        if (app(GitUpdater::class)->isGitManaged()) {
            return PreflightCheck::pass($key, $label,
                'Git-managed deployment — will update via git fetch/checkout + composer install instead of a file swap.');
        }

        if (is_link(rtrim($root, '/\\')) || is_dir(dirname($root).DIRECTORY_SEPARATOR.'releases')) {
            return PreflightCheck::fail($key, $label,
                'Symlink-release deployment detected — use your deploy tool to release the new version.');
        }

        return PreflightCheck::pass($key, $label, 'Standard deployment.');
    }

    /**
     * Only relevant (and only runs) for a git-managed install: the git apply path
     * needs exec/proc_open enabled plus a `git` and a `composer` binary on PATH.
     * Fails closed rather than letting a git-managed install silently attempt (and
     * partially fail) a path it can't actually complete.
     */
    public function checkGitTooling(): PreflightCheck
    {
        $key = 'git_tooling';
        $label = 'Git update tooling';
        $updater = app(GitUpdater::class);

        if (! $updater->isGitManaged()) {
            return PreflightCheck::pass($key, $label, 'Not a git-managed install — n/a.');
        }

        if (! $updater->toolingAvailable()) {
            return PreflightCheck::fail($key, $label,
                'exec/proc_open, `git`, or `composer` is unavailable on this server — the git update path cannot run. '
                .'Update manually via SSH instead (git pull + composer install + php artisan migrate).');
        }

        return PreflightCheck::pass($key, $label, 'git + composer + exec available.');
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

    /**
     * Verify the release's cryptographic signature (Chunk 6.1). When a public key is
     * baked, a missing/invalid signature FAILS (blocks the update — the release can't
     * be authenticated). When no key is provisioned, WARN (signatures are opt-in).
     */
    public function checkSignature(array $manifest): PreflightCheck
    {
        $key = 'signature';
        $label = 'Release signature';
        $signer = app(ReleaseSignature::class);

        if (! $signer->enforced()) {
            return PreflightCheck::warn($key, $label,
                'Release signature verification is not enabled (no public key provisioned). '
                .'Provision OE_RELEASE_PUBLIC_KEY so updates are authenticated, not just checksummed.');
        }

        [$ok, $reason] = $signer->verifyManifest($manifest);
        if (! $ok) {
            return PreflightCheck::fail($key, $label, $reason);
        }

        // Zip-path authenticity (version+sha256) says nothing about what a
        // git checkout actually pulls down — git mode never downloads or
        // hashes a zip at all. Separately verify the manifest's signed
        // commit binding so a compromised/re-pushed git remote can't serve
        // different code under a validly-signed tag name undetected.
        if (app(GitUpdater::class)->isGitManaged()) {
            [$gitOk, $gitReason] = $signer->verifyGitManifest($manifest);
            if (! $gitOk) {
                return PreflightCheck::fail($key, $label, $gitReason);
            }

            return PreflightCheck::pass($key, $label, 'Signature valid (zip + git commit binding).');
        }

        return PreflightCheck::pass($key, $label, 'Signature valid.');
    }

    /**
     * Warn (don't block) if the app-independent Recovery Console is disarmed by a
     * missing OE_RECOVERY_KEY. Without it, a failed update that leaves the app
     * unbootable has no out-of-band recovery path (CLAUDE rule #47 / decision #6).
     */
    public function checkRecoveryConsole(): PreflightCheck
    {
        $key = 'recovery';
        $label = 'Recovery Console';

        if (! (bool) config('updates.recovery.enabled', false)) {
            return PreflightCheck::warn($key, $label,
                'OE_RECOVERY_KEY is not set — the app-independent Recovery Console is disabled. '
                .'Set it so you can recover if an update leaves the app unable to boot.');
        }

        return PreflightCheck::pass($key, $label, 'Armed on demand (OE_RECOVERY_KEY set).');
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
