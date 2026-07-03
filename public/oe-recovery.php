<?php

/**
 * OeParts Recovery Console — public/oe-recovery.php  (Module 21, Phase 4)
 * ---------------------------------------------------------------------------
 * An APP-INDEPENDENT safety net. This file MUST NOT bootstrap the Laravel
 * framework (no vendor/autoload, no Kernel) — it exists precisely for when the
 * upgraded app can no longer boot, so it cannot depend on the app it recovers
 * (CLAUDE rule #47). It uses ONLY raw PDO + the filesystem, and parses `.env`
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
                    .'in <code>.env</code> to arm it (keep it secret). See CLAUDE rule #47.</p>'
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
                case 'restore_db':      return $this->restoreDatabase();
                case 'rollback_files':  return $this->rollbackFiles();
                case 'maintenance_off': return $this->forceMaintenanceOff();
                case 'opcache_reset':   return $this->resetOpcache();
                case 'disarm':          return $this->disarmConsole();
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

            $body = $banner.'<div class="grid">';

            // Environment.
            $body .= '<section><h2>Environment</h2>'.$rows([
                'Base directory' => $s['base_dir'],
                'State directory' => $s['state_dir'],
                'PHP version' => $s['php_version'],
                'Database reachable' => $s['db_reachable'] ? 'yes' : 'no',
                'Update lock held' => $s['lock_held'] ? 'yes' : 'no',
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
            $body .= '<section><h2>Update window</h2>'.$armBody.'</section>';

            // Swap map (rollback source of truth).
            if ($swap) {
                $swapped = is_array($swap['swapped'] ?? null) ? $swap['swapped'] : [];
                $body .= '<section><h2>Pending file swap</h2>'.$rows([
                    'Version' => $swap['version'] ?? '—',
                    'Completed' => ! empty($swap['completed']) ? 'yes' : 'no (interrupted)',
                    'Root' => $swap['root'] ?? '—',
                    'Backup dir' => $swap['backup_dir'] ?? '—',
                    'Paths swapped' => count($swapped),
                ]).'</section>';
            } else {
                $body .= '<section><h2>Pending file swap</h2><p class="muted">No <code>last-swap.json</code> '
                    .'— no interrupted swap to reverse.</p></section>';
            }

            $body .= '</div>';

            // Recent updates.
            $body .= '<h2>Recent updates</h2>'.$this->tableOr($s['updates'], ['id', 'from_version', 'to_version', 'status', 'step', 'finished_at'], 'No update history rows (or DB unreachable).');

            // Restorable backups.
            $body .= '<h2>Restorable backups</h2>'.$this->tableOr($s['backups'], ['id', 'profile', 'app_version', 'part_count', 'finished_at'], 'No successful backups found (or DB unreachable).');

            $body .= $this->actionsSection($token);

            $body .= '<div class="note">Recovery actions are live (Chunk 4.2). Rate-limiting, structured '
                .'audit logging, POST-only keys and per-action confirmation tokens are hardened in Chunk 4.3. '
                .'Every action here is destructive — a pre-update backup exists, but proceed deliberately.</div>';

            return $this->page('Recovery Console', $body);
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
            // never sits in the DOM; the token is IP-bound + expiring (Chunk 4.3).
            $tok = $this->e((string) ($token ?? ''));

            $actions = [
                ['restore_db', 'Restore database', 'Decrypt + apply the latest pre-update safety backup. Overwrites current data.'],
                ['rollback_files', 'Roll back files', 'Reverse the interrupted file swap (restore the previous release from last-swap.json).'],
                ['maintenance_off', 'Force maintenance OFF', 'Clear the maintenance flag so the storefront serves again.'],
                ['opcache_reset', 'Reset OPcache', 'Flush the PHP OPcache + realpath cache.'],
                ['disarm', 'Finish recovery & disarm', 'Close the update window and lock the console (do this when recovery is complete).'],
            ];

            $html = '<h2>Recovery actions</h2><div class="actions">';
            foreach ($actions as [$act, $label, $desc]) {
                $confirm = 'return confirm('.json_encode($label.' — are you sure? This cannot be undone.').')';
                $html .= '<form method="post" class="action" onsubmit="'.$this->e($confirm).'">'
                    .'<input type="hidden" name="token" value="'.$tok.'">'
                    .'<input type="hidden" name="action" value="'.$this->e($act).'">'
                    .'<button type="submit">'.$this->e($label).'</button>'
                    .'<span class="muted">'.$this->e($desc).'</span>'
                    .'</form>';
            }

            return $html.'</div>';
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
            $css = 'body{font:14px/1.5 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;'
                .'background:#f7f5ef;color:#1a1a1a;margin:0;padding:2rem}'
                .'.wrap{max-width:960px;margin:0 auto}'
                .'h1{font-size:1.4rem;margin:0 0 .25rem;letter-spacing:.02em}'
                .'h2{font-size:1rem;margin:1.5rem 0 .5rem;border-bottom:1px solid #d8d2c4;padding-bottom:.25rem}'
                .'.tag{display:inline-block;background:#1a1a1a;color:#f7f5ef;padding:.1rem .5rem;font-size:.7rem;letter-spacing:.1em}'
                .'.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem}'
                .'section{border:1px solid #d8d2c4;padding:.75rem 1rem;background:#fffdf8}'
                .'table{border-collapse:collapse;width:100%}'
                .'table.kv th{text-align:left;color:#6b6455;font-weight:400;padding:.15rem .5rem .15rem 0;vertical-align:top;white-space:nowrap}'
                .'table.kv td{padding:.15rem 0;word-break:break-all}'
                .'.scroll{overflow-x:auto}'
                .'table.data th,table.data td{border:1px solid #d8d2c4;padding:.3rem .5rem;text-align:left;white-space:nowrap}'
                .'table.data th{background:#efe9dc}'
                .'code{background:#efe9dc;padding:.05rem .3rem}'
                .'.muted{color:#6b6455}.err{color:#a11}'
                .'.login{display:flex;gap:.5rem;align-items:flex-end;flex-wrap:wrap}'
                .'input{font:inherit;padding:.4rem;border:1px solid #999;background:#fff}'
                .'button{font:inherit;padding:.45rem 1rem;background:#1a1a1a;color:#f7f5ef;border:0;cursor:pointer}'
                .'.note{margin-top:2rem;border:1px dashed #b7a; padding:.75rem 1rem;background:#fffdf8;color:#6b6455}'
                .'.banner{padding:.6rem 1rem;margin-bottom:1rem;border:1px solid}'
                .'.banner.ok{background:#eefbef;border-color:#7cbf88;color:#1c5b2a}'
                .'.banner.err{background:#fdecec;border-color:#d99;color:#8a1c1c}'
                .'.errs{margin:.25rem 0 1rem;padding-left:1.2rem;color:#8a1c1c}'
                .'.actions{display:flex;flex-direction:column;gap:.5rem}'
                .'.action{display:flex;gap:.75rem;align-items:center;border:1px solid #d8d2c4;padding:.5rem .75rem;background:#fffdf8;margin:0;flex-wrap:wrap}'
                .'.action button{white-space:nowrap}';

            return "<!doctype html><html lang=\"en\"><head><meta charset=\"utf-8\">"
                ."<meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">"
                ."<meta name=\"robots\" content=\"noindex,nofollow\">"
                ."<title>".$this->e($title)." — OeParts Recovery</title><style>{$css}</style></head>"
                ."<body><div class=\"wrap\"><span class=\"tag\">OEPARTS RECOVERY</span>"
                ."<h1>".$this->e($title)."</h1>{$body}</div></body></html>";
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
