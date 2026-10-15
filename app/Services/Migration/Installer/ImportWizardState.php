<?php

namespace App\Services\Migration\Installer;

use App\Support\AtomicJsonFile;

/**
 * What the installer's "import an existing site" branch remembers, and who it belongs to.
 *
 * `/install` has no login: whoever reaches it before the owner has finished could read or change the choices (live or
 * test, site address, groups) or start a run. So the branch is bound to ONE browser. The first request that starts it
 * gets a random 256-bit nonce in a cookie; only `sha256(nonce)` is stored here. Every later request must present a
 * cookie that hashes to the stored value.
 *
 * It lives in a file (storage/app/install/import-wizard.json, written through AtomicJsonFile) and never in the session:
 * the session may be a database table that the import is about to replace. The passphrase and the keys derived from it
 * are NEVER stored here; the run keeps them in its own escrow.
 *
 * The branch can be taken over by another browser only after it has been idle for `wizard_idle_seconds` (30 minutes),
 * or on the server with `php artisan oeparts:migrate:wizard-reset`.
 */
final class ImportWizardState
{
    public const COOKIE = 'oe_imp_wiz';

    private const VERSION = 1;

    public function __construct(private readonly ?string $path = null) {}

    public static function forApplication(): self
    {
        return app(self::class);
    }

    public function path(): string
    {
        return $this->path ?? storage_path('app/install/import-wizard.json');
    }

    /** @return array<string, mixed>|null null when no one has started the branch */
    public function read(): ?array
    {
        try {
            $state = $this->file()->read();
        } catch (\RuntimeException) {
            // A torn file holds only choices, never a secret. Treating it as "nobody started" lets the owner begin again.
            return null;
        }

        return is_array($state) && isset($state['nonce_hash']) && is_string($state['nonce_hash']) ? $state : null;
    }

    public function exists(): bool
    {
        return $this->read() !== null;
    }

    public function idleSeconds(): int
    {
        return max(60, (int) config('migration.installer.wizard_idle_seconds', 1800));
    }

    /** Nobody has touched the branch for long enough that another browser may take it over. */
    public function isIdle(?array $state = null): bool
    {
        $state ??= $this->read();

        if ($state === null) {
            return true;
        }

        $last = (int) ($state['last_activity'] ?? 0);

        return $last <= 0 || now()->timestamp - $last >= $this->idleSeconds();
    }

    /**
     * Claim the branch for the browser that is asking. Free when no one has started it or the last owner went idle.
     * Done under the file's lock, so two visitors pressing the button at the same moment cannot both win.
     *
     * @return string|null the nonce for the cookie (shown once, only its hash is kept), or null when someone else holds it
     */
    public function claim(string $ip): ?string
    {
        $nonce = bin2hex(random_bytes(32));
        $won = false;

        // A torn file (read() answered "nobody") must not make the update below throw: clear it and begin again.
        if ($this->read() === null && (is_file($this->path()) || is_file($this->path().'.prev'))) {
            $this->reset();
        }

        $this->file()->update(function (array $current) use ($nonce, $ip, &$won): array {
            $existing = isset($current['nonce_hash']) && is_string($current['nonce_hash']) ? $current : null;

            if ($existing !== null && ! $this->isIdle($existing)) {
                return $current;
            }

            $won = true;
            $now = now()->timestamp;

            return [
                'version' => self::VERSION,
                'nonce_hash' => hash('sha256', $nonce),
                'ip' => $ip,
                'started_at' => $now,
                'last_activity' => $now,
                'package' => null,
                'choices' => [],
            ];
        });

        $this->lockDown();

        return $won ? $nonce : null;
    }

    /** True when the cookie value belongs to the browser that holds the branch (constant-time compare). */
    public function owns(?string $nonce): bool
    {
        $state = $this->read();

        if ($state === null || ! is_string($nonce) || $nonce === '' || strlen($nonce) > 128) {
            return false;
        }

        return hash_equals((string) $state['nonce_hash'], hash('sha256', $nonce));
    }

    /** Record that the owner did something, so the idle clock restarts. */
    public function touch(): void
    {
        $this->change([]);
    }

    /**
     * Change fields of the stored choices. Owner only (the caller has already checked); a missing file is not created.
     *
     * @param  array<string, mixed>  $fields  top-level fields to replace, e.g. ['package' => 'site.oeparts']
     */
    public function change(array $fields): void
    {
        $this->file()->update(function (array $current) use ($fields): array {
            if (! isset($current['nonce_hash'])) {
                return $current;
            }

            unset($fields['nonce_hash'], $fields['ip'], $fields['started_at']);

            return array_replace($current, $fields, ['last_activity' => now()->timestamp]);
        });

        $this->lockDown();
    }

    /**
     * sha256 of host|port|database|username of the connection the installer writes (never the password).
     */
    public static function databaseFingerprint(?array $connection = null): string
    {
        $c = $connection ?? (array) config('database.connections.mysql', []);

        return hash('sha256', implode('|', [
            (string) ($c['host'] ?? ''), (string) ($c['port'] ?? ''), (string) ($c['database'] ?? ''), (string) ($c['username'] ?? ''),
        ]));
    }

    /** Remember the database the owner's own database step connected to. */
    public function rememberDatabase(?array $connection = null): void
    {
        $this->change(['db_fingerprint' => self::databaseFingerprint($connection)]);
    }

    /** False only when a database was remembered AND the live settings now point somewhere else. */
    public function databaseUnchanged(): bool
    {
        $stored = $this->read()['db_fingerprint'] ?? null;

        return ! is_string($stored) || hash_equals($stored, self::databaseFingerprint());
    }

    /** The branch is held by a browser that has not gone idle (a takeover is not yet allowed). */
    public function held(): bool
    {
        return $this->exists() && ! $this->isIdle();
    }

    /** A run was handed over to the runner page. */
    public function holdsRun(): bool
    {
        return is_string($this->read()['run'] ?? null) && $this->read()['run'] !== '';
    }

    public function package(): ?string
    {
        $package = $this->read()['package'] ?? null;

        return is_string($package) && $package !== '' ? $package : null;
    }

    /** @return array<string, mixed> */
    public function choices(): array
    {
        $choices = $this->read()['choices'] ?? [];

        return is_array($choices) ? $choices : [];
    }

    public function ip(): ?string
    {
        $ip = $this->read()['ip'] ?? null;

        return is_string($ip) ? $ip : null;
    }

    /** The owner left the branch, or the operator reset it on the server. Safe when nothing exists. */
    public function reset(): void
    {
        foreach ([$this->path(), $this->path().'.prev', $this->path().'.lock'] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    private function file(): AtomicJsonFile
    {
        return new AtomicJsonFile($this->path());
    }

    /** The file holds no secret, but it names the browser that owns the branch: owner-only is the right default. */
    private function lockDown(): void
    {
        foreach ([$this->path(), $this->path().'.prev', $this->path().'.lock'] as $file) {
            if (is_file($file)) {
                @chmod($file, 0600);
            }
        }
    }
}
