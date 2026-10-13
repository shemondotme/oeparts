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
