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
        public const STATE_LOGIN     = 'login';      // armed, awaiting/failed key
        public const STATE_READY     = 'ready';      // authenticated status view

        private string $baseDir;

        /** @var array<string,string> */
        private array $env;

        private string $stateDir;

        private ?PDO $pdo = null;

        private bool $pdoResolved = false;

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

            return new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT            => 5,
            ]);
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
        public function handle(?string $providedKey, ?string $ip): array
        {
            if (! $this->secretConfigured()) {
                return [404, self::STATE_DISABLED, $this->page(
                    'Recovery Console disabled',
                    '<p>The Recovery Console is not enabled on this install. Set <code>OE_RECOVERY_KEY</code> '
                    .'in <code>.env</code> to arm it (keep it secret). See CLAUDE rule #47.</p>'
                )];
            }

            if (! $this->ipAllowed($ip)) {
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

            if (! $this->authenticate($providedKey)) {
                $failed = $providedKey !== null && $providedKey !== '';

                return [$failed ? 403 : 401, self::STATE_LOGIN, $this->loginPage($failed)];
            }

            return [200, self::STATE_READY, $this->dashboardPage($this->status())];
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
        private function dashboardPage(array $s): string
        {
            $arm  = $s['arm_info'] ?? null;
            $swap = $s['swap_state'] ?? null;

            $rows = function (array $pairs): string {
                $html = '<table class="kv">';
                foreach ($pairs as $k => $v) {
                    $html .= '<tr><th>'.$this->e((string) $k).'</th><td>'.$this->e($this->scalar($v)).'</td></tr>';
                }

                return $html.'</table>';
            };

            $body = '<div class="grid">';

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

            $body .= '<div class="note"><strong>Recovery actions</strong> (file rollback, database restore, '
                .'force maintenance off, OPcache reset) arrive in Chunk 4.2. This build is read-only status.</div>';

            return $this->page('Recovery Console', $body);
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
                .'.note{margin-top:2rem;border:1px dashed #b7a; padding:.75rem 1rem;background:#fffdf8;color:#6b6455}';

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

            // Accept the key from POST (form) or GET (deep link). Query-string keys are
            // discouraged (they land in access logs); 4.3 tightens this to POST-only.
            $key = null;
            if (isset($_POST['key'])) {
                $key = (string) $_POST['key'];
            } elseif (isset($_GET['key'])) {
                $key = (string) $_GET['key'];
            }

            $ip = $_SERVER['REMOTE_ADDR'] ?? null;

            [$http, , $html] = $console->handle($key, $ip);

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
