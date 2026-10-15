<?php

namespace App\Security;

use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\MigrationPaths;
use App\Services\Migration\Package\Exceptions\WrongPassphraseException;
use App\Support\AtomicJsonFile;
use Illuminate\Support\Facades\Log;

/**
 * Slows down guessing of a package passphrase, per package and client address.
 *
 * State lives in small files (not the cache or the database) because the installer has neither
 * when it first asks for a passphrase. After `maxFailures` wrong passphrases the next attempts
 * are refused outright, BEFORE any key derivation runs, for `baseLock` seconds; every further
 * wrong passphrase doubles the lock up to `maxLock`. A correct passphrase clears the counter.
 * Only a wrong passphrase counts: a damaged or incomplete file says nothing about a guess.
 *
 * The cost of the key derivation is the real brake on guessing; this stops a client from simply
 * hammering it, and gives people a plain "wait N seconds" instead of silence. The package and
 * address are hashed into the file name, so no address is stored.
 */
final class PassphraseThrottle
{
    /** @var \Closure(): int */
    private readonly \Closure $clock;

    public function __construct(
        private readonly string $directory,
        private readonly int $maxFailures = 5,
        private readonly int $baseLockSeconds = 60,
        private readonly int $maxLockSeconds = 900,
        private readonly int $forgetAfterSeconds = 86400,
        ?\Closure $clock = null,
    ) {
        if ($maxFailures < 1 || $baseLockSeconds < 1 || $maxLockSeconds < $baseLockSeconds) {
            throw new \InvalidArgumentException('The throttle settings do not make sense.');
        }

        $this->clock = $clock ?? static fn (): int => time();
    }

    public static function forApplication(): self
    {
        $config = (array) config('migration.security.throttle', []);

        return new self(
            MigrationPaths::throttle(),
            (int) ($config['max_failures'] ?? 5),
            (int) ($config['base_lock_seconds'] ?? 60),
            (int) ($config['max_lock_seconds'] ?? 900),
            (int) ($config['forget_after_seconds'] ?? 86400),
        );
    }

    /**
     * Run $open (the call that tries the passphrase) under the throttle: refused while locked, a
     * wrong passphrase is counted and re-thrown, success clears the counter.
     *
     * @template T
     *
     * @param  callable(): T  $open
     * @return T
     *
     * @throws MigrationException PassphraseLocked
     * @throws WrongPassphraseException the wrong passphrase, after it was counted
     */
    public function attempt(string $packageKey, string $clientKey, callable $open): mixed
    {
        $this->assertAllowed($packageKey, $clientKey);

        try {
            $result = $open();
        } catch (WrongPassphraseException $e) {
            $this->recordFailure($packageKey, $clientKey);

            throw $e;
        }

        $this->recordSuccess($packageKey, $clientKey);

        return $result;
    }

    /** @throws MigrationException PassphraseLocked, with the seconds still to wait */
    public function assertAllowed(string $packageKey, string $clientKey): void
    {
        $seconds = $this->secondsLocked($packageKey, $clientKey);

        if ($seconds > 0) {
            throw new MigrationException(MigrationErrorCode::PassphraseLocked, ['seconds' => $seconds]);
        }
    }

    public function secondsLocked(string $packageKey, string $clientKey): int
    {
        $state = $this->readState($this->fileFor($packageKey, $clientKey));

        return max(0, (int) ($state['locked_until'] ?? 0) - ($this->clock)());
    }

    public function failures(string $packageKey, string $clientKey): int
    {
        return (int) ($this->readState($this->fileFor($packageKey, $clientKey))['failures'] ?? 0);
    }

    public function recordFailure(string $packageKey, string $clientKey): void
    {
        MigrationPaths::ensureDirectory($this->directory);
        $now = ($this->clock)();
        $file = $this->fileFor($packageKey, $clientKey);
        $this->readState($file); // clears an unreadable counter so update() starts from a clean one

        $file->update(function (array $state) use ($now): array {
            // A guess made while locked never reached the key derivation and is not counted again.
            if ((int) ($state['locked_until'] ?? 0) > $now) {
                return $state;
            }

            $failures = (int) ($state['failures'] ?? 0);
            if ($failures > 0 && $now - (int) ($state['last_failure'] ?? 0) > $this->forgetAfterSeconds) {
                $failures = 0;
            }

            $failures++;
            $lockedUntil = 0;

            if ($failures >= $this->maxFailures) {
                $exponent = min(20, $failures - $this->maxFailures);
                $lockedUntil = $now + min($this->maxLockSeconds, $this->baseLockSeconds * (2 ** $exponent));
            }

            return ['failures' => $failures, 'last_failure' => $now, 'locked_until' => $lockedUntil];
        });
    }

    public function recordSuccess(string $packageKey, string $clientKey): void
    {
        $this->forget($this->fileFor($packageKey, $clientKey)->path());
    }

    /**
     * Remove counters nobody has failed on for the forget period and whose lock is over.
     *
     * @return int counters removed
     */
    public function prune(): int
    {
        if (! is_dir($this->directory)) {
            return 0;
        }

        $now = ($this->clock)();
        $removed = 0;

        foreach (glob($this->directory.DIRECTORY_SEPARATOR.'*.json') ?: [] as $path) {
            try {
                $state = (new AtomicJsonFile($path))->read() ?? [];
            } catch (\RuntimeException) {
                $state = [];
            }

            $lastFailure = (int) ($state['last_failure'] ?? 0);

            if ((int) ($state['locked_until'] ?? 0) <= $now && $now - $lastFailure > $this->forgetAfterSeconds) {
                $this->forget($path);
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * The counter, or [] when there is none. A counter that cannot be read (a crash tore it and its
     * previous copy) is removed and logged: refusing every attempt forever, or failing with an
     * exception on the unlock screen, would be worse than starting that one counter again.
     *
     * @return array<string, mixed>
     */
    private function readState(AtomicJsonFile $file): array
    {
        try {
            return $file->read() ?? [];
        } catch (\RuntimeException $e) {
            $this->forget($file->path());
            $this->warn('A wrong-passphrase counter could not be read and was reset: '.$e->getMessage());

            return [];
        }
    }

    private function warn(string $message): void
    {
        try {
            Log::channel('migration')->warning($message);
        } catch (\Throwable) {
            // No application is booted (a plain unit test): there is no log to write to.
        }
    }

    private function fileFor(string $packageKey, string $clientKey): AtomicJsonFile
    {
        return new AtomicJsonFile($this->directory.DIRECTORY_SEPARATOR.hash('sha256', $packageKey."\0".$clientKey).'.json');
    }

    private function forget(string $path): void
    {
        foreach ([$path, $path.'.prev', $path.'.lock'] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}
