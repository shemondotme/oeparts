<?php

namespace App\Services\Migration\Import;

use App\Services\Migration\MigrationPaths;
use App\Support\AtomicJsonFile;

/**
 * Reads and writes the state files of import runs. One directory per run. A write goes through AtomicJsonFile: a torn
 * write falls back to the previous version, two writers cannot lose each other's change, and a file that exists but
 * cannot be read is an error, never "no run" (a state machine that mistakes corrupt for empty would start over).
 */
final class ImportStateStore
{
    /** A run id is generated here and never taken from a request path unchecked: this is the check. */
    private const ID_PATTERN = '/^[a-z0-9]{12,40}$/';

    public function __construct(private readonly ?string $root = null) {}

    public static function validId(string $id): bool
    {
        return preg_match(self::ID_PATTERN, $id) === 1;
    }

    /** @param  array<string, mixed>  $fields  initial values for ImportState */
    public function create(string $kind = 'import', array $fields = []): ImportState
    {
        $id = strtolower(bin2hex(random_bytes(8)));
        $state = ImportState::fromArray(['id' => $id, 'kind' => $kind] + $fields);

        MigrationPaths::ensureDirectory($this->runs());
        MigrationPaths::ensureDirectory($this->directory($id));
        $this->save($state);

        return $state;
    }

    public function directory(string $id): string
    {
        if (! self::validId($id)) {
            throw new \InvalidArgumentException('Not an import run id.');
        }

        return $this->runs().DIRECTORY_SEPARATOR.$id;
    }

    public function file(string $id): AtomicJsonFile
    {
        return new AtomicJsonFile($this->directory($id).DIRECTORY_SEPARATOR.'state.json');
    }

    /** @return ImportState|null null only when no such run exists at all */
    public function find(string $id): ?ImportState
    {
        if (! self::validId($id) || ! is_dir($this->runs().DIRECTORY_SEPARATOR.$id)) {
            return null;
        }

        $data = $this->file($id)->read();

        return $data === null ? null : ImportState::fromArray($data);
    }

    public function save(ImportState $state): void
    {
        $this->file($state->id)->write($state->toArray());
    }

    /**
     * Read, change and write under the file's lock, so a poll and a watchdog cannot overwrite each other.
     *
     * @param  callable(ImportState): void  $change
     */
    public function update(string $id, callable $change): ImportState
    {
        if (! is_file($this->file($id)->path())) {
            throw new \RuntimeException("There is no import run {$id} to update.");
        }

        $result = null;

        $this->file($id)->update(function (array $data) use ($change, &$result): array {
            $state = ImportState::fromArray($data);
            $change($state);
            $result = $state;

            return $state->toArray();
        });

        return $result instanceof ImportState ? $result : throw new \RuntimeException('The run state could not be updated.');
    }

    /** @return array<int, ImportState> every readable run, newest first */
    public function all(): array
    {
        $runs = [];

        foreach (glob($this->runs().DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR) ?: [] as $directory) {
            try {
                $state = $this->find(basename($directory));
            } catch (\RuntimeException) {
                continue; // an unreadable run is reported by whoever asks for it by id; it must not hide the others
            }

            if ($state !== null) {
                $runs[] = $state;
            }
        }

        usort($runs, static fn (ImportState $a, ImportState $b): int => strcmp($b->createdAt, $a->createdAt));

        return $runs;
    }

    /** The run that may still own the site, if any (not terminal). */
    public function active(): ?ImportState
    {
        foreach ($this->all() as $state) {
            if ($state->status->mayHoldTheSite()) {
                return $state;
            }
        }

        return null;
    }

    private function runs(): string
    {
        return $this->root ?? MigrationPaths::runs();
    }
}
