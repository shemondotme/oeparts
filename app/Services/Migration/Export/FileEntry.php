<?php

namespace App\Services\Migration\Export;

/** One file that passed the allowlist: where it is, how big, when it changed. The path is disk-relative. */
final class FileEntry
{
    public function __construct(
        public readonly string $disk,
        public readonly string $path,
        public readonly int $size,
        public readonly int $mtime,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['disk' => $this->disk, 'path' => $this->path, 'size' => $this->size, 'mtime' => $this->mtime];
    }
}
