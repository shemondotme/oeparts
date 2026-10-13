<?php

namespace App\Services\Instance;

use App\Support\AtomicJsonFile;

/**
 * The ledger of this installation's identity: where it came from, how its mode changed, the id watermarks
 * of copied data, the order-number baseline, which owner actions were acknowledged and the last readiness
 * result. A file under storage/app/instance (the import replaces tables, so this cannot live in one).
 *
 * It is a record, not a switch: .env always wins, and `drift()` says when the two disagree.
 */
final class InstanceState
{
    private const HISTORY_LIMIT = 200;

    public static function file(): AtomicJsonFile
    {
        return new AtomicJsonFile(storage_path('app/instance/instance.json'));
    }

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'v' => 1,
            'instance_id' => null,
            'source' => null,
            'mode_history' => [],
            'env_history' => [],
            'watermarks' => [],
            'sequence_baseline' => null,
            'acknowledged' => [],
            'last_readiness' => null,
        ];
    }

    /** @return array<string, mixed> */
    public function read(): array
    {
        try {
            $data = self::file()->read();
        } catch (\RuntimeException) {
            return self::defaults(); // an unreadable ledger must never stop the site; .env decides everything that matters
        }

        return array_replace(self::defaults(), $data ?? []);
    }

    /** @param  callable(array<string, mixed>): array<string, mixed>  $mutate */
    public function update(callable $mutate): array
    {
        $this->ensureDirectory();

        return self::file()->update(function (?array $current) use ($mutate): array {
            return $mutate(array_replace(self::defaults(), $current ?? []));
        });
    }

    public function recordEnvChange(string $by, array $changes): void
    {
        $this->update(function (array $state) use ($by, $changes): array {
            $state['env_history'][] = ['at' => now()->toIso8601String(), 'by' => $by, 'changes' => $changes];
            $state['env_history'] = array_slice($state['env_history'], -self::HISTORY_LIMIT);

            if (isset($changes['OE_INSTANCE_ID'])) {
                $state['instance_id'] = $changes['OE_INSTANCE_ID']['to'];
            }

            if (isset($changes['OE_MODE'])) {
                $state['mode_history'][] = [
                    'at' => now()->toIso8601String(), 'by' => $by,
                    'from' => $changes['OE_MODE']['from'] === '' ? InstanceMode::Production->value : $changes['OE_MODE']['from'],
                    'to' => $changes['OE_MODE']['to'] === '' ? InstanceMode::Production->value : $changes['OE_MODE']['to'],
                ];
                $state['mode_history'] = array_slice($state['mode_history'], -self::HISTORY_LIMIT);
            }

            return $state;
        });
    }

    /** The highest id of copied rows in a table: rows above it were created on this copy. */
    public function watermark(string $table): ?int
    {
        $value = $this->read()['watermarks'][$table] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /** @param  array<string, int>  $watermarks */
    public function setWatermarks(array $watermarks): void
    {
        $this->update(function (array $state) use ($watermarks): array {
            $state['watermarks'] = $watermarks;

            return $state;
        });
    }

    public function clearWatermarks(): void
    {
        $this->setWatermarks([]);
    }

    /** The mode the ledger last recorded, or null if it never recorded one. */
    public function recordedMode(): ?string
    {
        $history = $this->read()['mode_history'];
        $last = is_array($history) ? end($history) : false;

        return is_array($last) ? (string) ($last['to'] ?? '') : null;
    }

    /** A sentence when the ledger and .env disagree about the mode, else null. .env wins. */
    public function drift(InstanceMode $actual): ?string
    {
        $recorded = $this->recordedMode();

        if ($recorded === null || $recorded === $actual->value) {
            return null;
        }

        return "The ledger last recorded the mode \"{$recorded}\", but .env says \"{$actual->value}\". .env wins; the ledger is only a record.";
    }

    private function ensureDirectory(): void
    {
        $dir = dirname(self::file()->path());

        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new \RuntimeException("Cannot create the directory {$dir}.");
        }
    }
}
