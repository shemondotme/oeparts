<?php

namespace App\Services\Backup;

/**
 * What to restore and how (Module 14/21, Chunk 2.5). Supports partial restores:
 * DB-only, files-only, or a single table.
 */
class RestoreOptions
{
    public function __construct(
        public bool $database = true,
        public bool $files = true,
        /** Restore only this one DB table (implies database-only). */
        public ?string $table = null,
        /** Where files are extracted (defaults to storage/app/restore/run-{id}). */
        public ?string $targetRoot = null,
        /** Abort if the backup's app_version is newer than this server's. */
        public bool $strictVersion = false,
    ) {}

    public static function databaseOnly(?string $table = null): self
    {
        return new self(database: true, files: false, table: $table);
    }

    public static function filesOnly(?string $targetRoot = null): self
    {
        return new self(database: false, files: true, targetRoot: $targetRoot);
    }
}
