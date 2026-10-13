<?php

namespace App\Security;

use App\Models\ActivityLog;
use App\Models\Admin;
use Illuminate\Support\Facades\Log;

/**
 * The audit trail of the site-migration feature (export, share, fetch, import, rollback).
 *
 * Two records with different jobs, deliberately separate:
 *
 *  record()          DURABLE. One line in storage/app/migration/history.ndjson plus a line in the
 *                    "migration" log channel. It touches no database table, so it can be written
 *                    BEFORE an import replaces the database and is still there afterwards. It is
 *                    the only writer of history.ndjson. A write that cannot be stored throws,
 *                    because a destructive step must not start without its audit line.
 *
 *  recordActivityLog()  the admin panel's Activity Log row. It lives in the database, so for an
 *                    import it is written only AFTER the import is complete (the rows of the
 *                    old database are gone by then), with admin_id NULL when the initiating
 *                    admin does not exist in the new data (admin_id is nullable; only the
 *                    foreign key to admins remains).
 *
 * Nothing secret is ever written: keys that look like tokens, passphrases, passwords, secrets,
 * claims or keys are dropped, and URLs are cut down to scheme://host/...
 */
class PackageAudit
{
    /** Context keys with these words in them are dropped, whatever they hold. */
    private const SECRET_KEY_PATTERN = '/token|passphrase|password|secret|claim|key/i';

    private const MAX_STRING = 500;

    private const MAX_DEPTH = 5;

    public function __construct(private readonly ?string $historyPath = null) {}

    public function historyPath(): string
    {
        return $this->historyPath ?? storage_path('app/migration/history.ndjson');
    }

    /**
     * Write the durable record. Idempotent by event name + run id: a stage that is resumed after
     * a crash and records the same event again writes nothing the second time.
     *
     * @param  array<string, mixed>  $context
     * @return bool true when a line was written, false when it already existed
     *
     * @throws \RuntimeException when the line could not be stored
     */
    public function record(string $event, ?string $runId = null, array $context = []): bool
    {
        $line = json_encode(
            ['ts' => now()->toIso8601String(), 'event' => $event, 'run_id' => $runId] + $this->scrub($context),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        $path = $this->historyPath();
        $dir = dirname($path);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new \RuntimeException("Cannot create the audit directory {$dir}.");
        }

        $handle = @fopen($path, 'c+b');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open the audit history {$path}.");
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                throw new \RuntimeException("Cannot lock the audit history {$path}.");
            }

            if ($runId !== null && $this->alreadyRecorded($handle, $event, $runId)) {
                return false;
            }

            fseek($handle, 0, SEEK_END);
            $bytes = $line."\n";

            if (fwrite($handle, $bytes) !== strlen($bytes)) {
                throw new \RuntimeException("Short write to the audit history {$path}: the disk may be full.");
            }

            fflush($handle);
            if (function_exists('fsync')) {
                @fsync($handle);
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        // The log channel is a second copy for people who read logs; the file above is the record.
        try {
            Log::channel('migration')->info($event, ['run_id' => $runId] + $this->scrub($context));
        } catch (\Throwable $e) {
            Log::warning('The migration log channel could not be written: '.$e->getMessage());
        }

        return true;
    }

    /**
     * The Activity Log row the admin panel shows. Call it for an import only after it completed.
     *
     * @param  array<string, mixed>  $context
     */
    public function recordActivityLog(string $event, ?string $runId, ?int $adminId, array $context = []): ActivityLog
    {
        // The initiating admin may not exist in the data that was just imported: NULL, not a broken reference.
        $existingAdminId = $adminId !== null && Admin::query()->whereKey($adminId)->exists() ? $adminId : null;

        return ActivityLog::create([
            'admin_id' => $existingAdminId,
            'action' => $event,
            'model_type' => self::class,
            'model_id' => null,
            'old_values' => [],
            'new_values' => ['run_id' => $runId] + $this->scrub($context),
            'ip_address' => (string) (request()->ip() ?? '127.0.0.1'),
        ]);
    }

    /**
     * What is recorded for a run, oldest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(?string $runId = null): array
    {
        $path = $this->historyPath();
        if (! is_file($path)) {
            return [];
        }

        $entries = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $entry = json_decode($line, true);

            if (is_array($entry) && ($runId === null || ($entry['run_id'] ?? null) === $runId)) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * Drop secret-looking keys, cut URLs down to scheme://host/..., bound the size.
     *
     * @param  array<mixed>  $context
     * @return array<mixed>
     */
    public function scrub(array $context, int $depth = 0): array
    {
        $clean = [];

        foreach ($context as $key => $value) {
            if (is_string($key) && preg_match(self::SECRET_KEY_PATTERN, $key)) {
                continue;
            }

            if (is_array($value)) {
                $clean[$key] = $depth >= self::MAX_DEPTH ? '[…]' : $this->scrub($value, $depth + 1);
            } elseif (is_string($value)) {
                $clean[$key] = $this->scrubString($value);
            } elseif (is_scalar($value) || $value === null) {
                $clean[$key] = $value;
            }
            // Objects, closures and resources are never serialised into an audit line.
        }

        return $clean;
    }

    private function scrubString(string $value): string
    {
        $value = preg_replace_callback('~https?://[^\s"\'<>]+~i', function (array $m): string {
            $parts = parse_url($m[0]);

            return isset($parts['scheme'], $parts['host']) ? $parts['scheme'].'://'.$parts['host'].'/...' : '[url]';
        }, $value) ?? $value;

        return mb_strlen($value) > self::MAX_STRING ? mb_substr($value, 0, self::MAX_STRING).'…' : $value;
    }

    /** @param  resource  $handle  locked */
    private function alreadyRecorded($handle, string $event, string $runId): bool
    {
        rewind($handle);

        while (($line = fgets($handle)) !== false) {
            $entry = json_decode($line, true);

            if (is_array($entry) && ($entry['event'] ?? null) === $event && ($entry['run_id'] ?? null) === $runId) {
                return true;
            }
        }

        return false;
    }
}
