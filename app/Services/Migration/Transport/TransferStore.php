<?php

namespace App\Services\Migration\Transport;

use App\Services\Migration\MigrationPaths;
use App\Support\AtomicJsonFile;
use Illuminate\Support\Carbon;

/**
 * Reads and writes the state of package downloads: one directory per transfer under storage/app/migration/transfers, holding
 * state.json (written atomically, a torn write falls back to the previous copy) and the data.part being downloaded.
 */
final class TransferStore
{
    private const ID_PATTERN = '/^[a-z0-9]{12,40}$/';

    public function __construct(private readonly ?string $root = null) {}

    public static function validId(string $id): bool
    {
        return preg_match(self::ID_PATTERN, $id) === 1;
    }

    public function root(): string
    {
        return $this->root ?? MigrationPaths::root().DIRECTORY_SEPARATOR.'transfers';
    }

    /** @param  array<string, mixed>  $fields */
    public function create(array $fields = []): TransferState
    {
        $id = strtolower(bin2hex(random_bytes(8)));
        $now = Carbon::now()->toIso8601String();
        $state = TransferState::fromArray($fields + ['id' => $id, 'status' => TransferStatus::Queued->value, 'created_at' => $now, 'updated_at' => $now]);

        MigrationPaths::ensureDirectory($this->root());
        MigrationPaths::ensureDirectory($this->directory($id));
        $this->save($state);

        return $state;
    }

    public function directory(string $id): string
    {
        if (! self::validId($id)) {
            throw new \InvalidArgumentException('Not a transfer id.');
        }

        return $this->root().DIRECTORY_SEPARATOR.$id;
    }

    public function partPath(string $id): string
    {
        return $this->directory($id).DIRECTORY_SEPARATOR.'data.part';
    }

    private function file(string $id): AtomicJsonFile
    {
        return new AtomicJsonFile($this->directory($id).DIRECTORY_SEPARATOR.'state.json');
    }

    public function find(string $id): ?TransferState
    {
        if (! self::validId($id) || ! is_dir($this->root().DIRECTORY_SEPARATOR.$id)) {
            return null;
        }

        $data = $this->file($id)->read();

        return $data === null ? null : TransferState::fromArray($data);
    }

    public function save(TransferState $state): void
    {
        $state->updatedAt = Carbon::now()->toIso8601String();
        $this->file($state->id)->write($state->toArray());
    }

    /**
     * Change the state under the file's lock, so a poll and a job cannot overwrite each other.
     *
     * @param  callable(TransferState): void  $change
     */
    public function update(string $id, callable $change): TransferState
    {
        $result = null;

        $this->file($id)->update(function (array $data) use ($change, &$result): array {
            $state = TransferState::fromArray($data);
            $change($state);
            $state->updatedAt = Carbon::now()->toIso8601String();
            $result = $state;

            return $state->toArray();
        });

        return $result instanceof TransferState ? $result : throw new \RuntimeException('The transfer state could not be updated.');
    }

    /** @return list<TransferState> every readable transfer, newest first */
    public function all(): array
    {
        $found = [];

        foreach (glob($this->root().DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR) ?: [] as $directory) {
            try {
                $state = $this->find(basename($directory));
            } catch (\RuntimeException) {
                continue; // one unreadable transfer must not hide the others; it is reported when asked for by id
            }

            if ($state !== null) {
                $found[] = $state;
            }
        }

        usort($found, static fn (TransferState $a, TransferState $b): int => strcmp($b->createdAt, $a->createdAt));

        return $found;
    }

    /**
     * Take the per-transfer lock without waiting; null when another process is driving this transfer.
     *
     * @return resource|null
     */
    public function lock(string $id)
    {
        $handle = fopen($this->directory($id).DIRECTORY_SEPARATOR.'transfer.lock', 'c');

        if ($handle === false) {
            return null;
        }

        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return null;
        }

        return $handle;
    }

    /** @param  resource  $handle */
    public function unlock($handle): void
    {
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    /** Remove the transfer's directory and everything in it (a part file included). */
    public function delete(string $id): void
    {
        $directory = $this->directory($id);

        foreach (glob($directory.DIRECTORY_SEPARATOR.'{,.}*', GLOB_BRACE) ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        @rmdir($directory);
    }

    /** Remove only the downloaded bytes (the state stays, as a record of what happened). */
    public function discardPart(string $id): void
    {
        @unlink($this->partPath($id));
    }
}
