<?php

/**
 * OeParts Recovery Console — public/oe-recovery.php  (Module 21, Phase 4)
 * ---------------------------------------------------------------------------
 * An APP-INDEPENDENT safety net. This file MUST NOT bootstrap the Laravel
 * framework (no vendor/autoload, no Kernel) — it exists precisely for when the
 * upgraded app can no longer boot, so it cannot depend on the app it recovers
 *. It uses ONLY raw PDO + the filesystem, and parses `.env`
 * itself. It survives file swaps because public/ (except public/build) is not a
 * core path and is therefore preserved.
 *
 * Chunk 4.1 (this file's initial scope):
 *   - Standalone entry + a testable, framework-free OeRecoveryConsole class.
 *   - Reads the framework-independent state: `arm.flag` (arm-flag lifecycle),
 *     `last-swap.json` (dir-rename rollback map), `lock`.
 *   - Reads update_histories / backup_runs via raw PDO (best-effort; degrades to
 *     an empty list when the DB is unreachable — the console must still render).
 *   - Gate: OPT-IN-ARMED. Disabled unless OE_RECOVERY_KEY is set; only operable
 *     while an update window is armed; constant-time key check (hash_equals).
 *   - READ-ONLY status view. Destructive recovery actions (file rollback, DB
 *     restore, force-maintenance-off, opcache reset) land in Chunk 4.2; the full
 *     security hardening (IP allowlist enforcement, rate-limit, structured audit
 *     logging, auto-disarm-on-success from within the console) lands in Chunk 4.3.
 *
 * The heavy lifting lives in the OeRecoveryConsole class so it can be unit-tested
 * without a web request; the procedural bootstrap at the very bottom only runs when
 * this file is the HTTP entry point (guarded so `require`-ing it in tests is inert).
 */

if (! class_exists('OeRecoveryConsole', false)) {

    class OeRecoveryConsole
    {
        /** State-machine outcomes for handle() — keeps the gate testable. */
        public const STATE_DISABLED  = 'disabled';   // no OE_RECOVERY_KEY
        public const STATE_FORBIDDEN = 'forbidden';  // IP not allowed
        public const STATE_UNARMED   = 'unarmed';    // no update window open
        public const STATE_BLOCKED   = 'blocked';    // rate-limited (too many failures)
        public const STATE_LOGIN     = 'login';      // armed, awaiting/failed key
        public const STATE_READY     = 'ready';      // authenticated status view

        private string $baseDir;

        /** @var array<string,string> */
        private array $env;

        private string $stateDir;

        private ?PDO $pdo = null;

        private bool $pdoResolved = false;

        /** @var array<string,string> disk name → absolute local root (overridable for tests). */
        private array $diskRoots = [];

        /** @var callable|null injectable clock (unit tests); defaults to time(). */
        private $clock = null;

        private ?string $currentIp = null;

        /** @param array<string,string> $env */
        public function __construct(string $baseDir, array $env, ?string $stateDir = null)
        {
            $this->baseDir  = rtrim($baseDir, "/\\");
            $this->env      = $env;
            $this->stateDir = $stateDir !== null
                ? rtrim($stateDir, "/\\")
                : $this->baseDir.'/storage/app/updates';
        }

        public static function fromBase(string $baseDir): self
        {
            return new self($baseDir, self::parseEnv($baseDir.'/.env'));
        }

        /* ---- .env parsing (no Dotenv dependency) ----------------------- */

        /** @return array<string,string> */
        public static function parseEnv(string $path): array
        {
            $out = [];
            if (! is_file($path) || ! is_readable($path)) {
                return $out;
            }

            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = ltrim($line);
                if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                    continue;
                }
                [$key, $value] = explode('=', $line, 2);
                $key   = trim($key);
                $value = trim($value);

                // Strip surrounding quotes (single or double).
                $len = strlen($value);
                if ($len >= 2 && (($value[0] === '"' && $value[$len - 1] === '"')
                    || ($value[0] === "'" && $value[$len - 1] === "'"))) {
                    $value = substr($value, 1, -1);
                }

                if ($key !== '') {
                    $out[$key] = $value;
                }
            }

            return $out;
        }

        /* ---- Paths ----------------------------------------------------- */

        public function stateDir(): string
        {
            return $this->stateDir;
        }

        public function armFlagPath(): string
        {
            return $this->stateDir.'/arm.flag';
        }

        public function swapStatePath(): string
        {
            return $this->stateDir.'/last-swap.json';
        }

        public function lockPath(): string
        {
            return $this->stateDir.'/lock';
        }

        /* ---- Gate primitives ------------------------------------------- */

        /** The console is disabled entirely unless a recovery key is configured. */
        public function secretConfigured(): bool
        {
            return isset($this->env['OE_RECOVERY_KEY']) && $this->env['OE_RECOVERY_KEY'] !== '';
        }

        public function isArmed(): bool
        {
            return is_file($this->armFlagPath());
        }

        public function authenticate(?string $provided): bool
        {
            if (! $this->secretConfigured() || ! is_string($provided) || $provided === '') {
                return false;
            }

            return hash_equals((string) $this->env['OE_RECOVERY_KEY'], $provided);
        }

        /**
         * IP allowlist (parsed here in 4.1; strict enforcement + rate-limit + audit
         * logging are hardened in Chunk 4.3). Empty allowlist means "any IP".
         */
        public function ipAllowed(?string $ip): bool
        {
            $raw  = (string) ($this->env['OE_RECOVERY_IP_ALLOWLIST'] ?? '');
            $list = array_values(array_filter(array_map('trim', explode(',', $raw))));

            if ($list === []) {
                return true;
            }

            return $ip !== null && in_array($ip, $list, true);
        }

        /* ---- State readers --------------------------------------------- */

        /** @return array<string,mixed>|null */
        public function armInfo(): ?array
        {
            return $this->readJson($this->armFlagPath());
        }

        /** @return array<string,mixed>|null */
        public function swapState(): ?array
        {
            return $this->readJson($this->swapStatePath());
        }

        public function lockHeld(): bool
        {
            return is_file($this->lockPath());
        }

        /** @return array<string,mixed>|null */
        private function readJson(string $path): ?array
        {
            if (! is_file($path)) {
                return null;
            }
            $data = json_decode((string) @file_get_contents($path), true);

            return is_array($data) ? $data : null;
        }

        /* ---- Raw-PDO reads (best-effort; degrade to []) ---------------- */

        public function setPdo(?PDO $pdo): void
        {
            $this->pdo         = $pdo;
            $this->pdoResolved = true;
        }

        public function pdo(): ?PDO
        {
            if ($this->pdoResolved) {
                return $this->pdo;
            }
            $this->pdoResolved = true;

            try {
                $this->pdo = $this->connect();
            } catch (\Throwable $e) {
                $this->pdo = null;
            }

            return $this->pdo;
        }

        private function connect(): ?PDO
        {
            $driver = $this->env['DB_CONNECTION'] ?? 'mysql';
            if ($driver !== 'mysql' && $driver !== 'mariadb') {
                return null; // console targets the production MySQL/MariaDB stack
            }

            $host = $this->env['DB_HOST'] ?? '127.0.0.1';
            $port = $this->env['DB_PORT'] ?? '3306';
            $name = $this->env['DB_DATABASE'] ?? '';
            $user = $this->env['DB_USERNAME'] ?? '';
            $pass = $this->env['DB_PASSWORD'] ?? '';

            if ($name === '') {
                return null;
            }

            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT            => 5,
            ];
            // DB restore applies multi-statement SQL parts (schema DROP+CREATE, INSERT batches).
            if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) {
                $options[PDO::MYSQL_ATTR_MULTI_STATEMENTS] = true;
            }

            return new PDO($dsn, $user, $pass, $options);
        }

        public function setDiskRoot(string $disk, string $absRoot): void
        {
            $this->diskRoots[$disk] = rtrim($absRoot, "/\\");
        }

        /** Absolute local root for a backup disk. Console recovery handles LOCAL disks only. */
        public function diskRoot(string $disk): ?string
        {
            if (isset($this->diskRoots[$disk])) {
                return $this->diskRoots[$disk];
            }

            // Laravel's conventional 'local' disk root. Off-site disks (S3/SFTP) can't be
            // read framework-free — the operator must re-localise those before recovering.
            return $disk === 'local' ? $this->baseDir.'/storage/app' : null;
        }

        public function backupKeyConfigured(): bool
        {
            return isset($this->env['OE_BACKUP_KEY']) && trim((string) $this->env['OE_BACKUP_KEY']) !== '';
        }

        /* ---- Diagnostics: environment, log tail, git drift (Chunk 4.4) -- */

        /**
         * Mirrors config('updates.required_extensions') — hardcoded because this
         * file cannot call config() (no framework). Keep in sync by hand.
         */
        private const REQUIRED_EXTENSIONS = ['pdo_mysql', 'zip', 'openssl', 'mbstring', 'curl', 'fileinfo', 'json'];

        /** Mirrors config('updates.preflight.min_free_bytes')'s default. */
        private const MIN_FREE_BYTES = 200 * 1024 * 1024;

        /**
         * The same "is this install actually healthy" questions PreflightService
         * asks before an update — asked again here, read-only, for an operator who
         * landed on this console because the site is ALREADY broken and wants to
         * know why without SSH.
         *
         * @return array<int,array{label:string,ok:bool,message:string}>
         */
        public function environmentChecks(): array
        {
            $missingExt = array_values(array_filter(
                self::REQUIRED_EXTENSIONS,
                fn ($ext) => ! extension_loaded($ext)
            ));

            $free = @disk_free_space($this->baseDir);
            $freeOk = $free !== false && $free >= self::MIN_FREE_BYTES;

            return [
                $this->diagnostic(
                    'Composer autoload',
                    is_file($this->baseDir.'/vendor/autoload.php'),
                    'vendor/autoload.php is present.',
                    'vendor/autoload.php is MISSING — composer install has not completed.'
                ),
                $this->diagnostic(
                    'Required PHP extensions',
                    $missingExt === [],
                    'All required extensions are loaded.',
                    'Missing: '.implode(', ', $missingExt).'.'
                ),
                $this->diagnostic(
                    'storage/ writable',
                    is_writable($this->baseDir.'/storage'),
                    'storage/ is writable by this process.',
                    'storage/ is NOT writable by this process.'
                ),
                $this->diagnostic(
                    'bootstrap/cache writable',
                    is_writable($this->baseDir.'/bootstrap/cache'),
                    'bootstrap/cache is writable by this process.',
                    'bootstrap/cache is NOT writable by this process.'
                ),
                $this->diagnostic(
                    'Free disk space',
                    $freeOk,
                    $free !== false ? $this->humanBytes((float) $free).' free.' : 'Could not determine free disk space.',
                    $free !== false ? 'Only '.$this->humanBytes((float) $free).' free (below 200 MB).' : 'Could not determine free disk space.'
                ),
            ];
        }

        /** @return array{label:string,ok:bool,message:string} */
        private function diagnostic(string $label, bool $ok, string $okMessage, string $failMessage): array
        {
            return ['label' => $label, 'ok' => $ok, 'message' => $ok ? $okMessage : $failMessage];
        }

        private function humanBytes(float $bytes): string
        {
            $units = ['B', 'KB', 'MB', 'GB', 'TB'];
            $i = 0;
            while ($bytes >= 1024 && $i < count($units) - 1) {
                $bytes /= 1024;
                $i++;
            }

            return round($bytes, 1).' '.$units[$i];
        }

        /**
         * For a git-managed install: the working tree's ACTUAL current commit/tag,
         * compared against what the armed window says it should be — catches a
         * `git_checkout` that silently landed on the wrong ref. Read-only, local-only
         * git plumbing (no network), safe to run synchronously on every page load.
         *
         * @return array{head:string,tag:?string}|null null when this isn't a git install
         */
        public function gitDrift(): ?array
        {
            if (! is_dir($this->baseDir.'/.git')) {
                return null;
            }

            return [
                'head' => $this->gitOutput(['git', 'rev-parse', '--short', 'HEAD']) ?? 'unknown',
                'tag' => $this->gitOutput(['git', 'describe', '--tags', '--exact-match'], allowFail: true),
            ];
        }

        private function gitOutput(array $command, bool $allowFail = false): ?string
        {
            if (! $this->procFunctionsAvailable()) {
                return null;
            }

            $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $process = @proc_open($command, $descriptors, $pipes, $this->baseDir);
            if (! is_resource($process)) {
                return null;
            }

            fclose($pipes[0]);
            $out = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $code = proc_close($process);

            if ($code !== 0 && ! $allowFail) {
                return null;
            }

            $out = trim((string) $out);

            return $out !== '' ? $out : null;
        }

        /**
         * The current app log file. The 'daily' channel (this app's default, see
         * config/logging.php) rotates to `laravel-YYYY-MM-DD.log`; a 'single'
         * override (via LOG_CHANNEL env) writes plain `laravel.log`. Pick whichever
         * was modified most recently rather than assuming a filename.
         */
        public function applicationLogPath(): ?string
        {
            $dir = $this->baseDir.'/storage/logs';
            if (! is_dir($dir)) {
                return null;
            }

            $candidates = glob($dir.'/laravel-*.log') ?: [];
            if (is_file($dir.'/laravel.log')) {
                $candidates[] = $dir.'/laravel.log';
            }
            if ($candidates === []) {
                return null;
            }

            usort($candidates, fn ($a, $b) => filemtime($b) <=> filemtime($a));

            return $candidates[0];
        }

        /**
         * Last `$maxBytes` of a file without ever reading the whole thing — safe on
         * a multi-GB log. The leading partial line (cut mid-word by the byte window)
         * is dropped so the tail always starts on a real line boundary.
         */
        private function tailFile(string $path, int $maxBytes = 20000): string
        {
            if (! is_file($path) || ! is_readable($path)) {
                return '';
            }

            $size = (int) filesize($path);
            if ($size === 0) {
                return '';
            }

            $handle = @fopen($path, 'rb');
            if ($handle === false) {
                return '';
            }

            $readBytes = min($maxBytes, $size);
            fseek($handle, -$readBytes, SEEK_END);
            $data = (string) fread($handle, $readBytes);
            fclose($handle);

            if ($readBytes < $size) {
                $nl = strpos($data, "\n");
                if ($nl !== false) {
                    $data = substr($data, $nl + 1);
                }
            }

            return $data;
        }

        private function procFunctionsAvailable(): bool
        {
            $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

            foreach (['proc_open', 'proc_close', 'proc_get_status'] as $fn) {
                if (! function_exists($fn) || in_array($fn, $disabled, true)) {
                    return false;
                }
            }

            return true;
        }

        /* ---- Security: clock / rate-limit / audit / tokens (Chunk 4.3) -- */

        public function setClock(callable $clock): void
        {
            $this->clock = $clock;
        }

        private function now(): int
        {
            return $this->clock !== null ? (int) ($this->clock)() : time();
        }

        private function maxAttempts(): int
        {
            return max(1, (int) ($this->env['OE_RECOVERY_MAX_ATTEMPTS'] ?? 5));
        }

        private function lockoutSeconds(): int
        {
            return max(1, (int) ($this->env['OE_RECOVERY_LOCKOUT_SECONDS'] ?? 900));
        }

        private function tokenTtl(): int
        {
            return max(1, (int) ($this->env['OE_RECOVERY_TOKEN_TTL'] ?? 900));
        }

        public function throttleFile(): string
        {
            return $this->stateDir.'/recovery-throttle.json';
        }

        public function sessionFile(): string
        {
            return $this->stateDir.'/recovery-session.json';
        }

        public function logFile(): string
        {
            return $this->stateDir.'/recovery.log';
        }

        private function ipKey(?string $ip): string
        {
            return ($ip !== null && $ip !== '') ? $ip : 'unknown';
        }

        public function isBlocked(?string $ip): bool
        {
            $entry = ($this->readJson($this->throttleFile()) ?? [])[$this->ipKey($ip)] ?? null;

            return is_array($entry) && (int) ($entry['blocked_until'] ?? 0) > $this->now();
        }

        private function recordFailure(?string $ip): void
        {
            $throttle = $this->readJson($this->throttleFile()) ?? [];
            $k        = $this->ipKey($ip);
            $now      = $this->now();
            $entry    = $throttle[$k] ?? ['count' => 0, 'first' => $now, 'blocked_until' => 0];

            // Slide the window: a stale first-attempt starts a fresh count.
            if (($now - (int) ($entry['first'] ?? $now)) > $this->lockoutSeconds()) {
                $entry = ['count' => 0, 'first' => $now, 'blocked_until' => 0];
            }

            $entry['count'] = (int) $entry['count'] + 1;
            if ($entry['count'] >= $this->maxAttempts()) {
                $entry['blocked_until'] = $now + $this->lockoutSeconds();
            }

            $throttle[$k] = $entry;
            $this->writeJson($this->throttleFile(), $throttle);
        }

        private function recordSuccess(?string $ip): void
        {
            $throttle = $this->readJson($this->throttleFile()) ?? [];
            unset($throttle[$this->ipKey($ip)]);
            $this->writeJson($this->throttleFile(), $throttle);
        }

        /** Append one structured audit line (JSON) for every access/action. */
        public function audit(string $event, array $ctx = []): void
        {
            $this->ensureDir($this->stateDir);
            $line = json_encode(array_merge(
                ['ts' => gmdate('c', $this->now()), 'ip' => $this->currentIp, 'event' => $event],
                $ctx
            ), JSON_UNESCAPED_SLASHES);
            @file_put_contents($this->logFile(), $line.PHP_EOL, FILE_APPEND);
        }

        /** Mint a single-use-window confirm token bound to the client IP + a TTL. */
        public function mintToken(?string $ip): string
        {
            $token = bin2hex(random_bytes(32));
            $this->writeJson($this->sessionFile(), [
                'hash'    => hash('sha256', $token),
                'ip'      => $ip,
                'expires' => $this->now() + $this->tokenTtl(),
            ]);

            return $token;
        }

        public function validateToken(?string $token, ?string $ip): bool
        {
            if (! is_string($token) || $token === '') {
                return false;
            }

            $session = $this->readJson($this->sessionFile());
            if (! $session) {
                return false;
            }
            if ((int) ($session['expires'] ?? 0) < $this->now()) {
                return false;
            }
            if (($session['ip'] ?? null) !== $ip) {
                return false;
            }

            return hash_equals((string) ($session['hash'] ?? ''), hash('sha256', $token));
        }

        /** Explicitly close the recovery window: disarm + clear the session + throttle. */
        public function disarmConsole(): array
        {
            @unlink($this->armFlagPath());
            @unlink($this->sessionFile());
            @unlink($this->throttleFile());

            return ['ok' => true, 'action' => 'disarm',
                'message' => 'Recovery complete — the update window is closed and the console is disarmed and locked.'];
        }

        private function writeJson(string $path, array $data): void
        {
            $this->ensureDir(dirname($path));
            @file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        /** @return array<int,array<string,mixed>> */
        public function recentUpdates(int $limit = 10): array
        {
            return $this->query(
                'SELECT id, from_version, to_version, status, step, error, started_at, finished_at'
                .' FROM update_histories ORDER BY id DESC LIMIT '.$this->limit($limit)
            );
        }

        /** @return array<int,array<string,mixed>> */
        public function restorableBackups(int $limit = 10): array
        {
            return $this->query(
                "SELECT id, profile, status, app_version, total_bytes, part_count, manifest_path, finished_at"
                ." FROM backup_runs WHERE status = 'success' ORDER BY id DESC LIMIT ".$this->limit($limit)
            );
        }

        /** @return array<int,array<string,mixed>> */
        private function query(string $sql): array
        {
            $pdo = $this->pdo();
            if ($pdo === null) {
                return [];
            }

            try {
                $stmt = $pdo->query($sql);

                return $stmt ? $stmt->fetchAll() : [];
            } catch (\Throwable $e) {
                return [];
            }
        }

        private function limit(int $n): int
        {
            return max(1, min(100, $n));
        }

        /* ---- Status aggregate ------------------------------------------ */

        /** @return array<string,mixed> */
        public function status(): array
        {
            return [
                'base_dir'    => $this->baseDir,
                'state_dir'   => $this->stateDir,
                'php_version' => PHP_VERSION,
                'armed'       => $this->isArmed(),
                'arm_info'    => $this->armInfo(),
                'swap_state'  => $this->swapState(),
                'lock_held'   => $this->lockHeld(),
                'db_reachable' => $this->pdo() !== null,
                'updates'     => $this->recentUpdates(),
                'backups'     => $this->restorableBackups(),
                'environment' => $this->environmentChecks(),
                'git_drift'   => $this->gitDrift(),
                'app_log'     => $this->tailFile((string) $this->applicationLogPath()),
            ];
        }

        /* ---- HTTP handling (returns [status, state, html]) ------------- */

        /**
         * Resolve the gate and render the appropriate page.
         *
         * @return array{0:int,1:string,2:string} [httpStatus, stateConst, html]
         */
        public function handle(?string $providedKey, ?string $ip, ?string $action = null, ?string $token = null): array
        {
            $this->currentIp = $ip;

            if (! $this->secretConfigured()) {
                return [404, self::STATE_DISABLED, $this->page(
                    'Recovery Console disabled',
                    '<p>The Recovery Console is not enabled on this install. Set <code>OE_RECOVERY_KEY</code> '
                    .'in <code>.env</code> to arm it (keep it secret).</p>'
                )];
            }

            if (! $this->ipAllowed($ip)) {
                $this->audit('forbidden_ip');

                return [403, self::STATE_FORBIDDEN, $this->page(
                    'Access denied',
                    '<p>Your IP address is not permitted by <code>OE_RECOVERY_IP_ALLOWLIST</code>.</p>'
                )];
            }

            if (! $this->isArmed()) {
                return [423, self::STATE_UNARMED, $this->page(
                    'No active update window',
                    '<p>The console is only operable while an update window is armed. There is no '
                    .'<code>arm.flag</code> present, so nothing needs recovering. This is the normal, safe '
                    .'state between updates.</p>'
                )];
            }

            // Rate-limit: too many failed key/token attempts locks this IP out.
            if ($this->isBlocked($ip)) {
                $this->audit('blocked');

                return [429, self::STATE_BLOCKED, $this->page(
                    'Too many attempts',
                    '<p>Too many failed attempts. This address is temporarily locked out of the '
                    .'Recovery Console. Wait for the lockout window to elapse and try again.</p>'
                )];
            }

            // Action path: authenticate via a minted confirm token OR the raw key
            // (both POST-only). Actions never run from a GET link.
            if ($action !== null && $action !== '') {
                if (! ($this->validateToken($token, $ip) || $this->authenticate($providedKey))) {
                    $this->recordFailure($ip);
                    $this->audit('action_denied', ['action' => $action]);

                    return [403, self::STATE_LOGIN, $this->loginPage(true)];
                }

                $this->recordSuccess($ip);
                $result = $this->runAction($action);
                $this->audit('action', ['action' => $action, 'ok' => ! empty($result['ok'])]);

                // A successful disarm (or any action that closes the window) locks the console.
                if (! $this->isArmed()) {
                    return [200, self::STATE_UNARMED, $this->page(
                        'Recovery window closed',
                        '<p>'.$this->e((string) ($result['message'] ?? 'The console is disarmed and locked.')).'</p>'
                    )];
                }

                return [200, self::STATE_READY, $this->dashboardPage($this->status(), $this->mintToken($ip), $result)];
            }

            // Login path (POST key).
            if (! $this->authenticate($providedKey)) {
                $failed = $providedKey !== null && $providedKey !== '';
                if ($failed) {
                    $this->recordFailure($ip);
                    $this->audit('auth_fail');
                }

                return [$failed ? 403 : 401, self::STATE_LOGIN, $this->loginPage($failed)];
            }

            $this->recordSuccess($ip);
            $this->audit('auth_success');

            return [200, self::STATE_READY, $this->dashboardPage($this->status(), $this->mintToken($ip), null)];
        }

        /* ---- Recovery actions (Chunk 4.2 — still framework-free) -------- */

        /** @return array{ok:bool,action:string,message:string,detail?:array} */
        public function runAction(string $action): array
        {
            switch ($action) {
                case 'restore_db':       return $this->restoreDatabase();
                case 'rollback_files':   return $this->rollbackFiles();
                case 'maintenance_off':  return $this->forceMaintenanceOff();
                case 'opcache_reset':    return $this->resetOpcache();
                case 'clear_caches':     return $this->clearCaches();
                case 'disarm':           return $this->disarmConsole();
                default:
                    return ['ok' => false, 'action' => $action, 'message' => 'Unknown recovery action.'];
            }
        }

        /**
         * Reverse an interrupted file swap from `last-swap.json` — the framework-free
         * twin of UpdateSwapper::rollback(): move the new code back to staging and
         * restore each original from the swap-backup, in reverse order.
         */
        public function rollbackFiles(): array
        {
            $arm = $this->armInfo();
            if (($arm['deployment_type'] ?? null) === 'git') {
                // This action only knows how to reverse a dir-rename swap
                // (last-swap.json), which a git-managed install never writes
                // — it updates via `git checkout` + `composer install`
                // instead. Reporting the generic "nothing to roll back"
                // message here would read as a false all-clear for an
                // install that may genuinely be broken mid-update; give the
                // operator the real manual recovery steps instead.
                $from = $arm['from_version'] ?? '<previous version>';

                return ['ok' => false, 'action' => 'rollback_files',
                    'message' => 'This is a git-managed install — there is no file swap for this action to reverse. '
                        .'If the update left the app broken, recover manually via SSH: '
                        ."git checkout v{$from} && composer install --no-dev --optimize-autoloader && php artisan migrate"];
            }

            $map = $this->swapState();
            if (! $map || empty($map['swapped'])) {
                return ['ok' => false, 'action' => 'rollback_files',
                    'message' => 'No interrupted file swap to roll back (no last-swap.json).'];
            }

            $root       = (string) ($map['root'] ?? '');
            $backupDir  = (string) ($map['backup_dir'] ?? '');
            $stagingDir = (string) ($map['staging_dir'] ?? '');
            $restored = 0;
            $movedBack = 0;
            $errors = [];

            foreach (array_reverse($map['swapped']) as $entry) {
                $rel = (string) ($entry['path'] ?? '');
                if ($rel === '') {
                    continue;
                }
                $rootPath    = $root.'/'.$rel;
                $backupPath  = $backupDir.'/'.$rel;
                $stagingPath = $stagingDir.'/'.$rel;

                // Move the new code out of the way (preserve it in staging).
                if (file_exists($rootPath)) {
                    $this->ensureDir(dirname($stagingPath));
                    if (@rename($rootPath, $stagingPath)) {
                        $movedBack++;
                    } else {
                        $errors[] = 'could not move new code out: '.$rel;
                    }
                }

                // Restore the original (a path that had no original stays removed).
                if (! empty($entry['had_original']) && file_exists($backupPath)) {
                    $this->ensureDir(dirname($rootPath));
                    if (@rename($backupPath, $rootPath)) {
                        $restored++;
                    } else {
                        $errors[] = 'could not restore original: '.$rel;
                    }
                }
            }

            $this->resetRuntimeCaches();
            @unlink($this->swapStatePath()); // clear the recovery state once reversed

            return ['ok' => $errors === [], 'action' => 'rollback_files',
                'message' => $errors === []
                    ? "File swap reversed: {$restored} originals restored, {$movedBack} new paths moved out."
                    : 'File rollback completed with errors — see detail.',
                'detail' => ['restored' => $restored, 'moved_back' => $movedBack, 'errors' => $errors]];
        }

        /**
         * Restore the database from the latest successful pre-update safety backup —
         * the framework-free twin of RestoreManager: read the unencrypted manifest TOC,
         * decrypt each DB part (BackupCipher frame format), gunzip, and apply. Schema
         * parts run before data parts with FK checks disabled, so table order is safe.
         */
        public function restoreDatabase(): array
        {
            if (! $this->backupKeyConfigured()) {
                return ['ok' => false, 'action' => 'restore_db',
                    'message' => 'OE_BACKUP_KEY is not set — the encrypted pre-update backup cannot be decrypted.'];
            }

            $pdo = $this->pdo();
            if ($pdo === null) {
                return ['ok' => false, 'action' => 'restore_db',
                    'message' => 'Database is unreachable — cannot restore.'];
            }

            $run = $this->latestPreUpdateBackup();
            if ($run === null) {
                return ['ok' => false, 'action' => 'restore_db',
                    'message' => 'No successful pre-update backup found to restore from.'];
            }

            $manifest = $this->readManifest((string) $run['disk'], (string) $run['manifest_path']);
            if (! $manifest || empty($manifest['parts'])) {
                return ['ok' => false, 'action' => 'restore_db',
                    'message' => 'Backup manifest missing or unreadable at '.($run['manifest_path'] ?? '?').'.'];
            }

            $dbParts = array_values(array_filter(
                $manifest['parts'], fn ($p) => ($p['type'] ?? '') === 'db'
            ));
            if ($dbParts === []) {
                return ['ok' => false, 'action' => 'restore_db', 'message' => 'Backup contains no database parts.'];
            }

            // All schema (DROP+CREATE) before all data (INSERT): every table exists before
            // its rows regardless of part sequence. FK checks are disabled around the lot.
            $schema  = array_filter($dbParts, fn ($p) => (($p['meta']['kind'] ?? null) === 'schema'));
            $data    = array_filter($dbParts, fn ($p) => (($p['meta']['kind'] ?? null) === 'data'));
            $ordered = array_merge(array_values($schema), array_values($data));

            $driver  = strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
            $applied = 0;
            $tables  = [];
            $errors  = [];

            $this->toggleForeignKeys($pdo, $driver, false);
            try {
                foreach ($ordered as $p) {
                    try {
                        $sql = (string) gzdecode($this->loadPartPlaintext((string) $run['disk'], $p));
                        $pdo->exec($sql);
                        $applied++;
                        if ((($p['meta']['kind'] ?? null) === 'schema') && ! empty($p['name'])) {
                            $tables[] = $p['name'];
                        }
                    } catch (\Throwable $e) {
                        $errors[] = ($p['name'] ?? 'part').': '.$e->getMessage();
                    }
                }
            } finally {
                $this->toggleForeignKeys($pdo, $driver, true);
            }

            $this->resetRuntimeCaches();

            return ['ok' => $errors === [], 'action' => 'restore_db',
                'message' => $errors === []
                    ? 'Database restored from backup #'.$run['id'].": {$applied} parts applied, ".count($tables).' tables.'
                    : 'Database restore completed with errors — see detail.',
                'detail' => ['backup_id' => (int) $run['id'], 'parts_applied' => $applied,
                    'tables' => count($tables), 'errors' => $errors]];
        }

        /**
         * Clear the maintenance flag so the site serves again (framework-free): write the
         * `settings` row directly + a best-effort settings-cache purge. With a Redis/remote
         * cache the change lands within the 5-minute settings TTL (or on the next flush).
         */
        public function forceMaintenanceOff(): array
        {
            $pdo = $this->pdo();
            if ($pdo === null) {
                return ['ok' => false, 'action' => 'maintenance_off',
                    'message' => 'Database unreachable — cannot clear the maintenance flag.'];
            }

            try {
                $stmt = $pdo->prepare("UPDATE settings SET `value` = '0' WHERE `group` = 'maintenance' AND `key` = 'enabled'");
                $stmt->execute();
                $updated = $stmt->rowCount();
            } catch (\Throwable $e) {
                return ['ok' => false, 'action' => 'maintenance_off',
                    'message' => 'Could not update the maintenance setting: '.$e->getMessage()];
            }

            $cache = $this->clearMaintenanceCache();
            $this->resetRuntimeCaches();

            return ['ok' => true, 'action' => 'maintenance_off',
                'message' => $updated > 0
                    ? 'Maintenance mode flag cleared in the database.'
                    : 'No maintenance flag was set (already off).',
                'detail' => ['rows_updated' => $updated, 'cache' => $cache]];
        }

        public function resetOpcache(): array
        {
            $available = function_exists('opcache_reset');
            if ($available) {
                @opcache_reset();
            }
            clearstatcache(true);

            return ['ok' => true, 'action' => 'opcache_reset',
                'message' => $available
                    ? 'OPcache reset + realpath cache cleared.'
                    : 'OPcache not available on this SAPI; realpath cache cleared.',
                'detail' => ['opcache' => $available]];
        }

        /**
         * Framework-free twin of `php artisan optimize:clear`'s config/route/view
         * caches — exactly the stale-cache class of problem a manually-SSH'd fix
         * (composer install + migrate done outside the normal update flow) can leave
         * behind. Pure filesystem; needs no shell.
         */
        public function clearCaches(): array
        {
            $removed = 0;
            foreach (['config.php', 'routes-v7.php', 'routes.php', 'packages.php', 'services.php', 'events.php'] as $file) {
                $path = $this->baseDir.'/bootstrap/cache/'.$file;
                if (is_file($path) && @unlink($path)) {
                    $removed++;
                }
            }
            $removed += $this->clearDirContents($this->baseDir.'/storage/framework/views');
            $removed += $this->clearDirContents($this->baseDir.'/storage/framework/cache/data');

            $this->resetRuntimeCaches();

            return ['ok' => true, 'action' => 'clear_caches',
                'message' => "Cleared {$removed} cached file/folder entr".($removed === 1 ? 'y' : 'ies')
                    .' (bootstrap cache, compiled views, data cache) + OPcache.',
                'detail' => ['removed' => $removed]];
        }

        /** @return int entries removed (files + top-level subdirectories) */
        private function clearDirContents(string $dir): int
        {
            if (! is_dir($dir)) {
                return 0;
            }

            $removed = 0;
            foreach (scandir($dir) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..' || $entry === '.gitignore') {
                    continue;
                }
                $path = $dir.'/'.$entry;
                if (is_dir($path)) {
                    $this->removeTree($path);
                } else {
                    @unlink($path);
                }
                $removed++;
            }

            return $removed;
        }

        private function removeTree(string $dir): void
        {
            foreach (scandir($dir) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $path = $dir.'/'.$entry;
                is_dir($path) ? $this->removeTree($path) : @unlink($path);
            }
            @rmdir($dir);
        }

        /* ---- Action helpers -------------------------------------------- */

        /** @return array<string,mixed>|null */
        private function latestPreUpdateBackup(): ?array
        {
            $pdo = $this->pdo();
            if ($pdo === null) {
                return null;
            }

            try {
                $stmt = $pdo->query(
                    "SELECT id, disk, manifest_path FROM backup_runs"
                    ." WHERE `trigger` = 'pre_update' AND status = 'success' AND manifest_path IS NOT NULL"
                    .' ORDER BY id DESC LIMIT 1'
                );
                $row = $stmt ? $stmt->fetch() : false;

                return is_array($row) ? $row : null;
            } catch (\Throwable $e) {
                return null;
            }
        }

        /** @return array<string,mixed>|null */
        private function readManifest(string $disk, string $path): ?array
        {
            $root = $this->diskRoot($disk);
            if ($root === null || $path === '') {
                return null;
            }
            $abs = $root.'/'.ltrim($path, '/');
            if (! is_file($abs)) {
                return null;
            }
            $data = json_decode((string) @file_get_contents($abs), true);

            return is_array($data) ? $data : null;
        }

        /** Read a part off its (local) disk, verify + decrypt → still-gzipped plaintext. */
        private function loadPartPlaintext(string $runDisk, array $part): string
        {
            $disk = (string) ($part['disk'] ?? $runDisk);
            $root = $this->diskRoot($disk);
            if ($root === null) {
                throw new \RuntimeException('unsupported backup disk ['.$disk.'] — console recovery handles local disks only');
            }

            $abs = $root.'/'.ltrim((string) $part['path'], '/');
            if (! is_file($abs)) {
                throw new \RuntimeException('backup part missing on disk: '.($part['path'] ?? '?'));
            }

            $enc = (string) file_get_contents($abs);
            if (! empty($part['sha256']) && hash('sha256', $enc) !== $part['sha256']) {
                throw new \RuntimeException('ciphertext sha256 mismatch');
            }

            $plain = (($part['meta']['encrypted'] ?? false) === true)
                ? $this->cipherDecrypt($enc)
                : $enc;

            if (! empty($part['meta']['plain_sha256']) && hash('sha256', $plain) !== $part['meta']['plain_sha256']) {
                throw new \RuntimeException('plaintext sha256 mismatch');
            }

            return $plain;
        }

        /**
         * Decrypt an OeParts backup stream (framework-free port of BackupCipher):
         * header "OEENC1"+ver, then frames iv(12)·tag(16)·len(u32BE)·ciphertext, each an
         * independent AES-256-GCM frame with the frame index as AAD. Key = sha256(OE_BACKUP_KEY).
         */
        private function cipherDecrypt(string $enc): string
        {
            $key   = hash('sha256', trim((string) ($this->env['OE_BACKUP_KEY'] ?? '')), true);
            $magic = 'OEENC1';
            $headerLen = strlen($magic) + 1;

            if (strlen($enc) < $headerLen || substr($enc, 0, strlen($magic)) !== $magic) {
                throw new \RuntimeException('not an OeParts encrypted backup stream');
            }

            $len    = strlen($enc);
            $offset = $headerLen;
            $frame  = 0;
            $out    = '';

            while ($offset < $len) {
                if ($offset + 12 + 16 + 4 > $len) {
                    throw new \RuntimeException('truncated encrypted stream');
                }
                $iv = substr($enc, $offset, 12);
                $offset += 12;
                $tag = substr($enc, $offset, 16);
                $offset += 16;
                $clen = unpack('N', substr($enc, $offset, 4))[1];
                $offset += 4;
                if ($offset + $clen > $len) {
                    throw new \RuntimeException('truncated frame '.$frame);
                }
                $ct = substr($enc, $offset, $clen);
                $offset += $clen;

                $pt = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, pack('N', $frame));
                if ($pt === false) {
                    throw new \RuntimeException('decryption/authentication failed at frame '.$frame);
                }

                $out .= $pt;
                $frame++;
            }

            return $out;
        }

        private function toggleForeignKeys(PDO $pdo, string $driver, bool $on): void
        {
            try {
                if (in_array($driver, ['mysql', 'mariadb'], true)) {
                    $pdo->exec('SET FOREIGN_KEY_CHECKS = '.($on ? '1' : '0').';');
                } elseif ($driver === 'sqlite') {
                    $pdo->exec('PRAGMA foreign_keys = '.($on ? 'ON' : 'OFF').';');
                }
            } catch (\Throwable $e) {
                // Best-effort — a driver that rejects the toggle still restores.
            }
        }

        /** Best-effort purge of the cached `settings.maintenance` group (single key). */
        private function clearMaintenanceCache(): string
        {
            $store = strtolower((string) ($this->env['CACHE_STORE'] ?? $this->env['CACHE_DRIVER'] ?? 'file'));

            try {
                if ($store === 'file') {
                    $hash = sha1('settings.maintenance');
                    $file = $this->baseDir.'/storage/framework/cache/data/'
                        .substr($hash, 0, 2).'/'.substr($hash, 2, 2).'/'.$hash;
                    if (is_file($file)) {
                        @unlink($file);

                        return 'file cache entry cleared';
                    }

                    return 'file cache: no entry';
                }

                if ($store === 'database') {
                    $pdo = $this->pdo();
                    if ($pdo !== null) {
                        $pdo->exec("DELETE FROM cache WHERE `key` LIKE '%settings.maintenance'");

                        return 'database cache entry cleared';
                    }
                }
            } catch (\Throwable $e) {
                return 'cache clear skipped: '.$e->getMessage();
            }

            return $store.' cache: clears within the settings TTL (<= 5 min) or on next flush';
        }

        private function resetRuntimeCaches(): void
        {
            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }
            clearstatcache(true);
        }

        private function ensureDir(string $dir): void
        {
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
        }

        /* ---- Views (self-contained HTML, no external assets) ----------- */

        private function loginPage(bool $failed): string
        {
            $error = $failed
                ? '<p class="err">Invalid recovery key.</p>'
                : '<p class="muted">An update window is armed. Enter the recovery key to view status.</p>';

            return $this->page('Recovery Console', $error.
                '<form method="post" class="login">'
                .'<label>Recovery key<br><input type="password" name="key" autofocus autocomplete="off"></label>'
                .'<button type="submit">Unlock</button>'
                .'</form>');
        }

        /** @param array<string,mixed> $s */
        private function dashboardPage(array $s, ?string $token = null, ?array $actionResult = null): string
        {
            $arm  = $s['arm_info'] ?? null;
            $swap = $s['swap_state'] ?? null;

            $banner = $this->actionBanner($actionResult);

            $rows = function (array $pairs): string {
                $html = '<table class="kv">';
                foreach ($pairs as $k => $v) {
                    $html .= '<tr><th>'.$this->e((string) $k).'</th><td>'.$this->e($this->scalar($v)).'</td></tr>';
                }

                return $html.'</table>';
            };

            $topPills = '<div class="pillbar">'
                .$this->pill($s['db_reachable'] ? 'ok' : 'err', 'Database '.($s['db_reachable'] ? 'reachable' : 'unreachable'))
                .$this->pill($s['lock_held'] ? 'warn' : 'ok', 'Update lock '.($s['lock_held'] ? 'held' : 'free'))
                .$this->pill('info', 'PHP '.$s['php_version'])
                .'</div>';

            $body = $topPills.$banner.'<div class="grid">';

            // Environment.
            $body .= '<section class="card"><h2>Environment</h2>'.$rows([
                'Base directory' => $s['base_dir'],
                'State directory' => $s['state_dir'],
            ]).'</section>';

            // Armed update window.
            $armBody = $arm
                ? $rows([
                    'Armed at' => $arm['armed_at'] ?? '—',
                    'From → to' => (($arm['from_version'] ?? '?').' → '.($arm['to_version'] ?? '?')),
                    'History id' => $arm['history_id'] ?? '—',
                    'PHP (at arm)' => $arm['php_version'] ?? '—',
                    'PID' => $arm['pid'] ?? '—',
                ])
                : '<p class="muted">Armed, but the flag carries no detail.</p>';
            $body .= '<section class="card"><h2>Update window</h2>'.$armBody.'</section>';

            // Swap map (rollback source of truth).
            if ($swap) {
                $swapped = is_array($swap['swapped'] ?? null) ? $swap['swapped'] : [];
                $body .= '<section class="card"><h2>Pending file swap</h2>'.$rows([
                    'Version' => $swap['version'] ?? '—',
                    'Completed' => ! empty($swap['completed']) ? 'yes' : 'no (interrupted)',
                    'Root' => $swap['root'] ?? '—',
                    'Backup dir' => $swap['backup_dir'] ?? '—',
                    'Paths swapped' => count($swapped),
                ]).'</section>';
            } elseif (($arm['deployment_type'] ?? null) === 'git') {
                $from = $arm['from_version'] ?? '<previous version>';
                $body .= '<section class="card"><h2>Pending file swap</h2><p class="muted">This is a git-managed install — '
                    .'updates apply via <code>git checkout</code> + <code>composer install</code>, not a file swap, '
                    .'so "Roll back files" below cannot act here. If the app is broken, recover manually via SSH: '
                    .'<code>git checkout v'.htmlspecialchars($from).' &amp;&amp; composer install --no-dev '
                    .'--optimize-autoloader &amp;&amp; php artisan migrate</code></p></section>';
            } else {
                $body .= '<section class="card"><h2>Pending file swap</h2><p class="muted">No <code>last-swap.json</code> '
                    .'— no interrupted swap to reverse.</p></section>';
            }

            // Preflight / environment health — the same questions a normal update
            // asks beforehand, asked again here for an operator with no other way
            // to see why the site is broken.
            $body .= '<section class="card"><h2>Preflight checks</h2><ul class="checklist">';
            foreach ($s['environment'] as $check) {
                $body .= '<li>'.$this->pill($check['ok'] ? 'ok' : 'err', $check['label'])
                    .'<span class="muted">'.$this->e($check['message']).'</span></li>';
            }
            $body .= '</ul></section>';

            // Git drift (git-managed installs only).
            if ($s['git_drift'] !== null) {
                $drift = $s['git_drift'];
                $expected = $arm['to_version'] ?? null;
                $tagMatches = $expected === null || $drift['tag'] === null
                    || $drift['tag'] === $expected || $drift['tag'] === 'v'.$expected;
                $body .= '<section class="card"><h2>Deployment (git)</h2>'.$rows([
                    'Checked-out HEAD' => $drift['head'],
                    'Checked-out tag' => $drift['tag'] ?? '(detached, no exact tag)',
                    'Armed target version' => $expected ?? '—',
                ]);
                if (! $tagMatches) {
                    $body .= '<p class="err">Mismatch — the working tree is NOT on the armed target version. '
                        .'A <code>git_checkout</code> may have landed on the wrong ref.</p>';
                }
                $body .= '</section>';
            }

            // Manual recovery commands — pre-filled + copyable, never executed by this
            // console. composer install / migrate are deliberately NOT one-click
            // actions here: this file is a public, key-gated-but-internet-reachable
            // entry point, and giving it the ability to run shell commands was
            // judged (correctly) too large a capability to add just because an
            // operator asked for convenience. This gets most of the real value —
            // no more "was it --no-dev or --no-interaction?" under incident
            // pressure — with zero execution surface.
            $body .= '<section class="card"><h2>Manual recovery commands</h2>'
                .'<p class="muted small">Copies to clipboard. Run over SSH — this console never executes these.</p>'
                .'<div class="cmdlist">';
            foreach ($this->manualRecoveryCommands($s) as [$label, $cmd]) {
                $body .= '<div class="cmdrow"><div><div class="cmdlabel">'.$this->e($label).'</div>'
                    .'<code>'.$this->e($cmd).'</code></div>'
                    .'<button type="button" class="copy-btn" data-copy="'.$this->e($cmd).'">Copy</button></div>';
            }
            $body .= '</div></section>';

            $body .= '</div>';

            // Recent updates.
            $body .= '<h2>Recent updates</h2>'.$this->tableOr($s['updates'], ['id', 'from_version', 'to_version', 'status', 'step', 'finished_at'], 'No update history rows (or DB unreachable).');

            // Restorable backups.
            $body .= '<h2>Restorable backups</h2>'.$this->tableOr($s['backups'], ['id', 'profile', 'app_version', 'part_count', 'finished_at'], 'No successful backups found (or DB unreachable).');

            // Application log — the actual error, without SSH.
            $body .= '<h2>Application log <span class="muted small">(last lines)</span></h2>';
            $body .= $s['app_log'] !== ''
                ? '<pre class="term">'.$this->e($s['app_log']).'</pre>'
                : '<p class="muted">No application log found (or it is empty).</p>';

            $body .= $this->actionsSection($token);

            $body .= '<div class="note">Every action below is destructive — a pre-update backup exists, but '
                .'proceed deliberately. Rate-limiting, structured audit logging, and per-action confirmation '
                .'tokens (never the raw key) protect every one of them.</div>';

            return $this->page('Recovery Console', $body);
        }

        private function pill(string $tone, string $label): string
        {
            return '<span class="pill '.$this->e($tone).'">'.$this->e($label).'</span>';
        }

        /**
         * The exact SSH commands an operator would otherwise have to recall (or get
         * wrong) under incident pressure — correct for THIS install's deployment
         * type and armed target version. Display-only: see the docblock at the call
         * site for why this console never runs these itself.
         *
         * @param  array<string,mixed>  $s  status() aggregate
         * @return array<int,array{0:string,1:string}>
         */
        private function manualRecoveryCommands(array $s): array
        {
            $commands = [];

            if ($s['git_drift'] !== null) {
                $to = $s['arm_info']['to_version'] ?? null;
                if ($to !== null) {
                    $commands[] = ['Check out the target release', 'git checkout v'.$to];
                }
            }

            $commands[] = ['Install dependencies', 'composer install --no-dev --optimize-autoloader --no-interaction'];
            $commands[] = ['Run pending migrations', 'php artisan migrate --force --no-interaction'];
            $commands[] = ['Clear compiled caches', 'php artisan optimize:clear'];

            return $commands;
        }

        /** @param array<string,mixed>|null $result */
        private function actionBanner(?array $result): string
        {
            if (! $result) {
                return '';
            }

            $cls  = ! empty($result['ok']) ? 'ok' : 'err';
            $html = '<div class="banner '.$cls.'">'.$this->e((string) ($result['message'] ?? '')).'</div>';

            $errors = $result['detail']['errors'] ?? [];
            if (is_array($errors) && $errors !== []) {
                $html .= '<ul class="errs">';
                foreach ($errors as $err) {
                    $html .= '<li>'.$this->e((string) $err).'</li>';
                }
                $html .= '</ul>';
            }

            return $html;
        }

        private function actionsSection(?string $token): string
        {
            // Actions carry a short-lived confirm TOKEN (not the raw key) so the secret
            // never sits in the DOM; the token is IP-bound + expiring (Chunk 4.3). Tiers
            // are ordered least → most destructive, matching how an operator should
            // actually try things during an incident.
            $tiers = [
                ['Quick fixes', 'safe', [
                    ['opcache_reset', 'Reset OPcache', 'Flush the PHP OPcache + realpath cache.'],
                    ['clear_caches', 'Clear caches', 'Delete the compiled config/route/view/data caches so stale bytecode cannot mask a fix.'],
                    ['maintenance_off', 'Force maintenance OFF', 'Clear the maintenance flag so the storefront serves again.'],
                ]],
                ['Recovery operations', 'warn', [
                    ['rollback_files', 'Roll back files', 'Reverse the interrupted file swap (restore the previous release from last-swap.json).'],
                ]],
                ['Destructive — data loss risk', 'danger', [
                    ['restore_db', 'Restore database', 'Decrypt + apply the latest pre-update safety backup. Overwrites current data.'],
                ]],
                ['Finish', 'info', [
                    ['disarm', 'Finish recovery & disarm', 'Close the update window and lock the console (do this when recovery is complete).'],
                ]],
            ];

            $tok = $this->e((string) ($token ?? ''));
            $html = '<h2>Recovery actions</h2>';

            foreach ($tiers as [$tierLabel, $tone, $actions]) {
                $html .= '<div class="tier '.$this->e($tone).'"><h3>'.$this->e($tierLabel).'</h3><div class="actions">';
                foreach ($actions as [$act, $label, $desc]) {
                    $confirm = 'return confirm('.json_encode($label.' — are you sure? This cannot be undone.').')';
                    $html .= '<form method="post" class="action" onsubmit="'.$this->e($confirm).'">'
                        .'<input type="hidden" name="token" value="'.$tok.'">'
                        .'<input type="hidden" name="action" value="'.$this->e($act).'">'
                        .'<button type="submit">'.$this->e($label).'</button>'
                        .'<span class="muted">'.$this->e($desc).'</span>'
                        .'</form>';
                }
                $html .= '</div></div>';
            }

            return $html;
        }

        /**
         * @param  array<int,array<string,mixed>>  $rows
         * @param  array<int,string>  $cols
         */
        private function tableOr($rows, array $cols, string $empty): string
        {
            if (! is_array($rows) || $rows === []) {
                return '<p class="muted">'.$this->e($empty).'</p>';
            }

            $html = '<div class="scroll"><table class="data"><thead><tr>';
            foreach ($cols as $c) {
                $html .= '<th>'.$this->e($c).'</th>';
            }
            $html .= '</tr></thead><tbody>';
            foreach ($rows as $row) {
                $html .= '<tr>';
                foreach ($cols as $c) {
                    $html .= '<td>'.$this->e($this->scalar($row[$c] ?? '—')).'</td>';
                }
                $html .= '</tr>';
            }

            return $html.'</tbody></table></div>';
        }

        private function scalar(mixed $v): string
        {
            if (is_bool($v)) {
                return $v ? 'true' : 'false';
            }
            if ($v === null) {
                return '—';
            }
            if (is_scalar($v)) {
                return (string) $v;
            }

            return json_encode($v, JSON_UNESCAPED_SLASHES) ?: '—';
        }

        private function e(string $s): string
        {
            return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        private function page(string $title, string $body): string
        {
            $css = ':root{'
                    .'--bg:#f4f5f7;--surface:#ffffff;--border:#e2e5ea;--text:#1a2230;--muted:#667085;'
                    .'--ok:#1a8a4a;--ok-bg:#eafbf1;--ok-border:#9fdfbd;'
                    .'--warn:#9a6700;--warn-bg:#fff6e5;--warn-border:#f2d38a;'
                    .'--err:#b42318;--err-bg:#fdecea;--err-border:#f3b4ac;'
                    .'--info:#1d4fb8;--info-bg:#eef3ff;--info-border:#b9cdf5;'
                .'}'
                .'*{box-sizing:border-box}'
                .'body{font:15px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;'
                .'background:var(--bg);color:var(--text);margin:0;padding:2rem 1.25rem}'
                .'.wrap{max-width:1040px;margin:0 auto}'
                .'.tag{display:inline-block;background:var(--text);color:#fff;padding:.2rem .6rem;border-radius:4px;'
                .'font-size:.7rem;letter-spacing:.12em;font-weight:600}'
                .'h1{font-size:1.5rem;margin:.6rem 0 .25rem;letter-spacing:-.01em}'
                .'h2{font-size:1rem;margin:1.75rem 0 .6rem;color:var(--text);font-weight:600}'
                .'h3{font-size:.85rem;margin:0 0 .5rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;font-weight:600}'
                .'.pillbar{display:flex;gap:.5rem;flex-wrap:wrap;margin:.75rem 0 1.25rem}'
                .'.pill{display:inline-flex;align-items:center;gap:.35rem;padding:.2rem .6rem;border-radius:999px;'
                .'font-size:.75rem;font-weight:600;border:1px solid;white-space:nowrap}'
                .'.pill.ok{color:var(--ok);background:var(--ok-bg);border-color:var(--ok-border)}'
                .'.pill.warn{color:var(--warn);background:var(--warn-bg);border-color:var(--warn-border)}'
                .'.pill.err{color:var(--err);background:var(--err-bg);border-color:var(--err-border)}'
                .'.pill.info{color:var(--info);background:var(--info-bg);border-color:var(--info-border)}'
                .'.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(270px,1fr));gap:1rem}'
                .'.card,section{border:1px solid var(--border);border-radius:10px;padding:1rem 1.1rem;'
                .'background:var(--surface);box-shadow:0 1px 2px rgba(16,24,40,.04)}'
                .'table{border-collapse:collapse;width:100%}'
                .'table.kv th{text-align:left;color:var(--muted);font-weight:500;padding:.2rem .6rem .2rem 0;vertical-align:top;white-space:nowrap}'
                .'table.kv td{padding:.2rem 0;word-break:break-all}'
                .'.scroll{overflow-x:auto;border:1px solid var(--border);border-radius:10px}'
                .'table.data{background:var(--surface)}'
                .'table.data th,table.data td{padding:.5rem .75rem;text-align:left;white-space:nowrap;border-bottom:1px solid var(--border)}'
                .'table.data th{background:#fafbfc;color:var(--muted);font-weight:600;font-size:.8rem;text-transform:uppercase;letter-spacing:.03em}'
                .'table.data tr:last-child td{border-bottom:0}'
                .'code{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;background:#f1f2f5;'
                .'padding:.1rem .35rem;border-radius:4px;font-size:.85em}'
                .'.muted{color:var(--muted)}.small{font-size:.75rem;font-weight:400;text-transform:none}.err{color:var(--err)}'
                .'ul.checklist{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:.5rem}'
                .'ul.checklist li{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap}'
                .'.login{display:flex;gap:.5rem;align-items:flex-end;flex-wrap:wrap}'
                .'input{font:inherit;padding:.55rem .7rem;border:1px solid #cfd4dc;border-radius:6px;background:#fff}'
                .'button{font:inherit;font-weight:600;padding:.55rem 1.1rem;background:var(--text);color:#fff;'
                .'border:0;border-radius:6px;cursor:pointer}'
                .'button:hover{opacity:.88}'
                .'.note{margin-top:2rem;border:1px solid var(--border);border-radius:10px;padding:.9rem 1.1rem;'
                .'background:var(--surface);color:var(--muted);font-size:.85rem}'
                .'.banner{padding:.75rem 1rem;margin-bottom:1rem;border-radius:8px;border:1px solid;font-weight:500}'
                .'.banner.ok{background:var(--ok-bg);border-color:var(--ok-border);color:var(--ok)}'
                .'.banner.err{background:var(--err-bg);border-color:var(--err-border);color:var(--err)}'
                .'.errs{margin:.5rem 0 1rem;padding-left:1.2rem;color:var(--err)}'
                .'.term{background:#1a1d24;color:#d7dde5;border-radius:10px;padding:1rem 1.1rem;'
                .'font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:.82rem;line-height:1.5;'
                .'max-height:360px;overflow:auto;white-space:pre-wrap;word-break:break-all;margin:0}'
                .'.tier{border-left:3px solid var(--border);padding-left:1rem;margin:0 0 1.25rem}'
                .'.tier.safe{border-left-color:var(--ok-border)}'
                .'.tier.warn{border-left-color:var(--warn-border)}'
                .'.tier.danger{border-left-color:var(--err-border)}'
                .'.tier.info{border-left-color:var(--info-border)}'
                .'.actions{display:flex;flex-direction:column;gap:.5rem}'
                .'.action{display:flex;gap:.75rem;align-items:center;border:1px solid var(--border);border-radius:8px;'
                .'padding:.6rem .85rem;background:var(--surface);margin:0;flex-wrap:wrap}'
                .'.action button{white-space:nowrap}'
                .'.cmdlist{display:flex;flex-direction:column;gap:.6rem}'
                .'.cmdrow{display:flex;gap:.75rem;align-items:center;justify-content:space-between;flex-wrap:wrap}'
                .'.cmdrow code{display:block;margin-top:.15rem;background:#f1f2f5;padding:.3rem .5rem;white-space:normal;word-break:break-all}'
                .'.cmdlabel{font-size:.8rem;color:var(--muted)}'
                .'.copy-btn{background:#fff;color:var(--text);border:1px solid #cfd4dc;font-weight:500;padding:.4rem .8rem;flex-shrink:0}';

            $js = 'document.addEventListener("click",function(e){'
                .'var b=e.target.closest(".copy-btn");if(!b)return;'
                .'var t=b.getAttribute("data-copy")||"";'
                .'(navigator.clipboard&&navigator.clipboard.writeText?navigator.clipboard.writeText(t):Promise.reject())'
                .'.then(function(){var o=b.textContent;b.textContent="Copied";setTimeout(function(){b.textContent=o;},1200);})'
                .'.catch(function(){var o=b.textContent;b.textContent="Copy failed";setTimeout(function(){b.textContent=o;},1200);});'
                .'});';

            return "<!doctype html><html lang=\"en\"><head><meta charset=\"utf-8\">"
                ."<meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">"
                ."<meta name=\"robots\" content=\"noindex,nofollow\">"
                ."<title>".$this->e($title)." — OeParts Recovery</title><style>{$css}</style></head>"
                ."<body><div class=\"wrap\"><span class=\"tag\">OEPARTS RECOVERY</span>"
                ."<h1>".$this->e($title)."</h1>{$body}</div><script>{$js}</script></body></html>";
        }

        /* ---- HTTP entry point ------------------------------------------ */

        public static function main(): void
        {
            $console = self::fromBase(dirname(__DIR__));

            $isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

            // POST-only key + token + action. A key in a GET query string would leak into
            // access logs, so it is never read from $_GET (Chunk 4.3).
            $key    = ($isPost && isset($_POST['key'])) ? (string) $_POST['key'] : null;
            $token  = ($isPost && isset($_POST['token'])) ? (string) $_POST['token'] : null;
            $action = ($isPost && isset($_POST['action'])) ? (string) $_POST['action'] : null;

            $ip = $_SERVER['REMOTE_ADDR'] ?? null;

            [$http, , $html] = $console->handle($key, $ip, $action, $token);

            if (! headers_sent()) {
                http_response_code($http);
                header('Content-Type: text/html; charset=UTF-8');
                header('X-Robots-Tag: noindex, nofollow');
                header('Cache-Control: no-store');
            }

            echo $html;
        }
    }
}

// Auto-run ONLY when invoked as the real HTTP entry point. Under PHPUnit (PHP_SAPI
// === 'cli'), requiring this file just defines the class — the console does not run.
if (PHP_SAPI !== 'cli'
    && isset($_SERVER['SCRIPT_FILENAME'])
    && @realpath($_SERVER['SCRIPT_FILENAME']) === @realpath(__FILE__)) {
    OeRecoveryConsole::main();
}
