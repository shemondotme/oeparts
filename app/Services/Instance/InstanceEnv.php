<?php

namespace App\Services\Instance;

use App\Services\Install\InstallManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * The only door through which the migration and instance code writes to .env. It writes a short, fixed list
 * of keys and nothing else (never APP_ENV, APP_KEY, MAIL_*, TRUSTED_PROXIES, database or backup keys), and
 * only values made of plain characters, because the writer puts them between quotes and a quote, a dollar
 * sign, a backslash or a line break could break out of the line.
 *
 * Changing the mode, the mail policy or a cache prefix restarts the queue workers first. A worker reads
 * .env once, when it starts: left alone it would keep dropping every customer mail after the site went
 * live (or keep sending after it was quarantined) and nobody would see it. The signal goes out BEFORE the
 * file is written because workers look for it in the cache under the prefix they started with, and the
 * prefix may be the very thing being changed.
 */
final class InstanceEnv
{
    /** Keys this class may write. */
    public const WHITELIST = [
        'APP_URL', 'OE_MODE', 'OE_INSTANCE_ID', 'CACHE_PREFIX', 'REDIS_PREFIX',
        'OE_MAIL_POLICY', 'OE_MAIL_ALLOWLIST', 'OE_NONPROD_HTTP_CRON', 'CRON_FALLBACK_ENABLED',
    ];

    /** A change to any of these must reach the workers. */
    public const RESTART_KEYS = ['OE_MODE', 'OE_INSTANCE_ID', 'OE_MAIL_POLICY', 'OE_MAIL_ALLOWLIST', 'CACHE_PREFIX', 'REDIS_PREFIX'];

    private const VALUE_PATTERN = '/^[A-Za-z0-9_.:\/,@*+\- ]*$/';

    public function __construct(
        private readonly InstallManager $installer,
        private readonly ConfigReloader $reloader,
        private readonly InstanceState $state,
        private readonly ?string $envPath = null,
    ) {}

    /**
     * @param  array<string, string|bool>  $values  a bool is written as true or false
     * @return array{changed: array<string, array{from: string, to: string}>, restarted: bool, reloaded: array<int, string>}
     *
     * @throws \InvalidArgumentException a key not on the list, or a value with a character that is not allowed
     */
    public function apply(array $values, string $by): array
    {
        $values = $this->validated($values);

        $changed = [];

        foreach ($values as $key => $to) {
            $from = $this->current($key) ?? '';

            if ($from !== $to) {
                $changed[$key] = ['from' => $from, 'to' => $to];
            }
        }

        if ($changed === []) {
            return ['changed' => [], 'restarted' => false, 'reloaded' => []];
        }

        $restarted = false;

        if (array_intersect(array_keys($changed), self::RESTART_KEYS) !== [] && config('queue.default') !== 'sync') {
            // Signal first. If it cannot be sent nothing is written: a half-changed site is worse than an unchanged one.
            Artisan::call('queue:restart');
            $restarted = true;
        }

        $this->installer->updateEnvFile(array_map(static fn (array $change): string => $change['to'], $changed));

        try {
            $this->state->recordEnvChange($by, $changed);
        } catch (\Throwable $e) {
            // The ledger is a record, .env is the truth: a ledger that cannot be written must not undo a change that was made.
            Log::error('The instance ledger could not record an .env change: '.$e->getMessage());
        }

        $reloaded = $this->reloader->apply(array_map(static fn (array $change): string => $change['to'], $changed));

        return ['changed' => $changed, 'restarted' => $restarted, 'reloaded' => $reloaded];
    }

    /**
     * What .env holds now for every key this class may write: the parsed value (null = the key is not in the file at all),
     * the line exactly as written, and how many newlines end the file. Taken before an import changes anything, so a failed
     * import can put the file back exactly, not merely to something that reads the same.
     *
     * @return array{values: array<string, string|null>, raw: array<string, string|null>, trailing: int}
     */
    public function snapshot(): array
    {
        $text = $this->fileText();
        $values = [];
        $raw = [];

        foreach (self::WHITELIST as $key) {
            $values[$key] = $this->current($key);
            $raw[$key] = $this->rawLine($key, $text);
        }

        return ['values' => $values, 'raw' => $raw, 'trailing' => $this->trailingNewlines($text)];
    }

    /**
     * Put the file back to a snapshot, byte for byte as far as these keys go: a key that was changed gets its original line
     * back, a key that was NOT in the file is taken out again, and the file ends with the newlines it ended with. Keys that
     * already match are left alone, and so is every other line of the file.
     *
     * @param  array{values?: array<string, string|null>, raw?: array<string, string|null>, trailing?: int}  $image
     * @return array{changed: array<int, string>, restarted: bool}
     */
    public function restore(array $image, string $by): array
    {
        $before = (array) ($image['raw'] ?? []);
        $text = $this->fileText();
        $changes = [];

        foreach (self::WHITELIST as $key) {
            if (array_key_exists($key, $before) && $this->rawLine($key, $text) !== $before[$key]) {
                $changes[$key] = $before[$key];
            }
        }

        if ($changes === []) {
            return ['changed' => [], 'restarted' => false];
        }

        // Workers first, as in apply(): they read the restart signal under the prefix they started with.
        $restarted = false;

        if (array_intersect(array_keys($changes), self::RESTART_KEYS) !== [] && config('queue.default') !== 'sync') {
            Artisan::call('queue:restart');
            $restarted = true;
        }

        $this->writeLines($text, $changes, (int) ($image['trailing'] ?? 1));

        $values = (array) ($image['values'] ?? []);
        $this->reloader->apply(array_map(static fn (string $key): string => (string) ($values[$key] ?? ''), array_combine(array_keys($changes), array_keys($changes))));

        try {
            $this->state->recordEnvChange($by, array_map(static fn (?string $line): array => ['from' => '(import)', 'to' => $line === null ? '(removed)' : '(restored)'], $changes));
        } catch (\Throwable $e) {
            Log::error('The instance ledger could not record an .env restore: '.$e->getMessage());
        }

        return ['changed' => array_keys($changes), 'restarted' => $restarted];
    }

    private function fileText(): string
    {
        $path = $this->envPath ?? base_path('.env');

        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    private function rawLine(string $key, string $text): ?string
    {
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            if (str_starts_with($line, $key.'=')) {
                return $line;
            }
        }

        return null;
    }

    private function trailingNewlines(string $text): int
    {
        return strlen($text) - strlen(rtrim($text, "\n"));
    }

    /**
     * @param  array<string, string|null>  $changes  key => the line to have (null = no such line)
     */
    private function writeLines(string $text, array $changes, int $trailing): void
    {
        $path = $this->envPath ?? base_path('.env');
        $lines = preg_split('/\R/', rtrim($text, "\n")) ?: [];

        foreach ($changes as $key => $raw) {
            $kept = [];
            $placed = false;

            foreach ($lines as $line) {
                if (! str_starts_with($line, $key.'=')) {
                    $kept[] = $line;

                    continue;
                }

                if ($raw !== null && ! $placed) {
                    $kept[] = $raw; // the original line goes back where the changed one stood
                    $placed = true;
                }
            }

            if ($raw !== null && ! $placed) {
                $kept[] = $raw;
            }

            $lines = $kept;
        }

        // A key that was added at the end brought a blank line with it: drop blank lines at the very end, then end as the file did.
        while ($lines !== [] && end($lines) === '') {
            array_pop($lines);
        }

        $content = implode("\n", $lines).str_repeat("\n", max(0, $trailing));

        $tmp = $path.'.tmp';
        file_put_contents($tmp, $content);
        @chmod($tmp, @fileperms($path) & 0777);

        if (! @rename($tmp, $path)) {
            file_put_contents($path, $content);
            @unlink($tmp);
        }
    }

    /** The value of a key as .env holds it now, or null when the file has no such line. */
    public function current(string $key): ?string
    {
        $path = $this->envPath ?? base_path('.env');

        if (! is_file($path)) {
            return null;
        }

        foreach (preg_split('/\R/', (string) file_get_contents($path)) ?: [] as $line) {
            if (preg_match('/^'.preg_quote($key, '/').'=(.*)$/', $line, $m) !== 1) {
                continue;
            }

            $value = trim($m[1]);

            if (strlen($value) >= 2 && $value[0] === '"' && str_ends_with($value, '"')) {
                $value = stripcslashes(substr($value, 1, -1));
            }

            return $value;
        }

        return null;
    }

    /**
     * @param  array<string, string|bool>  $values
     * @return array<string, string>
     */
    private function validated(array $values): array
    {
        $clean = [];

        foreach ($values as $key => $value) {
            if (! in_array($key, self::WHITELIST, true)) {
                throw new \InvalidArgumentException("{$key} may not be written by the instance code.");
            }

            $value = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;

            if (preg_match(self::VALUE_PATTERN, $value) !== 1) {
                throw new \InvalidArgumentException("The value for {$key} contains a character that is not allowed.");
            }

            if ($key === 'OE_MODE' && $value !== '' && ! in_array($value, InstanceMode::values(), true)) {
                throw new \InvalidArgumentException('OE_MODE must be production, staging or quarantine.');
            }

            if ($key === 'APP_URL' && $value !== '' && preg_match('#^https?://[A-Za-z0-9.-]+(:\d+)?$#', $value) !== 1) {
                throw new \InvalidArgumentException('APP_URL must be http(s)://host with no path.');
            }

            $clean[$key] = $value;
        }

        return $clean;
    }
}
